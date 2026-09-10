<?php
/**
 * Aplica migration_collective_agreement_leave_types.sql de forma idempotente.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/models/Database.php';

$sqlFile = dirname(__DIR__) . '/migration_collective_agreement_leave_types.sql';
if (!is_file($sqlFile)) {
    fwrite(STDERR, "No se encontró $sqlFile\n");
    exit(1);
}

$port = defined('DB_PORT') ? (string)DB_PORT : '3306';
$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';port=' . $port . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");

$stripSqlComments = function ($stmt) {
    $lines = [];
    foreach (explode("\n", $stmt) as $line) {
        $trim = ltrim($line);
        if ($trim === '' || str_starts_with($trim, '--')) {
            continue;
        }
        $lines[] = $line;
    }
    return trim(implode("\n", $lines));
};

$hasCol = function ($table, $column) use ($pdo) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $st->execute([$table, $column]);
    return (int)$st->fetchColumn() > 0;
};
$hasIdx = function ($table, $index) use ($pdo) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
    $st->execute([$table, $index]);
    return (int)$st->fetchColumn() > 0;
};

if (!$hasCol('requests', 'agreement_leave_type_id')) {
    $pdo->exec('ALTER TABLE requests ADD COLUMN agreement_leave_type_id INT UNSIGNED NULL AFTER request_type_id');
    echo "Columna requests.agreement_leave_type_id creada.\n";
}
if (!$hasIdx('requests', 'idx_requests_agreement_leave')) {
    $pdo->exec('ALTER TABLE requests ADD INDEX idx_requests_agreement_leave (agreement_leave_type_id)');
    echo "Índice idx_requests_agreement_leave creado.\n";
}

$sql = file_get_contents($sqlFile);
$statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));

foreach ($statements as $stmt) {
    $stmt = $stripSqlComments($stmt);
    if ($stmt === '') {
        continue;
    }
    $pdo->exec($stmt);
}

$db = new Database();
$db->query('SELECT ca.code, COUNT(l.id) c FROM collective_agreements ca
    LEFT JOIN collective_agreement_leave_types l ON l.agreement_id = ca.id
    GROUP BY ca.id ORDER BY ca.code');
echo "\nLicencias por convenio:\n";
foreach ($db->resultSet() as $row) {
    echo "  {$row->code}: {$row->c}\n";
}
$db->query("SHOW COLUMNS FROM requests LIKE 'agreement_leave_type_id'");
echo $db->single() ? "\nColumna requests.agreement_leave_type_id: OK\n" : "\nFalta columna requests.agreement_leave_type_id\n";
echo "\nMigración completada.\n";
