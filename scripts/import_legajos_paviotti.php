<?php
/**
 * Importa legajos Paviotti desde Excel (hoja Movimientos / Listado de Legajos).
 *
 * - Solo crea usuarios que aún no existan (match por DNI, username=DNI, legajo o nombre).
 * - username = DNI (solo dígitos) y contraseña = DNI.
 * - role=empleado, employee_group=paviotti.
 * - Omite filas con fecha de egreso.
 *
 * Uso:
 *   php scripts/import_legajos_paviotti.php "/ruta/LEGAJOS.xlsx"            → dry-run
 *   php scripts/import_legajos_paviotti.php "/ruta/LEGAJOS.xlsx" --ejecutar
 */

if (php_sapi_name() !== 'cli') {
    die("Solo CLI.\n");
}

$root = dirname(__DIR__);
require $root . '/app/config/config.php';
require $root . '/app/models/Database.php';

$args = array_values(array_filter(array_slice($argv, 1), static fn($a) => strpos($a, '--') !== 0));
$xlsxPath = $args[0] ?? '';
$ejecutar = in_array('--ejecutar', $argv, true);

if ($xlsxPath === '' || !is_file($xlsxPath)) {
    die("Uso: php scripts/import_legajos_paviotti.php <legajos.xlsx> [--ejecutar]\n");
}

function leerXlsx(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        die("No se pudo abrir el xlsx.\n");
    }
    $ss = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        foreach (simplexml_load_string($ssXml)->si as $si) {
            $t = '';
            if (isset($si->t)) {
                $t = (string)$si->t;
            } else {
                foreach ($si->r as $r) {
                    $t .= (string)$r->t;
                }
            }
            $ss[] = $t;
        }
    }
    $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            preg_match('/^([A-Z]+)/', (string)$c['r'], $m);
            $v = isset($c->v) ? (string)$c->v : (isset($c->is->t) ? (string)$c->is->t : '');
            if ((string)$c['t'] === 's') {
                $v = $ss[(int)$v] ?? $v;
            }
            $cells[$m[1]] = trim($v);
        }
        $rows[] = $cells;
    }
    return $rows;
}

function serialAFecha($v): ?string
{
    $v = trim((string)$v);
    if ($v === '' || !is_numeric($v)) {
        return null;
    }
    return gmdate('Y-m-d', ((int)$v - 25569) * 86400);
}

function normName(string $s): string
{
    $s = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $s)));
    return strtr($s, [
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
        'Ñ' => 'N', 'Ü' => 'U',
    ]);
}

function nameTokens(string $s): array
{
    $tokens = preg_split('/\s+/', normName($s)) ?: [];
    return array_values(array_filter($tokens, static fn($t) => mb_strlen($t) > 2));
}

function namesLikelySame(string $a, string $b): bool
{
    $ta = nameTokens($a);
    $tb = nameTokens($b);
    if ($ta === [] || $tb === []) {
        return false;
    }
    $inter = count(array_intersect($ta, $tb));
    $need = min(2, min(count($ta), count($tb)));
    return $inter >= $need;
}

function resolverEmpresaId(string $razon, array $companies): ?int
{
    $clave = mb_strtoupper(preg_replace('/[^A-Za-z]/', '', $razon));
    $map = [
        'LANATURALEZA' => 'La Naturaleza',
        'CASAPAVIOTTI' => 'Casa Paviotti',
        'SERVICIOSSOCIALESPAVIOTTI' => 'Servicios Sociales',
        'SERVICIOSSOCIALES' => 'Servicios Sociales',
        'ECOFARMSRL' => 'Ecofarma',
        'ECOFARM' => 'Ecofarma',
        'AMSSI' => 'A.M.S.S.I',
        'CRUZVERDESALUD' => 'Ecofarma',
        'CHARMYSAS' => 'Ecofarma',
        'MARENGOSILVIADELCARMEN' => 'Ecofarma',
        'HIJOSDEJUANALLADIO' => 'Casa Paviotti',
    ];
    foreach ($map as $needle => $companyName) {
        if (strpos($clave, $needle) === 0 || strpos($clave, $needle) !== false) {
            return $companies[$companyName] ?? null;
        }
    }
    foreach ($companies as $name => $id) {
        $n = mb_strtoupper(preg_replace('/[^A-Za-z]/', '', $name));
        if ($n !== '' && (strpos($clave, $n) !== false || strpos($n, substr($clave, 0, 6)) === 0)) {
            return $id;
        }
    }
    return null;
}

