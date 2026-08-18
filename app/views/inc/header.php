<?php
// app/views/inc/header.php — Layout con Sidebar (mobile-first)

if (!function_exists('navIsActive')) {
    function navIsActive(...$paths) {
        $current = $_SERVER['REQUEST_URI'];
        foreach ($paths as $path) {
            if ($path !== '' && strpos($current, $path) !== false) return 'active';
        }
        return '';
    }
}

$_homeUrl = URLROOT;
if (isLoggedIn() && hasRole('empleado')) $_homeUrl = URLROOT . '/employee/index';
elseif (isLoggedIn() && isStaffAdmin()) $_homeUrl = URLROOT . '/admin/dashboard';

$_userName = 'Usuario';
if (!empty($_SESSION['user_full_name']))    $_userName = htmlspecialchars($_SESSION['user_full_name']);
elseif (!empty($_SESSION['user_username'])) $_userName = htmlspecialchars($_SESSION['user_username']);
$_userRole = isset($_SESSION['user_role']) ? ucfirst(htmlspecialchars($_SESSION['user_role'])) : '';
if (isLoggedIn() && !empty($_SESSION['user_id'])) {
    avatar_sync_session_from_db((int)$_SESSION['user_id']);
}
$_userAvatarUrl = avatar_url($_SESSION['user_profile_picture'] ?? '');
$_userAvatarOnerror = avatar_img_onerror_attr();
$_userInitial = mb_strtoupper(mb_substr(strip_tags($_userName), 0, 1, 'UTF-8'), 'UTF-8');

$_pendingRequestsCount = 0;
$_pendingRequestsPreview = [];
$_pendingAdvancesCount = 0;
if (isLoggedIn() && isStaffAdmin() && !empty($_SESSION['user_company_id'])) {
    $_reqNotifyModel = new Request();
    $_pendingRequestsCount = $_reqNotifyModel->countPendingByCompany((int)$_SESSION['user_company_id']);
    $_swapNotifyModel = new ShiftSwap();
    if ($_swapNotifyModel->isSchemaReady()) {
        $_pendingRequestsCount += $_swapNotifyModel->countPendingByCompany((int)$_SESSION['user_company_id']);
    } else {
        $_swapNotifyModel = null;
    }
    if (hasRole('admin') && function_exists('salary_advance_module_enabled') && salary_advance_module_enabled()) {
        $_saNotifyModel = new SalaryAdvance();
        $_pendingAdvancesCount = $_saNotifyModel->countPendingByCompany((int)$_SESSION['user_company_id']);
    }
    if ($_pendingRequestsCount > 0 || $_pendingAdvancesCount > 0) {
        $_pendingRequestsPreview = array_slice(
            $_reqNotifyModel->getPendingRequestsWithDetails((int)$_SESSION['user_company_id']),
            0,
            6
        );
        if (!empty($_swapNotifyModel)) {
            foreach (array_slice($_swapNotifyModel->getPendingByCompany((int)$_SESSION['user_company_id']), 0, 4) as $_sw) {
                $_pendingRequestsPreview[] = (object)[
                    'id' => 'swap-' . $_sw->id,
                    'full_name' => $_sw->proposer_name . ' ↔ ' . $_sw->accepter_name,
                    'type_name' => 'Cambio de turno',
                    'start_date' => $_sw->proposer_date,
                    'end_date' => $_sw->accepter_date,
                    'is_shift_swap' => true,
                    'swap_id' => $_sw->id,
                ];
            }
        }
        if ($_pendingAdvancesCount > 0 && !empty($_saNotifyModel)) {
            foreach (array_slice($_saNotifyModel->getPendingByCompany((int)$_SESSION['user_company_id']), 0, 4) as $_sa) {
                $_pendingRequestsPreview[] = (object)[
                    'id' => 'advance-' . $_sa->id,
                    'full_name' => $_sa->employee_name,
                    'type_name' => 'Adelanto de sueldo · ' . salary_advance_format_money($_sa->amount),
                    'start_date' => date('Y-m-d', strtotime($_sa->created_at)),
                    'end_date' => date('Y-m-d', strtotime($_sa->created_at)),
                    'is_salary_advance' => true,
                    'advance_id' => $_sa->id,
                ];
            }
        }
        $_pendingRequestsPreview = array_slice($_pendingRequestsPreview, 0, 8);
    }
}
$_adminNotifyTotalCount = $_pendingRequestsCount + (hasRole('admin') ? $_pendingAdvancesCount : 0);

$_empUnreadNotifications = 0;
$_empNotificationsPreview = [];
if (isLoggedIn() && hasRole('empleado') && function_exists('notifications_is_ready') && notifications_is_ready()) {
    $_empNotifModel = new UserNotification();
    $_empUid = (int)$_SESSION['user_id'];
    if (function_exists('employee_portal_count_visible_unread')) {
        $_empUnreadNotifications = employee_portal_count_visible_unread($_empUid);
    } else {
        $_empUnreadNotifications = $_empNotifModel->countUnread($_empUid);
    }
    $_empNotificationsPreview = $_empNotifModel->getRecentForUser($_empUid, 20);
    if (function_exists('employee_portal_filter_notifications')) {
        $_empNotificationsPreview = employee_portal_filter_notifications($_empNotificationsPreview);
        $_empNotificationsPreview = array_slice($_empNotificationsPreview, 0, 6);
    }
}

