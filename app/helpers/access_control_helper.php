<?php

/** Helpers compatibles: si la migración no existe se conserva el comportamiento legacy. */
function access_control_ready() {
    static $ready = null;
    if ($ready === null) $ready = (new AccessControl())->isReady();
    return $ready;
}

function access_current_scope() {
    if (!isLoggedIn() || !access_control_ready()) return null;
    return (new AccessControl())->currentScopeForUser((int)$_SESSION['user_id'], (int)($_SESSION['access_scope_id'] ?? 0));
}

function access_current_role() {
    $scope = access_current_scope();
    if ($scope) return $scope->access_role;
    $legacy = $_SESSION['user_role'] ?? 'empleado';
    return $legacy === 'admin' ? 'administrador' : ($legacy === 'supervisor' ? 'encargado' : 'operario');
}

function access_is_staff() {
    return in_array(access_current_role(), ['encargado','coordinador','rrhh','administrador'], true);
}

function access_can_manage_company($companyId, $branchId = 0) {
    if (!isLoggedIn()) return false;
    if (!access_control_ready()) return hasRole('admin') || (hasRole('supervisor') && (int)($_SESSION['user_company_id'] ?? 0) === (int)$companyId);
    return (new AccessControl())->canManageScope((int)$_SESSION['user_id'], (int)$companyId, (int)$branchId);
}

function access_user_can_manage_company($userId, $companyId) {
    if (!access_control_ready()) return false;
    foreach ((new AccessControl())->getScopesForUser((int)$userId, true) as $scope) {
        if ($scope->access_role === 'administrador') return true;
        if (in_array($scope->access_role, ['rrhh','coordinador'], true) && (int)$scope->company_id === (int)$companyId) return true;
    }
    return false;
}

function access_set_active_scope($scopeId) {
    if (!isLoggedIn() || !access_control_ready()) return false;
    $scope = (new AccessControl())->getScope((int)$scopeId, (int)$_SESSION['user_id']);
    if (!$scope || !(int)$scope->is_active) return false;
    // Candado organizacional: un scope sobre una empresa del otro grupo
    // (asignación errónea o heredada de antes de la bifurcación) no puede
    // activar esa empresa. Mismo criterio que setAdminActiveCompany().
    if (function_exists('org_locked_group') && function_exists('org_group_of_company')) {
        $locked = org_locked_group();
        if ($locked !== '' && org_group_of_company((int)$scope->company_id) !== $locked) return false;
    }
    $_SESSION['access_scope_id'] = (int)$scope->id;
    $_SESSION['user_company_id'] = (int)$scope->company_id;
    $_SESSION['user_branch_id'] = (int)($scope->branch_id ?? 0);
    $_SESSION['user_company_name'] = (new Company())->getNameById((int)$scope->company_id);
    return true;
}

function access_portal_feature_allowed($feature, $globalDefault = true) {
    if (!isLoggedIn() || !access_control_ready()) return $globalDefault;
    $scope = access_current_scope();
    if (!$scope) return $globalDefault;
    return (new AccessControl())->resolveFeature((int)$_SESSION['user_id'], (int)$scope->company_id, (int)($scope->branch_id ?? 0), $feature, $globalDefault);
}

function access_feature_key_for_portal($feature) {
    $map = [
        'pay_stubs'=>'pay_stubs', 'salary_advance'=>'salary_advance', 'overtime'=>'overtime_submit',
        'vacation_balance'=>'vacation_request', 'suggestions'=>'suggestions', 'training'=>'training',
        'surveys'=>'surveys', 'peer_stars'=>'peer_stars', 'mi_mes'=>'schedule',
    ];
    return $map[$feature] ?? $feature;
}

function access_can($capability, $companyId = 0, $branchId = 0) {
    if (!isLoggedIn()) return false;
    $companyId = $companyId ?: (int)($_SESSION['user_company_id'] ?? 0);
    $branchId = $branchId ?: (int)($_SESSION['user_branch_id'] ?? 0);
    if (!access_control_ready()) return isAdmin();
    return (new AccessControl())->hasCapability((int)$_SESSION['user_id'], $capability, $companyId, $branchId);
}

function require_capability($capability, $redirectTo = 'admin/dashboard') {
    if (!access_can($capability)) {
        $_SESSION['flash_error'] = 'No tenés permiso para realizar esta operación.';
        redirect($redirectTo);
    }
    return true;
}
