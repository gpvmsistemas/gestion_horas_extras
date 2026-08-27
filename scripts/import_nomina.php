<?php
/**
 * Importador de nóminas de RRHH Moderna (Excel .xlsx, hoja "Movimientos").
 *
 * Columnas esperadas: Legajo | Nombre | Domicilio | Altura | C.P | Localidad |
 * Provincia | Documento | CUIL | Nacimiento | Estado Civil | Cargo |
 * Fecha de Ingreso | Ingreso Recibo | Fecha de Egreso | Motivo | Sección |
 * Área | Razón | Razón Social | Teléfono | Mail | Hijos | Detalle | Obra Social
 *
 * Qué hace por cada fila SIN fecha de egreso:
 *  - users: alta/actualización (por username) con role=empleado,
 *    employee_group=moderna, empresa = razón social del Excel, sucursal fija
 *    (--sucursal, default "Comercial y MKT"), contraseña = últimos 4 del DNI,
 *    hire_date, estado civil, hijos, observaciones (Detalle) y contacto de
 *    emergencia si el Detalle trae "teléfono de emergencia".
 *  - employee_company_assignments: legajo (employee_number), ingreso
 *    (start_date), ingreso recibo (seniority_date), área y cargo (se crean en
 *    el catálogo si faltan).
 *  - employee_addresses: domicilio desglosado.
 *  - employee_health_coverages: obra social (se crea en el catálogo si falta).
 *  - employee_branch_assignments: sucursal principal.
 *
 * Los usuarios salen de un JSON aparte (NO commiteado — datos sensibles):
 *   { "por_legajo": { "1": "M37", "15": "M47", ... } }
 *
 * Uso (local y luego en el VPS, copiando xlsx + json a mano):
 *   php scripts/import_nomina.php "Nomina.xlsx" nomina_credenciales.json            → dry-run
 *   php scripts/import_nomina.php "Nomina.xlsx" nomina_credenciales.json --ejecutar
 *   [--sucursal "Comercial y MKT"]
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
$xlsxPath = $args[0] ?? '';
$credPath = $args[1] ?? '';
$ejecutar = in_array('--ejecutar', $argv, true);
$sucursalNombre = 'Comercial y MKT';
foreach ($argv as $i => $a) {
    if ($a === '--sucursal' && isset($argv[$i + 1])) {
        $sucursalNombre = $argv[$i + 1];
    }
}
if ($xlsxPath === '' || !file_exists($xlsxPath)) {
    die("Uso: php scripts/import_nomina.php <nomina.xlsx> <credenciales.json> [--ejecutar] [--sucursal \"Nombre\"]\n");
}
if ($credPath === '' || !file_exists($credPath)) {
    die("Falta el JSON de credenciales (usuario por legajo).\n");
}
$cred = json_decode(file_get_contents($credPath), true);
$porLegajo = $cred['por_legajo'] ?? [];
if (empty($porLegajo)) {
    die("El JSON de credenciales no tiene 'por_legajo'.\n");
}

// ── Lectura del xlsx (sin dependencias: ZipArchive + SimpleXML) ──
function leerXlsx($path) {
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

function serialAFecha($v) {
    $v = trim((string)$v);
    if ($v === '' || !is_numeric($v)) {
        return null;
    }
    return gmdate('Y-m-d', ((int)$v - 25569) * 86400);
}

function normalizarCargo($cargo) {
    $c = trim(preg_replace('/\s+/', ' ', $cargo));
    $map = [
        'adminitrativo'         => 'Administrativo',
        'administrativo'        => 'Administrativo',
        'gerente copmercial'    => 'Gerente Comercial',
        'gerente comercial'     => 'Gerente Comercial',
        'empleada de farmacia'  => 'Empleado/a de Farmacia',
        'empleado de farmacia'  => 'Empleado/a de Farmacia',
        'emp. especializ'       => 'Empleado/a Especializado/a',
    ];
    $k = mb_strtolower($c);
    return $map[$k] ?? $c;
}

$rows = leerXlsx($xlsxPath);
$header = array_shift($rows);
echo "Filas de datos: " . count($rows) . " (sucursal destino: {$sucursalNombre})\n\n";

$db = new Database();

// ── Resoluciones de catálogo (antes de las inserciones — wrapper de UNA sentencia) ──
$db->query("SELECT id, name FROM companies WHERE organization_group = 'moderna'");
$companiesByName = [];
foreach ($db->resultSet() as $c) {
    $companiesByName[mb_strtoupper(preg_replace('/[^A-Za-z]/', '', $c->name))] = (int)$c->id;
}
$db->query('SELECT id, company_id FROM company_branches WHERE name = ? AND is_active = 1 LIMIT 1');
$branchRow = $db->single([$sucursalNombre]);
if (!$branchRow) {
    die("La sucursal '{$sucursalNombre}' no existe en company_branches.\n");
}
$branchId = (int)$branchRow->id;

$resolverEmpresa = function ($razonSocial) use ($companiesByName) {
    // "FRANCE S.R.L." → FRANCE SRL; "MODERNA S.R.L." → MODERNA SRL; etc.
    $clave = mb_strtoupper(preg_replace('/[^A-Za-z]/', '', $razonSocial));
    foreach ($companiesByName as $nombre => $id) {
        if (strpos($clave, substr($nombre, 0, 6)) === 0 || strpos($nombre, substr($clave, 0, 6)) === 0) {
            return $id;
        }
    }
    return null;
};

$plan = [];
$errores = [];
foreach ($rows as $i => $r) {
    $fila = $i + 2;
    $legajo = trim($r['A'] ?? '');
    $nombre = trim(preg_replace('/\s+/', ' ', $r['B'] ?? ''));
    if ($legajo === '' || $nombre === '') {
        continue;
    }
    if (trim($r['O'] ?? '') !== '') {
        echo "  fila $fila ({$nombre}): con fecha de egreso — se omite.\n";
        continue;
    }
    $username = trim((string)($porLegajo[$legajo] ?? ''));
    if ($username === '') {
        $errores[] = "fila $fila ({$nombre}): sin usuario asignado para el legajo {$legajo} en el JSON.";
        continue;
    }
    $dni = preg_replace('/\D/', '', $r['H'] ?? '');
    if (strlen($dni) < 4) {
        $errores[] = "fila $fila ({$nombre}): DNI inválido.";
        continue;
    }
    $companyId = $resolverEmpresa($r['T'] ?? '');
    if ($companyId === null) {
        $errores[] = "fila $fila ({$nombre}): razón social '" . trim($r['T'] ?? '') . "' no mapea a una empresa Moderna.";
        continue;
    }
    $detalle = trim(preg_replace('/\s+/', ' ', $r['X'] ?? ''));
    $emergencia = ['name' => null, 'rel' => null, 'phone' => null];
    if (preg_match('/emergencia:?\s*(.+)$/iu', $detalle, $m)) {
        $resto = trim($m[1]);
        if (preg_match('/(\d[\d\s\-]{5,})/', $resto, $tel)) {
            $emergencia['phone'] = preg_replace('/\D/', '', $tel[1]);
            $resto = trim(str_replace($tel[1], '', $resto));
        }
        if (preg_match('/\(([^)]+)\)/', $resto, $par)) {
            $emergencia['rel'] = trim($par[1]);
            $resto = trim(str_replace($par[0], '', $resto));
        }
        $emergencia['name'] = $resto !== '' ? $resto : null;
    }
    $hijosDet = [];
    if (($r['W'] ?? '') !== '') {
        $hijosDet[] = 'Hijos: ' . trim($r['W']);
    }
    if ($detalle !== '') {
        $hijosDet[] = 'Detalle: ' . $detalle;
    }
    $plan[] = [
        'fila'        => $fila,
        'legajo'      => $legajo,
        'username'    => $username,
        'password'    => substr($dni, -4),
        'full_name'   => $nombre,
        'company_id'  => $companyId,
        'email'       => trim($r['V'] ?? '') ?: null,
        'phone'       => trim($r['U'] ?? '') ?: null,
        'dni'         => $dni,
        'cuil'        => trim($r['I'] ?? '') ?: null,
        'birth'       => serialAFecha($r['J'] ?? ''),
        'marital'     => trim($r['K'] ?? '') ?: null,
        'children'    => ($r['W'] ?? '') !== '' ? (int)$r['W'] : null,
        'cargo'       => normalizarCargo($r['L'] ?? ''),
        'ingreso'     => serialAFecha($r['M'] ?? ''),
        'recibo'      => serialAFecha($r['N'] ?? ''),
        'area'        => trim(preg_replace('/\s+/', ' ', $r['R'] ?? '')) ?: null,
        'calle'       => trim($r['C'] ?? '') ?: null,
        'altura'      => trim($r['D'] ?? '') ?: null,
        'cp'          => trim($r['E'] ?? '') ?: null,
        'localidad'   => trim($r['F'] ?? '') ?: null,
        'provincia'   => trim($r['G'] ?? '') ?: null,
        'obra_social' => trim($r['Y'] ?? '') ?: null,
        'hr_notes'    => $hijosDet ? implode(' · ', $hijosDet) : null,
        'emergencia'  => $emergencia,
    ];
}

foreach ($plan as $p) {
    $dir = trim(($p['calle'] ?? '') . ' ' . ($p['altura'] ?? ''));
    echo sprintf(
        "  fila %d · legajo %-4s · %-42s → usuario %-6s (clave ****%s) · %s · cargo: %s · ingreso %s · recibo %s%s\n",
        $p['fila'], $p['legajo'], $p['full_name'], $p['username'], $p['password'],
        array_search($p['company_id'], $companiesByName) !== false ? 'empresa #' . $p['company_id'] : 'empresa #' . $p['company_id'],
        $p['cargo'], $p['ingreso'], $p['recibo'],
        $p['emergencia']['phone'] ? ' · emergencia: ' . $p['emergencia']['name'] . ' (' . $p['emergencia']['rel'] . ') ' . $p['emergencia']['phone'] : ''
    );
}
if (!empty($errores)) {
    echo "\nERRORES:\n  " . implode("\n  ", $errores) . "\n";
    die("Corregí los errores antes de ejecutar.\n");
}
if (!$ejecutar) {
    die("\nDRY-RUN: nada se escribió. Ejecutá con --ejecutar para importar " . count($plan) . " colaborador(es).\n");
}

// ── Ejecución ──
$db->beginTransaction();
try {
    $creados = 0;
    $actualizados = 0;
    foreach ($plan as $p) {
        // Catálogos (find-or-create) — SIEMPRE resueltos antes del statement siguiente.
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
        if ($p['cargo'] !== '') {
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
        $db->query('SELECT id FROM users WHERE username = ? LIMIT 1');
        $existente = $db->single([$p['username']]);
        if ($existente) {
            $userId = (int)$existente->id;
            $db->query(
                "UPDATE users SET full_name=?, password=?, role='empleado', company_id=?, branch_id=?, area_id=?,
                        employee_group='moderna', is_active=1, email=?, phone_number=?, address=?,
                        document_number=?, cuil=?, birth_date=?, hire_date=?, marital_status=?, children_count=?,
                        emergency_contact_name=?, emergency_contact_relationship=?, emergency_contact_phone=?, hr_notes=?
                 WHERE id=?"
            );
            $db->execute([
                $p['full_name'], $hash, $p['company_id'], $branchId, $areaId,
                $p['email'], $p['phone'],
                trim(($p['calle'] ?? '') . ' ' . ($p['altura'] ?? '') . ', ' . ($p['localidad'] ?? '') . ', ' . ($p['provincia'] ?? '')),
                $p['dni'], $p['cuil'], $p['birth'], $p['ingreso'], $p['marital'], $p['children'],
                $p['emergencia']['name'], $p['emergencia']['rel'], $p['emergencia']['phone'], $p['hr_notes'],
                $userId,
            ]);
            $actualizados++;
        } else {
            $db->query(
                "INSERT INTO users (username, password, full_name, role, company_id, branch_id, area_id,
                        employee_group, is_active, attendance_control_mode, profile_picture,
                        email, phone_number, address, document_number, cuil, birth_date, hire_date,
                        marital_status, children_count,
                        emergency_contact_name, emergency_contact_relationship, emergency_contact_phone, hr_notes)
                 VALUES (?, ?, ?, 'empleado', ?, ?, ?, 'moderna', 1, 'required', 'default.png',
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $db->execute([
                $p['username'], $hash, $p['full_name'], $p['company_id'], $branchId, $areaId,
                $p['email'], $p['phone'],
                trim(($p['calle'] ?? '') . ' ' . ($p['altura'] ?? '') . ', ' . ($p['localidad'] ?? '') . ', ' . ($p['provincia'] ?? '')),
                $p['dni'], $p['cuil'], $p['birth'], $p['ingreso'], $p['marital'], $p['children'],
                $p['emergencia']['name'], $p['emergencia']['rel'], $p['emergencia']['phone'], $p['hr_notes'],
            ]);
            $userId = (int)$db->lastInsertId();
            $creados++;
        }

        // Legajo ampliado (assignment principal): legajo + ingreso + ingreso recibo + cargo + área
        $db->query('SELECT id FROM employee_company_assignments WHERE user_id = ? AND company_id = ? LIMIT 1');
        $asig = $db->single([$userId, $p['company_id']]);
        if ($asig) {
            $db->query(
                "UPDATE employee_company_assignments
                 SET employee_number=?, area_id=?, position_id=?, start_date=?, seniority_date=?, status='activo', is_primary=1
                 WHERE id=?"
            );
            $db->execute([$p['legajo'], $areaId, $positionId, $p['ingreso'], $p['recibo'], (int)$asig->id]);
            $asigId = (int)$asig->id;
        } else {
            $db->query(
                "INSERT INTO employee_company_assignments
                    (user_id, company_id, employee_number, area_id, position_id, start_date, seniority_date, status, is_primary)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'activo', 1)"
            );
            $db->execute([$userId, $p['company_id'], $p['legajo'], $areaId, $positionId, $p['ingreso'], $p['recibo']]);
            $asigId = (int)$db->lastInsertId();
        }

        // Domicilio desglosado
        if ($p['calle'] !== null) {
            $db->query('SELECT id FROM employee_addresses WHERE user_id = ? AND is_primary = 1 LIMIT 1');
            $addr = $db->single([$userId]);
            $addrVals = [
                $p['calle'], $p['altura'], $p['cp'], $p['localidad'], $p['provincia'],
                trim(($p['calle'] ?? '') . ' ' . ($p['altura'] ?? '') . ', CP ' . ($p['cp'] ?? '') . ', ' . ($p['localidad'] ?? '') . ', ' . ($p['provincia'] ?? '')),
            ];
            if ($addr) {
                $db->query(
                    'UPDATE employee_addresses SET street=?, street_number=?, postal_code=?, locality=?, province=?, original_text=? WHERE id=?'
                );
                $db->execute(array_merge($addrVals, [(int)$addr->id]));
            } else {
                $db->query(
                    'INSERT INTO employee_addresses (user_id, street, street_number, postal_code, locality, province, original_text, is_primary)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
                );
                $db->execute(array_merge([$userId], $addrVals));
            }
        }

        // Obra social
        if ($insurerId !== null) {
            $db->query('SELECT id FROM employee_health_coverages WHERE user_id = ? AND is_primary = 1 LIMIT 1');
            $cov = $db->single([$userId]);
            if ($cov) {
                $db->query('UPDATE employee_health_coverages SET health_insurer_id=?, employee_company_assignment_id=? WHERE id=?');
                $db->execute([$insurerId, $asigId, (int)$cov->id]);
            } else {
                $db->query(
                    "INSERT INTO employee_health_coverages (user_id, employee_company_assignment_id, health_insurer_id, status, is_primary)
                     VALUES (?, ?, ?, 'activa', 1)"
                );
                $db->execute([$userId, $asigId, $insurerId]);
            }
        }

        // Sucursal principal
        $db->query('INSERT IGNORE INTO employee_branch_assignments (user_id, branch_id, is_primary) VALUES (?, ?, 1)');
        $db->execute([$userId, $branchId]);
    }
    $db->commit();
    echo "\nIMPORTACIÓN COMPLETA: $creados creado(s), $actualizados actualizado(s).\n";
} catch (Throwable $e) {
    $db->rollBack();
    die('ERROR — rollback total: ' . $e->getMessage() . "\n");
}
