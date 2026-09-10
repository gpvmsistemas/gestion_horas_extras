<?php
// app/config/config.php
// Overrides opcionales: copiar config.example.php → config.local.php (no versionar).

if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

// --- Configuración de la Base de Datos ---
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'paviotti_lanaturaleza');
}

// --- Rutas de la Aplicación ---
if (!defined('APPROOT')) {
    define('APPROOT', dirname(dirname(__FILE__)));
}
require_once __DIR__ . '/../helpers/app_env_helper.php';
if (!defined('URLROOT')) {
    define('URLROOT', app_detect_urlroot());
}
if (!defined('APP_ENV')) {
    define('APP_ENV', app_runtime_environment());
}
if (!defined('SITENAME')) {
    define('SITENAME', 'Paviotti RRHH');
}
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', APP_ENV === 'local');
}
if (!defined('DEFAULT_COMPANY_NAME')) {
    define('DEFAULT_COMPANY_NAME', 'Ecofarma');
}
if (!defined('ATTENDANCE_LATE_TOLERANCE_MIN')) {
    define('ATTENDANCE_LATE_TOLERANCE_MIN', 5);
}
if (!defined('ATTENDANCE_EARLY_LEAVE_TOLERANCE_MIN')) {
    define('ATTENDANCE_EARLY_LEAVE_TOLERANCE_MIN', 5);
}

$GLOBALS['CLOCK_DEVICE_MAP'] = [
    'ECOFARMA'       => ['label' => 'ECOFARMA',           'badge' => 'danger'],
    'SOCIALES'       => ['label' => 'Servicios Sociales', 'badge' => 'primary'],
    'Hikvision SS'   => ['label' => 'Servicios Sociales', 'badge' => 'primary'],
    'Hikvision SS '  => ['label' => 'Servicios Sociales', 'badge' => 'primary'],
    'Anviz'          => ['label' => 'AMSSI',              'badge' => 'success'],
    'Anviz Oficina'  => ['label' => 'AMSSI',              'badge' => 'success'],
];

if (!defined('CLOCK_API_BASE_URL')) {
    define('CLOCK_API_BASE_URL', 'http://gpaviotti.com.ar:6333');
}
if (!defined('CLOCK_API_VERSION')) {
    define('CLOCK_API_VERSION', '0.2.0');
}
if (!defined('CLOCK_API_LOGIN_URL')) {
    define('CLOCK_API_LOGIN_URL', CLOCK_API_BASE_URL . '/login');
}
if (!defined('CLOCK_API_MARCACIONES_URL')) {
    define('CLOCK_API_MARCACIONES_URL', CLOCK_API_BASE_URL . '/api/v1/marcaciones');
}
if (!defined('CLOCK_API_LEGAJOS_URL')) {
    define('CLOCK_API_LEGAJOS_URL', CLOCK_API_BASE_URL . '/api/v1/legajos');
}
if (!defined('CLOCK_API_LEGAJO_POR_CODIGO_URL')) {
    define('CLOCK_API_LEGAJO_POR_CODIGO_URL', CLOCK_API_BASE_URL . '/api/v1/legajos/por-codigo');
}
if (!defined('CLOCK_API_CATALOGO_URL')) {
    define('CLOCK_API_CATALOGO_URL', CLOCK_API_BASE_URL . '/api/v1/catalogo');
}
if (!defined('CLOCK_API_RELOJES_URL')) {
    define('CLOCK_API_RELOJES_URL', CLOCK_API_BASE_URL . '/api/v1/relojes');
}
if (!defined('CLOCK_API_EMAIL')) {
    define('CLOCK_API_EMAIL', '');
}
if (!defined('CLOCK_API_PASSWORD')) {
    define('CLOCK_API_PASSWORD', '');
}
if (!defined('CLOCK_API_LEGAJO_RESOLVE_URL')) {
    define('CLOCK_API_LEGAJO_RESOLVE_URL', CLOCK_API_BASE_URL . '/api/v1/legajos/resolve');
}
if (!defined('EXTERNAL_CLOCK_API_URL')) {
    define('EXTERNAL_CLOCK_API_URL', CLOCK_API_MARCACIONES_URL);
}
if (!defined('EXTERNAL_CLOCK_API_KEY')) {
    define('EXTERNAL_CLOCK_API_KEY', '');
}
if (!defined('ECOFARMA_API_SUCURSALES_URL')) {
    define('ECOFARMA_API_SUCURSALES_URL', CLOCK_API_BASE_URL . '/api/v1/ecofarma/sucursales');
}
if (!defined('ECOFARMA_API_FACTURACION_URL')) {
    define('ECOFARMA_API_FACTURACION_URL', CLOCK_API_BASE_URL . '/api/v1/ecofarma/facturacion-acos');
}
if (!defined('ECOFARMA_API_RESUMEN_OPERADORES_URL')) {
    define('ECOFARMA_API_RESUMEN_OPERADORES_URL', CLOCK_API_BASE_URL . '/api/v1/ecofarma/facturacion-acos/resumen-operadores');
}
if (!defined('ECOFARMA_DEFAULT_OBRA_SOCIAL')) {
    define('ECOFARMA_DEFAULT_OBRA_SOCIAL', '999900');
}
if (!defined('ECOFARMA_DEFAULT_COMISION_PCT')) {
    define('ECOFARMA_DEFAULT_COMISION_PCT', 7);
}
if (!defined('EXTINTOS_DB_HOST')) {
    define('EXTINTOS_DB_HOST', 'localhost');
}
if (!defined('EXTINTOS_DB_NAME')) {
    define('EXTINTOS_DB_NAME', 'paviotti_extintos');
}
if (!defined('EXTINTOS_DB_USER')) {
    define('EXTINTOS_DB_USER', '');
}
if (!defined('EXTINTOS_DB_PASS')) {
    define('EXTINTOS_DB_PASS', '');
}

// Web Push (PWA) — generar con: php scripts/generate_vapid_keys.php
if (!defined('VAPID_PUBLIC_KEY')) {
    define('VAPID_PUBLIC_KEY', '');
}
if (!defined('VAPID_PRIVATE_KEY')) {
    define('VAPID_PRIVATE_KEY', '');
}
if (!defined('VAPID_SUBJECT')) {
    define('VAPID_SUBJECT', 'mailto:rrhh@rrhhpym.com');
}

// Polyfills de PHP 8 para entornos con PHP 7.4 (XAMPP local): el código
// escrito contra el VPS (PHP 8.4) los usa; en 8.x estas definiciones no aplican.
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return $needle === '' || strncmp((string)$haystack, (string)$needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return $needle === '' || substr((string)$haystack, -strlen($needle)) === (string)$needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle === '' || strpos((string)$haystack, (string)$needle) !== false;
    }
}
