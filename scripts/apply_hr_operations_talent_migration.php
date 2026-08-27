<?php
require dirname(__DIR__).'/app/config/config.php';
$file=dirname(__DIR__).'/migration_hr_operations_talent.sql';$sql=file_get_contents($file);if($sql===false)throw new RuntimeException('No se pudo leer la migración.');
$pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec($sql);$checksum=hash_file('sha256',$file);$stmt=$pdo->prepare("UPDATE schema_migrations SET checksum_sha256=?,applied_at=NOW(),applied_by='scripts/apply_hr_operations_talent_migration.php' WHERE migration_key='2026_08_hr_operations_talent'");$stmt->execute([$checksum]);echo "Migración RRHH aplicada: $checksum\n";
