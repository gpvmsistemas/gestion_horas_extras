<?php

class Announcement {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function getAll() {
        $this->db->query('SELECT a.*, u.full_name AS creator_name FROM announcements a
            LEFT JOIN users u ON u.id = a.created_by
            ORDER BY a.created_at DESC');
        return $this->db->resultSet();
    }

    public function getById($id) {
        $this->db->query('SELECT * FROM announcements WHERE id = :id');
        $this->db->bind(':id', (int)$id);
        return $this->db->single();
    }

    public function create(array $data, array $targets, $createdBy) {
        $this->db->query('INSERT INTO announcements (
            title, body, image_path, video_path, link_url, link_label, starts_at, ends_at,
            display_mode, target_all, send_email, is_active, created_by
        ) VALUES (
            :title, :body, :image_path, :video_path, :link_url, :link_label, :starts_at, :ends_at,
            :display_mode, :target_all, :send_email, :is_active, :created_by
        )');
        $this->bindAnnouncement($data, $createdBy);
        if (!$this->db->execute()) {
            return false;
        }
        $id = (int)$this->db->lastInsertId();
        $this->saveTargets($id, $targets, !empty($data['target_all']));
        return $id;
    }

    public function update($id, array $data, array $targets) {
        $this->db->query('UPDATE announcements SET
            title = :title, body = :body, image_path = :image_path,
            video_path = :video_path,
            link_url = :link_url, link_label = :link_label,
            starts_at = :starts_at, ends_at = :ends_at,
            display_mode = :display_mode, target_all = :target_all,
            send_email = :send_email, is_active = :is_active
            WHERE id = :id');
        $this->bindAnnouncement($data, null);
        $this->db->bind(':id', (int)$id);
        if (!$this->db->execute()) {
            return false;
        }
        $this->db->query('DELETE FROM announcement_targets WHERE announcement_id = :id');
        $this->db->bind(':id', (int)$id);
        $this->db->execute();
        $this->saveTargets($id, $targets, !empty($data['target_all']));
        return true;
    }

    public function delete($id) {
        $this->db->query('DELETE FROM announcements WHERE id = :id');
        $this->db->bind(':id', (int)$id);
        return $this->db->execute();
    }

    public function getTargets($announcementId) {
        $this->db->query('SELECT * FROM announcement_targets WHERE announcement_id = :id');
        $this->db->bind(':id', (int)$announcementId);
        return $this->db->resultSet();
    }

    private function bindAnnouncement(array $data, $createdBy) {
        $this->db->bind(':title', trim($data['title'] ?? ''));
        $this->db->bind(':body', $data['body'] ?? '');
        $this->db->bind(':image_path', !empty($data['image_path']) ? $data['image_path'] : null);
        $this->db->bind(':video_path', !empty($data['video_path']) ? $data['video_path'] : null);
        $this->db->bind(':link_url', !empty($data['link_url']) ? trim($data['link_url']) : null);
        $this->db->bind(':link_label', !empty($data['link_label']) ? trim($data['link_label']) : null);
        $this->db->bind(':starts_at', $data['starts_at']);
        $this->db->bind(':ends_at', $data['ends_at']);
        $dm = $data['display_mode'] ?? 'once';
        if (!in_array($dm, ['once', 'sessions_3', 'always'], true)) {
            $dm = 'once';
        }
        $this->db->bind(':display_mode', $dm);
        $this->db->bind(':target_all', !empty($data['target_all']) ? 1 : 0);
        $this->db->bind(':send_email', !empty($data['send_email']) ? 1 : 0);
        $this->db->bind(':is_active', !empty($data['is_active']) ? 1 : 0);
        if ($createdBy !== null) {
            $this->db->bind(':created_by', (int)$createdBy);
        }
    }

    private function saveTargets($announcementId, array $targets, $targetAll) {
        if ($targetAll) {
            return;
        }
        foreach ($targets as $t) {
            if (empty($t['target_type']) || empty($t['target_id'])) {
                continue;
            }
            $this->db->query('INSERT INTO announcement_targets (announcement_id, target_type, target_id)
                VALUES (:aid, :type, :tid)');
            $this->db->bind(':aid', (int)$announcementId);
            $this->db->bind(':type', $t['target_type']);
            $this->db->bind(':tid', (int)$t['target_id']);
            $this->db->execute();
        }
    }
}
