<?php

/** Visibilidad de funciones del portal empleado (system_settings, grupo employee). */

function employee_portal_can($feature) {
    static $map = [
        'overtime' => 'employee_show_overtime',
        'overtime_home' => 'employee_show_overtime_on_home',
        'cp_extras' => 'employee_show_cp_extras',
        'cp_extras_home' => 'employee_show_cp_extras_on_home',
        'vacation_balance' => 'employee_show_vacation_balance',
        'pay_stubs' => 'employee_show_pay_stubs',
        'training' => 'employee_show_training',
        'peer_stars' => 'employee_show_peer_stars',
        'surveys' => 'employee_show_surveys',
        'suggestions' => 'employee_show_suggestions',
        'mi_mes' => 'employee_show_mi_mes',
        'prode' => 'employee_show_prode',
        'salary_advance' => 'employee_show_salary_advance',
    ];
    if (!isset($map[$feature])) {
        return false;
    }
    if (!function_exists('setting_bool')) {
        return false;
    }
    if (!setting_bool($map[$feature], true)) {
        return false;
    }
    // Suite P&M: la organización Moderna no usa recibos de sueldo
    // (oculta menú, bloquea rutas via require_employee_portal_feature
    // y filtra las notificaciones de tipo pay_stub).
    if ($feature === 'pay_stubs' && function_exists('org_hides_pay_stubs') && org_hides_pay_stubs()) {
        return false;
    }
    if ($feature === 'overtime' && function_exists('overtime_empleado_scope_allowed')) {
        return overtime_empleado_scope_allowed();
    }
    if ($feature === 'cp_extras') {
        if (!function_exists('current_user_uses_casapav_tasks') || !current_user_uses_casapav_tasks()) {
            return false;
        }
        if (function_exists('cp_scope_enabled_for_user') && function_exists('isLoggedIn') && isLoggedIn()) {
            $user = (new User())->getUserById((int)$_SESSION['user_id']);
            return $user ? cp_scope_enabled_for_user($user) : false;
        }
    }
    return true;
}

function require_employee_portal_feature($feature, $redirectTo = 'employee/index') {
    if (!employee_portal_can($feature)) {
        $_SESSION['flash_error'] = 'Esta sección no está disponible en el portal de empleados.';
        redirect($redirectTo);
    }
}

/** Horas extras clásicas (no Casa Paviotti). */
function employee_portal_show_overtime_nav() {
    if (function_exists('current_user_uses_casapav_tasks') && current_user_uses_casapav_tasks()) {
        return false;
    }
    return employee_portal_can('overtime');
}

/** Extras por tarea Casa Paviotti. */
function employee_portal_show_cp_extras_nav() {
    if (!function_exists('current_user_uses_casapav_tasks') || !current_user_uses_casapav_tasks()) {
        return false;
    }
    if (!function_exists('cp_tasks_is_ready') || !cp_tasks_is_ready()) {
        return false;
    }
    return employee_portal_can('cp_extras');
}

function employee_portal_all_settings() {
    return [
        'employee_show_overtime' => ['label' => 'Módulo Horas extras (empleado)', 'hint' => 'Menú, /employee/dashboard y carga de horas. También debe estar habilitado en la empresa/área del empleado.'],
        'employee_show_overtime_on_home' => ['label' => 'Horas extras en inicio', 'hint' => 'Tarjeta estadística y listado reciente en /employee/index.'],
        'employee_show_cp_extras' => ['label' => 'Módulo Extras Casa Paviotti', 'hint' => 'Menú Cargar Extras y /cpTask (solo empresas CP).'],
        'employee_show_cp_extras_on_home' => ['label' => 'Extras CP en inicio', 'hint' => 'Banner y tarjeta pendientes en /employee/index.'],
        'employee_show_vacation_balance' => ['label' => 'Saldo de vacaciones', 'hint' => 'Días pendientes en inicio, solicitudes y Mi mes.'],
        'employee_show_pay_stubs' => ['label' => 'Recibos de sueldo', 'hint' => 'Menú Mis recibos y firma de recibos.'],
        'employee_show_training' => ['label' => 'Capacitación', 'hint' => 'Sección Aprendizaje en el menú.'],
        'employee_show_peer_stars' => ['label' => 'Reconocimiento entre pares', 'hint' => 'Menú Reconocer compañeros.'],
        'employee_show_surveys' => ['label' => 'Encuestas', 'hint' => 'Menú Encuestas.'],
        'employee_show_suggestions' => ['label' => 'Sugerencias', 'hint' => 'Menú y acceso rápido Sugerencias.'],
        'employee_show_mi_mes' => ['label' => 'Mi mes (calendario)', 'hint' => 'Vista calendario /employee/miMes.'],
        'employee_show_prode' => ['label' => 'Copa del mundo 2026', 'hint' => 'Pronósticos fase de grupos /prode.'],
        'employee_show_salary_advance' => ['label' => 'Adelanto de sueldo', 'hint' => 'Menú y solicitudes en /salaryAdvance.'],
    ];
}

