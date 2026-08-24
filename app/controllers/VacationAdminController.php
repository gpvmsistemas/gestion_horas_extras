<?php

class VacationAdminController {
    private $agreementModel;
    private $entitlement;
    private $balanceModel;
    private $userModel;
    private $companyModel;

    public function __construct() {
        if (!hasRole('admin')) {
            redirect('login');
        }
        ensureAdminCompanySession();
        $this->agreementModel = new CollectiveAgreement();
        $this->entitlement = new VacationEntitlementService();
        $this->balanceModel = new VacationBalance();
        $this->userModel = new User();
        $this->companyModel = new Company();
    }

    public function agreements() {
        if (!$this->agreementModel->isReady()) {
            $_SESSION['flash_error'] = 'Ejecutá migration_collective_agreements.sql (ver MIGRATIONS.md #22).';
            redirect('admin/dashboard');
        }
        $agreements = $this->agreementModel->getAll(false);
        foreach ($agreements as $ag) {
            $ag->rules = $this->agreementModel->getRules((int)$ag->id);
        }
        $companyId = requireAdminCompany('admin/dashboard');
        $companies = $this->companyModel->getAllCompanies();
        $defaults = [];
        foreach ($companies as $co) {
            $def = $this->agreementModel->getDefaultForCompany((int)$co->id);
            $defaults[(int)$co->id] = $def;
        }
        $this->view('admin/vacation/agreements', [
            'agreements' => $agreements,
            'companies' => $companies,
            'defaults' => $defaults,
            'company_id' => $companyId,
        ]);
    }

