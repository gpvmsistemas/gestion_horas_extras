<?php

class NotificationBroadcast {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function getAll() {
        $this->db->query('SELECT b.*, u.full_name AS creator_name,
            (SELECT COUNT(*) FROM user_notifications un WHERE un.broadcast_id = b.id) AS recipient_count
            FROM notification_broadcasts b
            LEFT JOIN users u ON u.id = b.created_by
            ORDER BY b.created_at DESC');
        return $this->db->resultSet();
    }

    public function getById($id) {
        $this->db->query('SELECT * FROM notification_broadcasts WHERE id = :id');
        $this->db->bind(':id', (int)$id);
        return $this->db->single();
    }

    public function createAndDispatch(array $data, array $targetRows, array $recipientIds, $createdBy) {
        $type = $data['type'] ?? 'manual';
        if (!in_array($type, ['manual', 'course', 'pay_stub'], true)) {
            $type = 'manual';
        }
        // Resolver placeholders antes de la transacción: otras instancias Database()
        // (p. ej. User en notification_user_context) comparten PDO persistente y cierran la transacción.
        $pending = [];
        foreach ($recipientIds as $uid) {
            $uid = (int)$uid;
            if ($uid <= 0) {
                continue;
            }
            $pending[] = [
                'user_id' => $uid,
                'title' => notification_apply_placeholders($data['title'] ?? '', $uid),
                'body' => notification_apply_placeholders($data['body'] ?? '', $uid),
                'link_url' => $data['link_url'] ?? null,
                'type' => $type,
                'reference_id' => $data['reference_id'] ?? null,
            ];
        }

        $this->db->beginTransaction();
        try {
            $this->db->query('INSERT INTO notification_broadcasts (
                title, body, link_url, type, starts_at, ends_at, target_all, send_email, is_active, created_by
            ) VALUES (
                :title, :body, :link_url, :type, :starts_at, :ends_at, :target_all, :send_email, 1, :created_by
            )');
            $this->db->bind(':title', trim($data['title'] ?? ''));
            $this->db->bind(':body', $data['body'] ?? '');
            $this->db->bind(':link_url', !empty($data['link_url']) ? trim($data['link_url']) : null);
            $this->db->bind(':type', $type);
            $this->db->bind(':starts_at', !empty($data['starts_at']) ? $data['starts_at'] : null);
            $this->db->bind(':ends_at', !empty($data['ends_at']) ? $data['ends_at'] : null);
            $this->db->bind(':target_all', !empty($data['target_all']) ? 1 : 0);
            $this->db->bind(':send_email', !empty($data['send_email']) ? 1 : 0);
            $this->db->bind(':created_by', (int)$createdBy);
            $this->db->execute();
            $broadcastId = (int)$this->db->lastInsertId();

            if (empty($data['target_all'])) {
                foreach ($targetRows as $t) {
                    $this->db->query('INSERT INTO notification_targets (broadcast_id, target_type, target_id)
                        VALUES (:bid, :type, :tid)');
                    $this->db->bind(':bid', $broadcastId);
                    $this->db->bind(':type', $t['target_type']);
                    $this->db->bind(':tid', (int)$t['target_id']);
                    $this->db->execute();
                }
            }

            $userNotif = new UserNotification($this->db);
            $sent = 0;
            $skipped = 0;
            foreach ($pending as $personalized) {
                $personalized['broadcast_id'] = $broadcastId;
                if ($userNotif->create($personalized)) {
                    $sent++;
                } else {
                    $skipped++;
                }
            }

            $this->db->commit();

            if (function_exists('push_notify_user')) {
                foreach ($pending as $personalized) {
                    push_notify_user(
                        (int)$personalized['user_id'],
                        $personalized['title'] ?? '',
                        $personalized['body'] ?? '',
                        $personalized['link_url'] ?? null
                    );
                }
            }

            return ['id' => $broadcastId, 'sent' => $sent, 'skipped' => $skipped];
        } catch (Throwable $e) {
            $this->db->rollBack();
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log('NotificationBroadcast::createAndDispatch: ' . $e->getMessage());
            }
            return false;
        }
    }

    public function getTargets($broadcastId) {
        $this->db->query('SELECT * FROM notification_targets WHERE broadcast_id = :id');
        $this->db->bind(':id', (int)$broadcastId);
        return $this->db->resultSet();
    }

    /**
     * Elimina un envío manual y las notificaciones de la campana de cada empleado.
     */
    public function deleteBroadcast($id) {
        $id = (int)$id;
        $row = $this->getById($id);
        if (!$row || $row->type !== 'manual') {
            return false;
        }
        $this->db->beginTransaction();
        try {
            (new UserNotification($this->db))->deleteByBroadcastId($id);
            $this->db->query('DELETE FROM notification_targets WHERE broadcast_id = :id');
            $this->db->bind(':id', $id);
            $this->db->execute();
            $this->db->query('DELETE FROM notification_broadcasts WHERE id = :id AND type = \'manual\'');
            $this->db->bind(':id', $id);
            $this->db->execute();
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }
}