function resolverBranchId(string $seccion, int $companyId, array $branchesByCompany): ?int
{
    if ($companyId !== 5 || empty($branchesByCompany[$companyId])) {
        return null;
    }
    $s = mb_strtolower($seccion);
    $rules = [
        'dermolife' => 'Dermolife',
        'catedral' => 'Catedral',
        'jujuy' => 'Jujuy',
        '9 de julio' => 'Cruz Verde Central',
        'cruz verde' => 'Cruz Verde Central',
        'administraci' => 'Central',
        'ecofarma' => 'Central',
    ];
    foreach ($rules as $needle => $branchNeedle) {
        if (strpos($s, $needle) === false) {
            continue;
        }
        foreach ($branchesByCompany[$companyId] as $b) {
            if (stripos($b->name, $branchNeedle) !== false) {
                return (int)$b->id;
            }
        }
    }
    foreach ($branchesByCompany[$companyId] as $b) {
        if (stripos($b->name, 'Central') !== false) {
            return (int)$b->id;
        }
    }
    return (int)$branchesByCompany[$companyId][0]->id;
}

function cargoNombre(string $raw): ?string
{
    $raw = trim(preg_replace('/\s+/', ' ', $raw));
    if ($raw === '' || ctype_digit($raw)) {
        return null;
    }
    return $raw;
}

$rows = leerXlsx($xlsxPath);
// Saltar título PAVIOTTI + "Listado de Legajos." + encabezado
while ($rows && !isset($rows[0]['A'])) {
    array_shift($rows);
}
if ($rows && mb_strtolower(trim($rows[0]['A'] ?? '')) !== 'legajo') {
    // filas 0-1 título, fila 2 header en este archivo
    if (isset($rows[2]) && mb_strtolower(trim($rows[2]['A'] ?? '')) === 'legajo') {
        array_shift($rows);
        array_shift($rows);
    }
}
$header = array_shift($rows);
if (mb_strtolower(trim($header['A'] ?? '')) !== 'legajo') {
    die("No se encontró la fila de encabezado (Legajo).\n");
}

$db = new Database();
$db->query("SELECT id, name FROM companies WHERE organization_group = 'paviotti'");
$companies = [];
foreach ($db->resultSet() as $c) {
    $companies[$c->name] = (int)$c->id;
}
if ($companies === []) {
    die("No hay empresas organization_group=paviotti.\n");
}

$db->query('SELECT id, company_id, name FROM company_branches WHERE is_active = 1 ORDER BY company_id, name');
$branchesByCompany = [];
foreach ($db->resultSet() as $b) {
    $branchesByCompany[(int)$b->company_id][] = $b;
}

$db->query(
    'SELECT u.id, u.username, u.document_number, u.full_name, u.company_id, u.is_active,
            eca.employee_number
     FROM users u
     LEFT JOIN employee_company_assignments eca ON eca.user_id = u.id AND eca.is_primary = 1'
);
$byDoc = [];
$byUser = [];
$byLeg = [];
$users = [];
foreach ($db->resultSet() as $u) {
    $users[] = $u;
    $d = preg_replace('/\D+/', '', (string)$u->document_number);
    if ($d !== '') {
        $byDoc[$d] = $u;
    }
    $byUser[trim((string)$u->username)] = $u;
    if ($u->employee_number !== null && trim((string)$u->employee_number) !== '') {
        $byLeg[trim((string)$u->employee_number)] = $u;
    }
    if (preg_match('/^\d+$/', (string)$u->username)) {
        $byLeg[(string)$u->username] = $byLeg[(string)$u->username] ?? $u;
    }
}

function findExisting(array $ctx, string $dni, string $legajo, string $nombre)
{
    if (isset($ctx['byDoc'][$dni])) {
        return [$ctx['byDoc'][$dni], 'dni'];
    }
    if (isset($ctx['byUser'][$dni])) {
        return [$ctx['byUser'][$dni], 'username=dni'];
    }
    if (isset($ctx['byLeg'][$legajo])) {
        return [$ctx['byLeg'][$legajo], 'legajo'];
    }
    foreach ($ctx['users'] as $u) {
        if (namesLikelySame($nombre, (string)$u->full_name)) {
            return [$u, 'nombre'];
        }
    }
    return [null, ''];
}

