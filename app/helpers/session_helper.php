<?php
if (session_status() === PHP_SESSION_NONE) {
    $secure = function_exists('app_request_is_https') ? app_request_is_https() : (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    );
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => $secure,
    ]);
}

// Función de redirección simple
function redirect($page){
    header('location: ' . URLROOT . '/' . $page);
    exit();
}

// Verificar si el usuario está logueado
function isLoggedIn(){
    return isset($_SESSION['user_id']);
}

// Verificar si el usuario tiene un rol específico
function hasRole($role){
    if(isLoggedIn() && ($_SESSION['user_role'] ?? '') === $role){
        return true;
    }
    return false;
}

function hasAnyRole(...$roles) {
    if (!isLoggedIn()) {
        return false;
    }
    foreach ($roles as $role) {
        if ($_SESSION['user_role'] === $role) {
            return true;
        }
    }
    return false;
}

function isAdmin() {
    if (function_exists('access_control_ready') && access_control_ready()) {
        return access_current_role() === 'administrador';
    }
    return hasRole('admin');
}

function isSupervisor() {
    if (function_exists('access_control_ready') && access_control_ready()) {
        return access_current_role() === 'encargado';
    }
    return hasRole('supervisor');
}

function isStaffAdmin() {
    if (function_exists('access_control_ready') && access_control_ready()) {
        return access_is_staff();
    }
    return hasAnyRole('admin', 'supervisor');
}

// ── CSRF ──────────────────────────────────────────────────────────────────────

/**
 * Genera (o reutiliza) un token CSRF almacenado en sesión.
 * Compatible con PHP 7.4+.
 */
function csrf_token(){
    if(empty($_SESSION['csrf_token'])){
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica que el token CSRF del POST coincida con el de la sesión.
 * Si falla, termina la ejecución con HTTP 403.
 */
function csrf_verify(){
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if(empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)){
        http_response_code(403);
        die('Error: token de seguridad inválido. Por favor, recarga la página e intenta de nuevo.');
    }
}

/** Regenera el token CSRF (p. ej. tras login exitoso). */
function csrf_regenerate() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/** json_encode seguro para incrustar en bloques <script>. */
function json_script_safe($data) {
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
}