    public function saveCompanyDefault() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('vacationAdmin/agreements');
        }
        csrf_verify();
        requireAdminCompany('vacationAdmin/agreements');
        $companyId = (int)($_POST['company_id'] ?? 0);
        $agreementId = (int)($_POST['agreement_id'] ?? 0);
        $activeCompany = adminCompanyId();
        if ($companyId > 0 && $companyId !== $activeCompany) {
            $_SESSION['flash_error'] = 'Solo podés configurar la empresa activa en sesión.';
            redirect('vacationAdmin/agreements');
        }
        if ($companyId > 0 && $agreementId > 0) {
            $this->agreementModel->setDefaultForCompany($companyId, $agreementId);
            $_SESSION['flash_success'] = 'Convenio por defecto guardado.';
        }
        redirect('vacationAdmin/agreements');
    }

    public function editAgreement($id = 0) {
        $id = (int)$id;
        $agreement = $id > 0 ? $this->agreementModel->getById($id) : null;
        $rules = $id > 0 ? $this->agreementModel->getRules($id) : [];
        $this->view('admin/vacation/edit_agreement', [
            'agreement' => $agreement,
            'rules' => $rules,
            'day_count_modes' => vacation_day_count_modes(),
            'is_new' => $id <= 0,
        ]);
    }

    public function saveAgreement() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('vacationAdmin/agreements');
        }
        csrf_verify();
        $id = (int)($_POST['id'] ?? 0);
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        if ($code === '' || $name === '') {
            $_SESSION['flash_error'] = 'Código y nombre del convenio son obligatorios.';
            redirect('vacationAdmin/editAgreement/' . ($id > 0 ? $id : ''));
        }
        $ok = $this->agreementModel->saveAgreement([
            'id' => $id > 0 ? $id : null,
            'code' => $code,
            'name' => $name,
            'description' => trim($_POST['description'] ?? ''),
            'jurisdiction' => trim($_POST['jurisdiction'] ?? ''),
            'legal_reference' => trim($_POST['legal_reference'] ?? ''),
            'period_start_month' => (int)($_POST['period_start_month'] ?? 1),
            'period_start_day' => (int)($_POST['period_start_day'] ?? 1),
            'notice_days' => (int)($_POST['notice_days'] ?? 30),
            'start_rule' => trim($_POST['start_rule'] ?? 'lct'),
            'split_policy' => trim($_POST['split_policy'] ?? 'lct_7'),
            'minimum_request_days' => (float)($_POST['minimum_request_days'] ?? 7),
            'is_active' => !empty($_POST['is_active']),
        ]);
        if (!$ok) {
            $_SESSION['flash_error'] = 'No se pudo guardar el convenio (¿código duplicado?).';
            redirect('vacationAdmin/agreements');
        }
        if ($id <= 0) {
            $id = (int)$this->agreementModel->lastInsertId();
        }
        $_SESSION['flash_success'] = 'Convenio guardado. Agregá las reglas de antigüedad abajo.';
        redirect('vacationAdmin/editAgreement/' . $id);
    }

    public function saveAgreementRule($agreementId) {
        $agreementId = (int)$agreementId;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $agreementId <= 0) {
            redirect('vacationAdmin/agreements');
        }
        csrf_verify();
        $maxMonths = trim($_POST['max_months'] ?? '');
        $this->agreementModel->insertRule([
            'agreement_id' => $agreementId,
            'min_months' => (int)($_POST['min_months'] ?? 0),
            'max_months' => $maxMonths === '' ? null : (int)$maxMonths,
            'days_entitled' => (int)($_POST['days_entitled'] ?? 0),
            'day_count_mode' => $_POST['day_count_mode'] ?? 'calendar',
            'allows_split' => true,
            'allows_carryover' => !empty($_POST['allows_carryover']),
            'min_consecutive_days' => (int)($_POST['min_consecutive_days'] ?? 7),
            'notes' => trim($_POST['notes'] ?? ''),
        ]);
        $_SESSION['flash_success'] = 'Regla agregada.';
        redirect('vacationAdmin/editAgreement/' . $agreementId);
    }

    public function vacationSetup($userId) {
        $userId = (int)$userId;
        $user = adminResolveUser($userId);
        if (!$this->balanceModel->isReady()) {
            $_SESSION['flash_error'] = 'Ejecutá migration_collective_agreements.sql.';
            redirect('admin/employeeProfile/' . $userId);
        }
        $summary = $this->entitlement->getSummaryForUser($userId);
        $agreements = $this->agreementModel->getAll();
        $bounds = $this->entitlement->getPeriodBoundsForDate($userId);
        $suggestedLabel = $bounds['period_label'] ?? date('Y');
        $this->view('admin/vacation/setup', [
            'user' => $user,
            'summary' => $summary,
            'agreements' => $agreements,
            'movements' => $this->balanceModel->getMovementsByUser($userId, 30),
            'suggested_period' => $suggestedLabel,
        ]);
    }

    public function calculateVacationPreview($userId) {
        $userId = (int)$userId;
        adminResolveUser($userId);
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'message' => 'Método no permitido.']);
            exit;
        }
        csrf_verify();
        $periodLabel = trim($_POST['period_label'] ?? '');
        if ($periodLabel === '' && !empty($_POST['use_first_period'])) {
            $periods = $_POST['periods'] ?? [];
            if (is_array($periods) && isset($periods[0]['period_label'])) {
                $periodLabel = trim($periods[0]['period_label']);
            }
        }
        $result = $this->entitlement->calculatePreview($userId, [
            'hire_date' => trim($_POST['hire_date'] ?? ''),
            'agreement_id' => (int)($_POST['agreement_id'] ?? 0),
            'as_of_date' => trim($_POST['as_of_date'] ?? ''),
            'period_label' => $periodLabel,
        ]);
        echo json_encode($result);
        exit;
    }

    public function saveVacationSetup($userId) {
        $userId = (int)$userId;
        adminResolveUser($userId);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('vacationAdmin/vacationSetup/' . $userId);
        }
        csrf_verify();

        $user = $this->userModel->getUserById($userId);
        $hireDate = trim($_POST['hire_date'] ?? '');
        if ($hireDate === '' && !empty($user->hire_date)) {
            $hireDate = $user->hire_date;
        }
        $probation = trim($_POST['probation_start_date'] ?? '');
        if ($probation === '' && !empty($user->probation_start_date)) {
            $probation = $user->probation_start_date;
        }
        $agreementId = (int)($_POST['agreement_id'] ?? 0);
        $probVal = ($probation !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $probation)) ? $probation : null;
        $hireVal = ($hireDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hireDate)) ? $hireDate : null;
        if ($hireVal || $probVal || $agreementId > 0) {
            $this->userModel->updateVacationProfile(
                $userId,
                $hireVal,
                $agreementId > 0 ? $agreementId : null,
                $probVal
            );
        }

        $adminId = (int)$_SESSION['user_id'];
        $periods = $_POST['periods'] ?? [];
        $importErrors = [];
        if (is_array($periods)) {
            foreach ($periods as $row) {
                $label = trim($row['period_label'] ?? '');
                if ($label === '') {
                    continue;
                }
                $entitled = (float)($row['days_entitled'] ?? 0);
                $taken = (float)($row['days_taken'] ?? 0);
                $importResult = $this->entitlement->importPeriodBalance($userId, $label, $entitled, $taken, $adminId, 'Carga inicial RRHH');
                if (!$importResult['ok']) $importErrors[] = $label . ': ' . $importResult['message'];
            }
        }

        if (!empty($_POST['liquidate_current'])) {
            $result = $this->entitlement->liquidatePeriod($userId, null, $adminId);
            if ($importErrors) {
                $_SESSION['flash_error'] = implode(' ', $importErrors) . ' ' . $result['message'];
            } else {
                $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['message'];
            }
        } elseif ($importErrors) {
            $_SESSION['flash_error'] = implode(' ', $importErrors);
        } else {
            $_SESSION['flash_success'] = 'Datos de vacaciones guardados.';
        }
        redirect('vacationAdmin/vacationSetup/' . $userId);
    }

    public function addHistoricalBalance($userId) {
        $userId = (int)$userId;
        adminResolveUser($userId);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('vacationAdmin/vacationSetup/' . $userId);
        }
        csrf_verify();
        $result = $this->entitlement->addHistoricalBalance(
            $userId, (int)($_POST['year'] ?? 0), (float)($_POST['days'] ?? 0),
            (int)$_SESSION['user_id'], trim(strip_tags($_POST['reason'] ?? ''))
        );
        $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['message'];
        redirect('vacationAdmin/vacationSetup/' . $userId);
    }

    public function addConventionalCredit($userId) {
        $userId = (int)$userId;
        adminResolveUser($userId);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('vacationAdmin/vacationSetup/' . $userId);
        }
        csrf_verify();
        $result = $this->entitlement->addConventionalCredit(
            $userId, (int)($_POST['year'] ?? 0), (float)($_POST['days'] ?? 0),
            trim($_POST['expires_at'] ?? ''), (int)$_SESSION['user_id'],
            trim(strip_tags($_POST['reason'] ?? ''))
        );
        $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['message'];
        redirect('vacationAdmin/vacationSetup/' . $userId);
    }

    public function convertBalance($userId) {
        $userId = (int)$userId;
        adminResolveUser($userId);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('vacationAdmin/vacationSetup/' . $userId);
        }
        csrf_verify();
        $result = $this->entitlement->convertPeriodBalance(
            $userId, (int)($_POST['period_id'] ?? 0), trim($_POST['target_mode'] ?? ''),
            (float)($_POST['target_pending'] ?? -1), (int)$_SESSION['user_id'],
            trim(strip_tags($_POST['reason'] ?? ''))
        );
        $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['message'];
        redirect('vacationAdmin/vacationSetup/' . $userId);
    }

    public function liquidateCompanyBatch() {
        if (!$this->balanceModel->isReady()) {
            $_SESSION['flash_error'] = 'Ejecutá migration_collective_agreements.sql.';
            redirect('vacationAdmin/agreements');
        }

        $companyId = requireAdminCompany('admin/dashboard');
        $companyName = $this->companyModel->getNameById($companyId);
        $bounds = null;
        $employees = $this->userModel->getActiveEmployeesForVacationLiquidation($companyId);
        if (!empty($employees)) {
            $first = $employees[0];
            $bounds = $this->entitlement->getPeriodBoundsForDate((int)$first->id);
        }
        $suggestedPeriod = $bounds['period_label'] ?? date('Y');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $periodLabel = trim($_POST['period_label'] ?? '');
            $result = $this->entitlement->liquidateCompanyBatch(
                $companyId,
                $periodLabel !== '' ? $periodLabel : null,
                (int)$_SESSION['user_id']
            );
            $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['message'];
            $_SESSION['vacation_batch_report'] = $result;
            redirect('vacationAdmin/liquidateCompanyBatch?company_id=' . $companyId);
        }

        $report = $_SESSION['vacation_batch_report'] ?? null;
        unset($_SESSION['vacation_batch_report']);

        $this->view('admin/vacation/liquidate_batch', [
            'company_id' => $companyId,
            'company_name' => $companyName,
            'suggested_period' => $suggestedPeriod,
            'preview' => $this->entitlement->getBatchLiquidationPreview($companyId),
            'report' => $report,
        ]);
    }

    public function liquidateUser($userId) {
        $userId = (int)$userId;
        adminResolveUser($userId);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/employeeProfile/' . $userId);
        }
        csrf_verify();
        $periodLabel = trim($_POST['period_label'] ?? '');
        $result = $this->entitlement->liquidatePeriod(
            $userId,
            $periodLabel !== '' ? $periodLabel : null,
            (int)$_SESSION['user_id']
        );
        $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['message'];
        redirect('admin/employeeProfile/' . $userId . '#tab-vacation');
    }

    public function reports() {
        $filters = $this->vacationReportFilters();
        $report = $this->balanceModel->getPendingReport($filters);
        $statsFilters = $filters;
        $statsFilters['export'] = true;
        $statsFilters['balance_status'] = 'both';
        $stats = $this->buildVacationStats($this->balanceModel->getPendingReport($statsFilters)['rows']);
        $this->view('admin/vacation/reports', [
            'filters' => $filters,
            'report' => $report,
            'stats' => $stats,
            'companies' => $this->companyModel->getAllCompanies(),
            'agreements' => $this->agreementModel->getAll(),
            'areas' => (new Area())->getAll(),
        ]);
    }

    public function exportVacationBalancesCsv() {
        if (!$this->entitlement->isReady()) {
            $_SESSION['flash_error'] = 'Módulo de vacaciones no disponible.';
            redirect('vacationAdmin/reports');
        }
        $filters = $this->vacationReportFilters();
        $filters['export'] = true;
        $report = $this->balanceModel->getPendingReport($filters);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="vacaciones_saldos_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['Empleado', 'Documento', 'Empresa', 'Area', 'Convenio', 'Pendiente total',
            'Historico', 'Periodo actual', 'Periodo mas antiguo', 'Proximo vencimiento'], ';');
        foreach ($report['rows'] as $row) {
            fputcsv($out, [
                $row->full_name,
                $row->document_number ?? '',
                $row->company_name ?? '',
                $row->area_name ?? '',
                $row->agreement_name ?? '',
                number_format($row->total_pending, 1, '.', ''),
                number_format($row->historical_pending, 1, '.', ''),
                number_format($row->current_pending, 1, '.', ''),
                $row->oldest_period ?? '',
                $row->next_expiry ?? '',
            ], ';');
        }
        fclose($out);
        exit();
    }

    private function vacationReportFilters() {
        $types = ['annual', 'historical', 'conventional_credit'];
        $activeInput = $_GET['active'] ?? 'active';
        $balanceStatusInput = $_GET['balance_status'] ?? 'with';
        $active = in_array($activeInput, ['active', 'inactive', 'all'], true) ? $activeInput : 'active';
        $balanceStatus = in_array($balanceStatusInput, ['with', 'without', 'both'], true) ? $balanceStatusInput : 'with';
        // Aislamiento organizacional: el reporte (filas, stats y CSV) queda
        // limitado a las empresas del grupo del admin, y un company_id ajeno
        // por URL se descarta.
        $orgIds = [];
        if (function_exists('org_locked_group') && function_exists('org_group_company_ids')) {
            $locked = org_locked_group();
            if ($locked !== '') {
                $orgIds = array_map('intval', org_group_company_ids($locked));
            }
        }
        $companyId = (int)($_GET['company_id'] ?? 0);
        if ($orgIds && $companyId && !in_array($companyId, $orgIds, true)) {
            $companyId = 0;
        }
        return [
            'company_ids' => $orgIds,
            'company_id' => $companyId,
            'agreement_id' => (int)($_GET['agreement_id'] ?? 0),
            'area_id' => (int)($_GET['area_id'] ?? 0),
            'search' => trim($_GET['search'] ?? ''),
            'period' => preg_match('/^\d{4}(?:-\d{4})?$/', $_GET['period'] ?? '') ? $_GET['period'] : '',
            'balance_type' => in_array($_GET['balance_type'] ?? '', $types, true) ? $_GET['balance_type'] : '',
            'active' => $active,
            'balance_status' => $balanceStatus,
            'min_days' => is_numeric($_GET['min_days'] ?? null) ? $_GET['min_days'] : '',
            'max_days' => is_numeric($_GET['max_days'] ?? null) ? $_GET['max_days'] : '',
            'historical_only' => !empty($_GET['historical_only']),
            'expiring_only' => !empty($_GET['expiring_only']),
            'sort' => trim($_GET['sort'] ?? 'pending_desc'),
            'page' => max(1, (int)($_GET['page'] ?? 1)),
            'per_page' => max(10, min(200, (int)($_GET['per_page'] ?? 50))),
        ];
    }

    private function buildVacationStats(array $rows) {
        $stats = [
            'employees_with_pending'=>0, 'total_pending'=>0, 'historical_pending'=>0,
            'current_pending'=>0, 'expiring_credits'=>0, 'without_agreement'=>0,
            'without_current_liquidation'=>0, 'by_company'=>[], 'by_agreement'=>[],
        ];
        foreach ($rows as $row) {
            $pending = (float)$row->total_pending;
            if ($pending > 0) $stats['employees_with_pending']++;
            $stats['total_pending'] += $pending;
            $stats['historical_pending'] += (float)$row->historical_pending;
            $stats['current_pending'] += (float)$row->current_pending;
            if (!empty($row->has_expiring_credit)) $stats['expiring_credits']++;
            if (empty($row->effective_agreement_id)) $stats['without_agreement']++;
            if (empty($row->has_current_liquidation)) $stats['without_current_liquidation']++;
            $company = $row->company_name ?: 'Sin empresa';
            $agreement = $row->agreement_name ?: 'Sin convenio';
            $stats['by_company'][$company] = ($stats['by_company'][$company] ?? 0) + $pending;
            $stats['by_agreement'][$agreement] = ($stats['by_agreement'][$agreement] ?? 0) + $pending;
        }
        arsort($stats['by_company']);
        arsort($stats['by_agreement']);
        return $stats;
    }

    private function view($view, $data = []) {
        if (file_exists(APPROOT . '/views/' . $view . '.php')) {
            require APPROOT . '/views/' . $view . '.php';
        } else {
            die('Vista no encontrada: ' . $view);
        }
    }
}