/** Aplica filtros de visibilidad solo en sesión empleado (no en admin). */
function employee_portal_apply_day_visibility(array $day) {
    if (!employee_portal_should_filter_for_session()) {
        return $day;
    }
    $planned = $day['planned'] ?? [];
    if (!employee_portal_can('overtime')) {
        $planned = array_values(array_filter($planned, function ($block) {
            return ($block->type ?? '') !== 'overtime';
        }));
        $day['planned'] = $planned;
        $day['has_planned'] = !empty($planned);
    }
    if (!employee_portal_can('cp_extras')) {
        $day['cp_tasks'] = [];
    }
    return $day;
}

function employee_portal_should_filter_for_session() {
    return function_exists('hasRole') && hasRole('empleado');
}

function employee_portal_filter_schedule_entries(array $entries) {
    if (!employee_portal_should_filter_for_session() || employee_portal_can('overtime')) {
        return $entries;
    }
    return array_values(array_filter($entries, function ($entry) {
        return ($entry->type ?? '') !== 'overtime';
    }));
}

function employee_portal_normalize_path($url) {
    $url = trim((string)$url);
    if ($url === '' || $url === '#') {
        return '';
    }
    $root = rtrim(defined('URLROOT') ? URLROOT : '', '/');
    if ($root !== '' && strpos($url, $root) === 0) {
        $url = substr($url, strlen($root));
    }
    if ($url !== '' && $url[0] !== '/') {
        $url = '/' . $url;
    }
    $q = strpos($url, '?');
    if ($q !== false) {
        $url = substr($url, 0, $q);
    }
    return $url;
}

/** Rutas del portal que exigen un módulo visible. */
function employee_portal_path_feature_map() {
    return [
        '/employee/payStub' => 'pay_stubs',
        '/employee/payStubs' => 'pay_stubs',
        '/cpTask' => 'cp_extras',
        '/employee/dashboard' => 'overtime',
        '/training' => 'training',
        '/survey' => 'surveys',
        '/suggestion' => 'suggestions',
        '/peerStar' => 'peer_stars',
        '/employee/miMes' => 'mi_mes',
        '/prode' => 'prode',
        '/salaryAdvance' => 'salary_advance',
    ];
}

function employee_portal_path_allowed($path) {
    $path = employee_portal_normalize_path($path);
    if ($path === '') {
        return true;
    }
    foreach (employee_portal_path_feature_map() as $prefix => $feature) {
        if ($path === $prefix || strpos($path, $prefix . '/') === 0) {
            return employee_portal_can($feature);
        }
    }
    return true;
}

function employee_portal_notification_field($notification, $key, $default = '') {
    if (is_object($notification)) {
        return $notification->$key ?? $default;
    }
    if (is_array($notification)) {
        return $notification[$key] ?? $default;
    }
    return $default;
}

function employee_portal_notification_visible($notification) {
    if (!employee_portal_should_filter_for_session()) {
        return true;
    }
    $type = employee_portal_notification_field($notification, 'type', 'manual');
    $typeMap = [
        'pay_stub' => 'pay_stubs',
        'survey' => 'surveys',
        'course' => 'training',
    ];
    if (isset($typeMap[$type]) && !employee_portal_can($typeMap[$type])) {
        return false;
    }
    $link = employee_portal_notification_field($notification, 'link_url', '');
    if ($link !== '' && !employee_portal_path_allowed($link)) {
        return false;
    }
    return true;
}

function employee_portal_filter_notifications(array $items) {
    return array_values(array_filter($items, 'employee_portal_notification_visible'));
}

function employee_portal_count_visible_unread($userId) {
    if (!function_exists('notifications_is_ready') || !notifications_is_ready()) {
        return 0;
    }
    $model = new UserNotification();
    $count = 0;
    foreach ($model->getAllForUser((int)$userId) as $n) {
        if (empty($n->read_at) && employee_portal_notification_visible($n)) {
            $count++;
        }
    }
    return $count;
}

function employee_portal_announcement_visible(array $announcement) {
    if (!employee_portal_should_filter_for_session()) {
        return true;
    }
    $link = $announcement['link_url'] ?? '';
    return $link === '' || employee_portal_path_allowed($link);
}
