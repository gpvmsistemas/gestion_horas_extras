<?php
class EmployeeController {
    private $overtimeModel;
    private $scheduleModel;
    private $workScheduleModel;
    private $requestModel;
    private $userModel;
    private $shiftSwapModel;

    private $userNotificationModel;
    private $payStubModel;
    private $announcementDisplay;

    public function __construct(){
        if(!hasRole('empleado')){
            redirect('login');
        }
        $this->overtimeModel     = new Overtime();
        $this->scheduleModel     = new Schedule();
        $this->workScheduleModel = new WorkSchedule();
        $this->requestModel      = new Request();
        $this->userModel         = new User();
        $this->shiftSwapModel    = new ShiftSwap();
        if (notifications_is_ready()) {
            $this->userNotificationModel = new UserNotification();
            $this->payStubModel = new PayStub();
            $this->announcementDisplay = new AnnouncementDisplayService();
        }
    }

    public function index(){
        $userId = $_SESSION['user_id'];

        // Solicitudes pendientes
        $allRequests      = $this->requestModel->getRequestsByUserId($userId);
        $pendingRequests  = array_filter($allRequests, function($r){ return $r->status === 'Pendiente'; });

        $recentUpdates = [];
        foreach ($allRequests as $r) {
            if ($r->status !== 'Pendiente') {
                $recentUpdates[] = (object)[
                    'kind' => 'licencia',
                    'label' => $r->type_name,
                    'status' => $r->status,
                    'date' => $r->start_date,
                ];
            }
        }
        if ($this->shiftSwapModel->isSchemaReady()) {
            foreach ($this->shiftSwapModel->getSwapsByUserId($userId) as $sw) {
                if ($sw->status !== 'Pendiente') {
                    $recentUpdates[] = (object)[
                        'kind' => 'swap',
                        'label' => 'Cambio de turno',
                        'status' => $sw->status,
                        'date' => $sw->proposer_date ?? $sw->created_at,
                    ];
                }
            }
        }
        usort($recentUpdates, function ($a, $b) {
            return strcmp($b->date ?? '', $a->date ?? '');
        });
        $recentUpdates = array_slice($recentUpdates, 0, 5);

        // Horarios próximos 7 días
        $user = $this->userModel->getUserById($userId);
        $companyId = $user ? $user->company_id : null;
        $upcomingSchedule = [];
        if ($companyId) {
            $startD = date('Y-m-d');
            $endD   = date('Y-m-d', strtotime('+6 days'));
            $allSched = $this->workScheduleModel->getScheduleEntriesForPeriod($companyId, $startD, $endD);
            foreach ($allSched as $s) {
                if ($s->user_id == $userId) {
                    $upcomingSchedule[] = $s;
                }
            }
            if (function_exists('schedule_filter_entries_for_scope')) {
                $upcomingSchedule = schedule_filter_entries_for_scope(
                    $upcomingSchedule,
                    (int)$companyId,
                    (int)$userId
                );
            }
            if (function_exists('employee_portal_filter_schedule_entries')) {
                $upcomingSchedule = employee_portal_filter_schedule_entries($upcomingSchedule);
            }
        }

        // Horas extras pendientes recientes (no aplica a Casa Paviotti con cp_*)
        $recentOvertime = $this->overtimeModel->getPendingEntriesByUserId($userId);
        $cpPendingTotal = 0;
        $cpPendingCount = 0;
        $usesCpTasks = function_exists('current_user_uses_casapav_tasks') && current_user_uses_casapav_tasks();
        $cpExtrasEnabled = employee_portal_can('cp_extras');
        $showCpHome = $usesCpTasks && $cpExtrasEnabled && employee_portal_can('cp_extras_home');
        $showOvertimeHome = !$usesCpTasks && employee_portal_can('overtime_home');
        if ($usesCpTasks && $cpExtrasEnabled && function_exists('cp_tasks_is_ready') && cp_tasks_is_ready()) {
            $cpTask = new CpTask();
            $cpPending = $cpTask->getPendingByUser($userId);
            $cpPendingExt = $cpTask->getPendingExternalByUser($userId);
            $cpPendingCount = count($cpPending) + count($cpPendingExt);
            foreach ($cpPending as $p) {
                $cpPendingTotal += (float)$p->amount;
            }
            foreach ($cpPendingExt as $p) {
                $cpPendingTotal += (float)$p->amount;
            }
            $recentOvertime = [];
        } elseif (!$showOvertimeHome) {
            $recentOvertime = [];
        }

        $vacationPending = null;
        if (employee_portal_can('vacation_balance') && function_exists('vacation_module_ready') && vacation_module_ready()) {
            $vacationPending = (new VacationBalance())->getTotalPending($userId);
        }

        $prodeOpen = false;
        $prodeFilled = 0;
        $prodeTotal = 0;
        $prodeSubmitted = false;
        if (function_exists('prode_is_ready') && prode_is_ready() && employee_portal_can('prode')) {
            $prodeEdition = (new ProdeEdition())->getActiveEdition();
            if ($prodeEdition && ($prodeEdition->status ?? '') === 'open') {
                $prodeOpen = true;
                $prodeTotal = (new ProdeEdition())->countMatches((int)$prodeEdition->id);
                $prodeFilled = (new ProdePrediction())->countFilledPredictions((int)$userId, (int)$prodeEdition->id);
                $entry = (new ProdePrediction())->getEntry((int)$prodeEdition->id, (int)$userId);
                $prodeSubmitted = $entry && ($entry->status ?? '') === 'submitted';
            }
        }

        $data = [
            'pendingRequestsCount'=> count($pendingRequests),
            'upcomingSchedule'    => $upcomingSchedule,
            'recentOvertime'      => array_slice($recentOvertime, 0, 5),
            'recentUpdates'       => $recentUpdates,
            'user'                => $user,
            'uses_cp_tasks'       => $usesCpTasks,
            'show_cp_home'        => $showCpHome,
            'show_overtime_home'  => $showOvertimeHome,
            'cp_pending_total'    => $cpPendingTotal,
            'cp_pending_count'    => $cpPendingCount,
            'vacation_pending'    => $vacationPending,
            'prode_open'          => $prodeOpen,
            'prode_filled'        => $prodeFilled,
            'prode_total'         => $prodeTotal,
            'prode_submitted'     => $prodeSubmitted,
        ];

        $this->view('employee/index', $data);
    }

