<?php

class RequestController {
    private $requestModel;
    private $userModel;
    private $workScheduleModel;
    private $shiftSwapModel;

    public function __construct() {
        requireEmployeeRole();
        $this->requestModel = new Request();
        $this->userModel = new User();
        $this->workScheduleModel = new WorkSchedule();
        $this->shiftSwapModel = new ShiftSwap();
    }

    public function index() {
        $userId = (int)$_SESSION['user_id'];
        $user = $this->userModel->getUserById($userId);
        if (!$user) {
            redirect('login');
        }

        $requests = $this->requestModel->getRequestsByUserId($userId);
        $requestTypes = $this->filterEmployeeRequestTypes($this->requestModel->getRequestTypes(), $user);

        $swapModuleReady = $this->shiftSwapModel->isSchemaReady();
        $shiftSwaps = [];
        if ($swapModuleReady) {
            $shiftSwaps = $this->shiftSwapModel->getSwapsByUserId($userId);
        }

        $mySchedules = $this->workScheduleModel->getUpcomingSchedulesForUser(
            $userId,
            $user->company_id,
            date('Y-m-d'),
            date('Y-m-d', strtotime('+60 days'))
        );
        $companyModel = new Company();
        $companyName = $companyModel->getNameById($user->company_id);
        $hasCompany = !empty($user->company_id);
        $colleagues = $hasCompany
            ? $this->userModel->getColleaguesForShiftSwap($user->company_id, $userId)
            : [];

        $vacationPending = null;
        if (employee_portal_can('vacation_balance') && vacation_module_ready()) {
            $vacationPending = (new VacationBalance())->getTotalPending($userId);
        }

        $effectiveAgreement = null;
        $agreementLeaveTypes = [];
        if (agreement_leave_types_ready()) {
            $effectiveAgreement = (new VacationEntitlementService())->getEffectiveAgreement($user);
            if ($effectiveAgreement) {
                $agreementLeaveTypes = (new CollectiveAgreement())->getLeaveTypes((int)$effectiveAgreement->id);
            }
        }

        $data = [
            'requests'          => $requests,
            'requestTypes'      => $requestTypes,
            'shiftSwaps'        => $shiftSwaps,
            'mySchedules'       => $mySchedules,
            'colleagues'        => $colleagues,
            'company_name'      => $companyName,
            'has_company'       => $hasCompany,
            'swap_module_ready' => $swapModuleReady,
            'vacation_pending'  => $vacationPending,
            'vacation_ready'    => vacation_module_ready(),
            'effective_agreement' => $effectiveAgreement,
            'agreement_leave_types' => $agreementLeaveTypes,
        ];

        $this->view('employee/requests', $data);
    }

    public function streamCertificate($id = 0) {
        $id = (int)$id;
        $userId = (int)$_SESSION['user_id'];
        $request = $this->requestModel->getRequestById($id);
        if (!$request || (int)$request->user_id !== $userId || empty($request->certificate_path)) {
            http_response_code(404);
            exit;
        }
        protected_upload_send('request_certificates/' . $request->certificate_path, true, basename((string)$request->certificate_path));
    }

