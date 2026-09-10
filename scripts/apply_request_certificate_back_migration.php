<?php
/**
 * Agrega requests.certificate_back_path (dorso del certificado).
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';

$port = defined('DB_PORT') ? (string)DB_PORT : '3306';
$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';port=' . $port . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'requests' AND COLUMN_NAME = 'certificate_back_path'");
$st->execute();
if ((int)$st->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE requests ADD COLUMN certificate_back_path VARCHAR(255) NULL AFTER certificate_path');
    echo "Columna requests.certificate_back_path creada.\n";
} else {
    echo "Columna requests.certificate_back_path ya existe.\n";
}
