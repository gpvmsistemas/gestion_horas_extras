<?php

class PushSubscription {
    private $db;

    public function __construct(Database $db = null) {
        $this->db = $db ?? new Database();
    }

    public function upsert($userId, $endpoint, $publicKey, $authToken, $userAgent = null) {
        $userId = (int)$userId;
        $endpoint = trim((string)$endpoint);
        $publicKey = trim((string)$publicKey);
        $authToken = trim((string)$authToken);
        if ($userId <= 0 || $endpoint === '' || $publicKey === '' || $authToken === '') {
            return false;
        }

        $this->db->query('SELECT id, user_id FROM push_subscriptions WHERE endpoint = :endpoint LIMIT 1');
        $this->db->bind(':endpoint', $endpoint);
        $existing = $this->db->single();

        if ($existing) {
            $this->db->query('UPDATE push_subscriptions SET
                user_id = :user_id,
                public_key = :public_key,
                auth_token = :auth_token,
                user_agent = :user_agent,
                updated_at = NOW()
                WHERE id = :id');
            $this->db->bind(':id', (int)$existing->id);
            $this->db->bind(':user_id', $userId);
            $this->db->bind(':public_key', $publicKey);
            $this->db->bind(':auth_token', $authToken);
            $this->db->bind(':user_agent', $userAgent !== null && $userAgent !== '' ? mb_substr($userAgent, 0, 255) : null);
            return $this->db->execute();
        }

        $this->db->query('INSERT INTO push_subscriptions (
            user_id, endpoint, public_key, auth_token, user_agent
        ) VALUES (
            :user_id, :endpoint, :public_key, :auth_token, :user_agent
        )');
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':endpoint', $endpoint);
        $this->db->bind(':public_key', $publicKey);
        $this->db->bind(':auth_token', $authToken);
        $this->db->bind(':user_agent', $userAgent !== null && $userAgent !== '' ? mb_substr($userAgent, 0, 255) : null);
        return $this->db->execute();
    }

    public function deleteByEndpoint($userId, $endpoint) {
        $this->db->query('DELETE FROM push_subscriptions
            WHERE user_id = :user_id AND endpoint = :endpoint');
        $this->db->bind(':user_id', (int)$userId);
        $this->db->bind(':endpoint', trim((string)$endpoint));
        return $this->db->execute();
    }

    public function deleteById($id, $userId) {
        $this->db->query('DELETE FROM push_subscriptions WHERE id = :id AND user_id = :user_id');
        $this->db->bind(':id', (int)$id);
        $this->db->bind(':user_id', (int)$userId);
        return $this->db->execute();
    }

    public function getByUserId($userId) {
        $this->db->query('SELECT * FROM push_subscriptions WHERE user_id = :user_id ORDER BY updated_at DESC');
        $this->db->bind(':user_id', (int)$userId);
        return $this->db->resultSet();
    }

    public function deleteByEndpointGlobal($endpoint) {
        $this->db->query('DELETE FROM push_subscriptions WHERE endpoint = :endpoint');
        $this->db->bind(':endpoint', trim((string)$endpoint));
        return $this->db->execute();
    }

    public function countByUserId($userId) {
        $this->db->query('SELECT COUNT(*) AS c FROM push_subscriptions WHERE user_id = :user_id');
        $this->db->bind(':user_id', (int)$userId);
        $row = $this->db->single();
        return $row ? (int)$row->c : 0;
    }
}
