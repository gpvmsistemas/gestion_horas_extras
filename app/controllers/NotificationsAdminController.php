<?php

class NotificationsAdminController {
    private $announcementModel;
    private $broadcastModel;
    private $payStubModel;
    private $targetService;
    private $mailService;
    private $userModel;
    private $companyModel;

    public function __construct() {
        if (!hasRole('admin')) {
            redirect('login');
        }
        if (!notifications_is_ready()) {
            $_SESSION['flash_error'] = 'Ejecutá migration_notifications_paystubs.sql en MySQL (ver MIGRATIONS.md #20).';
            redirect('admin/dashboard');
        }
        $this->announcementModel = new Announcement();
        $this->broadcastModel = new NotificationBroadcast();
        $this->payStubModel = new PayStub();
        $this->targetService = new NotificationTargetService();
        $this->mailService = new MailService();
        $this->userModel = new User();
        $this->companyModel = new Company();
    }

    public function index() {
        $this->view('admin/notifications/hub', []);
    }

    /** Redirige a Configuración → Correo (evita duplicar la pantalla SMTP). */
    public function mailConfig() {
        if (function_exists('system_settings_ready') && system_settings_ready() && isAdmin()) {
            if (!system_config_unlocked()) {
                $_SESSION['flash_error'] = 'Ingresá la clave de configuración para editar el correo.';
                redirect('systemConfig/unlock');
            }
            redirect('systemConfig?tab=mail');
        }
        $_SESSION['flash_error'] = 'Ejecutá migration_system_settings.sql para configurar el correo en Configuración.';
        redirect('notificationsAdmin');
    }

    // --- Avisos modales ---
    public function announcements() {
        $this->view('admin/notifications/announcements', [
            'items' => $this->announcementModel->getAll(),
        ]);
    }

    public function announcementForm($id = 0) {
        $id = (int)$id;
        $item = $id ? $this->announcementModel->getById($id) : null;
        if ($id && !$item) {
            redirect('notificationsAdmin/announcements');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $data = $this->parseAnnouncementPost();
            if ($data === false) {
                redirect('notificationsAdmin/announcementForm/' . $id);
            }
            if (empty($data['target_all']) && empty(notification_announcement_targets_from_post($_POST))) {
                $_SESSION['flash_error'] = 'Seleccioná destinatarios en la vista previa.';
                redirect('notificationsAdmin/announcementForm/' . $id);
            }
            $targets = notification_announcement_targets_from_post($_POST);
            $imagePath = $item->image_path ?? null;
            $uploaded = $this->uploadAnnouncementImage();
            if ($uploaded === false) {
                redirect('notificationsAdmin/announcementForm/' . $id);
            }
            if ($uploaded) {
                $imagePath = $uploaded;
            }
            $data['image_path'] = $imagePath;

            $videoPath = $item->video_path ?? null;
            $uploadedVideo = $this->uploadAnnouncementVideo();
            if ($uploadedVideo === false) {
                redirect('notificationsAdmin/announcementForm/' . $id);
            }
            if ($uploadedVideo) {
                if (!empty($videoPath)) {
                    $oldVideo = pay_stub_upload_absolute_path($videoPath);
                    if ($oldVideo && is_file($oldVideo)) {
                        @unlink($oldVideo);
                    }
                }
                $videoPath = $uploadedVideo;
            }
            if (!empty($_POST['remove_video']) && empty($uploadedVideo) && !empty($videoPath)) {
                $oldVideo = pay_stub_upload_absolute_path($videoPath);
                if ($oldVideo && is_file($oldVideo)) {
                    @unlink($oldVideo);
                }
                $videoPath = null;
            }
            $data['video_path'] = $videoPath;

            if ($id) {
                $this->announcementModel->update($id, $data, $targets);
                $announcementId = $id;
                $_SESSION['flash_success'] = 'Aviso actualizado.';
            } else {
                $announcementId = $this->announcementModel->create($data, $targets, $_SESSION['user_id']);
                if (!$announcementId) {
                    $_SESSION['flash_error'] = 'No se pudo crear el aviso.';
                    redirect('notificationsAdmin/announcements');
                }
                $_SESSION['flash_success'] = 'Aviso creado.';
            }
            if (!empty($data['send_email'])) {
                $this->dispatchAnnouncementEmails($announcementId, $data, $_POST);
            }
            redirect('notificationsAdmin/announcements');
        }

        $selected = ['target_all' => false, 'company_ids' => [], 'area_ids' => [], 'user_ids' => []];
        if ($item) {
            $selected['target_all'] = (int)$item->target_all === 1;
            foreach ($this->announcementModel->getTargets($id) as $t) {
                if ($t->target_type === 'company') {
                    $selected['company_ids'][] = (int)$t->target_id;
                } elseif ($t->target_type === 'area') {
                    $selected['area_ids'][] = (int)$t->target_id;
                } else {
                    $selected['user_ids'][] = (int)$t->target_id;
                }
            }
        }
        $targeting = notification_admin_targeting_data();
        $this->view('admin/notifications/announcement_form', [
            'item' => $item,
            'selected' => $selected,
            'companies' => $targeting['companies'],
            'areas' => $targeting['areas'],
            'users' => $targeting['users'],
        ]);
    }

