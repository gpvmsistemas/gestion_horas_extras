<?php

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService {

    public function isConfigured() {
        return function_exists('pwa_push_ready') && pwa_push_ready();
    }

    /**
     * Envía push a todos los dispositivos del usuario. No incluye datos sensibles en el cuerpo.
     *
     * @return int Cantidad de envíos exitosos.
     */
    public function sendToUser($userId, $title, $body, $linkUrl = null) {
        if (!$this->isConfigured()) {
            return 0;
        }

        $subscriptions = (new PushSubscription())->getByUserId((int)$userId);
        if (empty($subscriptions)) {
            return 0;
        }

        $safeTitle = $this->sanitizePushText($title, 80) ?: 'Nueva notificación';
        $safeBody = $this->sanitizePushText($body, 120) ?: 'Tenés un aviso nuevo en el portal.';
        $targetUrl = $this->resolveTargetUrl($linkUrl);

        $payload = json_encode([
            'title' => $safeTitle,
            'body' => $safeBody,
            'url' => $targetUrl,
            'tag' => 'rrhh-user-' . (int)$userId,
            'icon' => function_exists('pwa_icon_url') ? pwa_icon_url(192) : (URLROOT . '/img/pym.png'),
        ], JSON_UNESCAPED_UNICODE);

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => VAPID_SUBJECT,
                    'publicKey' => VAPID_PUBLIC_KEY,
                    'privateKey' => VAPID_PRIVATE_KEY,
                ],
            ]);

            foreach ($subscriptions as $sub) {
                $subscription = Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys' => [
                        'p256dh' => $sub->public_key,
                        'auth' => $sub->auth_token,
                    ],
                ]);
                $webPush->queueNotification($subscription, $payload);
            }

            $sent = 0;
            $pushModel = new PushSubscription();
            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $sent++;
                    continue;
                }
                $endpoint = $report->getEndpoint();
                if ($endpoint && $this->isExpiredSubscription($report)) {
                    $pushModel->deleteByEndpointGlobal($endpoint);
                }
            }
            return $sent;
        } catch (Throwable $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log('WebPushService::sendToUser: ' . $e->getMessage());
            }
            return 0;
        }
    }

    private function isExpiredSubscription($report) {
        $reason = (string)$report->getReason();
        return stripos($reason, 'expired') !== false
            || stripos($reason, '410') !== false
            || stripos($reason, '404') !== false
            || stripos($reason, 'unsubscribed') !== false;
    }

    private function sanitizePushText($text, $maxLen) {
        $text = trim(strip_tags((string)$text));
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text) > $maxLen) {
            $text = mb_substr($text, 0, $maxLen - 1) . '…';
        }
        return $text;
    }

    private function resolveTargetUrl($linkUrl) {
        $fallback = URLROOT . '/employee/notifications';
        $linkUrl = trim((string)$linkUrl);
        if ($linkUrl === '') {
            return $fallback;
        }
        if (strpos($linkUrl, 'http://') === 0 || strpos($linkUrl, 'https://') === 0) {
            $base = rtrim(URLROOT, '/');
            if (strpos($linkUrl, $base) === 0) {
                return $linkUrl;
            }
            return $fallback;
        }
        if ($linkUrl[0] === '/') {
            $path = parse_url(URLROOT, PHP_URL_PATH) ?: '';
            return rtrim(URLROOT, '/') . $linkUrl;
        }
        return rtrim(URLROOT, '/') . '/' . ltrim($linkUrl, '/');
    }
}
