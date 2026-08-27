<?php
/** Aplica la migración de permisos y alcances en la base configurada localmente. */
chdir(__DIR__ . '/../public');
require '../app/bootstrap.php';

$sql = file_get_contents('../migration_access_control_scopes.sql');
if ($sql === false) {
    fwrite(STDERR, "No se encontró migration_access_control_scopes.sql\n");
    exit(1);
}
$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
    $statement = trim($statement);
    if ($statement !== '') {
        $pdo->exec($statement);
    }
}
echo "Migración de permisos aplicada.\n";