$_brandLogoUrl = function_exists('company_brand_logo_url') ? company_brand_logo_url() : (URLROOT . '/img/logo-paviotti.png');
$_brandName = function_exists('company_brand_display_name') ? company_brand_display_name() : (defined('SITENAME') ? SITENAME : 'RRHH');
$_brandSubtitle = function_exists('company_brand_subtitle') ? company_brand_subtitle() : 'Gestión de RRHH';
$_bodyBrandClass = function_exists('company_brand_body_class') ? company_brand_body_class() : '';
$_usesCpNav = function_exists('current_user_uses_casapav_tasks') && current_user_uses_casapav_tasks();
// Suite P&M: la organización Moderna pisa la marca por empresa
// (nombre, logo, tema de colores) y unifica: sin selector de empresa.
if (function_exists('org_brand_name')) {
    $_brandName = org_brand_name($_brandName);
    $_brandSubtitle = org_brand_subtitle($_brandSubtitle);
    $_brandLogoUrl = org_brand_logo_url($_brandLogoUrl);
    $_bodyBrandClass = trim($_bodyBrandClass . ' ' . org_body_class());
}
$_orgHidesPaviottiModules = function_exists('org_hides_paviotti_business_modules') && org_hides_paviotti_business_modules();
$_orgIsModerna = function_exists('org_is_moderna') && org_is_moderna();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo (function_exists('org_is_moderna') && org_is_moderna()) ? 'Suite Red Farmacias Moderna' : SITENAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css' rel='stylesheet' />
    <link rel="stylesheet" href="<?php echo URLROOT; ?>/css/style.css">
    <?php if (isLoggedIn() && function_exists('notifications_is_ready') && notifications_is_ready()): ?>
    <link rel="stylesheet" href="<?php echo URLROOT; ?>/css/notifications.css">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
</head>
<body<?php
echo isLoggedIn()
    ? ($_bodyBrandClass !== '' ? ' class="' . htmlspecialchars($_bodyBrandClass) . '"' : '')
    : ' class="auth-page"';
?>>

<?php if(isLoggedIn()): ?>

<!-- ══════════════════════════════════════════════
     SIDEBAR (offcanvas en mobile, fija en desktop)
     ══════════════════════════════════════════════ -->
