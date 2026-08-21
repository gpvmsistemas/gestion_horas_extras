<?php

/**
 * Asegura que el admin tenga company_id en sesión (desde su usuario).
 */
function ensureAdminCompanySession() {
    if (!isLoggedIn() || !isStaffAdmin()) {
        return;
    }
    if (!empty($_SESSION['user_company_id'])) {
        return;
    }
    $userModel = new User();
    $admin = $userModel->getUserById((int)$_SESSION['user_id']);
    if ($admin && !empty($admin->company_id)) {
        $_SESSION['user_company_id'] = (int)$admin->company_id;
        $companyModel = new Company();
        $_SESSION['user_company_name'] = $companyModel->getNameById($admin->company_id);
    }
}

/**
 * ID de empresa activa del admin en sesión.
 */
function adminCompanyId() {
    ensureAdminCompanySession();
    return isset($_SESSION['user_company_id']) ? (int)$_SESSION['user_company_id'] : 0;
}

/** Sucursal operativa elegida dentro de la empresa activa (0 = todas). */
function adminBranchId() {
    $branchId = (int)($_SESSION['admin_branch_id'] ?? 0);
    $companyId = adminCompanyId();
    if ($branchId <= 0 || $companyId <= 0) return 0;
    if (!(new Company())->getBranchByIdForCompany($branchId, $companyId, true)) {
        unset($_SESSION['admin_branch_id']);
        return 0;
    }
    return $branchId;
}

/**
 * Exige empresa en sesión; redirige si falta.
 */
function requireAdminCompany($redirectTo = 'admin/users') {
    $companyId = adminCompanyId();
    if ($companyId <= 0) {
        $_SESSION['flash_error'] = 'Tu usuario admin no tiene empresa asignada. Asignala en Usuarios o elegí una empresa activa.';
        redirect($redirectTo);
    }
    return $companyId;
}

/**
 * Cambia la empresa operativa del admin (multi-empresa).
 */
function setAdminActiveCompany($companyId) {
    $companyId = (int)$companyId;
    if ($companyId <= 0) {
        return false;
    }
    $companyModel = new Company();
    if (!$companyModel->getById($companyId)) {
        return false;
    }
    if (function_exists('access_control_ready') && access_control_ready()
        && !access_user_can_manage_company((int)$_SESSION['user_id'], $companyId)) {
        return false;
    }
    if (isSupervisor()) {
        $admin = (new User())->getUserById((int)($_SESSION['user_id'] ?? 0));
        if (!$admin || (int)($admin->company_id ?? 0) !== $companyId) {
            return false;
        }
    }
    $_SESSION['user_company_id'] = $companyId;
    $_SESSION['user_company_name'] = $companyModel->getNameById($companyId);
    if ((int)($_SESSION['admin_branch_id'] ?? 0) > 0
        && !$companyModel->getBranchByIdForCompany((int)$_SESSION['admin_branch_id'], $companyId, true)) {
        unset($_SESSION['admin_branch_id']);
    }
    return true;
}

/**
 * Valida return_url post-cambio de empresa: solo rutas internas bajo URLROOT.
 */
function admin_safe_return_path($returnUrl, $default = 'admin/dashboard') {
    $returnUrl = trim((string)$returnUrl);
    if ($returnUrl === '') {
        return $default;
    }
    $base = rtrim(URLROOT, '/');
    if (strpos($returnUrl, $base . '/') !== 0 && $returnUrl !== $base) {
        return $default;
    }
    $path = substr($returnUrl, strlen($base));
    if ($path === '' || $path[0] !== '/') {
        return $default;
    }
    if (strpos($path, '//') !== false || strpos($path, '..') !== false) {
        return $default;
    }
    return ltrim($path, '/');
}

/**
 * Evita volver, tras cambiar de empresa, a una entidad identificada en el
 * contexto anterior. Las pantallas de colección conservan sus filtros.
 */
function admin_company_switch_return_path($returnUrl) {
    $path = admin_safe_return_path($returnUrl, 'admin/dashboard');
    $pathOnly = parse_url('/' . ltrim($path, '/'), PHP_URL_PATH) ?: '/';
    $contextRoutes = [
        '#^/admin/(?:employeeProfile|employeeDetails|editUser|editEntry|editRequest)/#' => 'admin/users',
        '#^/admin/editCompany/#' => 'admin/companies',
        '#^/vacationAdmin/(?:vacationSetup|editAgreement)/#' => 'vacationAdmin/agreements',
        '#^/salaryAdvanceAdmin/(?:installments|history|receipt)/#' => 'salaryAdvanceAdmin/index',
        '#^/cpTaskAdmin/closureDetail/#' => 'cpTaskAdmin/reports',
        '#^/trainingAdmin/(?:courseEdit|previewLesson)/#' => 'trainingAdmin/courses',
        '#^/surveyAdmin/(?:edit|results)/#' => 'surveyAdmin/index',
    ];
    foreach ($contextRoutes as $pattern => $fallback) {
        if (preg_match($pattern, $pathOnly)) return $fallback;
    }
    return $path;
}