    public function hrDocuments(){
        $m=new HrSuite();$uid=(int)$_SESSION['user_id'];
        $ppe=$m->query('SELECT pd.*,pi.name item_name FROM ppe_deliveries pd JOIN ppe_items pi ON pi.id=pd.item_id WHERE pd.user_id=? ORDER BY pd.delivered_on DESC',[$uid]);
        $assets=$m->query("SELECT a.*,ac.name category_name FROM assets a JOIN asset_categories ac ON ac.id=a.category_id WHERE a.current_custodian_user_id=? AND a.status='assigned'",[$uid]);
        $incidents=$m->query("SELECT * FROM employee_incidents WHERE user_id=? AND workflow_status IN('notified','received','refused') ORDER BY incident_date DESC",[$uid]);
        $this->view('employee/hr_documents',compact('ppe','assets','incidents'));
    }

    public function acknowledgePpe($id){if($_SERVER['REQUEST_METHOD']!=='POST')redirect('employee/hrDocuments');csrf_verify();$this->verifyOwnPassword($_POST['password']??'');$m=new HrSuite();$row=$m->one("SELECT * FROM ppe_deliveries WHERE id=? AND user_id=? AND status='issued'",[(int)$id,(int)$_SESSION['user_id']]);if($row){$m->execute("UPDATE ppe_deliveries SET status='acknowledged',acknowledged_at=NOW(),acknowledged_ip=? WHERE id=?",[$_SERVER['REMOTE_ADDR']??null,$id]);$m->audit()->record('ppe.acknowledged','ppe_delivery',$id,['status'=>'issued'],['status'=>'acknowledged','document_hash'=>$row->document_hash],'Acuse de recibo con clave',$row->company_id,$row->branch_id);}redirect('employee/hrDocuments');}
    public function acknowledgeIncident($id){if($_SERVER['REQUEST_METHOD']!=='POST')redirect('employee/hrDocuments');csrf_verify();$this->verifyOwnPassword($_POST['password']??'');$decision=($_POST['decision']??'received')==='refused'?'refused':'received';$statement=trim($_POST['statement']??'');$m=new HrSuite();$r=$m->one("SELECT ei.*,u.company_id,u.branch_id FROM employee_incidents ei JOIN users u ON u.id=ei.user_id WHERE ei.id=? AND ei.user_id=? AND ei.workflow_status='notified'",[(int)$id,(int)$_SESSION['user_id']]);if($r){$m->execute('INSERT INTO employee_incident_acknowledgements(incident_id,user_id,decision,statement_text,document_hash,request_ip) VALUES(?,?,?,?,?,?)',[$id,(int)$_SESSION['user_id'],$decision,$statement?:null,$r->document_hash,$_SERVER['REMOTE_ADDR']??null]);$m->execute('UPDATE employee_incidents SET workflow_status=? WHERE id=?',[$decision,$id]);$m->audit()->record('discipline.acknowledged','employee_incident',$id,['status'=>'notified'],['status'=>$decision,'statement'=>$statement,'document_hash'=>$r->document_hash],'Acuse de recibo con clave',$r->company_id,$r->branch_id);}redirect('employee/hrDocuments');}
    private function verifyOwnPassword($password){$db=new Database();$db->query('SELECT password FROM users WHERE id=?');$u=$db->single([(int)$_SESSION['user_id']]);if(!$u||!password_verify((string)$password,$u->password)){$_SESSION['flash_error']='Clave incorrecta.';redirect('employee/hrDocuments');}}

