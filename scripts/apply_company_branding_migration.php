<?php
chdir(__DIR__ . '/../public');
require_once '../app/bootstrap.php';

$db = new Database();
$columns = [];
$db->query('SHOW COLUMNS FROM companies');
foreach ($db->resultSet() as $column) $columns[$column->Field] = true;

if (!isset($columns['brand_color'])) {
    $db->query("ALTER TABLE companies ADD COLUMN brand_color VARCHAR(7) NOT NULL DEFAULT '#e91e8c'");
    $db->execute();
    echo "Agregada companies.brand_color\n";
}
if (!isset($columns['logo_path'])) {
    $db->query('ALTER TABLE companies ADD COLUMN logo_path VARCHAR(255) NULL DEFAULT NULL');
    $db->execute();
    echo "Agregada companies.logo_path\n";
}
$db->query("UPDATE companies SET brand_color = '#2E7D32' WHERE LOWER(name) = 'la naturaleza' AND (brand_color IS NULL OR brand_color = '' OR LOWER(brand_color) = '#e91e8c')");
$db->execute();
echo "Migración de identidad visual lista.\n";
