<?php

/** Bloquea acceso HTTP directo a carpetas sensibles bajo public/uploads/. */

function uploads_deny_direct_htaccess_contents() {
    return <<<'HTACCESS'
# Sin acceso directo: usar controladores de descarga/visualización autenticados.
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
HTACCESS;
}

function uploads_ensure_private_directory($relativePath) {
    $rel = trim(str_replace('\\', '/', (string)$relativePath), '/');
    if ($rel === '' || strpos($rel, '..') !== false) {
        return false;
    }
    $dir = dirname(APPROOT) . '/public/uploads/' . $rel;
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) {
            return false;
        }
    }
    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, uploads_deny_direct_htaccess_contents());
    }
    return true;
}

function uploads_protect_sensitive_paths() {
    foreach ([
        'pay_stubs',
        'pay_stub_signatures',
        'request_certificates',
        'justifications',
        'courses',
        'announcements',
        'employee_incidents',
    ] as $path) {
        uploads_ensure_private_directory($path);
    }
}

/** Ruta absoluta a un archivo bajo public/uploads/ (sin ..). */
function protected_upload_absolute_path($relativePath) {
    $rel = ltrim(str_replace('\\', '/', (string)$relativePath), '/');
    if ($rel === '' || strpos($rel, '..') !== false) {
        return null;
    }
    $abs = dirname(APPROOT) . '/public/uploads/' . $rel;
    return is_file($abs) ? $abs : null;
}

/** Envía un archivo de uploads con cabeceras adecuadas y termina la ejecución. */
function protected_upload_send($relativePath, $inline = true, $downloadName = null) {
    $abs = protected_upload_absolute_path($relativePath);
    if (!$abs) {
        http_response_code(404);
        exit;
    }
    $mime = mime_content_type($abs) ?: 'application/octet-stream';
    $filename = $downloadName ?: basename($abs);
    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $filename) . '"');
    header('Content-Length: ' . (string)filesize($abs));
    header('Cache-Control: private, no-cache');
    header('X-Content-Type-Options: nosniff');
    readfile($abs);
    exit;
}

/**
 * Valida un archivo subido por extensión, MIME real y tamaño.
 * @return array{ok:bool,ext?:string,message?:string}
 */
function uploads_validate_uploaded_file(array $file, array $allowedExtensions, array $allowedMimes, $maxBytes = 5242880) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'No se pudo subir el archivo.'];
    }
    if (($file['size'] ?? 0) > (int)$maxBytes) {
        return ['ok' => false, 'message' => 'El archivo supera el tamaño máximo permitido.'];
    }
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, $allowedExtensions, true)) {
        return ['ok' => false, 'message' => 'Formato de archivo no permitido.'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    if ($mime === '' || !in_array($mime, $allowedMimes, true)) {
        return ['ok' => false, 'message' => 'Tipo de archivo no válido.'];
    }
    return ['ok' => true, 'ext' => $ext];
}

function uploads_document_mimes() {
    return [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
    ];
}

function uploads_avatar_extensions() {
    return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
}

function uploads_avatar_mimes() {
    $groups = uploads_document_mimes();
    $filtered = [];
    foreach (uploads_avatar_extensions() as $ext) {
        if (isset($groups[$ext])) {
            $filtered[$ext] = $groups[$ext];
        }
    }
    return uploads_flat_mimes($filtered);
}

function uploads_flat_mimes(array $groups) {
    $flat = [];
    foreach ($groups as $mimes) {
        foreach ((array)$mimes as $m) {
            $flat[] = $m;
        }
    }
    return array_values(array_unique($flat));
}

function uploads_store_request_certificate($requestId, $fileKey = 'certificate', $nameSuffix = '') {
    $requestId = (int)$requestId;
    if ($requestId <= 0 || empty($_FILES[$fileKey]['name']) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'filename' => null, 'message' => ''];
    }
    $nameSuffix = preg_replace('/[^a-z0-9_-]/i', '', (string)$nameSuffix);
    $docMimes = uploads_document_mimes();
    $valid = uploads_validate_uploaded_file(
        $_FILES[$fileKey],
        array_keys($docMimes),
        uploads_flat_mimes($docMimes),
        10 * 1024 * 1024
    );
    if (!$valid['ok']) {
        return ['ok' => false, 'filename' => null, 'message' => $valid['message']];
    }
    uploads_ensure_private_directory('request_certificates');
    $dir = APPROOT . '/../public/uploads/request_certificates/';
    $filename = 'req_' . $requestId . ($nameSuffix !== '' ? $nameSuffix : '') . '_' . time() . '.' . $valid['ext'];
    if (!move_uploaded_file($_FILES[$fileKey]['tmp_name'], $dir . $filename)) {
        return ['ok' => false, 'filename' => null, 'message' => 'No se pudo guardar el certificado.'];
    }
    return ['ok' => true, 'filename' => $filename, 'message' => ''];
}

function admin_justification_stream_url($userId, $workDate) {
    return URLROOT . '/admin/streamJustification/' . (int)$userId . '/' . rawurlencode((string)$workDate);
}

function admin_request_certificate_stream_url($requestId) {
    return URLROOT . '/admin/streamRequestCertificate/' . (int)$requestId;
}

function upload_filename_is_image($filename) {
    $ext = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
}

function request_certificate_stream_url($requestId) {
    return URLROOT . '/request/streamCertificate/' . (int)$requestId;
}

function request_certificate_back_stream_url($requestId) {
    return URLROOT . '/request/streamCertificateBack/' . (int)$requestId;
}

function admin_request_certificate_back_stream_url($requestId) {
    return URLROOT . '/admin/streamRequestCertificateBack/' . (int)$requestId;
}

function learning_resource_stream_url($resourceId, $forAdmin = false) {
    $prefix = $forAdmin ? '/trainingAdmin/streamResource/' : '/training/streamResource/';
    return URLROOT . $prefix . (int)$resourceId;
}

function learning_lesson_stream_url($courseId, $lessonId, $forAdmin = false) {
    $prefix = $forAdmin ? '/trainingAdmin/streamLessonFile/' : '/training/streamLessonFile/';
    return URLROOT . $prefix . (int)$courseId . '/' . (int)$lessonId;
}