    public function misHorarios(){
        if (function_exists('access_portal_feature_allowed') && !access_portal_feature_allowed('schedule', true)) {
            $_SESSION['flash_error'] = 'Tu horario no está disponible para este contexto de trabajo.';
            redirect('employee/index');
        }
        $userId = $_SESSION['user_id'];

        $month = (isset($_GET['mes']) && preg_match('/^\d{4}-\d{2}$/', $_GET['mes']))
                  ? $_GET['mes'] : date('Y-m');

        $user      = $this->userModel->getUserById($userId);
        $companyId = $user ? $user->company_id : null;

        $scheduleByDate = [];
        $calendarEvents = [];
        if ($companyId) {
            $scheduleByDate = $this->workScheduleModel->getUserScheduleForMonth($userId, $companyId, $month);
        }

        $requests = $this->requestModel->getApprovedRequestsForUserCalendar($userId);

        foreach ($scheduleByDate as $date => $entries) {
            if (function_exists('schedule_filter_entries_for_scope')) {
                $entries = schedule_filter_entries_for_scope($entries, (int)$companyId, (int)$userId);
            }
            foreach ($entries as $entry) {
                $title = ($entry->type === 'shift' && !empty($entry->shift_name))
                          ? $entry->shift_name : ucfirst($entry->type);
                $color = $entry->color ?? '#3788d8';
                $calendarEvents[] = [
                    'title' => $title,
                    'start' => $date . ($entry->start_time ? 'T' . $entry->start_time : ''),
                    'end'   => $date . ($entry->end_time   ? 'T' . $entry->end_time   : ''),
                    'color' => $color,
                ];
            }
        }
        foreach ($requests as $req) {
            $calendarEvents[] = [
                'title'  => $req->type_name,
                'start'  => $req->start_date,
                'end'    => $req->end_date ? date('Y-m-d', strtotime($req->end_date . ' +1 day')) : null,
                'color'  => $req->color,
                'allDay' => true,
            ];
        }

        // Lista plana para cards mobile
        $scheduleList = [];
        foreach ($scheduleByDate as $date => $entries) {
            foreach ($entries as $entry) {
                $entry->date = $date;
                $scheduleList[] = $entry;
            }
        }

        $data = [
            'calendarEvents' => json_encode($calendarEvents),
            'scheduleList'   => $scheduleList,
            'scheduleByDate' => $scheduleByDate,
            'currentMonth'   => $month,
            'user'           => $user,
        ];

        $this->view('employee/mis_horarios', $data);
    }

