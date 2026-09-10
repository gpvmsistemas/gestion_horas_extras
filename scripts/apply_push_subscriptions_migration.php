<?php
/**
 * Crea la tabla push_subscriptions para notificaciones Web Push (PWA).
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';

$sqlFile = __DIR__ . '/migration_push_subscriptions.sql';
if (!is_file($sqlFile)) {
    fwrite(STDERR, "No se encontró migration_push_subscriptions.sql\n");
    exit(1);
}

$port = defined('DB_PORT') ? (string)DB_PORT : '3306';
$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';port=' . $port . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'push_subscriptions'");
$st->execute();
if ((int)$st->fetchColumn() > 0) {
    echo "Tabla push_subscriptions ya existe.\n";
    exit(0);
}

$sql = file_get_contents($sqlFile);
$pdo->exec($sql);
echo "Tabla push_subscriptions creada.\n";
echo "Generá claves VAPID: php scripts/generate_vapid_keys.php\n";