function userBelongsToCompany($userId, $companyId) {
    $userId = (int)$userId;
    $companyId = (int)$companyId;
    if ($userId <= 0 || $companyId <= 0) {
        return false;
    }
    $user = (new User())->getUserById($userId);
    return $user && (int)$user->company_id === $companyId;
}

function requireUserInAdminCompany($userId, $redirectTo = 'admin/users') {
    $companyId = requireAdminCompany($redirectTo);
    if (!userBelongsToCompany($userId, $companyId)) {
        $_SESSION['flash_error'] = 'No tenés permiso para operar sobre ese usuario.';
        redirect($redirectTo);
    }
    return $companyId;
}

/**
 * Carga un usuario para ficha/acciones admin. Si el listado es multi-empresa y el
 * empleado es de otra empresa, alinea la empresa activa en sesión (evita redirect a /admin/users).
 */
function supervisorAreaId() {
    return isSupervisor() ? (int)($_SESSION['user_area_id'] ?? 0) : 0;
}

function requireAdminOnly($redirectTo = 'admin/dashboard') {
    if (!isLoggedIn() || !isStaffAdmin()) {
        redirect('login');
    }
    if (function_exists('access_control_ready') && access_control_ready()
        && !in_array(access_current_role(), ['administrador', 'rrhh'], true)) {
        $_SESSION['flash_error'] = 'Esta acción requiere perfil Administrador o RRHH.';
        redirect($redirectTo);
    }
    if (isSupervisor()) {
        $_SESSION['flash_error'] = 'Esta acción solo está disponible para administradores RRHH.';
        redirect($redirectTo);
    }
}

function supervisorCanAccessUser($user) {
    if (!$user || !isStaffAdmin()) {
        return false;
    }
    if ((int)($user->company_id ?? 0) !== adminCompanyId()) {
        return false;
    }
    if (function_exists('access_control_ready') && access_control_ready()) {
        $role = access_current_role();
        if ($role === 'administrador' || $role === 'rrhh' || $role === 'coordinador') {
            return access_can_manage_company((int)($user->company_id ?? 0), 0);
        }
        if ($role === 'encargado') {
            $scope = access_current_scope();
            return $scope && (int)($scope->company_id ?? 0) === (int)($user->company_id ?? 0)
                && (int)($scope->branch_id ?? 0) > 0 && (new User())->isUserAssignedToBranch((int)$user->id, (int)$scope->branch_id);
        }
        return false;
    }
    if (!isSupervisor()) {
        return true;
    }
    $areaId = supervisorAreaId();
    if ($areaId <= 0) {
        return true;
    }
    return (int)($user->area_id ?? 0) === $areaId;
}

function requireSupervisorUserAccess($userId, $redirectTo = 'admin/dashboard') {
    requireAdminCompany($redirectTo);
    $user = (new User())->getUserById((int)$userId);
    if (!$user || !supervisorCanAccessUser($user)) {
        $_SESSION['flash_error'] = 'No tenés permiso para ver este empleado.';
        redirect($redirectTo);
    }
    return $user;
}

function filterStaffRowsByUserArea(array $rows) {
    if (!isSupervisor()) {
        return $rows;
    }
    $areaId = supervisorAreaId();
    if ($areaId <= 0) {
        return $rows;
    }
    $userModel = new User();
    return array_values(array_filter($rows, function ($r) use ($areaId, $userModel) {
        $uid = (int)($r->user_id ?? 0);
        if ($uid <= 0) {
            return false;
        }
        $u = $userModel->getUserById($uid);
        return $u && (int)($u->area_id ?? 0) === $areaId;
    }));
}

function filterEmployeesForStaff(array $employees) {
    if (!isSupervisor()) {
        return $employees;
    }
    $areaId = supervisorAreaId();
    if ($areaId <= 0) {
        return $employees;
    }
    return array_values(array_filter($employees, function ($u) use ($areaId) {
        return (int)($u->area_id ?? 0) === $areaId;
    }));
}

function adminResolveUser($userId, $redirectTo = 'admin/users') {
    $userId = (int)$userId;
    if ($userId <= 0) {
        redirect($redirectTo);
    }
    $user = (new User())->getUserById($userId);
    if (!$user) {
        $_SESSION['flash_error'] = 'Usuario no encontrado.';
        redirect($redirectTo);
    }
    if (isSupervisor() && !supervisorCanAccessUser($user)) {
        $_SESSION['flash_error'] = 'No tenés permiso para ver este empleado.';
        redirect($redirectTo);
    }
    $userCompany = (int)($user->company_id ?? 0);
    if ($userCompany > 0) {
        $activeCompany = adminCompanyId();
        if ($activeCompany !== $userCompany) {
            if (!setAdminActiveCompany($userCompany)) {
                $_SESSION['flash_error'] = 'No se pudo abrir la ficha (empresa del usuario inválida).';
                redirect($redirectTo);
            }
        }
    }
    return $user;
}

/** Campo hidden CSRF para formularios. */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function requireEmployeeRole() {
    if (!isLoggedIn()) {
        redirect('login');
    }
    if (!hasRole('empleado')) {
        if (hasRole('admin')) {
            redirect('admin/dashboard');
        }
        redirect('login');
    }
}

function postString($key, $default = '') {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}
