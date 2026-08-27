<?php
// ----------------------------------------------------------------------
// ARCHIVO: app/models/WorkSchedule.php (CON MÉTODO FALTANTE AÑADIDO)
// ----------------------------------------------------------------------

class WorkSchedule {
    private $db;

    public function __construct($db = null){
        $this->db = $db instanceof Database ? $db : new Database;
    }

    /**
     * Obtiene todas las entradas de horario para un rango de fechas.
     */
    public function getScheduleEntriesForPeriod($companyId, $startDate, $endDate, $branchId = null) {
        $branchReady = $this->isBranchScheduleReady();
        $multipleBranches = (new User())->isMultipleBranchAssignmentsReady();
        $branchWhere = ($branchReady && (int)$branchId > 0)
            ? ($multipleBranches
                ? ' AND (es.branch_id = :branch_id OR (es.branch_id IS NULL AND EXISTS (SELECT 1 FROM employee_branch_assignments eba WHERE eba.user_id = u.id AND eba.branch_id = :legacy_branch_id)))'
                : ' AND COALESCE(es.branch_id, u.branch_id) = :branch_id')
            : '';
        $sql = "SELECT 
                    es.*, 
                    s.shift_name, s.color 
                FROM employee_schedules es
                LEFT JOIN shifts s ON es.shift_id = s.id
                JOIN users u ON es.user_id = u.id
                WHERE u.company_id = :company_id 
                AND es.schedule_date BETWEEN :start_date AND :end_date
                {$branchWhere}
                ORDER BY es.start_time ASC";
        
        $this->db->query($sql);
        $this->db->bind(':company_id', $companyId);
        $this->db->bind(':start_date', $startDate);
        $this->db->bind(':end_date', $endDate);
        if ($branchReady && (int)$branchId > 0) {
            $this->db->bind(':branch_id', (int)$branchId);
            if ($multipleBranches) {
                $this->db->bind(':legacy_branch_id', (int)$branchId);
            }
        }
        
        return $this->db->resultSet();
    }

