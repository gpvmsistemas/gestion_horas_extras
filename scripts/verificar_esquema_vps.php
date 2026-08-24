<?php
/**
 * Verificador de esquema para el despliegue (VPS u otro entorno).
 *
 * Chequea, contra la base configurada, qué migraciones de la integración
 * Suite P&M están APLICADAS y cuáles PENDIENTES, en el orden correcto de
 * aplicación, imprimiendo el comando exacto de cada una. No modifica nada.
 *
 * Uso:  php scripts/verificar_esquema_vps.php
 */

if (php_sapi_name() !== 'cli') {
    die("Solo CLI.\n");
}
$root = dirname(__DIR__);
require $root . '/app/config/config.php';
if (file_exists($root . '/app/config/config.local.php')) {
    require $root . '/app/config/config.local.php';
}
require $root . '/app/models/Database.php';

$db = new Database();

$col = function ($tabla, $columna) use ($db) {
    try {
        $db->query("SHOW COLUMNS FROM `$tabla` LIKE " . "'" . $columna . "'");
        return (bool)$db->single();
    } catch (Throwable $e) {
        return false;
    }
};
$tab = function ($tabla) use ($db) {
    try {
        $db->query("SHOW TABLES LIKE '" . $tabla . "'");
        return (bool)$db->single();
    } catch (Throwable $e) {
        return false;
    }
};
$cnt = function ($sql) use ($db) {
    try {
        $db->query($sql);
        return (int)($db->single()->n ?? 0);
    } catch (Throwable $e) {
        return -1;
    }
};

