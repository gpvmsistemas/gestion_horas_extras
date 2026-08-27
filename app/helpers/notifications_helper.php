<?php

function notifications_is_ready() {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $db = new Database();
        $db->query("SHOW TABLES LIKE 'user_notifications'");
        $ready = (bool)$db->single();
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

function pay_stub_period_label($period) {
    if (!preg_match('/^\d{4}-\d{2}$/', (string)$period)) {
        return $period;
    }
    $parts = explode('-', $period);
    $meses = [
        '01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr',
        '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago',
        '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic',
    ];
    $m = $parts[1];
    return ($meses[$m] ?? $m) . '-' . $parts[0];
}

function pay_stub_period_display($period) {
    return 'Recibo haberes ' . pay_stub_period_label($period);
}

function notification_targets_from_post(array $post) {
    $rows = [];
    if (!empty($post['target_all'])) {
        return $rows;
    }
    foreach ($post['company_ids'] ?? [] as $cid) {
        $cid = (int)$cid;
        if ($cid > 0) {
            $rows[] = ['target_type' => 'company', 'target_id' => $cid];
        }
    }
    foreach ($post['area_ids'] ?? [] as $aid) {
        $aid = (int)$aid;
        if ($aid > 0) {
            $rows[] = ['target_type' => 'area', 'target_id' => $aid];
        }
    }
    foreach (array_unique((array)($post['employee_groups'] ?? [])) as $group) {
        $group = User::normalizeOrganizationGroup($group);
        $rows[] = ['target_type' => 'employee_group', 'target_id' => $group === 'moderna' ? 2 : 1];
    }
    foreach ($post['user_ids'] ?? [] as $uid) {
        $uid = (int)$uid;
        if ($uid > 0) {
            $rows[] = ['target_type' => 'user', 'target_id' => $uid];
        }
    }
    return $rows;
}

/**
 * Destinos para avisos modales: la vista previa manda recipient_ids[] y debe persistirse
 * como target user (el modal no usa resolveUserIds al mostrar).
 */
function notification_announcement_targets_from_post(array $post) {
    if (!empty($post['target_all'])) {
        return [];
    }
    $recipientIds = notification_resolve_recipient_ids_from_post($post);
    if (!empty($recipientIds)) {
        $rows = [];
        foreach ($recipientIds as $uid) {
            $rows[] = ['target_type' => 'user', 'target_id' => (int)$uid];
        }
        return $rows;
    }
    return notification_targets_from_post($post);
}

function notification_admin_targeting_data() {
    $companyModel = new Company();
    $areaModel = new Area();
    $userModel = new User();
    $companies = $companyModel->getAllCompanies();
    $areas = $areaModel->getAll(false);
    $users = $userModel->getActiveEmployeesForNotifications();
    $pickerUsers = $userModel->getUsersForNotificationPicker();
    return ['companies' => $companies, 'areas' => $areas, 'users' => $users, 'picker_users' => $pickerUsers];
}

/**
 * Destinatarios finales: lista marcada en vista previa o filtros de alcance.
 */
function notification_resolve_recipient_ids_from_post(array $post) {
    $svc = new NotificationTargetService();
    $fromPreview = [];
    foreach ($post['recipient_ids'] ?? [] as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $fromPreview[] = $id;
        }
    }
    $fromPreview = $svc->filterRecipientIds($fromPreview);
    if (!empty($fromPreview)) {
        return $fromPreview;
    }
    return $svc->resolveUserIds($svc->targetsFromPost($post));
}

function notification_user_to_target_json($u, $withRole = false) {
    $row = [
        'id' => (int)$u->id,
        'name' => $u->full_name,
        'company_id' => !empty($u->company_id) ? (int)$u->company_id : null,
        'company_name' => $u->company_name ?? 'Sin empresa',
        'area_id' => isset($u->area_id) && $u->area_id !== null && $u->area_id !== '' ? (int)$u->area_id : null,
        'employee_group' => User::normalizeOrganizationGroup($u->employee_group ?? 'paviotti'),
        'area_name' => !empty($u->area_name) ? $u->area_name : 'Sin área',
    ];
    if ($withRole) {
        $row['role'] = $u->role ?? 'empleado';
    }
    return $row;
}

function notification_targeting_json($data) {
    $users = [];
    foreach ($data['users'] as $u) {
        $users[] = notification_user_to_target_json($u);
    }
    $pickerUsers = [];
    foreach ($data['picker_users'] ?? [] as $u) {
        $pickerUsers[] = notification_user_to_target_json($u, true);
    }
    $areas = [];
    foreach ($data['areas'] as $a) {
        $areas[] = [
            'id' => (int)$a->id,
            'name' => $a->name,
            'company_id' => ($a->company_id === null || $a->company_id === '') ? null : (int)$a->company_id,
            'scope' => Area::scopeLabel($a),
        ];
    }
    $companies = [];
    foreach ($data['companies'] as $c) {
        $companies[] = ['id' => (int)$c->id, 'name' => $c->name];
    }
    return [
        'users' => $users,
        'picker_users' => $pickerUsers,
        'areas' => $areas,
        'companies' => $companies,
    ];
}

/** @deprecated Solo avatares públicos; recibos/avisos usan rutas autenticadas. */
function notification_upload_url($relativePath) {
    if (empty($relativePath)) {
        return '';
    }
    return URLROOT . '/uploads/' . ltrim(str_replace('\\', '/', $relativePath), '/');
}

