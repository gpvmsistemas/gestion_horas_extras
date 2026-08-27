<?php

class MarcacionesCache {
    private $db;

    public function __construct() {
        $this->db = new Database;
    }

    private function clockScopeReady() {
        static $ready = null;
        if ($ready !== null) return $ready;
        try { $this->db->query("SHOW TABLES LIKE 'clock_devices'"); $ready = (bool)$this->db->single(); }
        catch (Throwable $e) { $ready = false; }
        return $ready;
    }

    public function existsBySerial($serialNo) {
        $this->db->query('SELECT id FROM marcaciones_cache WHERE event_serial_no = :serial');
        $this->db->bind(':serial', $serialNo);
        $this->db->single();
        return $this->db->rowCount() > 0;
    }

    public function updatePersonNameIfEmpty($serialNo, $personName) {
        $personName = trim($personName);
        if ($personName === '') {
            return false;
        }
        $this->db->query(
            'UPDATE marcaciones_cache SET person_name = :person_name
             WHERE event_serial_no = :serial
               AND (person_name IS NULL OR TRIM(person_name) = \'\')'
        );
        $this->db->bind(':person_name', $personName);
        $this->db->bind(':serial', $serialNo);
        return $this->db->execute();
    }

    public function updatePersonNameIfEmptyByEmployee($employeeId, $personName, $deviceName = null) {
        $personName = trim($personName);
        $employeeId = trim((string)$employeeId);
        if ($personName === '' || $employeeId === '') {
            return false;
        }
        $sql = 'UPDATE marcaciones_cache SET person_name = :person_name
                WHERE employee_id = :employee_id
                  AND (person_name IS NULL OR TRIM(person_name) = \'\')';
        if ($deviceName !== null && $deviceName !== '') {
            $sql .= ' AND device_name = :device_name';
        }
        $this->db->query($sql);
        $this->db->bind(':person_name', $personName);
        $this->db->bind(':employee_id', $employeeId);
        if ($deviceName !== null && $deviceName !== '') {
            $this->db->bind(':device_name', $deviceName);
        }
        return $this->db->execute();
    }

    public function insert($data) {
        $this->db->query('INSERT INTO marcaciones_cache (
            api_event_id, employee_id, person_name, user_id, event_time,
            device_name, direction, direction_label, event_serial_no, sync_batch_id
        ) VALUES (
            :api_event_id, :employee_id, :person_name, :user_id, :event_time,
            :device_name, :direction, :direction_label, :event_serial_no, :sync_batch_id
        )');
        $this->db->bind(':api_event_id', $data['api_event_id'] ?? null);
        $this->db->bind(':employee_id', $data['employee_id']);
        $this->db->bind(':person_name', $data['person_name'] ?? null);
        $this->db->bind(':user_id', $data['user_id'] ?? null);
        $this->db->bind(':event_time', $data['event_time']);
        $this->db->bind(':device_name', $data['device_name'] ?? null);
        $this->db->bind(':direction', $data['direction'] ?? null);
        $this->db->bind(':direction_label', $data['direction_label'] ?? null);
        $this->db->bind(':event_serial_no', $data['event_serial_no']);
        $this->db->bind(':sync_batch_id', $data['sync_batch_id'] ?? null);
        return $this->db->execute();
    }

    /**
     * @param array $filters start_date, end_date, device_name, employee_id, person_q, mapped, direction, branch_id
     */
    public function getAll($filters = [], $companyId = null) {
        $query = 'SELECT mc.*, u.full_name, c.name AS company_name, b.name AS employee_branch_name,
                  (SELECT GROUP_CONCAT(cb2.name ORDER BY cb2.name SEPARATOR ", ") FROM clock_devices cd2
                   JOIN clock_device_branches cdb2 ON cdb2.clock_device_id = cd2.id AND cdb2.is_active = 1
                   JOIN company_branches cb2 ON cb2.id = cdb2.branch_id WHERE cd2.external_name = mc.device_name) AS clock_branch_names
                  FROM marcaciones_cache mc
                  LEFT JOIN users u ON mc.user_id = u.id
                  LEFT JOIN companies c ON c.id = u.company_id
                  LEFT JOIN company_branches b ON b.id = u.branch_id
                  WHERE 1=1';

        if ($companyId !== null && $this->clockScopeReady()) {
            $query .= ' AND (u.company_id = :company_id OR (mc.user_id IS NULL AND EXISTS (
                SELECT 1 FROM clock_devices cd JOIN clock_device_branches cdb ON cdb.clock_device_id = cd.id AND cdb.is_active = 1
                JOIN company_branches cb ON cb.id = cdb.branch_id
                WHERE cd.external_name = mc.device_name AND cb.company_id = :company_id
            )))';
        } elseif ($companyId !== null) {
            $query .= ' AND (mc.user_id IS NULL OR u.company_id = :company_id)';
        }
        if (!empty($filters['start_date'])) {
            $query .= ' AND DATE(mc.event_time) >= :start_date';
        }
        if (!empty($filters['end_date'])) {
            $query .= ' AND DATE(mc.event_time) <= :end_date';
        }
        if (!empty($filters['device_name'])) {
            $query .= ' AND mc.device_name = :device_name';
        }
        if (!empty($filters['employee_id'])) {
            $query .= ' AND mc.employee_id = :employee_id';
        }
        if (!empty($filters['person_q'])) {
            $query .= ' AND (
                u.full_name LIKE :person_q1
                OR mc.person_name LIKE :person_q2
                OR mc.employee_id LIKE :person_q3
            )';
        }
        if (!empty($filters['direction'])) {
            $query .= ' AND mc.direction = :direction';
        }
        if (!empty($filters['branch_id']) && $this->clockScopeReady()) {
            $query .= ' AND (u.branch_id = :branch_id OR EXISTS (
                SELECT 1 FROM employee_branch_assignments eba WHERE eba.user_id = mc.user_id AND eba.branch_id = :branch_user_id
            ) OR EXISTS (
                SELECT 1 FROM clock_devices cd JOIN clock_device_branches cdb ON cdb.clock_device_id = cd.id AND cdb.is_active = 1
                WHERE cd.external_name = mc.device_name AND cdb.branch_id = :branch_id
            ))';
        }
        if (($filters['mapped'] ?? '') === 'yes') {
            $query .= ' AND mc.user_id IS NOT NULL';
        } elseif (($filters['mapped'] ?? '') === 'no') {
            $query .= ' AND mc.user_id IS NULL';
        }

        $query .= ' ORDER BY mc.event_time DESC';

        $this->db->query($query);
        if ($companyId !== null) {
            $this->db->bind(':company_id', $companyId);
        }
        if (!empty($filters['start_date'])) {
            $this->db->bind(':start_date', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $this->db->bind(':end_date', $filters['end_date']);
        }
        if (!empty($filters['device_name'])) {
            $this->db->bind(':device_name', $filters['device_name']);
        }
        if (!empty($filters['employee_id'])) {
            $this->db->bind(':employee_id', $filters['employee_id']);
        }
        if (!empty($filters['person_q'])) {
            $like = '%' . $filters['person_q'] . '%';
            $this->db->bind(':person_q1', $like);
            $this->db->bind(':person_q2', $like);
            $this->db->bind(':person_q3', $like);
        }
        if (!empty($filters['direction'])) {
            $this->db->bind(':direction', $filters['direction']);
        }
        if (!empty($filters['branch_id']) && $this->clockScopeReady()) {
            $this->db->bind(':branch_id', (int)$filters['branch_id']);
            $this->db->bind(':branch_user_id', (int)$filters['branch_id']);
        }

        return $this->db->resultSet();
    }

    public function getDistinctDevices($companyId = null) {
        $sql = 'SELECT DISTINCT mc.device_name
                FROM marcaciones_cache mc
                LEFT JOIN users u ON mc.user_id = u.id
                WHERE mc.device_name IS NOT NULL';
        if ($companyId !== null && $this->clockScopeReady()) {
            $sql .= ' AND (u.company_id = :company_id OR (mc.user_id IS NULL AND EXISTS (
                SELECT 1 FROM clock_devices cd JOIN clock_device_branches cdb ON cdb.clock_device_id = cd.id AND cdb.is_active = 1
                JOIN company_branches cb ON cb.id = cdb.branch_id
                WHERE cd.external_name = mc.device_name AND cb.company_id = :company_id
            )))';
        } elseif ($companyId !== null) {
            $sql .= ' AND (mc.user_id IS NULL OR u.company_id = :company_id)';
        }
        $sql .= ' ORDER BY mc.device_name';
        $this->db->query($sql);
        if ($companyId !== null) {
            $this->db->bind(':company_id', $companyId);
        }
        return $this->db->resultSet();
    }

    public function getStats($filters = [], $companyId = null) {
        $rows = $this->getAll($filters, $companyId);
        $total = count($rows);
        $mapped = 0;
        $entrada = 0;
        $salida = 0;
        $personKeys = [];
        $dayKeys = [];

        foreach ($rows as $r) {
            if ($r->user_id) {
                $mapped++;
            }
            if (($r->direction ?? '') === 'P10') {
                $entrada++;
            } elseif (($r->direction ?? '') === 'P20') {
                $salida++;
            }
            $pk = !empty($r->user_id) ? 'u' . $r->user_id : 'e' . ($r->employee_id ?? '');
            $personKeys[$pk] = true;
            $dayKeys[date('Y-m-d', strtotime($r->event_time)) . '|' . $pk] = true;
        }

        return [
            'total'        => $total,
            'mapped'       => $mapped,
            'unmapped'     => $total - $mapped,
            'entrada'      => $entrada,
            'salida'       => $salida,
            'persons'      => count($personKeys),
            'person_days'  => count($dayKeys),
        ];
    }

    /** Legajos en caché sin user_id (agrupados). */
    public function getUnmappedLegajosGrouped($companyId = null, $sinceDays = 90) {
        $since = date('Y-m-d', strtotime('-' . max(1, (int)$sinceDays) . ' days'));
        $sql = 'SELECT mc.employee_id,
                       MAX(mc.person_name) AS person_name,
                       MAX(mc.device_name) AS device_name,
                       COUNT(*) AS punch_count,
                       MIN(mc.event_time) AS first_seen,
                       MAX(mc.event_time) AS last_seen
                FROM marcaciones_cache mc
                LEFT JOIN users u ON mc.user_id = u.id
                WHERE mc.user_id IS NULL
                  AND mc.employee_id IS NOT NULL
                  AND TRIM(mc.employee_id) <> \'\'
                  AND DATE(mc.event_time) >= :since
        ';
        if ($companyId !== null && $this->clockScopeReady()) {
            $sql .= ' AND EXISTS (SELECT 1 FROM clock_devices cd
                JOIN clock_device_branches cdb ON cdb.clock_device_id = cd.id AND cdb.is_active = 1
                JOIN company_branches cb ON cb.id = cdb.branch_id
                WHERE cd.external_name = mc.device_name AND cb.company_id = :company_id)';
        }
        $sql .= ' GROUP BY mc.employee_id, mc.device_name ORDER BY punch_count DESC, last_seen DESC';
        $this->db->query($sql);
        $this->db->bind(':since', $since);
        if ($companyId !== null && $this->clockScopeReady()) $this->db->bind(':company_id', (int)$companyId);
        return $this->db->resultSet();
    }

    public function countUnmappedLegajos($companyId = null, $sinceDays = 90) {
        return count($this->getUnmappedLegajosGrouped($companyId, $sinceDays));
    }

    /** Al mapear, relaciona también las fichadas importadas previamente de ese dispositivo. */
    public function assignMappedUser($employeeId, $deviceName, $userId) {
        $sql = 'UPDATE marcaciones_cache SET user_id = :user_id WHERE employee_id = :employee_id';
        if (trim((string)$deviceName) !== '') $sql .= ' AND device_name = :device_name';
        $this->db->query($sql);
        $this->db->bind(':user_id', (int)$userId);
        $this->db->bind(':employee_id', (string)$employeeId);
        if (trim((string)$deviceName) !== '') $this->db->bind(':device_name', trim((string)$deviceName));
        return $this->db->execute();
    }

    public function getDeviceNamesForUserDay($userId, $workDate) {
        $this->db->query('SELECT DISTINCT device_name FROM marcaciones_cache WHERE user_id = ? AND DATE(event_time) = ? AND device_name IS NOT NULL ORDER BY device_name');
        return array_map(function ($row) { return $row->device_name; }, $this->db->resultSet([(int)$userId, $workDate]));
    }
}
