<?php
/**
 * Genera un par de claves VAPID para Web Push.
 * Copiá el resultado a app/config/config.local.php (no versionar).
 */
require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!class_exists('Minishlink\\WebPush\\VAPID')) {
    fwrite(STDERR, "Ejecutá primero: php composer.phar install\n");
    exit(1);
}

$keys = Minishlink\WebPush\VAPID::createVapidKeys();

echo "Agregá esto a app/config/config.local.php:\n\n";
echo "define('VAPID_PUBLIC_KEY', " . var_export($keys['publicKey'], true) . ");\n";
echo "define('VAPID_PRIVATE_KEY', " . var_export($keys['privateKey'], true) . ");\n";
echo "define('VAPID_SUBJECT', 'mailto:rrhh@tu-dominio.com');\n\n";
