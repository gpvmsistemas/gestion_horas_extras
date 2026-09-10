<?php

class CollectiveAgreement {
    private $db;

    public function __construct($db = null) {
        $this->db = $db instanceof Database ? $db : new Database();
    }

    public function isReady() {
        $this->db->query("SHOW TABLES LIKE 'collective_agreements'");
        return (bool)$this->db->single();
    }

    public function getAll($activeOnly = true) {
        $sql = 'SELECT * FROM collective_agreements';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY name ASC';
        $this->db->query($sql);
        return $this->db->resultSet();
    }

    public function getById($id) {
        $this->db->query('SELECT * FROM collective_agreements WHERE id = :id');
        $this->db->bind(':id', (int)$id);
        return $this->db->single();
    }

    public function getRules($agreementId) {
        $this->db->query('SELECT * FROM collective_agreement_rules WHERE agreement_id = :aid ORDER BY min_months ASC');
        $this->db->bind(':aid', (int)$agreementId);
        return $this->db->resultSet();
    }

    public function getDefaultForCompany($companyId) {
        $this->db->query('
            SELECT ca.* FROM company_agreement_defaults cad
            JOIN collective_agreements ca ON ca.id = cad.agreement_id
            WHERE cad.company_id = :cid
        ');
        $this->db->bind(':cid', (int)$companyId);
        return $this->db->single();
    }

    public function setDefaultForCompany($companyId, $agreementId) {
        $this->db->query('
            INSERT INTO company_agreement_defaults (company_id, agreement_id) VALUES (:cid, :aid)
            ON DUPLICATE KEY UPDATE agreement_id = :aid2
        ');
        $this->db->bind(':cid', (int)$companyId);
        $this->db->bind(':aid', (int)$agreementId);
        $this->db->bind(':aid2', (int)$agreementId);
        return $this->db->execute();
    }

    public function saveAgreement(array $data) {
        if (!empty($data['id'])) {
            $this->db->query('
                UPDATE collective_agreements SET code = :code, name = :name, description = :description,
                    jurisdiction = :jurisdiction, legal_reference = :legal_reference,
                    period_start_month = :psm, period_start_day = :psd, notice_days = :notice_days,
                    start_rule = :start_rule, split_policy = :split_policy,
                    minimum_request_days = :minimum_request_days, is_active = :active
                WHERE id = :id
            ');
            $this->db->bind(':id', (int)$data['id']);
        } else {
            $this->db->query('
                INSERT INTO collective_agreements
                    (code,name,description,jurisdiction,legal_reference,period_start_month,period_start_day,
                     notice_days,start_rule,split_policy,minimum_request_days,is_active)
                VALUES (:code,:name,:description,:jurisdiction,:legal_reference,:psm,:psd,
                        :notice_days,:start_rule,:split_policy,:minimum_request_days,:active)
            ');
        }
        $this->db->bind(':code', $data['code']);
        $this->db->bind(':name', $data['name']);
        $this->db->bind(':description', $data['description'] ?? null);
        $this->db->bind(':jurisdiction', $data['jurisdiction'] ?? null);
        $this->db->bind(':legal_reference', $data['legal_reference'] ?? null);
        $this->db->bind(':psm', (int)($data['period_start_month'] ?? 1));
        $this->db->bind(':psd', (int)($data['period_start_day'] ?? 1));
        $this->db->bind(':notice_days', (int)($data['notice_days'] ?? 30));
        $this->db->bind(':start_rule', $data['start_rule'] ?? 'lct');
        $this->db->bind(':split_policy', $data['split_policy'] ?? 'lct_7');
        $this->db->bind(':minimum_request_days', (float)($data['minimum_request_days'] ?? 7));
        $this->db->bind(':active', !empty($data['is_active']) ? 1 : 0);
        return $this->db->execute();
    }

    public function lastInsertId() {
        return $this->db->lastInsertId();
    }

    public function deleteRulesForAgreement($agreementId) {
        $this->db->query('DELETE FROM collective_agreement_rules WHERE agreement_id = :aid');
        $this->db->bind(':aid', (int)$agreementId);
        return $this->db->execute();
    }

    public function insertRule(array $rule) {
        $this->db->query('
            INSERT INTO collective_agreement_rules
                (agreement_id, min_months, max_months, days_entitled, day_count_mode, allows_split,
                 allows_carryover, min_consecutive_days, notes)
            VALUES (:aid, :min_m, :max_m, :days, :mode, :split, :carry, :min_days, :notes)
        ');
        $this->db->bind(':aid', (int)$rule['agreement_id']);
        $this->db->bind(':min_m', (int)$rule['min_months']);
        $this->db->bind(':max_m', isset($rule['max_months']) && $rule['max_months'] !== '' ? (int)$rule['max_months'] : null);
        $this->db->bind(':days', (int)$rule['days_entitled']);
        $this->db->bind(':mode', $rule['day_count_mode'] ?? 'calendar');
        $this->db->bind(':split', !empty($rule['allows_split']) ? 1 : 0);
        $this->db->bind(':carry', !empty($rule['allows_carryover']) ? 1 : 0);
        $this->db->bind(':min_days', isset($rule['min_consecutive_days']) ? (int)$rule['min_consecutive_days'] : null);
        $this->db->bind(':notes', $rule['notes'] ?? null);
        return $this->db->execute();
    }

    public function leaveTypesReady() {
        $this->db->query("SHOW TABLES LIKE 'collective_agreement_leave_types'");
        return (bool)$this->db->single();
    }

    public function leaveTypeRequiresApprovalReady() {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        if (!$this->leaveTypesReady()) {
            $ready = false;
            return $ready;
        }
        $this->db->query("SHOW COLUMNS FROM collective_agreement_leave_types LIKE 'requires_approval'");
        $ready = (bool)$this->db->single();
        return $ready;
    }

    public function getLeaveTypes($agreementId, $activeOnly = true) {
        if (!$this->leaveTypesReady()) {
            return [];
        }
        $sql = 'SELECT * FROM collective_agreement_leave_types WHERE agreement_id = :aid';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, name ASC';
        $this->db->query($sql);
        $this->db->bind(':aid', (int)$agreementId);
        return $this->db->resultSet();
    }

    public function getLeaveTypeById($id) {
        if (!$this->leaveTypesReady() || (int)$id <= 0) {
            return null;
        }
        $this->db->query('SELECT * FROM collective_agreement_leave_types WHERE id = :id');
        $this->db->bind(':id', (int)$id);
        return $this->db->single();
    }

    public function saveLeaveType(array $data) {
        if (!$this->leaveTypesReady()) {
            return false;
        }
        $maxYear = trim((string)($data['max_days_per_year'] ?? ''));
        $maxEvent = trim((string)($data['max_days_per_event'] ?? ''));
        $minNotice = trim((string)($data['min_notice_days'] ?? ''));
        $requiresApproval = !isset($data['requires_approval']) || !empty($data['requires_approval']) ? 1 : 0;
        $approvalSql = $this->leaveTypeRequiresApprovalReady() ? ', requires_approval = :requires_approval' : '';
        if (!empty($data['id'])) {
            $this->db->query('
                UPDATE collective_agreement_leave_types SET
                    code = :code, name = :name, description = :description, legal_reference = :legal_reference,
                    category = :category, is_paid = :is_paid, requires_certificate = :requires_certificate'
                    . $approvalSql . ',
                    max_days_per_year = :max_year, max_days_per_event = :max_event, min_notice_days = :min_notice,
                    day_count_mode = :day_count_mode, sort_order = :sort_order, is_active = :is_active, notes = :notes
                WHERE id = :id AND agreement_id = :aid
            ');
            $this->db->bind(':id', (int)$data['id']);
            $this->db->bind(':aid', (int)$data['agreement_id']);
        } else {
            $insertApprovalCols = $this->leaveTypeRequiresApprovalReady() ? ', requires_approval' : '';
            $insertApprovalVals = $this->leaveTypeRequiresApprovalReady() ? ', :requires_approval' : '';
            $this->db->query('
                INSERT INTO collective_agreement_leave_types
                    (agreement_id, code, name, description, legal_reference, category, is_paid, requires_certificate'
                    . $insertApprovalCols . ',
                     max_days_per_year, max_days_per_event, min_notice_days, day_count_mode, sort_order, is_active, notes)
                VALUES
                    (:aid, :code, :name, :description, :legal_reference, :category, :is_paid, :requires_certificate'
                    . $insertApprovalVals . ',
                     :max_year, :max_event, :min_notice, :day_count_mode, :sort_order, :is_active, :notes)
            ');
            $this->db->bind(':aid', (int)$data['agreement_id']);
        }
        $this->db->bind(':code', strtoupper(trim($data['code'] ?? '')));
        $this->db->bind(':name', trim($data['name'] ?? ''));
        $this->db->bind(':description', trim($data['description'] ?? '') ?: null);
        $this->db->bind(':legal_reference', trim($data['legal_reference'] ?? '') ?: null);
        $this->db->bind(':category', $data['category'] ?? 'other');
        $this->db->bind(':is_paid', !empty($data['is_paid']) ? 1 : 0);
        $this->db->bind(':requires_certificate', !empty($data['requires_certificate']) ? 1 : 0);
        if ($this->leaveTypeRequiresApprovalReady()) {
            $this->db->bind(':requires_approval', $requiresApproval);
        }
        $this->db->bind(':max_year', $maxYear === '' ? null : (float)$maxYear);
        $this->db->bind(':max_event', $maxEvent === '' ? null : (float)$maxEvent);
        $this->db->bind(':min_notice', $minNotice === '' ? null : (int)$minNotice);
        $this->db->bind(':day_count_mode', $data['day_count_mode'] ?? 'calendar');
        $this->db->bind(':sort_order', (int)($data['sort_order'] ?? 0));
        $this->db->bind(':is_active', !isset($data['is_active']) || !empty($data['is_active']) ? 1 : 0);
        $this->db->bind(':notes', trim($data['notes'] ?? '') ?: null);
        return $this->db->execute();
    }

    public function deleteLeaveType($agreementId, $leaveTypeId) {
        if (!$this->leaveTypesReady()) {
            return false;
        }
        $this->db->query('DELETE FROM collective_agreement_leave_types WHERE id = :id AND agreement_id = :aid');
        $this->db->bind(':id', (int)$leaveTypeId);
        $this->db->bind(':aid', (int)$agreementId);
        return $this->db->execute();
    }
}