$ctx = compact('byDoc', 'byUser', 'byLeg', 'users');
$plan = [];
$omitidos = [];
$errores = [];

foreach ($rows as $i => $r) {
    $fila = $i + 4; // approx Excel row (títulos + header)
    $legajo = trim($r['A'] ?? '');
    $nombre = trim(preg_replace('/\s+/', ' ', $r['B'] ?? ''));
    if ($legajo === '' || $nombre === '') {
        continue;
    }
    if (trim($r['O'] ?? '') !== '') {
        $omitidos[] = "fila $fila ($nombre): egreso — omitido";
        continue;
    }
    $dni = preg_replace('/\D+/', '', $r['H'] ?? '');
    if (strlen($dni) < 7) {
        $errores[] = "fila $fila ($nombre): DNI inválido '{$r['H']}'";
        continue;
    }
    [$existente, $how] = findExisting($ctx, $dni, $legajo, $nombre);
    if ($existente) {
        $omitidos[] = sprintf(
            'fila %d (%s): ya existe #%d @%s (%s) — no se crea',
            $fila,
            $nombre,
            (int)$existente->id,
            $existente->username,
            $how
        );
        continue;
    }
    $razon = trim($r['T'] ?? '');
    $companyId = resolverEmpresaId($razon, $companies);
    if ($companyId === null) {
        $errores[] = "fila $fila ($nombre): razón social '$razon' sin empresa Paviotti";
        continue;
    }
    $seccion = trim($r['R'] ?? '');
    $branchId = resolverBranchId($seccion, $companyId, $branchesByCompany);
    $plan[] = [
        'fila' => $fila,
        'legajo' => $legajo,
        'username' => $dni,
        'password' => $dni,
        'full_name' => $nombre,
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'email' => trim($r['V'] ?? '') ?: null,
        'phone' => trim($r['U'] ?? '') ?: null,
        'dni' => $dni,
        'cuil' => trim($r['I'] ?? '') ?: null,
        'birth' => serialAFecha($r['J'] ?? ''),
        'marital' => trim($r['K'] ?? '') ?: null,
        'children' => ($r['W'] ?? '') !== '' ? (int)$r['W'] : null,
        'cargo' => cargoNombre($r['L'] ?? ''),
        'ingreso' => serialAFecha($r['M'] ?? ''),
        'recibo' => serialAFecha($r['N'] ?? ''),
        'area' => $seccion !== '' ? $seccion : null,
        'calle' => trim($r['C'] ?? '') ?: null,
        'altura' => trim($r['D'] ?? '') ?: null,
        'cp' => trim($r['E'] ?? '') ?: null,
        'localidad' => trim($r['F'] ?? '') ?: null,
        'provincia' => trim($r['G'] ?? '') ?: null,
        'obra_social' => trim($r['Y'] ?? '') ?: null,
        'razon' => $razon,
    ];
}

echo 'Excel: ' . count($rows) . " filas de datos\n";
echo 'A crear: ' . count($plan) . "\n";
echo 'Omitidos (ya existen / egreso): ' . count($omitidos) . "\n";
if ($errores) {
    echo "\nERRORES:\n  " . implode("\n  ", $errores) . "\n";
    die("Corregí los errores antes de ejecutar.\n");
}

$byCo = [];
foreach ($plan as $p) {
    $byCo[$p['company_id']][] = $p;
}
$idToName = array_flip($companies);
foreach ($byCo as $cid => $list) {
    echo "\n== " . ($idToName[$cid] ?? "empresa #$cid") . ' (' . count($list) . ") ==\n";
    foreach ($list as $p) {
        echo sprintf(
            "  L%-4s  user/pass=%-10s  %-42s  branch=%s  cargo=%s\n",
            $p['legajo'],
            $p['username'],
            $p['full_name'],
            $p['branch_id'] ?? '—',
            $p['cargo'] ?? '—'
        );
    }
}

if (!$ejecutar) {
    echo "\nDRY-RUN: nada escrito. Reejecutá con --ejecutar para crear " . count($plan) . " usuario(s).\n";
    exit(0);
}

