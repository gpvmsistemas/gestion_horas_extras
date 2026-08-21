<?php

class AdminController {
    private $userModel;
    private $overtimeModel;
    private $requestModel;
    private $scheduleModel;
    private $companyModel;
    private $suggestionModel;
    private $userNoteModel;
    private $shiftModel;
    private $workScheduleModel;
    private $holidayModel;
    private $templateModel;
    private $syncModel;
    private $marcacionesCacheModel;
    private $attendanceSummaryModel;
    private $planVsActualService;
    private $calendarMonthService;
    private $justificationModel;
    private $shiftSwapModel;
    private $employeeIncidentModel;
    private $clockDeviceModel;
    private $employeeRecordModel;

    public function __construct(){
        if (!isStaffAdmin()) {
            redirect('login');
        }
        $this->userModel = new User();
        $this->overtimeModel = new Overtime();
        $this->requestModel = new Request();
        $this->workScheduleModel = new WorkSchedule();
        $this->scheduleModel = new Schedule();
        $this->companyModel = new Company();
        $this->suggestionModel = new Suggestion();
        $this->userNoteModel = new UserNote();
        $this->shiftModel = new Shift();
        $this->holidayModel = new Holiday();
        $this->templateModel = new Template();
        $this->syncModel = new SyncModel();
        $this->marcacionesCacheModel = new MarcacionesCache();
        $this->attendanceSummaryModel = new AttendanceDaySummary();
        $this->planVsActualService = new PlanVsActualService();
        $this->calendarMonthService = new CalendarMonthService();
        $this->justificationModel = new AttendanceJustification();
        $this->shiftSwapModel = new ShiftSwap();
        $this->employeeIncidentModel = new EmployeeIncident();
        $this->clockDeviceModel = new ClockDevice();
        $this->employeeRecordModel = new EmployeeRecord();
        ensureAdminCompanySession();
    }

    public function index(){
        redirect('admin/dashboard');
    }

