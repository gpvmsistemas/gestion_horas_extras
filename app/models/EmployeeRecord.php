<?php

/** Datos ampliados del legajo; se activa únicamente cuando su migración está instalada. */
class EmployeeRecord {
    private $db;

    public function __construct($db = null) {
        $this->db = $db instanceof Database ? $db : new Database();
    }

    public function isReady() {
        try {
            $this->db->query("SHOW TABLES LIKE 'employee_company_assignments'");
            return (bool)$this->db->single();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function getFormData($userId, $companyId) {
        if (!$this->isReady()) return ['ready' => false];
        $this->db->query('SELECT * FROM employee_company_assignments WHERE user_id = ? AND company_id = ? ORDER BY is_primary DESC, id DESC LIMIT 1');
        $assignment = $this->db->single([(int)$userId, (int)$companyId]);
        $this->db->query('SELECT * FROM employee_addresses WHERE user_id = ? ORDER BY is_primary DESC, id DESC LIMIT 1');
        $address = $this->db->single([(int)$userId]);
        $this->db->query('SELECT ehc.*, hi.display_name AS insurer_name, hip.name AS plan_name
            FROM employee_health_coverages ehc
            INNER JOIN health_insurers hi ON hi.id = ehc.health_insurer_id
            LEFT JOIN health_insurance_plans hip ON hip.id = ehc.health_plan_id
            WHERE ehc.user_id = ? ORDER BY ehc.is_primary DESC, ehc.id DESC LIMIT 1');
        $coverage = $this->db->single([(int)$userId]);
        return [
            'ready' => true,
            'assignment' => $assignment,
            'address' => $address,
            'coverage' => $coverage,
            'assignments' => $this->getAssignments($userId),
            'coverages' => $this->getCoverages($userId),
            'positions' => $this->getPositions($companyId),
            'insurers' => $this->getInsurers(),
            'plans' => $this->getPlans(),
            'supervisors' => $this->getSupervisors($companyId),
        ];
    }

    /** Snapshot agregado para /admin/users; evita consultas N+1 por empleado. */
    public function getUserListMetadata($companyId = null) {
        if (!$this->isReady()) return [];
        $branchSelect = "'' AS branch_names, 0 AS branch_count";
        if ($this->tableExists('employee_branch_assignments')) {
            $branchSelect = "(SELECT GROUP_CONCAT(b.name ORDER BY eba.is_primary DESC,b.name SEPARATOR ' · ')
                    FROM employee_branch_assignments eba INNER JOIN company_branches b ON b.id=eba.branch_id
                    WHERE eba.user_id=u.id) AS branch_names,
                (SELECT COUNT(*) FROM employee_branch_assignments ebc WHERE ebc.user_id=u.id) AS branch_count";
        }
        $sql = "SELECT u.id AS user_id, eca.employee_number, eca.status AS employment_status,
                eca.work_mode, eca.employment_type, eca.start_date, eca.end_date,
                jp.name AS position_name, a.name AS area_name, su.full_name AS supervisor_name,
                {$branchSelect},
                EXISTS(SELECT 1 FROM employee_addresses ea WHERE ea.user_id=u.id AND ea.is_primary=1) AS has_structured_address,
                EXISTS(SELECT 1 FROM employee_health_coverages ehc WHERE ehc.user_id=u.id AND ehc.is_primary=1 AND ehc.status IN ('activa','en_tramite')) AS has_health_coverage
            FROM users u
            LEFT JOIN employee_company_assignments eca ON eca.id=(
                SELECT e2.id FROM employee_company_assignments e2
                WHERE e2.user_id=u.id AND e2.company_id=u.company_id
                ORDER BY e2.is_primary DESC,e2.id DESC LIMIT 1
            )
            LEFT JOIN job_positions jp ON jp.id=eca.position_id
            LEFT JOIN areas a ON a.id=eca.area_id
            LEFT JOIN users su ON su.id=eca.supervisor_user_id";
        $params = [];
        if ((int)$companyId > 0) {
            $sql .= ' WHERE u.company_id=?';
            $params[] = (int)$companyId;
        }
        $this->db->query($sql);
        $rows = $this->db->resultSet($params);
        $map = [];
        foreach ($rows as $row) $map[(int)$row->user_id] = $row;
        return $map;
    }

    private function tableExists($table) {
        try {
            $this->db->query('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
            return (bool)$this->db->single([(string)$table]);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function getAssignments($userId) {
        if (!$this->isReady()) return [];
        $this->db->query('SELECT eca.*, c.name AS company_name, a.name AS area_name, jp.name AS position_name,
                su.full_name AS supervisor_name
            FROM employee_company_assignments eca
            INNER JOIN companies c ON c.id = eca.company_id
            LEFT JOIN areas a ON a.id = eca.area_id
            LEFT JOIN job_positions jp ON jp.id = eca.position_id
            LEFT JOIN users su ON su.id = eca.supervisor_user_id
            WHERE eca.user_id = ? ORDER BY eca.is_primary DESC, eca.start_date DESC, eca.id DESC');
        return $this->db->resultSet([(int)$userId]);
    }

    public function getCoverages($userId) {
        if (!$this->isReady()) return [];
        $this->db->query('SELECT ehc.*, hi.display_name AS insurer_name, hip.name AS plan_name
            FROM employee_health_coverages ehc
            INNER JOIN health_insurers hi ON hi.id = ehc.health_insurer_id
            LEFT JOIN health_insurance_plans hip ON hip.id = ehc.health_plan_id
            WHERE ehc.user_id = ? ORDER BY ehc.is_primary DESC, ehc.start_date DESC, ehc.id DESC');
        return $this->db->resultSet([(int)$userId]);
    }

    public function getPositions($companyId) {
        if (!$this->isReady()) return [];
        $this->db->query('SELECT * FROM job_positions WHERE is_active = 1 AND (company_id IS NULL OR company_id = ?) ORDER BY name');
        return $this->db->resultSet([(int)$companyId]);
    }

    public function getInsurers() {
        if (!$this->isReady()) return [];
        $this->db->query('SELECT * FROM health_insurers WHERE is_active = 1 ORDER BY display_name');
        return $this->db->resultSet();
    }

    public function getPlans() {
        if (!$this->isReady()) return [];
        $this->db->query('SELECT * FROM health_insurance_plans WHERE is_active = 1 ORDER BY health_insurer_id, name');
        return $this->db->resultSet();
    }

    private function getSupervisors($companyId) {
        $this->db->query("SELECT id,full_name,role FROM users WHERE company_id=? AND is_active=1 AND role IN ('supervisor','admin') ORDER BY full_name");
        return $this->db->resultSet([(int)$companyId]);
    }

    public function savePosition($companyId, $name, $description = '') {
        if (!$this->isReady() || (int)$companyId <= 0 || trim($name) === '') return false;
        $this->db->query('INSERT INTO job_positions (company_id,name,description) VALUES (?,?,?) ON DUPLICATE KEY UPDATE description=VALUES(description),is_active=1');
        return $this->db->execute([(int)$companyId, mb_substr(trim($name),0,120), mb_substr(trim($description),0,500) ?: null]);
    }

    public function saveInsurer($displayName, $legalName, $type) {
        if (!$this->isReady() || trim($displayName) === '') return false;
        if (!in_array($type, ['obra_social','prepaga','mutual','otra'], true)) $type = 'otra';
        $legalName = trim($legalName) ?: trim($displayName);
        $this->db->query('INSERT INTO health_insurers (legal_name,display_name,insurer_type) VALUES (?,?,?) ON DUPLICATE KEY UPDATE legal_name=VALUES(legal_name),insurer_type=VALUES(insurer_type),is_active=1,updated_at=NOW()');
        return $this->db->execute([mb_substr($legalName,0,180),mb_substr(trim($displayName),0,120),$type]);
    }

    public function savePlan($insurerId, $name, $code = '') {
        if (!$this->isReady() || (int)$insurerId <= 0 || trim($name) === '') return false;
        $this->db->query('SELECT 1 FROM health_insurers WHERE id=? AND is_active=1');
        if (!$this->db->single([(int)$insurerId])) return false;
        $this->db->query('INSERT INTO health_insurance_plans (health_insurer_id,code,name) VALUES (?,?,?) ON DUPLICATE KEY UPDATE code=VALUES(code),is_active=1');
        return $this->db->execute([(int)$insurerId,mb_substr(trim($code),0,50)?:null,mb_substr(trim($name),0,120)]);
    }

    public static function fromPost(array $post) {
        $text = function ($key, $max = 255) use ($post) {
            return mb_substr(trim((string)($post[$key] ?? '')), 0, $max);
        };
        return [
            'employee_number' => $text('employee_number', 50),
            'position_id' => max(0, (int)($post['position_id'] ?? 0)),
            'employment_type' => $text('employment_type', 20),
            'work_mode' => $text('work_mode', 20),
            'employment_status' => $text('employment_status', 20),
            'seniority_date' => $text('seniority_date', 10),
            'employment_end_date' => $text('employment_end_date', 10),
            'termination_reason' => $text('termination_reason'),
            'cost_center' => $text('cost_center', 80),
            'supervisor_user_id' => max(0, (int)($post['supervisor_user_id'] ?? 0)),
            'street' => $text('street', 140), 'street_number' => $text('street_number', 30),
            'floor_unit' => $text('floor_unit', 40), 'neighborhood' => $text('neighborhood', 100),
            'postal_code' => $text('postal_code', 20), 'locality' => $text('locality', 120),
            'administrative_area' => $text('administrative_area', 120), 'province' => $text('province', 120),
            'country_code' => strtoupper($text('country_code', 2)) ?: 'AR',
            'reference_notes' => $text('reference_notes'),
            'latitude' => $text('latitude', 20), 'longitude' => $text('longitude', 20),
            'address_verification_status' => $text('address_verification_status', 20),
            'health_insurer_id' => max(0, (int)($post['health_insurer_id'] ?? 0)),
            'health_plan_id' => max(0, (int)($post['health_plan_id'] ?? 0)),
            'affiliate_number' => $text('affiliate_number', 80),
            'health_member_role' => $text('health_member_role', 20),
            'health_status' => $text('health_status', 20),
            'health_start_date' => $text('health_start_date', 10),
            'health_end_date' => $text('health_end_date', 10),
            'contribution_redirected' => !empty($post['contribution_redirected']) ? 1 : 0,
        ];
    }

    public function validate(array $data, $companyId, $userId) {
        $errors = [];
        $allowed = [
            'employment_type' => ['permanente','plazo_fijo','eventual','temporario','practica','otro'],
            'work_mode' => ['presencial','hibrido','remoto'],
            'employment_status' => ['preingreso','activo','licencia','suspendido','finalizado'],
            'address_verification_status' => ['pendiente','confirmada','manual'],
            'health_member_role' => ['titular','adherente'],
            'health_status' => ['en_tramite','activa','suspendida','finalizada'],
        ];
        foreach ($allowed as $key => $values) if (!in_array($data[$key], $values, true)) $errors[$key] = 'Valor no válido.';
        foreach (['seniority_date','employment_end_date','health_start_date','health_end_date'] as $key) {
            if ($data[$key] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data[$key])) $errors[$key] = 'Fecha no válida.';
        }
        if ($data['seniority_date'] !== '' && $data['employment_end_date'] !== '' && $data['employment_end_date'] < $data['seniority_date']) $errors['employment_end_date'] = 'La finalización no puede ser anterior a la antigüedad reconocida.';
        if ($data['health_start_date'] !== '' && $data['health_end_date'] !== '' && $data['health_end_date'] < $data['health_start_date']) $errors['health_end_date'] = 'La baja no puede ser anterior al alta.';
        if ($data['latitude'] !== '' && (!is_numeric($data['latitude']) || (float)$data['latitude'] < -90 || (float)$data['latitude'] > 90)) $errors['latitude'] = 'Latitud no válida.';
        if ($data['longitude'] !== '' && (!is_numeric($data['longitude']) || (float)$data['longitude'] < -180 || (float)$data['longitude'] > 180)) $errors['longitude'] = 'Longitud no válida.';
        if ($data['employment_status'] === 'finalizado' && $data['employment_end_date'] === '') $errors['employment_end_date'] = 'Indicá la fecha de finalización.';
        if ($data['position_id'] > 0) {
            $this->db->query('SELECT 1 FROM job_positions WHERE id = ? AND is_active = 1 AND (company_id IS NULL OR company_id = ?)');
            if (!$this->db->single([$data['position_id'], (int)$companyId])) $errors['position_id'] = 'El puesto no pertenece a la empresa.';
        }
        if ($data['supervisor_user_id'] === (int)$userId) $errors['supervisor_user_id'] = 'El empleado no puede supervisarse a sí mismo.';
        if ($data['supervisor_user_id'] > 0) {
            $this->db->query("SELECT 1 FROM users WHERE id=? AND company_id=? AND is_active=1 AND role IN ('supervisor','admin')");
            if (!$this->db->single([$data['supervisor_user_id'], (int)$companyId])) $errors['supervisor_user_id'] = 'El supervisor no pertenece a la empresa o no tiene un rol válido.';
        }
        if ($data['health_plan_id'] > 0) {
            if ($data['health_insurer_id'] <= 0) {
                $errors['health_plan_id'] = 'Seleccioná primero la cobertura.';
            } else {
                $this->db->query('SELECT 1 FROM health_insurance_plans WHERE id=? AND health_insurer_id=? AND is_active=1');
                if (!$this->db->single([$data['health_plan_id'],$data['health_insurer_id']])) $errors['health_plan_id'] = 'El plan no pertenece a la cobertura seleccionada.';
            }
        }
        return $errors;
    }

    public function save($userId, $companyId, $areaId, $agreementId, $hireDate, array $data) {
        if (!$this->isReady()) return true;
        $this->db->beginTransaction();
        try {
            $this->db->query('SELECT id FROM employee_company_assignments WHERE user_id = ? AND company_id = ? AND is_primary = 1 ORDER BY id DESC LIMIT 1');
            $existing = $this->db->single([(int)$userId, (int)$companyId]);
            $assignmentParams = [$data['employee_number'] ?: null, $areaId ?: null, $data['position_id'] ?: null, $agreementId ?: null,
                $data['employment_type'], $data['work_mode'], $data['employment_status'], $hireDate ?: null,
                $data['seniority_date'] ?: ($hireDate ?: null), $data['employment_end_date'] ?: null,
                $data['termination_reason'] ?: null, $data['cost_center'] ?: null, $data['supervisor_user_id'] ?: null];
            if ($existing) {
                $this->db->query('UPDATE employee_company_assignments SET employee_number=?,area_id=?,position_id=?,agreement_id=?,employment_type=?,work_mode=?,status=?,start_date=?,seniority_date=?,end_date=?,termination_reason=?,cost_center=?,supervisor_user_id=?,updated_at=NOW() WHERE id=?');
                $this->db->execute(array_merge($assignmentParams, [(int)$existing->id]));
                $assignmentId = (int)$existing->id;
            } else {
                $this->db->query('UPDATE employee_company_assignments SET is_primary=0 WHERE user_id=?');
                $this->db->execute([(int)$userId]);
                $this->db->query('INSERT INTO employee_company_assignments (user_id,company_id,employee_number,area_id,position_id,agreement_id,employment_type,work_mode,status,start_date,seniority_date,end_date,termination_reason,cost_center,supervisor_user_id,is_primary) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)');
                $this->db->execute(array_merge([(int)$userId, (int)$companyId], $assignmentParams));
                $assignmentId = (int)$this->db->lastInsertId();
            }
            $this->saveAddress($userId, $data);
            $this->saveCoverage($userId, $assignmentId, $data);
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    private function saveAddress($userId, array $d) {
        $hasAddress = $d['street'] !== '' || $d['locality'] !== '' || $d['latitude'] !== '' || $d['longitude'] !== '';
        if (!$hasAddress) return;
        $this->db->query('SELECT id FROM employee_addresses WHERE user_id=? AND is_primary=1 ORDER BY id DESC LIMIT 1');
        $row = $this->db->single([(int)$userId]);
        $original = trim($d['street'] . ' ' . $d['street_number'] . ', ' . $d['locality'] . ', ' . $d['province'], ' ,');
        $params = [$d['street']?:null,$d['street_number']?:null,$d['floor_unit']?:null,$d['neighborhood']?:null,$d['postal_code']?:null,
            $d['locality']?:null,$d['administrative_area']?:null,$d['province']?:null,$d['country_code'],$d['reference_notes']?:null,$original?:null,
            $d['latitude']!==''?(float)$d['latitude']:null,$d['longitude']!==''?(float)$d['longitude']:null,$d['address_verification_status'],
            $d['address_verification_status']!=='pendiente'?date('Y-m-d H:i:s'):null];
        if ($row) {
            $this->db->query('UPDATE employee_addresses SET street=?,street_number=?,floor_unit=?,neighborhood=?,postal_code=?,locality=?,administrative_area=?,province=?,country_code=?,reference_notes=?,original_text=?,latitude=?,longitude=?,verification_status=?,verified_at=?,updated_at=NOW() WHERE id=?');
            $this->db->execute(array_merge($params, [(int)$row->id]));
        } else {
            $this->db->query('INSERT INTO employee_addresses (user_id,street,street_number,floor_unit,neighborhood,postal_code,locality,administrative_area,province,country_code,reference_notes,original_text,latitude,longitude,verification_status,verified_at,is_primary) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)');
            $this->db->execute(array_merge([(int)$userId], $params));
        }
        if ($original !== '') {
            $this->db->query('UPDATE users SET address=? WHERE id=?');
            $this->db->execute([$original, (int)$userId]);
        }
    }

    private function saveCoverage($userId, $assignmentId, array $d) {
        if ($d['health_insurer_id'] <= 0) return;
        $this->db->query('SELECT id FROM employee_health_coverages WHERE user_id=? AND is_primary=1 ORDER BY id DESC LIMIT 1');
        $row = $this->db->single([(int)$userId]);
        $params = [$assignmentId,$d['health_insurer_id'],$d['health_plan_id']?:null,$d['affiliate_number']?:null,$d['health_member_role'],
            $d['contribution_redirected'],$d['health_status'],$d['health_start_date']?:null,$d['health_end_date']?:null];
        if ($row) {
            $this->db->query('UPDATE employee_health_coverages SET employee_company_assignment_id=?,health_insurer_id=?,health_plan_id=?,affiliate_number=?,member_role=?,contribution_redirected=?,status=?,start_date=?,end_date=?,updated_at=NOW() WHERE id=?');
            $this->db->execute(array_merge($params, [(int)$row->id]));
        } else {
            $this->db->query('INSERT INTO employee_health_coverages (user_id,employee_company_assignment_id,health_insurer_id,health_plan_id,affiliate_number,member_role,contribution_redirected,status,start_date,end_date,is_primary) VALUES (?,?,?,?,?,?,?,?,?,?,1)');
            $this->db->execute(array_merge([(int)$userId], $params));
        }
    }
}
