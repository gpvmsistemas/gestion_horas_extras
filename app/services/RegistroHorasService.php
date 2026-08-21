<?php

/**
 * Registro de Horas (Suite P&M) — lógica real del módulo compartido.
 *
 * Persiste en employee_schedules (bloques type='custom' con branch_name),
 * así los horarios conviven con el planificador, calendario y asistencia.
 * Porta la lógica de hoursapp-moderna (record_hours/recalculate_daily_extra_hours):
 *  - verificación de horas ya cargadas en el día, con confirmación;
 *  - bloqueo por vacaciones/licencia (bloques vacation/leave del día);
 *  - turnos que cruzan medianoche divididos en dos días (hasta 23:59 / desde 00:00);
 *  - extras por organización: Moderna = umbral diario acumulado 8h L-V / 5h S-D,
 *    feriado (empresa o local por ciudad) => todas las horas del día son extra;
 *    Paviotti = las extras siguen siendo los bloques type='overtime' (50/100 aparte).
 */
class RegistroHorasService {

    /** Tipos de bloque que cuentan como "horario de trabajo" cargado. */
    const WORK_TYPES = ['shift', 'custom', 'overtime'];

    private $db;

    public function __construct($db = null) {
        $this->db = ($db instanceof Database) ? $db : new Database();
    }

