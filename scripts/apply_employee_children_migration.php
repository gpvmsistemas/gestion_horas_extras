<?php
/**
 * Crea la tabla employee_children (hijos/as con fecha y sexo).
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';

$port = defined('DB_PORT') ? (string)DB_PORT : '3306';
$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';port=' . $port . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec(file_get_contents(dirname(__DIR__) . '/migration_employee_children.sql'));
echo "Tabla employee_children verificada.\n";