    /**
     * Borra todas las entradas de un día para un usuario y luego inserta las nuevas.
     */
    public function saveDaySchedule($userId, $date, $entries, $branchId = null) {
        $ownTx = !$this->db->inTransaction();
        if ($ownTx) {
            $this->db->beginTransaction();
        }
        try {
            $hasBranch = $this->isBranchScheduleReady() && (int)$branchId > 0;
            $this->db->query($hasBranch
                ? 'DELETE FROM employee_schedules WHERE user_id = :user_id AND schedule_date = :schedule_date AND branch_id = :branch_id'
                : 'DELETE FROM employee_schedules WHERE user_id = :user_id AND schedule_date = :schedule_date');
            $this->db->bind(':user_id', $userId);
            $this->db->bind(':schedule_date', $date);
            if ($hasBranch) $this->db->bind(':branch_id', (int)$branchId);
            $this->db->execute();

            if (!empty($entries)) {
                $this->db->query($hasBranch
                    ? 'INSERT INTO employee_schedules (user_id, schedule_date, shift_id, start_time, end_time, type, notes, branch_id) VALUES (:user_id, :schedule_date, :shift_id, :start_time, :end_time, :type, :notes, :branch_id)'
                    : 'INSERT INTO employee_schedules (user_id, schedule_date, shift_id, start_time, end_time, type, notes) VALUES (:user_id, :schedule_date, :shift_id, :start_time, :end_time, :type, :notes)');
                
                foreach ($entries as $entry) {
                    $this->db->bind(':user_id', $userId);
                    $this->db->bind(':schedule_date', $date);
                    $this->db->bind(':shift_id', ($entry['shift_id'] > 0) ? $entry['shift_id'] : NULL);
                    $this->db->bind(':start_time', !empty($entry['start_time']) ? $entry['start_time'] : NULL);
                    $this->db->bind(':end_time', !empty($entry['end_time']) ? $entry['end_time'] : NULL);
                    $this->db->bind(':type', $entry['type']);
                    $this->db->bind(':notes', $entry['notes']);
                    if ($hasBranch) {
                        $this->db->bind(':branch_id', (int)$branchId > 0 ? (int)$branchId : null);
                    }
                    $this->db->execute();
                }
            }
            if ($ownTx) {
                $this->db->commit();
            }
            return true;
        } catch (Exception $e) {
            if ($ownTx) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function isBranchScheduleReady() {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $this->db->query("SHOW COLUMNS FROM employee_schedules LIKE 'branch_id'");
            $ready = (bool)$this->db->single();
        } catch (Throwable $e) {
            $ready = false;
        }
        return $ready;
    }

    /**
     * MÉTODO CORREGIDO: Ahora el parámetro $companyId es opcional.
     * Si no se proporciona, se busca en la base de datos.
     */
    public function getUserScheduleForCurrentWeek($userId, $companyId = null) {
        // Si no se pasa el companyId, lo buscamos usando el modelo de Usuario.
        // Esto hace que la función sea más robusta ante llamadas de controladores antiguos.
        if (is_null($companyId)) {
            $userModel = new User(); // Asumimos que el autoloader del framework carga el modelo User.
            $user = $userModel->getUserById($userId);
            if ($user && isset($user->company_id)) {
                $companyId = $user->company_id;
            } else {
                // Si no se encuentra el usuario o su compañía, devolvemos un array vacío para evitar errores.
                return array();
            }
        }

        // Calcular el primer y último día de la semana actual (Lunes a Domingo)
        $today = new DateTime();
        $dayOfWeek = $today->format('N'); // 1 (para Lunes) a 7 (para Domingo)
        $startDate = clone $today;
        $startDate->modify('-' . ($dayOfWeek - 1) . ' days');
        $endDate = clone $startDate;
        $endDate->modify('+6 days');

        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');

        // Reutilizamos el método que ya existe y funciona
        $allEntries = $this->getScheduleEntriesForPeriod($companyId, $startDateStr, $endDateStr);

        // Filtramos las entradas solo para el usuario solicitado
        $userEntries = array();
        foreach($allEntries as $entry){
            if($entry->user_id == $userId){
                $userEntries[] = $entry;
            }
        }
        return $userEntries;
    }

    public function getUserScheduleForMonth($userId, $companyId, $month) {
        $startDate = date('Y-m-01', strtotime($month));
        $endDate = date('Y-m-t', strtotime($month));

        $sql = "SELECT 
                    es.*, 
                    s.shift_name, s.color 
                FROM employee_schedules es
                LEFT JOIN shifts s ON es.shift_id = s.id
                JOIN users u ON es.user_id = u.id
                WHERE u.company_id = :company_id 
                AND es.user_id = :user_id
                AND es.schedule_date BETWEEN :start_date AND :end_date
                ORDER BY es.schedule_date ASC, es.start_time ASC";
        
        $this->db->query($sql);
        $this->db->bind(':company_id', $companyId);
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':start_date', $startDate);
        $this->db->bind(':end_date', $endDate);
        
        $results = $this->db->resultSet();

        $schedulesByDate = [];
        foreach ($results as $row) {
            $schedulesByDate[$row->schedule_date][] = $row;
        }
        return $schedulesByDate;
    }

    public function getDashboardScheduleSummary($companyId, $startDate, $endDate) {
            $sql = "SELECT schedule_date, type, start_time, end_time 
                    FROM employee_schedules es
                    JOIN users u ON es.user_id = u.id
                    WHERE u.company_id = :company_id 
                    AND es.schedule_date BETWEEN :start_date AND :end_date";
            $this->db->query($sql);
            $this->db->bind(':company_id', $companyId);
            $this->db->bind(':start_date', $startDate);
            $this->db->bind(':end_date', $endDate);
            return $this->db->resultSet();
        }

