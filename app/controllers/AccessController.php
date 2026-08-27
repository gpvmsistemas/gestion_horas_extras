<?php

class AccessController {
    private $access;

    public function __construct() {
        if (!isLoggedIn()) redirect('login');
        $this->access = new AccessControl();
    }

    public function setContext() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('employee/index');
        csrf_verify();
        if (!access_set_active_scope((int)($_POST['scope_id'] ?? 0))) $_SESSION['flash_error'] = 'No podés usar ese contexto de trabajo.';
        $return = admin_safe_return_path(trim($_POST['return_url'] ?? ''), hasRole('empleado') ? 'employee/index' : 'admin/dashboard');
        redirect($return);
    }

    public function savePolicies() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/companies');
        csrf_verify();
        $companyId = (int)($_POST['company_id'] ?? 0); $branchId = (int)($_POST['branch_id'] ?? 0);
        $ok = $this->access->savePolicies($companyId, $branchId, (array)($_POST['features'] ?? []), (int)$_SESSION['user_id']);
        $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $ok ? 'Permisos del portal guardados.' : 'No tenés permiso para modificar esta política.';
        redirect('admin/editCompany/' . $companyId);
    }

    public function copyPolicies() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/companies');
        csrf_verify();
        $companyId = (int)($_POST['company_id'] ?? 0); $branchId = (int)($_POST['branch_id'] ?? 0);
        $sourceCompanyId = (int)($_POST['source_company_id'] ?? 0); $sourceBranchId = (int)($_POST['source_branch_id'] ?? 0);
        if ($sourceCompanyId <= 0) {
            $parts = explode(':', trim((string)($_POST['policy_template_source'] ?? '')));
            if (count($parts) === 2) { $sourceCompanyId = (int)$parts[0]; $sourceBranchId = (int)$parts[1]; }
        }
        $ok = $this->access->copyPolicies($sourceCompanyId, $sourceBranchId, $companyId, $branchId, (int)$_SESSION['user_id']);
        $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $ok ? 'Configuración de permisos copiada.' : 'No se pudo copiar la configuración seleccionada.';
        redirect('admin/editCompany/' . $companyId);
    }

    public function saveUserScopes($userId) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/users');
        csrf_verify();
        $role = access_current_role();
        if (!in_array($role, ['administrador','rrhh'], true)) { $_SESSION['flash_error'] = 'Solo Administrador o RRHH pueden asignar perfiles.'; redirect('admin/editUser/' . (int)$userId); }
        $ok = $this->access->saveScopes((int)$userId, (array)($_POST['scopes'] ?? []), (int)$_SESSION['user_id']);
        $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $ok ? 'Asignaciones de acceso actualizadas.' : 'Revisá empresa, sucursal, perfil y vigencia.';
        redirect('admin/editUser/' . (int)$userId);
    }

    public function saveUserOverrides($userId) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/users');
        csrf_verify();
        $companyId = (int)($_POST['company_id'] ?? 0); $branchId = (int)($_POST['branch_id'] ?? 0);
        $ok = $this->access->saveUserOverrides((int)$userId, $companyId, $branchId, (array)($_POST['features'] ?? []), (int)$_SESSION['user_id']);
        $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $ok ? 'Excepciones del portal guardadas.' : 'No tenés permiso para modificar estas excepciones.';
        redirect('admin/editUser/' . (int)$userId);
    }

    public function saveScopeCapabilities($scopeId) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/users'); csrf_verify();
        $scope=$this->access->getScope((int)$scopeId); if(!$scope){$_SESSION['flash_error']='Asignación inexistente.';redirect('admin/users');}
        $ok=$this->access->saveScopeCapabilities((int)$scopeId,(array)($_POST['capabilities']??[]),(int)$_SESSION['user_id']);
        $_SESSION[$ok?'flash_success':'flash_error']=$ok?'Permisos operativos actualizados.':'No tenés permiso para modificar esta asignación.';
        redirect('admin/editUser/'.(int)$scope->user_id.'#permisos');
    }
}
