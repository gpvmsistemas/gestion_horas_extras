<?php
require_once __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$data = function_exists('pwa_manifest_data') ? pwa_manifest_data() : [
    'name' => SITENAME,
    'short_name' => 'RRHH',
    'start_url' => '/employee/index',
    'display' => 'standalone',
];

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