        public function getWhoIsWorkingNow($companyId) {
            $sql = $this->scheduleSelectWithResolvedTimes('u.full_name, s.shift_name') . "
                    JOIN users u ON u.id = es.user_id
                    WHERE u.company_id = :p_company_id
                    AND es.schedule_date = CURDATE()
                    AND (
                        (es.start_time IS NOT NULL AND es.end_time IS NOT NULL
                         AND CURRENT_TIME() BETWEEN es.start_time AND es.end_time)
                        OR (str.shift_id IS NOT NULL
                         AND CURRENT_TIME() BETWEEN str.first_start AND str.last_end)
                    )
                    ORDER BY COALESCE(es.start_time, str.first_start)";
            $this->db->query($sql);
            $this->db->bind(':p_company_id', $companyId);
            return $this->db->resultSet();
        }

        public function countWorkingNowByCompany($companyId) {
            $sql = "SELECT COUNT(DISTINCT es.user_id) AS count
                FROM employee_schedules es
                JOIN users u ON es.user_id = u.id
                LEFT JOIN (
                    SELECT shift_id,
                           MIN(start_time) AS first_start,
                           MAX(end_time) AS last_end
                    FROM shift_time_ranges
                    GROUP BY shift_id
                ) str ON str.shift_id = es.shift_id
                WHERE u.company_id = :p_company_id
                AND es.schedule_date = CURDATE()
                AND (
                    (es.start_time IS NOT NULL AND es.end_time IS NOT NULL
                     AND CURRENT_TIME() BETWEEN es.start_time AND es.end_time)
                    OR (str.shift_id IS NOT NULL
                     AND CURRENT_TIME() BETWEEN str.first_start AND str.last_end)
                )";
            $this->db->query($sql);
            $this->db->bind(':p_company_id', $companyId);
            $row = $this->db->single();
            return $row ? (int)$row->count : 0;
        }

    public function getSchedulesForUserOnDate($userId, $date) {
        $this->db->query($this->scheduleSelectWithResolvedTimes() . "
            WHERE es.user_id = :user_id
              AND es.schedule_date = :schedule_date
              AND (
                  (es.start_time IS NOT NULL AND es.end_time IS NOT NULL)
                  OR str.shift_id IS NOT NULL
              )
            ORDER BY COALESCE(es.start_time, str.first_start) ASC
        ");
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':schedule_date', $date);
        return $this->db->resultSet();
    }