<aside class="app-sidebar" id="appSidebar" aria-label="Menú principal">

    <div class="sidebar-brand-area">
        <a href="<?php echo $_homeUrl; ?>" class="sidebar-brand-link">
            <div class="sidebar-brand-icon">
                <img src="<?php echo htmlspecialchars($_brandLogoUrl); ?>" alt="<?php echo htmlspecialchars($_brandName); ?>" class="sidebar-brand-logo">
            </div>
            <div class="sidebar-brand-text">
                <span class="brand-name"><?php echo htmlspecialchars($_brandName); ?></span>
                <span class="brand-subtitle"><?php echo htmlspecialchars($_brandSubtitle); ?></span>
            </div>
        </a>
        <button class="sidebar-close-btn d-lg-none" id="sidebarCloseBtn" aria-label="Cerrar menú">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="sidebar-user-panel">
        <div class="sidebar-user-avatar-wrap">
            <img src="<?php echo htmlspecialchars($_userAvatarUrl, ENT_QUOTES, 'UTF-8'); ?>"
                 alt="<?php echo $_userName; ?>"
                 class="sidebar-user-avatar-image"
                 onerror="<?php echo $_userAvatarOnerror; ?>">
            <span class="sidebar-user-avatar-fallback" style="display:none;" aria-hidden="true"><?php echo $_userInitial; ?></span>
        </div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?php echo $_userName; ?></div>
            <div class="sidebar-user-role"><span class="sidebar-role-dot"></span><?php echo $_userRole; ?></div>
        </div>
    </div>

    <div class="sidebar-nav-wrapper">
        <nav>
            <?php if(isStaffAdmin()): ?>

            <a href="<?php echo URLROOT; ?>/admin/dashboard"
               class="sidebar-nav-link <?php echo navIsActive('/admin/dashboard'); ?>">
                <i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span>
            </a>
            <a href="<?php echo URLROOT; ?>/admin/hrAlerts"
               class="sidebar-nav-link <?php echo navIsActive('/admin/hrAlerts'); ?>">
                <i class="fas fa-fw fa-bell"></i><span>Alertas RRHH</span>
            </a>

            <?php if (isSupervisor()): ?>

            <div class="sidebar-section-title">Mi equipo</div>
            <a href="<?php echo URLROOT; ?>/admin/calendar"
               class="sidebar-nav-link <?php echo navIsActive('/admin/calendar'); ?>">
                <i class="fas fa-fw fa-calendar"></i><span>Calendario equipo</span>
            </a>
            <a href="<?php echo URLROOT; ?>/admin/requests"
               class="sidebar-nav-link <?php echo navIsActive('/admin/requests'); ?>">
                <i class="fas fa-fw fa-inbox"></i><span>Solicitudes</span>
                <?php if ($_pendingRequestsCount > 0): ?>
                    <span class="sidebar-badge"><?php echo (int)$_pendingRequestsCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo URLROOT; ?>/admin/attendance"
               class="sidebar-nav-link <?php echo navIsActive('/admin/attendance'); ?>">
                <i class="fas fa-fw fa-user-check"></i><span>Asistencia</span>
            </a>

            <?php else: ?>

            <?php if (function_exists('org_hours_registry_enabled') && org_hours_registry_enabled()): ?>
            <div class="sidebar-section-title">Registro de Horas</div>

            <a href="<?php echo URLROOT; ?>/registroHoras/vistaGeneral"
               class="sidebar-nav-link <?php echo navIsActive('/registroHoras/vistaGeneral'); ?>">
                <i class="fas fa-fw fa-table"></i><span>Vista general</span>
            </a>
            <a href="<?php echo URLROOT; ?>/registroHoras/carga"
               class="sidebar-nav-link <?php echo navIsActive('/registroHoras/carga'); ?>">
                <i class="fas fa-fw fa-clock"></i><span>Carga de horarios</span>
            </a>
            <a href="<?php echo URLROOT; ?>/registroHoras/horarios"
               class="sidebar-nav-link <?php echo navIsActive('/registroHoras/horarios'); ?>">
                <i class="fas fa-fw fa-calendar-week"></i><span>Horarios por empleado</span>
            </a>
            <a href="<?php echo URLROOT; ?>/registroHoras/duplicar"
               class="sidebar-nav-link <?php echo navIsActive('/registroHoras/duplicar'); ?>">
                <i class="fas fa-fw fa-copy"></i><span>Duplicación</span>
            </a>
            <a href="<?php echo URLROOT; ?>/registroHoras/cargaMasiva"
               class="sidebar-nav-link <?php echo navIsActive('/registroHoras/cargaMasiva'); ?>">
                <i class="fas fa-fw fa-layer-group"></i><span>Carga masiva</span>
            </a>
            <?php endif; ?>

            <div class="sidebar-section-title">Personal</div>

            <a href="<?php echo URLROOT; ?>/admin/users"
               class="sidebar-nav-link <?php echo navIsActive('/admin/users', '/admin/createUser', '/admin/editUser'); ?>">
                <i class="fas fa-fw fa-users"></i><span>Usuarios</span>
            </a>
            <a href="<?php echo URLROOT; ?>/vacationAdmin/agreements"
               class="sidebar-nav-link <?php echo navIsActive('/vacationAdmin/agreements', '/vacationAdmin/editAgreement'); ?>">
                <i class="fas fa-fw fa-umbrella-beach"></i><span>Convenios / Vacaciones</span>
            </a>
            <a href="<?php echo URLROOT; ?>/vacationAdmin/reports"
               class="sidebar-nav-link <?php echo navIsActive('/vacationAdmin/reports'); ?>">
                <i class="fas fa-fw fa-chart-pie"></i><span>Reportes vacaciones</span>
            </a>
            <a href="<?php echo URLROOT; ?>/admin/weeklyPlanner"
               class="sidebar-nav-link <?php echo navIsActive('/admin/weeklyPlanner'); ?>">
                <i class="fas fa-fw fa-calendar-alt"></i><span>Planificador Semanal</span>
            </a>
            <a href="<?php echo URLROOT; ?>/admin/calendar"
               class="sidebar-nav-link <?php echo navIsActive('/admin/calendar', '/admin/saveAttendanceJustification'); ?>">
                <i class="fas fa-fw fa-calendar"></i><span>Calendario Mensual</span>
            </a>
            <a href="<?php echo URLROOT; ?>/admin/shiftManager"
               class="sidebar-nav-link <?php echo navIsActive('/admin/shiftManager'); ?>">
                <i class="fas fa-fw fa-business-time"></i><span>Turnos</span>
            </a>
            <a href="<?php echo URLROOT; ?>/admin/templates"
               class="sidebar-nav-link <?php echo navIsActive('/admin/templates'); ?>">
                <i class="fas fa-fw fa-copy"></i><span>Plantillas</span>
            </a>
            <a href="<?php echo URLROOT; ?>/admin/holidays"
               class="sidebar-nav-link <?php echo navIsActive('/admin/holidays'); ?>">
                <i class="fas fa-fw fa-star"></i><span>Feriados</span>
            </a>

            <?php if (function_exists('learning_is_ready') && learning_is_ready()): ?>
            <div class="sidebar-section-title">Capacitación</div>
            <a href="<?php echo URLROOT; ?>/trainingAdmin/courses"
               class="sidebar-nav-link <?php echo navIsActive('/trainingAdmin'); ?>">
                <i class="fas fa-fw fa-graduation-cap"></i><span>Cursos</span>
            </a>
            <a href="<?php echo URLROOT; ?>/trainingAdmin/areas"
               class="sidebar-nav-link <?php echo navIsActive('/trainingAdmin/areas'); ?>">
                <i class="fas fa-fw fa-sitemap"></i><span>Áreas</span>
            </a>
            <a href="<?php echo URLROOT; ?>/trainingAdmin/tasks"
               class="sidebar-nav-link <?php echo navIsActive('/trainingAdmin/tasks'); ?>">
                <i class="fas fa-fw fa-tasks"></i><span>Tareas</span>
            </a>
            <a href="<?php echo URLROOT; ?>/trainingAdmin/rewards"
               class="sidebar-nav-link <?php echo navIsActive('/trainingAdmin/rewards'); ?>">
                <i class="fas fa-fw fa-gift"></i><span>Premios</span>
            </a>
            <a href="<?php echo URLROOT; ?>/trainingAdmin/reports"
               class="sidebar-nav-link <?php echo navIsActive('/trainingAdmin/reports'); ?>">
                <i class="fas fa-fw fa-chart-bar"></i><span>Reportes</span>
            </a>
            <?php if (function_exists('peer_stars_is_ready') && peer_stars_is_ready()): ?>
            <a href="<?php echo URLROOT; ?>/peerStarAdmin/ranking"
               class="sidebar-nav-link <?php echo navIsActive('/peerStarAdmin'); ?>">
                <i class="fas fa-fw fa-star text-warning"></i><span>Ranking reconocimiento</span>
            </a>
            <?php endif; ?>
            <?php endif; ?>

            <?php if (function_exists('prode_is_ready') && prode_is_ready() && !$_orgHidesPaviottiModules): ?>
            <div class="sidebar-section-title">PRODE</div>
            <a href="<?php echo URLROOT; ?>/prodeAdmin/ranking"
               class="sidebar-nav-link <?php echo navIsActive('/prodeAdmin'); ?>">
                <i class="fas fa-fw fa-futbol"></i><span>Copa del mundo 2026</span>
            </a>
            <?php endif; ?>

            <div class="sidebar-section-title">Operaciones</div>

            <?php if (!$_orgHidesPaviottiModules): ?>
            <a href="<?php echo URLROOT; ?>/ecofarma/index"
               class="sidebar-nav-link <?php echo navIsActive('/ecofarma'); ?>">
                <i class="fas fa-fw fa-pills"></i><span>Comisiones Ecofarma</span>
            </a>
            <?php endif; ?>
            <a href="<?php echo URLROOT; ?>/admin/requests"
               class="sidebar-nav-link <?php echo navIsActive('/admin/requests', '/admin/approveRequest', '/admin/rejectRequest', '/admin/editRequest'); ?>">
                <i class="fas fa-fw fa-inbox"></i>
                <span>Solicitudes</span>
                <?php if ($_pendingRequestsCount > 0): ?>
                    <span class="sidebar-badge"><?php echo (int)$_pendingRequestsCount; ?></span>
                <?php endif; ?>
            </a>
            <?php if (hasRole('admin') && function_exists('salary_advance_module_enabled') && salary_advance_module_enabled()): ?>
            <a href="<?php echo URLROOT; ?>/salaryAdvanceAdmin/index"
               class="sidebar-nav-link <?php echo navIsActive('/salaryAdvanceAdmin'); ?>">
                <i class="fas fa-fw fa-hand-holding-usd"></i>
                <span>Adelantos de sueldo</span>
                <?php if ($_pendingAdvancesCount > 0): ?>
                    <span class="sidebar-badge"><?php echo (int)$_pendingAdvancesCount; ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            <?php if (function_exists('overtime_staff_can_view') && overtime_staff_can_view()): ?>
            <a href="<?php echo URLROOT; ?>/admin/pendingOvertime"
               class="sidebar-nav-link <?php echo navIsActive('/admin/pendingOvertime', '/admin/employeeDetails', '/admin/editEntry'); ?>">
                <i class="fas fa-fw fa-clock"></i><span>Horas pendientes</span>
            </a>
            <a href="<?php echo URLROOT; ?>/admin/history"
               class="sidebar-nav-link <?php echo navIsActive('/admin/history'); ?>">
                <i class="fas fa-fw fa-history"></i><span>Historial Cierres</span>
            </a>
            <?php endif; ?>
            <?php if (function_exists('cp_tasks_is_ready') && cp_tasks_is_ready()
                && !$_orgHidesPaviottiModules
                && function_exists('cp_staff_can_view') && cp_staff_can_view()
                && function_exists('company_uses_casapav_tasks') && company_uses_casapav_tasks(adminCompanyId())): ?>
            <a href="<?php echo URLROOT; ?>/cpTaskAdmin/pending"
               class="sidebar-nav-link <?php echo navIsActive('/cpTaskAdmin/pending', '/cpTaskAdmin/closeMonth'); ?>">
                <i class="fas fa-fw fa-tasks"></i><span>Extras Casa Paviotti</span>
            </a>
            <?php if (!isSupervisor()): ?>
            <a href="<?php echo URLROOT; ?>/cpTaskAdmin/rates"
               class="sidebar-nav-link <?php echo navIsActive('/cpTaskAdmin/rates', '/cpTaskAdmin/catalogs'); ?>">
                <i class="fas fa-fw fa-dollar-sign"></i><span>Tarifas CP</span>
            </a>
            <a href="<?php echo URLROOT; ?>/cpTaskAdmin/rateIncrease"
               class="sidebar-nav-link <?php echo navIsActive('/cpTaskAdmin/rateIncrease'); ?>">
                <i class="fas fa-fw fa-percent"></i><span>Aumento % CP</span>
            </a>
            <a href="<?php echo URLROOT; ?>/cpTaskAdmin/reports"
               class="sidebar-nav-link <?php echo navIsActive('/cpTaskAdmin/reports', '/cpTaskAdmin/history', '/cpTaskAdmin/closureDetail'); ?>">
                <i class="fas fa-fw fa-chart-bar"></i><span>Reportes CP</span>
            </a>
            <?php endif; ?>
            <?php endif; ?>
            <a href="<?php echo URLROOT; ?>/admin/suggestions"
               class="sidebar-nav-link <?php echo navIsActive('/admin/suggestions'); ?>">
                <i class="fas fa-fw fa-lightbulb"></i><span>Sugerencias</span>
            </a>

            <div class="sidebar-section-title">Sistema</div>

            <?php if (!$_orgIsModerna): // Infraestructura de relojes/multiempresa de Paviotti; Moderna tendrá su propio sync (CrossChex) ?>
            <a href="<?php echo URLROOT; ?>/admin/sync"
               class="sidebar-nav-link <?php echo navIsActive('/admin/sync', '/admin/runApiSync'); ?>">
                <i class="fas fa-fw fa-sync-alt"></i><span>Sincronización</span>
            </a>
            <a href="<?php echo URLROOT; ?>/admin/mapeoApi"
               class="sidebar-nav-link <?php echo navIsActive('/admin/mapeoApi', '/admin/saveMappingFromApi', '/admin/deleteMappingFromApi'); ?>">
                <i class="fas fa-fw fa-link"></i><span>Mapeo de Relojes</span>
            </a>
            <a href="<?php echo URLROOT; ?>/admin/mapeoIncompleto"
               class="sidebar-nav-link <?php echo navIsActive('/admin/mapeoIncompleto'); ?>">
                <i class="fas fa-fw fa-unlink"></i><span>Mapeo incompleto</span>
            </a>
            <a href="<?php echo URLROOT; ?>/admin/marcacionesTodas"
               class="sidebar-nav-link <?php echo navIsActive('/admin/marcacionesTodas', '/admin/marcaciones', '/admin/clockingsReport'); ?>">
                <i class="fas fa-fw fa-fingerprint"></i><span>Marcaciones</span>
            </a>
            <a href="<?php echo URLROOT; ?>/admin/companies"
               class="sidebar-nav-link <?php echo navIsActive('/admin/companies'); ?>">
                <i class="fas fa-fw fa-building"></i><span>Empresas</span>
            </a>
            <?php endif; ?>
            <?php if (function_exists('notifications_is_ready') && notifications_is_ready()): ?>
            <a href="<?php echo URLROOT; ?>/notificationsAdmin"
               class="sidebar-nav-link <?php echo navIsActive('/notificationsAdmin'); ?>">
                <i class="fas fa-fw fa-bell"></i><span>Notificaciones</span>
            </a>
            <?php endif; ?>
            <?php if (isAdmin() && function_exists('system_settings_ready') && system_settings_ready()): ?>
            <a href="<?php echo URLROOT; ?>/systemConfig"
               class="sidebar-nav-link <?php echo navIsActive('/systemConfig'); ?>">
                <i class="fas fa-fw fa-cog"></i><span>Configuración</span>
            </a>
            <?php endif; ?>

            <?php endif; /* fin admin completo */ ?>
            <?php endif; /* fin isStaffAdmin */ ?>
            <?php if(hasRole('empleado')): ?>

            <div class="sidebar-section-title">Mi Espacio</div>

            <a href="<?php echo URLROOT; ?>/employee/index"
               class="sidebar-nav-link <?php echo navIsActive('/employee/index'); ?>">
                <i class="fas fa-fw fa-home"></i><span>Inicio</span>
            </a>
            <?php if (function_exists('employee_portal_show_cp_extras_nav') && employee_portal_show_cp_extras_nav()): ?>
            <a href="<?php echo URLROOT; ?>/cpTask/index"
               class="sidebar-nav-link <?php echo navIsActive('/cpTask'); ?>">
                <i class="fas fa-fw fa-clipboard-list"></i><span>Cargar Extras</span>
            </a>
            <?php endif; ?>
            <?php if (function_exists('employee_portal_can') && employee_portal_can('mi_mes')): ?>
            <a href="<?php echo URLROOT; ?>/employee/miMes"
               class="sidebar-nav-link <?php echo navIsActive('/employee/miMes'); ?>">
                <i class="fas fa-fw fa-calendar"></i><span>Mi mes</span>
            </a>
            <?php endif; ?>
            <?php if (function_exists('prode_portal_is_open') && prode_portal_is_open()): ?>
            <a href="<?php echo URLROOT; ?>/prode/index"
               class="sidebar-nav-link <?php echo navIsActive('/prode'); ?>">
                <i class="fas fa-fw fa-futbol"></i><span>Copa del mundo 2026</span>
            </a>
            <?php endif; ?>
            <?php if (function_exists('peer_stars_is_ready') && peer_stars_is_ready() && employee_portal_can('peer_stars')): ?>
            <a href="<?php echo URLROOT; ?>/peerStar/index"
               class="sidebar-nav-link <?php echo navIsActive('/peerStar'); ?>">
                <i class="fas fa-fw fa-star text-warning"></i><span>Reconocer compañeros</span>
            </a>
            <?php endif; ?>
            <a href="<?php echo URLROOT; ?>/employee/misHorarios"
               class="sidebar-nav-link <?php echo navIsActive('/employee/misHorarios'); ?>">
                <i class="fas fa-fw fa-calendar-alt"></i><span>Mis Horarios</span>
            </a>
            <a href="<?php echo URLROOT; ?>/request/index"
               class="sidebar-nav-link <?php echo navIsActive('/request/index'); ?>">
                <i class="fas fa-fw fa-file-alt"></i><span>Mis Solicitudes</span>
            </a>
            <?php if (function_exists('salary_advance_module_enabled') && salary_advance_module_enabled() && employee_portal_can('salary_advance')): ?>
            <a href="<?php echo URLROOT; ?>/salaryAdvance/index"
               class="sidebar-nav-link <?php echo navIsActive('/salaryAdvance'); ?>">
                <i class="fas fa-fw fa-hand-holding-usd"></i><span>Adelanto de sueldo</span>
            </a>
            <?php endif; ?>
            <?php if (function_exists('employee_portal_show_overtime_nav') && employee_portal_show_overtime_nav()): ?>
            <a href="<?php echo URLROOT; ?>/employee/dashboard"
               class="sidebar-nav-link <?php echo navIsActive('/employee/dashboard'); ?>">
                <i class="fas fa-fw fa-plus-circle"></i><span>Horas Extras</span>
            </a>
            <?php endif; ?>
            <a href="<?php echo URLROOT; ?>/employee/profile"
               class="sidebar-nav-link <?php echo navIsActive('/employee/profile'); ?>">
                <i class="fas fa-fw fa-user"></i><span>Mi perfil</span>
            </a>
            <?php if (function_exists('employee_portal_can') && employee_portal_can('suggestions')): ?>
            <a href="<?php echo URLROOT; ?>/suggestion/index"
               class="sidebar-nav-link <?php echo navIsActive('/suggestion/index'); ?>">
                <i class="fas fa-fw fa-comment-alt"></i><span>Sugerencias</span>
            </a>
            <?php endif; ?>
            <?php if (function_exists('surveys_is_ready') && surveys_is_ready() && employee_portal_can('surveys')): ?>
            <a href="<?php echo URLROOT; ?>/survey/index"
               class="sidebar-nav-link <?php echo navIsActive('/survey'); ?>">
                <i class="fas fa-fw fa-poll"></i><span>Encuestas</span>
            </a>
            <?php endif; ?>
            <?php if (function_exists('notifications_is_ready') && notifications_is_ready() && employee_portal_can('pay_stubs')
                && !(function_exists('org_hides_pay_stubs') && org_hides_pay_stubs())): ?>
            <a href="<?php echo URLROOT; ?>/employee/payStubs"
               class="sidebar-nav-link <?php echo navIsActive('/employee/payStubs', '/employee/payStubSign'); ?>">
                <i class="fas fa-fw fa-file-invoice-dollar"></i><span>Mis recibos</span>
            </a>
            <?php endif; ?>

            <?php if (function_exists('learning_is_ready') && learning_is_ready() && employee_portal_can('training')): ?>
            <div class="sidebar-section-title">Aprendizaje</div>
            <a href="<?php echo URLROOT; ?>/training/index"
               class="sidebar-nav-link <?php echo navIsActive('/training'); ?>">
                <i class="fas fa-fw fa-graduation-cap"></i><span>Mis cursos</span>
            </a>
            <a href="<?php echo URLROOT; ?>/training/tasks"
               class="sidebar-nav-link <?php echo navIsActive('/training/tasks'); ?>">
                <i class="fas fa-fw fa-tasks"></i><span>Mis tareas</span>
            </a>
            <a href="<?php echo URLROOT; ?>/training/stars"
               class="sidebar-nav-link <?php echo navIsActive('/training/stars'); ?>">
                <i class="fas fa-fw fa-star"></i><span>Estrellas y premios</span>
            </a>
            <?php endif; ?>

            <?php endif; ?>
        </nav>
    </div>

    <div class="sidebar-footer">
        <?php // Vuelta al modo Paviotti mientras el simulador (demo) está activo; desaparece con employee_group real. ?>
        <?php if (function_exists('org_simulator_available') && org_simulator_available() && $_orgIsModerna): ?>
        <form method="post" action="<?php echo URLROOT; ?>/registroHoras/setOrg" class="mb-1">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="org_group" value="paviotti">
            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="sidebar-logout-btn w-100" style="background:none;border:0;text-align:left;">
                <i class="fas fa-fw fa-arrow-right-arrow-left"></i><span>Vista Paviotti (demo)</span>
            </button>
        </form>
        <?php endif; ?>
        <a href="<?php echo URLROOT; ?>/login/logout" class="sidebar-logout-btn">
            <i class="fas fa-fw fa-sign-out-alt"></i><span>Cerrar Sesión</span>
        </a>
    </div>
