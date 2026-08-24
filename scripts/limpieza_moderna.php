<?php
/**
 * Limpieza TOTAL del lado Moderna antes de importar la nómina real.
 *
 * Borra:
 *  - todos los usuarios con role='empleado' de las empresas del grupo
 *    organizacional 'moderna' (los 8 demos), con TODAS sus filas dependientes
 *    (recorre las FK hacia users vía information_schema, más las tablas sin FK
 *    que tengan columna user_id);
 *  - todos los bloques de employee_schedules registrados en sucursales de
 *    Moderna (restos de pruebas manuales, sin importar de qué usuario).
 *
 * NO toca: cuentas staff (admin/rrhh/supervisor, p. ej. axel.moderna) ni nada
 * de Paviotti.
 *
 * Uso (local y luego en el VPS):
 *   php scripts/limpieza_moderna.php            → dry-run (solo muestra)
 *   php scripts/limpieza_moderna.php --ejecutar → borra de verdad
 *
 * Requiere app/config/config.php (o definir DB_* antes).
 */

if (php_sapi_name() !== 'cli') {
    die("Solo CLI.\n");
}
$root = dirname(__DIR__);
require $root . '/app/config/config.php';
if (file_exists($root . '/app/config/config.local.php')) {
    require $root . '/app/config/config.local.php';
}
require $root . '/app/models/Database.php';

$ejecutar = in_array('--ejecutar', $argv, true);
$db = new Database();

// 1) Empresas del grupo moderna
$db->query("SELECT id, name FROM companies WHERE organization_group = 'moderna'");
$companies = $db->resultSet();
if (empty($companies)) {
    die("No hay empresas del grupo 'moderna'. Nada para limpiar.\n");
}
$companyIds = array_map(fn($c) => (int)$c->id, $companies);
$phC = implode(',', array_fill(0, count($companyIds), '?'));
echo "Empresas Moderna: " . implode(', ', array_map(fn($c) => "{$c->name} (#{$c->id})", $companies)) . "\n";

// 2) Empleados a borrar (solo role empleado — el staff queda intacto)
$db->query("SELECT id, username, full_name FROM users WHERE role = 'empleado' AND company_id IN ($phC)");
$users = $db->resultSet($companyIds);
$userIds = array_map(fn($u) => (int)$u->id, $users);
echo "\nEmpleados a eliminar (" . count($users) . "):\n";
foreach ($users as $u) {
    echo "  #{$u->id} {$u->username} — {$u->full_name}\n";
}

// 3) Sucursales Moderna (para el borrado de horarios residuales)
$db->query("SELECT name FROM company_branches WHERE company_id IN ($phC)");
$branchNames = array_map(fn($b) => $b->name, $db->resultSet($companyIds));
echo "\nSucursales Moderna: " . count($branchNames) . "\n";

// 4) Tablas que referencian users (FK) + tablas con columna user_id sin FK
$db->query(
    "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
     WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'users'"
);
$fkRefs = [];
foreach ($db->resultSet() as $r) {
    $fkRefs[$r->TABLE_NAME . '.' . $r->COLUMN_NAME] = ['t' => $r->TABLE_NAME, 'c' => $r->COLUMN_NAME];
}
$db->query(
    "SELECT TABLE_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'user_id' AND TABLE_NAME <> 'users'"
);
foreach ($db->resultSet() as $r) {
    $fkRefs[$r->TABLE_NAME . '.user_id'] = ['t' => $r->TABLE_NAME, 'c' => 'user_id'];
}
echo "Tablas dependientes detectadas: " . count($fkRefs) . "\n";

// 5) Conteos del plan
$total = 0;
if (!empty($userIds)) {
    $phU = implode(',', array_fill(0, count($userIds), '?'));
    foreach ($fkRefs as $ref) {
        try {
            $db->query("SELECT COUNT(*) n FROM `{$ref['t']}` WHERE `{$ref['c']}` IN ($phU)");
            $n = (int)$db->single($userIds)->n;
        } catch (Throwable $e) {
            $n = 0;
        }
        if ($n > 0) {
            echo "  {$ref['t']}.{$ref['c']}: $n fila(s)\n";
            $total += $n;
        }
    }
}
$residual = 0;
if (!empty($branchNames)) {
    $phB = implode(',', array_fill(0, count($branchNames), '?'));
    $extraParams = $branchNames;
    $sqlResidual = "FROM employee_schedules WHERE branch_name IN ($phB)";
    if (!empty($userIds)) {
        $sqlResidual .= " AND user_id NOT IN (" . implode(',', array_map('intval', $userIds)) . ")";
    }
    $db->query("SELECT COUNT(*) n $sqlResidual");
    $residual = (int)$db->single($extraParams)->n;
}
echo "\nTotal filas dependientes: $total · horarios residuales en sucursales Moderna: $residual\n";

if (!$ejecutar) {
    die("\nDRY-RUN: no se borró nada. Ejecutá con --ejecutar para aplicar.\n");
}

// 6) Ejecución: dependientes (con reintentos por FK anidadas) → users → residuales
$db->beginTransaction();
try {
    if (!empty($userIds)) {
        $phU = implode(',', array_fill(0, count($userIds), '?'));
        $pendientes = array_values($fkRefs);
        for ($pass = 1; $pass <= 5 && !empty($pendientes); $pass++) {
            $fallidas = [];
            foreach ($pendientes as $ref) {
                try {
                    $db->query("DELETE FROM `{$ref['t']}` WHERE `{$ref['c']}` IN ($phU)");
                    $db->execute($userIds);
                } catch (Throwable $e) {
                    $fallidas[] = $ref; // FK de nietos: reintentar en la próxima pasada
                }
            }
            $pendientes = $fallidas;
        }
        if (!empty($pendientes)) {
            throw new RuntimeException('No se pudieron vaciar: ' . implode(', ', array_map(fn($r) => $r['t'], $pendientes)));
        }
        $db->query("DELETE FROM users WHERE id IN ($phU) AND role = 'empleado'");
        $db->execute($userIds);
        echo "Usuarios eliminados: " . $db->rowCount() . "\n";
    }
    if (!empty($branchNames)) {
        $phB = implode(',', array_fill(0, count($branchNames), '?'));
        $db->query("DELETE FROM employee_schedules WHERE branch_name IN ($phB)");
        $db->execute($branchNames);
        echo "Horarios residuales eliminados: " . $db->rowCount() . "\n";
    }
    $db->commit();
    echo "\nLimpieza Moderna COMPLETA.\n";
} catch (Throwable $e) {
    $db->rollBack();
    die("ERROR — rollback total: " . $e->getMessage() . "\n");
}