    /**
     * Todas las entradas del planificador para un día (incluye vacaciones, licencias, etc.).
     */
    public function getPlannerEntriesForUserOnDate($userId, $date, $branchId = null) {
        $branchWhere = ($this->isBranchScheduleReady() && (int)$branchId > 0) ? ' AND es.branch_id = :branch_id' : '';
        $this->db->query($this->scheduleSelectWithResolvedTimes() . "
            WHERE es.user_id = :user_id
              AND es.schedule_date = :schedule_date
              {$branchWhere}
            ORDER BY COALESCE(es.start_time, str.first_start) ASC, es.id ASC
        ");
        $this->db->bind(':user_id', (int)$userId);
        $this->db->bind(':schedule_date', $date);
        if ($branchWhere !== '') $this->db->bind(':branch_id', (int)$branchId);
        return $this->db->resultSet();
    }

    public function getScheduleEntryById($scheduleId, $companyId = null) {
        $sql = $this->scheduleSelectWithResolvedTimes('es.*, u.full_name, u.company_id') . "
                JOIN users u ON u.id = es.user_id
                WHERE es.id = :schedule_id";
        if ($companyId !== null) {
            $sql .= ' AND u.company_id = :p_company_id';
        }
        $this->db->query($sql);
        $this->db->bind(':schedule_id', $scheduleId);
        if ($companyId !== null) {
            $this->db->bind(':p_company_id', $companyId);
        }
        return $this->db->single();
    }

    public function getUpcomingSchedulesForUser($userId, $companyId, $fromDate = null, $toDate = null) {
        $fromDate = $fromDate ?: date('Y-m-d');
        $toDate = $toDate ?: date('Y-m-d', strtotime('+60 days'));

        $this->db->query($this->scheduleSelectWithResolvedTimes() . "
            WHERE es.user_id = :user_id
              AND es.schedule_date BETWEEN :from_date AND :to_date
              AND (
                  (es.start_time IS NOT NULL AND es.end_time IS NOT NULL)
                  OR str.shift_id IS NOT NULL
              )
            ORDER BY es.schedule_date ASC, COALESCE(es.start_time, str.first_start) ASC
        ");
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':from_date', $fromDate);
        $this->db->bind(':to_date', $toDate);
        return $this->db->resultSet();
    }

    /**
     * SELECT base: horarios con horas propias o tomadas del turno (shift_time_ranges).
     */
    private function scheduleSelectWithResolvedTimes($esColumns = 'es.*') {
        return "SELECT {$esColumns}, s.shift_name, s.color,
                COALESCE(es.start_time, str.first_start) AS start_time,
                COALESCE(es.end_time, str.last_end) AS end_time
            FROM employee_schedules es
            LEFT JOIN shifts s ON s.id = es.shift_id
            LEFT JOIN (
                SELECT shift_id,
                       MIN(start_time) AS first_start,
                       MAX(end_time) AS last_end
                FROM shift_time_ranges
                GROUP BY shift_id
            ) str ON str.shift_id = es.shift_id";
    }

    public function getUsersApproachingWeeklyLimit($companyId, $threshold = 0.8) {
        $today = new DateTime();
        $dayOfWeek = $today->format('N'); // 1 (para Lunes) a 7 (para Domingo)
        $startDate = clone $today;
        $startDate->modify('-' . ($dayOfWeek - 1) . ' days');
        $endDate = clone $startDate;
        $endDate->modify('+6 days');

        $sql = "SELECT 
                    u.id, 
                    u.full_name, 
                    u.profile_picture, 
                    u.weekly_hour_limit, 
                    SUM(
                        CASE
                            WHEN es.start_time IS NOT NULL AND es.end_time IS NOT NULL
                                THEN TIME_TO_SEC(TIMEDIFF(es.end_time, es.start_time))
                            WHEN str.shift_id IS NOT NULL
                                THEN TIME_TO_SEC(TIMEDIFF(str.last_end, str.first_start))
                            ELSE 0
                        END
                    ) / 3600 AS hours_worked
                FROM employee_schedules es
                JOIN users u ON es.user_id = u.id
                LEFT JOIN (
                    SELECT shift_id,
                           MIN(start_time) AS first_start,
                           MAX(end_time) AS last_end
                    FROM shift_time_ranges
                    GROUP BY shift_id
                ) str ON str.shift_id = es.shift_id
                WHERE u.company_id = :p_company_id
                AND es.schedule_date BETWEEN :start_date AND :end_date
                AND u.weekly_hour_limit > 0
                AND (
                    (es.start_time IS NOT NULL AND es.end_time IS NOT NULL)
                    OR str.shift_id IS NOT NULL
                )
                GROUP BY u.id, u.full_name, u.profile_picture, u.weekly_hour_limit
                HAVING hours_worked >= (u.weekly_hour_limit * :threshold)";

        $this->db->query($sql);
        $this->db->bind(':p_company_id', $companyId);
        $this->db->bind(':start_date', $startDate->format('Y-m-d'));
        $this->db->bind(':end_date', $endDate->format('Y-m-d'));
        $this->db->bind(':threshold', $threshold);

        return $this->db->resultSet();
    }

    /**
     * Convierte filas de employee_schedules al formato de saveDaySchedule.
     */
    public function entriesToPlannerArrays($rows) {
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'type' => $row->type ?? 'shift',
                'shift_id' => !empty($row->shift_id) ? (int)$row->shift_id : null,
                'start_time' => $row->start_time ?? null,
                'end_time' => $row->end_time ?? null,
                'notes' => $row->notes ?? null,
            ];
        }
        return $out;
    }

    public function dayHasScheduleEntries($userId, $date, $branchId = null) {
        $branchWhere = ($this->isBranchScheduleReady() && (int)$branchId > 0) ? ' AND branch_id = :branch_id' : '';
        $this->db->query('SELECT 1 FROM employee_schedules WHERE user_id = :uid AND schedule_date = :d' . $branchWhere . ' LIMIT 1');
        $this->db->bind(':uid', (int)$userId);
        $this->db->bind(':d', $date);
        if ($branchWhere !== '') $this->db->bind(':branch_id', (int)$branchId);
        $this->db->single();
        return $this->db->rowCount() > 0;
    }

    /**
     * Copia el patrón de la semana de referencia (Lun–Dom) a cada fecha del rango [from, to].
     * @return array{ok:bool,message:string,schedules:array,days_updated:int}
     */
    public function buildWeekPatternApplyPayload($userId, $refWeekStart, $from, $to, $overwrite = true, $branchId = 0) {
        $userId = (int)$userId;
        if ($userId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $refWeekStart)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            return ['ok' => false, 'message' => 'Fechas o usuario inválidos.', 'schedules' => [], 'days_updated' => 0];
        }
        if ($from > $to) {
            $tmp = $from;
            $from = $to;
            $to = $tmp;
        }
        $daysSpan = (int)((strtotime($to) - strtotime($from)) / 86400) + 1;
        if ($daysSpan > 93) {
            return ['ok' => false, 'message' => 'El rango no puede superar 93 días.', 'schedules' => [], 'days_updated' => 0];
        }

        $byDow = [];
        for ($i = 0; $i < 7; $i++) {
            $d = date('Y-m-d', strtotime($refWeekStart . " +{$i} days"));
            $dow = (int)date('N', strtotime($d));
            $rows = $this->getPlannerEntriesForUserOnDate($userId, $d, $branchId);
            $byDow[$dow] = $this->entriesToPlannerArrays($rows);
        }

        $userSchedules = [];
        $daysUpdated = 0;
        $cursor = $from;
        while ($cursor <= $to) {
            $dow = (int)date('N', strtotime($cursor));
            $pattern = $byDow[$dow] ?? [];
            if (!$overwrite && $this->dayHasScheduleEntries($userId, $cursor, $branchId)) {
                $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
                continue;
            }
            $userSchedules[$cursor] = $pattern;
            $daysUpdated++;
            $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
        }

        if ($daysUpdated === 0) {
            return ['ok' => false, 'message' => 'No hay días para actualizar (activá sobrescribir o ampliá el rango).', 'schedules' => [], 'days_updated' => 0];
        }

        return [
            'ok' => true,
            'message' => '',
            'schedules' => [$userId => $userSchedules],
            'days_updated' => $daysUpdated,
        ];
    }

    /** Copia una jornada base a todos los lunes a viernes de un rango. */
    public function buildFixedWeekdayApplyPayload($userId, $sourceDate, $from, $to, $overwrite = true, $branchId = 0) {
        $userId = (int)$userId;
        foreach ([$sourceDate, $from, $to] as $date) {
            if ($userId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date)) {
                return ['ok'=>false, 'message'=>'Empleado o fechas inválidas.', 'schedules'=>[], 'days_updated'=>0];
            }
        }
        if ($from > $to) { [$from, $to] = [$to, $from]; }
        if ((int)((strtotime($to) - strtotime($from)) / 86400) + 1 > 93) {
            return ['ok'=>false, 'message'=>'El rango no puede superar 93 días.', 'schedules'=>[], 'days_updated'=>0];
        }
        $sourceEntries = $this->getPlannerEntriesForUserOnDate($userId, $sourceDate, $branchId);
        $pattern = $this->entriesToPlannerArrays($sourceEntries);
        if (empty($pattern)) {
            return ['ok'=>false, 'message'=>'La jornada base no tiene horarios para copiar.', 'schedules'=>[], 'days_updated'=>0];
        }
        $userSchedules = [];
        $daysUpdated = 0;
        for ($cursor = $from; $cursor <= $to; $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'))) {
            if ((int)date('N', strtotime($cursor)) > 5) continue;
            if (!$overwrite && $this->dayHasScheduleEntries($userId, $cursor, $branchId)) continue;
            $userSchedules[$cursor] = $pattern;
            $daysUpdated++;
        }
        if ($daysUpdated === 0) {
            return ['ok'=>false, 'message'=>'No hay días hábiles para actualizar en el rango.', 'schedules'=>[], 'days_updated'=>0];
        }
        return ['ok'=>true, 'message'=>'', 'schedules'=>[$userId => $userSchedules], 'days_updated'=>$daysUpdated];
    }

}
?>