$db->beginTransaction();
try {
    $creados = 0;
    foreach ($plan as $p) {
        $areaId = null;
        if ($p['area'] !== null) {
            $db->query('SELECT id FROM areas WHERE name = ? LIMIT 1');
            $row = $db->single([$p['area']]);
            if (!$row) {
                $db->query('INSERT INTO areas (company_id, name, is_active) VALUES (?, ?, 1)');
                $db->execute([$p['company_id'], $p['area']]);
                $areaId = (int)$db->lastInsertId();
            } else {
                $areaId = (int)$row->id;
            }
        }

        $positionId = null;
        if ($p['cargo'] !== null) {
            $db->query('SELECT id FROM job_positions WHERE company_id = ? AND name = ? LIMIT 1');
            $row = $db->single([$p['company_id'], $p['cargo']]);
            if (!$row) {
                $db->query('INSERT INTO job_positions (company_id, name, is_active) VALUES (?, ?, 1)');
                $db->execute([$p['company_id'], $p['cargo']]);
                $positionId = (int)$db->lastInsertId();
            } else {
                $positionId = (int)$row->id;
            }
        }

        $insurerId = null;
        if ($p['obra_social'] !== null) {
            $db->query('SELECT id FROM health_insurers WHERE display_name = ? OR legal_name = ? LIMIT 1');
            $row = $db->single([$p['obra_social'], $p['obra_social']]);
            if (!$row) {
                $db->query("INSERT INTO health_insurers (legal_name, display_name, insurer_type, is_active) VALUES (?, ?, 'obra_social', 1)");
                $db->execute([$p['obra_social'], $p['obra_social']]);
                $insurerId = (int)$db->lastInsertId();
            } else {
                $insurerId = (int)$row->id;
            }
        }

        $hash = password_hash($p['password'], PASSWORD_DEFAULT);
        $address = trim(($p['calle'] ?? '') . ' ' . ($p['altura'] ?? '') . ', ' . ($p['localidad'] ?? '') . ', ' . ($p['provincia'] ?? ''));
        $db->query(
            "INSERT INTO users (
                username, password, full_name, role, company_id, branch_id, area_id,
                employee_group, is_active, attendance_control_mode, profile_picture,
                email, phone_number, address, document_number, cuil, birth_date, hire_date,
                marital_status, children_count
             ) VALUES (
                ?, ?, ?, 'empleado', ?, ?, ?,
                'paviotti', 1, 'required', 'default.png',
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?
             )"
        );
        $db->execute([
            $p['username'], $hash, $p['full_name'], $p['company_id'], $p['branch_id'], $areaId,
            $p['email'], $p['phone'], $address !== ', ,' ? $address : null,
            $p['dni'], $p['cuil'], $p['birth'], $p['ingreso'],
            $p['marital'], $p['children'],
        ]);
        $userId = (int)$db->lastInsertId();
        $creados++;

        $db->query(
            "INSERT INTO employee_company_assignments
                (user_id, company_id, employee_number, area_id, position_id, start_date, seniority_date, status, is_primary)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'activo', 1)"
        );
        $db->execute([
            $userId, $p['company_id'], $p['legajo'], $areaId, $positionId, $p['ingreso'], $p['recibo'],
        ]);
        $asigId = (int)$db->lastInsertId();

        if ($p['calle'] !== null) {
            $db->query(
                'INSERT INTO employee_addresses (user_id, street, street_number, postal_code, locality, province, original_text, is_primary)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
            );
            $db->execute([
                $userId,
                $p['calle'], $p['altura'], $p['cp'], $p['localidad'], $p['provincia'],
                trim(($p['calle'] ?? '') . ' ' . ($p['altura'] ?? '') . ', CP ' . ($p['cp'] ?? '') . ', ' . ($p['localidad'] ?? '') . ', ' . ($p['provincia'] ?? '')),
            ]);
        }

        if ($insurerId !== null) {
            $db->query(
                "INSERT INTO employee_health_coverages (user_id, employee_company_assignment_id, health_insurer_id, status, is_primary)
                 VALUES (?, ?, ?, 'activa', 1)"
            );
            $db->execute([$userId, $asigId, $insurerId]);
        }

        if ($p['branch_id']) {
            $db->query('INSERT IGNORE INTO employee_branch_assignments (user_id, branch_id, is_primary) VALUES (?, ?, 1)');
            $db->execute([$userId, $p['branch_id']]);
        }
    }
    $db->commit();
    echo "\nIMPORTACIÓN COMPLETA: $creados usuario(s) creado(s). Username y contraseña = DNI.\n";
} catch (Throwable $e) {
    $db->rollBack();
    die('ERROR — rollback: ' . $e->getMessage() . "\n");
}
