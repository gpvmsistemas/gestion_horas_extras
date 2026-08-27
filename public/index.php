<?php
$appPublic = __DIR__;
$pathInfo = (string)($_SERVER['PATH_INFO'] ?? '');
if ($pathInfo === '') {
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $req = str_replace('\\', '/', (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''));
    if ($script !== '' && strpos($req, $script) === 0) {
        $pathInfo = substr($req, strlen($script));
    }
}
if ($pathInfo !== '' && $pathInfo !== '/') {
    $rel = str_replace('\\', '/', $pathInfo);
    if ($rel[0] !== '/') {
        $rel = '/' . $rel;
    }
    if (strpos($rel, '..') === false) {
        $candidate = $appPublic . $rel;
        $realFile = realpath($candidate);
        $realRoot = realpath($appPublic);
        $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
        $staticTypes = [
            'css' => 'text/css; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'json' => 'application/json; charset=UTF-8',
            'map' => 'application/json; charset=UTF-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'pdf' => 'application/pdf',
        ];
        if ($realFile && $realRoot && strpos($realFile, $realRoot . DIRECTORY_SEPARATOR) === 0 && is_file($realFile) && isset($staticTypes[$ext])) {
            header('Content-Type: ' . $staticTypes[$ext]);
            header('X-Content-Type-Options: nosniff');
            readfile($realFile);
            exit;
        }
    }
}

require_once __DIR__ . '/../app/bootstrap.php';

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (function_exists('app_request_is_https') && app_request_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

if (defined('APP_DEBUG') && APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

$init = new Core();