<?php

function push_subscriptions_ready() {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $db = new Database();
        $db->query("SHOW TABLES LIKE 'push_subscriptions'");
        $ready = (bool)$db->single();
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

function pwa_push_configured() {
    return defined('VAPID_PUBLIC_KEY') && VAPID_PUBLIC_KEY !== ''
        && defined('VAPID_PRIVATE_KEY') && VAPID_PRIVATE_KEY !== ''
        && defined('VAPID_SUBJECT') && VAPID_SUBJECT !== '';
}

function pwa_push_ready() {
    return push_subscriptions_ready()
        && pwa_push_configured()
        && class_exists('Minishlink\\WebPush\\WebPush');
}

function pwa_vapid_public_key() {
    return pwa_push_configured() ? (string)VAPID_PUBLIC_KEY : '';
}

/**
 * Dispara Web Push a los dispositivos del empleado (no bloquea si falla).
 *
 * @return int Envíos exitosos.
 */
function push_notify_user($userId, $title, $body, $linkUrl = null) {
    if (!pwa_push_ready()) {
        return 0;
    }
    return (new WebPushService())->sendToUser((int)$userId, $title, $body, $linkUrl);
}

function pwa_manifest_data() {
    $urlPath = parse_url(URLROOT, PHP_URL_PATH);
    $basePath = is_string($urlPath) ? rtrim($urlPath, '/') : '';
    $scope = ($basePath !== '' ? $basePath : '') . '/';
    $startUrl = ($basePath !== '' ? $basePath : '') . '/employee/index';
    $themeColor = function_exists('company_brand_color') ? company_brand_color() : '#1e3a5f';
    $icon192 = URLROOT . '/img/pwa/icon-192.png';
    $icon512 = URLROOT . '/img/pwa/icon-512.png';
    if (!is_file(APPROOT . '/../public/img/pwa/icon-192.png')) {
        $icon192 = URLROOT . '/img/pwa/logo-pm.png';
        $icon512 = URLROOT . '/img/pwa/logo-pm.png';
    }

    return [
        'name' => function_exists('app_name') ? app_name() : SITENAME,
        'short_name' => 'RRHH',
        'description' => 'Portal de empleados — asistencia, solicitudes y comunicaciones.',
        'start_url' => $startUrl,
        'scope' => $scope,
        'display' => 'standalone',
        'orientation' => 'portrait-primary',
        'background_color' => '#ffffff',
        'theme_color' => $themeColor,
        'lang' => 'es-AR',
        'icons' => [
            [
                'src' => $icon192,
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => $icon512,
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any maskable',
            ],
        ],
    ];
}

function pwa_icon_url($size = 192) {
    $size = (int)$size;
    if (!in_array($size, [192, 512], true)) {
        $size = 192;
    }
    $rel = 'img/pwa/icon-' . $size . '.png';
    if (is_file(APPROOT . '/../public/' . $rel)) {
        return URLROOT . '/' . $rel;
    }
    if (is_file(APPROOT . '/../public/img/pwa/logo-pm.png')) {
        return URLROOT . '/img/pwa/logo-pm.png';
    }
    return URLROOT . '/img/pym.png';
}

/** Portal empleado o cualquier sesión activa (para instalar la PWA). */
function pwa_install_ui_enabled() {
    return isLoggedIn();
}