    public function deleteAnnouncement($id) {
        csrf_verify();
        $this->announcementModel->delete((int)$id);
        $_SESSION['flash_success'] = 'Aviso eliminado.';
        redirect('notificationsAdmin/announcements');
    }

    // --- Notificaciones campana ---
    public function broadcasts() {
        $this->view('admin/notifications/broadcasts', [
            'items' => $this->broadcastModel->getAll(),
        ]);
    }

    public function deleteBroadcast($id) {
        csrf_verify();
        $id = (int)$id;
        if ($this->broadcastModel->deleteBroadcast($id)) {
            $_SESSION['flash_success'] = 'Notificación eliminada de la campana de los empleados.';
        } else {
            $_SESSION['flash_error'] = 'No se pudo eliminar (solo envíos manuales).';
        }
        redirect('notificationsAdmin/broadcasts');
    }

    public function broadcastForm($id = 0) {
        if ($id) {
            redirect('notificationsAdmin/broadcasts');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $data = $this->parseBroadcastPost();
            if ($data === false) {
                redirect('notificationsAdmin/broadcastForm');
            }
            $targetOpts = $this->targetService->targetsFromPost($_POST);
            $targetRows = notification_targets_from_post($_POST);
            $recipientIds = notification_resolve_recipient_ids_from_post($_POST);
            if (empty($recipientIds)) {
                $_SESSION['flash_error'] = 'Seleccioná al menos un empleado en la vista previa (Actualizar vista previa). '
                    . 'Solo reciben notificación usuarios con rol empleado activo; si filtrás por área, deben tener esa área asignada en su perfil.';
                redirect('notificationsAdmin/broadcastForm');
            }
            $data['target_all'] = $targetOpts['target_all'];
            $result = $this->broadcastModel->createAndDispatch($data, $targetRows, $recipientIds, $_SESSION['user_id']);
            if (!$result || empty($result['id'])) {
                $_SESSION['flash_error'] = 'No se pudo crear la notificación.';
                redirect('notificationsAdmin/broadcastForm');
            }
            if (!empty($data['send_email'])) {
                $this->dispatchBroadcastEmails($data, $recipientIds);
            }
            $msg = 'Notificación enviada a ' . (int)$result['sent'] . ' empleado(s).';
            if (!empty($result['skipped'])) {
                $msg .= ' (' . (int)$result['skipped'] . ' omitidos por duplicado).';
            }
            $_SESSION['flash_success'] = $msg;
            redirect('notificationsAdmin/broadcasts');
        }
        $targeting = notification_admin_targeting_data();
        $this->view('admin/notifications/broadcast_form', [
            'companies' => $targeting['companies'],
            'areas' => $targeting['areas'],
            'users' => $targeting['users'],
            'picker_users' => $targeting['picker_users'],
        ]);
    }

