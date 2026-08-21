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

    /**
     * Columnas de lectura de bloques. Los bloques type='shift' del planificador
     * suelen tener start/end NULL (las horas viven en shift_time_ranges): se
     * reconstituyen con COALESCE (envolvente MIN-MAX del turno, igual que
     * WorkSchedule), para que el cálculo de horas/extras y las vistas NO los
     * ignoren. branch_id se incluye solo si la columna existe.
     */
    private function blockSelectColumns() {
        $branchId = $this->branchIdColumnReady() ? 'es.branch_id,' : 'NULL AS branch_id,';
        return "es.id, es.user_id, es.schedule_date, es.shift_id, {$branchId}
                COALESCE(es.start_time, (SELECT MIN(str.start_time) FROM shift_time_ranges str WHERE str.shift_id = es.shift_id)) AS start_time,
                COALESCE(es.end_time,   (SELECT MAX(str.end_time)   FROM shift_time_ranges str WHERE str.shift_id = es.shift_id)) AS end_time,
                es.type, es.notes, es.branch_name";
    }

    /** Bloques del día de un empleado, ordenados por hora de inicio. */
    public function getDayBlocks($userId, $date) {
        // Se trae también el día anterior: un bloque nocturno heredado sin
        // dividir (end < start) derrama su cola 00:00–fin sobre este día.
        $fetchStart = date('Y-m-d', strtotime($date . ' -1 day'));
        $this->db->query(
            'SELECT ' . $this->blockSelectColumns() . '
             FROM employee_schedules es
             WHERE es.user_id = ? AND es.schedule_date BETWEEN ? AND ?
             ORDER BY es.schedule_date ASC, start_time IS NULL, start_time ASC, es.id ASC'
        );
        $rows = $this->expandLegacyNocturnal($this->db->resultSet([(int)$userId, $fetchStart, $date]));
        $day = array_values(array_filter($rows, function ($r) use ($date) {
            return $r->schedule_date === $date;
        }));
        return $this->sortBlocks($day);
    }

    /** Mapa fecha => bloques para un empleado en un rango. */
    public function getBlocksForRange($userId, $startDate, $endDate) {
        $fetchStart = date('Y-m-d', strtotime($startDate . ' -1 day'));
        $this->db->query(
            'SELECT ' . $this->blockSelectColumns() . '
             FROM employee_schedules es
             WHERE es.user_id = ? AND es.schedule_date BETWEEN ? AND ?
             ORDER BY es.schedule_date ASC, start_time IS NULL, start_time ASC, es.id ASC'
        );
        $rows = $this->expandLegacyNocturnal($this->db->resultSet([(int)$userId, $fetchStart, $endDate]));
        $map = [];
        foreach ($rows as $r) {
            if ($r->schedule_date < $startDate || $r->schedule_date > $endDate) {
                continue;
            }
            $map[$r->schedule_date][] = $r;
        }
        foreach ($map as $d => $list) {
            $map[$d] = $this->sortBlocks($list);
        }
        return $map;
    }

    /** Mapa user_id => fecha => bloques para varios empleados en un rango. */
    public function getBlocksForUsers(array $userIds, $startDate, $endDate) {
        $userIds = array_values(array_filter(array_map('intval', $userIds)));
        if (empty($userIds)) {
            return [];
        }
        $fetchStart = date('Y-m-d', strtotime($startDate . ' -1 day'));
        $ph = implode(',', array_fill(0, count($userIds), '?'));
        $this->db->query(
            'SELECT ' . $this->blockSelectColumns() . "
             FROM employee_schedules es
             WHERE es.user_id IN ($ph) AND es.schedule_date BETWEEN ? AND ?
             ORDER BY es.schedule_date ASC, start_time IS NULL, start_time ASC, es.id ASC"
        );
        $rows = $this->expandLegacyNocturnal($this->db->resultSet(array_merge($userIds, [$fetchStart, $endDate])));
        $map = [];
        foreach ($rows as $r) {
            if ($r->schedule_date < $startDate || $r->schedule_date > $endDate) {
                continue;
            }
            $map[(int)$r->user_id][$r->schedule_date][] = $r;
        }
        foreach ($map as $uid => $byDate) {
            foreach ($byDate as $d => $list) {
                $map[$uid][$d] = $this->sortBlocks($list);
            }
        }
        return $map;
    }

    /** Bloque puntual por id, SIN expandir (fila cruda de employee_schedules). */
    public function getBlockById($blockId) {
        $this->db->query(
            'SELECT ' . $this->blockSelectColumns() . '
             FROM employee_schedules es
             WHERE es.id = ? LIMIT 1'
        );
        $row = $this->db->single([(int)$blockId]);
        return $row ?: null;
    }

    /**
     * Bloques nocturnos HEREDADOS sin dividir (end < start, dato del planificador
     * viejo o de otra fuente): en la capa de lectura se parten virtualmente en
     * dos días —igual que hace splitRange al guardar— para que horas, extras,
     * conflictos y vistas los imputen al día que corresponde. Ambas partes
     * comparten el id del registro original (editar/borrar opera sobre el todo)
     * y quedan marcadas con nocturnal_legacy / virtual_part.
     */
    private function expandLegacyNocturnal(array $rows) {
        $out = [];
        foreach ($rows as $r) {
            $isWork = in_array($r->type, self::WORK_TYPES, true);
            $s = $r->start_time ? substr($r->start_time, 0, 5) : null;
            $e = $r->end_time ? substr($r->end_time, 0, 5) : null;
            if (!$isWork || $s === null || $e === null || $e >= $s) {
                $out[] = $r;
                continue;
            }
            // Ambas partes conservan los datos ORIGINALES de la fila: la
            // edición individual debe precargar el turno completo (no la
            // mitad virtual), para que guardar sin cambios no pierda horas.
            if ($e === '00:00') {
                // Termina justo a medianoche: una sola parte hasta fin de día.
                $one = clone $r;
                $one->end_time = '23:59:00';
                $one->nocturnal_legacy = true;
                $one->orig_date = $r->schedule_date;
                $one->orig_start = $s;
                $one->orig_end = $e;
                $out[] = $one;
                continue;
            }
            $first = clone $r;
            $first->end_time = '23:59:00';
            $first->nocturnal_legacy = true;
            $first->virtual_part = 1;
            $first->orig_date = $r->schedule_date;
            $first->orig_start = $s;
            $first->orig_end = $e;
            $out[] = $first;
            $second = clone $r;
            $second->schedule_date = date('Y-m-d', strtotime($r->schedule_date . ' +1 day'));
            $second->start_time = '00:00:00';
            $second->nocturnal_legacy = true;
            $second->virtual_part = 2;
            $second->orig_date = $r->schedule_date;
            $second->orig_start = $s;
            $second->orig_end = $e;
            $out[] = $second;
        }
        return $out;
    }

    /** Orden estable por hora de inicio (los NULL al final) e id. */
    private function sortBlocks(array $blocks) {
        usort($blocks, function ($a, $b) {
            if (($a->start_time === null) !== ($b->start_time === null)) {
                return $a->start_time === null ? 1 : -1;
            }
            $cmp = strcmp((string)$a->start_time, (string)$b->start_time);
            return $cmp !== 0 ? $cmp : ((int)$a->id <=> (int)$b->id);
        });
        return $blocks;
    }

    // ─────────────────────── Validación de días ───────────────────────

    /**
     * Clasifica las fechas objetivo de un empleado:
     * ['blocked'        => fechas con vacaciones/licencia/guardia,
     *  'blocked_reason' => fecha => 'Vacaciones'|'Licencia'|'Guardia',
     *  'conflict'       => fecha => bloques de trabajo existentes]
     * Bloquean: bloques vacation/leave del día Y períodos de estado declarados
     * (employee_status_periods — port del state/inicio_estado/fin_estado de
     * hoursapp, que la suite aplica por día en vez de rechazar todo el envío).
     */
    public function classifyDates($userId, array $dates) {
        if (empty($dates)) {
            return ['blocked' => [], 'blocked_reason' => [], 'conflict' => []];
        }
        sort($dates);
        $map = $this->getBlocksForRange($userId, $dates[0], end($dates));
        $statusMap = $this->statusPeriodsForUsers([(int)$userId], $dates[0], end($dates));
        $periods = $statusMap[(int)$userId] ?? [];
        $blocked = [];
        $reasons = [];
        $conflict = [];
        foreach ($dates as $date) {
            foreach ($map[$date] ?? [] as $b) {
                if ($b->type === 'vacation' || $b->type === 'leave') {
                    $blocked[] = $date;
                    $reasons[$date] = $b->type === 'vacation' ? 'Vacaciones' : 'Licencia';
                    continue 2;
                }
            }
            foreach ($periods as $p) {
                if ($date >= $p->start_date && $date <= $p->end_date) {
                    $blocked[] = $date;
                    $reasons[$date] = self::statusLabel($p->status);
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
        return ['blocked' => $blocked, 'blocked_reason' => $reasons, 'conflict' => $conflict];
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
                // Un nocturno HEREDADO del día anterior (fila end < start sin
                // dividir) derrama su cola 00:00–fin sobre este día: es el
                // conflicto que la lectura expandida muestra. Sobrescribir el
                // día debe quitar también esa cola sin tocar las horas del día
                // previo → se recorta la fila a fin de día (23:59).
                $prev = date('Y-m-d', strtotime($date . ' -1 day'));
                $this->db->query(
                    "UPDATE employee_schedules SET end_time = '23:59:00'
                     WHERE user_id = ? AND schedule_date = ? AND type IN ('shift','custom','overtime')
                       AND start_time IS NOT NULL AND end_time IS NOT NULL
                       AND end_time < start_time"
                );
                $this->db->execute([(int)$userId, $prev]);
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

    // ─────────────────── Edición y borrado de bloques ───────────────────

    /**
     * Reemplaza un bloque de trabajo por otro (edición individual): borra el
     * original e inserta el nuevo, dividiendo el cruce de medianoche igual que
     * la carga (a diferencia del edit_hours de hoursapp, que guardaba el bloque
     * nocturno sin dividir). Atómico. Un bloque type='shift' del planificador
     * editado se materializa como 'custom' con horas propias.
     */
    public function replaceBlock($userId, $blockId, $date, $start, $end, $branch, $notes = null) {
        try {
            $ownTx = !$this->db->inTransaction();
            if ($ownTx) {
                $this->db->beginTransaction();
            }
            $this->db->query(
                "DELETE FROM employee_schedules
                 WHERE id = ? AND user_id = ? AND type IN ('shift','custom','overtime')"
            );
            $this->db->execute([(int)$blockId, (int)$userId]);
            if ($this->db->rowCount() !== 1) {
                $this->db->rollBack();
                return false;
            }
            foreach ($this->splitRange($date, $start, $end) as $seg) {
                $ok = $this->saveDay($userId, $seg[0], [[
                    'start'  => $seg[1],
                    'end'    => $seg[2],
                    'branch' => $branch,
                    'notes'  => $notes,
                ]], false);
                if (!$ok) {
                    $this->db->rollBack();
                    return false;
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

    /**
     * Borra bloques de trabajo por id de UN empleado (individual o selección
     * múltiple de la vista). Nunca toca vacation/leave. Devuelve cuántos borró.
     */
    public function deleteBlocks($userId, array $blockIds) {
        $ids = array_values(array_filter(array_map('intval', $blockIds)));
        if (empty($ids)) {
            return 0;
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $this->db->query(
            "DELETE FROM employee_schedules
             WHERE user_id = ? AND id IN ($ph) AND type IN ('shift','custom','overtime')"
        );
        $this->db->execute(array_merge([(int)$userId], $ids));
        return $this->db->rowCount();
    }

    /**
     * Bloques de trabajo del grupo organizacional en un rango, opcionalmente
     * filtrados por sucursal — la base del borrado masivo. Incluye empleados
     * INACTIVOS (igual que hoursapp: sus registros también se listan y borran
     * salvo exclusión manual); la barrera multi-tenant es u.company_id.
     * Filas crudas (sin expandir: se borra la fila real) + datos del empleado.
     * Sin recálculo de extras posterior: en la suite las extras se derivan al
     * leer (computeDay), borrar filas deja el estado consistente solo.
     */
    public function findWorkBlocksByCompanies(array $companyIds, $startDate, $endDate, $branchName = null) {
        $companyIds = array_values(array_filter(array_map('intval', $companyIds)));
        if (empty($companyIds)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($companyIds), '?'));
        $sql = 'SELECT ' . $this->blockSelectColumns() . ",
                       u.full_name, u.is_active, u.company_id, u.area_id
                FROM employee_schedules es
                JOIN users u ON u.id = es.user_id
                WHERE u.company_id IN ($ph) AND u.role = 'empleado'
                  AND es.schedule_date BETWEEN ? AND ?
                  AND es.type IN ('shift','custom','overtime')";
        $params = array_merge($companyIds, [$startDate, $endDate]);
        if ($branchName !== null && $branchName !== '') {
            $sql .= ' AND es.branch_name = ?';
            $params[] = $branchName;
        }
        $sql .= ' ORDER BY u.full_name ASC, es.schedule_date ASC, start_time IS NULL, start_time ASC, es.id ASC';
        $this->db->query($sql);
        return $this->db->resultSet($params);
    }

    /**
     * Bloques "borde" de turnos nocturnos divididos, para el borrado masivo:
     * $edge='tail' busca colas 00:00–X, $edge='head' busca cabezas X–23:59,
     * en las fechas dadas para esos empleados (y sucursal si se filtra).
     * user_id => fecha => [bloques]. El matching fino lo hace el llamador.
     */
    public function findEdgeBlocks(array $userIds, array $dates, $branchName, $edge) {
        $userIds = array_values(array_filter(array_map('intval', $userIds)));
        $dates = array_values(array_unique(array_filter($dates)));
        if (empty($userIds) || empty($dates)) {
            return [];
        }
        $phU = implode(',', array_fill(0, count($userIds), '?'));
        $phD = implode(',', array_fill(0, count($dates), '?'));
        $cond = $edge === 'tail' ? "es.start_time = '00:00:00'" : "es.end_time IN ('23:59:00','23:59:59')";
        $sql = "SELECT es.id, es.user_id, es.schedule_date, es.start_time, es.end_time, es.type, es.branch_name
                FROM employee_schedules es
                WHERE es.user_id IN ($phU) AND es.schedule_date IN ($phD)
                  AND es.type IN ('shift','custom','overtime') AND {$cond}";
        $params = array_merge($userIds, $dates);
        if ($branchName !== null && $branchName !== '') {
            $sql .= ' AND es.branch_name = ?';
            $params[] = $branchName;
        }
        $this->db->query($sql);
        $map = [];
        foreach ($this->db->resultSet($params) as $r) {
            $map[(int)$r->user_id][$r->schedule_date][] = $r;
        }
        return $map;
    }

    // ─────────────── Estados del empleado (Guardia/Vacaciones/Licencia) ───────────────
    //  Port del Employee.state + inicio_estado/fin_estado de hoursapp: períodos
    //  declarados por RRHH que bloquean la carga de horas en las fechas cubiertas.

    /** La migración migration_estados_empleado.sql ya corrió. */
    public function statusTableReady() {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $this->db->query("SHOW TABLES LIKE 'employee_status_periods'");
            $ready = (bool)$this->db->single();
        } catch (Throwable $e) {
            $ready = false;
        }
        return $ready;
    }

    /** Etiqueta visible de un estado ('guardia' => 'Guardia'). */
    public static function statusLabel($status) {
        $labels = ['guardia' => 'Guardia', 'vacaciones' => 'Vacaciones', 'licencia' => 'Licencia'];
        return $labels[$status] ?? ucfirst((string)$status);
    }

    /** user_id => períodos que SOLAPAN el rango dado. */
    public function statusPeriodsForUsers(array $userIds, $startDate, $endDate) {
        $userIds = array_values(array_filter(array_map('intval', $userIds)));
        if (empty($userIds) || !$this->statusTableReady()) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($userIds), '?'));
        $this->db->query(
            "SELECT id, user_id, status, start_date, end_date, notes
             FROM employee_status_periods
             WHERE user_id IN ($ph) AND start_date <= ? AND end_date >= ?
             ORDER BY start_date ASC, id ASC"
        );
        $map = [];
        foreach ($this->db->resultSet(array_merge($userIds, [$endDate, $startDate])) as $r) {
            $map[(int)$r->user_id][] = $r;
        }
        return $map;
    }

    /** user_id => etiqueta del estado vigente en la fecha ('Guardia'…), solo los no activos. */
    public function currentStatuses(array $userIds, $date) {
        $map = $this->statusPeriodsForUsers($userIds, $date, $date);
        $out = [];
        foreach ($map as $uid => $periods) {
            $out[$uid] = self::statusLabel($periods[0]->status);
        }
        return $out;
    }

    /** Alta de un período de estado. */
    public function addStatusPeriod($userId, $status, $startDate, $endDate, $notes, $createdBy) {
        if (!$this->statusTableReady()) {
            return false;
        }
        try {
            $this->db->query(
                'INSERT INTO employee_status_periods (user_id, status, start_date, end_date, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            return $this->db->execute([
                (int)$userId,
                $status,
                $startDate,
                $endDate,
                ($notes ?? '') !== '' ? $notes : null,
                (int)$createdBy ?: null,
            ]);
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Baja de un período de estado; valida pertenencia si se pasa $userId. */
    public function deleteStatusPeriod($periodId, $userId = null) {
        if (!$this->statusTableReady()) {
            return false;
        }
        $sql = 'DELETE FROM employee_status_periods WHERE id = ?';
        $params = [(int)$periodId];
        if ($userId !== null) {
            $sql .= ' AND user_id = ?';
            $params[] = (int)$userId;
        }
        $this->db->query($sql);
        $this->db->execute($params);
        return $this->db->rowCount() > 0;
    }

    /** Períodos vigentes o futuros de un conjunto de empleados (gestión). */
    public function listStatusPeriods(array $userIds, $fromDate) {
        $userIds = array_values(array_filter(array_map('intval', $userIds)));
        if (empty($userIds) || !$this->statusTableReady()) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($userIds), '?'));
        $this->db->query(
            "SELECT id, user_id, status, start_date, end_date, notes
             FROM employee_status_periods
             WHERE user_id IN ($ph) AND end_date >= ?
             ORDER BY start_date ASC, id ASC"
        );
        return $this->db->resultSet(array_merge($userIds, [$fromDate]));
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

    // ─────────────── Feriados a nivel BLOQUE (regla del negocio) ───────────────
    //  - nacionales: aplican a todas las cadenas;
    //  - fijos (tabla holidays): por empresa del empleado;
    //  - provincia/localidad: aplican si la SUCURSAL DONDE SE REGISTRÓ LA HORA
    //    está en esa provincia/localidad (no la sucursal "de casa" del empleado).

    /** Precarga feriados fijos y reglas recurrentes para un rango. */
    public function buildHolidayContext(array $companyIds, $startDate, $endDate) {
        $ctx = ['fixed' => [], 'national' => [], 'province' => [], 'locality' => []];
        $ids = array_values(array_filter(array_map('intval', $companyIds)));
        if (!empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            try {
                $this->db->query("SELECT company_id, holiday_date, name FROM holidays
                                  WHERE company_id IN ($ph) AND holiday_date BETWEEN ? AND ?");
                foreach ($this->db->resultSet(array_merge($ids, [$startDate, $endDate])) as $r) {
                    $ctx['fixed'][(int)$r->company_id][$r->holiday_date] = $r->name;
                }
            } catch (Throwable $e) {
                // tabla holidays no disponible: seguimos solo con reglas
            }
        }
        try {
            $this->db->query('SELECT name, month_day, scope_type, province, locality FROM holiday_rules WHERE is_active = 1');
            $rules = $this->db->resultSet();
        } catch (Throwable $e) {
            $rules = [];
        }
        $y1 = (int)substr($startDate, 0, 4);
        $y2 = (int)substr($endDate, 0, 4);
        foreach ($rules as $rule) {
            for ($y = $y1; $y <= $y2; $y++) {
                $date = $y . '-' . $rule->month_day;
                $dt = DateTime::createFromFormat('!Y-m-d', $date);
                if (!$dt || $dt->format('Y-m-d') !== $date || $date < $startDate || $date > $endDate) {
                    continue;
                }
                if ($rule->scope_type === 'national') {
                    $ctx['national'][$date] = $rule->name;
                } elseif ($rule->scope_type === 'province') {
                    $ctx['province'][$this->normalizePlace($rule->province)][$date] = $rule->name;
                } elseif ($rule->scope_type === 'locality') {
                    $ctx['locality'][$this->normalizePlace($rule->locality)][$date] = $rule->name;
                }
            }
        }
        return $ctx;
    }

    /** Nombre del feriado que aplica a un bloque puntual, o null. */
    public function blockHolidayName(array $ctx, $date, $companyId, $branchId = null, $branchName = null) {
        if (isset($ctx['national'][$date])) {
            return $ctx['national'][$date];
        }
        if ((int)$companyId > 0 && isset($ctx['fixed'][(int)$companyId][$date])) {
            return $ctx['fixed'][(int)$companyId][$date];
        }
        $loc = $this->branchLocation($branchId, $branchName);
        if ($loc !== null) {
            if ($loc['locality'] !== '' && isset($ctx['locality'][$loc['locality']][$date])) {
                return $ctx['locality'][$loc['locality']][$date];
            }
            if ($loc['province'] !== '' && isset($ctx['province'][$loc['province']][$date])) {
                return $ctx['province'][$loc['province']][$date];
            }
        }
        return null;
    }

    /** Ubicación normalizada de una sucursal (por id o por nombre), cacheada. */
    private function branchLocation($branchId, $branchName) {
        $key = ((int)$branchId) . '|' . (string)$branchName;
        static $cache = [];
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $row = null;
        try {
            if ((int)$branchId > 0) {
                $this->db->query('SELECT locality, province FROM company_branches WHERE id = ? LIMIT 1');
                $row = $this->db->single([(int)$branchId]);
            }
            if (!$row && (string)$branchName !== '') {
                $this->db->query('SELECT locality, province FROM company_branches WHERE name = ? AND is_active = 1 LIMIT 1');
                $row = $this->db->single([(string)$branchName]);
            }
        } catch (Throwable $e) {
            $row = null;
        }
        $cache[$key] = $row ? [
            'locality' => $this->normalizePlace($row->locality ?? ''),
            'province' => $this->normalizePlace($row->province ?? ''),
        ] : null;
        return $cache[$key];
    }

    /** Misma normalización que Holiday::normalizePlace (Córdoba ≈ cordoba). */
    private function normalizePlace($value) {
        $value = trim((string)$value);
        if (function_exists('iconv')) {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($ascii !== false) {
                $value = $ascii;
            }
        }
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    // ─────────────────────── Cálculo de horas/extras ───────────────────────

    /** Minutos de un bloque; '23:59' como fin cuenta como fin de día (medianoche). */
    public function blockMinutes($start, $end) {
        $s = $this->toMinutes($start);
        $e = $this->toMinutes($end);
        if ($end === '23:59' || $end === '23:59:00' || $end === '23:59:59') {
            $e = 24 * 60;
        }
        // Estricto (<): inicio == fin es un bloque de 0 minutos (igual que
        // hoursapp), NUNCA un turno de 24 h fantasma. Solo end < start se
        // interpreta como nocturno sin dividir (datos históricos del planificador).
        if ($e < $s) {
            $e += 24 * 60;
        }
        return $e - $s;
    }

    /**
     * Horas y extras de un día según la organización.
     * Moderna: umbral diario acumulado 8h (L-V) / 5h (S-D) sobre los bloques
     * ordenados por inicio; feriado => las horas del bloque son extra.
     * $blockHolidayFn (opcional): callable(bloque) => bool que evalúa el feriado
     * POR BLOQUE según la sucursal donde se registró la hora; los bloques en
     * feriado son 100% extra y no consumen el umbral (los demás acumulan entre
     * sí). Sin la callable, $isHoliday aplica a todo el día (comportamiento
     * hoursapp original).
     * Paviotti: horas = todos los bloques; extras = bloques type='overtime'
     * (la clasificación 50/100 sigue siendo del módulo de horas extras).
     * Devuelve ['hours' => float, 'extra' => float,
     *           'block_extra' => [id de bloque => horas extra de ESE bloque]]
     * — el excedente se atribuye al bloque (y sucursal) que lo genera, igual
     * que recalculate_daily_extra_hours de hoursapp.
     */
    public function computeDay($org, $date, array $blocks, $isHoliday, $blockHolidayFn = null) {
        $work = array_values(array_filter($blocks, function ($b) {
            return in_array($b->type, self::WORK_TYPES, true) && $b->start_time && $b->end_time;
        }));
        usort($work, function ($a, $b) {
            return strcmp($a->start_time, $b->start_time);
        });

        $totalMin = 0;
        $extraMin = 0;
        $blockExtra = [];

        if ($org === 'moderna') {
            $dow = (int)date('N', strtotime($date));
            $thresholdMin = ($dow >= 6 ? 5 : 8) * 60;
            $accMin = 0; // acumulado de bloques NO feriados contra el umbral
            foreach ($work as $b) {
                $dur = $this->blockMinutes($b->start_time, $b->end_time);
                $blockHoliday = is_callable($blockHolidayFn) ? (bool)$blockHolidayFn($b) : (bool)$isHoliday;
                if ($blockHoliday) {
                    $thisExtra = $dur;
                } else {
                    $newAcc = $accMin + $dur;
                    $thisExtra = max(0, $newAcc - max($thresholdMin, $accMin));
                    $accMin = $newAcc;
                }
                $extraMin += $thisExtra;
                $totalMin += $dur;
                if (isset($b->id)) {
                    $blockExtra[(int)$b->id] = round($thisExtra / 60, 2);
                }
            }
        } else {
            foreach ($work as $b) {
                $dur = $this->blockMinutes($b->start_time, $b->end_time);
                $totalMin += $dur;
                if ($b->type === 'overtime') {
                    $extraMin += $dur;
                    if (isset($b->id)) {
                        $blockExtra[(int)$b->id] = round($dur / 60, 2);
                    }
                }
            }
        }

        return [
            'hours' => round($totalMin / 60, 2),
            'extra' => round($extraMin / 60, 2),
            'block_extra' => $blockExtra,
        ];
    }

    private function toMinutes($time) {
        $parts = explode(':', (string)$time);
        return ((int)($parts[0] ?? 0)) * 60 + (int)($parts[1] ?? 0);
    }
}