    public function vacationPreview() {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !vacation_module_ready()) {
            echo json_encode(['ok'=>false, 'message'=>'Vista previa no disponible.']);
            exit;
        }
        csrf_verify();
        $userId = (int)$_SESSION['user_id'];
        $start = trim($_POST['start_date'] ?? '');
        $end = trim($_POST['end_date'] ?? '') ?: $start;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            echo json_encode(['ok'=>false, 'message'=>'Seleccioná las fechas.']);
            exit;
        }
        $preview = (new VacationLedgerService())->previewRequest((object)[
            'user_id'=>$userId, 'start_date'=>$start, 'end_date'=>$end,
        ]);
        $available = (new VacationBalance())->getTotalPending($userId);
        $preview['total_available'] = $available;
        $preview['remaining_after'] = $preview['ok'] ? max(0, $available - (float)$preview['days']) : $available;
        echo json_encode($preview, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function create() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('request/index');
        }
        csrf_verify();

        $userId = (int)$_SESSION['user_id'];
        $user = $this->userModel->getUserById($userId);
        $agreementLeaveTypeId = (int)($_POST['agreement_leave_type_id'] ?? 0);
        $typeId = (int)($_POST['request_type_id'] ?? 0);
        $agreementLeaveType = null;
        if ($agreementLeaveTypeId > 0 && agreement_leave_types_ready()) {
            $agreementLeaveType = (new CollectiveAgreement())->getLeaveTypeById($agreementLeaveTypeId);
            $effectiveAgreement = (new VacationEntitlementService())->getEffectiveAgreement($user);
            if (!$agreementLeaveType || !$effectiveAgreement
                || (int)$agreementLeaveType->agreement_id !== (int)$effectiveAgreement->id
                || empty($agreementLeaveType->is_active)) {
                $_SESSION['flash_error'] = 'La licencia seleccionada no corresponde a tu convenio.';
                redirect('request/index?tab=absence');
            }
            $licenseTypeId = $this->requestModel->getLicenseRequestTypeId();
            if (!$licenseTypeId) {
                $_SESSION['flash_error'] = 'Falta el tipo de solicitud «Licencia» en el sistema. Contactá a RRHH.';
                redirect('request/index?tab=absence');
            }
            $typeId = $licenseTypeId;
        }
        $type = $this->getRequestTypeById($typeId);
        $requestedStart = trim($_POST['start_date'] ?? '');
        $requestedEnd = !empty($_POST['end_date']) ? trim($_POST['end_date']) : $requestedStart;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedStart)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedEnd) || $requestedEnd < $requestedStart) {
            $_SESSION['flash_error'] = 'Indicá un rango de fechas válido.';
            redirect('request/index?tab=absence');
        }

        if ($this->isVacationType($type) && !employee_portal_can('vacation_balance')) {
            $_SESSION['flash_error'] = 'Las solicitudes de vacaciones no están disponibles en el portal.';
            redirect('request/index?tab=absence');
        }

        if ($this->isVacationType($type) && vacation_module_ready()) {
            $entitlement = new VacationEntitlementService();
            $days = $entitlement->countDaysForUserRange(
                $userId,
                $requestedStart,
                $requestedEnd
            );
            $agreement = $entitlement->getEffectiveAgreement($user);
            if (!$agreement) {
                $_SESSION['flash_error'] = 'No tenés un convenio de vacaciones asignado. Pedí a RRHH que complete tu encuadramiento.';
                redirect('request/index?tab=absence');
            }
            $minimumDays = (float)($agreement->minimum_request_days ?? 7);
            $pending = (new VacationBalance())->getTotalPending($userId);
            if ($days <= 0) {
                $_SESSION['flash_error'] = 'El rango de fechas no tiene días hábiles de vacaciones.';
                redirect('request/index?tab=absence');
            }
            if ($days > $pending) {
                $_SESSION['flash_error'] = 'No tenés suficientes días pendientes (' . vacation_format_days($pending) . '). Pedí a RRHH la liquidación del período.';
                redirect('request/index?tab=absence');
            }
            if ($days < $minimumDays) {
                $_SESSION['flash_error'] = 'La solicitud de vacaciones debe comprender al menos '
                    . vacation_format_days($minimumDays) . ' días computables. Tu saldo restante no se pierde.';
                redirect('request/index?tab=absence');
            }
        } elseif ($this->isVacationType($type)) {
            $_SESSION['flash_error'] = 'Las solicitudes de vacaciones deben gestionarse con Recursos Humanos.';
            redirect('request/index?tab=absence');
        } elseif ($agreementLeaveType) {
            $days = vacation_count_days_in_range(
                $requestedStart,
                $requestedEnd,
                $agreementLeaveType->day_count_mode ?? 'calendar',
                (int)($user->company_id ?? 0),
                null,
                (int)($user->branch_id ?? 0)
            );
            if ($days <= 0) {
                $_SESSION['flash_error'] = 'El rango de fechas no tiene días computables para esta licencia.';
                redirect('request/index?tab=absence');
            }
            if ($agreementLeaveType->max_days_per_event !== null && $days > (float)$agreementLeaveType->max_days_per_event) {
                $_SESSION['flash_error'] = 'Esta licencia admite como máximo '
                    . vacation_format_days((float)$agreementLeaveType->max_days_per_event)
                    . ' por evento.';
                redirect('request/index?tab=absence');
            }
            if (!empty($agreementLeaveType->min_notice_days)) {
                $noticeDays = (int)$agreementLeaveType->min_notice_days;
                $minStart = date('Y-m-d', strtotime('+' . $noticeDays . ' days'));
                if ($requestedStart < $minStart) {
                    $_SESSION['flash_error'] = 'Esta licencia requiere avisar con al menos ' . $noticeDays . ' día(s) de anticipación.';
                    redirect('request/index?tab=absence');
                }
            }
            if ($agreementLeaveType->max_days_per_year !== null && $agreementLeaveType->max_days_per_year !== '') {
                $year = (int)substr($requestedStart, 0, 4);
                $used = $this->requestModel->sumAgreementLeaveDaysForYear($userId, (int)$agreementLeaveType->id, $year);
                $maxYear = (float)$agreementLeaveType->max_days_per_year;
                if ($used + $days > $maxYear) {
                    $_SESSION['flash_error'] = 'Superás el máximo anual de '
                        . vacation_format_days($maxYear) . ' para esta licencia. Ya registraste '
                        . vacation_format_days($used) . ' en ' . $year . '.';
                    redirect('request/index?tab=absence');
                }
            }
        }

        if ($this->isShiftSwapType($type)) {
            $_SESSION['flash_error'] = 'Los cambios de turno se gestionan en la pestaña «Cambio de turno».';
            redirect('request/index?tab=swap');
        }

        $allowedTypeIds = array_map(function ($t) {
            return (int)$t->id;
        }, $this->filterEmployeeRequestTypes($this->requestModel->getRequestTypes(), $user));
        if ($typeId > 0 && !in_array($typeId, $allowedTypeIds, true) && !$agreementLeaveType) {
            $_SESSION['flash_error'] = 'El tipo de solicitud seleccionado no está disponible.';
            redirect('request/index?tab=absence');
        }

        $data = [
            'user_id'         => $userId,
            'request_type_id' => $typeId,
            'start_date'      => $requestedStart,
            'end_date'        => $requestedEnd,
            'reason'          => trim(strip_tags($_POST['reason'] ?? '')),
        ];
        if ($agreementLeaveType) {
            $data['agreement_leave_type_id'] = (int)$agreementLeaveType->id;
        }

        if ($data['request_type_id'] <= 0 || $data['start_date'] === '' || $data['reason'] === '') {
            $_SESSION['flash_error'] = 'Completá todos los campos de la solicitud.';
            redirect('request/index?tab=absence');
        }

        if (!$this->requestModel->createRequest($data)) {
            $_SESSION['flash_error'] = 'No se pudo enviar la solicitud.';
            redirect('request/index?tab=absence');
        }

        $newId = (int)$this->requestModel->lastInsertId();
        if ($newId > 0 && !empty($_FILES['certificate']['name'])) {
            $upload = uploads_store_request_certificate($newId, 'certificate');
            if (!$upload['ok']) {
                $this->requestModel->deleteRequest($newId);
                $_SESSION['flash_error'] = $upload['message'] ?: 'El certificado no pudo guardarse.';
                redirect('request/index?tab=absence');
            }
            $this->requestModel->updateAdminMeta($newId, ['certificate_path' => $upload['filename']]);
        }
        if ($newId > 0 && !empty($_FILES['certificate_back']['name'])) {
            $uploadBack = uploads_store_request_certificate($newId, 'certificate_back', '_back');
            if (!$uploadBack['ok']) {
                $_SESSION['flash_error'] = $uploadBack['message'] ?: 'El dorso del certificado no pudo guardarse.';
                redirect('request/index?tab=absence');
            }
            $this->requestModel->updateAdminMeta($newId, ['certificate_back_path' => $uploadBack['filename']]);
        }

        $autoRegistered = false;
        if ($newId > 0 && $agreementLeaveType && !agreement_leave_type_requires_approval($agreementLeaveType)) {
            if ($this->requestModel->updateRequestStatus($newId, 'Aprobado')) {
                $autoRegistered = true;
            }
        }

        if ($autoRegistered) {
            $successMsg = 'Tu aviso quedó registrado. No necesitás esperar aprobación de RRHH.';
        } else {
            $successMsg = 'Tu solicitud fue enviada correctamente.';
        }
        if ($agreementLeaveType && !empty($agreementLeaveType->requires_certificate)) {
            $hasCert = $newId > 0 && !empty($_FILES['certificate']['name']);
            $hasCertBack = $newId > 0 && !empty($_FILES['certificate_back']['name']);
            if (!$hasCert && !$hasCertBack) {
                $successMsg .= ' Cuando tengas el certificado médico, adjuntalo (frente y dorso) desde tu historial.';
            } elseif (!$hasCert || !$hasCertBack) {
                $successMsg .= ' Recordá completar frente y dorso del certificado cuando los tengas.';
            }
        }
        $_SESSION['flash_success'] = $successMsg;
        redirect('request/index?tab=absence');
    }

    private function requestAllowsCertificateUpload($request) {
        if (!$request || empty($request->agreement_leave_type_id) || empty($request->agreement_leave_requires_certificate)) {
            return false;
        }
        if (($request->status ?? '') === 'Rechazado') {
            return false;
        }
        if (($request->status ?? '') === 'Pendiente') {
            return true;
        }
        if (($request->status ?? '') === 'Aprobado') {
            return !agreement_leave_type_requires_approval($request);
        }
        return false;
    }

    public function uploadCertificate($id = 0) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('request/index?tab=absence');
        }
        csrf_verify();
        $id = (int)$id;
        $userId = (int)$_SESSION['user_id'];
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'Solicitud inválida.';
            redirect('request/index?tab=absence');
        }

        $request = $this->requestModel->getRequestById($id);
        if (!$request || (int)$request->user_id !== $userId) {
            $_SESSION['flash_error'] = 'No encontramos esa solicitud.';
            redirect('request/index?tab=absence');
        }
        if (!$this->requestAllowsCertificateUpload($request)) {
            $_SESSION['flash_error'] = 'No podés adjuntar certificados en esta solicitud.';
            redirect('request/index?tab=absence');
        }

        $meta = [];
        $hasFront = !empty($_FILES['certificate']['name']) && ($_FILES['certificate']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        $hasBack = !empty($_FILES['certificate_back']['name']) && ($_FILES['certificate_back']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        if (!$hasFront && !$hasBack) {
            $_SESSION['flash_error'] = 'Seleccioná al menos un archivo (frente o dorso del certificado).';
            redirect('request/index?tab=absence');
        }

        if ($hasFront) {
            $upload = uploads_store_request_certificate($id, 'certificate');
            if (!$upload['ok']) {
                $_SESSION['flash_error'] = $upload['message'] ?: 'No se pudo guardar el frente del certificado.';
                redirect('request/index?tab=absence');
            }
            $meta['certificate_path'] = $upload['filename'];
        }
        if ($hasBack) {
            if (!$this->requestModel->supportsCertificateBack()) {
                $_SESSION['flash_error'] = 'Falta actualizar la base de datos para el dorso del certificado. Avisá a RRHH para ejecutar la migración en el servidor.';
                redirect('request/index?tab=absence');
            }
            $uploadBack = uploads_store_request_certificate($id, 'certificate_back', '_back');
            if (!$uploadBack['ok']) {
                $_SESSION['flash_error'] = $uploadBack['message'] ?: 'No se pudo guardar el dorso del certificado.';
                redirect('request/index?tab=absence');
            }
            $meta['certificate_back_path'] = $uploadBack['filename'];
        }

        if (!$this->requestModel->updateAdminMeta($id, $meta)) {
            $_SESSION['flash_error'] = 'No se pudo actualizar la solicitud.';
            redirect('request/index?tab=absence');
        }

        $_SESSION['flash_success'] = 'Certificado guardado correctamente.';
        redirect('request/index?tab=absence');
    }

    public function streamCertificateBack($id = 0) {
        $id = (int)$id;
        $userId = (int)$_SESSION['user_id'];
        $request = $this->requestModel->getRequestById($id);
        if (!$request || (int)$request->user_id !== $userId || empty($request->certificate_back_path)) {
            http_response_code(404);
            exit;
        }
        protected_upload_send('request_certificates/' . $request->certificate_back_path, true, basename((string)$request->certificate_back_path));
    }

    public function vacationPlanilla($id = 0) {
        $userId = (int)$_SESSION['user_id'];
        if (!vacation_module_ready()) {
            $_SESSION['flash_error'] = 'La planilla de vacaciones no está disponible.';
            redirect('request/index?tab=absence');
        }
        $request = null;
        $id = (int)$id;
        if ($id > 0) {
            $request = $this->requestModel->getRequestById($id);
            if (!$request || (int)$request->user_id !== $userId || !vacation_is_vacation_request($request)) {
                $_SESSION['flash_error'] = 'No se encontró esa solicitud de vacaciones.';
                redirect('request/index?tab=absence');
            }
        } elseif (!employee_portal_can('vacation_balance')) {
            $_SESSION['flash_error'] = 'El saldo de vacaciones no está habilitado en el portal.';
            redirect('request/index?tab=absence');
        }
        $asPdf = isset($_GET['format']) && $_GET['format'] === 'pdf';
        if (!vacation_planilla_render($userId, $request, $asPdf)) {
            $_SESSION['flash_error'] = 'No se pudo generar la planilla.';
            redirect('request/index?tab=absence');
        }
    }

    public function createShiftSwap() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('request/index');
        }
        csrf_verify();

        $userId = (int)$_SESSION['user_id'];
        $user = $this->userModel->getUserById($userId);
        if (!$user) {
            redirect('request/index');
        }

        $proposerScheduleId = (int)($_POST['proposer_schedule_id'] ?? 0);
        $accepterUserId = (int)($_POST['accepter_user_id'] ?? 0);
        $notes = trim(strip_tags($_POST['notes'] ?? ''));

        if (empty($user->company_id)) {
            $_SESSION['flash_error'] = 'Tu usuario no tiene empresa asignada. Pedí a Recursos Humanos que te asignen una (Servicios Sociales, Casa Paviotti, A.M.S.S.I o Ecofarma).';
            redirect('request/index?tab=swap');
        }

        if (!$proposerScheduleId || !$accepterUserId) {
            $_SESSION['flash_error'] = 'Seleccioná tu turno y el compañero con quien querés intercambiar.';
            redirect('request/index?tab=swap');
        }

        if ($accepterUserId === $userId) {
            $_SESSION['flash_error'] = 'No podés intercambiar turno con vos mismo.';
            redirect('request/index?tab=swap');
        }

        $mySchedule = $this->workScheduleModel->getScheduleEntryById($proposerScheduleId, $user->company_id);
        if (!$mySchedule || (int)$mySchedule->user_id !== $userId) {
            $_SESSION['flash_error'] = 'El turno que ofrecés no es válido.';
            redirect('request/index?tab=swap');
        }

        $colleague = $this->userModel->getUserById($accepterUserId);
        if (!$colleague
            || (int)$colleague->company_id !== (int)$user->company_id
            || empty($colleague->is_active)) {
            $_SESSION['flash_error'] = 'Solo podés intercambiar turno con un compañero activo de tu misma empresa.';
            redirect('request/index?tab=swap');
        }

        if (!$this->shiftSwapModel->isSchemaReady()) {
            $_SESSION['flash_error'] = 'El módulo de cambio de turno no está listo. Ejecute migration_shift_swaps_fix.sql.';
            redirect('request/index?tab=swap');
        }

        if ($this->shiftSwapModel->hasPendingForProposerAndColleague($proposerScheduleId, $accepterUserId)) {
            $_SESSION['flash_error'] = 'Ya hay una solicitud pendiente con ese compañero para este turno.';
            redirect('request/index?tab=swap');
        }

        if ($this->shiftSwapModel->createSwapRequest([
            'proposer_user_id'      => $userId,
            'accepter_user_id'      => $accepterUserId,
            'proposer_schedule_id'  => $proposerScheduleId,
            'accepter_schedule_id'  => null,
            'notes'                 => $notes,
        ])) {
            $_SESSION['flash_success'] = 'Solicitud de cambio de turno enviada. Un administrador debe aprobarla.';
        } else {
            $_SESSION['flash_error'] = 'No se pudo registrar el cambio de turno.';
        }

        redirect('request/index');
    }

    private function filterEmployeeRequestTypes(array $types, $user = null) {
        $agreementCodes = [];
        if ($user && agreement_leave_types_ready()) {
            $agreement = (new VacationEntitlementService())->getEffectiveAgreement($user);
            if ($agreement) {
                foreach ((new CollectiveAgreement())->getLeaveTypes((int)$agreement->id) as $leave) {
                    $agreementCodes[] = strtoupper((string)$leave->code);
                }
            }
        }
        return array_values(array_filter($types, function ($t) use ($agreementCodes) {
            if ($this->isShiftSwapType($t)) {
                return false;
            }
            if ($this->isVacationType($t)) {
                return vacation_module_ready() && employee_portal_can('vacation_balance');
            }
            if ($this->isLicenseRequestType($t)) {
                return false;
            }
            $name = mb_strtolower((string)($t->name ?? ''), 'UTF-8');
            if (in_array('EXAMEN', $agreementCodes, true) && strpos($name, 'examen') !== false) {
                return false;
            }
            return true;
        }));
    }

    private function isLicenseRequestType($type) {
        if (!$type || empty($type->name)) {
            return false;
        }
        return mb_strtolower((string)$type->name, 'UTF-8') === 'licencia';
    }

    private function isVacationType($type) {
        if (!$type || empty($type->name)) {
            return false;
        }
        $name = mb_strtolower($type->name, 'UTF-8');
        return (strpos($name, 'vacacion') !== false) || (isset($type->id) && (int)$type->id === 1);
    }

    private function isShiftSwapType($type) {
        if (!$type || empty($type->name)) {
            return false;
        }
        $name = mb_strtolower($type->name, 'UTF-8');
        return strpos($name, 'cambio de turno') !== false
            || strpos($name, 'cambio turno') !== false
            || strpos($name, 'intercambio') !== false;
    }

    private function getRequestTypeById($id) {
        foreach ($this->requestModel->getRequestTypes() as $type) {
            if ((int)$type->id === (int)$id) {
                return $type;
            }
        }
        return null;
    }

    private function formatScheduleLabel($entry) {
        $date = date('d/m/Y', strtotime($entry->schedule_date));
        $name = !empty($entry->shift_name) ? $entry->shift_name : ucfirst($entry->type ?? 'Turno');
        $start = $entry->start_time ? substr($entry->start_time, 0, 5) : '';
        $end = $entry->end_time ? substr($entry->end_time, 0, 5) : '';
        return $date . ' · ' . $name . ' (' . $start . '–' . $end . ')';
    }

    private function view($view, $data = []) {
        if (file_exists('../app/views/' . $view . '.php')) {
            require_once '../app/views/' . $view . '.php';
        } else {
            die('La vista no existe');
        }
    }
}