    /** La migración migration_registro_horas.sql ya corrió (patrón SHOW COLUMNS de la suite). */
    public static function isSchemaReady() {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $db = new Database();
            $db->query("SHOW COLUMNS FROM employee_schedules LIKE 'branch_name'");
            $ready = (bool)$db->single();
        } catch (Throwable $e) {
            $ready = false;
        }
        return $ready;
    }

    // ─────────────────────────── Lectura ───────────────────────────

    /** Empleados activos de una empresa, con su sucursal (company_branches o área) resuelta. */
    public function getActiveEmployees($companyId) {
        return $this->getActiveEmployeesForCompanies([(int)$companyId]);
    }

    /**
     * Empleados activos de un conjunto de empresas (el Registro de Horas opera
     * sobre TODO el grupo organizacional: RRHH carga horas para cualquiera de
     * sus sociedades sin importar la empresa activa).
     */
    public function getActiveEmployeesForCompanies(array $companyIds) {
        $companyIds = array_values(array_filter(array_map('intval', $companyIds)));
        if (empty($companyIds)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($companyIds), '?'));
        try {
            $this->db->query(
                "SELECT u.id, u.full_name, u.company_id, u.area_id, u.branch_id,
                        cb.name AS branch_name, a.name AS area_name
                 FROM users u
                 LEFT JOIN company_branches cb ON cb.id = u.branch_id
                 LEFT JOIN areas a ON a.id = u.area_id
                 WHERE u.company_id IN ($ph) AND u.is_active = 1 AND u.role = 'empleado'
                 ORDER BY cb.name IS NULL, cb.name ASC, a.name IS NULL, a.name ASC, u.full_name ASC"
            );
            return $this->db->resultSet($companyIds);
        } catch (Throwable $e) {
            // Esquema sin users.branch_id/company_branches (pre-migración)
            $this->db->query(
                "SELECT u.id, u.full_name, u.company_id, u.area_id, NULL AS branch_id,
                        NULL AS branch_name, a.name AS area_name
                 FROM users u
                 LEFT JOIN areas a ON a.id = u.area_id
                 WHERE u.company_id IN ($ph) AND u.is_active = 1 AND u.role = 'empleado'
                 ORDER BY a.name IS NULL, a.name ASC, u.full_name ASC"
            );
            return $this->db->resultSet($companyIds);
        }
    }

    /** Bloques del día de un empleado, ordenados por hora de inicio. */
    public function getDayBlocks($userId, $date) {
        $this->db->query(
            'SELECT id, user_id, schedule_date, shift_id, start_time, end_time, type, notes, branch_name
             FROM employee_schedules
             WHERE user_id = ? AND schedule_date = ?
             ORDER BY start_time IS NULL, start_time ASC, id ASC'
        );
        return $this->db->resultSet([(int)$userId, $date]);
    }

    /** Mapa fecha => bloques para un empleado en un rango. */
    public function getBlocksForRange($userId, $startDate, $endDate) {
        $this->db->query(
            'SELECT id, user_id, schedule_date, shift_id, start_time, end_time, type, notes, branch_name
             FROM employee_schedules
             WHERE user_id = ? AND schedule_date BETWEEN ? AND ?
             ORDER BY schedule_date ASC, start_time IS NULL, start_time ASC, id ASC'
        );
        $rows = $this->db->resultSet([(int)$userId, $startDate, $endDate]);
        $map = [];
        foreach ($rows as $r) {
            $map[$r->schedule_date][] = $r;
        }
        return $map;
    }

    /** Mapa user_id => fecha => bloques para varios empleados en un rango. */
    public function getBlocksForUsers(array $userIds, $startDate, $endDate) {
        $userIds = array_values(array_filter(array_map('intval', $userIds)));
        if (empty($userIds)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($userIds), '?'));
        $this->db->query(
            "SELECT id, user_id, schedule_date, shift_id, start_time, end_time, type, notes, branch_name
             FROM employee_schedules
             WHERE user_id IN ($ph) AND schedule_date BETWEEN ? AND ?
             ORDER BY schedule_date ASC, start_time IS NULL, start_time ASC, id ASC"
        );
        $rows = $this->db->resultSet(array_merge($userIds, [$startDate, $endDate]));
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r->user_id][$r->schedule_date][] = $r;
        }
        return $map;
    }

    // ─────────────────────── Validación de días ───────────────────────

    /**
     * Clasifica las fechas objetivo de un empleado:
     * ['blocked' => fechas con vacaciones/licencia, 'conflict' => fecha => bloques de trabajo existentes]
     */
    public function classifyDates($userId, array $dates) {
        if (empty($dates)) {
            return ['blocked' => [], 'conflict' => []];
        }
        sort($dates);
        $map = $this->getBlocksForRange($userId, $dates[0], end($dates));
        $blocked = [];
        $conflict = [];
        foreach ($dates as $date) {
            foreach ($map[$date] ?? [] as $b) {
                if ($b->type === 'vacation' || $b->type === 'leave') {
                    $blocked[] = $date;
                    continue 2;
                }
            }
            $work = array_values(array_filter($map[$date] ?? [], function ($b) {
                return in_array($b->type, self::WORK_TYPES, true);
            }));
            if (!empty($work)) {
                $conflict[$date] = $work;
            }
        }
        return ['blocked' => $blocked, 'conflict' => $conflict];
    }

    // ─────────────────────────── Escritura ───────────────────────────

    /**
     * Divide un rango horario en segmentos por día (cruce de medianoche igual
     * que hoursapp: hasta 23:59 del día D y desde 00:00 del día D+1).
     * Devuelve [[date, start, end], ...].
     */
    public function splitRange($date, $start, $end) {
        if ($end > $start) {
            return [[$date, $start, $end]];
        }
        $next = date('Y-m-d', strtotime($date . ' +1 day'));
        $segments = [[$date, $start, '23:59']];
        if ($end !== '00:00') {
            $segments[] = [$next, '00:00', $end];
        }
        return $segments;
    }

    /**
     * Guarda los bloques de trabajo de un día.
     * $blocks: [['start'=>'HH:MM','end'=>'HH:MM','branch'=>string,'notes'=>string], ...]
     * $overwrite: true reemplaza los bloques de trabajo existentes; false agrega.
     * Nunca toca bloques vacation/leave (esos días se rechazan antes con classifyDates).
     */
    public function saveDay($userId, $date, array $blocks, $overwrite) {
        if (empty($blocks)) {
            return false;
        }
        try {
            $ownTx = !$this->db->inTransaction();
            if ($ownTx) {
                $this->db->beginTransaction();
            }
            if ($overwrite) {
                $this->db->query(
                    "DELETE FROM employee_schedules
                     WHERE user_id = ? AND schedule_date = ? AND type IN ('shift','custom','overtime')"
                );
                $this->db->execute([(int)$userId, $date]);
            }
            $withBranchId = $this->branchIdColumnReady();
            foreach ($blocks as $b) {
                $branchName = ($b['branch'] ?? '') !== '' ? $b['branch'] : null;
                // Resolver ANTES de preparar el INSERT: el wrapper Database guarda
                // un único statement y resolveBranchId lo reemplazaría (HY093).
                $branchId = ($withBranchId && $branchName !== null) ? $this->resolveBranchId($branchName) : null;
                if ($withBranchId) {
                    $this->db->query(
                        "INSERT INTO employee_schedules (user_id, schedule_date, shift_id, start_time, end_time, type, notes, branch_name, branch_id)
                         VALUES (?, ?, NULL, ?, ?, 'custom', ?, ?, ?)"
                    );
                    $this->db->execute([
                        (int)$userId,
                        $date,
                        $b['start'] . ':00',
                        $b['end'] . ':00',
                        ($b['notes'] ?? '') !== '' ? $b['notes'] : null,
                        $branchName,
                        $branchId,
                    ]);
                } else {
                    $this->db->query(
                        "INSERT INTO employee_schedules (user_id, schedule_date, shift_id, start_time, end_time, type, notes, branch_name)
                         VALUES (?, ?, NULL, ?, ?, 'custom', ?, ?)"
                    );
                    $this->db->execute([
                        (int)$userId,
                        $date,
                        $b['start'] . ':00',
                        $b['end'] . ':00',
                        ($b['notes'] ?? '') !== '' ? $b['notes'] : null,
                        $branchName,
                    ]);
                }
            }
            if ($ownTx) {
                $this->db->commit();
            }
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /** employee_schedules.branch_id existe (modelo relacional de sucursales). */
    private function branchIdColumnReady() {
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

    /** Id en company_branches para un nombre de sucursal (null si no existe). */
    private function resolveBranchId($branchName) {
        static $cache = [];
        if (array_key_exists($branchName, $cache)) {
            return $cache[$branchName];
        }
        try {
            $this->db->query('SELECT id FROM company_branches WHERE name = ? AND is_active = 1 LIMIT 1');
            $row = $this->db->single([$branchName]);
            $cache[$branchName] = $row ? (int)$row->id : null;
        } catch (Throwable $e) {
            $cache[$branchName] = null;
        }
        return $cache[$branchName];
    }

    // ─────────────────────────── Feriados ───────────────────────────

    /**
     * Feriados en un rango: fecha => nombre.
     * Usa el motor de la suite (Holiday::getHolidaysForPeriod: feriados fijos de
     * la empresa + reglas recurrentes por provincia/localidad, resueltas por la
     * sucursal del empleado si se indica $branchId). Fallback: tabla holidays.
     */
    public function getHolidaysInRange($companyId, $startDate, $endDate, $branchId = null) {
        if (class_exists('Holiday') && method_exists('Holiday', 'getHolidaysForPeriod')) {
            try {
                $rows = (new Holiday($this->db))->getHolidaysForPeriod((int)$companyId, $startDate, $endDate, $branchId);
                $map = [];
                foreach ($rows as $r) {
                    $map[$r->holiday_date] = $r->name;
                }
                return $map;
            } catch (Throwable $e) {
                // sigue al fallback
            }
        }
        try {
            $this->db->query(
                'SELECT holiday_date, name FROM holidays
                 WHERE company_id = ? AND holiday_date BETWEEN ? AND ?'
            );
            $rows = $this->db->resultSet([(int)$companyId, $startDate, $endDate]);
        } catch (Throwable $e) {
            return [];
        }
        $map = [];
        foreach ($rows as $r) {
            $map[$r->holiday_date] = $r->name;
        }
        return $map;
    }

    // ─────────────────────── Cálculo de horas/extras ───────────────────────

    /** Minutos de un bloque; '23:59' como fin cuenta como fin de día (medianoche). */
    public function blockMinutes($start, $end) {
        $s = $this->toMinutes($start);
        $e = $this->toMinutes($end);
        if ($end === '23:59' || $end === '23:59:00') {
            $e = 24 * 60;
        }
        if ($e <= $s) {
            $e += 24 * 60; // bloque nocturno guardado sin dividir (datos históricos del planificador)
        }
        return $e - $s;
    }

    /**
     * Horas y extras de un día según la organización.
     * Moderna: umbral diario acumulado 8h (L-V) / 5h (S-D) sobre los bloques
     * ordenados por inicio; feriado => todas las horas son extra.
     * Paviotti: horas = todos los bloques; extras = bloques type='overtime'
     * (la clasificación 50/100 sigue siendo del módulo de horas extras).
     * Devuelve ['hours' => float, 'extra' => float].
     */
    public function computeDay($org, $date, array $blocks, $isHoliday) {
        $work = array_values(array_filter($blocks, function ($b) {
            return in_array($b->type, self::WORK_TYPES, true) && $b->start_time && $b->end_time;
        }));
        usort($work, function ($a, $b) {
            return strcmp($a->start_time, $b->start_time);
        });

        $totalMin = 0;
        $extraMin = 0;

        if ($org === 'moderna') {
            $dow = (int)date('N', strtotime($date));
            $thresholdMin = ($dow >= 6 ? 5 : 8) * 60;
            foreach ($work as $b) {
                $dur = $this->blockMinutes($b->start_time, $b->end_time);
                if ($isHoliday) {
                    $extraMin += $dur;
                } else {
                    $newTotal = $totalMin + $dur;
                    $extraMin += max(0, $newTotal - max($thresholdMin, $totalMin));
                }
                $totalMin += $dur;
            }
        } else {
            foreach ($work as $b) {
                $dur = $this->blockMinutes($b->start_time, $b->end_time);
                $totalMin += $dur;
                if ($b->type === 'overtime') {
                    $extraMin += $dur;
                }
            }
        }

        return [
            'hours' => round($totalMin / 60, 2),
            'extra' => round($extraMin / 60, 2),
        ];
    }

    private function toMinutes($time) {
        $parts = explode(':', (string)$time);
        return ((int)($parts[0] ?? 0)) * 60 + (int)($parts[1] ?? 0);
    }
}