    // --- Recibos ---
    // Suite P&M: la organización Moderna no usa el módulo de recibos.
    private function requirePayStubsOrg() {
        if (function_exists('org_hides_pay_stubs') && org_hides_pay_stubs()) {
            $_SESSION['flash_error'] = 'La organización Moderna no utiliza el módulo de recibos de sueldo.';
            redirect('notificationsAdmin/index');
        }
    }

    public function payStubs() {
        $this->requirePayStubsOrg();
        ensureAdminCompanySession();
        $companyId = requireAdminCompany('admin/dashboard');
        $search = trim($_GET['q'] ?? '');
        $this->view('admin/notifications/pay_stubs', [
            'stubs' => $this->payStubModel->getForAdminList($companyId, $search),
            'companies' => $this->companyModel->getAllCompanies(),
            'company_id' => $companyId,
            'search' => $search,
        ]);
    }

    public function uploadPayStub() {
        $this->requirePayStubsOrg();
        csrf_verify();
        $userId = (int)($_POST['user_id'] ?? 0);
        requireUserInAdminCompany($userId, 'notificationsAdmin/payStubs');
        $periodInput = trim($_POST['period'] ?? '');
        $sendEmail = !empty($_POST['send_email']);
        $user = $this->userModel->getUserById($userId);
        if (!$user || $user->role !== 'empleado') {
            $_SESSION['flash_error'] = 'Empleado no válido.';
            redirect('notificationsAdmin/payStubs');
        }
        $period = pay_stub_period_normalize($periodInput);
        if (!$period) {
            $_SESSION['flash_error'] = 'Período inválido. Usá MM-AAAA (ej. 06-2026).';
            redirect('notificationsAdmin/payStubs');
        }
        if ($this->payStubModel->periodExists($userId, $period)) {
            $_SESSION['flash_error'] = 'Ya existe un recibo para ese período.';
            redirect('notificationsAdmin/payStubs');
        }
        $upload = $this->uploadPayStubFile($userId);
        if ($upload === false) {
            redirect('notificationsAdmin/payStubs');
        }
        $adminNote = trim($_POST['admin_note'] ?? '');
        $stubId = $this->payStubModel->create([
            'user_id' => $userId,
            'company_id' => (int)$user->company_id,
            'period' => $period,
            'file_path' => $upload['path'],
            'file_type' => $upload['type'],
            'admin_note' => $adminNote,
            'uploaded_by' => $_SESSION['user_id'],
        ]);
        if (!$stubId) {
            $_SESSION['flash_error'] = 'No se pudo guardar el recibo.';
            redirect('notificationsAdmin/payStubs');
        }
        $titleTpl = pay_stub_period_display($period) . ' disponible';
        $bodyTpl = 'Hola <nombre>, tenés un recibo de haberes pendiente de firma.';
        $link = URLROOT . '/employee/payStubSign/' . $stubId;
        $notif = new UserNotification();
        if (!$notif->existsForUser($userId, 'pay_stub', $stubId)) {
            $notif->create([
                'user_id' => $userId,
                'title' => notification_apply_placeholders($titleTpl, $userId),
                'body' => notification_apply_placeholders($bodyTpl, $userId),
                'link_url' => $link,
                'type' => 'pay_stub',
                'reference_id' => $stubId,
            ]);
        }
        if ($sendEmail) {
            $subject = notification_apply_placeholders($titleTpl, $userId);
            $bodyHtml = '<p>' . htmlspecialchars(notification_apply_placeholders($bodyTpl, $userId)) . '</p>'
                . '<p><a href="' . htmlspecialchars($link) . '">Ver recibo</a></p>';
            $this->mailService->sendToUser($userId, $subject, $bodyHtml);
        }
        $_SESSION['flash_success'] = 'Recibo cargado y notificación enviada.';
        redirect('notificationsAdmin/payStubs');
    }