</aside>
<!-- Overlay oscuro para mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ══════════════════════════════════════════════
     CONTENT WRAPPER
     ══════════════════════════════════════════════ -->
<div id="contentWrapper">

    <header class="app-topbar">
        <div class="topbar-left">
            <button class="topbar-toggle d-lg-none"
                    id="sidebarMobileToggle"
                    aria-label="Abrir menú">
                <i class="fas fa-bars"></i>
            </button>
            <button class="topbar-toggle d-none d-lg-flex"
                    id="sidebarDesktopToggle" aria-label="Colapsar menú">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div class="topbar-right">
            <?php // Selector de organización (demo): solo en la vista Paviotti — Moderna unifica y no muestra selectores. ?>
            <?php if (function_exists('org_simulator_available') && org_simulator_available() && !$_orgIsModerna): ?>
            <form method="post" action="<?php echo URLROOT; ?>/registroHoras/setOrg" class="topbar-company-form d-none d-md-flex align-items-center me-2" title="Simulador de organización (fase maqueta): define qué vista de la Suite P&amp;M se muestra">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="return_url" value="<?php echo htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                <label class="text-muted small me-1 mb-0" for="topbarOrgSelect">Organización</label>
                <select name="org_group" id="topbarOrgSelect" class="form-select form-select-sm" style="max-width:170px" onchange="this.form.submit()">
                    <option value="paviotti" <?php echo org_current_group() === 'paviotti' ? 'selected' : ''; ?>>Paviotti/Ecofarma</option>
                    <option value="moderna" <?php echo org_current_group() === 'moderna' ? 'selected' : ''; ?>>Moderna</option>
                </select>
                <span class="badge bg-secondary ms-1" title="Se reemplaza por users.employee_group al integrar">demo</span>
            </form>
            <?php endif; ?>
            <?php if (hasRole('admin')):
                $_companySwitcher = (new Company())->getAllCompanies();
                // Suite P&M: la empresa Moderna no aparece en el selector de Paviotti.
                if (function_exists('org_moderna_company_id') && org_moderna_company_id()) {
                    $_companySwitcher = array_values(array_filter($_companySwitcher, function ($c) {
                        return (int)$c->id !== org_moderna_company_id();
                    }));
                }
                $_activeCompanyId = (int)($_SESSION['user_company_id'] ?? 0);
            ?>
            <?php if (!$_orgIsModerna): // Moderna unifica: sin selector de empresa ?>
            <form method="post" action="<?php echo URLROOT; ?>/admin/setCompany" class="topbar-company-form d-none d-md-flex align-items-center me-2">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="return_url" value="<?php echo htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                <label class="text-muted small me-1 mb-0" for="topbarCompanySelect">Empresa</label>
                <select name="company_id" id="topbarCompanySelect" class="form-select form-select-sm" style="max-width:200px" onchange="this.form.submit()">
                    <?php if ($_activeCompanyId <= 0): ?>
                    <option value="" selected disabled>Elegir…</option>
                    <?php endif; ?>
                    <?php foreach ($_companySwitcher as $_co): ?>
                    <option value="<?php echo (int)$_co->id; ?>" <?php echo (int)$_co->id === $_activeCompanyId ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($_co->name); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
            <div class="dropdown topbar-notify">
                <button type="button"
                        class="topbar-notify-btn dropdown-toggle<?php echo $_adminNotifyTotalCount > 0 ? ' has-alerts' : ''; ?>"
                        id="topbarNotifyBtn"
                        data-bs-toggle="dropdown"
                        data-bs-auto-close="outside"
                        aria-expanded="false"
                        aria-label="Solicitudes pendientes"
                        title="Solicitudes pendientes de revisión">
                    <i class="fas fa-bell"></i>
                    <?php if ($_adminNotifyTotalCount > 0): ?>
                    <span class="topbar-notify-badge"><?php echo $_adminNotifyTotalCount > 99 ? '99+' : (int)$_adminNotifyTotalCount; ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end topbar-notify-menu" aria-labelledby="topbarNotifyBtn">
                    <div class="topbar-notify-menu-head">
                        <strong>Solicitudes pendientes</strong>
                        <?php if ($_adminNotifyTotalCount > 0): ?>
                        <span class="badge bg-warning text-dark"><?php echo (int)$_adminNotifyTotalCount; ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($_adminNotifyTotalCount === 0): ?>
                    <div class="topbar-notify-empty">
                        <i class="fas fa-check-circle text-success me-2"></i>Sin solicitudes por revisar
                    </div>
                    <?php else: ?>
                    <div class="topbar-notify-list">
                        <?php foreach ($_pendingRequestsPreview as $req):
                            $reqEnd = $req->end_date ? date('d/m', strtotime($req->end_date)) : date('d/m', strtotime($req->start_date));
                            $reqRange = date('d/m', strtotime($req->start_date));
                            if ($req->end_date && $req->end_date !== $req->start_date) {
                                $reqRange .= ' – ' . $reqEnd;
                            }
                        ?>
                        <?php
                        $notifyHref = !empty($req->is_shift_swap)
                            ? URLROOT . '/admin/requests#shift-swaps'
                            : (!empty($req->is_salary_advance)
                                ? URLROOT . '/salaryAdvanceAdmin/index?open=' . (int)$req->advance_id
                                : URLROOT . '/admin/requests?open=' . (int)$req->id);
                        ?>
                        <a href="<?php echo $notifyHref; ?>" class="topbar-notify-item">
                            <span class="topbar-notify-item-avatar"><?php echo mb_strtoupper(mb_substr($req->full_name, 0, 1, 'UTF-8'), 'UTF-8'); ?></span>
                            <span class="topbar-notify-item-body">
                                <span class="topbar-notify-item-name"><?php echo htmlspecialchars($req->full_name); ?></span>
                                <span class="topbar-notify-item-meta"><?php echo htmlspecialchars($req->type_name); ?> · <?php echo $reqRange; ?></span>
                            </span>
                            <span class="topbar-notify-item-pill">Pendiente</span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($_adminNotifyTotalCount > count($_pendingRequestsPreview)): ?>
                    <div class="topbar-notify-more text-muted small px-3 py-1">
                        +<?php echo $_adminNotifyTotalCount - count($_pendingRequestsPreview); ?> más…
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    <div class="topbar-notify-footer">
                        <a href="<?php echo URLROOT; ?>/admin/requests" class="btn btn-sm btn-primary w-100">
                            <?php echo $_pendingRequestsCount > 0 ? 'Revisar solicitudes' : 'Ir a solicitudes'; ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (hasRole('empleado') && function_exists('notifications_is_ready') && notifications_is_ready()): ?>
            <?php require APPROOT . '/views/inc/partials/employee_notify_dropdown.php'; ?>
            <?php endif; ?>
            <div class="topbar-user">
                <?php if (hasRole('empleado')): ?>
                <a href="<?php echo URLROOT; ?>/employee/profile" class="topbar-avatar-shell text-decoration-none" title="Mi perfil">
                <?php else: ?>
                <div class="topbar-avatar-shell" title="<?php echo $_userName; ?>">
                <?php endif; ?>
                    <img src="<?php echo htmlspecialchars($_userAvatarUrl, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo $_userName; ?>"
                         class="topbar-avatar-image"
                         onerror="<?php echo $_userAvatarOnerror; ?>">
                    <div class="topbar-avatar-initial" style="display:none;"><?php echo $_userInitial; ?></div>
                <?php echo hasRole('empleado') ? '</a>' : '</div>'; ?>
                <div class="topbar-user-info d-none d-sm-flex">
                    <span class="topbar-user-name"><?php echo $_userName; ?></span>
                    <span class="topbar-user-role"><i class="fas fa-crown me-1" style="font-size:.6rem;color:var(--clr-warning);"></i><?php echo $_userRole; ?></span>
                </div>
            </div>
            <a href="<?php echo URLROOT; ?>/login/logout"
               class="topbar-logout-btn"
               title="Cerrar sesión"
               aria-label="Cerrar sesión">
                <i class="fas fa-sign-out-alt"></i>
                <span class="topbar-logout-label d-none d-md-inline">Salir</span>
            </a>
        </div>
    </header>

    <?php if(isset($_SESSION['flash_success']) || !empty($_SESSION['flash_success_html'])): ?>
    <div class="flash-msg flash-success" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php
        if (!empty($_SESSION['flash_success_html'])) {
            echo $_SESSION['flash_success_html'];
            unset($_SESSION['flash_success_html']);
        } elseif (isset($_SESSION['flash_success'])) {
            echo htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8');
            unset($_SESSION['flash_success']);
        }
        if (!empty($_SESSION['flash_success_link'])) {
            $fl = $_SESSION['flash_success_link'];
            echo ' <a href="' . htmlspecialchars($fl['url'], ENT_QUOTES, 'UTF-8') . '" class="alert-link fw-semibold">'
               . htmlspecialchars($fl['text'], ENT_QUOTES, 'UTF-8') . '</a>';
            unset($_SESSION['flash_success_link']);
        }
        ?>
    </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['flash_error'])): ?>
    <div class="flash-msg flash-error" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
    </div>
    <?php endif; ?>

    <main class="page-content">

<?php else: ?>
<main class="login-wrapper">
<?php endif; ?>