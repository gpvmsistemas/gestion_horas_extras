<?php

class VacationBalance {
    private $db;

    public function __construct($db = null) {
        $this->db = $db instanceof Database ? $db : new Database();
    }

    public function isReady() {
        return vacation_module_ready();
    }

    public function getPeriodsByUser($userId, $openOnly = false) {
        $sql = 'SELECT vbp.*, ca.name AS agreement_name, ca.code AS agreement_code
                FROM vacation_balance_periods vbp
                JOIN collective_agreements ca ON ca.id = vbp.agreement_id
                WHERE vbp.user_id = :uid';
        if ($openOnly) {
            $sql .= " AND vbp.status = 'open' AND vbp.days_pending > 0
                AND (vbp.balance_type <> 'conventional_credit' OR vbp.expires_at IS NULL OR vbp.expires_at >= CURDATE())";
        }
        $sql .= ' ORDER BY vbp.period_start DESC, vbp.id DESC';
        $this->db->query($sql);
        $this->db->bind(':uid', (int)$userId);
        return $this->db->resultSet();
    }

    public function getOpenPeriodsForUpdate($userId) {
        $this->db->query("SELECT vbp.*, ca.code AS agreement_code
            FROM vacation_balance_periods vbp
            JOIN collective_agreements ca ON ca.id = vbp.agreement_id
            WHERE vbp.user_id = :uid AND vbp.status = 'open' AND vbp.days_pending > 0
              AND (vbp.balance_type <> 'conventional_credit' OR vbp.expires_at IS NULL OR vbp.expires_at >= CURDATE())
            ORDER BY vbp.period_start ASC,
                     CASE WHEN vbp.expires_at IS NULL THEN 1 ELSE 0 END ASC,
                     vbp.expires_at ASC, vbp.id ASC
            FOR UPDATE");
        $this->db->bind(':uid', (int)$userId);
        return $this->db->resultSet();
    }

    public function getPeriodByUserLabel($userId, $periodLabel, $balanceType = null) {
        $sql = 'SELECT * FROM vacation_balance_periods WHERE user_id = :uid AND period_label = :lbl';
        if ($balanceType !== null) {
            $sql .= ' AND balance_type = :balance_type';
        }
        $sql .= ' ORDER BY id ASC LIMIT 1';
        $this->db->query($sql);
        $this->db->bind(':uid', (int)$userId);
        $this->db->bind(':lbl', $periodLabel);
        if ($balanceType !== null) {
            $this->db->bind(':balance_type', $balanceType);
        }
        return $this->db->single();
    }

    public function getPeriodByUserLabelForUpdate($userId, $periodLabel, $balanceType) {
        $this->db->query('SELECT * FROM vacation_balance_periods
            WHERE user_id=:uid AND period_label=:lbl AND balance_type=:balance_type LIMIT 1 FOR UPDATE');
        $this->db->bind(':uid', (int)$userId);
        $this->db->bind(':lbl', $periodLabel);
        $this->db->bind(':balance_type', $balanceType);
        return $this->db->single();
    }

    public function getPeriodByIdForUpdate($id) {
        $this->db->query('SELECT * FROM vacation_balance_periods WHERE id = :id FOR UPDATE');
        $this->db->bind(':id', (int)$id);
        return $this->db->single();
    }

    public function getExpiredCreditsForUpdate($userId = null) {
        $sql = "SELECT * FROM vacation_balance_periods
            WHERE balance_type='conventional_credit' AND status='open' AND days_pending>0
              AND expires_at IS NOT NULL AND expires_at < CURDATE()";
        if ($userId !== null) $sql .= ' AND user_id=:uid';
        $sql .= ' ORDER BY user_id,expires_at,id FOR UPDATE';
        $this->db->query($sql);
        if ($userId !== null) $this->db->bind(':uid', (int)$userId);
        return $this->db->resultSet();
    }

    public function closeExpiredCredit($periodId) {
        $this->db->query("UPDATE vacation_balance_periods SET adjustment_days=days_taken-days_entitled,
            days_pending=0,status='closed' WHERE id=:id");
        $this->db->bind(':id', (int)$periodId);
        return $this->db->execute();
    }

    public function updatePeriodExpiry($periodId, $expiresAt) {
        $this->db->query('UPDATE vacation_balance_periods SET expires_at=:expires WHERE id=:id');
        $this->db->bind(':expires', $expiresAt);
        $this->db->bind(':id', (int)$periodId);
        return $this->db->execute();
    }

    public function convertPeriod($periodId, $targetMode, $targetPending, $notes) {
        $period = $this->getPeriodByIdForUpdate($periodId);
        if (!$period) {
            return false;
        }
        $adjustment = (float)$targetPending + (float)$period->days_taken - (float)$period->days_entitled;
        $this->db->query('UPDATE vacation_balance_periods SET count_mode_snapshot=:mode,
            adjustment_days=:adjustment, days_pending=:pending, status=:status, origin_notes=:notes WHERE id=:id');
        $this->db->bind(':mode', $targetMode);
        $this->db->bind(':adjustment', $adjustment);
        $this->db->bind(':pending', (float)$targetPending);
        $this->db->bind(':status', (float)$targetPending > 0 ? 'open' : 'closed');
        $this->db->bind(':notes', $notes);
        $this->db->bind(':id', (int)$periodId);
        return $this->db->execute() ? $period : false;
    }

    public function getPeriodById($id) {
        $this->db->query('SELECT * FROM vacation_balance_periods WHERE id = :id');
        $this->db->bind(':id', (int)$id);
        return $this->db->single();
    }

    public function getTotalPending($userId) {
        $this->db->query("SELECT COALESCE(SUM(days_pending), 0) AS total
            FROM vacation_balance_periods
            WHERE user_id = :uid AND status = 'open' AND days_pending > 0
              AND (balance_type <> 'conventional_credit' OR expires_at IS NULL OR expires_at >= CURDATE())");
        $this->db->bind(':uid', (int)$userId);
        $row = $this->db->single();
        return $row ? (float)$row->total : 0.0;
    }

    public function createPeriod(array $data) {
        $adjustment = (float)($data['adjustment_days'] ?? 0);
        $taken = (float)($data['days_taken'] ?? 0);
        $pending = max(0, (float)$data['days_entitled'] + $adjustment - $taken);
        $status = $pending > 0 ? ($data['status'] ?? 'open') : 'closed';
        $this->db->query('INSERT INTO vacation_balance_periods
            (user_id, period_label, period_start, period_end, balance_type, agreement_id,
             agreement_rule_id, count_mode_snapshot, days_entitled, days_taken, adjustment_days,
             days_pending, status, expires_at, origin_notes, liquidated_at, liquidated_by)
            VALUES (:uid,:lbl,:ps,:pe,:btype,:aid,:rid,:mode,:ent,:taken,:adjustment,
                    :pending,:status,:expires,:notes,:liq_at,:liq_by)');
        $this->db->bind(':uid', (int)$data['user_id']);
        $this->db->bind(':lbl', $data['period_label']);
        $this->db->bind(':ps', $data['period_start']);
        $this->db->bind(':pe', $data['period_end']);
        $this->db->bind(':btype', $data['balance_type'] ?? 'annual');
        $this->db->bind(':aid', (int)$data['agreement_id']);
        $this->db->bind(':rid', $data['agreement_rule_id'] ?? null);
        $this->db->bind(':mode', $data['count_mode_snapshot'] ?? 'calendar');
        $this->db->bind(':ent', (float)$data['days_entitled']);
        $this->db->bind(':taken', $taken);
        $this->db->bind(':adjustment', $adjustment);
        $this->db->bind(':pending', $pending);
        $this->db->bind(':status', $status);
        $this->db->bind(':expires', $data['expires_at'] ?? null);
        $this->db->bind(':notes', $data['origin_notes'] ?? null);
        $this->db->bind(':liq_at', $data['liquidated_at'] ?? null);
        $this->db->bind(':liq_by', $data['liquidated_by'] ?? null);
        return $this->db->execute() ? (int)$this->db->lastInsertId() : 0;
    }

    public function updatePeriodBalances($periodId, $daysEntitled, $daysTaken, $adjustmentDays = null) {
        $period = $this->getPeriodById($periodId);
        if (!$period) {
            return false;
        }
        $adjustment = $adjustmentDays === null ? (float)($period->adjustment_days ?? 0) : (float)$adjustmentDays;
        $pending = max(0, (float)$daysEntitled + $adjustment - (float)$daysTaken);
        $this->db->query('UPDATE vacation_balance_periods SET days_entitled=:ent, days_taken=:taken,
            adjustment_days=:adjustment, days_pending=:pending, status=:status WHERE id=:id');
        $this->db->bind(':ent', (float)$daysEntitled);
        $this->db->bind(':taken', (float)$daysTaken);
        $this->db->bind(':adjustment', $adjustment);
        $this->db->bind(':pending', $pending);
        $this->db->bind(':status', $pending <= 0 ? 'closed' : 'open');
        $this->db->bind(':id', (int)$periodId);
        return $this->db->execute();
    }

    /**
     * Corrige fechas / convenio / regla de un período ya existente (p. ej. re-liquidación).
     */
    public function updatePeriodLiquidationMeta($periodId, array $meta) {
        $period = $this->getPeriodById($periodId);
        if (!$period) {
            return false;
        }
        $this->db->query('UPDATE vacation_balance_periods
            SET period_start = :ps, period_end = :pe, agreement_id = :aid, agreement_rule_id = :rid,
                count_mode_snapshot = :mode, liquidated_at = :liq_at, liquidated_by = :liq_by
            WHERE id = :id');
        $this->db->bind(':ps', $meta['period_start'] ?? $period->period_start);
        $this->db->bind(':pe', $meta['period_end'] ?? $period->period_end);
        $this->db->bind(':aid', (int)($meta['agreement_id'] ?? $period->agreement_id));
        $this->db->bind(':rid', isset($meta['agreement_rule_id']) ? (int)$meta['agreement_rule_id'] : ($period->agreement_rule_id !== null ? (int)$period->agreement_rule_id : null));
        $this->db->bind(':mode', $meta['count_mode_snapshot'] ?? $period->count_mode_snapshot);
        $this->db->bind(':liq_at', $meta['liquidated_at'] ?? date('Y-m-d H:i:s'));
        $this->db->bind(':liq_by', $meta['liquidated_by'] ?? null);
        $this->db->bind(':id', (int)$periodId);
        return $this->db->execute();
    }

    public function addMovement(array $data) {
        $datesJson = null;
        if (isset($data['schedule_dates'])) {
            $datesJson = is_string($data['schedule_dates']) ? $data['schedule_dates']
                : json_encode(array_values($data['schedule_dates']));
        }
        $snapshotJson = isset($data['schedule_snapshot'])
            ? (is_string($data['schedule_snapshot']) ? $data['schedule_snapshot'] : json_encode($data['schedule_snapshot']))
            : null;
        $this->db->query('INSERT INTO vacation_balance_movements
            (period_id,user_id,movement_type,source,days,request_id,operation_key,schedule_dates,schedule_snapshot,notes,created_by)
            VALUES (:pid,:uid,:mtype,:source,:days,:request,:opkey,:dates,:snapshot,:notes,:created_by)');
        $this->db->bind(':pid', (int)$data['period_id']);
        $this->db->bind(':uid', (int)$data['user_id']);
        $this->db->bind(':mtype', $data['movement_type']);
        $this->db->bind(':source', $data['source']);
        $this->db->bind(':days', (float)$data['days']);
        $this->db->bind(':request', $data['request_id'] ?? null);
        $this->db->bind(':opkey', $data['operation_key'] ?? null);
        $this->db->bind(':dates', $datesJson);
        $this->db->bind(':snapshot', $snapshotJson);
        $this->db->bind(':notes', $data['notes'] ?? null);
        $this->db->bind(':created_by', (int)$data['created_by']);
        return $this->db->execute();
    }

    public function getTakeMovementsForRequest($requestId, $forUpdate = false) {
        $sql = "SELECT m.*, p.days_taken, p.days_entitled, p.adjustment_days
            FROM vacation_balance_movements m
            JOIN vacation_balance_periods p ON p.id = m.period_id
            WHERE m.request_id = :rid AND m.movement_type = 'take'
            ORDER BY m.id ASC" . ($forUpdate ? ' FOR UPDATE' : '');
        $this->db->query($sql);
        $this->db->bind(':rid', (int)$requestId);
        return $this->db->resultSet();
    }

    public function getMovementsByUser($userId, $limit = 50) {
        $this->db->query('SELECT m.*, p.period_label FROM vacation_balance_movements m
            JOIN vacation_balance_periods p ON p.id=m.period_id
            WHERE m.user_id=:uid ORDER BY m.created_at DESC, m.id DESC LIMIT ' . (int)$limit);
        $this->db->bind(':uid', (int)$userId);
        return $this->db->resultSet();
    }

    public function syncUserVacationCache($userId) {
        return (new User($this->db))->updateVacationBalance($userId, round($this->getTotalPending($userId), 2));
    }

    public function getPendingReport(array $filters) {
        $where = ['1=1'];
        $having = [];
        $bind = [];
        // Aislamiento organizacional: limita el reporte a las empresas del
        // grupo del admin (lo inyecta vacationReportFilters cuando hay lock).
        if (!empty($filters['company_ids']) && is_array($filters['company_ids'])) {
            $ids = array_map('intval', $filters['company_ids']);
            $where[] = 'u.company_id IN (' . implode(',', $ids ?: [0]) . ')';
        }
        foreach (['company_id'=>'u.company_id','area_id'=>'u.area_id'] as $key=>$column) {
            if (!empty($filters[$key])) {
                $where[] = $column . ' = :' . $key;
                $bind[':' . $key] = (int)$filters[$key];
            }
        }
        if (!empty($filters['agreement_id'])) {
            $where[] = 'COALESCE(u.agreement_id,a.agreement_id,cad.agreement_id) = :agreement_id';
            $bind[':agreement_id'] = (int)$filters['agreement_id'];
        }
        if (($filters['active'] ?? 'active') !== 'all') {
            $where[] = 'u.is_active = :is_active';
            $bind[':is_active'] = ($filters['active'] ?? 'active') === 'inactive' ? 0 : 1;
        }
        if (!empty($filters['search'])) {
            $where[] = '(u.full_name LIKE :search_name OR u.document_number LIKE :search_doc OR u.cuil LIKE :search_cuil)';
            $search = '%' . $filters['search'] . '%';
            $bind[':search_name'] = $search;
            $bind[':search_doc'] = $search;
            $bind[':search_cuil'] = $search;
        }
        if (!empty($filters['no_agreement'])) {
            $where[] = 'COALESCE(u.agreement_id,a.agreement_id,cad.agreement_id) IS NULL';
        }
        if (!empty($filters['no_hire'])) {
            $where[] = 'u.hire_date IS NULL';
        }

        $balanceConditions = ["vbp.status='open'", 'vbp.days_pending>0',
            "(vbp.balance_type<>'conventional_credit' OR vbp.expires_at IS NULL OR vbp.expires_at>=CURDATE())"];
        if (!empty($filters['period'])) {
            $balanceConditions[] = 'vbp.period_label = :period';
            $bind[':period'] = $filters['period'];
        }
        if (!empty($filters['balance_type'])) {
            $balanceConditions[] = 'vbp.balance_type = :balance_type';
            $bind[':balance_type'] = $filters['balance_type'];
        }
        if (!empty($filters['historical_only'])) {
            $balanceConditions[] = "(vbp.balance_type='historical' OR vbp.period_start < :current_year_start)";
            $bind[':current_year_start'] = date('Y-01-01');
        }
        if (!empty($filters['expiring_only'])) {
            $balanceConditions[] = 'vbp.expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)';
        }
        $balanceMatch = implode(' AND ', $balanceConditions);
        if (($filters['balance_status'] ?? 'both') === 'with') {
            $having[] = 'total_pending > 0';
        } elseif (($filters['balance_status'] ?? 'both') === 'without') {
            $having[] = 'total_pending = 0';
        }
        if (!empty($filters['no_liquidation'])) {
            $having[] = 'has_current_liquidation = 0';
        }
        if (($filters['min_days'] ?? '') !== '') {
            $having[] = 'total_pending >= :min_days';
            $bind[':min_days'] = (float)$filters['min_days'];
        }
        if (($filters['max_days'] ?? '') !== '') {
            $having[] = 'total_pending <= :max_days';
            $bind[':max_days'] = (float)$filters['max_days'];
        }

        $currentPeriodLabel = preg_match('/^\d{4}(?:-\d{4})?$/', (string)($filters['current_period'] ?? ''))
            ? (string)$filters['current_period']
            : (string)date('Y');

        $select = "SELECT u.id AS user_id,u.full_name,u.document_number,u.hire_date,u.is_active,
            u.company_id,c.name AS company_name,u.area_id,a.name AS area_name,
            COALESCE(u.agreement_id,a.agreement_id,cad.agreement_id) AS effective_agreement_id,
            ca.code AS agreement_code,ca.name AS agreement_name,
            COALESCE(SUM(CASE WHEN $balanceMatch THEN vbp.days_pending ELSE 0 END),0) AS total_pending,
            COALESCE(SUM(CASE WHEN $balanceMatch AND (vbp.balance_type='historical' OR vbp.period_start < '" . date('Y-01-01') . "') THEN vbp.days_pending ELSE 0 END),0) AS historical_pending,
            COALESCE(SUM(CASE WHEN $balanceMatch AND vbp.balance_type='annual' AND vbp.period_label=:current_period_lbl THEN vbp.days_pending ELSE 0 END),0) AS current_pending,
            MIN(CASE WHEN $balanceMatch THEN vbp.period_start END) AS oldest_period,
            MIN(CASE WHEN $balanceMatch THEN vbp.expires_at END) AS next_expiry,
            MAX(CASE WHEN vbp.balance_type='conventional_credit' AND vbp.status='open'
                AND vbp.days_pending>0 AND vbp.expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                THEN 1 ELSE 0 END) AS has_expiring_credit,
            MAX(CASE WHEN vbp.balance_type='annual' AND vbp.period_label=:current_period_lbl2 THEN 1 ELSE 0 END) AS has_current_liquidation
            FROM users u JOIN companies c ON c.id=u.company_id
            LEFT JOIN areas a ON a.id=u.area_id
            LEFT JOIN company_agreement_defaults cad ON cad.company_id=u.company_id
            LEFT JOIN collective_agreements ca ON ca.id=COALESCE(u.agreement_id,a.agreement_id,cad.agreement_id)
            LEFT JOIN vacation_balance_periods vbp ON vbp.user_id=u.id
            WHERE " . implode(' AND ', $where) . " GROUP BY u.id,u.full_name,u.document_number,u.hire_date,u.is_active,
            u.company_id,c.name,u.area_id,a.name,effective_agreement_id,ca.code,ca.name";
        if ($having) {
            $select .= ' HAVING ' . implode(' AND ', $having);
        }
        $baseSql = $select;
        $orders = [
            'pending_asc'=>'total_pending ASC','name'=>'u.full_name ASC','company'=>'c.name ASC,u.full_name ASC',
            'agreement'=>'ca.name ASC,u.full_name ASC','oldest'=>'oldest_period ASC','expiry'=>'next_expiry ASC',
        ];
        $select .= ' ORDER BY ' . ($orders[$filters['sort'] ?? ''] ?? 'total_pending DESC,u.full_name ASC');

        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(10, min(200, (int)($filters['per_page'] ?? 50)));
        $allSql = $select;
        if (empty($filters['export'])) {
            $select .= ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage);
        }
        $bind[':current_period_lbl'] = $currentPeriodLabel;
        $bind[':current_period_lbl2'] = $currentPeriodLabel;
        $this->db->query($select);
        foreach ($bind as $key=>$value) {
            $this->db->bind($key, $value);
        }
        $rows = $this->db->resultSet();

        $countSql = 'SELECT COUNT(*) AS total FROM (' . $baseSql . ') report_rows';
        $this->db->query($countSql);
        foreach ($bind as $key=>$value) {
            $this->db->bind($key, $value);
        }
        $countRow = $this->db->single();
        $details = $this->getPeriodDetailsForUsers(array_map(function($r){ return (int)$r->user_id; }, $rows), $filters);
        return ['rows'=>$rows,'details'=>$details,'total'=>(int)($countRow->total ?? 0),'page'=>$page,'per_page'=>$perPage];
    }

    private function getPeriodDetailsForUsers(array $userIds, array $filters = []) {
        if (!$userIds) {
            return [];
        }
        $ids = array_values(array_unique(array_map('intval', $userIds)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where = ["vbp.user_id IN ($placeholders)", "vbp.status='open'", 'vbp.days_pending>0',
            "(vbp.balance_type<>'conventional_credit' OR vbp.expires_at IS NULL OR vbp.expires_at>=CURDATE())"];
        $params = $ids;
        if (!empty($filters['period'])) {
            $where[] = 'vbp.period_label = ?';
            $params[] = $filters['period'];
        }
        if (!empty($filters['balance_type'])) {
            $where[] = 'vbp.balance_type = ?';
            $params[] = $filters['balance_type'];
        }
        if (!empty($filters['historical_only'])) {
            $where[] = "(vbp.balance_type='historical' OR vbp.period_start < ?)";
            $params[] = date('Y-01-01');
        }
        if (!empty($filters['expiring_only'])) {
            $where[] = 'vbp.expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)';
        }
        $this->db->query("SELECT vbp.*,ca.code AS agreement_code FROM vacation_balance_periods vbp
            JOIN collective_agreements ca ON ca.id=vbp.agreement_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY vbp.user_id,vbp.period_start ASC,vbp.id ASC");
        $rows = $this->db->resultSet($params);
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int)$row->user_id][] = $row;
        }
        return $grouped;
    }
}
