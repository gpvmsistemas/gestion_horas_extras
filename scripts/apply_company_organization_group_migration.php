<?php
chdir(__DIR__ . '/../public');
require_once '../app/bootstrap.php';

$db = new Database();
$db->query("SHOW COLUMNS FROM companies LIKE 'organization_group'");
if (!$db->single()) {
    $db->query("ALTER TABLE companies ADD COLUMN organization_group ENUM('paviotti', 'moderna') NOT NULL DEFAULT 'paviotti' AFTER name");
    $db->execute();
    echo "Agregada companies.organization_group\n";
}
echo "Migración de grupo organizacional lista.\n";
