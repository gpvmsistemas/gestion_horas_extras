<?php
/**
 * Verificador exhaustivo: TODAS las tablas que el código referencia vs las
 * que existen en la base configurada. Corrido en el VPS responde de una vez
 * "¿qué páginas van a fallar por esquema faltante?" sin ir módulo por módulo.
 *
 * Escanea app/ (controllers, models, services, helpers, views) buscando
 * FROM/JOIN/INSERT INTO/UPDATE/SHOW ... y lista cada tabla faltante con los
 * archivos que la usan. Solo lee: no modifica nada.
 *
 * Uso:  php scripts/verificar_tablas_codigo.php
 */

if (php_sapi_name() !== 'cli') {
    die("Solo CLI.\n");
}
$root = dirname(__DIR__);
require $root . '/app/config/config.php';
if (file_exists($root . '/app/config/config.local.php')) {
    require $root . '/app/config/config.local.php';
}

$pdo = new PDO(
    'mysql:host=' . DB_HOST . (defined('DB_PORT') ? ';port=' . DB_PORT : '') . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$existentes = array_map('strtolower', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
$existentes = array_flip($existentes);

// Tokens que el regex atrapa pero no son tablas de esta base.
$noTablas = array_flip([
    'dual', 'select', 'information_schema', 'database', 'mysql', 'the', 'a',
    'each', 'post', 'get', 'session', 'this', 'that', 'them', 'it', 'us', 'all',
    'del', 'los', 'las', 'una', 'con', 'para',
    // Tablas de bases EXTERNAS (reloj CrossChex / extintos), no de esta base:
    'checkinout', 'userinfo',
]);

$refs = []; // tabla => [archivos]
$dirs = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));
foreach ($dirs as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $src = file_get_contents($file->getPathname());
    if ($src === false) {
        continue;
    }
    if (!preg_match_all('/\b(?:FROM|JOIN|INSERT\s+INTO|INSERT\s+IGNORE\s+INTO|(?<!KEY )UPDATE|DELETE\s+FROM|SHOW\s+COLUMNS\s+FROM|SHOW\s+INDEX\s+FROM|TRUNCATE\s+TABLE)\s+`?([a-z_][a-z0-9_]{2,})`?/i', $src, $m)) {
        continue;
    }
    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    foreach ($m[1] as $t) {
        $t = strtolower($t);
        if (isset($noTablas[$t])) {
            continue;
        }
        $refs[$t][$rel] = true;
    }
}
ksort($refs);

$faltantes = [];
foreach ($refs as $tabla => $archivos) {
    if (!isset($existentes[$tabla])) {
        $faltantes[$tabla] = array_keys($archivos);
    }
}

echo 'Base: ' . DB_NAME . '@' . DB_HOST . ' · tablas en la base: ' . count($existentes)
    . ' · tablas referenciadas por el código: ' . count($refs) . "\n\n";
if (!$faltantes) {
    echo "TODAS las tablas que el código usa existen en esta base.\n";
    exit(0);
}
echo count($faltantes) . " tabla(s) FALTANTES — las páginas que las usan van a fallar:\n\n";
foreach ($faltantes as $tabla => $archivos) {
    echo "  [FALTA] $tabla\n";
    foreach (array_slice($archivos, 0, 4) as $a) {
        echo "          usada en $a\n";
    }
    if (count($archivos) > 4) {
        echo '          … y ' . (count($archivos) - 4) . " archivo(s) más\n";
    }
}
echo "\nSolución habitual: php scripts/aplicar_pendientes_vps.php (y si una tabla no\n";
echo "está cubierta por el applier, avisar con esta salida para agregarla).\n";
exit(1);