    private function parseAnnouncementPost() {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            $_SESSION['flash_error'] = 'El título es obligatorio.';
            return false;
        }
        $range = $this->parseDateRange();
        if ($range === false) {
            return false;
        }
        return [
            'title' => $title,
            'body' => $_POST['body'] ?? '',
            'link_url' => $_POST['link_url'] ?? '',
            'link_label' => $_POST['link_label'] ?? '',
            'starts_at' => $range['starts_at'],
            'ends_at' => $range['ends_at'],
            'display_mode' => $_POST['display_mode'] ?? 'once',
            'target_all' => !empty($_POST['target_all']),
            'send_email' => !empty($_POST['send_email']),
            'is_active' => !empty($_POST['is_active']),
        ];
    }

    private function parseBroadcastPost() {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            $_SESSION['flash_error'] = 'El título es obligatorio.';
            return false;
        }
        $range = $this->parseDateRange(true);
        if ($range === false) {
            return false;
        }
        return [
            'title' => $title,
            'body' => $_POST['body'] ?? '',
            'link_url' => $_POST['link_url'] ?? '',
            'type' => 'manual',
            'starts_at' => $range['starts_at'],
            'ends_at' => $range['ends_at'],
            'send_email' => !empty($_POST['send_email']),
        ];
    }

    private function parseDateRange($optional = false) {
        $starts = trim($_POST['starts_at'] ?? '');
        $ends = trim($_POST['ends_at'] ?? '');
        if ($starts === '' && $ends === '' && !empty($_POST['date_preset'])) {
            $preset = $_POST['date_preset'];
            if ($preset === 'week') {
                $starts = date('Y-m-d 00:00:00');
                $ends = date('Y-m-d 23:59:59', strtotime('+6 days'));
            } elseif ($preset === 'month') {
                $starts = date('Y-m-01 00:00:00');
                $ends = date('Y-m-t 23:59:59');
            }
        }
        if ($starts === '' || $ends === '') {
            if ($optional) {
                return ['starts_at' => null, 'ends_at' => null];
            }
            $_SESSION['flash_error'] = 'Indicá fecha desde y hasta (o un preset).';
            return false;
        }
        $startsAt = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $starts)));
        $endsAt = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $ends)));
        if ($startsAt > $endsAt) {
            $_SESSION['flash_error'] = 'La fecha de inicio debe ser anterior a la de fin.';
            return false;
        }
        return ['starts_at' => $startsAt, 'ends_at' => $endsAt];
    }

    private function uploadAnnouncementImage() {
        if (empty($_FILES['image']['name']) || ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $docMimes = uploads_document_mimes();
        $valid = uploads_validate_uploaded_file(
            $_FILES['image'],
            ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            uploads_flat_mimes($docMimes),
            5 * 1024 * 1024
        );
        if (!$valid['ok']) {
            $_SESSION['flash_error'] = $valid['message'];
            return false;
        }
        if (function_exists('uploads_ensure_private_directory')) {
            uploads_ensure_private_directory('announcements');
        }
        $dir = APPROOT . '/../public/uploads/announcements';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $_SESSION['flash_error'] = 'No se pudo crear la carpeta de avisos.';
            return false;
        }
        $filename = 'ann-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $valid['ext'];
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $dir . '/' . $filename)) {
            $_SESSION['flash_error'] = 'No se pudo guardar la imagen.';
            return false;
        }
        return 'announcements/' . $filename;
    }

    /** Video del aviso (mp4/webm, máx. 50MB). null = sin archivo nuevo; false = error. */
    private function uploadAnnouncementVideo() {
        if (empty($_FILES['video']['name']) || ($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $valid = uploads_validate_uploaded_file(
            $_FILES['video'],
            ['mp4', 'webm'],
            ['video/mp4', 'video/webm'],
            50 * 1024 * 1024
        );
        if (!$valid['ok']) {
            $_SESSION['flash_error'] = $valid['message'];
            return false;
        }
        if (function_exists('uploads_ensure_private_directory')) {
            uploads_ensure_private_directory('announcements');
        }
        $dir = APPROOT . '/../public/uploads/announcements';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $_SESSION['flash_error'] = 'No se pudo crear la carpeta de avisos.';
            return false;
        }
        $filename = 'annv-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $valid['ext'];
        if (!move_uploaded_file($_FILES['video']['tmp_name'], $dir . '/' . $filename)) {
            $_SESSION['flash_error'] = 'No se pudo guardar el video.';
            return false;
        }
        return 'announcements/' . $filename;
    }

    private function uploadPayStubFile($userId) {
        if (empty($_FILES['pay_stub_file']['name']) || ($_FILES['pay_stub_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'Seleccioná un archivo PDF o imagen.';
            return false;
        }
        $docMimes = uploads_document_mimes();
        $valid = uploads_validate_uploaded_file(
            $_FILES['pay_stub_file'],
            ['pdf', 'jpg', 'jpeg', 'png'],
            uploads_flat_mimes($docMimes),
            10 * 1024 * 1024
        );
        if (!$valid['ok']) {
            $_SESSION['flash_error'] = $valid['message'];
            return false;
        }
        $isPdf = $valid['ext'] === 'pdf';
        if (function_exists('uploads_ensure_private_directory')) {
            uploads_ensure_private_directory('pay_stubs/' . (int)$userId);
        }
        $dir = APPROOT . '/../public/uploads/pay_stubs/' . (int)$userId;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $_SESSION['flash_error'] = 'No se pudo crear la carpeta de recibos.';
            return false;
        }
        $filename = 'stub-' . time() . '.' . $valid['ext'];
        if (!move_uploaded_file($_FILES['pay_stub_file']['tmp_name'], $dir . '/' . $filename)) {
            $_SESSION['flash_error'] = 'Error al guardar el archivo.';
            return false;
        }
        return [
            'path' => 'pay_stubs/' . (int)$userId . '/' . $filename,
            'type' => $isPdf ? 'pdf' : 'image',
        ];
    }

    private function dispatchAnnouncementEmails($announcementId, array $data, array $post) {
        if (!$this->mailService->isAvailable()) {
            return;
        }
        $userIds = notification_resolve_recipient_ids_from_post($post);
        foreach ($userIds as $uid) {
            $title = notification_apply_placeholders($data['title'], $uid);
            $body = '<h3>' . htmlspecialchars($title) . '</h3>'
                . notification_apply_placeholders($data['body'] ?? '', $uid);
            if (!empty($data['link_url'])) {
                $label = !empty($data['link_label']) ? $data['link_label'] : 'Ver más';
                $body .= '<p><a href="' . htmlspecialchars($data['link_url']) . '">' . htmlspecialchars($label) . '</a></p>';
            }
            $this->mailService->sendToUser($uid, $title, $body);
        }
    }

    private function dispatchBroadcastEmails(array $data, array $userIds) {
        if (!$this->mailService->isAvailable()) {
            return;
        }
        foreach ($userIds as $uid) {
            $title = notification_apply_placeholders($data['title'], $uid);
            $body = '<p>' . nl2br(htmlspecialchars(notification_apply_placeholders($data['body'] ?? '', $uid))) . '</p>';
            if (!empty($data['link_url'])) {
                $body .= '<p><a href="' . htmlspecialchars($data['link_url']) . '">Abrir enlace</a></p>';
            }
            $this->mailService->sendToUser($uid, $title, $body);
        }
    }

    public function streamAnnouncementVideo($id = 0) {
        $id = (int)$id;
        $row = $this->announcementModel->getById($id);
        if (!$row || empty($row->video_path)) {
            http_response_code(404);
            exit;
        }
        protected_upload_send($row->video_path, true, basename((string)$row->video_path));
    }

    public function streamAnnouncementImage($id = 0) {
        $id = (int)$id;
        $row = $this->announcementModel->getById($id);
        if (!$row || empty($row->image_path)) {
            http_response_code(404);
            exit;
        }
        $absolute = pay_stub_upload_absolute_path($row->image_path);
        if (!$absolute || !is_file($absolute)) {
            http_response_code(404);
            exit;
        }
        $mime = mime_content_type($absolute) ?: 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline');
        header('Content-Length: ' . filesize($absolute));
        header('Cache-Control: private, no-cache');
        readfile($absolute);
        exit;
    }

    private function view($view, $data = []) {
        if (file_exists('../app/views/' . $view . '.php')) {
            require_once '../app/views/' . $view . '.php';
        } else {
            die('Vista no encontrada: ' . $view);
        }
    }
}
