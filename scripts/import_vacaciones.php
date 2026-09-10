<?php
/**
 * Importa el informe de vacaciones de RRHH Moderna (JSON generado por
 * scripts/convertir_vacaciones_xls.py) al módulo de vacaciones v2.
 *
 * Por cada colaborador (resuelto por LEGAJO en employee_company_assignments
 * del grupo moderna):
 *  - vacation_balance_periods: período anual (label del informe, p. ej. 2025)
 *    con days_entitled (asignados), adjustment_days (saldo inicial de arrastre),
 *    days_taken y days_pending (saldo del informe). El snapshot referencia el
 *    CCT 430/05 Farmacia Córdoba (code FARMACIA-430-05, la escala del informe,
 *    antigüedad desde la FECHA DE INGRESO) sin asociar el convenio al usuario.
 *  - vacation_balance_movements: accrual + opening_balance (si hay arrastre) +
 *    un take por período tomado, todos con operation_key idempotente
 *    (re-ejecutar no duplica).
 *  - employee_schedules: cada día de cada período tomado se materializa como
 *    bloque type='vacation' (los futuros BLOQUEAN la carga de horas). No
 *    duplica si el día ya tiene bloque de vacaciones.
 *  - users.vacation_days_available = saldo final (compatibilidad v1).
 *
 * Uso (local y luego en el VPS):
 *   php scripts/import_vacaciones.php vacaciones_2025.json            → dry-run
 *   php scripts/import_vacaciones.php vacaciones_2025.json --ejecutar
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

$args = array_values(array_filter(array_slice($argv, 1), fn($a) => strpos($a, '--') !== 0));
$jsonPath = $args[0] ?? '';
$ejecutar = in_array('--ejecutar', $argv, true);
if ($jsonPath === '' || !file_exists($jsonPath)) {
    die("Uso: php scripts/import_vacaciones.php <vacaciones.json> [--ejecutar]\n");
}
$datos = json_decode(file_get_contents($jsonPath), true);
$empleados = $datos['empleados'] ?? [];
if (empty($empleados)) {
    die("El JSON no tiene empleados.\n");
}

$db = new Database();

// CCT 430/05 Empleados de Farmacia Córdoba, resuelto por su código único:
// el id numérico varía según el entorno (en el VPS el catálogo base solo
// trae el CEC, la escala de Farmacia la siembra aplicar_pendientes_vps.php).
$db->query("SELECT id FROM collective_agreements WHERE code = 'FARMACIA-430-05'");
$agreementFarmacia = (int)($db->single()->id ?? 0);
if ($agreementFarmacia <= 0) {
    die("No existe el CCT 430/05 (code FARMACIA-430-05) en collective_agreements.\nAplicalo con: php scripts/aplicar_pendientes_vps.php\n");
}
define('AGREEMENT_FARMACIA', $agreementFarmacia);

// Actor de los movimientos: primer admin del sistema.
$db->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
$actorId = (int)($db->single()->id ?? 0);
if ($actorId <= 0) {
    die("No hay usuario admin para registrar los movimientos.\n");
}

function rangoDias($desde, $hasta) {
    $out = [];
    $cursor = $desde;
    while ($cursor <= $hasta) {
        $out[] = $cursor;
        $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
    }
    return $out;
}

// ── Plan ──
$plan = [];
$errores = [];
foreach ($empleados as $e) {
    $legajo = ltrim((string)$e['legajo'], '0');
    $db->query(
        "SELECT u.id, u.full_name, u.hire_date
         FROM employee_company_assignments eca
         JOIN users u ON u.id = eca.user_id
         WHERE eca.employee_number = ? AND u.employee_group = 'moderna' AND u.role = 'empleado'
         LIMIT 1"
    );
    $u = $db->single([$legajo]);
    if (!$u) {
        $errores[] = "legajo {$legajo} ({$e['nombre']}): no existe en la nómina importada.";
        continue;
    }
    // Coherencia de fecha de ingreso informe vs sistema (aviso, no bloqueante).
    $aviso = ($u->hire_date && $u->hire_date !== $e['ingreso'])
        ? " AVISO: ingreso informe {$e['ingreso']} ≠ sistema {$u->hire_date}"
        : '';

    $anio = (int)($e['periodo'] ?? date('Y'));
    $periodStart = sprintf('%04d-01-01', $anio);
    $periodEnd = sprintf('%04d-12-31', $anio);

    // Regla del convenio según antigüedad (desde fecha de INGRESO) al cierre del período.
    $meses = 0;
    if ($u->hire_date) {
        $d1 = new DateTime($u->hire_date);
        $d2 = new DateTime($periodEnd);
        $diff = $d1->diff($d2);
        $meses = $diff->y * 12 + $diff->m;
    }
    $db->query(
        'SELECT id FROM collective_agreement_rules
         WHERE agreement_id = ? AND min_months <= ? AND (max_months IS NULL OR max_months >= ?)
         ORDER BY min_months DESC LIMIT 1'
    );
    $rule = $db->single([AGREEMENT_FARMACIA, $meses, $meses]);

    $tomados = [];
    $totalTomado = 0.0;
    foreach ($e['tomados'] as $t) {
        $dias = rangoDias($t['desde'], $t['hasta']);
        if (count($dias) !== (int)$t['dias']) {
            $aviso .= " AVISO: {$t['desde']}–{$t['hasta']} tiene " . count($dias) . " días corridos pero el informe dice {$t['dias']}.";
        }
        $tomados[] = ['desde' => $t['desde'], 'hasta' => $t['hasta'], 'dias' => (float)$t['dias'], 'fechas' => $dias];
        $totalTomado += (float)$t['dias'];
    }

    $plan[] = [
        'user_id'      => (int)$u->id,
        'nombre'       => $u->full_name,
        'legajo'       => $legajo,
        'anio'         => $anio,
        'period_start' => $periodStart,
        'period_end'   => $periodEnd,
        'rule_id'      => $rule ? (int)$rule->id : null,
        'entitled'     => (float)$e['asignados'],
        'inicial'      => (float)$e['saldo_inicial'],
        'tomado'       => $totalTomado,
        'saldo'        => (float)$e['saldo_final'],
        'tomados'      => $tomados,
        'aviso'        => trim($aviso),
    ];
}

foreach ($plan as $p) {
    echo sprintf(
        "  legajo %-4s %-42s período %d: %s asignados%s · %s tomados en %d tramo(s) · saldo %s%s\n",
        $p['legajo'], $p['nombre'], $p['anio'],
        rtrim(rtrim(number_format($p['entitled'], 1), '0'), '.'),
        $p['inicial'] > 0 ? ' (+' . rtrim(rtrim(number_format($p['inicial'], 1), '0'), '.') . ' arrastre)' : '',
        rtrim(rtrim(number_format($p['tomado'], 1), '0'), '.'), count($p['tomados']),
        rtrim(rtrim(number_format($p['saldo'], 1), '0'), '.'),
        $p['aviso'] !== '' ? "\n      " . $p['aviso'] : ''
    );
    foreach ($p['tomados'] as $t) {
        echo "      {$t['desde']} → {$t['hasta']} ({$t['dias']} días)\n";
    }
}
if (!empty($errores)) {
    echo "\nERRORES:\n  " . implode("\n  ", $errores) . "\n";
    die("Corregí los errores antes de ejecutar.\n");
}
if (!$ejecutar) {
    die("\nDRY-RUN: nada se escribió. Ejecutá con --ejecutar para importar.\n");
}

// ── Ejecución ──
$db->beginTransaction();
$omitidos = 0;
try {
    foreach ($plan as $p) {
        // Período (upsert por user + label)
        $db->query('SELECT id FROM vacation_balance_periods WHERE user_id = ? AND period_label = ? LIMIT 1');
        $per = $db->single([$p['user_id'], (string)$p['anio']]);
        if ($per) {
            $periodId = (int)$per->id;
            // Si el sistema ya operó sobre ese período (solicitudes aprobadas,
            // liquidación del motor), el informe NO lo pisa: se revisa a mano.
            $db->query("SELECT COUNT(*) AS c FROM vacation_balance_movements WHERE period_id = ? AND source <> 'import'");
            $ajenos = (int)($db->single([$periodId])->c ?? 0);
            if ($ajenos > 0) {
                echo "  OMITIDO usuario {$p['user_id']} período {$p['anio']}: tiene {$ajenos} movimiento(s) del sistema; no se pisa.\n";
                $omitidos++;
                continue;
            }
            $db->query(
                "UPDATE vacation_balance_periods
                 SET days_entitled=?, adjustment_days=?, days_taken=?, days_pending=?, origin_notes=?
                 WHERE id=?"
            );
            $db->execute([
                $p['entitled'], $p['inicial'], $p['tomado'], $p['saldo'],
                'Importado del informe de vacaciones RRHH (período ' . $p['anio'] . ')',
                $periodId,
            ]);
        } else {
            $db->query(
                "INSERT INTO vacation_balance_periods
                    (user_id, period_label, period_start, period_end, balance_type, agreement_id, agreement_rule_id,
                     count_mode_snapshot, days_entitled, days_taken, adjustment_days, days_pending, status, origin_notes)
                 VALUES (?, ?, ?, ?, 'annual', ?, ?, 'calendar', ?, ?, ?, ?, 'open', ?)"
            );
            $db->execute([
                $p['user_id'], (string)$p['anio'], $p['period_start'], $p['period_end'],
                AGREEMENT_FARMACIA, $p['rule_id'],
                $p['entitled'], $p['tomado'], $p['inicial'], $p['saldo'],
                'Importado del informe de vacaciones RRHH (período ' . $p['anio'] . ')',
            ]);
            $periodId = (int)$db->lastInsertId();
        }

        // Movimientos idempotentes (operation_key única)
        $mov = function ($tipo, $dias, $clave, $notas, $fechas = null) use ($db, $periodId, $p, $actorId) {
            $db->query(
                "INSERT IGNORE INTO vacation_balance_movements
                    (period_id, user_id, movement_type, source, days, operation_key, schedule_dates, notes, created_by)
                 VALUES (?, ?, ?, 'import', ?, ?, ?, ?, ?)"
            );
            $db->execute([
                $periodId, $p['user_id'], $tipo, $dias, $clave,
                $fechas !== null ? json_encode($fechas) : null,
                $notas, $actorId,
            ]);
        };
        $mov('accrual', $p['entitled'], "imp{$p['anio']}:acc:{$p['user_id']}", 'Vacaciones Período ' . $p['anio'] . ' (informe RRHH)');
        if ($p['inicial'] > 0) {
            $mov('opening_balance', $p['inicial'], "imp{$p['anio']}:ini:{$p['user_id']}", 'Saldo inicial de períodos previos (informe RRHH)');
        }
        foreach ($p['tomados'] as $t) {
            $mov('take', $t['dias'], "imp{$p['anio']}:take:{$p['user_id']}:{$t['desde']}",
                'Vacaciones ' . date('d/m/Y', strtotime($t['desde'])) . ' – ' . date('d/m/Y', strtotime($t['hasta'])) . ' (informe RRHH)',
                $t['fechas']);

            // Bloques en el calendario (sin duplicar días ya bloqueados)
            foreach ($t['fechas'] as $f) {
                $db->query("SELECT id FROM employee_schedules WHERE user_id = ? AND schedule_date = ? AND type = 'vacation' LIMIT 1");
                $ya = $db->single([$p['user_id'], $f]);
                if (!$ya) {
                    $db->query(
                        "INSERT INTO employee_schedules (user_id, schedule_date, shift_id, start_time, end_time, type, notes)
                         VALUES (?, ?, NULL, NULL, NULL, 'vacation', ?)"
                    );
                    $db->execute([$p['user_id'], $f, 'Vacaciones período ' . $p['anio'] . ' (importado)']);
                }
            }
        }

        // Compatibilidad v1
        $db->query('UPDATE users SET vacation_days_available = ? WHERE id = ?');
        $db->execute([$p['saldo'], $p['user_id']]);
    }
    $db->commit();
    echo "\nIMPORTACIÓN DE VACACIONES COMPLETA: " . (count($plan) - $omitidos) . " colaborador(es)"
        . ($omitidos > 0 ? ", {$omitidos} omitido(s) por tener movimientos del sistema" : '') . ".\n";
} catch (Throwable $e) {
    $db->rollBack();
    die('ERROR — rollback total: ' . $e->getMessage() . "\n");
}
