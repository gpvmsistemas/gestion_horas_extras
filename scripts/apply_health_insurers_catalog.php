<?php
chdir(__DIR__ . '/../public');
require_once '../app/bootstrap.php';

$catalog = [
    ['AMICOS', 'mutual'],
    ['AMUR', 'mutual'],
    ['APM', 'obra_social'],
    ['ASPURC', 'obra_social'],
    ['Avalian', 'prepaga'],
    ['Caja Notarial', 'obra_social'],
    ['CPCE', 'mutual'],
    ['DASUTEN', 'obra_social'],
    ['Federada Salud', 'prepaga'],
    ['Jerárquicos Salud', 'mutual'],
    ['OPDEA', 'obra_social'],
    ['OSDE', 'prepaga'],
    ['OSFATUN', 'obra_social'],
    ['OSITAC', 'obra_social'],
    ['OSPECOR', 'obra_social'],
    ['OSPES', 'obra_social'],
    ['OSPJN', 'obra_social'],
    ['OSSACRA', 'obra_social'],
    ['Medifé', 'prepaga'],
    ['Poder Judicial', 'obra_social'],
    ['Prevención Salud', 'prepaga'],
    ['Sancor Salud', 'prepaga'],
    ['SMAI', 'mutual'],
    ['SOS Red de Asistencia', 'prepaga'],
    ['Swiss Medical', 'prepaga'],
];

$db = new Database();
$db->query("SHOW TABLES LIKE 'health_insurers'");
if (!$db->single()) {
    fwrite(STDERR, "Falta la tabla health_insurers. Ejecutá primero migration_employee_record_complete.sql.\n");
    exit(1);
}

$sql = 'INSERT INTO health_insurers (legal_name,display_name,insurer_type) VALUES (?,?,?) '
     . 'ON DUPLICATE KEY UPDATE legal_name=VALUES(legal_name),insurer_type=VALUES(insurer_type),is_active=1,updated_at=NOW()';
$db->beginTransaction();
try {
    foreach ($catalog as [$name, $type]) {
        $db->query($sql);
        $db->execute([$name, $name, $type]);
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, "No se pudo actualizar el catálogo: {$e->getMessage()}\n");
    exit(1);
}

$names = array_column($catalog, 0);
$placeholders = implode(',', array_fill(0, count($names), '?'));
$db->query("SELECT COUNT(*) AS total FROM health_insurers WHERE is_active=1 AND display_name IN ($placeholders)");
$result = $db->single($names);
echo "Catálogo de coberturas actualizado: " . (int)($result->total ?? 0) . " de " . count($catalog) . " registros activos.\n";
