<?php
/**
 * Agrega collective_agreement_leave_types.requires_approval y marca ENFERMEDAD como solo aviso.
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
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'collective_agreement_leave_types' AND COLUMN_NAME = 'requires_approval'");
$st->execute();
if ((int)$st->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE collective_agreement_leave_types
        ADD COLUMN requires_approval TINYINT(1) NOT NULL DEFAULT 1
        AFTER requires_certificate');
    echo "Columna collective_agreement_leave_types.requires_approval creada.\n";
} else {
    echo "Columna collective_agreement_leave_types.requires_approval ya existe: migración ya aplicada, sin cambios.\n";
    echo "(No se vuelven a marcar licencias ni a aprobar solicitudes en masa: eso lo decide RRHH desde Convenios.)\n";
    exit(0);
}

$updated = $pdo->exec("UPDATE collective_agreement_leave_types
    SET requires_approval = 0
    WHERE UPPER(code) = 'ENFERMEDAD'");
echo "Licencias ENFERMEDAD marcadas como solo aviso: {$updated} fila(s).\n";

$pending = $pdo->exec("UPDATE requests r
    INNER JOIN collective_agreement_leave_types alt ON alt.id = r.agreement_leave_type_id
    SET r.status = 'Aprobado'
    WHERE r.status = 'Pendiente' AND alt.requires_approval = 0");
echo "Avisos de enfermedad pendientes pasados a registrados: {$pending} fila(s).\n";

$renamed = $pdo->exec("UPDATE collective_agreement_leave_types SET name = 'Enfermedad' WHERE UPPER(code) = 'ENFERMEDAD'");
echo "Licencias ENFERMEDAD renombradas a «Enfermedad»: {$renamed} fila(s).\n";