function pay_stub_stream_url($stubId) {
    return URLROOT . '/employee/streamPayStub/' . (int)$stubId;
}

function pay_stub_signature_stream_url($stubId) {
    return URLROOT . '/employee/streamPayStubSignature/' . (int)$stubId;
}

function announcement_image_stream_url($announcementId, $forAdmin = false) {
    $prefix = $forAdmin ? '/notificationsAdmin/streamAnnouncementImage/' : '/employee/streamAnnouncementImage/';
    return URLROOT . $prefix . (int)$announcementId;
}

function announcement_video_stream_url($announcementId, $forAdmin = false) {
    $prefix = $forAdmin ? '/notificationsAdmin/streamAnnouncementVideo/' : '/employee/streamAnnouncementVideo/';
    return URLROOT . $prefix . (int)$announcementId;
}

function pay_stub_upload_absolute_path($relativePath) {
    if (function_exists('protected_upload_absolute_path')) {
        return protected_upload_absolute_path($relativePath);
    }
    if (empty($relativePath)) {
        return null;
    }
    $rel = ltrim(str_replace('\\', '/', (string)$relativePath), '/');
    if ($rel === '' || strpos($rel, '..') !== false) {
        return null;
    }
    $path = APPROOT . '/../public/uploads/' . $rel;
    return is_file($path) ? $path : null;
}

function pay_stub_download_filename($period, $fullName, $filePath) {
    $slug = preg_replace('/[^a-z0-9]+/i', '-', trim((string)$fullName));
    $slug = trim($slug, '-') ?: 'empleado';
    $ext = strtolower(pathinfo((string)$filePath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        $ext = 'pdf';
    }
    return 'recibo-' . $period . '-' . $slug . '.' . $ext;
}

function pay_stub_period_normalize($input) {
    $input = trim((string)$input);
    if (preg_match('/^(\d{2})-(\d{4})$/', $input, $m)) {
        return $m[2] . '-' . $m[1];
    }
    if (preg_match('/^(\d{4})-(\d{2})$/', $input)) {
        return $input;
    }
    return null;
}

function notification_placeholders_catalog() {
    return [
        '<nombre>' => 'Primer nombre del empleado',
        '<nombre_completo>' => 'Nombre y apellido',
        '<empresa>' => 'Empresa',
        '<area>' => 'Área',
        '<usuario>' => 'Usuario de acceso',
        '<email>' => 'Correo electrónico',
    ];
}

/**
 * Contexto del empleado para reemplazar variables en título/cuerpo.
 */
function notification_user_context($userId) {
    static $cache = [];
    $userId = (int)$userId;
    if (isset($cache[$userId])) {
        return $cache[$userId];
    }
    $user = (new User())->getUserById($userId);
    if (!$user) {
        return [];
    }
    $companyName = '';
    if (!empty($user->company_id)) {
        $co = (new Company())->getById((int)$user->company_id);
        $companyName = $co ? $co->name : '';
    }
    $areaName = '';
    if (!empty($user->area_id) && class_exists('Area')) {
        $area = (new Area())->getById((int)$user->area_id);
        $areaName = $area ? $area->name : '';
    }
    $fullName = trim((string)($user->full_name ?? ''));
    $firstName = $fullName;
    if ($fullName !== '') {
        $parts = preg_split('/\s+/', $fullName);
        $firstName = $parts[0] ?? $fullName;
    }
    $cache[$userId] = [
        '<nombre>' => $firstName,
        '<nombre_completo>' => $fullName,
        '<empresa>' => $companyName,
        '<area>' => $areaName,
        '<usuario>' => (string)($user->username ?? ''),
        '<email>' => (string)($user->email ?? ''),
    ];
    return $cache[$userId];
}

/**
 * Reemplaza &lt;nombre&gt;, &lt;empresa&gt;, etc. (sin distinguir mayúsculas).
 */
function notification_apply_placeholders($text, $userId) {
    if ($text === '' || $text === null) {
        return (string)$text;
    }
    $ctx = notification_user_context($userId);
    if (empty($ctx)) {
        return (string)$text;
    }
    $out = (string)$text;
    foreach ($ctx as $tag => $value) {
        $pattern = '/' . preg_quote($tag, '/') . '/iu';
        $out = preg_replace($pattern, $value, $out);
    }
    return $out;
}

function notify_course_published($courseId) {
    if (!notifications_is_ready() || !learning_is_ready()) {
        return;
    }
    $course = (new Course())->getById((int)$courseId);
    if (!$course || !(int)$course->is_published) {
        return;
    }
    $svc = new LearningAssignmentService();
    $userIds = $svc->getUserIdsForCourse((int)$courseId);
    if (empty($userIds)) {
        return;
    }
    $titleTpl = 'Nuevo curso: ' . $course->title;
    $bodyTpl = 'Hola <nombre>, hay un curso nuevo disponible en Aprendizaje.';
    $link = URLROOT . '/training/course/' . (int)$courseId;
    $notif = new UserNotification();
    $sent = 0;
    foreach ($userIds as $uid) {
        if ($notif->existsForUser($uid, 'course', (int)$courseId)) {
            continue;
        }
        if ($notif->create([
            'user_id' => $uid,
            'title' => notification_apply_placeholders($titleTpl, $uid),
            'body' => notification_apply_placeholders($bodyTpl, $uid),
            'link_url' => $link,
            'type' => 'course',
            'reference_id' => (int)$courseId,
        ])) {
            $sent++;
        }
    }
    return $sent;
}

