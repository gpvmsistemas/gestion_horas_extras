<?php

class EmployeeIncident {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function isSchemaReady() {
        $this->db->query("SHOW TABLES LIKE 'employee_incidents'");
        return (bool)$this->db->single();
    }

    public function getRecentByCompany($companyId, $limit = 10) {
        if (!$this->isSchemaReady()) {
            return [];
        }
        $this->db->query('
            SELECT ei.*, u.full_name AS employee_name, a.full_name AS admin_name
            FROM employee_incidents ei
            JOIN users u ON u.id = ei.user_id
            JOIN users a ON a.id = ei.admin_id
            WHERE u.company_id = :company_id
            ORDER BY ei.incident_date DESC, ei.created_at DESC
            LIMIT ' . (int)$limit);
        $this->db->bind(':company_id', (int)$companyId);
        return $this->db->resultSet();
    }

    public function getByUserId($userId) {
        $this->db->query("
            SELECT ei.*, a.full_name AS admin_name
            FROM employee_incidents ei
            JOIN users a ON a.id = ei.admin_id
            WHERE ei.user_id = :user_id
            ORDER BY ei.incident_date DESC, ei.created_at DESC
        ");
        $this->db->bind(':user_id', (int)$userId);
        return $this->db->resultSet();
    }

    public function getByUserInPeriod($userId, $startDate, $endDate) {
        if (!$this->isSchemaReady()) {
            return [];
        }
        $this->db->query("
            SELECT ei.*, a.full_name AS admin_name
            FROM employee_incidents ei
            JOIN users a ON a.id = ei.admin_id
            WHERE ei.user_id = :user_id
              AND ei.incident_date BETWEEN :start_date AND :end_date
            ORDER BY ei.incident_date ASC
        ");
        $this->db->bind(':user_id', (int)$userId);
        $this->db->bind(':start_date', $startDate);
        $this->db->bind(':end_date', $endDate);
        return $this->db->resultSet();
    }

    public function getByIdForUser($id, $userId) {
        $this->db->query("
            SELECT ei.*, a.full_name AS admin_name
            FROM employee_incidents ei
            JOIN users a ON a.id = ei.admin_id
            WHERE ei.id = :id AND ei.user_id = :user_id
        ");
        $this->db->bind(':id', (int)$id);
        $this->db->bind(':user_id', (int)$userId);
        return $this->db->single();
    }

    public function add($data) {
        $this->db->query("
            INSERT INTO employee_incidents
                (user_id, admin_id, incident_type, title, description, incident_date,
                 attachment_path, attachment_original_name)
            VALUES
                (:user_id, :admin_id, :incident_type, :title, :description, :incident_date,
                 :attachment_path, :attachment_original_name)
        ");
        $this->db->bind(':user_id', (int)$data['user_id']);
        $this->db->bind(':admin_id', (int)$data['admin_id']);
        $this->db->bind(':incident_type', $data['incident_type']);
        $this->db->bind(':title', $data['title'] !== '' ? $data['title'] : null);
        $this->db->bind(':description', $data['description']);
        $this->db->bind(':incident_date', $data['incident_date']);
        $this->db->bind(':attachment_path', $data['attachment_path'] ?? null);
        $this->db->bind(':attachment_original_name', $data['attachment_original_name'] ?? null);
        return $this->db->execute();
    }

    public function deleteForUser($id, $userId) {
        $row = $this->getByIdForUser($id, $userId);
        if (!$row) {
            return false;
        }
        if (in_array($row->workflow_status ?? 'draft', ['notified','received','refused'], true)) return false;
        $this->db->query('DELETE FROM employee_incidents WHERE id = :id AND user_id = :user_id');
        $this->db->bind(':id', (int)$id);
        $this->db->bind(':user_id', (int)$userId);
        if (!$this->db->execute()) {
            return false;
        }
        if (!empty($row->attachment_path)) {
            employee_incident_delete_file($userId, $row->attachment_path);
        }
        return true;
    }
}