    public function dashboard(){
        if (function_exists('current_user_uses_casapav_tasks') && current_user_uses_casapav_tasks()) {
            if (function_exists('employee_portal_show_cp_extras_nav') && employee_portal_show_cp_extras_nav()) {
                redirect('cpTask/index');
            }
            redirect('employee/index');
        }
        require_employee_portal_feature('overtime');
        $userId = (int)$_SESSION['user_id'];
        $year = (int)date('Y');
        $month = (int)date('n');

        $all = $this->overtimeModel->getEntriesByUserId($userId);
        $pending = array_filter($all, function ($e) { return $e->status === 'pending'; });
        $history = array_filter($all, function ($e) { return $e->status !== 'pending'; });

        $monthEntries = $this->overtimeModel->getEntriesByUserIdForMonth($userId, $year, $month);
        $monthSummary = $this->overtimeModel->summarizeMonthEntries($monthEntries);

        $feedback = null;
        if (!empty($_SESSION['overtime_feedback'])) {
            $feedback = $_SESSION['overtime_feedback'];
            unset($_SESSION['overtime_feedback']);
        }

        $data = [
            'entries' => array_values($pending),
            'history' => array_values($history),
            'month_entries' => $monthEntries,
            'month_summary' => $monthSummary,
            'month_label' => overtime_month_label_es($year, $month),
            'overtime_feedback' => $feedback,
        ];
        $this->view('employee/dashboard', $data);
    }

    public function profile() {
        if (function_exists('access_portal_feature_allowed') && !access_portal_feature_allowed('profile_edit', true)) {
            $_SESSION['flash_error'] = 'La edición de perfil no está disponible.';
            redirect('employee/index');
        }
        $user = $this->userModel->getUserById($_SESSION['user_id']);
        $companyName = null;
        if ($user && !empty($user->company_id)) {
            $companyName = (new Company())->getNameById($user->company_id);
        }
        $this->view('employee/profile', ['user' => $user, 'company_name' => $companyName]);
    }

