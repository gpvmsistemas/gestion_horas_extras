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
}