// [etiqueta, comando, detector] — EN ORDEN DE APLICACIÓN.
$pasos = [
    ['Base organizacional', null, null],
    ['users.employee_group', 'mysql BASE < migration_employee_groups.sql',
        fn() => $col('users', 'employee_group')],
    ['companies.organization_group', 'mysql BASE < migration_company_organization_group.sql',
        fn() => $col('companies', 'organization_group')],
    ['Branding por empresa', 'mysql BASE < migration_company_branding.sql',
        fn() => $col('companies', 'brand_color')],
    ['users.branch_id (planner scope)', 'mysql BASE < migration_branch_planner_scope.sql',
        fn() => $col('users', 'branch_id')],
    ['users.attendance_control_mode', 'mysql BASE < migration_attendance_control_mode.sql',
        fn() => $col('users', 'attendance_control_mode')],
    ['Relojes por sucursal (clock_devices)', 'php scripts/aplicar_pendientes_vps.php',
        fn() => $tab('clock_devices') && $tab('clock_device_branches') && $tab('user_clock_device_mappings')],
    ['Control de acceso (user_access_scopes)', 'mysql BASE < migration_access_control_scopes.sql  (o php scripts/apply_access_control_migration.php)',
        fn() => $tab('user_access_scopes')],

    ['Sucursales y feriados', null, null],
    ['company_branches (Ecofarma)', 'mysql BASE < migration_ecofarma_branches.sql',
        fn() => $tab('company_branches')],
    ['Sucursales Moderna', 'mysql BASE < migration_moderna_branches.sql  (o php scripts/apply_moderna_branches_migration.php)',
        fn() => $cnt("SELECT COUNT(*) n FROM company_branches WHERE name = 'Marcellino'") > 0],
    ['employee_branch_assignments', 'mysql BASE < migration_employee_multiple_branches.sql',
        fn() => $tab('employee_branch_assignments')],
    ['company_locations + holiday_rules', 'mysql BASE < migration_holiday_locations.sql',
        fn() => $tab('holiday_rules')],
    ['Reglas de feriados locales Córdoba', 'mysql BASE < migration_cordoba_local_holiday_rules.sql',
        fn() => $cnt("SELECT COUNT(*) n FROM holiday_rules WHERE scope_type IN ('locality','province')") > 0],

    ['Legajo, convenios y vacaciones', null, null],
    ['Convenios colectivos (CCT)', 'mysql BASE < migration_collective_agreements.sql (paso 22 del paquete hosting)',
        fn() => $tab('collective_agreements')],
    ['Escala CCT 430/05 Farmacia Córdoba', 'php scripts/aplicar_pendientes_vps.php',
        fn() => $cnt("SELECT COUNT(*) n FROM collective_agreement_rules r
            JOIN collective_agreements a ON a.id = r.agreement_id
            WHERE a.code = 'FARMACIA-430-05'") >= 4],
    ['Tipos vacation/leave en employee_schedules', 'mysql BASE < migration_schedule_vacation_types.sql (paso 23)',
        function () use ($db) {
            try {
                $db->query("SHOW COLUMNS FROM employee_schedules LIKE 'type'");
                $r = $db->single();
                return $r && strpos($r->Type, 'vacation') !== false;
            } catch (Throwable $e) {
                return false;
            }
        }],
    ['Vacaciones v2 (balances)', 'php scripts/aplicar_pendientes_vps.php  (en MariaDB: mysql BASE < migration_vacation_management_v2.sql)',
        fn() => $tab('vacation_balance_periods')
            && $col('vacation_balance_periods', 'balance_type')
            && $col('vacation_balance_periods', 'adjustment_days')
            && $col('vacation_balance_movements', 'operation_key')],
    ['Legajo ampliado (assignments/addresses/coverages)', 'mysql BASE < migration_employee_record_complete.sql (paso 39)',
        fn() => $tab('employee_company_assignments')],
    ['Catálogo de obras sociales', 'php scripts/apply_health_insurers_catalog.php',
        fn() => $cnt('SELECT COUNT(*) n FROM health_insurers') >= 20],

    ['RRHH Integral (programa hr_operations_talent)', null, null],
    ['Auditoría y capacidades (audit_events + access_capabilities)', 'php scripts/aplicar_pendientes_vps.php',
        fn() => $tab('audit_events') && $tab('access_capabilities')
            && $cnt('SELECT COUNT(*) n FROM access_capabilities') >= 20],
    ['Vencimientos, EPP y activos', 'php scripts/aplicar_pendientes_vps.php',
        fn() => $tab('employee_expirations') && $tab('ppe_deliveries') && $tab('assets')],
    ['ATS/Vacantes (job_vacancies + consentimiento + preingreso)', 'php scripts/aplicar_pendientes_vps.php',
        fn() => $tab('job_vacancies') && $tab('candidates') && $tab('job_applications')
            && $cnt('SELECT COUNT(*) n FROM career_consents') >= 1
            && $col('users', 'employment_status')],
    ['Desempeño, onboarding y jobs programados', 'php scripts/aplicar_pendientes_vps.php',
        fn() => $tab('performance_reviews') && $tab('onboarding_checklists')
            && $tab('scheduled_job_runs') && $tab('hr_feature_flags')],

    ['Suite P&M — Registro de Horas y nómina Moderna', null, null],
    ['Registro de Horas (branch_name/branch_id en schedules)', 'mysql BASE < scripts/migration_registro_horas.sql',
        fn() => $col('employee_schedules', 'branch_name')],
    ['Sociedades Moderna (MODERNA/FRANCE/FCF)', 'mysql BASE < scripts/migration_moderna_empresas.sql',
        fn() => $cnt("SELECT COUNT(*) n FROM companies WHERE organization_group = 'moderna'") >= 3],
    ['Videos en anuncios', 'mysql BASE < scripts/migration_announcement_video.sql',
        fn() => $col('announcements', 'video_path')],
    ['Estados del empleado (guardia/vacaciones/licencia)', 'mysql BASE < scripts/migration_estados_empleado.sql',
        fn() => $tab('employee_status_periods')],
    ['Horario de atención por sucursal', 'mysql BASE < scripts/migration_horario_atencion_sucursal.sql',
        fn() => $col('company_branches', 'schedule_text')],
    ['Ficha personal (estado civil/hijos/parentesco)', 'mysql BASE < scripts/migration_ficha_personal.sql',
        fn() => $col('users', 'marital_status')],
    ['Scope admin Moderna (tras crear axel.moderna)', 'mysql BASE < scripts/fix_scope_admin_moderna.sql',
        fn() => $cnt("SELECT COUNT(*) n FROM user_access_scopes s JOIN users u ON u.id = s.user_id WHERE u.username = 'axel.moderna' AND s.access_role = 'administrador'") > 0],
];

$pend = 0;
foreach ($pasos as $p) {
    if ($p[1] === null) {
        echo "\n═══ {$p[0]} ═══\n";
        continue;
    }
    $ok = (bool)$p[2]();
    if (!$ok) {
        $pend++;
    }
    echo sprintf("  [%s] %s\n", $ok ? 'APLICADA ' : 'PENDIENTE', $p[0]);
    if (!$ok) {
        echo "              → {$p[1]}\n";
    }
}
echo "\n" . ($pend === 0
    ? "ESQUEMA COMPLETO: listo para los pasos de datos (limpieza → nómina → vacaciones).\n"
    : "$pend paso(s) PENDIENTES — aplicalos EN EL ORDEN LISTADO y volvé a correr este verificador.\n"
      . "En MySQL (VPS) los .sql con ADD COLUMN IF NOT EXISTS fallan con error 1064:\n"
      . "usá  php scripts/aplicar_pendientes_vps.php  que aplica todo lo pendiente de una vez.\n");