    public function updateProfile() {
        if (function_exists('access_portal_feature_allowed') && !access_portal_feature_allowed('profile_edit', true)) {
            $_SESSION['flash_error'] = 'La edición de perfil no está disponible.';
            redirect('employee/index');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('employee/profile');
        }
        csrf_verify();
        $userId = (int)$_SESSION['user_id'];
        $data = [];
        $password = postString('password');
        $password2 = postString('password_confirm');
        if ($password !== '') {
            if (strlen($password) < 6) {
                $_SESSION['flash_error'] = 'La contraseña debe tener al menos 6 caracteres.';
                redirect('employee/profile');
            }
            if ($password !== $password2) {
                $_SESSION['flash_error'] = 'Las contraseñas no coinciden.';
                redirect('employee/profile');
            }
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        if (!empty($_FILES['profile_picture']['name']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
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
                $filename = 'user_' . $userId . '_' . time() . '.' . $valid['ext'];
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $avatarDir . $filename)) {
                    $data['profile_picture'] = $filename;
                    $_SESSION['user_profile_picture'] = $filename;
                }
            }
        }
        if (empty($data)) {
            $_SESSION['flash_error'] = 'No hay cambios para guardar.';
            redirect('employee/profile');
        }
        if ($this->userModel->updateEmployeeProfile($userId, $data)) {
            $_SESSION['flash_success'] = 'Perfil actualizado.';
        } else {
            $_SESSION['flash_error'] = 'No se pudo actualizar el perfil.';
        }
        redirect('employee/profile');
    }

    public function proposeSwap() {
        $scheduleId = isset($_GET['proposer_schedule_id']) ? (int)$_GET['proposer_schedule_id'] : 0;
        $qs = 'request/index?tab=swap';
        if ($scheduleId > 0) {
            $qs .= '&schedule_id=' . $scheduleId;
        }
        redirect($qs);
    }

    public function add(){
        require_employee_portal_feature('overtime');
        if (function_exists('current_user_uses_casapav_tasks') && current_user_uses_casapav_tasks()) {
            $_SESSION['flash_error'] = 'En Casa Paviotti las extras se cargan por tarea, no por horas.';
            redirect('cpTask/index');
        }
        if($_SERVER['REQUEST_METHOD'] == 'POST'){
            csrf_verify();

            $data = [
                'user_id'    => $_SESSION['user_id'],
                'date'       => postString('date'),
                'start_time' => postString('start_time'),
                'end_time'   => postString('end_time'),
                'is_holiday' => (int)($_POST['is_holiday'] ?? 0),
                'reason'     => postString('reason'),
            ];

            if(empty($data['date']) || empty($data['start_time']) || empty($data['end_time']) || empty($data['reason'])){
                $_SESSION['overtime_flash_error'] = 'Completá fecha, horario y motivo para guardar.';
                redirect('employee/dashboard');
            } elseif ($this->overtimeModel->checkForDuplicateEntry($data)){
                $_SESSION['overtime_flash_error'] = 'duplicate';
                redirect('employee/dashboard');
            } else {
                $entryId = $this->overtimeModel->addEntry($data);
                if ($entryId) {
                    $saved = $this->overtimeModel->getEntryById($entryId);
                    $year = (int)date('Y', strtotime($data['date']));
                    $month = (int)date('n', strtotime($data['date']));
                    $monthEntries = $this->overtimeModel->getEntriesByUserIdForMonth($data['user_id'], $year, $month);
                    $monthSummary = $this->overtimeModel->summarizeMonthEntries($monthEntries);

                    $monthForJs = [];
                    foreach ($monthEntries as $e) {
                        $monthForJs[] = overtime_format_entry_for_js($e);
                    }

                    $_SESSION['overtime_feedback'] = [
                        'saved' => $saved ? overtime_format_entry_for_js($saved) : null,
                        'month_label' => overtime_month_label_es($year, $month),
                        'month_summary' => $monthSummary,
                        'month_entries' => $monthForJs,
                    ];
                    redirect('employee/dashboard');
                } else {
                    $_SESSION['overtime_flash_error'] = 'No se pudieron guardar las horas. Intentá de nuevo.';
                    redirect('employee/dashboard');
                }
            }
        } else {
            redirect('employee/dashboard');
        }
    }

    public function notifications() {
        if (!$this->userNotificationModel) {
            redirect('employee/index');
        }
        $userId = (int)$_SESSION['user_id'];
        $items = $this->userNotificationModel->getAllForUser($userId);
        if (function_exists('employee_portal_filter_notifications')) {
            $items = employee_portal_filter_notifications($items);
        }
        $this->view('employee/notifications', [
            'items' => $items,
        ]);
    }

    public function markNotificationRead($id) {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false]);
            return;
        }
        csrf_verify();
        if (!$this->userNotificationModel) {
            echo json_encode(['ok' => false]);
            return;
        }
        $ok = $this->userNotificationModel->markRead((int)$id, (int)$_SESSION['user_id']);
        $unread = function_exists('employee_portal_count_visible_unread')
            ? employee_portal_count_visible_unread((int)$_SESSION['user_id'])
            : $this->userNotificationModel->countUnread((int)$_SESSION['user_id']);
        echo json_encode(['ok' => $ok, 'unread' => $unread]);
    }

    public function markAllNotificationsRead() {
        csrf_verify();
        if ($this->userNotificationModel) {
            $this->userNotificationModel->markAllRead((int)$_SESSION['user_id']);
            $_SESSION['flash_success'] = 'Notificaciones marcadas como leídas.';
        }
        redirect('employee/notifications');
    }

    public function pendingAnnouncements() {
        header('Content-Type: application/json; charset=utf-8');
        if (!$this->announcementDisplay) {
            echo json_encode(['items' => []]);
            return;
        }
        $userId = (int)$_SESSION['user_id'];
        $items = [];
        foreach ($this->announcementDisplay->getPendingForUser($userId) as $a) {
            $row = [
                'id' => (int)$a->id,
                'title' => notification_apply_placeholders($a->title, $userId),
                'body' => notification_apply_placeholders($a->body, $userId),
                'image_url' => !empty($a->image_path) ? announcement_image_stream_url((int)$a->id) : '',
                'video_url' => (!empty($a->video_path) && function_exists('announcement_video_stream_url')) ? announcement_video_stream_url((int)$a->id) : '',
                'link_url' => $a->link_url ?? '',
                'link_label' => $a->link_label ?? 'Ver más',
            ];
            if (function_exists('employee_portal_announcement_visible')
                && !employee_portal_announcement_visible($row)) {
                continue;
            }
            $items[] = $row;
        }
        echo json_encode(['items' => $items]);
    }

    public function recordAnnouncementShown($id) {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false]);
            return;
        }
        csrf_verify();
        if (!$this->announcementDisplay) {
            echo json_encode(['ok' => false]);
            return;
        }
        $this->announcementDisplay->recordShown((int)$id, (int)$_SESSION['user_id']);
        echo json_encode(['ok' => true]);
    }

    public function dismissAnnouncement($id) {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false]);
            return;
        }
        csrf_verify();
        if (!$this->announcementDisplay) {
            echo json_encode(['ok' => false]);
            return;
        }
        $uid = (int)$_SESSION['user_id'];
        $aid = (int)$id;
        $this->announcementDisplay->recordShown($aid, $uid);
        $ok = $this->announcementDisplay->dismiss($aid, $uid);
        echo json_encode(['ok' => $ok]);
    }

    public function payStubs() {
        require_employee_portal_feature('pay_stubs');
        if (!$this->payStubModel) {
            redirect('employee/index');
        }
        $userId = (int)$_SESSION['user_id'];
        $stubs = $this->payStubModel->getByUserId($userId);
        $oldestPending = $this->payStubModel->getOldestPendingId($userId);
        $this->view('employee/pay_stubs', [
            'stubs' => $stubs,
            'oldest_pending_id' => $oldestPending,
        ]);
    }

    public function payStubSign($id = 0) {
        require_employee_portal_feature('pay_stubs');
        if (!$this->payStubModel) {
            redirect('employee/payStubs');
        }
        $id = (int)$id;
        $userId = (int)$_SESSION['user_id'];
        if (!$this->payStubModel->canUserAccess($userId, $id)) {
            $_SESSION['flash_error'] = 'Debés firmar el recibo anterior primero.';
            redirect('employee/payStubs');
        }
        $stub = $this->payStubModel->getById($id);
        if (!$stub || (int)$stub->user_id !== $userId) {
            redirect('employee/payStubs');
        }
        $this->view('employee/pay_stub_sign', [
            'stub' => $stub,
            'file_url' => pay_stub_stream_url($id),
            'download_url' => URLROOT . '/employee/downloadPayStub/' . $id,
            'signature_url' => !empty($stub->signature_path) ? pay_stub_signature_stream_url($id) : '',
            'is_pdf' => $stub->file_type === 'pdf',
        ]);
    }

    public function streamPayStub($id = 0) {
        $this->sendPayStubFile((int)$id, true);
    }

    public function streamPayStubSignature($id = 0) {
        require_employee_portal_feature('pay_stubs');
        if (!$this->payStubModel) {
            http_response_code(404);
            exit;
        }
        $id = (int)$id;
        $userId = (int)$_SESSION['user_id'];
        $stub = $this->payStubModel->getById($id);
        if (!$stub || (int)$stub->user_id !== $userId || empty($stub->signature_path)) {
            http_response_code(404);
            exit;
        }
        $absolute = pay_stub_upload_absolute_path($stub->signature_path);
        if (!$absolute) {
            http_response_code(404);
            exit;
        }
        $mime = mime_content_type($absolute) ?: 'image/png';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline');
        header('Content-Length: ' . filesize($absolute));
        header('Cache-Control: private, no-cache');
        readfile($absolute);
        exit;
    }

    public function streamAnnouncementVideo($id = 0) {
        if (!$this->announcementDisplay) {
            http_response_code(404);
            exit;
        }
        $id = (int)$id;
        $userId = (int)$_SESSION['user_id'];
        if (!$this->announcementDisplay->userCanAccess($id, $userId)) {
            http_response_code(403);
            exit;
        }
        $row = (new Announcement())->getById($id);
        if (!$row || empty($row->video_path)) {
            http_response_code(404);
            exit;
        }
        protected_upload_send($row->video_path, true, basename((string)$row->video_path));
    }

    public function streamAnnouncementImage($id = 0) {
        if (!$this->announcementDisplay) {
            http_response_code(404);
            exit;
        }
        $id = (int)$id;
        $userId = (int)$_SESSION['user_id'];
        if (!$this->announcementDisplay->userCanAccess($id, $userId)) {
            http_response_code(403);
            exit;
        }
        $row = (new Announcement())->getById($id);
        if (!$row || empty($row->image_path)) {
            http_response_code(404);
            exit;
        }
        protected_upload_send($row->image_path, true, basename((string)$row->image_path));
    }

    public function downloadPayStub($id = 0) {
        $this->sendPayStubFile((int)$id, false);
    }

    private function sendPayStubFile($id, $inline) {
        require_employee_portal_feature('pay_stubs');
        if (!$this->payStubModel) {
            redirect('employee/payStubs');
        }
        $id = (int)$id;
        $userId = (int)$_SESSION['user_id'];
        $stub = $this->payStubModel->getById($id);
        if (!$stub || (int)$stub->user_id !== $userId) {
            $_SESSION['flash_error'] = 'Recibo no encontrado.';
            redirect('employee/payStubs');
        }
        if (!$inline && $stub->status !== 'signed' && !$this->payStubModel->canUserAccess($userId, $id)) {
            $_SESSION['flash_error'] = 'No podés descargar este recibo aún.';
            redirect('employee/payStubs');
        }
        if ($inline && $stub->status !== 'signed' && !$this->payStubModel->canUserAccess($userId, $id)) {
            http_response_code(403);
            exit;
        }
        $absolute = pay_stub_upload_absolute_path($stub->file_path);
        if (!$absolute) {
            if ($inline) {
                http_response_code(404);
                exit;
            }
            $_SESSION['flash_error'] = 'Archivo no disponible.';
            redirect('employee/payStubSign/' . $id);
        }
        $mime = $stub->file_type === 'pdf' ? 'application/pdf' : (mime_content_type($absolute) ?: 'application/octet-stream');
        $filename = pay_stub_download_filename($stub->period, $stub->full_name ?? 'empleado', $stub->file_path);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . filesize($absolute));
        header('Cache-Control: private, no-cache');
        readfile($absolute);
        exit;
    }

    public function signPayStub($id = 0) {
        require_employee_portal_feature('pay_stubs');
        csrf_verify();
        if (!$this->payStubModel) {
            redirect('employee/payStubs');
        }
        $id = (int)$id;
        $userId = (int)$_SESSION['user_id'];
        if (!$this->payStubModel->canUserAccess($userId, $id)) {
            $_SESSION['flash_error'] = 'No podés firmar este recibo aún.';
            redirect('employee/payStubs');
        }
        $dataUrl = $_POST['signature_data'] ?? '';
        if (!preg_match('#^data:image/png;base64,#', $dataUrl)) {
            $_SESSION['flash_error'] = 'Firma inválida.';
            redirect('employee/payStubSign/' . $id);
        }
        $raw = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1));
        if ($raw === false || strlen($raw) > 500000) {
            $_SESSION['flash_error'] = 'No se pudo procesar la firma.';
            redirect('employee/payStubSign/' . $id);
        }
        uploads_ensure_private_directory('pay_stub_signatures');
        $dir = APPROOT . '/../public/uploads/pay_stub_signatures';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $_SESSION['flash_error'] = 'Error al guardar la firma.';
            redirect('employee/payStubSign/' . $id);
        }
        $filename = 'sig-u' . $userId . '-ps' . $id . '-' . time() . '.png';
        $relative = 'pay_stub_signatures/' . $filename;
        if (!file_put_contents($dir . '/' . $filename, $raw)) {
            $_SESSION['flash_error'] = 'Error al guardar la firma.';
            redirect('employee/payStubSign/' . $id);
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($this->payStubModel->sign($id, $userId, $relative, $ip)) {
            if ($this->userNotificationModel) {
                $this->userNotificationModel->markReadByReference($userId, 'pay_stub', $id);
            }
            $_SESSION['flash_success'] = 'Recibo firmado correctamente.';
        } else {
            $_SESSION['flash_error'] = 'No se pudo registrar la firma.';
        }
        redirect('employee/payStubs');
    }

    /**
     * Portal empleado: roadmap del mes (misma lógica que calendario admin, solo lectura).
     */
    public function miMes() {
        require_employee_portal_feature('mi_mes');
        $userId = (int)$_SESSION['user_id'];
        $user = $this->userModel->getUserById($userId);
        $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
        $companyId = (int)($user->company_id ?? 0);
        $calendar = null;
        $monthStats = null;
        $vacationPending = null;
        if ($companyId > 0) {
            try {
                $calendar = (new CalendarMonthService())->buildMonth($companyId, $userId, $month);
                if (!empty($calendar['days_list'])) {
                    $monthStats = calendarComputeMonthStats($calendar['days_list']);
                }
            } catch (Exception $e) {
                $_SESSION['flash_error'] = 'No se pudo cargar el calendario del mes.';
            }
            if (employee_portal_can('vacation_balance') && function_exists('vacation_module_ready') && vacation_module_ready()) {
                $vacationPending = (new VacationBalance())->getTotalPending($userId);
            }
        }
        $this->view('employee/mi_mes', [
            'user' => $user,
            'month' => $month,
            'calendar' => $calendar,
            'month_stats' => $monthStats,
            'vacation_pending' => $vacationPending,
        ]);
    }

    private function view($view, $data = []){
        if(file_exists('../app/views/' . $view . '.php')){
            require_once '../app/views/' . $view . '.php';
        } else {
            die('Error: La vista no existe: ' . $view);
        }
    }
}
?>
