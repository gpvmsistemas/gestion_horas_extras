<?php
chdir(__DIR__ . '/../public');
require_once '../app/bootstrap.php';

$db = new Database();
$db->query("SHOW TABLES LIKE 'company_branches'");
if (!$db->single()) {
    fwrite(STDERR, "Falta la tabla company_branches. Ejecutá primero migration_ecofarma_branches.sql.\n");
    exit(1);
}

$db->query("SHOW COLUMNS FROM companies LIKE 'organization_group'");
if (!$db->single()) {
    fwrite(STDERR, "Falta companies.organization_group. Ejecutá primero la migración de grupo organizacional.\n");
    exit(1);
}

$db->query("SELECT id FROM companies WHERE organization_group = 'moderna' ORDER BY id LIMIT 1");
$company = $db->single();
if (!$company) {
    fwrite(STDERR, "No existe una empresa clasificada con organization_group = moderna.\n");
    exit(1);
}

$sql = file_get_contents(__DIR__ . '/../migration_moderna_branches.sql');
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "No se pudo leer migration_moderna_branches.sql.\n");
    exit(1);
}

$db->query($sql);
$db->execute();

$db->query('SELECT COUNT(*) AS total FROM company_branches WHERE company_id = ? AND is_active = 1');
$result = $db->single([(int)$company->id]);
echo 'Sucursales activas de Moderna: ' . (int)($result->total ?? 0) . "\n";