    /**
     * Cambia la empresa operativa del panel admin (multi-empresa).
     */
    public function setCompany() {
        requireAdminOnly();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/dashboard');
        }
        csrf_verify();
        $companyId = (int)($_POST['company_id'] ?? 0);
        // Suite P&M: el usuario RRHH solo puede navegar empresas de SU organización.
        if (function_exists('org_locked_group') && org_locked_group() !== ''
            && function_exists('org_group_of_company')) {
            $targetGroup = org_group_of_company($companyId);
            if ($targetGroup !== '' && $targetGroup !== org_locked_group()) {
                $_SESSION['flash_error'] = 'Esa empresa pertenece a otra organización.';
                redirect(admin_company_switch_return_path(trim($_POST['return_url'] ?? '')));
            }
        }
        if (!setAdminActiveCompany($companyId)) {
            $_SESSION['flash_error'] = 'Empresa no válida.';
        } else {
            $branchId = (int)($_POST['branch_id'] ?? 0);
            if ($branchId > 0 && !$this->companyModel->getBranchByIdForCompany($branchId, $companyId, true)) {
                $_SESSION['flash_error'] = 'La sucursal no pertenece a la empresa seleccionada.';
            } else {
                $_SESSION['admin_branch_id'] = $branchId;
                $_SESSION['flash_success'] = $branchId > 0 ? 'Empresa y sucursal activas actualizadas.' : 'Empresa activa actualizada.';
            }
        }
        $return = trim($_POST['return_url'] ?? '');
        redirect(admin_company_switch_return_path($return));
    }

    // ─────────────────────────────────────────────────────────────────
    // MAPEO DE EMPLEADOS API → USUARIOS LOCALES
    // ─────────────────────────────────────────────────────────────────

    public function mapeoIncompleto() {
        requireAdminOnly();
        $companyId = requireAdminCompany('admin/dashboard');
        $sinceDays = max(7, (int)($_GET['days'] ?? 90));
        $legajos = $this->marcacionesCacheModel->getUnmappedLegajosGrouped($companyId, $sinceDays);
        $allUsers = $this->userModel->getUsersByCompany($companyId);
        foreach ($legajos as &$row) {
            $row->suggestions = suggest_users_for_clock_name($row->person_name ?? '', $allUsers);
        }
        unset($row);
        $this->view('admin/mapeo_incompleto', [
            'legajos' => $legajos,
            'since_days' => $sinceDays,
            'company_id' => $companyId,
            'all_users' => $allUsers,
        ]);
    }

    /** Catálogo de dispositivos y sucursales donde pueden usarse. */
    public function clockDevices() {
        requireAdminOnly();
        if (!$this->clockDeviceModel->isReady()) {
            $_SESSION['flash_error'] = 'Falta ejecutar migration_clock_devices_scope.sql.';
            redirect('admin/marcacionesTodas');
        }
        $companies = $this->companyModel->getAllCompanies();
        $branches = [];
        foreach ($companies as $company) {
            foreach ($this->companyModel->getBranches((int)$company->id, false) as $branch) { $branch->company_name = $company->name; $branches[] = $branch; }
        }
        $this->view('admin/clock_devices', [
            'devices' => $this->clockDeviceModel->getAllWithScopes(),
            'branches' => $branches,
            'companies' => $companies,
        ]);
    }

    public function saveClockDevice() {
        requireAdminOnly();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/clockDevices');
        csrf_verify();
        if (!$this->clockDeviceModel->isReady()) redirect('admin/clockDevices');
        $ok = $this->clockDeviceModel->save(
            (int)($_POST['id'] ?? 0), $_POST['external_name'] ?? '', $_POST['display_name'] ?? '',
            $_POST['branch_ids'] ?? [], !empty($_POST['is_active'])
        );
        $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $ok ? 'Reloj y sucursales actualizados.' : 'No se pudo guardar el reloj. Revisá el nombre y las sucursales.';
        redirect('admin/clockDevices');
    }

    public function mapeoApi() {
        redirect('admin/mapeoIncompleto');
        /*
        requireAdminOnly();
        $employees = [];
        $error     = null;
        $consulted = false;

        $startDate = $_POST['start_date'] ?? $_GET['start_date'] ?? date('Y-m-d', strtotime('-1 month'));
        $endDate   = $_POST['end_date']   ?? $_GET['end_date']   ?? date('Y-m-d');

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['consultar'])) {
            csrf_verify();
            $consulted = true;
            set_time_limit(180);
            $result = $this->syncModel->getApiEmployees($startDate, $endDate);
            if (is_string($result)) {
                $error = $result;
            } else {
                $employees = $result;
            }
        }

        $companyId = (int)($_SESSION['user_company_id'] ?? 0);
        $allUsersCompany = $this->userModel->getUsersByCompany($companyId);
        $buscarUsuario = trim($_GET['buscar_usuario'] ?? $_POST['buscar_usuario'] ?? '');

        if (!is_string($employees)) {
            foreach ($employees as &$emp) {
                $emp['suggestions'] = suggest_users_for_clock_name(
                    $emp['personName'] ?? '',
                    $allUsersCompany
                );
            }
            unset($emp);
        }

        $data = [
            'employees'        => $employees,
            'all_users'        => $allUsersCompany,
            'start_date'       => $startDate,
            'end_date'         => $endDate,
            'consulted'        => $consulted,
            'error'            => $error,
            'company_id'       => $companyId,
            'company_name'     => $_SESSION['user_company_name'] ?? '',
            'buscar_usuario'   => $buscarUsuario,
            'usuarios_busqueda'=> $buscarUsuario !== '' ? $this->userModel->searchUsersByName($buscarUsuario, 25) : [],
        ];
        $this->view('admin/mapeo_api', $data); */
    }

    public function saveMappingFromApi() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/mapeoApi');
        }
        csrf_verify();
        requireAdminOnly('admin/mapeoApi');
        requireAdminCompany('admin/mapeoApi');

        $employeeID = trim($_POST['employee_id'] ?? '');
        $deviceName = trim($_POST['device_name'] ?? 'API');
        $userId     = (int)($_POST['user_id'] ?? 0);
        $startDate  = $_POST['start_date'] ?? '';
        $endDate    = $_POST['end_date']   ?? '';

        if (empty($employeeID) || $userId <= 0) {
            $_SESSION['flash_error'] = 'Selecciona un usuario para asignar.';
            redirect('admin/mapeoApi');
        }

        requireUserInAdminCompany($userId, 'admin/mapeoApi');

        $this->userModel->upsertClockMapping($userId, $deviceName ?: 'API', $employeeID);
        $this->marcacionesCacheModel->assignMappedUser($employeeID, $deviceName, $userId);

        $user = $this->userModel->getUserById($userId);
        $name = $user ? $user->full_name : "ID {$userId}";
        $_SESSION['flash_success_html'] = "ID de reloj <strong>" . htmlspecialchars($employeeID) . "</strong> asignado a <strong>" . htmlspecialchars($name) . "</strong>.";

        // Volver a la página con el mismo rango consultado
        $qs = http_build_query(['start_date' => $startDate, 'end_date' => $endDate]);
        redirect('admin/mapeoApi?' . $qs);
    }

    public function deleteMappingFromApi() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/mapeoApi');
        }
        csrf_verify();
        requireAdminOnly('admin/mapeoApi');
        requireAdminCompany('admin/mapeoApi');

        $employeeID = trim($_POST['employee_id'] ?? '');
        $startDate  = $_POST['start_date'] ?? '';
        $endDate    = $_POST['end_date']   ?? '';

        if (!empty($employeeID)) {
            $companyId = requireAdminCompany('admin/mapeoApi');
            $mappingUserId = $this->userModel->getUserIdByClockEmployeeId($employeeID, trim($_POST['device_name'] ?? ''));
            if ($mappingUserId && !userBelongsToCompany($mappingUserId, $companyId)) {
                $_SESSION['flash_error'] = 'No tenés permiso para eliminar ese mapeo.';
            } else {
                $this->userModel->deleteClockMapping($employeeID, trim($_POST['device_name'] ?? ''));
                $_SESSION['flash_success_html'] = "Mapeo del ID <strong>" . htmlspecialchars($employeeID) . "</strong> eliminado.";
            }
        }

        $qs = http_build_query(['start_date' => $startDate, 'end_date' => $endDate]);
        redirect('admin/mapeoApi?' . $qs);
    }

    // ─────────────────────────────────────────────────────────────────
    // SINCRONIZACIÓN  (sólo vía API externa — relojes directos: eliminado)
    // ─────────────────────────────────────────────────────────────────

    public function sync(){
        requireAdminOnly();
        $this->view('admin/sync', []);
    }

    public function runApiSync(){
        requireAdminOnly();
        set_time_limit(180);
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            redirect('admin/sync');
        }
        csrf_verify();

        $startDate  = trim($_POST['api_start_date'] ?? '');
        $endDate    = trim($_POST['api_end_date']   ?? '');
        $employeeID = trim($_POST['api_employee_id'] ?? '') ?: null;

        if (empty($startDate) || empty($endDate)) {
            $_SESSION['flash_error'] = 'Debes indicar fecha desde y fecha hasta.';
            redirect('admin/sync');
        }

        $companyId = $_SESSION['user_company_id'] ?? null;
        $result = $this->syncModel->syncFromExternalApi($startDate, $endDate, $employeeID, $companyId);

        if ($result['success']) {
            $msg = $result['message'];
            if (!empty($result['api_total'])) {
                $msg = 'API: ' . (int)$result['api_total'] . ' marcación(es) en el período. ' . $msg;
            }
            $_SESSION['flash_success'] = $msg;
            if (!empty($result['cache_count'])) {
                $_SESSION['flash_success_link'] = [
                    'url'  => URLROOT . '/admin/marcacionesTodas',
                    'text' => 'Ver todas las marcaciones',
                ];
            }
            if (!empty($result['not_mapped'])) {
                $labels = array_slice(array_values($result['not_mapped']), 0, 8);
                $extra  = count($result['not_mapped']) > 8 ? ' y ' . (count($result['not_mapped']) - 8) . ' más' : '';
                $_SESSION['flash_success'] .= ' Sin mapear: ' . implode(', ', $labels) . $extra . '.';
            }
        } else {
            $_SESSION['flash_error'] = 'Error al sincronizar: ' . $result['message'];
        }
        redirect('admin/sync');
    }

    public function processSync(){
        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            redirect('admin/sync');
        }
        csrf_verify();
        requireAdminOnly('admin/sync');

        $rawResponse = isset($_POST['raw_response']) ? $_POST['raw_response'] : '';
        $responseData = json_decode($rawResponse, true);
        $events = isset($responseData['AcsEvent']['InfoList']) ? $responseData['AcsEvent']['InfoList'] : [];

        $normalized = [];
        foreach($events as $event){
            if(empty($event['employeeNoString']) || empty($event['time'])){
                continue;
            }
            $normalized[] = [
                'serialNo' => $event['serialNo'] ?? md5($event['employeeNoString'] . '|' . $event['time']),
                'employeeNoString' => $event['employeeNoString'],
                'time' => $event['time'],
                'device_name' => $event['deviceName'] ?? null,
                'direction' => $event['direction'] ?? null,
                'direction_label' => $event['directionStr'] ?? null,
            ];
        }

        $result = $this->syncModel->processAndSaveData($normalized, $_POST['start_date'] ?? '', $_POST['end_date'] ?? '');
        if($result['success']){
            $_SESSION['flash_success'] = $result['message'];
        } else {
            $_SESSION['flash_error'] = $result['message'] ?? 'Error al importar sincronización.';
        }
        redirect('admin/sync');
    }

    // ─────────────────────────────────────────────────────────────────
    // MARCACIONES — vista global de caché local
    // ─────────────────────────────────────────────────────────────────

    public function marcaciones(){
        redirect('admin/marcacionesTodas');
    }

    /**
     * Todas las marcaciones importadas (empleados del sistema y legajos sin mapear).
     */
    public function marcacionesTodas(){
        $viewMode = $_GET['view'] ?? 'grouped';
        if (!in_array($viewMode, ['grouped', 'list'], true)) {
            $viewMode = 'grouped';
        }

        $filters = [
            'start_date'   => $_GET['start_date']   ?? date('Y-m-01'),
            'end_date'     => $_GET['end_date']     ?? date('Y-m-d'),
            'device_name'  => $_GET['device_name']  ?? '',
            'employee_id'  => trim($_GET['employee_id'] ?? ''),
            'person_q'     => trim($_GET['person_q'] ?? ''),
            'mapped'       => $_GET['mapped']       ?? '',
            'direction'    => $_GET['direction']    ?? '',
            'branch_id'    => (int)($_GET['branch_id'] ?? 0),
        ];

        $companyId = requireAdminCompany('admin/dashboard');
        $marcaciones = $this->marcacionesCacheModel->getAll($filters, $companyId);
        $devices     = $this->marcacionesCacheModel->getDistinctDevices($companyId);
        $branches    = $this->companyModel->getBranches($companyId, false);
        $stats       = $this->marcacionesCacheModel->getStats($filters, $companyId);
        $groups      = marcBuildPersonDayGroups($marcaciones);

        $data = [
            'marcaciones' => $marcaciones,
            'groups'      => $groups,
            'devices'     => $devices,
            'branches'    => $branches,
            'filters'     => $filters,
            'stats'       => $stats,
            'view_mode'   => $viewMode,
        ];
        $this->view('admin/marcaciones_todas', $data);
    }

    // --- Métodos del Dashboard e Historial ---

    public function dashboard(){
        if (!isset($_SESSION['user_company_id'])) {
            $adminUser = $this->userModel->getUserById($_SESSION['user_id']);
            $_SESSION['user_company_id'] = $adminUser->company_id;
        }
        $companyId = $_SESSION['user_company_id'];
        
        $activeUsers = $this->userModel->countActiveUsersByCompany($companyId);
        $workingNow = $this->workScheduleModel->countWorkingNowByCompany($companyId);
        $onLeaveToday = $this->requestModel->countOnLeaveTodayByCompany($companyId);
        $pendingRequests = $this->requestModel->getPendingRequestsWithDetails($companyId);
        $pendingOvertimeTotals = $this->overtimeModel->getPendingTotalsByType($companyId);
        $employeesWithPending = $this->overtimeModel->countEmployeesWithPendingHours($companyId);

        $pendingOvertimeWithRate = $this->overtimeModel->getPendingOvertimeWithRate($companyId);
        $estimatedCost = 0;
        if($pendingOvertimeWithRate){
            foreach ($pendingOvertimeWithRate as $entry) {
                $cost50 = $entry->hours_50 * $entry->hourly_rate * 1.5;
                $cost100 = $entry->hours_100 * $entry->hourly_rate * 2;
                $estimatedCost += $cost50 + $cost100;
            }
        }

        $usersNearLimit = $this->workScheduleModel->getUsersApproachingWeeklyLimit($companyId);

        $overtime_50 = isset($pendingOvertimeTotals->total_50) ? $pendingOvertimeTotals->total_50 : 0;
        $overtime_100 = isset($pendingOvertimeTotals->total_100) ? $pendingOvertimeTotals->total_100 : 0;
        
        $topEmployees = $this->overtimeModel->getTopEmployeesByPendingHours(5, $companyId);
        
        $overtimeByDayRaw = $this->overtimeModel->getPendingHoursByDayOfWeek($companyId);
        $overtimeByDay = array_fill(0, 7, 0);
        if(!empty($overtimeByDayRaw)){
            foreach($overtimeByDayRaw as $day) { $overtimeByDay[$day->day_of_week - 1] = $day->total_hours; }
        }

        $monthRequests = $this->requestModel->getMonthlyRequestSummary($companyId, date('Y-m'));
        $requestLabels = array(); $requestData = array();
        if(!empty($monthRequests)){
            foreach($monthRequests as $req){ $requestLabels[] = $req->type_name; $requestData[] = $req->count; }
        }

        $historicalData = $this->overtimeModel->getHistoricalOvertime($companyId, 6);
        $historicalLabels = [];
        $historicalData50 = [];
        $historicalData100 = [];
        $nombres_mes = array('Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic');

        for ($i = 5; $i >= 0; $i--) {
            $date = new DateTime(date('Y-m-01') . " -$i months");
            $monthKey = $date->format('Y-m');
            $monthName = $nombres_mes[(int)$date->format('n') - 1];
            $historicalLabels[] = $monthName;
            $historicalData50[$monthKey] = 0;
            $historicalData100[$monthKey] = 0;
        }

        if($historicalData){
            foreach ($historicalData as $month) {
                if (isset($historicalData50[$month->month])) {
                    $historicalData50[$month->month] = $month->total_50;
                    $historicalData100[$month->month] = $month->total_100;
                }
            }
        }

        $birthdayInfo = $this->userModel->getBirthdayInfo($companyId);
        $latestSuggestion = $this->suggestionModel->getLatestSuggestionByCompany($companyId);

        $today = date('Y-m-d');
        try {
            if (!$this->attendanceSummaryModel->hasRowsForDate($companyId, $today)) {
                $this->planVsActualService->recomputeRange($companyId, $today, $today);
            }
            $attendanceStats = $this->attendanceSummaryModel->getDashboardSummary($companyId, $today);
            $attendanceAlerts = $this->attendanceSummaryModel->getAlertsForDate($companyId, $today, 12);
        } catch (Exception $e) {
            $attendanceStats = null;
            $attendanceAlerts = [];
        }

        $overtimeEnabled = function_exists('overtime_staff_can_view')
            && overtime_staff_can_view($companyId);

        if ($overtimeEnabled) {
            $this->overtimeModel->recalculatePendingRates($companyId);
        }

        $data = array(
            'overtime_enabled' => $overtimeEnabled,
            'stats' => array(
                'active_users' => $activeUsers,
                'working_now' => $workingNow,
                'on_leave_today' => $onLeaveToday,
                'pending_requests_count' => count($pendingRequests),
                'overtime_50' => $overtime_50,
                'overtime_100' => $overtime_100,
                'employees_with_pending' => $employeesWithPending,
                'estimated_cost' => $estimatedCost
            ),
            'charts' => array(
                'overtime_distribution' => json_encode(array($overtime_50, $overtime_100)),
                'overtime_by_day' => json_encode($overtimeByDay),
                'requests' => array('labels' => json_encode($requestLabels), 'data' => json_encode($requestData)),
                'historical' => [
                    'labels' => json_encode($historicalLabels),
                    'data50' => json_encode(array_values($historicalData50)),
                    'data100' => json_encode(array_values($historicalData100))
                ]
            ),
            'top_employees' => $topEmployees,
            'pending_requests' => $pendingRequests,
            'birthday_info' => $birthdayInfo,
            'latest_suggestion' => $latestSuggestion,
            'users_near_limit' => $usersNearLimit,
            'attendance_date' => $today,
            'attendance_stats' => $attendanceStats,
            'attendance_alerts' => $attendanceAlerts,
        );

        $this->view('admin/dashboard', $data);
    }

    /**
     * Detalle planificado vs fichado (enlace desde dashboard).
     */
    public function attendance() {
        $query = array_filter([
            'date' => trim((string)($_GET['date'] ?? '')),
            'status' => trim((string)($_GET['status'] ?? '')),
            'branch_id' => max(0, (int)($_GET['branch_id'] ?? 0)),
            'user_id' => max(0, (int)($_GET['user_id'] ?? 0)),
        ], static function ($value) {
            return $value !== '' && $value !== 0;
        });
        redirect('admin/controlAsistencia' . (!empty($query) ? '?' . http_build_query($query) : ''));
    }

    /** Panel diario único: plan, fichadas, estado y resolución. */
    public function controlAsistencia() {
        $companyId = requireAdminCompany('admin/dashboard');
        $workDate = $_GET['date'] ?? date('Y-m-d');
        $statusFilter = $_GET['status'] ?? 'alerts';
        $branchId = (int)($_GET['branch_id'] ?? 0);
        $userIdFilter = (int)($_GET['user_id'] ?? 0);

        if (!empty($_GET['recompute'])) {
            redirect('admin/attendance?date=' . urlencode($workDate) . '&status=' . urlencode($statusFilter));
        }

        $rows = [];
        $stats = null;
        try {
            if (!$this->attendanceSummaryModel->hasRowsForDate($companyId, $workDate)) {
                $this->planVsActualService->recomputeRange($companyId, $workDate, $workDate);
            }
            $rows = filterStaffRowsByUserArea(
                $this->attendanceSummaryModel->getAllForDate($companyId, $workDate, $statusFilter)
            );
            $rows = array_values(array_filter($rows, function ($row) use ($branchId, $userIdFilter) {
                if ($userIdFilter > 0 && (int)$row->user_id !== $userIdFilter) return false;
                if ($branchId <= 0) return true;
                $user = $this->userModel->getUserById((int)$row->user_id);
                if ((int)($user->branch_id ?? 0) === $branchId) return true;
                foreach ($this->userModel->getBranchAssignmentsForUser((int)$row->user_id) as $branch) if ((int)$branch->id === $branchId) return true;
                return false;
            }));
            foreach ($rows as $row) {
                $user = $this->userModel->getUserById((int)$row->user_id);
                $row->branch_name = '';
                foreach ($this->userModel->getBranchAssignmentsForUser((int)$row->user_id) as $branch) {
                    if (!empty($branch->is_primary)) { $row->branch_name = $branch->name; break; }
                    if ($row->branch_name === '') $row->branch_name = $branch->name;
                }
                $row->attendance_control_mode = User::normalizeAttendanceControlMode($user->attendance_control_mode ?? 'required');
                $row->device_names = implode(', ', $this->marcacionesCacheModel->getDeviceNamesForUserDay((int)$row->user_id, $workDate));
            }
            $stats = $this->attendanceSummaryModel->getDashboardSummary($companyId, $workDate);
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Módulo de asistencia no disponible. Ejecute migration_attendance_summary.sql.';
            redirect('admin/dashboard');
        }

        $data = [
            'work_date'     => $workDate,
            'status_filter' => $statusFilter,
            'rows'          => $rows,
            'stats'         => $stats,
            'branches'      => $this->companyModel->getBranches($companyId, false),
            'employees'     => $this->userModel->getUsersByCompany($companyId),
            'branch_id'     => $branchId,
            'user_id_filter'=> $userIdFilter,
        ];
        $this->view('admin/control_asistencia', $data);
    }

    public function recomputeAttendance() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/dashboard');
        }
        csrf_verify();
        $companyId = requireAdminCompany('admin/dashboard');
        $start = trim($_POST['start_date'] ?? $_POST['date'] ?? date('Y-m-d'));
        $end   = trim($_POST['end_date'] ?? $start);
        $redirectTo = trim($_POST['redirect_to'] ?? 'admin/dashboard');
        $statusFilter = trim($_POST['status'] ?? '');
        if ($start > $end) {
            $tmp = $start; $start = $end; $end = $tmp;
        }
        try {
            $n = $this->planVsActualService->recomputeRange($companyId, $start, $end);
            $_SESSION['flash_success'] = "Asistencia actualizada del {$start} al {$end} ({$n} días-persona procesados).";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Error al recalcular asistencia: ' . $e->getMessage();
        }
        if ($redirectTo === 'admin/attendance' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            $qs = ['date' => $start];
            if ($statusFilter !== '') {
                $qs['status'] = $statusFilter;
            }
            redirect('admin/attendance?' . http_build_query($qs));
        }
        redirect($redirectTo);
    }

    /**
     * Calendario mensual: planificado + fichado + solicitudes + justificaciones.
     */
    public function calendar() {
        $companyId = requireAdminCompany('admin/dashboard');
        $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
        $userId = (int)($_GET['user_id'] ?? 0);

        if ($userId > 0) {
            requireSupervisorUserAccess($userId, 'admin/calendar');
        }

        $selectedUser = $userId > 0 ? $this->userModel->getUserById($userId) : null;
        $effectiveCompanyId = $selectedUser && (int)$selectedUser->company_id > 0
            ? (int)$selectedUser->company_id
            : $companyId;

        $employees = filterEmployeesForStaff($this->userModel->getUsersByCompany($effectiveCompanyId));
        $activeEmployees = array_filter($employees, function ($u) {
            return !empty($u->is_active);
        });

        if ($userId <= 0 && !empty($activeEmployees)) {
            $first = reset($activeEmployees);
            $userId = (int)$first->id;
            $selectedUser = $first;
            $effectiveCompanyId = (int)$first->company_id;
        }

        $calendar = null;
        if ($userId > 0) {
            if (!$selectedUser) {
                $selectedUser = $this->userModel->getUserById($userId);
            }
            try {
                $calendar = $this->calendarMonthService->buildMonth($effectiveCompanyId, $userId, $month);
            } catch (Exception $e) {
                $_SESSION['flash_error'] = 'Error al cargar calendario: ' . $e->getMessage();
            }
        }

        $vacationPending = null;
        if ($userId > 0 && function_exists('vacation_module_ready') && vacation_module_ready()) {
            $vacationPending = (new VacationBalance())->getTotalPending($userId);
        }

        $viewMode = ($_GET['view'] ?? 'grid') === 'list' ? 'list' : 'grid';
        $listFilter = ($_GET['filter'] ?? '') === 'alerts' ? 'alerts' : '';

        $monthStats = null;
        $employeeNav = ['prev' => null, 'next' => null];
        if ($calendar && !empty($calendar['days_list'])) {
            $monthStats = calendarComputeMonthStats($calendar['days_list']);
        }
        $activeList = array_values($activeEmployees);
        foreach ($activeList as $i => $emp) {
            if ((int)$emp->id === $userId) {
                if ($i > 0) {
                    $employeeNav['prev'] = $activeList[$i - 1];
                }
                if ($i < count($activeList) - 1) {
                    $employeeNav['next'] = $activeList[$i + 1];
                }
                break;
            }
        }

        $data = [
            'month'            => $month,
            'user_id'          => $userId,
            'employees'        => $activeEmployees,
            'selected_user'    => $selectedUser,
            'calendar'         => $calendar,
            'view_mode'        => $viewMode,
            'list_filter'      => $listFilter,
            'month_stats'      => $monthStats,
            'employee_nav'     => $employeeNav,
            'vacation_pending' => $vacationPending,
            'justification_types'       => AttendanceJustification::typeLabels(),
            'justification_types_grouped' => AttendanceJustification::typeLabelsGrouped(),
            'suggest_type'              => $_GET['suggest'] ?? '',
        ];
        $this->view('admin/calendar', $data);
    }

    public function saveAttendanceJustification() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/calendar');
        }
        csrf_verify();
        $companyId = requireAdminCompany('admin/calendar');
        $userId    = (int)($_POST['user_id'] ?? 0);
        $workDate  = trim($_POST['work_date'] ?? '');
        $month     = trim($_POST['month'] ?? date('Y-m'));
        $type      = trim($_POST['justification_type'] ?? '');
        $notes     = trim(strip_tags($_POST['notes'] ?? ''));
        $leaveTime = trim($_POST['leave_time'] ?? '');
        $priorNotice = !empty($_POST['prior_notice']);
        $delete    = !empty($_POST['delete_justification']);

        if ($userId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
            $_SESSION['flash_error'] = 'Datos inválidos para la justificación.';
            redirect('admin/calendar?month=' . urlencode($month) . '&user_id=' . $userId);
        }

        $targetUser = requireSupervisorUserAccess($userId, 'admin/calendar');
        $companyId = (int)$targetUser->company_id;

        $allowed = array_keys(AttendanceJustification::typeLabels());
        if ($delete) {
            try {
                $this->justificationModel->deleteByUserDate($userId, $workDate);
                $_SESSION['flash_success'] = 'Justificación eliminada.';
            } catch (Exception $e) {
                $_SESSION['flash_error'] = 'Ejecute migration_attendance_justifications.sql en la base de datos.';
            }
            redirect('admin/calendar?month=' . urlencode($month) . '&user_id=' . $userId . '&day=' . urlencode($workDate));
        }

        if (!in_array($type, $allowed, true)) {
            $_SESSION['flash_error'] = 'Seleccione un tipo de motivo válido.';
            redirect('admin/calendar?month=' . urlencode($month) . '&user_id=' . $userId . '&day=' . urlencode($workDate));
        }

        if (AttendanceJustification::isEarlyLeaveType($type)) {
            if ($leaveTime === '') {
                $_SESSION['flash_error'] = 'Indique la hora de salida para el permiso anticipado.';
                redirect('admin/calendar?month=' . urlencode($month) . '&user_id=' . $userId . '&day=' . urlencode($workDate) . '&suggest=' . urlencode($type));
            }
            if (!$priorNotice) {
                $_SESSION['flash_error'] = 'Debe confirmar que hubo aviso previo a RRHH o al supervisor.';
                redirect('admin/calendar?month=' . urlencode($month) . '&user_id=' . $userId . '&day=' . urlencode($workDate) . '&suggest=' . urlencode($type));
            }
        }

        $filePath = null;
        if (!empty($_FILES['certificate']['name']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
            $docMimes = uploads_document_mimes();
            $valid = uploads_validate_uploaded_file(
                $_FILES['certificate'],
                array_keys($docMimes),
                uploads_flat_mimes($docMimes),
                10 * 1024 * 1024
            );
            if (!$valid['ok']) {
                $_SESSION['flash_error'] = $valid['message'];
                redirect('admin/calendar?month=' . urlencode($month) . '&user_id=' . $userId . '&day=' . urlencode($workDate));
            }
            uploads_ensure_private_directory('justifications');
            $dir = APPROOT . '/../public/uploads/justifications/';
            $filename = 'just_' . $userId . '_' . str_replace('-', '', $workDate) . '_' . time() . '.' . $valid['ext'];
            if (move_uploaded_file($_FILES['certificate']['tmp_name'], $dir . $filename)) {
                $filePath = $filename;
            }
        }

        try {
            $this->justificationModel->upsert([
                'company_id'          => $companyId,
                'user_id'             => $userId,
                'work_date'           => $workDate,
                'justification_type'  => $type,
                'leave_time'          => $leaveTime,
                'prior_notice'        => $priorNotice,
                'notes'               => $notes,
                'file_path'           => $filePath,
                'created_by'          => $_SESSION['user_id'],
            ]);
            $_SESSION['flash_success'] = 'Motivo registrado correctamente.';
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'No se pudo guardar. Ejecute migration_attendance_justifications.sql.';
        }

        redirect('admin/calendar?month=' . urlencode($month) . '&user_id=' . $userId . '&day=' . urlencode($workDate));
    }

    public function streamJustification($userId = 0, $workDate = '') {
        $userId = (int)$userId;
        $workDate = trim((string)$workDate);
        if ($userId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
            http_response_code(404);
            exit;
        }
        if (adminCompanyId() <= 0) {
            http_response_code(403);
            exit;
        }
        $target = $this->userModel->getUserById($userId);
        if (!$target || !supervisorCanAccessUser($target)) {
            http_response_code(403);
            exit;
        }
        $just = $this->justificationModel->getByUserDate($userId, $workDate);
        if (!$just || empty($just->file_path)) {
            http_response_code(404);
            exit;
        }
        protected_upload_send('justifications/' . $just->file_path, true, basename((string)$just->file_path));
    }

    public function streamRequestCertificate($id = 0) {
        $id = (int)$id;
        if ($id <= 0 || !isset($_SESSION['user_company_id'])) {
            http_response_code(404);
            exit;
        }
        $request = $this->requestModel->getRequestByIdForCompany($id, (int)$_SESSION['user_company_id']);
        if (!$request || empty($request->certificate_path)) {
            http_response_code(404);
            exit;
        }
        protected_upload_send('request_certificates/' . $request->certificate_path, true, basename((string)$request->certificate_path));
    }

    public function history(){
        require_staff_overtime_access();
        $companyId = requireAdminCompany('admin/dashboard');
        $closures = $this->overtimeModel->getArchivedHistory($companyId);
        $data = ['closures' => $closures];
        $this->view('admin/history', $data);
    }

    public function closureDetails($id){
        require_staff_overtime_access();
        $companyId = requireAdminCompany('admin/dashboard');
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if(!$id){
            redirect('admin/history');
        }

        $closure = $this->overtimeModel->getClosureById($id, $companyId);
        if(!$closure){
            $_SESSION['flash_error'] = 'Cierre no encontrado.';
            redirect('admin/history');
        }

        $entries = $this->overtimeModel->getEntriesByClosureId($id, $companyId);
        $this->view('admin/closure_details', ['closure' => $closure, 'entries' => $entries]);
    }

    public function getClosureSummaryAjax(){
        header('Content-Type: application/json');
        if (!function_exists('overtime_staff_can_view') || !overtime_staff_can_view()) {
            echo json_encode([]);
            exit();
        }
        $companyId = adminCompanyId();
        if ($companyId <= 0) {
            echo json_encode([]);
            exit();
        }
        echo json_encode($this->overtimeModel->getAllPendingEntries($companyId));
        exit();
    }

    public function exportPendingClosure() {
        require_staff_overtime_access();
        $companyId = requireAdminCompany('admin/dashboard');
        $entries = $this->overtimeModel->getAllPendingEntries($companyId);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $filename = 'resumen_cierre_pendiente_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['Resumen del cierre pendiente — ' . date('d/m/Y H:i')], ';');
        fputcsv($out, [], ';');
        fputcsv($out, ['Empleado', 'Hs. 50%', 'Hs. 100%', 'Total'], ';');

        $sum50 = $sum100 = $sumAll = 0.0;
        foreach ($entries as $e) {
            $h50 = (float)$e->hours_50;
            $h100 = (float)$e->hours_100;
            $total = (float)$e->total_hours;
            $sum50 += $h50;
            $sum100 += $h100;
            $sumAll += $total;
            fputcsv($out, [
                $e->full_name,
                number_format($h50, 2, ',', ''),
                number_format($h100, 2, ',', ''),
                number_format($total, 2, ',', ''),
            ], ';');
        }
        fputcsv($out, [], ';');
        fputcsv($out, [
            'TOTAL',
            number_format($sum50, 2, ',', ''),
            number_format($sum100, 2, ',', ''),
            number_format($sumAll, 2, ',', ''),
        ], ';');
        fclose($out);
        exit;
    }

    public function createClosure(){
        require_staff_overtime_access();
        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            redirect('admin/dashboard');
        }
        csrf_verify();

        $companyId = requireAdminCompany('admin/dashboard');
        $closureId = $this->overtimeModel->createClosure($_SESSION['user_id'], $companyId);
        if ($closureId) {
            $_SESSION['flash_success'] = 'Cierre generado correctamente. Podés descargar el detalle en Excel.';
            redirect('admin/closureDetails/' . (int)$closureId);
        }
        $_SESSION['flash_error'] = 'No hay horas pendientes para cerrar o no se pudo generar el cierre.';
        redirect('admin/dashboard');
    }

    public function exportClosure($id = 0) {
        require_staff_overtime_access();
        $companyId = requireAdminCompany('admin/dashboard');
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if (!$id) {
            redirect('admin/history');
        }
        $closure = $this->overtimeModel->getClosureById($id, $companyId);
        if (!$closure) {
            $_SESSION['flash_error'] = 'Cierre no encontrado.';
            redirect('admin/history');
        }
        $entries = $this->overtimeModel->getEntriesByClosureId($id, $companyId);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $filename = 'cierre_horas_' . $id . '_' . date('Y-m-d', strtotime($closure->closure_date)) . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['Cierre #' . $id . ' — ' . date('d/m/Y H:i', strtotime($closure->closure_date))], ';');
        fputcsv($out, ['Cerrado por: ' . ($closure->admin_username ?? '')], ';');
        fputcsv($out, [], ';');
        fputcsv($out, ['Empleado', 'Fecha', 'Desde', 'Hasta', 'Hs. 50%', 'Hs. 100%', 'Total', 'Motivo'], ';');

        $sum50 = $sum100 = $sumAll = 0.0;
        foreach ($entries as $e) {
            $total = (float)$e->hours_50 + (float)$e->hours_100;
            $sum50 += (float)$e->hours_50;
            $sum100 += (float)$e->hours_100;
            $sumAll += $total;
            fputcsv($out, [
                $e->full_name,
                date('d/m/Y', strtotime($e->entry_date)),
                date('H:i', strtotime($e->start_time)),
                date('H:i', strtotime($e->end_time)),
                number_format((float)$e->hours_50, 2, ',', ''),
                number_format((float)$e->hours_100, 2, ',', ''),
                number_format($total, 2, ',', ''),
                $e->reason,
            ], ';');
        }
        fputcsv($out, [], ';');
        fputcsv($out, [
            'TOTAL',
            '',
            '',
            '',
            number_format($sum50, 2, ',', ''),
            number_format($sum100, 2, ',', ''),
            number_format($sumAll, 2, ',', ''),
            '',
        ], ';');
        fclose($out);
        exit;
    }

    public function companies(){
        requireAdminOnly('admin/dashboard');
        $companies = $this->companyModel->getAllCompanies();
        $this->view('admin/companies', [
            'companies' => $companies,
            'policy_templates_ready' => (new AccessControl())->isReady(),
            'organization_group_ready' => $this->companyModel->organizationGroupReady(),
            'location_ready' => $this->companyModel->locationReady(),
            'show_overtime_column' => $this->companyModel->hasShowOvertimeColumn(),
            'show_cp_extras_column' => $this->companyModel->hasShowCpExtrasColumn(),
        ]);
    }

    public function editCompany($id) {
        requireAdminOnly('admin/companies');
        $id = filter_var($id, FILTER_VALIDATE_INT);
        $company = $id ? $this->companyModel->getById($id) : null;
        if (!$company) {
            $_SESSION['flash_error'] = 'Empresa no encontrada.';
            redirect('admin/companies');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $name = postString('company_name');
            $locality = postString('locality');
            $province = postString('province');
            $brandColor = strtoupper(postString('brand_color') ?: '#E91E8C');
            $branches = isset($_POST['branches']) && is_array($_POST['branches']) ? $_POST['branches'] : [];
            $showOt = null;
            $showCp = null;
            $usesCp = function_exists('company_uses_casapav_tasks') && company_uses_casapav_tasks($id);
            if ($this->companyModel->hasShowOvertimeColumn() && !$usesCp) {
                $showOt = !empty($_POST['show_overtime']);
            }
            if ($this->companyModel->hasShowCpExtrasColumn() && $usesCp) {
                $showCp = !empty($_POST['show_cp_extras']);
            }
            if ($name === '') {
                $_SESSION['flash_error'] = 'El nombre es obligatorio.';
            } elseif ($this->companyModel->updateCompany($id, $name, $showOt, $showCp)) {
                if ($this->companyModel->locationReady()
                    && !$this->companyModel->saveLocation($id, $locality, $province)) {
                    $_SESSION['flash_error'] = 'La empresa se actualizó, pero no se pudo guardar la ubicación.';
                    redirect('admin/editCompany/' . $id);
                }
                if ($this->companyModel->branchesReady()
                    && !$this->companyModel->saveBranches($id, $branches)) {
                    $_SESSION['flash_error'] = 'La empresa se actualizó, pero revisá que cada sucursal tenga nombre, localidad y provincia.';
                    redirect('admin/editCompany/' . $id);
                }
                $this->applyNewBranchPolicyTemplates($id, $branches);
                if ($this->companyModel->brandingReady()) {
                    $logoPath = $this->storeCompanyLogo($id);
                    if ($logoPath === false) {
                        $_SESSION['flash_error'] = 'La empresa se actualizó, pero no se pudo guardar el logo. Usá PNG, JPG, GIF o WEBP de hasta 2 MB.';
                        redirect('admin/editCompany/' . $id);
                    }
                    if (!$this->companyModel->saveBranding($id, $brandColor, $logoPath)) {
                        $_SESSION['flash_error'] = 'La empresa se actualizó, pero no se pudo guardar su identidad visual.';
                        redirect('admin/editCompany/' . $id);
                    }
                }
                if ((int)($_SESSION['user_company_id'] ?? 0) === (int)$id) {
                    $_SESSION['user_company_name'] = $name;
                }
                $_SESSION['flash_success'] = 'Empresa actualizada. Las sucursales nuevas ya tienen su política inicial.';
                redirect('admin/editCompany/' . $id);
            } else {
                $_SESSION['flash_error'] = 'No se pudo actualizar la empresa.';
            }
        }
        $usesCp = function_exists('company_uses_casapav_tasks') && company_uses_casapav_tasks($id);
        $this->view('admin/edit_company', [
            'company' => $company,
            'location' => $this->companyModel->getLocation($id),
            'location_ready' => $this->companyModel->locationReady(),
            'branches' => $this->companyModel->getBranches($id, false),
            'branches_ready' => $this->companyModel->branchesReady(),
            'uses_cp_tasks' => $usesCp,
            'show_overtime_column' => $this->companyModel->hasShowOvertimeColumn(),
            'show_cp_extras_column' => $this->companyModel->hasShowCpExtrasColumn(),
            'branding' => $this->companyModel->getBranding($id),
        ]);
    }

    public function createCompany(){
        requireAdminOnly('admin/companies');
        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            redirect('admin/companies');
        }
        csrf_verify();

        $companyName = isset($_POST['company_name']) ? trim(strip_tags($_POST['company_name'])) : '';
        if(empty($companyName)){
            $_SESSION['flash_error'] = 'El nombre de la empresa es obligatorio.';
            redirect('admin/companies');
        }

        $organizationGroup = Company::normalizeOrganizationGroup($_POST['organization_group'] ?? 'paviotti');
        $companyId = $this->companyModel->createCompany($companyName, $organizationGroup);
        if($companyId){
            $sourceCompanyId = (int)($_POST['policy_source_company_id'] ?? 0);
            $access = new AccessControl();
            if ($sourceCompanyId > 0 && $access->isReady()) {
                $access->copyPolicies($sourceCompanyId, 0, $companyId, 0, (int)$_SESSION['user_id']);
            }
            $_SESSION['flash_success'] = $sourceCompanyId > 0 ? 'Empresa creada con los permisos de la empresa modelo.' : 'Empresa creada correctamente. Configurá sus permisos antes de asignar empleados.';
            redirect('admin/editCompany/' . $companyId);
        } else {
            $_SESSION['flash_error'] = 'No se pudo crear la empresa.';
        }
        redirect('admin/companies');
    }

    /** Aplica una plantilla sólo a las sucursales creadas en este envío. */
    private function applyNewBranchPolicyTemplates($companyId, array $branches) {
        $access = new AccessControl();
        if (!$access->isReady()) return;
        foreach ($branches as $branch) {
            if ((int)($branch['id'] ?? 0) > 0) continue;
            $source = trim((string)($branch['policy_template'] ?? ''));
            if ($source === '' || $source === 'inherit') continue;
            $parts = explode(':', $source);
            if (count($parts) !== 2) continue;
            $sourceCompanyId = (int)$parts[0]; $sourceBranchId = (int)$parts[1];
            $name = trim((string)($branch['name'] ?? ''));
            if ($sourceCompanyId <= 0 || $name === '') continue;
            foreach ($this->companyModel->getBranches($companyId, false) as $savedBranch) {
                if ($savedBranch->name === $name) {
                    $access->copyPolicies($sourceCompanyId, $sourceBranchId, $companyId, (int)$savedBranch->id, (int)$_SESSION['user_id']);
                    break;
                }
            }
        }
    }

    /** Guarda logos públicos de empresa fuera de los uploads privados. */
    private function storeCompanyLogo($companyId) {
        if (empty($_FILES['company_logo']) || ($_FILES['company_logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        $file = $_FILES['company_logo'];
        $valid = uploads_validate_uploaded_file($file, uploads_avatar_extensions(), uploads_avatar_mimes(), 2097152);
        if (!$valid['ok']) return false;
        $dir = dirname(APPROOT) . '/public/img/companies/';
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) return false;
        try { $token = bin2hex(random_bytes(8)); } catch (Throwable $e) { $token = uniqid(); }
        $filename = 'company_' . (int)$companyId . '_' . $token . '.' . $valid['ext'];
        if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) return false;
        return 'img/companies/' . $filename;
    }

    public function users() {
        requireAdminOnly();
        $companyFilter = isset($_GET['company_id']) ? (int)$_GET['company_id'] : adminCompanyId();
        // Suite P&M: cada organización ve solo su nómina.
        // Moderna => forzado a la empresa Moderna; Paviotti => la empresa Moderna
        // queda excluida del listado y de los chips de filtro.
        $modernaCompanyId = function_exists('org_moderna_company_id') ? org_moderna_company_id() : null;
        $isModernaView = function_exists('org_is_moderna') && org_is_moderna();
        if ($isModernaView && $modernaCompanyId) {
            $companyFilter = $modernaCompanyId;
        } elseif (!$isModernaView && $modernaCompanyId && $companyFilter === $modernaCompanyId) {
            $companyFilter = (adminCompanyId() > 0 && adminCompanyId() !== $modernaCompanyId) ? adminCompanyId() : 0;
        }
        $branchFilter = $companyFilter > 0 ? adminBranchId() : 0;
        $users = $this->userModel->getAllUsersWithCompany($companyFilter > 0 ? $companyFilter : null, $branchFilter);
        $recordReady = $this->employeeRecordModel->isReady();
        $recordMetadata = $recordReady ? $this->employeeRecordModel->getUserListMetadata($companyFilter > 0 ? $companyFilter : null) : [];
        foreach ($users as $user) {
            $meta = $recordMetadata[(int)$user->id] ?? null;
            foreach (['employee_number','employment_status','work_mode','employment_type','start_date','end_date','position_name','area_name','supervisor_name','branch_names','branch_count','has_structured_address','has_health_coverage'] as $field) {
                $user->$field = $meta->$field ?? null;
            }
            $checks = [
                !empty($user->document_number) || !empty($user->cuil),
                !empty($user->email) || !empty($user->phone_number),
                !empty($user->hire_date),
                !empty($user->position_name),
                (int)($user->branch_count ?? 0) > 0,
                !empty($user->has_structured_address),
                !empty($user->has_health_coverage),
            ];
            $user->record_completed = count(array_filter($checks));
            $user->record_total = count($checks);
            $user->record_percent = (int)round($user->record_completed * 100 / max(1, $user->record_total));
        }
        $companies = $this->companyModel->getAllCompanies();
        if (!$isModernaView && $modernaCompanyId) {
            $users = array_values(array_filter($users, fn($u) => (int)($u->company_id ?? 0) !== $modernaCompanyId));
            $companies = array_values(array_filter($companies, fn($c) => (int)$c->id !== $modernaCompanyId));
        }
        $this->view('admin/users', [
            'users'          => $users,
            'companies'      => $companies,
            'company_filter' => $companyFilter,
            'branch_filter'  => $branchFilter,
            'active_branch'  => $branchFilter > 0 ? $this->companyModel->getBranchByIdForCompany($branchFilter, $companyFilter, true) : null,
            'employee_record_ready' => $recordReady,
        ]);
    }

    public function employeeDetails($id){
        require_staff_overtime_access();
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if (!$id) {
            redirect('admin/users');
        }
        $user = adminResolveUser($id);
        if (function_exists('overtime_staff_can_view') && !overtime_staff_can_view((int)$user->company_id, $user)) {
            $_SESSION['flash_error'] = 'Horas extras no habilitadas para este empleado.';
            redirect('admin/users');
        }

        $entries = array_filter(
            $this->overtimeModel->getEntriesByUserId($id),
            function($entry){ return ($entry->status ?? 'pending') === 'pending'; }
        );

        $this->overtimeModel->recalculatePendingRates(adminCompanyId());
        $entries = array_filter(
            $this->overtimeModel->getEntriesByUserId($id),
            function($entry){ return ($entry->status ?? 'pending') === 'pending'; }
        );

        $this->view('admin/employee_details', ['user' => $user, 'entries' => $entries]);
    }

    public function pendingOvertime() {
        require_staff_overtime_access();
        $companyId = requireAdminCompany('admin/dashboard');
        if (function_exists('overtime_staff_can_view') && !overtime_staff_can_view($companyId)) {
            $_SESSION['flash_error'] = 'Horas extras no habilitadas para tu perfil o empresa.';
            redirect('admin/dashboard');
        }

        $entries = $this->overtimeModel->getAllPendingEntries($companyId);
        $this->overtimeModel->recalculatePendingRates($companyId);
        $entries = $this->overtimeModel->getAllPendingEntries($companyId);
        $entries = filterStaffRowsByUserArea($entries);

        $this->view('admin/pending_overtime', ['entries' => $entries]);
    }

    public function editEntry($id){
        require_staff_overtime_access();
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if(!$id){
            redirect('admin/users');
        }

        $entry = $this->overtimeModel->getEntryById($id);
        if(!$entry){
            redirect('admin/users');
        }

        $user = adminResolveUser((int)$entry->user_id, 'admin/users');
        if (function_exists('overtime_staff_can_view') && !overtime_staff_can_view((int)$user->company_id, $user)) {
            $_SESSION['flash_error'] = 'No podés editar horas extras de este empleado.';
            redirect('admin/users');
        }

        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            csrf_verify();
            $data = [
                'id' => $id,
                'date' => isset($_POST['date']) ? trim($_POST['date']) : '',
                'start_time' => isset($_POST['start_time']) ? trim($_POST['start_time']) : '',
                'end_time' => isset($_POST['end_time']) ? trim($_POST['end_time']) : '',
                'is_holiday' => isset($_POST['is_holiday']) ? (int)$_POST['is_holiday'] : 0,
                'reason' => isset($_POST['reason']) ? trim(strip_tags($_POST['reason'])) : '',
            ];

            if($this->overtimeModel->updateEntry($data)){
                $_SESSION['flash_success'] = 'Entrada actualizada correctamente.';
                redirect('admin/employeeDetails/' . $entry->user_id);
            }

            $_SESSION['flash_error'] = 'No se pudo actualizar la entrada.';
            redirect('admin/editEntry/' . $id);
        }

        $this->view('admin/edit_entry', ['entry' => $entry]);
    }

    public function deleteEntry($id, $userId = null){
        require_staff_overtime_access();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/users');
        }
        csrf_verify();
        $id = filter_var($id, FILTER_VALIDATE_INT);
        $entry = $id ? $this->overtimeModel->getEntryById($id) : null;
        if(!$entry){
            redirect('admin/users');
        }
        if (($entry->status ?? 'pending') !== 'pending') {
            $_SESSION['flash_error'] = 'Solo se pueden eliminar horas extras pendientes.';
            redirect('admin/employeeDetails/' . (int)$entry->user_id);
        }

        $user = adminResolveUser((int)$entry->user_id, 'admin/users');
        if (function_exists('overtime_staff_can_view') && !overtime_staff_can_view((int)$user->company_id, $user)) {
            $_SESSION['flash_error'] = 'No podés eliminar horas extras de este empleado.';
            redirect('admin/users');
        }

        if($this->overtimeModel->deleteEntry($id)){
            $_SESSION['flash_success'] = 'Entrada eliminada correctamente.';
        } else {
            $_SESSION['flash_error'] = 'No se pudo eliminar la entrada.';
        }

        $redirectTo = 'admin/employeeDetails/' . $entry->user_id;
        if (!empty($_POST['redirect_to']) && $_POST['redirect_to'] === 'pendingOvertime') {
            $redirectTo = 'admin/pendingOvertime';
        }
        redirect($redirectTo);
    }

    public function createUser(){
        requireAdminOnly();
        if($_SERVER['REQUEST_METHOD'] == 'POST'){
            csrf_verify();
            $data = array_merge([
                'username' => isset($_POST['username']) ? trim($_POST['username']) : '',
                'password' => isset($_POST['password']) ? trim($_POST['password']) : '',
                'confirm_password' => isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '',
                'role' => isset($_POST['role']) ? $_POST['role'] : 'empleado',
                'company_id' => isset($_POST['company_id']) ? (int)$_POST['company_id'] : 0,
                'branch_id' => isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0,
                'branch_ids' => isset($_POST['branch_ids']) && is_array($_POST['branch_ids']) ? $_POST['branch_ids'] : [],
                'attendance_control_mode' => User::normalizeAttendanceControlMode($_POST['attendance_control_mode'] ?? 'required'),
                'employee_group' => User::normalizeOrganizationGroup($_POST['employee_group'] ?? 'paviotti'),
                'profile_picture' => 'default.png',
                'errors' => [],
            ], name_from_post($_POST), User::profileFromPost($_POST), [
                'area_id' => isset($_POST['area_id']) ? (int)$_POST['area_id'] : 0,
                'probation_start_date' => trim($_POST['probation_start_date'] ?? ''),
                'hire_date' => trim($_POST['hire_date'] ?? ''),
                'agreement_id' => isset($_POST['agreement_id']) ? (int)$_POST['agreement_id'] : 0,
            ], EmployeeRecord::fromPost($_POST));
            if ($this->employeeRecordModel->isReady()) {
                $data['errors'] = array_merge($data['errors'], $this->employeeRecordModel->validate($data, $data['company_id'], 0));
            }
            if (empty($data['first_name'])) {
                $data['errors']['first_name'] = 'Los nombres son obligatorios.';
            }
            if (empty($data['last_name'])) {
                $data['errors']['last_name'] = 'Los apellidos son obligatorios.';
            }
            if(empty($data['username']) || empty($data['password'])){ $data['errors']['general'] = 'Por favor, completa todos los campos obligatorios.'; }
            if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $data['errors']['email'] = 'Email no válido.';
            }
            if ($data['company_id'] <= 0) { $data['errors']['company_id'] = 'Seleccioná la empresa del usuario.'; }
            $this->validateUserBranch($data);
            $this->validateUserArea($data);
            if($this->userModel->findUserByUsername($data['username'])){ $data['errors']['username'] = 'Este nombre de usuario ya está en uso.'; }
            if(strlen($data['password']) < 4){ $data['errors']['password'] = 'La contraseña debe tener al menos 4 caracteres.'; }
            if($data['password'] != $data['confirm_password']){ $data['errors']['confirm_password'] = 'Las contraseñas no coinciden.'; }
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $valid = uploads_validate_uploaded_file(
                    $_FILES['profile_picture'],
                    uploads_avatar_extensions(),
                    uploads_avatar_mimes(),
                    2097152
                );
                if ($valid['ok']) {
                    $avatarDir = APPROOT . '/../public/uploads/avatars/';
                    if (!is_dir($avatarDir)) {
                        mkdir($avatarDir, 0755, true);
                    }
                    $new_filename = uniqid('avatar_', true) . '.' . $valid['ext'];
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $avatarDir . $new_filename)) {
                        $data['profile_picture'] = $new_filename;
                    }
                } else {
                    $data['errors']['picture'] = $valid['message'];
                }
            }
            if(empty($data['errors'])){
                $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
                if($this->userModel->createUser($data)){
                    $createdUser = $this->userModel->getUserByUsername($data['username']);
                    if ($createdUser && (new AccessControl())->isReady()) {
                        (new AccessControl())->saveScopes((int)$createdUser->id, [[
                            'company_id' => (int)$data['company_id'],
                            'branch_id' => (int)($data['branch_id'] ?? 0),
                            'access_role' => $data['role'] === 'admin' ? 'administrador' : ($data['role'] === 'supervisor' ? 'encargado' : 'operario'),
                            'is_primary' => 1, 'is_active' => 1, 'starts_on' => $data['hire_date'] ?? '',
                        ]], (int)$_SESSION['user_id']);
                    }
                    if ($createdUser && !$this->employeeRecordModel->save((int)$createdUser->id, $data['company_id'], $data['area_id'], $data['agreement_id'], $data['hire_date'], $data)) {
                        $_SESSION['flash_error'] = 'El usuario fue creado, pero no se pudo completar el legajo ampliado.';
                    }
                    $_SESSION['flash_success'] = 'Usuario creado con éxito.';
                    redirect('admin/users');
                }
            } else {
                $data['companies'] = $this->companyModel->getAllCompanies();
                $data['default_company_id'] = $this->companyModel->getDefaultCompanyId();
                $this->view('admin/create_user', array_merge($data, $this->employmentViewData(0, $data), $this->employeeRecordViewData(0, $data['company_id'])));
            }
        } else {
            $this->view('admin/create_user', array_merge([
                'errors' => [],
                'companies' => $this->companyModel->getAllCompanies(),
                'default_company_id' => $this->companyModel->getDefaultCompanyId(),
            ], $this->employmentViewData(), $this->employeeRecordViewData(0, (int)($this->companyModel->getDefaultCompanyId() ?? 0))));
        }
    }
    
    public function editUser($id){
        requireAdminOnly();
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if (!$id) {
            redirect('admin/users');
        }

        if($_SERVER['REQUEST_METHOD'] == 'POST'){
            csrf_verify();
            $companyId = isset($_POST['company_id']) ? (int)$_POST['company_id'] : 0;
            $data = array_merge([
                'id' => $id,
                'role' => isset($_POST['role']) ? $_POST['role'] : 'empleado',
                'company_id' => $companyId,
                'branch_id' => isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0,
                'branch_ids' => isset($_POST['branch_ids']) && is_array($_POST['branch_ids']) ? $_POST['branch_ids'] : [],
                'attendance_control_mode' => User::normalizeAttendanceControlMode($_POST['attendance_control_mode'] ?? 'required'),
                'employee_group' => User::normalizeOrganizationGroup($_POST['employee_group'] ?? 'paviotti'),
                'hourly_rate' => isset($_POST['hourly_rate']) ? trim($_POST['hourly_rate']) : 0,
                'weekly_hour_limit' => isset($_POST['weekly_hour_limit']) ? trim($_POST['weekly_hour_limit']) : '',
                'vacation_days_available' => isset($_POST['vacation_days_available']) ? trim($_POST['vacation_days_available']) : '',
                'probation_start_date' => trim($_POST['probation_start_date'] ?? ''),
                'hire_date' => trim($_POST['hire_date'] ?? ''),
                'agreement_id' => isset($_POST['agreement_id']) ? (int)$_POST['agreement_id'] : 0,
                'password' => isset($_POST['password']) ? trim($_POST['password']) : '',
                'confirm_password' => isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '',
                'password_hash' => '',
                'profile_picture' => '',
                'errors' => array(),
            ], name_from_post($_POST), User::profileFromPost($_POST), [
                'area_id' => isset($_POST['area_id']) ? (int)$_POST['area_id'] : 0,
                'plex_operator_name' => trim($_POST['plex_operator_name'] ?? ''),
            ], EmployeeRecord::fromPost($_POST));
            if ($this->employeeRecordModel->isReady()) {
                $data['errors'] = array_merge($data['errors'], $this->employeeRecordModel->validate($data, $companyId, $id));
            }
            if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $data['errors']['email'] = 'Email no válido.';
            }
            if (empty($data['first_name'])) {
                $data['errors']['first_name'] = 'Los nombres son obligatorios.';
            }
            if (empty($data['last_name'])) {
                $data['errors']['last_name'] = 'Los apellidos son obligatorios.';
            }
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $valid = uploads_validate_uploaded_file(
                    $_FILES['profile_picture'],
                    uploads_avatar_extensions(),
                    uploads_avatar_mimes(),
                    2097152
                );
                if ($valid['ok']) {
                    $avatarDir = APPROOT . '/../public/uploads/avatars/';
                    if (!is_dir($avatarDir)) {
                        mkdir($avatarDir, 0755, true);
                    }
                    $new_filename = uniqid('avatar_') . '.' . $valid['ext'];
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $avatarDir . $new_filename)) {
                        $data['profile_picture'] = $new_filename;
                    }
                } else {
                    $data['errors']['picture'] = $valid['message'];
                }
            }
            if ($data['company_id'] <= 0) {
                $data['errors']['company_id'] = 'Seleccioná la empresa del usuario.';
            } elseif (!$this->companyModel->getById($data['company_id'])) {
                $data['errors']['company_id'] = 'La empresa seleccionada no existe.';
            }
            $this->validateUserBranch($data);
            $this->validateUserArea($data);
            if(!empty($data['password'])){
                if(strlen($data['password']) < 4){ $data['errors']['password'] = 'La contraseña debe tener al menos 4 caracteres.'; }
                if($data['password'] != $data['confirm_password']){ $data['errors']['confirm_password'] = 'Las contraseñas no coinciden.'; }
                if(empty($data['errors']['password']) && empty($data['errors']['confirm_password'])){
                    $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
                }
            }
            if(empty($data['errors'])){
                $clockMappings = isset($_POST['clock_mappings']) && is_array($_POST['clock_mappings']) ? $_POST['clock_mappings'] : array();
                // Primero el update del usuario; los mapeos (que borran y reinsertan) solo si aquel funcionó.
                if(!$this->userModel->updateUser($data)){
                    die('Algo salió mal al actualizar el usuario.');
                }
                if(!$this->userModel->saveBranchAssignments($id, $data['company_id'], $data['branch_ids'], $data['branch_id'])){
                    die('Error al guardar las sucursales del usuario.');
                }
                if(!$this->employeeRecordModel->save($id, $data['company_id'], $data['area_id'], $data['agreement_id'], $data['hire_date'], $data)){
                    die('Error al guardar el legajo ampliado del usuario.');
                }
                if(!$this->userModel->saveClockMappings($id, $clockMappings)){
                    die('Error al guardar los IDs de los relojes.');
                }
                if ((int)$id === (int)($_SESSION['user_id'] ?? 0)) {
                    $_SESSION['user_company_id'] = $data['company_id'];
                    $_SESSION['user_company_name'] = $this->companyModel->getNameById($data['company_id']);
                    $_SESSION['user_full_name'] = $data['full_name'];
                }
                $_SESSION['flash_success'] = 'Usuario actualizado correctamente.';
                redirect('admin/users');
            } else {
                $user = $this->userModel->getUserById($id);
                if ($user) {
                    $user->company_id = $data['company_id'];
                    $user->branch_id = !empty($data['branch_id']) ? (int)$data['branch_id'] : null;
                    $user->area_id = !empty($data['area_id']) ? (int)$data['area_id'] : null;
                    $user->employee_group = $data['employee_group'];
                }
                $clockMappings = $this->userModel->getClockMappingsForUser($id);
                $data['user'] = $user;
                $data['clock_mappings'] = $clockMappings;
                $data['companies'] = $this->companyModel->getAllCompanies();
                $data['current_company_name'] = $this->companyModel->getNameById($data['company_id']);
                $this->view('admin/edit_user', array_merge($data, $this->employmentViewData($id, $user), $this->employeeRecordViewData($id, $data['company_id'])));
            }
        } else {
            $user = $this->userModel->getUserById($id);
            if (!$user) {
                redirect('admin/users');
            }
            $clockMappings = $this->userModel->getClockMappingsForUser($id);
            $data = array(
                'user' => $user,
                'clock_mappings' => $clockMappings,
                'companies' => $this->companyModel->getAllCompanies(),
                'current_company_name' => $this->companyModel->getNameById($user->company_id),
                'errors' => array()
            );
            $this->view('admin/edit_user', array_merge($data, $this->employmentViewData($id, $user), $this->employeeRecordViewData($id, (int)$user->company_id)));
        }
    }

    public function toggleUserStatus($id){
        requireAdminOnly('admin/users');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/users');
        }
        csrf_verify();
        $id = (int)$id;
        if ($id == $_SESSION['user_id']) {
            $_SESSION['flash_error'] = 'No puedes desactivar tu propia cuenta.';
            redirect('admin/users');
        }
        $target = $this->userModel->getUserById($id);
        if (!$target) {
            $_SESSION['flash_error'] = 'Usuario no encontrado.';
            redirect('admin/users');
        }
        if ((int)($target->company_id ?? 0) > 0) {
            requireUserInAdminCompany($id, 'admin/users');
        }
        if($this->userModel->toggleUserStatus($id)){
            $_SESSION['flash_success'] = 'El estado del usuario ha sido cambiado con éxito.';
        } else {
            $_SESSION['flash_error'] = 'No se pudo cambiar el estado del usuario.';
        }
        redirect('admin/users');
    }

    public function employeeCatalogs() {
        requireAdminOnly();
        $companyId = requireAdminCompany('admin/users');
        if (!$this->employeeRecordModel->isReady()) {
            $_SESSION['flash_error'] = 'Falta ejecutar migration_employee_record_complete.sql.';
            redirect('admin/users');
        }
        $this->view('admin/employee_catalogs', [
            'positions' => $this->employeeRecordModel->getPositions($companyId),
            'insurers' => $this->employeeRecordModel->getInsurers(),
            'plans' => $this->employeeRecordModel->getPlans(),
            'company_id' => $companyId,
        ]);
    }

    public function saveEmployeeCatalog() {
        requireAdminOnly();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/employeeCatalogs');
        csrf_verify();
        $companyId = requireAdminCompany('admin/employeeCatalogs');
        $kind = trim((string)($_POST['kind'] ?? ''));
        $ok = false;
        if ($kind === 'position') $ok = $this->employeeRecordModel->savePosition($companyId, $_POST['name'] ?? '', $_POST['description'] ?? '');
        if ($kind === 'insurer') $ok = $this->employeeRecordModel->saveInsurer($_POST['display_name'] ?? '', $_POST['legal_name'] ?? '', $_POST['insurer_type'] ?? 'otra');
        if ($kind === 'plan') $ok = $this->employeeRecordModel->savePlan((int)($_POST['health_insurer_id'] ?? 0), $_POST['name'] ?? '', $_POST['code'] ?? '');
        $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $ok ? 'Catálogo actualizado.' : 'No se pudo guardar. Revisá los datos.';
        redirect('admin/employeeCatalogs');
    }

    public function requests(){
        if (isset($_GET['company_id'])) {
            $cid = (int)$_GET['company_id'];
            if ($cid > 0) {
                setAdminActiveCompany($cid);
            }
        }
        $companyId = requireAdminCompany('admin/requests');
        $allRequests = $this->requestModel->getAllRequestsByCompany($companyId);
        if (isSupervisor()) {
            $allRequests = filterStaffRowsByUserArea($allRequests);
        }
        if (function_exists('vacation_module_ready') && vacation_module_ready()) {
            $vacationTypeId = $this->requestModel->getVacationRequestTypeId();
            $vacationLedger = new VacationLedgerService();
            foreach ($allRequests as $requestRow) {
                if ($requestRow->status === 'Pendiente' && (int)$requestRow->request_type_id === (int)$vacationTypeId) {
                    $requestRow->vacation_preview = $vacationLedger->previewRequest($requestRow);
                }
            }
        }
        $calendarEvents = array();
        if(!empty($allRequests)){
            foreach($allRequests as $request){
                $color = '#6c757d';
                if ($request->status == 'Aprobado') $color = '#198754';
                if ($request->status == 'Rechazado') $color = '#dc3545';
                $calendarEvents[] = [
                    'title' => $request->full_name . ' - ' . $request->type_name,
                    'start' => $request->start_date,
                    'end' => $request->end_date ? date('Y-m-d', strtotime($request->end_date . ' +1 day')) : null,
                    'color' => $color,
                    'allDay' => true
                ];
            }
        }
        $jsonEvents = json_encode($calendarEvents);
        if ($jsonEvents === false) {
            $jsonEvents = '[]';
        }
        $pendingShiftSwaps = [];
        $swapModuleReady = $this->shiftSwapModel->isSchemaReady();
        if ($swapModuleReady) {
            $pendingShiftSwaps = $this->shiftSwapModel->getPendingByCompany($companyId);
        }

        $data = [
            'requests' => $allRequests,
            'calendarEvents' => $jsonEvents,
            'open_request_id' => isset($_GET['open']) ? (int)$_GET['open'] : 0,
            'pending_shift_swaps' => $pendingShiftSwaps,
            'swap_module_ready' => $swapModuleReady,
            'company_id' => $companyId,
            'company_name' => $_SESSION['user_company_name'] ?? '',
            'companies' => $this->companyModel->getAllCompanies(),
        ];
        $this->view('admin/requests', $data);
    }

    public function approveShiftSwap($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/requests');
        }
        csrf_verify();
        $companyId = requireAdminCompany('admin/requests');
        $id = (int)$id;
        if (!$this->shiftSwapModel->isSchemaReady()) {
            $_SESSION['flash_error'] = 'Ejecute migration_shift_swaps_fix.sql en la base de datos.';
            redirect('admin/requests');
        }
        if (!$this->shiftSwapModel->getByIdForCompany($id, $companyId)) {
            $_SESSION['flash_error'] = 'Cambio de turno no encontrado en tu empresa activa.';
            redirect('admin/requests');
        }
        $result = $this->shiftSwapModel->approveSwap($id, (int)$_SESSION['user_id']);
        $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['message'];
        redirect('admin/requests');
    }

    public function rejectShiftSwap($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/requests');
        }
        csrf_verify();
        $companyId = requireAdminCompany('admin/requests');
        $id = (int)$id;
        if (!$this->shiftSwapModel->isSchemaReady()) {
            $_SESSION['flash_error'] = 'Ejecute migration_shift_swaps_fix.sql en la base de datos.';
            redirect('admin/requests');
        }
        if (!$this->shiftSwapModel->getByIdForCompany($id, $companyId)) {
            $_SESSION['flash_error'] = 'Cambio de turno no encontrado en tu empresa activa.';
            redirect('admin/requests');
        }
        $result = $this->shiftSwapModel->rejectSwap($id, (int)$_SESSION['user_id']);
        $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['message'];
        redirect('admin/requests');
    }

    public function processRequest() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/requests');
        }
        csrf_verify();
        requireAdminCompany('admin/requests');

        $companyId = $_SESSION['user_company_id'];
        $id = (int)($_POST['request_id'] ?? 0);
        $action = trim($_POST['action'] ?? '');

        if ($id <= 0) {
            $_SESSION['flash_error'] = 'Solicitud inválida.';
            redirect('admin/requests');
        }

        $request = $this->requestModel->getRequestByIdForCompany($id, $companyId);
        if (!$request) {
            $_SESSION['flash_error'] = 'Solicitud no encontrada.';
            redirect('admin/requests');
        }

        $adminNotes = trim(strip_tags($_POST['admin_notes'] ?? ''));
        $meta = [];
        if ($adminNotes !== '') {
            $meta['admin_notes'] = $adminNotes;
        }

        $certFilename = $this->uploadRequestCertificate($id);
        if ($certFilename) {
            $meta['certificate_path'] = $certFilename;
        }

        if (!empty($meta)) {
            try {
                $this->requestModel->updateAdminMeta($id, $meta);
            } catch (Exception $e) {
                $_SESSION['flash_error'] = 'Ejecute migration_requests_admin_review.sql en la base de datos.';
                redirect('admin/requests?open=' . $id);
            }
        }

        $redirectUrl = 'admin/requests?open=' . $id;

        try {
            if ($action === 'approve') {
                $result = $this->approveRequestWithBalance($id, $request);
                if ($result['ok']) {
                    $_SESSION['flash_success'] = $result['message'];
                    redirect('admin/requests');
                }
                $_SESSION['flash_error'] = $result['message'];
                redirect($redirectUrl);
            }

            if ($action === 'reject') {
                $rejectResult = $this->rejectRequestWithVacationReversal($id, $request);
                if ($rejectResult['ok']) {
                    $_SESSION['flash_success'] = $rejectResult['message'];
                    redirect('admin/requests');
                }
                $_SESSION['flash_error'] = $rejectResult['message'];
                redirect($redirectUrl);
            }

            if ($action === 'dismiss') {
                if ($this->requestModel->dismissFromQueue($id)) {
                    $_SESSION['flash_success'] = 'Solicitud descartada de la bandeja de prioridad.';
                    redirect('admin/requests');
                }
                $_SESSION['flash_error'] = 'No se pudo descartar la solicitud.';
                redirect($redirectUrl);
            }

            if ($action === 'save_certificate') {
                $_SESSION['flash_success'] = $certFilename
                    ? 'Certificado guardado correctamente.'
                    : 'Datos guardados.';
                redirect($redirectUrl);
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Ejecute migration_requests_admin_review.sql en la base de datos.';
            redirect($redirectUrl);
        }

        $_SESSION['flash_error'] = 'Acción no válida.';
        redirect('admin/requests');
    }

    private function uploadRequestCertificate($requestId) {
        if (empty($_FILES['certificate']['name']) || $_FILES['certificate']['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $docMimes = uploads_document_mimes();
        $valid = uploads_validate_uploaded_file(
            $_FILES['certificate'],
            array_keys($docMimes),
            uploads_flat_mimes($docMimes),
            10 * 1024 * 1024
        );
        if (!$valid['ok']) {
            $_SESSION['flash_error'] = $valid['message'];
            return null;
        }
        uploads_ensure_private_directory('request_certificates');
        $dir = APPROOT . '/../public/uploads/request_certificates/';
        $filename = 'req_' . (int)$requestId . '_' . time() . '.' . $valid['ext'];
        if (move_uploaded_file($_FILES['certificate']['tmp_name'], $dir . $filename)) {
            return $filename;
        }
        return null;
    }

    /**
     * @return array{ok:bool,message:string}
     */
    private function approveRequestWithBalance($id, $request = null) {
        if (!$request) {
            $request = $this->requestModel->getRequestById($id);
        }
        if (!$request) {
            return ['ok' => false, 'message' => 'Solicitud no encontrada.'];
        }

        $vacationRequestTypeId = $this->requestModel->getVacationRequestTypeId();
        $isVacation = $vacationRequestTypeId && (int)$request->request_type_id === $vacationRequestTypeId;

        if ($isVacation && vacation_module_ready()) {
            $adminId = (int)($_SESSION['user_id'] ?? 0);
            $db = new Database();
            $db->beginTransaction();
            try {
                $requestTx = new Request($db);
                $lockedRequest = $requestTx->getRequestByIdForUpdate($id);
                if (!$lockedRequest || $lockedRequest->status !== 'Pendiente') {
                    $db->rollBack();
                    return ['ok' => false, 'message' => 'La solicitud ya fue procesada por otro administrador.'];
                }
                $ledger = new VacationLedgerService($db);
                $exceptionReason = trim(strip_tags($_POST['vacation_exception_reason'] ?? ''));
                $takeResult = $ledger->applyTakeFromRequest($lockedRequest, $adminId, $exceptionReason);
                if (!$takeResult['ok']) {
                    $db->rollBack();
                    return $takeResult;
                }
                if (!$requestTx->updateRequestStatus($id, 'Aprobado')) {
                    $db->rollBack();
                    return ['ok' => false, 'message' => 'Error al aprobar la solicitud.'];
                }
                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                return ['ok' => false, 'message' => 'Error al aprobar la solicitud.'];
            }
            return ['ok' => true, 'message' => 'Vacaciones aprobadas. ' . $takeResult['message']];
        }

        if ($isVacation) {
            $user = $this->userModel->getUserById($request->user_id);
            $availableDays = $user->vacation_days_available;
            $entitlement = new VacationEntitlementService();
            $duration = $entitlement->countDaysForUserRange(
                $request->user_id,
                $request->start_date,
                $request->end_date ?: $request->start_date
            );
            if ($duration > $availableDays) {
                return ['ok' => false, 'message' => 'El usuario no tiene suficientes días de vacaciones para aprobar esta solicitud.'];
            }
            $db = new Database();
            $ownTx = !$db->inTransaction();
            if ($ownTx) {
                $db->beginTransaction();
            }
            try {
                $this->userModel->updateVacationBalance($request->user_id, $availableDays - $duration);
                if (!$this->requestModel->updateRequestStatus($id, 'Aprobado')) {
                    if ($ownTx) {
                        $db->rollBack();
                    }
                    return ['ok' => false, 'message' => 'Error al aprobar la solicitud.'];
                }
                if ($ownTx) {
                    $db->commit();
                }
            } catch (Throwable $e) {
                if ($ownTx) {
                    $db->rollBack();
                }
                return ['ok' => false, 'message' => 'Error al aprobar la solicitud.'];
            }
            return ['ok' => true, 'message' => 'Solicitud aprobada correctamente.'];
        }

        if ($this->requestModel->updateRequestStatus($id, 'Aprobado')) {
            return ['ok' => true, 'message' => 'Solicitud aprobada correctamente.'];
        }
        return ['ok' => false, 'message' => 'Error al aprobar la solicitud.'];
    }

    private function rejectRequestWithVacationReversal($id, $request = null) {
        $request = $request ?: $this->requestModel->getRequestById($id);
        if (!$request) return ['ok'=>false, 'message'=>'Solicitud no encontrada.'];
        $vacationTypeId = $this->requestModel->getVacationRequestTypeId();
        $isApprovedVacation = $request->status === 'Aprobado'
            && (int)$request->request_type_id === (int)$vacationTypeId
            && function_exists('vacation_module_ready') && vacation_module_ready();
        if (!$isApprovedVacation) {
            return $this->requestModel->updateRequestStatus($id, 'Rechazado')
                ? ['ok'=>true, 'message'=>'Solicitud rechazada.']
                : ['ok'=>false, 'message'=>'No se pudo rechazar la solicitud.'];
        }
        $db = new Database();
        $db->beginTransaction();
        try {
            $requestTx = new Request($db);
            $locked = $requestTx->getRequestByIdForUpdate($id);
            if (!$locked || $locked->status !== 'Aprobado') throw new RuntimeException('La solicitud cambió de estado.');
            $reversal = (new VacationLedgerService($db))->reverseRequest($id, (int)$_SESSION['user_id']);
            if (!$reversal['ok'] || !$requestTx->updateRequestStatus($id, 'Rechazado')) {
                throw new RuntimeException($reversal['message'] ?? 'No se pudo cancelar.');
            }
            $db->commit();
            return ['ok'=>true, 'message'=>'Solicitud cancelada y días restaurados a sus períodos originales.'];
        } catch (Throwable $e) {
            $db->rollBack();
            return ['ok'=>false, 'message'=>$e->getMessage()];
        }
    }

    public function approveRequest($id){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/requests');
        }
        csrf_verify();
        $companyId = requireAdminCompany('admin/requests');
        $id = (int)$id;
        $request = $this->requestModel->getRequestByIdForCompany($id, $companyId);
        if (!$request) {
            $_SESSION['flash_error'] = 'Solicitud no encontrada.';
            redirect('admin/requests');
        }
        $result = $this->approveRequestWithBalance($id, $request);
        if ($result['ok']) {
            $_SESSION['flash_success'] = $result['message'];
        } else {
            $_SESSION['flash_error'] = $result['message'];
        }
        redirect('admin/requests');
    }

    public function rejectRequest($id){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/requests');
        }
        csrf_verify();
        $companyId = requireAdminCompany('admin/requests');
        $id = (int)$id;
        $request = $this->requestModel->getRequestByIdForCompany($id, $companyId);
        if (!$request) {
            $_SESSION['flash_error'] = 'Solicitud no encontrada.';
            redirect('admin/requests');
        }
        $result = $this->rejectRequestWithVacationReversal($id, $request);
        $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['message'];
        redirect('admin/requests');
    }

    public function editRequest($id){
        $companyId = requireAdminCompany('admin/requests');
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if(!$id){
            redirect('admin/requests');
        }

        $request = $this->requestModel->getRequestByIdForCompany($id, $companyId);
        if(!$request){
            redirect('admin/requests');
        }

        $user = adminResolveUser((int)$request->user_id, 'admin/requests');

        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            csrf_verify();
            $data = [
                'id' => $id,
                'request_type_id' => isset($_POST['request_type_id']) ? (int)$_POST['request_type_id'] : 0,
                'start_date' => isset($_POST['start_date']) ? trim($_POST['start_date']) : '',
                'end_date' => !empty($_POST['end_date']) ? trim($_POST['end_date']) : trim($_POST['start_date']),
                'reason' => isset($_POST['reason']) ? trim(strip_tags($_POST['reason'])) : '',
                'status' => isset($_POST['status']) ? trim($_POST['status']) : 'Pendiente',
            ];

            if ($data['status'] === 'Aprobado' && $request->status !== 'Aprobado') {
                // Guardar primero los campos editados (sin aprobar aún) para que la
                // aprobación use las fechas/tipo nuevos y no los viejos de la BD.
                $dataForUpdate = $data;
                $dataForUpdate['status'] = $request->status;
                if (!$this->requestModel->updateRequest($dataForUpdate)) {
                    $_SESSION['flash_error'] = 'No se pudo actualizar la solicitud.';
                    redirect('admin/editRequest/' . $id);
                }
                $updatedRequest = $this->requestModel->getRequestByIdForCompany($id, $companyId);
                $result = $this->approveRequestWithBalance($id, $updatedRequest);
                $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['message'];
                redirect('admin/requests');
            }

            $vacationTypeId = $this->requestModel->getVacationRequestTypeId();
            $isApprovedVacationEdit = $request->status === 'Aprobado'
                && (int)$request->request_type_id === (int)$vacationTypeId
                && function_exists('vacation_module_ready') && vacation_module_ready();
            if ($isApprovedVacationEdit && $data['status'] !== 'Aprobado') {
                $result = $this->rejectRequestWithVacationReversal($id, $request);
                if (!$result['ok']) {
                    $_SESSION['flash_error'] = $result['message'];
                    redirect('admin/editRequest/' . $id);
                }
                $data['status'] = 'Rechazado';
                if ($this->requestModel->updateRequest($data)) {
                    $_SESSION['flash_success'] = $result['message'];
                    redirect('admin/requests');
                }
                $_SESSION['flash_error'] = 'Los días fueron restaurados, pero no se pudieron guardar los demás cambios.';
                redirect('admin/editRequest/' . $id);
            }

            if($this->requestModel->updateRequest($data)){
                $_SESSION['flash_success'] = 'Solicitud actualizada correctamente.';
                redirect('admin/requests');
            }

            $_SESSION['flash_error'] = 'No se pudo actualizar la solicitud.';
            redirect('admin/editRequest/' . $id);
        }

        $request->full_name = $user->full_name;
        $this->view('admin/edit_request', ['request' => $request, 'requestTypes' => $this->requestModel->getRequestTypes()]);
    }

    /** @deprecated Usar marcacionesTodas. Redirige por compatibilidad con enlaces antiguos. */
    public function clockingsReport() {
        $qs = [];
        if (!empty($_GET['start_date'])) {
            $qs['start_date'] = $_GET['start_date'];
        }
        if (!empty($_GET['end_date'])) {
            $qs['end_date'] = $_GET['end_date'];
        }
        redirect('admin/marcacionesTodas' . ($qs ? '?' . http_build_query($qs) : ''));
    }

    public function reports(){
        $companyId = requireAdminCompany('admin/dashboard');
        $overtimeInReports = function_exists('company_uses_classic_overtime')
            && company_uses_classic_overtime($companyId);
        $filters = [
            'start_date' => isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'),
            'end_date'   => isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'),
            'user_id'    => isset($_GET['user_id']) ? $_GET['user_id'] : 'all'
        ];

        $users = $this->userModel->getUsersByCompany($_SESSION['user_company_id']);
        $report_data = array();

        if(isset($_GET['start_date'])){
            $allEntries = $this->workScheduleModel->getScheduleEntriesForPeriod($_SESSION['user_company_id'], $filters['start_date'], $filters['end_date']);
            
            $filteredEntries = ($filters['user_id'] != 'all') 
                ? array_filter($allEntries, function($e) use ($filters) { return $e->user_id == $filters['user_id']; })
                : $allEntries;

            foreach($filteredEntries as $entry){
                $userId = $entry->user_id;
                if(!isset($report_data[$userId])){
                    $user = $this->userModel->getUserById($userId);
                    $report_data[$userId] = [
                        'full_name' => $user->full_name,
                        'limit' => $user->weekly_hour_limit,
                        'regular_hours' => 0,
                        'overtime_hours' => 0,
                        'total_hours' => 0
                    ];
                }

                $hours = 0;
                if (!empty($entry->start_time) && !empty($entry->end_time)) {
                    $start = strtotime($entry->start_time); $end = strtotime($entry->end_time);
                    if ($end < $start) { $end += 24 * 3600; }
                    $hours = ($end - $start) / 3600;
                }

                if ($entry->type == 'overtime') {
                    if ($overtimeInReports) {
                        $report_data[$userId]['overtime_hours'] += $hours;
                    } else {
                        $report_data[$userId]['regular_hours'] += $hours;
                    }
                } else {
                    $report_data[$userId]['regular_hours'] += $hours;
                }
                $report_data[$userId]['total_hours'] += $hours;
            }
        }
        
        $data = [
            'users' => $users,
            'filters' => $filters,
            'report_data' => $report_data,
            'overtime_in_reports' => $overtimeInReports,
        ];

        $this->view('admin/reports', $data);
    }

    public function exportReportCsv(){
        $companyId = requireAdminCompany('admin/dashboard');
        $overtimeInReports = function_exists('company_uses_classic_overtime')
            && company_uses_classic_overtime($companyId);
        $filters = [
            'start_date' => isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'),
            'end_date'   => isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'),
            'user_id'    => isset($_GET['user_id']) ? $_GET['user_id'] : 'all'
        ];

        $allEntries = $this->workScheduleModel->getScheduleEntriesForPeriod($_SESSION['user_company_id'], $filters['start_date'], $filters['end_date']);
        $filteredEntries = ($filters['user_id'] != 'all')
            ? array_filter($allEntries, function($e) use ($filters) { return $e->user_id == $filters['user_id']; })
            : $allEntries;

        $reportData = [];
        foreach($filteredEntries as $entry){
            $userId = $entry->user_id;
            if(!isset($reportData[$userId])){
                $user = $this->userModel->getUserById($userId);
                $reportData[$userId] = [
                    'full_name' => $user->full_name,
                    'limit' => $user->weekly_hour_limit,
                    'regular_hours' => 0,
                    'overtime_hours' => 0,
                    'total_hours' => 0,
                ];
            }

            $hours = 0;
            if (!empty($entry->start_time) && !empty($entry->end_time)) {
                $start = strtotime($entry->start_time);
                $end = strtotime($entry->end_time);
                if ($end < $start) { $end += 24 * 3600; }
                $hours = ($end - $start) / 3600;
            }

            if ($entry->type == 'overtime') {
                if ($overtimeInReports) {
                    $reportData[$userId]['overtime_hours'] += $hours;
                } else {
                    $reportData[$userId]['regular_hours'] += $hours;
                }
            } else {
                $reportData[$userId]['regular_hours'] += $hours;
            }
            $reportData[$userId]['total_hours'] += $hours;
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="reporte_horas_' . date('Ymd_His') . '.csv"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        $csvHeader = ['Empleado', 'Limite semanal', 'Horas regulares'];
        if ($overtimeInReports) {
            $csvHeader[] = 'Horas extra';
        }
        $csvHeader[] = 'Total horas';
        fputcsv($output, $csvHeader, ';');
        foreach ($reportData as $row) {
            $csvRow = [
                $row['full_name'],
                number_format($row['limit'], 2, '.', ''),
                number_format($row['regular_hours'], 2, '.', ''),
            ];
            if ($overtimeInReports) {
                $csvRow[] = number_format($row['overtime_hours'], 2, '.', '');
            }
            $csvRow[] = number_format($row['total_hours'], 2, '.', '');
            fputcsv($output, $csvRow, ';');
        }
        fclose($output);
        exit();
    }

    public function weeklyPlanner(){
        if($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['is_ajax'])){
            // Fix 5: verificar token CSRF
            csrf_verify();
            $schedules = isset($_POST['schedules']) ? $_POST['schedules'] : array();
            $plannerResult = $this->savePlannerSchedulesWithVacationLedger($schedules, (int)($_POST['branch_id'] ?? 0));
            if (!$plannerResult['ok']) {
                $_SESSION['flash_error'] = $plannerResult['message'];
            } else {
                $_SESSION['flash_success'] = 'Planificador guardado con éxito.';
            }
            // Fix 7: validar formato antes de redirigir
            $redir_week = isset($_POST['current_week']) ? $_POST['current_week'] : date('Y-\WW');
            if(!preg_match('/^\d{4}-W(0[1-9]|[1-4]\d|5[0-3])$/', $redir_week)){
                $redir_week = date('Y-\WW');
            }
            $redirectBranch = max(0, (int)($_POST['branch_id'] ?? 0));
            redirect('admin/weeklyPlanner?week=' . $redir_week . ($redirectBranch > 0 ? '&branch_id=' . $redirectBranch : ''));
        }

        if (!isset($_SESSION['user_company_id'])) {
            $adminUser = $this->userModel->getUserById($_SESSION['user_id']);
            $_SESSION['user_company_id'] = $adminUser->company_id;
        }

        // Fix 7: validar formato del parámetro ?week=
        $current_week_string = isset($_GET['week']) ? $_GET['week'] : date('Y-\WW');
        if(!preg_match('/^\d{4}-W(0[1-9]|[1-4]\d|5[0-3])$/', $current_week_string)){
            $current_week_string = date('Y-\WW');
        }
        $year = date('Y', strtotime($current_week_string));
        $week = date('W', strtotime($current_week_string));
        
        $dias_semana = array('Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mié', 'Thu' => 'Jue', 'Fri' => 'Vie', 'Sat' => 'Sáb', 'Sun' => 'Dom');
        $nombres_mes = array(1=>'Ene', 2=>'Feb', 3=>'Mar', 4=>'Abr', 5=>'May', 6=>'Jun', 7=>'Jul', 8=>'Ago', 9=>'Sep', 10=>'Oct', 11=>'Nov', 12=>'Dic');
        
        $week_dates = array();
        for ($i = 0; $i < 7; $i++) {
            $date_string = date('Y-m-d', strtotime($year . "W" . $week . ($i + 1)));
            $week_dates[] = array('full_date' => $date_string, 'display' => $dias_semana[date('D', strtotime($date_string))] . ' ' . date('d/m', strtotime($date_string)));
        }
        
        $weekStartDate = $week_dates[0]['full_date'];
        $weekEndDate = end($week_dates)['full_date'];
        
        $companyId = (int)$_SESSION['user_company_id'];
        $branches = $this->companyModel->getBranches($companyId, false);
        $branchScopeReady = !empty($branches)
            && $this->userModel->isBranchAssignmentReady()
            && $this->workScheduleModel->isBranchScheduleReady();
        $branchId = (int)($_GET['branch_id'] ?? 0);
        $selectedBranch = null;
        if ($branchScopeReady) {
            $selectedBranch = $this->companyModel->getBranchByIdForCompany($branchId, $companyId, true);
            if (!$selectedBranch) {
                $selectedBranch = $branches[0];
                $branchId = (int)$selectedBranch->id;
            }
        } else {
            $branchId = 0;
        }

        $users = $this->userModel->getUsersByCompany($companyId, $branchId);
        $shifts = $this->shiftModel->getShiftsWithRangesByCompany($_SESSION['user_company_id']);
        $holidaysData = $this->holidayModel->getHolidaysForPeriod($companyId, $weekStartDate, $weekEndDate, $branchId);
        $requestsData = $this->requestModel->getApprovedRequestsForPeriod($weekStartDate, $weekEndDate, $companyId, $branchId);
        
        $month1_start = date('Y-m-01', strtotime($weekStartDate));
        $month1_end = date('Y-m-t', strtotime($weekStartDate));
        $allEntries = $this->workScheduleModel->getScheduleEntriesForPeriod($companyId, $month1_start, $month1_end, $branchId);

        $month2_start = date('Y-m-01', strtotime($weekEndDate));
        if ($month1_start != $month2_start) {
            $month2_end = date('Y-m-t', strtotime($weekEndDate));
            $month2_entries = $this->workScheduleModel->getScheduleEntriesForPeriod($companyId, $month2_start, $month2_end, $branchId);
            $allEntries = array_merge($allEntries, $month2_entries);
        }

        // El tablero muestra una sede, pero el límite semanal es del empleado:
        // se calcula también el total consolidado de todas sus sucursales.
        $allCompanyEntries = $allEntries;
        if ($branchId > 0) {
            $allCompanyEntries = $this->workScheduleModel->getScheduleEntriesForPeriod($companyId, $month1_start, $month1_end);
            if ($month1_start != $month2_start) {
                $allCompanyEntries = array_merge($allCompanyEntries,
                    $this->workScheduleModel->getScheduleEntriesForPeriod($companyId, $month2_start, $month2_end));
            }
        }

        $holidays = array();
        foreach($holidaysData as $h) { $holidays[$h->holiday_date] = true; }

        $requests = array();
        foreach($requestsData as $req) {
            $current_date = new DateTime($req->start_date);
            $end_date = new DateTime($req->end_date);
            while($current_date <= $end_date) {
                $requests[$req->user_id][$current_date->format('Y-m-d')] = $req;
                $current_date->modify('+1 day');
            }
        }

        $schedules = array();
        $weekly_totals = array();
        $weekly_branch_totals = array();
        $monthly_totals = array();
        $shiftsById = array();
        foreach($shifts as $s){ $shiftsById[$s->id] = $s; }

        $month1_num = (int)date('m', strtotime($weekStartDate));
        $month2_num = (int)date('m', strtotime($weekEndDate));
        foreach($users as $user) { 
            $weekly_totals[$user->id] = 0;
            $weekly_branch_totals[$user->id] = 0;
            $monthly_totals[$user->id][$month1_num] = 0;
            if ($month1_num != $month2_num) {
                $monthly_totals[$user->id][$month2_num] = 0;
            }
        }

        foreach ($allEntries as $entry) {
            if (!isset($schedules[$entry->user_id][$entry->schedule_date])) {
                $schedules[$entry->user_id][$entry->schedule_date] = array();
            }
            $schedules[$entry->user_id][$entry->schedule_date][] = $entry;

            $hours = 0;
            if ($entry->type == 'shift' && !empty($entry->shift_id) && isset($shiftsById[$entry->shift_id])) {
                $hours = $shiftsById[$entry->shift_id]->total_hours;
            } elseif (($entry->type == 'custom' || $entry->type == 'overtime') && !empty($entry->start_time) && !empty($entry->end_time)) {
                $start = strtotime($entry->start_time); $end = strtotime($entry->end_time);
                if ($end < $start) { $end += 24 * 3600; }
                $hours = ($end - $start) / 3600;
            }

            $entry_month = (int)date('m', strtotime($entry->schedule_date));
            if (isset($monthly_totals[$entry->user_id][$entry_month])) {
                $monthly_totals[$entry->user_id][$entry_month] += $hours;
            }
            if ($entry->schedule_date >= $weekStartDate && $entry->schedule_date <= $weekEndDate) {
                if (isset($weekly_branch_totals[$entry->user_id])) {
                    $weekly_branch_totals[$entry->user_id] += $hours;
                }
            }
        }

        foreach ($allCompanyEntries as $entry) {
            if ($entry->schedule_date < $weekStartDate || $entry->schedule_date > $weekEndDate
                || !isset($weekly_totals[$entry->user_id])) {
                continue;
            }
            $hours = 0;
            if ($entry->type == 'shift' && !empty($entry->shift_id) && isset($shiftsById[$entry->shift_id])) {
                $hours = $shiftsById[$entry->shift_id]->total_hours;
            } elseif (($entry->type == 'custom' || $entry->type == 'overtime') && !empty($entry->start_time) && !empty($entry->end_time)) {
                $start = strtotime($entry->start_time); $end = strtotime($entry->end_time);
                if ($end < $start) { $end += 24 * 3600; }
                $hours = ($end - $start) / 3600;
            }
            $weekly_totals[$entry->user_id] += $hours;
        }

        $overtimePlanner = function_exists('overtime_staff_can_view') && overtime_staff_can_view($companyId);

        $data = array(
            'users' => $users, 'shifts' => $shifts, 'week_dates' => $week_dates,
            'schedules' => $schedules, 'weekly_totals' => $weekly_totals, 'weekly_branch_totals' => $weekly_branch_totals, 'monthly_totals' => $monthly_totals,
            'holidays' => $holidays, 'requests' => $requests,
            'nombres_mes' => $nombres_mes, 'month1_num' => $month1_num, 'month2_num' => $month2_num,
            'current_week_string' => $current_week_string,
            'prev_week_string' => date('Y-\WW', strtotime($current_week_string . ' -1 week')),
            'next_week_string' => date('Y-\WW', strtotime($current_week_string . ' +1 week')),
            'overtime_planner_enabled' => $overtimePlanner,
            'branches' => $branches,
            'branch_scope_ready' => $branchScopeReady,
            'selected_branch_id' => $branchId,
            'selected_branch' => $selectedBranch,
        );
        $templates = $this->templateModel->getTemplatesByCompany($_SESSION['user_company_id']);
        $data['templates'] = $templates;
        $this->view('admin/weekly_planner', $data);
    }

    // Fix 1: método applyTemplate que faltaba
    public function applyTemplate(){
        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            redirect('admin/weeklyPlanner');
            return;
        }
        // Fix 5: verificar token CSRF
        csrf_verify();

        $templateId  = filter_input(INPUT_POST, 'template_id',  FILTER_VALIDATE_INT);
        $targetWeek  = isset($_POST['target_week']) ? trim($_POST['target_week']) : '';
        $branchId = max(0, (int)($_POST['branch_id'] ?? 0));

        // Fix 7: validar formato de la semana
        if(!$templateId || !preg_match('/^\d{4}-W(0[1-9]|[1-4]\d|5[0-3])$/', $targetWeek)){
            $_SESSION['flash_error'] = 'Datos de plantilla inválidos.';
            redirect('admin/weeklyPlanner');
            return;
        }

        // Lunes de esa semana (setISODate; strtotime no soporta "YYYY-WnnD")
        $weekDate = new DateTime();
        $weekDate->setISODate((int)substr($targetWeek, 0, 4), (int)substr($targetWeek, 6, 2));
        $weekStartDate = $weekDate->format('Y-m-d');

        if ($this->userModel->isBranchAssignmentReady()
            && !empty($this->companyModel->getBranches((int)$_SESSION['user_company_id'], false))
            && !$this->companyModel->getBranchByIdForCompany($branchId, (int)$_SESSION['user_company_id'], true)) {
            $_SESSION['flash_error'] = 'Seleccioná una sucursal válida para aplicar la plantilla.';
            redirect('admin/weeklyPlanner?week=' . $targetWeek);
        }

        if($this->templateModel->applyTemplateToWeek($templateId, $weekStartDate, $_SESSION['user_company_id'], $branchId)){
            $_SESSION['flash_success'] = 'Plantilla aplicada con éxito.';
        } else {
            $_SESSION['flash_error'] = 'Error al aplicar la plantilla.';
        }
        redirect('admin/weeklyPlanner?week=' . $targetWeek . ($branchId > 0 ? '&branch_id=' . $branchId : ''));
    }

    /**
     * Repite el horario de la semana visible de un empleado en un rango de fechas.
     */
    public function applySchedulePattern() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/weeklyPlanner');
        }
        csrf_verify();
        requireAdminCompany('admin/weeklyPlanner');

        $userId = (int)($_POST['user_id'] ?? 0);
        $targetWeek = trim($_POST['ref_week'] ?? $_POST['current_week'] ?? '');
        $from = trim($_POST['from_date'] ?? '');
        $to = trim($_POST['to_date'] ?? '');
        $overwrite = !empty($_POST['overwrite']);
        $returnWeek = trim($_POST['return_week'] ?? $targetWeek);

        if ($userId <= 0 || !preg_match('/^\d{4}-W(0[1-9]|[1-4]\d|5[0-3])$/', $targetWeek)) {
            $_SESSION['flash_error'] = 'Datos inválidos para repetir horario.';
            redirect('admin/weeklyPlanner?week=' . urlencode($returnWeek ?: date('Y-\WW')));
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $_SESSION['flash_error'] = 'Indicá fecha desde y hasta.';
            redirect('admin/weeklyPlanner?week=' . urlencode($returnWeek));
        }

        $targetUser = requireSupervisorUserAccess($userId, 'admin/weeklyPlanner');

        $branchId = max(0, (int)($_POST['branch_id'] ?? 0));
        $refWeekStart = date('Y-m-d', strtotime($targetWeek . '1'));
        $payload = $this->workScheduleModel->buildWeekPatternApplyPayload($userId, $refWeekStart, $from, $to, $overwrite, $branchId);
        if (!$payload['ok']) {
            $_SESSION['flash_error'] = $payload['message'];
            redirect('admin/weeklyPlanner?week=' . urlencode($returnWeek));
        }

        if ($this->userModel->isBranchAssignmentReady()
            && !$this->userModel->isUserAssignedToBranch((int)$targetUser->id, $branchId)) {
            $_SESSION['flash_error'] = 'El empleado no pertenece a la sucursal seleccionada.';
            redirect('admin/weeklyPlanner?week=' . urlencode($returnWeek) . ($branchId > 0 ? '&branch_id=' . $branchId : ''));
        }
        $result = $this->savePlannerSchedulesWithVacationLedger($payload['schedules'], $branchId);
        if ($result['ok']) {
            $_SESSION['flash_success'] = 'Horario repetido en ' . (int)$payload['days_updated'] . ' día(s) para '
                . $targetUser->full_name . '.';
        } else {
            $_SESSION['flash_error'] = $result['message'];
        }
        redirect('admin/weeklyPlanner?week=' . urlencode($returnWeek) . ($branchId > 0 ? '&branch_id=' . $branchId : ''));
    }

    /** Aplica una misma jornada base todos los lunes a viernes. */
    public function applyFixedWeekdaySchedule() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/weeklyPlanner');
        csrf_verify();
        requireAdminCompany('admin/weeklyPlanner');
        $userId = (int)($_POST['user_id'] ?? 0);
        $sourceDate = trim($_POST['source_date'] ?? '');
        $from = trim($_POST['from_date'] ?? '');
        $to = trim($_POST['to_date'] ?? '');
        $branchId = max(0, (int)($_POST['branch_id'] ?? 0));
        $returnWeek = trim($_POST['return_week'] ?? date('Y-\WW'));
        $targetUser = requireSupervisorUserAccess($userId, 'admin/weeklyPlanner');
        if ($this->userModel->isBranchAssignmentReady()
            && !$this->userModel->isUserAssignedToBranch((int)$targetUser->id, $branchId)) {
            $_SESSION['flash_error'] = 'El empleado no pertenece a la sucursal seleccionada.';
            redirect('admin/weeklyPlanner?week=' . urlencode($returnWeek) . '&branch_id=' . $branchId);
        }
        $payload = $this->workScheduleModel->buildFixedWeekdayApplyPayload(
            $userId, $sourceDate, $from, $to, !empty($_POST['overwrite']), $branchId
        );
        if (!$payload['ok']) {
            $_SESSION['flash_error'] = $payload['message'];
        } else {
            $result = $this->savePlannerSchedulesWithVacationLedger($payload['schedules'], $branchId);
            $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['ok']
                ? 'Jornada fija aplicada de lunes a viernes en ' . (int)$payload['days_updated'] . ' día(s).'
                : $result['message'];
        }
        redirect('admin/weeklyPlanner?week=' . urlencode($returnWeek) . ($branchId > 0 ? '&branch_id=' . $branchId : ''));
    }

    public function shiftManager(){
        $shifts = $this->shiftModel->getShiftsWithRangesByCompany($_SESSION['user_company_id']);
        $data = array('shifts' => $shifts);
        if(isset($_SESSION['success'])){ $data['success'] = $_SESSION['success']; unset($_SESSION['success']); }
        if(isset($_SESSION['error'])){   $data['error']   = $_SESSION['error'];   unset($_SESSION['error']);   }
        $this->view('admin/shift_manager', $data);
    }

    public function updateShift($id){
        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            redirect('admin/shiftManager');
        }
        csrf_verify();

        $id = filter_var($id, FILTER_VALIDATE_INT);
        if(!$id){
            $_SESSION['error'] = 'Turno inv&aacute;lido.';
            redirect('admin/shiftManager');
        }

        $shiftName = isset($_POST['shift_name']) ? trim(strip_tags($_POST['shift_name'])) : '';
        $notes     = isset($_POST['notes'])      ? trim(strip_tags($_POST['notes']))      : '';
        $color     = isset($_POST['color'])      ? trim($_POST['color'])                  : '#3788d8';
        $ranges    = isset($_POST['ranges'])     ? $_POST['ranges']                       : [];

        if(empty($shiftName)){
            $_SESSION['error'] = 'El nombre del turno es obligatorio.';
            redirect('admin/shiftManager');
        }

        if(!preg_match('/^#[0-9a-fA-F]{6}$/', $color)){
            $color = '#3788d8';
        }

        $cleanRanges = [];
        foreach($ranges as $range){
            $inicio = isset($range['inicio']) ? trim($range['inicio']) : '';
            $fin    = isset($range['fin'])    ? trim($range['fin'])    : '';
            if(preg_match('/^\d{2}:\d{2}$/', $inicio) && preg_match('/^\d{2}:\d{2}$/', $fin)){
                $cleanRanges[] = ['inicio' => $inicio, 'fin' => $fin];
            }
        }

        if(empty($cleanRanges)){
            $_SESSION['error'] = 'Debe ingresar al menos un rango horario v&aacute;lido.';
            redirect('admin/shiftManager');
        }

        $data = [
            'shift_name' => $shiftName,
            'notes'      => $notes,
            'color'      => $color,
            'ranges'     => $cleanRanges,
        ];

        if($this->shiftModel->updateShiftWithRanges($id, $_SESSION['user_company_id'], $data)){
            $_SESSION['success'] = 'Turno actualizado correctamente.';
        } else {
            $_SESSION['error'] = 'Error al actualizar el turno.';
        }

        redirect('admin/shiftManager');
    }

    public function createSplitShift(){
        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            redirect('admin/shiftManager');
        }
        csrf_verify();

        $shiftName = isset($_POST['shift_name']) ? trim(strip_tags($_POST['shift_name'])) : '';
        $notes     = isset($_POST['notes'])      ? trim(strip_tags($_POST['notes']))      : '';
        $color     = isset($_POST['color'])      ? trim($_POST['color'])                  : '#3788d8';
        $ranges    = isset($_POST['ranges'])     ? $_POST['ranges']                       : [];

        // Validar nombre
        if(empty($shiftName)){
            $_SESSION['error'] = 'El nombre del turno es obligatorio.';
            redirect('admin/shiftManager');
        }

        // Validar color (debe ser un hex válido)
        if(!preg_match('/^#[0-9a-fA-F]{6}$/', $color)){
            $color = '#3788d8';
        }

        // Validar rangos
        $cleanRanges = [];
        foreach($ranges as $range){
            $inicio = isset($range['inicio']) ? trim($range['inicio']) : '';
            $fin    = isset($range['fin'])    ? trim($range['fin'])    : '';
            if(preg_match('/^\d{2}:\d{2}$/', $inicio) && preg_match('/^\d{2}:\d{2}$/', $fin)){
                $cleanRanges[] = ['inicio' => $inicio, 'fin' => $fin];
            }
        }

        if(empty($cleanRanges)){
            $_SESSION['error'] = 'Debe ingresar al menos un rango horario válido.';
            redirect('admin/shiftManager');
        }

        $data = [
            'company_id' => $_SESSION['user_company_id'],
            'shift_name' => $shiftName,
            'notes'      => $notes,
            'color'      => $color,
            'ranges'     => $cleanRanges,
        ];

        if($this->shiftModel->createShiftWithRanges($data)){
            $_SESSION['success'] = 'Turno creado correctamente.';
        } else {
            $_SESSION['error'] = 'Error al guardar el turno. Inténtalo de nuevo.';
        }

        redirect('admin/shiftManager');
    }

    public function deleteShift($id){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/shiftManager');
        }
        csrf_verify();
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if(!$id){
            redirect('admin/shiftManager');
        }

        if($this->shiftModel->deleteShift($id, $_SESSION['user_company_id'])){
            $_SESSION['success'] = 'Turno eliminado.';
        } else {
            $_SESSION['error'] = 'Turno no encontrado o no tienes permiso para eliminarlo.';
        }

        redirect('admin/shiftManager');
    }

    public function holidays(){
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            csrf_verify();
            if (($_POST['holiday_action'] ?? '') === 'create_scoped_rule') {
                $rule = [
                    'name' => trim(strip_tags($_POST['name'] ?? '')),
                    'month_day' => !empty($_POST['rule_date']) ? substr(trim($_POST['rule_date']), 5, 5) : trim($_POST['month_day'] ?? ''),
                    'scope_type' => trim($_POST['scope_type'] ?? ''),
                    'province' => trim(strip_tags($_POST['province'] ?? '')),
                    'locality' => trim(strip_tags($_POST['locality'] ?? '')),
                ];
                $ready = $this->holidayModel->isScopedRulesReady();
                $created = $ready && $this->holidayModel->createScopedRule($rule);
                $_SESSION[$created ? 'flash_success' : 'flash_error'] = $ready
                    ? ($created ? 'Regla de feriado guardada.' : 'No se pudo guardar la regla. Revisá alcance, fecha y ubicación.')
                    : 'Falta ejecutar la migración de reglas geográficas de feriados.';
                redirect('admin/holidays');
            }
            $data = [
                'name' => isset($_POST['name']) ? trim(strip_tags($_POST['name'])) : '',
                'holiday_date' => isset($_POST['holiday_date']) ? trim($_POST['holiday_date']) : '',
                'company_id' => $_SESSION['user_company_id'],
            ];
            if(!empty($data['name']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['holiday_date']) && $this->holidayModel->createHoliday($data)){
                $_SESSION['flash_success'] = 'Feriado guardado correctamente.';
            } else {
                $_SESSION['flash_error'] = 'No se pudo guardar el feriado.';
            }
            redirect('admin/holidays');
        }

        $holidays = $this->holidayModel->getHolidaysByCompany($_SESSION['user_company_id']);
        $this->view('admin/holidays', [
            'holidays' => $holidays,
            'scoped_rules_ready' => $this->holidayModel->isScopedRulesReady(),
            'scoped_rules' => $this->holidayModel->getScopedRules(),
        ]);
    }

    public function deleteHoliday($id){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/holidays');
        }
        csrf_verify();
        $companyId = requireAdminCompany('admin/holidays');
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if($id && $this->holidayModel->deleteHolidayForCompany($id, $companyId)){
            $_SESSION['flash_success'] = 'Feriado eliminado.';
        } else {
            $_SESSION['flash_error'] = 'No se pudo eliminar el feriado.';
        }
        redirect('admin/holidays');
    }

    public function deleteScopedHolidayRule($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/holidays');
        }
        csrf_verify();
        requireAdminOnly('admin/holidays');
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id && $this->holidayModel->deleteScopedRule($id)) {
            $_SESSION['flash_success'] = 'Regla de feriado eliminada.';
        } else {
            $_SESSION['flash_error'] = 'No se pudo eliminar la regla de feriado.';
        }
        redirect('admin/holidays');
    }

    public function templates(){
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            csrf_verify();
            $templateName = isset($_POST['template_name']) ? trim(strip_tags($_POST['template_name'])) : '';
            $sourceWeek = isset($_POST['source_week']) ? trim($_POST['source_week']) : '';

            if(empty($templateName) || !preg_match('/^\d{4}-W\d{2}$/', $sourceWeek)){
                $_SESSION['flash_error'] = 'Debes indicar un nombre y una semana válida.';
                redirect('admin/templates');
            }

            $year = (int)substr($sourceWeek, 0, 4);
            $week = (int)substr($sourceWeek, 6, 2);
            $date = new DateTime();
            $date->setISODate($year, $week);
            $startDate = $date->format('Y-m-d');
            $endDate = date('Y-m-d', strtotime($startDate . ' +6 days'));
            $entries = $this->workScheduleModel->getScheduleEntriesForPeriod($_SESSION['user_company_id'], $startDate, $endDate);

            $data = [
                'template_name' => $templateName,
                'company_id' => $_SESSION['user_company_id'],
                'entries' => $entries,
            ];

            if($this->templateModel->createTemplateFromWeek($data)){
                $_SESSION['flash_success'] = 'Plantilla creada correctamente.';
            } else {
                $_SESSION['flash_error'] = 'No se pudo crear la plantilla.';
            }
            redirect('admin/templates');
        }

        $templates = $this->templateModel->getTemplatesByCompany($_SESSION['user_company_id']);
        $this->view('admin/templates', ['templates' => $templates]);
    }

    public function deleteTemplate($id){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/templates');
        }
        csrf_verify();
        $companyId = requireAdminCompany('admin/templates');
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if($id && $this->templateModel->deleteTemplateForCompany($id, $companyId)){
            $_SESSION['flash_success'] = 'Plantilla eliminada.';
        } else {
            $_SESSION['flash_error'] = 'No se pudo eliminar la plantilla.';
        }
        redirect('admin/templates');
    }

    public function suggestions(){
        $suggestions = $this->suggestionModel->getAllSuggestionsByCompany($_SESSION['user_company_id']);
        $data = ['suggestions' => $suggestions];
        $this->view('admin/suggestions', $data);
    }

    public function addNote($id){
        redirect('admin/addEmployeeIncident/' . (int)$id);
    }

    public function addEmployeeIncident($id) {
        $id = (int)$id;
        if ($id <= 0) {
            redirect('admin/users');
        }
        adminResolveUser($id);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/employeeProfile/' . $id . '#tab-incidents');
        }
        csrf_verify();

        if (!$this->employeeIncidentModel->isSchemaReady()) {
            $_SESSION['flash_error'] = 'Falta la tabla employee_incidents. Ejecutá migration_employee_incidents.sql (ver MIGRATIONS.md).';
            redirect('admin/employeeProfile/' . $id . '#tab-incidents');
        }

        $type = postString('incident_type');
        $title = postString('title');
        $description = postString('description');
        $incidentDate = postString('incident_date');

        if (!employee_incident_is_valid_type($type)) {
            $_SESSION['flash_error'] = 'Seleccioná un tipo de incidencia válido.';
            redirect('admin/employeeProfile/' . $id . '#tab-incidents');
        }
        if ($description === '') {
            $_SESSION['flash_error'] = 'La descripción es obligatoria.';
            redirect('admin/employeeProfile/' . $id . '#tab-incidents');
        }
        if ($incidentDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $incidentDate)) {
            $_SESSION['flash_error'] = 'Indicá una fecha de la incidencia válida.';
            redirect('admin/employeeProfile/' . $id . '#tab-incidents');
        }

        $attachmentPath = null;
        $attachmentOriginal = null;
        try {
            $upload = employee_incident_store_upload($id);
            if ($upload) {
                $attachmentPath = $upload['path'];
                $attachmentOriginal = $upload['original'];
            }
        } catch (RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            redirect('admin/employeeProfile/' . $id . '#tab-incidents');
        }

        $ok = $this->employeeIncidentModel->add([
            'user_id' => $id,
            'admin_id' => (int)$_SESSION['user_id'],
            'incident_type' => $type,
            'title' => $title,
            'description' => $description,
            'incident_date' => $incidentDate,
            'attachment_path' => $attachmentPath,
            'attachment_original_name' => $attachmentOriginal,
        ]);

        $extras = [];
        if ($ok && $type === 'suspension') {
            if (!empty($_POST['deactivate_user'])) {
                if ($this->userModel->setInactive($id)) {
                    $extras[] = 'Usuario desactivado (no puede iniciar sesión).';
                }
            }
            if (!empty($_POST['notify_hr_email']) && class_exists('MailService')) {
                $mail = new MailService();
                if ($mail->isAvailable()) {
                    $target = $this->userModel->getUserById($id);
                    $admins = $this->userModel->getActiveAdminsByCompany((int)($target->company_id ?? adminCompanyId()));
                    $body = '<p>Se registró una <strong>suspensión</strong> para '
                        . htmlspecialchars($target->full_name ?? 'empleado')
                        . ' con fecha ' . htmlspecialchars($incidentDate) . '.</p>'
                        . '<p><a href="' . htmlspecialchars(URLROOT . '/admin/employeeProfile/' . $id . '#tab-incidents') . '">Ver ficha</a></p>';
                    foreach ($admins as $adm) {
                        $mail->sendToUser((int)$adm->id, 'Incidencia: suspensión registrada', $body);
                    }
                    $extras[] = 'Notificación enviada a RRHH.';
                }
            }
        }

        $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $ok
            ? ('Incidencia registrada correctamente.' . ($extras ? ' ' . implode(' ', $extras) : ''))
            : 'No se pudo guardar la incidencia.';
        redirect('admin/employeeProfile/' . $id . '#tab-incidents');
    }

    public function deleteEmployeeIncident($userId, $incidentId) {
        $userId = (int)$userId;
        $incidentId = (int)$incidentId;
        if ($userId <= 0 || $incidentId <= 0) {
            redirect('admin/users');
        }
        adminResolveUser($userId);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/employeeProfile/' . $userId . '#tab-incidents');
        }
        csrf_verify();

        if (!$this->employeeIncidentModel->isSchemaReady()) {
            $_SESSION['flash_error'] = 'Falta la tabla employee_incidents.';
            redirect('admin/employeeProfile/' . $userId . '#tab-incidents');
        }

        if ($this->employeeIncidentModel->deleteForUser($incidentId, $userId)) {
            $_SESSION['flash_success'] = 'Incidencia eliminada.';
        } else {
            $_SESSION['flash_error'] = 'No se encontró la incidencia.';
        }
        redirect('admin/employeeProfile/' . $userId . '#tab-incidents');
    }

    public function streamEmployeeIncident($userId, $incidentId) {
        $userId = (int)$userId;
        $incidentId = (int)$incidentId;
        if ($userId <= 0 || $incidentId <= 0) {
            http_response_code(404);
            exit;
        }
        adminResolveUser($userId);
        employee_incident_send_attachment($userId, $incidentId, true);
    }

    public function downloadEmployeeIncident($userId, $incidentId) {
        $userId = (int)$userId;
        $incidentId = (int)$incidentId;
        if ($userId <= 0 || $incidentId <= 0) {
            redirect('admin/users');
        }
        adminResolveUser($userId);

        if (!$this->employeeIncidentModel->isSchemaReady()) {
            $_SESSION['flash_error'] = 'Módulo de incidencias no disponible.';
            redirect('admin/employeeProfile/' . $userId);
        }

        $row = $this->employeeIncidentModel->getByIdForUser($incidentId, $userId);
        if (!$row || empty($row->attachment_path)) {
            $_SESSION['flash_error'] = 'Adjunto no encontrado.';
            redirect('admin/employeeProfile/' . $userId . '#tab-incidents');
        }

        if (!protected_upload_absolute_path(employee_incident_storage_relative($userId, $row->attachment_path))) {
            $_SESSION['flash_error'] = 'El archivo adjunto ya no existe en el servidor.';
            redirect('admin/employeeProfile/' . $userId . '#tab-incidents');
        }

        employee_incident_send_attachment($userId, $incidentId, false);
    }

    public function employeeProfile($id){
        $id = (int)$id;
        $user = adminResolveUser($id);

        // Get data
        $requests = $this->requestModel->getRequestsByUserId($id);
        $salaryAdvances = [];
        $salaryAdvancesReady = false;
        if (class_exists('SalaryAdvance')) {
            $salaryAdvanceModel = new SalaryAdvance();
            $salaryAdvancesReady = $salaryAdvanceModel->isSchemaReady();
            if ($salaryAdvancesReady) {
                $salaryAdvances = $salaryAdvanceModel->getByUserId($id);
            }
        }
        $overtimeEntries = $this->overtimeModel->getEntriesByUserId($id);
        
        // Marcaciones (pestaña jornada) — último mes
        $lastMonthStart = date('Y-m-01', strtotime('-1 month'));
        $today = date('Y-m-d');
        $clockings = $this->scheduleModel->getMarcacionesByUser($id, 200, [
            'start_date' => $lastMonthStart,
            'end_date'   => $today,
        ]);

        $currentMonth = date('Y-m');
        $schedule = $this->workScheduleModel->getUserScheduleForMonth($id, $user->company_id, $currentMonth);

        // Calendario: ~4 meses atrás y 2 adelante (navegación FullCalendar)
        $calRangeStart = date('Y-m-d', strtotime('-4 months'));
        $calRangeEnd   = date('Y-m-d', strtotime('+2 months'));
        $calendarEvents = buildEmployeeProfileCalendarEvents(
            $id,
            (int)$user->company_id,
            $calRangeStart,
            $calRangeEnd
        );
        if (!(new AttendanceJustification())->isSchemaReady()) {
            $_SESSION['flash_error'] = 'Falta la tabla attendance_justifications. Ejecutá migration_attendance_justifications.sql en phpMyAdmin (ver MIGRATIONS.md).';
        } elseif (!(new AttendanceDaySummary())->isSchemaReady()) {
            $_SESSION['flash_error'] = 'Falta la tabla attendance_day_summary. Ejecutá migration_attendance_summary.sql en phpMyAdmin (ver MIGRATIONS.md).';
        }

        $byDay = [];
        foreach ($clockings as $c) {
            $day = date('Y-m-d', strtotime($c->event_time));
            $byDay[$day][] = $c;
        }
        $daySummaries = [];
        foreach ($byDay as $day => $items) {
            $daySummaries[$day] = $this->scheduleModel->computeDaySummaryFromClockings($items);
        }

        $incidents = [];
        $incidentsReady = $this->employeeIncidentModel->isSchemaReady();
        if ($incidentsReady) {
            $incidents = $this->employeeIncidentModel->getByUserId($id);
        }

        $vacationSummary = null;
        $vacationReady = vacation_module_ready();
        if ($vacationReady) {
            $vacationSummary = (new VacationEntitlementService())->getSummaryForUser($id);
        }

        $roadmapMonth = preg_match('/^\d{4}-\d{2}$/', $_GET['roadmap_month'] ?? '') ? $_GET['roadmap_month'] : date('Y-m');
        $roadmapCalendar = null;
        $roadmapStats = null;
        try {
            $roadmapCalendar = $this->calendarMonthService->buildMonth((int)$user->company_id, $id, $roadmapMonth);
            if (!empty($roadmapCalendar['days_list'])) {
                $roadmapStats = calendarComputeMonthStats($roadmapCalendar['days_list']);
            }
        } catch (Exception $e) {
            // Tablas de asistencia pueden no existir
        }

        $ecofarmaCommissionsUrl = null;
        if ($this->userModel->isPlexOperatorReady() && !empty($user->plex_operator_name)) {
            $ecofarmaCommissionsUrl = URLROOT . '/ecofarma/index?operador=' . rawurlencode($user->plex_operator_name);
        }

        $overtimeTab = function_exists('overtime_staff_can_view')
            && overtime_staff_can_view((int)$user->company_id, $user);

        $employeeRecord = $this->employeeRecordModel->getFormData($id, (int)$user->company_id);

        $pendingOvertimeCount = 0;
        if ($overtimeTab) {
            foreach ($overtimeEntries as $oe) {
                if (($oe->status ?? '') === 'pending') {
                    $pendingOvertimeCount++;
                }
            }
        }

        $data = [
            'user' => $user,
            'requests' => $requests,
            'salary_advances' => $salaryAdvances,
            'salary_advances_ready' => $salaryAdvancesReady,
            'overtime' => $overtimeTab ? $overtimeEntries : [],
            'overtime_tab_enabled' => $overtimeTab,
            'pending_overtime_count' => $pendingOvertimeCount,
            'clockings' => $clockings,
            'day_summaries' => $daySummaries,
            'schedule' => $schedule,
            'current_month' => $currentMonth,
            'calendarEvents' => json_encode($calendarEvents),
            'incidents' => $incidents,
            'incidents_ready' => $incidentsReady,
            'incident_types' => employee_incident_types(),
            'vacation_summary' => $vacationSummary,
            'vacation_ready' => $vacationReady,
            'roadmap_month' => $roadmapMonth,
            'roadmap_calendar' => $roadmapCalendar,
            'roadmap_stats' => $roadmapStats,
            'ecofarma_commissions_url' => $ecofarmaCommissionsUrl,
            'plex_operator_ready' => $this->userModel->isPlexOperatorReady(),
            'employee_record' => $employeeRecord,
        ];

        $this->view('admin/employee_profile', $data);
    }

    public function hrAlerts() {
        $companyId = requireAdminCompany('admin/dashboard');
        $workDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
        $dashboard = (new HrAlertsService())->buildDashboard($companyId, $workDate);
        // Suite P&M: los legajos sin mapear vienen de los relojes de Paviotti
        // (marcaciones_cache no distingue empresa); en la vista Moderna se ocultan.
        if (function_exists('org_is_moderna') && org_is_moderna()) {
            $dashboard['unmapped_legajos'] = [];
            $dashboard['unmapped_count'] = 0;
        }
        $this->view('admin/hr_alerts', array_merge(['company_id' => $companyId], $dashboard));
    }

    public function exportAttendanceMonthCsv() {
        requireAdminOnly();
        $companyId = requireAdminCompany('admin/attendance');
        $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
        if (!$this->attendanceSummaryModel->isSchemaReady()) {
            $_SESSION['flash_error'] = 'Ejecutá migration_attendance_summary.sql';
            redirect('admin/attendance');
        }
        $rows = $this->attendanceSummaryModel->getMonthReportByCompany($companyId, $month);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="asistencia_' . $month . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['Empleado', 'Días total', 'OK', 'Tarde', 'Ausente', 'Licencia', 'Alertas'], ';');
        foreach ($rows as $r) {
            fputcsv($out, [
                $r->full_name,
                (int)$r->days_total,
                (int)$r->days_ok,
                (int)$r->days_late,
                (int)$r->days_no_show,
                (int)$r->days_leave,
                (int)$r->days_alerts,
            ], ';');
        }
        fclose($out);
        exit();
    }

    public function attendanceClosures() {
        require_capability('attendance.prepare');
        $companyId = requireAdminCompany('admin/dashboard');
        $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
        $suite = new HrSuite();
        $closures = $suite->query('SELECT ac.*,u.full_name prepared_name,cu.full_name closed_name FROM attendance_closures ac LEFT JOIN users u ON u.id=ac.prepared_by LEFT JOIN users cu ON cu.id=ac.closed_by WHERE ac.company_id=? ORDER BY ac.period_month DESC,ac.version_no DESC', [$companyId]);
        $preview = $this->attendanceSummaryModel->getMonthReportByCompany($companyId, $month);
        $this->view('admin/attendance_closures', compact('month','closures','preview'));
    }

    public function prepareAttendanceClosure() {
        require_capability('attendance.prepare'); if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/attendanceClosures'); csrf_verify();
        $companyId=requireAdminCompany(); $month=preg_match('/^\d{4}-\d{2}$/',$_POST['month']??'')?$_POST['month']:date('Y-m'); $suite=new HrSuite();
        $rows=$this->attendanceSummaryModel->getMonthReportByCompany($companyId,$month); $snapshot=$this->attendanceClosureSnapshot($suite,$companyId,$month,$rows); $json=json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $last=$suite->one('SELECT MAX(version_no) n FROM attendance_closures WHERE company_id=? AND period_month=?',[$companyId,$month]); $version=(int)($last->n??0)+1;
        $suite->execute("INSERT INTO attendance_closures(company_id,period_month,version_no,status,snapshot_json,snapshot_hash,prepared_by,prepared_at) VALUES(?,?,?,'draft',?,?,?,NOW())",[$companyId,$month,$version,$json,hash('sha256',$json),(int)$_SESSION['user_id']]);
        $id=$suite->one('SELECT LAST_INSERT_ID() id')->id;$suite->audit()->record('attendance.closure.prepared','attendance_closure',$id,null,['month'=>$month,'version'=>$version,'snapshot_hash'=>hash('sha256',$json)],trim($_POST['reason']??''),$companyId);$_SESSION['flash_success']='Cierre preparado. Revisalo antes de congelarlo.';redirect('admin/attendanceClosures?month='.$month);
    }

    public function closeAttendanceClosure($id) {require_capability('attendance.close');if($_SERVER['REQUEST_METHOD']!=='POST')redirect('admin/attendanceClosures');csrf_verify();$suite=new HrSuite();$c=$suite->one("SELECT * FROM attendance_closures WHERE id=? AND company_id=? AND status='draft'",[(int)$id,adminCompanyId()]);if($c){$suite->execute("UPDATE attendance_closures SET status='closed',closed_by=?,closed_at=NOW() WHERE id=?",[(int)$_SESSION['user_id'],$id]);$suite->audit()->record('attendance.closure.closed','attendance_closure',$id,['status'=>'draft'],['status'=>'closed'],trim($_POST['reason']??''),adminCompanyId());}redirect('admin/attendanceClosures?month='.($c->period_month??date('Y-m')));}
    public function reopenAttendanceClosure($id) {require_capability('attendance.close');if($_SERVER['REQUEST_METHOD']!=='POST')redirect('admin/attendanceClosures');csrf_verify();$reason=trim($_POST['reason']??'');if($reason===''){$_SESSION['flash_error']='La reapertura requiere motivo.';redirect('admin/attendanceClosures');}$suite=new HrSuite();$c=$suite->one("SELECT * FROM attendance_closures WHERE id=? AND company_id=? AND status='closed'",[(int)$id,adminCompanyId()]);if($c){$suite->execute("UPDATE attendance_closures SET status='reopened',reopened_at=NOW(),reopen_reason=? WHERE id=?",[$reason,$id]);$suite->audit()->record('attendance.closure.reopened','attendance_closure',$id,['status'=>'closed'],['status'=>'reopened'],$reason,adminCompanyId());}redirect('admin/attendanceClosures?month='.($c->period_month??date('Y-m')));}
    public function exportAttendanceClosureCsv($id){require_capability('attendance.review');$suite=new HrSuite();$c=$suite->one('SELECT * FROM attendance_closures WHERE id=? AND company_id=?',[(int)$id,adminCompanyId()]);if(!$c){http_response_code(404);exit;}$s=json_decode($c->snapshot_json,true);header('Content-Type:text/csv;charset=UTF-8');header('Content-Disposition:attachment;filename="novedades_'.$c->period_month.'_v'.$c->version_no.'.csv"');$o=fopen('php://output','w');fprintf($o,chr(0xEF).chr(0xBB).chr(0xBF));fputcsv($o,['Empleado','Días','OK','Tardanzas','Ausencias','Licencias','Salidas tempranas','Falta salida','Horas extra 50%','Horas extra 100%'],';');foreach($s['employees']??[] as $r)fputcsv($o,array_values($r),';');fclose($o);exit;}
    public function exportAttendanceClosurePdf($id){require_capability('attendance.review');$suite=new HrSuite();$c=$suite->one('SELECT * FROM attendance_closures WHERE id=? AND company_id=?',[(int)$id,adminCompanyId()]);if(!$c){http_response_code(404);exit;}$s=json_decode($c->snapshot_json,true);(new SimplePdfService())->download('novedades_'.$c->period_month.'_v'.$c->version_no.'.pdf','Novedades mensuales',['Empleado','Días','OK','Tarde','Aus.','Lic.','Salida temp.','Falta salida','HE 50','HE 100'],array_map('array_values',$s['employees']??[]),['Período'=>$c->period_month,'Versión'=>$c->version_no,'Hash'=>$c->snapshot_hash,'Carácter'=>'Informativo; sin importes ni liquidación salarial']);}
    private function attendanceClosureSnapshot($suite,$companyId,$month,$rows){$start=$month.'-01';$end=date('Y-m-t',strtotime($start));$details=$suite->query("SELECT ads.user_id,SUM(ads.status='early_leave') early,SUM(ads.status='missing_out') missing_out,COALESCE(SUM(CASE WHEN oe.status='archived' THEN oe.hours_50 ELSE 0 END),0) h50,COALESCE(SUM(CASE WHEN oe.status='archived' THEN oe.hours_100 ELSE 0 END),0) h100 FROM attendance_day_summary ads LEFT JOIN overtime_entries oe ON oe.user_id=ads.user_id AND oe.entry_date=ads.work_date WHERE ads.company_id=? AND ads.work_date BETWEEN ? AND ? GROUP BY ads.user_id",[$companyId,$start,$end]);$map=[];foreach($details as $d)$map[$d->user_id]=$d;$out=[];foreach($rows as $r){$x=$map[$r->user_id]??(object)[];$out[]=['employee'=>$r->full_name,'days'=>(int)$r->days_total,'ok'=>(int)$r->days_ok,'late'=>(int)$r->days_late,'absence'=>(int)$r->days_no_show,'leave'=>(int)$r->days_leave,'early'=>(int)($x->early??0),'missing_out'=>(int)($x->missing_out??0),'h50'=>(float)($x->h50??0),'h100'=>(float)($x->h100??0)];}return ['company_id'=>$companyId,'period'=>$month,'generated_at'=>date(DATE_ATOM),'employees'=>$out];}

    private function view($view, $data = []){
        if (file_exists('../app/views/' . $view . '.php')) {
            extract($data, EXTR_SKIP);
            require_once '../app/views/' . $view . '.php';
        } else {
            die('Error: La vista no existe: ' . $view);
        }
    }

    public function saveDayAjax() {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método de solicitud no permitido.']);
            exit();
        }

        // Fix 5: verificar token CSRF en AJAX
        $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if(empty($csrfToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)){
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
            exit();
        }

        // Fix 4: sanitización compatible con PHP 7.4+ (sin FILTER_SANITIZE_STRING deprecado)
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $date   = isset($_POST['date']) ? trim(strip_tags($_POST['date'])) : '';

        // Fix 7: validar formato de fecha
        if(!$userId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)){
            echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
            exit();
        }

        $targetUser = $this->userModel->getUserById($userId);
        if (!$targetUser || (int)$targetUser->company_id !== adminCompanyId() || !supervisorCanAccessUser($targetUser)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No autorizado.']);
            exit();
        }

        $branchId = max(0, (int)($_POST['branch_id'] ?? 0));
        if ($this->userModel->isBranchAssignmentReady()
            && ($branchId <= 0 || !$this->userModel->isUserAssignedToBranch((int)$targetUser->id, $branchId))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'El empleado no pertenece a la sucursal seleccionada.']);
            exit();
        }

        $entries = array();
        $companyId = (int)($_SESSION['user_company_id'] ?? 0);
        $validTypes = function_exists('planner_valid_schedule_types')
            ? planner_valid_schedule_types($companyId)
            : (vacation_module_ready() ? vacation_planner_valid_types() : ['shift', 'custom', 'overtime']);
        $allowNewOvertime = in_array('overtime', $validTypes, true);
        if (isset($_POST['schedules'][$userId][$date])) {
            foreach ($_POST['schedules'][$userId][$date] as $entryData) {
                // Fix 4: sanitización compatible PHP 7.4+
                $type     = isset($entryData['type']) ? trim(strip_tags($entryData['type'])) : 'shift';
                $shiftId  = isset($entryData['shift_id']) ? filter_var($entryData['shift_id'], FILTER_VALIDATE_INT) : null;
                $startTime = isset($entryData['start_time']) ? trim(strip_tags($entryData['start_time'])) : null;
                $endTime   = isset($entryData['end_time'])   ? trim(strip_tags($entryData['end_time']))   : null;
                $notes     = isset($entryData['notes'])      ? trim(strip_tags($entryData['notes']))      : null;

                if(!in_array($type, $validTypes)) continue; // ignorar tipos desconocidos

                $entries[] = array(
                    'type'       => $type,
                    'shift_id'   => ($shiftId > 0) ? $shiftId : null,
                    'start_time' => (!empty($startTime) && preg_match('/^\d{2}:\d{2}$/', $startTime)) ? $startTime : null,
                    'end_time'   => (!empty($endTime)   && preg_match('/^\d{2}:\d{2}$/', $endTime))   ? $endTime   : null,
                    'notes'      => $notes,
                );
            }
        }

        $oldEntries = $this->workScheduleModel->getPlannerEntriesForUserOnDate($userId, $date, $branchId);
        if (!$allowNewOvertime && function_exists('planner_preserve_locked_overtime_entries')) {
            $entries = planner_preserve_locked_overtime_entries($entries, $oldEntries);
        }
        if ($this->workScheduleModel->saveDaySchedule($userId, $date, $entries, $branchId)) {
            if (vacation_module_ready()) {
                $ledger = new VacationLedgerService();
                $vr = $ledger->processPlannerDayChange($userId, $date, $oldEntries, $entries, (int)$_SESSION['user_id']);
                if (!$vr['ok']) {
                    echo json_encode(['success' => false, 'message' => $vr['message']]);
                    exit();
                }
            }
            echo json_encode(['success' => true, 'message' => 'Horario guardado con éxito.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar el horario.']);
        }
        exit();
    }

    /**
     * Guarda planificación semanal y aplica ledger de vacaciones por día.
     * @return array{ok:bool,message:string}
     */
    private function savePlannerSchedulesWithVacationLedger(array $schedules, $branchId = 0) {
        $ledger = vacation_module_ready() ? new VacationLedgerService() : null;
        $adminId = (int)($_SESSION['user_id'] ?? 0);
        $companyId = (int)($_SESSION['user_company_id'] ?? 0);
        $validTypes = function_exists('planner_valid_schedule_types')
            ? planner_valid_schedule_types($companyId)
            : (vacation_module_ready() ? vacation_planner_valid_types() : ['shift', 'custom', 'overtime']);
        $allowNewOvertime = in_array('overtime', $validTypes, true);

        if ($this->userModel->isBranchAssignmentReady()) {
            $branches = $this->companyModel->getBranches($companyId, false);
            if (!empty($branches) && !$this->companyModel->getBranchByIdForCompany((int)$branchId, $companyId, true)) {
                return ['ok' => false, 'message' => 'Seleccioná una sucursal válida para guardar la planificación.'];
            }
        }

        foreach ($schedules as $userId => $days) {
            $targetUser = $this->userModel->getUserById((int)$userId);
            if (!$targetUser || $targetUser->company_id != $_SESSION['user_company_id']) {
                continue;
            }
            if ($this->userModel->isBranchAssignmentReady()
                && ((int)$branchId <= 0 || !$this->userModel->isUserAssignedToBranch((int)$targetUser->id, (int)$branchId))) {
                continue;
            }
            foreach ($days as $date => $rawEntries) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date)) {
                    continue;
                }
                $entries = [];
                if (is_array($rawEntries)) {
                    foreach ($rawEntries as $entryData) {
                        $type = isset($entryData['type']) ? trim(strip_tags($entryData['type'])) : 'shift';
                        if (!in_array($type, $validTypes, true)) {
                            continue;
                        }
                        $shiftId = isset($entryData['shift_id']) ? filter_var($entryData['shift_id'], FILTER_VALIDATE_INT) : null;
                        $entries[] = [
                            'type' => $type,
                            'shift_id' => ($shiftId > 0) ? $shiftId : null,
                            'start_time' => !empty($entryData['start_time']) ? $entryData['start_time'] : null,
                            'end_time' => !empty($entryData['end_time']) ? $entryData['end_time'] : null,
                            'notes' => isset($entryData['notes']) ? trim(strip_tags($entryData['notes'])) : null,
                        ];
                    }
                }
                $oldEntries = $this->workScheduleModel->getPlannerEntriesForUserOnDate((int)$userId, $date, $branchId);
                if (!$allowNewOvertime && function_exists('planner_preserve_locked_overtime_entries')) {
                    $entries = planner_preserve_locked_overtime_entries($entries, $oldEntries);
                }
                if (!$this->workScheduleModel->saveDaySchedule((int)$userId, $date, $entries, $branchId)) {
                    return ['ok' => false, 'message' => 'Error al guardar el día ' . $date . '.'];
                }
                if ($ledger) {
                    $vr = $ledger->processPlannerDayChange((int)$userId, $date, $oldEntries, $entries, $adminId);
                    if (!$vr['ok']) {
                        return $vr;
                    }
                }
            }
        }
        return ['ok' => true, 'message' => ''];
    }

    /**
     * Verifica que el área elegida pertenezca a la empresa del formulario.
     */
    private function employmentViewData($userId = 0, $source = null) {
        $agreementModel = new CollectiveAgreement();
        $userModel = new User();
        $employmentReady = $userModel->isVacationProfileReady();
        $balanceReady = function_exists('vacation_module_ready') && vacation_module_ready();
        return [
            'employment_ready' => $employmentReady,
            'agreements_ready' => $agreementModel->isReady(),
            'vacation_balance_ready' => $balanceReady,
            'agreements' => $agreementModel->isReady() ? $agreementModel->getAll() : [],
            'vacation_total_pending' => ($balanceReady && $userId > 0)
                ? (new VacationBalance())->getTotalPending($userId) : null,
            'show_legacy_vacation_days' => $employmentReady && !$balanceReady,
            'source' => $source,
            'user_id' => (int)$userId,
        ];
    }

    /** Valida que la sede sea operativa y pertenezca a la empresa elegida. */
    private function validateUserBranch(array &$data) {
        if (!$this->userModel->isBranchAssignmentReady()) {
            return;
        }
        $companyId = (int)($data['company_id'] ?? 0);
        $branchIds = array_values(array_unique(array_filter(array_map('intval', (array)($data['branch_ids'] ?? [])))));
        $branchId = (int)($data['branch_id'] ?? 0);
        $role = (string)($data['role'] ?? 'empleado');
        $branches = $companyId > 0 ? $this->companyModel->getBranches($companyId, false) : [];

        if ($role === 'empleado' && !empty($branches) && empty($branchIds)) {
            $data['errors']['branch_id'] = 'Seleccioná al menos una sucursal donde trabaja el empleado.';
            return;
        }
        foreach ($branchIds as $candidateBranchId) {
            if (!$this->companyModel->getBranchByIdForCompany($candidateBranchId, $companyId, true)) {
                $data['errors']['branch_id'] = 'Una sucursal seleccionada no pertenece a la empresa o está inactiva.';
                return;
            }
        }
        if (!empty($branchIds) && !in_array($branchId, $branchIds, true)) {
            $data['errors']['branch_id'] = 'Elegí una de las sucursales seleccionadas como principal.';
            return;
        }
        $data['branch_ids'] = $branchIds;
        $data['branch_id'] = !empty($branchIds) ? $branchId : 0;
    }

    private function employeeRecordViewData($userId, $companyId) {
        return ['employee_record' => $this->employeeRecordModel->getFormData((int)$userId, (int)$companyId)];
    }

    private function validateUserArea(array &$data) {
        if (!$this->userModel->isAreaReady()) {
            return;
        }
        $areaId = isset($data['area_id']) ? (int)$data['area_id'] : 0;
        if ($areaId <= 0) {
            return;
        }
        $areaModel = new Area();
        $companyId = (int)($data['company_id'] ?? 0);
        if (!$areaModel->isAvailableForCompany($areaId, $companyId)) {
            $data['errors']['area_id'] = 'El área no aplica a la empresa del empleado.';
        }
    }
}
?>
