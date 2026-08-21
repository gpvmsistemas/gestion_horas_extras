<?php

/**
 * Registro de Horas — módulo compartido de la Suite P&M.
 *
 * Funciona para ambas organizaciones sobre la empresa activa de sesión:
 * los empleados y horarios son reales (employee_schedules vía
 * RegistroHorasService). Cuando la empresa activa todavía no tiene nómina
 * (p. ej. Moderna antes de la migración), las pantallas muestran datos de
 * ejemplo claramente marcados como maqueta.
 *
 * Lógica portada de hoursapp-moderna (record_hours):
 *  - verificación de horas ya cargadas en el día, con confirmación para
 *    sobrescribir o agregar como turno adicional;
 *  - bloqueo por vacaciones/licencia;
 *  - división de turnos que cruzan medianoche;
 *  - segundo turno (partido) con sucursal propia;
 *  - extras por organización (Moderna: umbral 8/5 + feriados).
 */
class RegistroHorasController {

    private $service;
    private $userModel;

    public function __construct(){
        if (!isLoggedIn()) {
            redirect('login');
        }
        if (!org_hours_registry_enabled()) {
            $_SESSION['flash_error'] = 'No tienes acceso al Registro de Horas.';
            redirect('admin/dashboard');
        }
        $this->userModel = new User();
        $this->service = RegistroHorasService::isSchemaReady() ? new RegistroHorasService() : null;
    }

    public function index(){
        redirect('registroHoras/vistaGeneral');
    }

    // ─────────────────────────── Pantallas ───────────────────────────

    /** Vista general — todos los empleados por sucursal (equivale a branch_hours). */
    public function vistaGeneral(){
        $data = $this->orgData();
        $monday = $this->weekMonday($_GET['week'] ?? '');
        $data['weekDays'] = $this->weekDays($monday);
        $data['weekValue'] = date('o-\WW', strtotime($monday));

        if ($data['realMode']) {
            $sunday = end($data['weekDays']);
            $ids = array_column($data['employees'], 'id');
            $blocksMap = $this->service->getBlocksForUsers($ids, $monday, $sunday);
            $statusMap = $this->service->statusPeriodsForUsers($ids, $monday, $sunday);
            $data['schedule'] = $this->buildWeekSchedule($data['employees'], $data['weekDays'], $blocksMap, $statusMap);
        } else {
            $data['schedule'] = $this->sampleWeekSchedule($data['employees']);
        }
        $this->render('registro_horas/vista_general', $data);
    }

    /** Carga de horarios (equivale a record_hours de hoursapp). */
    public function carga(){
        $data = $this->orgData();
        $this->render('registro_horas/carga', $data);
    }

    /** POST real de la carga: valida, verifica conflictos y guarda. */
    public function store(){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('registroHoras/carga');
        }
        csrf_verify();
        $data = $this->orgData();
        if (!$data['realMode']) {
            redirect('registroHoras/carga');
        }

        $input = $this->readCargaInput();
        $employee = $this->resolveEmployee($input['employee_id']);
        $errors = $this->validateCargaInput($input, $employee);
        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            redirect('registroHoras/carga');
        }

        // Expandir el rango de fechas a segmentos por día (división de medianoche incluida).
        $plan = [];
        foreach ($this->dateRange($input['start_date'], $input['end_date']) as $date) {
            foreach ($this->service->splitRange($date, $input['start_time'], $input['end_time']) as $seg) {
                $plan[$seg[0]][] = ['start' => $seg[1], 'end' => $seg[2], 'branch' => $input['branch_name']];
            }
            if ($input['two_turns']) {
                foreach ($this->service->splitRange($date, $input['start_time_2'], $input['end_time_2']) as $seg) {
                    $plan[$seg[0]][] = ['start' => $seg[1], 'end' => $seg[2], 'branch' => $input['branch_name_2'] ?: $input['branch_name']];
                }
            }
        }
        ksort($plan);

        $classified = $this->service->classifyDates($employee->id, array_keys($plan));
        $blocked = $classified['blocked'];
        $conflicts = array_intersect_key($classified['conflict'], $plan);

        // Conflictos sin confirmación: re-mostrar la carga con el panel de decisión.
        if (!empty($conflicts) && !in_array($input['confirm_mode'], ['overwrite', 'append'], true)) {
            $data['conflict'] = [
                'input'     => $input,
                'employee'  => $employee,
                'conflicts' => $conflicts,
                'blocked'   => $blocked,
            ];
            $this->render('registro_horas/carga', $data);
            return;
        }

        $saved = 0;
        $failed = 0;
        foreach ($plan as $date => $blocks) {
            if (in_array($date, $blocked, true)) {
                continue;
            }
            $overwrite = isset($conflicts[$date]) && $input['confirm_mode'] === 'overwrite';
            if ($this->service->saveDay($employee->id, $date, $blocks, $overwrite)) {
                $saved++;
            } else {
                $failed++;
            }
        }

        $msg = "Se cargaron {$saved} día(s) para " . $employee->full_name . '.';
        if (!empty($blocked)) {
            $reasons = $classified['blocked_reason'] ?? [];
            $msg .= ' Omitidos por estado del empleado: ' . implode(', ', array_map(function ($d) use ($reasons) {
                return date('d/m', strtotime($d)) . (isset($reasons[$d]) ? ' (' . $reasons[$d] . ')' : '');
            }, $blocked)) . '.';
        }
        if ($failed > 0) {
            $_SESSION['flash_error'] = "No se pudieron guardar {$failed} día(s). " . $msg;
        } else {
            $_SESSION['flash_success'] = $msg;
        }
        redirect('registroHoras/horarios?employee_id=' . (int)$employee->id);
    }

    /** Horarios por empleado (equivale a view_hours: mes o rango libre + ciudad + sucursal). */
    public function horarios(){
        $data = $this->orgData();
        $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');

        // Rango libre (from/to) tiene prioridad sobre el mes, como en view_hours.
        $from = (string)($_GET['from'] ?? '');
        $to = (string)($_GET['to'] ?? '');
        $rangeMode = $this->validDate($from) && $this->validDate($to)
            && $from <= $to
            && (strtotime($to) - strtotime($from)) <= 366 * 86400;
        if ($rangeMode) {
            $first = $from;
            $last = $to;
        } else {
            $from = $to = '';
            $first = $month . '-01';
            $last = date('Y-m-t', strtotime($first));
        }
        // El calendario mensual solo tiene sentido si el rango vive en un mes.
        $data['monthValue'] = substr($first, 0, 7) === substr($last, 0, 7) ? substr($first, 0, 7) : null;
        $data['filters'] = [
            'month'  => $month,
            'from'   => $from,
            'to'     => $to,
            'city'   => (string)($_GET['city'] ?? ''),
            'branch' => (string)($_GET['branch'] ?? ''),
            'range_mode' => $rangeMode,
        ];
        $data['rangeStart'] = $first;
        $data['rangeEnd'] = $last;

        if ($data['realMode']) {
            $selectedId = (int)($_GET['employee_id'] ?? 0);
            $data['selected'] = $this->pickEmployee($data['employees'], $selectedId);
            $blocksMap = $this->service->getBlocksForRange($data['selected']->id, $first, $last);
            // Feriados evaluados POR BLOQUE: nacionales para todas las cadenas,
            // fijos por la empresa del empleado, locales por la sucursal donde
            // se registró cada hora.
            $empCompanyId = (int)($data['selected']->company_id ?? 0) ?: (int)adminCompanyId();
            $holidayCtx = $this->service->buildHolidayContext($this->orgCompanyIds(), $first, $last);
            $records = $this->buildMonthRecords($data['org'], $blocksMap, $holidayCtx, $empCompanyId);
            $records = $this->filterRecordsByPlace($records, $data['filters']['city'], $data['filters']['branch'], $data['branchesByCity']);
            $data['records'] = $records;
            $data['statusPeriods'] = $this->service->statusPeriodsForUsers([$data['selected']->id], $first, $last)[$data['selected']->id] ?? [];
        } else {
            $data['selected'] = $data['employees'][0];
            $data['records'] = $this->sampleMonthRecords($data['org']);
            $data['statusPeriods'] = [];
        }
        $this->render('registro_horas/horarios', $data);
    }

    /** Filtro por sucursal exacta o por ciudad (todas sus sucursales), post-cálculo
     *  de extras: el reparto por bloque ya quedó hecho sobre el día completo. */
    private function filterRecordsByPlace($records, $city, $branch, $branchesByCity){
        if ($branch !== '') {
            return array_values(array_filter($records, fn($r) => $r->branch_name === $branch));
        }
        if ($city !== '' && isset($branchesByCity[$city])) {
            $inCity = $branchesByCity[$city];
            return array_values(array_filter($records, fn($r) => in_array($r->branch_name, $inCity, true)));
        }
        return $records;
    }

    /**
     * Duplicación de horarios con verificación previa. Modos (port completo de
     * duplicate_hours + duplicate_hours_massive de hoursapp):
     *  - individual: semana → semana de un empleado (pares alineados por día);
     *  - masivo:     semana → semana de todos los empleados;
     *  - pares:      pares de fechas ARBITRARIOS de un empleado (cualquier
     *                origen → cualquier destino, un origen → varios destinos);
     *  - sucursal:   masiva por sucursal — copia lo trabajado en ESA sucursal
     *                en las fechas origen a todos los empleados que registraron
     *                horas ahí, con sobrescritura POR EMPLEADO.
     */
    public function duplicar(){
        $data = $this->orgData();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $data['realMode']) {
            csrf_verify();
            $action = postString('action');
            $mode = postString('mode');
            if (!in_array($mode, ['individual', 'masivo', 'pares', 'sucursal'], true)) {
                $mode = 'individual';
            }
            $req = [
                'mode'        => $mode,
                'employee_id' => (int)($_POST['employee_id'] ?? 0),
                'src_week'    => postString('src_week'),
                'dest_week'   => postString('dest_week'),
                'branch_name' => postString('branch_name'),
                'pair_src'    => array_map('strval', (array)($_POST['pair_src'] ?? [])),
                'pair_dest'   => array_map('strval', (array)($_POST['pair_dest'] ?? [])),
                'overwrite'   => !empty($_POST['overwrite']),
                // sucursal: sobrescritura fina por empleado (checkbox en la verificación)
                'overwrite_ids' => array_map('intval', (array)($_POST['overwrite_ids'] ?? [])),
            ];

            $pairs = $this->resolveDuplicatePairs($req);
            if ($pairs === null) {
                redirect('registroHoras/duplicar');
            }

            if ($mode === 'pares' || $mode === 'individual') {
                $targets = array_values(array_filter($data['employees'], fn($e) => $e->id === $req['employee_id']));
            } else {
                $targets = $data['employees'];
            }
            if (empty($targets)) {
                $_SESSION['flash_error'] = 'Empleado no válido.';
                redirect('registroHoras/duplicar');
            }

            $branchFilter = $mode === 'sucursal' ? $req['branch_name'] : null;
            if ($mode === 'sucursal' && $branchFilter === '') {
                $_SESSION['flash_error'] = 'Elegí la sucursal cuyos horarios querés duplicar.';
                redirect('registroHoras/duplicar');
            }

            $preview = $this->buildDuplicatePreview($targets, $pairs, $branchFilter);
            if ($action === 'execute') {
                $overwriteIds = $mode === 'sucursal' ? $req['overwrite_ids'] : null;
                // "Mantener": filas desmarcadas en la verificación quedan fuera.
                $keep = !empty($_POST['keep_present']) ? array_map('strval', (array)($_POST['keep'] ?? [])) : null;
                $result = $this->executeDuplicate($preview, $req['overwrite'], $overwriteIds, $keep);
                $_SESSION['flash_success'] = "Duplicación completada: {$result['days']} día(s) copiados a {$result['emps']} empleado(s)."
                    . ($result['skipped'] > 0 ? " {$result['skipped']} día(s) omitidos (licencias, guardias o sin permiso de sobrescritura)." : '');
                redirect($req['dest_week'] !== ''
                    ? 'registroHoras/vistaGeneral?week=' . urlencode($req['dest_week'])
                    : 'registroHoras/vistaGeneral');
            }
            $data['preview'] = $preview;
            $data['previewReq'] = $req;
        }

        $this->render('registro_horas/duplicar', $data);
    }

    /**
     * Pares [origen, destino] según el modo. null = entrada inválida (con flash).
     * Semanal: 7 pares alineados por posición de día. Pares/sucursal: fechas
     * arbitrarias tipiadas; se admite un origen repetido (a varios destinos)
     * pero no dos pares idénticos, máx. 31.
     */
    private function resolveDuplicatePairs($req){
        if ($req['mode'] === 'individual' || $req['mode'] === 'masivo') {
            // Formato estricto: una semana malformada NO puede caer al fallback
            // "semana actual" de weekMonday (duplicaría sobre fechas no elegidas).
            $weekOk = preg_match('/^\d{4}-W\d{2}$/', $req['src_week'])
                && preg_match('/^\d{4}-W\d{2}$/', $req['dest_week']);
            $srcMonday = $this->weekMonday($req['src_week']);
            $destMonday = $this->weekMonday($req['dest_week']);
            if (!$weekOk || $srcMonday === $destMonday) {
                $_SESSION['flash_error'] = 'Elegí una semana de origen y una de destino distintas.';
                return null;
            }
            $src = $this->weekDays($srcMonday);
            $dest = $this->weekDays($destMonday);
            return array_map(fn($i) => [$src[$i], $dest[$i]], range(0, 6));
        }
        $pairs = [];
        $seen = [];
        foreach ($req['pair_src'] as $i => $srcDate) {
            $destDate = $req['pair_dest'][$i] ?? '';
            if ($srcDate === '' && $destDate === '') {
                continue; // fila vacía del armador de pares
            }
            if (!$this->validDate($srcDate) || !$this->validDate($destDate) || $srcDate === $destDate) {
                $_SESSION['flash_error'] = 'Revisá los pares de fechas: origen y destino deben ser fechas válidas y distintas.';
                return null;
            }
            $key = $srcDate . '>' . $destDate;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $pairs[] = [$srcDate, $destDate];
        }
        if (empty($pairs) || count($pairs) > 31) {
            $_SESSION['flash_error'] = 'Agregá entre 1 y 31 pares de fechas para duplicar.';
            return null;
        }
        return $pairs;
    }

    /** Carga masiva por sucursal, con verificación previa. */
    public function cargaMasiva(){
        $data = $this->orgData();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $data['realMode']) {
            csrf_verify();
            $action = postString('action');
            $req = [
                'branch_name' => postString('branch_name'),
                'start_date'  => postString('start_date'),
                'end_date'    => postString('end_date'),
                'start_time'  => postString('start_time'),
                'end_time'    => postString('end_time'),
                'overwrite'   => !empty($_POST['overwrite']),
                'employee_ids' => array_map('intval', (array)($_POST['employee_ids'] ?? [])),
            ];
            $timeOk = $this->validTime($req['start_time']) && $this->validTime($req['end_time'])
                && $req['start_time'] !== $req['end_time'];
            $dateOk = $this->validDate($req['start_date']) && $this->validDate($req['end_date'])
                && $req['start_date'] <= $req['end_date']
                && count($this->dateRange($req['start_date'], $req['end_date'])) <= 93;
            $targets = array_values(array_filter($data['employees'], fn($e) => in_array($e->id, $req['employee_ids'], true)));
            $branchOk = $req['branch_name'] !== '' && in_array($req['branch_name'], $this->orgBranchCatalog(), true);
            if (!$timeOk || !$dateOk || empty($targets) || !$branchOk) {
                $_SESSION['flash_error'] = 'Revisá sucursal, fechas (máx. 93 días), horario (entrada distinta de salida) y empleados seleccionados.';
                redirect('registroHoras/cargaMasiva');
            }

            $preview = $this->buildMassivePreview($targets, $req);
            if ($action === 'execute') {
                $result = $this->executeMassive($preview, $req);
                $_SESSION['flash_success'] = "Carga masiva completada: {$result['days']} día(s) cargados a {$result['emps']} empleado(s)."
                    . ($result['skipped'] > 0 ? " {$result['skipped']} día(s) omitidos." : '');
                redirect('registroHoras/vistaGeneral');
            }
            $data['preview'] = $preview;
            $data['previewReq'] = $req;
        }

        $this->render('registro_horas/carga_masiva', $data);
    }

    /** POST: edición individual de un bloque cargado (lápiz de "Por empleado"). */
    public function editarBloque(){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('registroHoras/horarios');
        }
        csrf_verify();
        $blockId = (int)($_POST['block_id'] ?? 0);
        $block = $this->service !== null ? $this->service->getBlockById($blockId) : null;
        $employee = $block ? $this->resolveEmployee($block->user_id) : null;
        if (!$block || !$employee || !in_array($block->type, RegistroHorasService::WORK_TYPES, true)) {
            $_SESSION['flash_error'] = 'Bloque no válido o sin permiso para editarlo.';
            redirect('registroHoras/horarios');
        }
        $date = postString('schedule_date');
        $start = postString('start_time');
        $end = postString('end_time');
        $branch = postString('branch_name');
        $notes = postString('notes');
        // Sucursal: del catálogo de la organización, o la que el bloque ya
        // tenía (datos legados con sucursal renombrada se preservan).
        $branchOk = $branch !== '' && (in_array($branch, $this->orgBranchCatalog(), true)
            || $branch === (string)($block->branch_name ?? ''));
        if (!$this->validDate($date) || !$this->validTime($start) || !$this->validTime($end)
            || $start === $end || !$branchOk) {
            $_SESSION['flash_error'] = 'Revisá fecha, horario (entrada distinta de salida) y sucursal.';
            $this->horariosRedirect($employee->id);
        }
        // A diferencia de edit_hours de hoursapp, acá sí se valida el destino:
        // no se puede dejar un bloque (ni la cola de su cruce de medianoche)
        // en un día bloqueado por estado/licencia. La fecha original queda
        // exenta (corrección in situ de anomalías).
        $segDates = array_column($this->service->splitRange($date, $start, $end), 0);
        $checkDates = array_values(array_diff($segDates, [$block->schedule_date]));
        if (!empty($checkDates)) {
            $classified = $this->service->classifyDates($employee->id, $checkDates);
            if (!empty($classified['blocked'])) {
                $bDate = $classified['blocked'][0];
                $reason = mb_strtolower($classified['blocked_reason'][$bDate] ?? 'licencia');
                $_SESSION['flash_error'] = 'No se puede dejar el bloque en el ' . date('d/m', strtotime($bDate)) . ": ese día está bloqueado por {$reason}.";
                $this->horariosRedirect($employee->id);
            }
        }
        if ($this->service->replaceBlock($employee->id, $blockId, $date, $start, $end, $branch, $notes)) {
            $crossesMidnight = $end < $start;
            $_SESSION['flash_success'] = 'Bloque actualizado.' . ($crossesMidnight ? ' El horario cruza medianoche: quedó dividido en dos días.' : '');
        } else {
            $_SESSION['flash_error'] = 'No se pudo actualizar el bloque.';
        }
        $this->horariosRedirect($employee->id);
    }

    /** POST: borrado de bloques por id — individual (tacho) o selección múltiple. */
    public function eliminarBloque(){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('registroHoras/horarios');
        }
        csrf_verify();
        $ids = array_map('intval', (array)($_POST['block_ids'] ?? []));
        if (!empty($_POST['block_id'])) {
            $ids[] = (int)$_POST['block_id'];
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids) || $this->service === null) {
            $_SESSION['flash_error'] = 'No se seleccionó ningún bloque para eliminar.';
            $this->horariosRedirect((int)($_POST['employee_id'] ?? 0));
        }
        // Validación bloque por bloque: pertenencia al grupo y tipo de trabajo.
        $byUser = [];
        foreach ($ids as $id) {
            $b = $this->service->getBlockById($id);
            if ($b && in_array($b->type, RegistroHorasService::WORK_TYPES, true)) {
                $byUser[(int)$b->user_id][] = $id;
            }
        }
        $deleted = 0;
        $lastEmployeeId = (int)($_POST['employee_id'] ?? 0);
        foreach ($byUser as $uid => $userBlockIds) {
            if ($this->resolveEmployee($uid) === null) {
                continue;
            }
            $deleted += $this->service->deleteBlocks($uid, $userBlockIds);
            $lastEmployeeId = $uid;
        }
        if ($deleted > 0) {
            $_SESSION['flash_success'] = "Se eliminaron {$deleted} bloque(s) de horario.";
        } else {
            $_SESSION['flash_error'] = 'No se eliminó ningún bloque (sin permiso o ya no existían).';
        }
        $this->horariosRedirect($lastEmployeeId);
    }

    /**
     * Borrado masivo por sucursal y rango, con verificación previa — port de
     * delete_hours_massive de hoursapp con las correcciones acordadas: sin
     * recálculo de extras (acá se derivan al leer), exclusión por empleado y
     * por fecha en la verificación, confirmación "ELIMINAR" validada en
     * SERVIDOR, colas nocturnas del día siguiente detectadas (opt-out) y
     * auditoría del evento. vacation/leave nunca se tocan.
     */
    public function borradoMasivo(){
        $data = $this->orgData();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $data['realMode']) {
            csrf_verify();
            $action = postString('action');
            $req = [
                'branch_name' => postString('branch_name'),
                'start_date'  => postString('start_date'),
                'end_date'    => postString('end_date'),
            ];
            $dateOk = $this->validDate($req['start_date']) && $this->validDate($req['end_date'])
                && $req['start_date'] <= $req['end_date']
                && count($this->dateRange($req['start_date'], $req['end_date'])) <= 93;
            $branchOk = $req['branch_name'] !== ''
                && in_array($req['branch_name'], array_merge(...array_values($data['branchesByCity'])), true);
            if (!$dateOk || !$branchOk) {
                $_SESSION['flash_error'] = 'Revisá sucursal y fechas: rango válido de hasta 93 días.';
                redirect('registroHoras/borradoMasivo');
            }
            $dates = $this->dateRange($req['start_date'], $req['end_date']);

            $blocks = $this->service->findWorkBlocksByCompanies($this->orgCompanyIds(), $req['start_date'], $req['end_date'], $req['branch_name']);
            $blocks = $this->restrictRowsToStaffScope($blocks, 'user_id');
            if (empty($blocks)) {
                $_SESSION['flash_error'] = 'No hay registros de horas en las fechas seleccionadas para esa sucursal.';
                redirect('registroHoras/borradoMasivo');
            }

            if ($action === 'execute') {
                $this->executeMassDelete($blocks, $dates, $req);
                return;
            }
            $data['preview'] = $this->buildDeletePreview($blocks, $dates, $req);
            $data['previewReq'] = $req;
        }

        $this->render('registro_horas/borrado_masivo', $data);
    }

    /** Estructura de la verificación previa del borrado masivo. */
    private function buildDeletePreview(array $blocks, array $dates, $req){
        $dateSet = array_flip($dates);
        $byUser = [];
        $dateCounts = array_fill_keys($dates, 0);
        $tailCandidates = []; // [user_id, D+1] de cabezas 23:59 cuyo día siguiente queda fuera
        $intruderCandidates = []; // [user_id, D-1] de colas 00:00 cuya cabeza quedaría suelta
        foreach ($blocks as $b) {
            $uid = (int)$b->user_id;
            if (!isset($byUser[$uid])) {
                $byUser[$uid] = [
                    'user_id' => $uid,
                    'name'    => $b->full_name,
                    'active'  => (int)$b->is_active === 1,
                    'count'   => 0,
                    'minutes' => 0,
                    'dates'   => [],
                    'types'   => [],
                    'night'   => false,
                ];
            }
            $byUser[$uid]['count']++;
            $byUser[$uid]['minutes'] += $this->service->blockMinutes($b->start_time, $b->end_time);
            $byUser[$uid]['dates'][$b->schedule_date] = ($byUser[$uid]['dates'][$b->schedule_date] ?? 0) + 1;
            $byUser[$uid]['types'][$b->type] = ($byUser[$uid]['types'][$b->type] ?? 0) + 1;
            if (isset($dateCounts[$b->schedule_date])) {
                $dateCounts[$b->schedule_date]++;
            }
            $end5 = substr((string)$b->end_time, 0, 5);
            $start5 = substr((string)$b->start_time, 0, 5);
            if ($end5 === '23:59') {
                $byUser[$uid]['night'] = true;
                $next = date('Y-m-d', strtotime($b->schedule_date . ' +1 day'));
                if (!isset($dateSet[$next])) {
                    $tailCandidates[] = [$uid, $next];
                }
            }
            if ($start5 === '00:00') {
                $byUser[$uid]['night'] = true;
                $prev = date('Y-m-d', strtotime($b->schedule_date . ' -1 day'));
                if (!isset($dateSet[$prev])) {
                    $intruderCandidates[] = [$uid, $prev];
                }
            }
        }

        // Colas nocturnas huérfanas hacia adelante (se ofrecen para borrar) e
        // intrusos hacia atrás (solo advertencia: su cabeza quedará suelta).
        $tails = $this->matchEdgeBlocks($tailCandidates, $req['branch_name'], 'tail');
        $intruders = $this->matchEdgeBlocks($intruderCandidates, $req['branch_name'], 'head');

        // Feriados que aplican a ESTA sucursal (informativos).
        $ctx = $this->service->buildHolidayContext($this->orgCompanyIds(), $dates[0], end($dates));
        $holidays = [];
        foreach ($dates as $d) {
            $n = $this->service->blockHolidayName($ctx, $d, (int)adminCompanyId(), null, $req['branch_name']);
            if ($n !== null) {
                $holidays[$d] = $n;
            }
        }

        return [
            'rows'       => array_values($byUser),
            'dateCounts' => $dateCounts,
            'holidays'   => $holidays,
            'total'      => count($blocks),
            'tails'      => $tails,
            'intruders'  => array_keys($intruders),
        ];
    }

    /** Bloques borde que EXISTEN para los pares candidato [user_id, fecha]. Devuelve user_id => filas. */
    private function matchEdgeBlocks(array $candidates, $branchName, $edge){
        if (empty($candidates)) {
            return [];
        }
        $map = $this->service->findEdgeBlocks(
            array_unique(array_column($candidates, 0)),
            array_unique(array_column($candidates, 1)),
            $branchName,
            $edge
        );
        $out = [];
        foreach ($candidates as $c) {
            foreach ($map[$c[0]][$c[1]] ?? [] as $row) {
                $out[$c[0]][] = $row;
            }
        }
        return $out;
    }

    /** Ejecución del borrado masivo sobre datos FRESCOS (nunca ids del preview). */
    private function executeMassDelete(array $blocks, array $dates, $req){
        if (postString('confirm_text') !== 'ELIMINAR') {
            $_SESSION['flash_error'] = 'Escribí ELIMINAR en el campo de confirmación para ejecutar el borrado.';
            redirect('registroHoras/borradoMasivo');
        }
        $includeEmp = array_map('intval', (array)($_POST['include_employee_ids'] ?? []));
        $includeDates = array_values(array_intersect((array)($_POST['include_dates'] ?? []), $dates));
        if (empty($includeEmp) || empty($includeDates)) {
            $_SESSION['flash_error'] = 'Dejá seleccionado al menos un colaborador y una fecha para borrar.';
            redirect('registroHoras/borradoMasivo');
        }
        $dateSet = array_flip($includeDates);
        $rangeSet = array_flip($dates);
        $toDelete = []; // user_id => [ids]
        $tailCandidates = [];
        $skipped = 0;
        foreach ($blocks as $b) {
            $uid = (int)$b->user_id;
            if (!in_array($uid, $includeEmp, true) || !isset($dateSet[$b->schedule_date])) {
                $skipped++;
                continue;
            }
            $toDelete[$uid][] = (int)$b->id;
            if (substr((string)$b->end_time, 0, 5) === '23:59') {
                // Cola nocturna SOLO si el día siguiente cae FUERA del rango
                // completo (igual que la verificación previa): una fecha del
                // rango desmarcada por el operador se respeta y no se toca.
                $next = date('Y-m-d', strtotime($b->schedule_date . ' +1 day'));
                if (!isset($rangeSet[$next])) {
                    $tailCandidates[] = [$uid, $next];
                }
            }
        }
        $deleted = 0;
        foreach ($toDelete as $uid => $ids) {
            $deleted += $this->service->deleteBlocks($uid, $ids);
        }
        $tailsDeleted = 0;
        if (!empty($_POST['delete_night_tails'])) {
            foreach ($this->matchEdgeBlocks($tailCandidates, $req['branch_name'], 'tail') as $uid => $rows) {
                $tailsDeleted += $this->service->deleteBlocks($uid, array_map(fn($r) => (int)$r->id, $rows));
            }
        }

        try {
            if (class_exists('AuditService')) {
                (new AuditService())->record(
                    'registro_horas.borrado_masivo',
                    'employee_schedules',
                    $req['branch_name'],
                    null,
                    [
                        'branch'    => $req['branch_name'],
                        'desde'     => $req['start_date'],
                        'hasta'     => $req['end_date'],
                        'borrados'  => $deleted,
                        'colas'     => $tailsDeleted,
                        'excluidos' => $skipped,
                        'empleados' => count($toDelete),
                    ],
                    'Borrado masivo del Registro de Horas',
                    (int)adminCompanyId()
                );
            }
        } catch (Throwable $e) {
            // la auditoría nunca frena la operación ya ejecutada
        }

        $msg = "Borrado masivo completado: {$deleted} bloque(s) eliminados de " . count($toDelete) . " colaborador(es) en {$req['branch_name']}.";
        if ($skipped > 0) {
            $msg .= " Excluidos: {$skipped} bloque(s).";
        }
        if ($tailsDeleted > 0) {
            $msg .= " Se eliminaron además {$tailsDeleted} tramo(s) nocturnos del día siguiente.";
        }
        $_SESSION['flash_success'] = $msg;
        redirect('registroHoras/vistaGeneral');
    }

    /** Estados del empleado (Guardia/Vacaciones/Licencia): períodos que bloquean la carga. */
    public function estados(){
        $data = $this->orgData();
        $data['statusReady'] = $this->service !== null && $this->service->statusTableReady();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $data['realMode'] && $data['statusReady']) {
            csrf_verify();
            $action = postString('action');
            if ($action === 'create') {
                $employee = $this->resolveEmployee((int)($_POST['employee_id'] ?? 0));
                $status = postString('status');
                $start = postString('start_date');
                $end = postString('end_date');
                $ok = $employee !== null
                    && in_array($status, ['guardia', 'vacaciones', 'licencia'], true)
                    && $this->validDate($start) && $this->validDate($end)
                    && $start <= $end
                    && (strtotime($end) - strtotime($start)) <= 366 * 86400;
                if ($ok && $this->service->addStatusPeriod($employee->id, $status, $start, $end, postString('notes'), (int)($_SESSION['user_id'] ?? 0))) {
                    $_SESSION['flash_success'] = RegistroHorasService::statusLabel($status) . ' registrada para ' . $employee->full_name
                        . ' del ' . date('d/m', strtotime($start)) . ' al ' . date('d/m', strtotime($end))
                        . '. La carga de horas queda bloqueada en esas fechas.';
                } else {
                    $_SESSION['flash_error'] = 'Revisá empleado, estado y rango de fechas (máx. 1 año).';
                }
            } elseif ($action === 'delete') {
                $employee = $this->resolveEmployee((int)($_POST['employee_id'] ?? 0));
                $periodId = (int)($_POST['period_id'] ?? 0);
                if ($employee !== null && $this->service->deleteStatusPeriod($periodId, $employee->id)) {
                    $_SESSION['flash_success'] = 'Período de estado eliminado.';
                } else {
                    $_SESSION['flash_error'] = 'No se pudo eliminar el período.';
                }
            }
            redirect('registroHoras/estados');
        }

        if ($data['realMode'] && $data['statusReady']) {
            $data['periods'] = $this->service->listStatusPeriods(array_column($data['employees'], 'id'), date('Y-m-d'));
        } else {
            $data['periods'] = [];
        }
        $data['employeesById'] = [];
        foreach ($data['employees'] as $e) {
            $data['employeesById'][$e->id] = $e;
        }
        $this->render('registro_horas/estados', $data);
    }

    /**
     * GET JSON para la vista previa en vivo de la carga: por fecha del rango,
     * conflictos existentes, días bloqueados (con motivo) y feriados según la
     * sucursal elegida (y la del segundo turno). El cálculo de horas/extras lo
     * hace el cliente con las mismas reglas.
     */
    public function previewData(){
        header('Content-Type: application/json; charset=utf-8');
        $fail = function ($error) {
            echo json_encode(['ok' => false, 'error' => $error]);
            exit;
        };
        if ($this->service === null) {
            $fail('schema');
        }
        $start = (string)($_GET['start'] ?? '');
        $end = (string)($_GET['end'] ?? '');
        if (!$this->validDate($start) || !$this->validDate($end) || $start > $end) {
            $fail('fechas');
        }
        $dates = $this->dateRange($start, $end);
        if (count($dates) > 93) {
            $fail('rango');
        }
        $employee = $this->resolveEmployee((int)($_GET['employee_id'] ?? 0));
        if ($employee === null) {
            $fail('empleado');
        }
        $branch = (string)($_GET['branch'] ?? '');
        $branch2 = (string)($_GET['branch2'] ?? '');

        $classified = $this->service->classifyDates($employee->id, $dates);
        $ctx = $this->service->buildHolidayContext($this->orgCompanyIds(), $start, $end);
        $empCompanyId = (int)($employee->company_id ?? 0) ?: (int)adminCompanyId();

        $conflicts = [];
        foreach ($classified['conflict'] as $date => $blocks) {
            // Igual que buildMonthRecords: bloques sin horas (shift huérfano de
            // shift_time_ranges) no viajan — romperían el cálculo del cliente.
            $withTimes = array_values(array_filter($blocks, fn($b) => $b->start_time && $b->end_time));
            if (empty($withTimes)) {
                continue;
            }
            $conflicts[$date] = array_map(function ($b) {
                return [
                    'start'  => substr((string)$b->start_time, 0, 5),
                    'end'    => substr((string)$b->end_time, 0, 5),
                    'branch' => (string)($b->branch_name ?? ''),
                ];
            }, $withTimes);
        }
        $holidays = [];
        $holidays2 = [];
        foreach ($dates as $d) {
            $n = $this->service->blockHolidayName($ctx, $d, $empCompanyId, null, $branch !== '' ? $branch : null);
            if ($n !== null) {
                $holidays[$d] = $n;
            }
            if ($branch2 !== '') {
                $n2 = $this->service->blockHolidayName($ctx, $d, $empCompanyId, null, $branch2);
                if ($n2 !== null) {
                    $holidays2[$d] = $n2;
                }
            }
        }

        echo json_encode([
            'ok'        => true,
            'org'       => org_current_group(),
            'blocked'   => $classified['blocked_reason'],
            'conflicts' => $conflicts,
            'holidays'  => $holidays,
            'holidays2' => $branch2 !== '' ? $holidays2 : $holidays,
        ]);
        exit;
    }

    /** Vuelta a "Por empleado" conservando los filtros con los que se abrió el modal. */
    private function horariosRedirect($employeeId){
        $qs = ['employee_id' => (int)$employeeId];
        foreach (['month', 'from', 'to', 'city', 'branch'] as $f) {
            $v = postString('back_' . $f);
            if ($v !== '' && preg_match('/^[\p{L}\p{N} .\'&()\-]{1,80}$/u', $v)) {
                $qs[$f] = $v;
            }
        }
        redirect('registroHoras/horarios?' . http_build_query($qs));
    }

    /** Receptor de los formularios en modo maqueta (empresa sin nómina): no persiste nada. */
    public function mockSubmit(){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('registroHoras/carga');
        }
        csrf_verify();
        $back = postString('mock_back');
        $allowed = ['vistaGeneral', 'carga', 'horarios', 'duplicar', 'cargaMasiva', 'borradoMasivo', 'estados'];
        if (!in_array($back, $allowed, true)) {
            $back = 'carga';
        }
        $_SESSION['flash_success'] = 'Maqueta: esta empresa aún no tiene nómina cargada, el formulario es una vista previa.';
        redirect('registroHoras/' . $back);
    }

    /** Simulador de organización (barra superior) — fase pre-migración. */
    public function setOrg(){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/dashboard');
        }
        csrf_verify();
        if (!org_simulator_available()) {
            $_SESSION['flash_error'] = 'La organización ya está definida por el usuario; el simulador no aplica.';
            redirect('admin/dashboard');
        }
        $group = org_set_simulated_group(postString('org_group'));

        // La vista Moderna aísla datos cambiando la empresa activa a "Moderna";
        // al volver a Paviotti se restaura la empresa previa.
        if (function_exists('isAdmin') && isAdmin() && function_exists('setAdminActiveCompany')) {
            if ($group === 'moderna') {
                $modernaId = org_moderna_company_id();
                if ($modernaId) {
                    $_SESSION['sim_prev_company_id'] = (int)($_SESSION['user_company_id'] ?? 0);
                    setAdminActiveCompany($modernaId);
                }
            } else {
                $prev = (int)($_SESSION['sim_prev_company_id'] ?? 0);
                if ($prev > 0 && $prev !== org_moderna_company_id()) {
                    setAdminActiveCompany($prev);
                    unset($_SESSION['sim_prev_company_id']);
                }
            }
        }

        $_SESSION['flash_success'] = 'Vista cambiada a organización ' . org_display_label($group) . ' (simulador).';
        $return = function_exists('admin_safe_return_path')
            ? admin_safe_return_path(postString('return_url'))
            : 'admin/dashboard';
        redirect($return);
    }

    // ─────────────────────── Datos y helpers ───────────────────────

    /** Datos comunes: organización, empleados reales de la empresa activa o muestra. */
    private function orgData(){
        $org = org_current_group();
        $real = $this->realEmployees();
        return [
            'org'            => $org,
            'orgLabel'       => org_display_label($org),
            'realMode'       => $real !== null && count($real) > 0,
            'employees'      => ($real !== null && count($real) > 0) ? $real : org_sample_employees($org),
            'branchesByCity' => org_branches_by_city($org),
            'companyName'    => (string)($_SESSION['user_company_name'] ?? ''),
        ];
    }

    /** Ids de empresas sobre las que opera el módulo: todo el grupo organizacional. */
    private function orgCompanyIds(){
        $ids = function_exists('org_group_company_ids') ? org_group_company_ids(org_current_group()) : [];
        if (empty($ids)) {
            $active = function_exists('adminCompanyId') ? (int)adminCompanyId() : 0;
            $ids = $active > 0 ? [$active] : [];
        }
        return $ids;
    }

    /**
     * Empleados activos reales de TODO el grupo (null si el esquema no está migrado).
     * El Registro de Horas no distingue empresa: RRHH carga para cualquier
     * sociedad de su organización.
     */
    private function realEmployees(){
        if ($this->service === null) {
            return null;
        }
        $companyIds = $this->orgCompanyIds();
        if (empty($companyIds)) {
            return null;
        }
        $rows = $this->service->getActiveEmployeesForCompanies($companyIds);
        // Sub-alcance del staff: supervisores legacy por área y encargados de
        // access_control por sucursal. (filterStaffRowsByUserArea NO sirve acá:
        // espera filas con user_id y estas traen id — descartaría todo.)
        $rows = $this->restrictRowsToStaffScope($rows, 'id');
        // Estado real del día: período vigente en employee_status_periods
        // (Guardia/Vacaciones/Licencia declarado por RRHH) o Activo.
        $statuses = $this->service->currentStatuses(array_map(fn($u) => (int)$u->id, $rows), date('Y-m-d'));
        $companyName = (string)($_SESSION['user_company_name'] ?? 'Empresa');
        return array_map(function ($u) use ($companyName, $statuses) {
            // Sucursal real (company_branches) > área homónima > empresa como respaldo.
            $branch = !empty($u->branch_name) ? $u->branch_name
                : (!empty($u->area_name) ? $u->area_name : ($companyName . ' (sin sucursal)'));
            return (object)[
                'id'         => (int)$u->id,
                'name'       => $u->full_name,
                'full_name'  => $u->full_name,
                'branch'     => $branch,
                'branch_id'  => !empty($u->branch_id) ? (int)$u->branch_id : null,
                'company_id' => (int)($u->company_id ?? 0),
                'city'       => $branch,
                'state'      => $statuses[(int)$u->id] ?? 'Activo',
                'cajero'     => false,
                'manager'    => false,
            ];
        }, $rows);
    }

    private function resolveEmployee($employeeId){
        $u = $this->userModel->getUserById((int)$employeeId);
        if (!$u || $u->role !== 'empleado' || (int)$u->is_active !== 1) {
            return null;
        }
        // Cualquier empresa del grupo organizacional del usuario logueado.
        if (!in_array((int)$u->company_id, $this->orgCompanyIds(), true)) {
            return null;
        }
        if ($this->staffScopeApplies() && function_exists('supervisorCanAccessUser') && !supervisorCanAccessUser($u)) {
            return null;
        }
        return $u;
    }

    /** El usuario logueado tiene sub-alcance (supervisor legacy o encargado de access_control). */
    private function staffScopeApplies(){
        if (function_exists('isSupervisor') && isSupervisor()) {
            return true;
        }
        return function_exists('access_control_ready') && access_control_ready()
            && function_exists('access_current_role') && access_current_role() === 'encargado';
    }

    /**
     * Restringe filas (empleados o bloques) al sub-alcance del staff logueado
     * usando supervisorCanAccessUser por empleado (cacheado). $idField indica
     * dónde vive el id de usuario en cada fila ('id' o 'user_id').
     * Admin/RRHH plenos pasan sin filtro.
     */
    private function restrictRowsToStaffScope(array $rows, $idField){
        if (!$this->staffScopeApplies() || !function_exists('supervisorCanAccessUser')) {
            return $rows;
        }
        $cache = [];
        $userModel = $this->userModel;
        return array_values(array_filter($rows, function ($r) use ($idField, $userModel, &$cache) {
            $uid = (int)($r->{$idField} ?? 0);
            if ($uid <= 0) {
                return false;
            }
            if (!array_key_exists($uid, $cache)) {
                $u = $userModel->getUserById($uid);
                $cache[$uid] = $u && supervisorCanAccessUser($u);
            }
            return $cache[$uid];
        }));
    }

    private function readCargaInput(){
        return [
            'employee_id'  => (int)($_POST['employee_id'] ?? 0),
            'start_date'   => postString('start_date'),
            'end_date'     => postString('end_date'),
            'start_time'   => postString('start_time'),
            'end_time'     => postString('end_time'),
            'branch_name'  => postString('branch_name'),
            'two_turns'    => !empty($_POST['two_turns']),
            'start_time_2' => postString('start_time_2'),
            'end_time_2'   => postString('end_time_2'),
            'branch_name_2' => postString('branch_name_2'),
            'confirm_mode' => postString('confirm_mode'),
        ];
    }

    /** Fecha ISO con calendario real (rechaza 2026-02-30, 2026-13-05). */
    private function validDate($value){
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string)$value, $m)) {
            return false;
        }
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
    }

    /** Hora HH:MM real (rechaza 25:99). */
    private function validTime($value){
        if (!preg_match('/^(\d{2}):(\d{2})$/', (string)$value, $m)) {
            return false;
        }
        return (int)$m[1] <= 23 && (int)$m[2] <= 59;
    }

    private function validateCargaInput($input, $employee){
        $errors = [];
        if (!$employee) {
            $errors[] = 'Empleado no válido para esta organización.';
        }
        foreach (['start_date', 'end_date'] as $f) {
            if (!$this->validDate($input[$f])) {
                $errors[] = 'Fechas inválidas.';
                return $errors;
            }
        }
        if ($input['start_date'] > $input['end_date']) {
            $errors[] = 'La fecha "Desde" no puede ser posterior a "Hasta".';
        } elseif (count($this->dateRange($input['start_date'], $input['end_date'])) > 93) {
            $errors[] = 'El rango no puede superar 93 días.';
        }
        foreach (['start_time', 'end_time'] as $f) {
            if (!$this->validTime($input[$f])) {
                $errors[] = 'Horario inválido.';
            }
        }
        if ($input['start_time'] === $input['end_time']) {
            $errors[] = 'La entrada y la salida no pueden ser iguales.';
        }
        // La sucursal debe pertenecer al catálogo de la organización (impide
        // asociar horas a sucursales de otro grupo por POST forjado).
        $catalog = $this->orgBranchCatalog();
        if ($input['branch_name'] === '' || !in_array($input['branch_name'], $catalog, true)) {
            $errors[] = 'Elegí una sucursal válida.';
        }
        if ($input['two_turns']) {
            if (!$this->validTime($input['start_time_2']) || !$this->validTime($input['end_time_2'])) {
                $errors[] = 'Horario del segundo turno inválido.';
            } elseif ($input['start_time_2'] === $input['end_time_2']) {
                $errors[] = 'En el segundo turno, la entrada y la salida no pueden ser iguales.';
            }
            if ($input['branch_name_2'] !== '' && !in_array($input['branch_name_2'], $catalog, true)) {
                $errors[] = 'La sucursal del segundo turno no es válida.';
            }
        }
        return $errors;
    }

    /** Sucursales válidas de la organización activa (aplanadas). */
    private function orgBranchCatalog(){
        static $catalog = null;
        if ($catalog === null) {
            $byCity = org_branches_by_city(org_current_group());
            $catalog = empty($byCity) ? [] : array_merge(...array_values($byCity));
        }
        return $catalog;
    }

    private function dateRange($start, $end){
        $dates = [];
        $cursor = strtotime($start);
        $endTs = strtotime($end);
        while ($cursor <= $endTs && count($dates) <= 94) {
            $dates[] = date('Y-m-d', $cursor);
            $cursor = strtotime('+1 day', $cursor);
        }
        return $dates;
    }

    private function weekMonday($isoWeek){
        if (preg_match('/^(\d{4})-W(\d{2})$/', $isoWeek, $m)) {
            $dt = new DateTime();
            $dt->setISODate((int)$m[1], (int)$m[2]);
            return $dt->format('Y-m-d');
        }
        return date('Y-m-d', strtotime('monday this week'));
    }

    private function weekDays($monday){
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = date('Y-m-d', strtotime("+{$i} day", strtotime($monday)));
        }
        return $days;
    }

    /** Estructura de la vista general: user_id => [dow 1..7 => ['ranges'=>[], 'absent'=>string|null]]. */
    private function buildWeekSchedule($employees, $weekDays, $blocksMap, array $statusMap = []){
        $schedule = [];
        foreach ($employees as $emp) {
            $days = [];
            foreach ($weekDays as $i => $date) {
                $dow = $i + 1;
                $ranges = [];
                $absent = null;
                foreach ($blocksMap[$emp->id][$date] ?? [] as $b) {
                    if ($b->type === 'vacation' || $b->type === 'leave') {
                        $absent = $b->type === 'vacation' ? 'Vacaciones' : 'Licencia';
                        continue;
                    }
                    if ($b->start_time && $b->end_time) {
                        $ranges[] = substr($b->start_time, 0, 5) . ' – ' . substr($b->end_time, 0, 5)
                            . ($b->branch_name ? ' · ' . $b->branch_name : '');
                    }
                }
                // Estados declarados (Guardia/…): marcan ausencia si el día cae
                // dentro de un período y no hay otra ausencia más específica.
                if ($absent === null) {
                    foreach ($statusMap[$emp->id] ?? [] as $p) {
                        if ($date >= $p->start_date && $date <= $p->end_date) {
                            $absent = RegistroHorasService::statusLabel($p->status);
                            break;
                        }
                    }
                }
                $days[$dow] = ['ranges' => $ranges, 'absent' => $absent];
            }
            $schedule[$emp->id] = ['days' => $days];
        }
        return $schedule;
    }

    /** Registros del mes para "Por empleado" a partir de bloques reales. */
    private function buildMonthRecords($org, $blocksMap, array $holidayCtx, $empCompanyId){
        $records = [];
        $svc = $this->service;
        $blockHolidayFn = function ($b) use ($svc, $holidayCtx, $empCompanyId) {
            return $svc->blockHolidayName(
                $holidayCtx,
                $b->schedule_date ?? '',
                $empCompanyId,
                $b->branch_id ?? null,
                $b->branch_name ?? null
            ) !== null;
        };
        foreach ($blocksMap as $date => $blocks) {
            $work = array_values(array_filter($blocks, function ($b) {
                return in_array($b->type, RegistroHorasService::WORK_TYPES, true) && $b->start_time && $b->end_time;
            }));
            if (empty($work)) {
                continue;
            }
            $day = $this->service->computeDay($org, $date, $work, false, $blockHolidayFn);
            foreach ($work as $b) {
                $records[] = (object)[
                    'id'          => (int)($b->id ?? 0),
                    'date'        => $date,
                    'start_time'  => substr($b->start_time, 0, 5),
                    'end_time'    => substr($b->end_time, 0, 5),
                    'branch_name' => $b->branch_name ?: 'Sin sucursal',
                    // sucursal cruda para el modal de edición ('' si no tiene):
                    // la etiqueta "Sin sucursal" es solo de presentación y no
                    // debe persistirse como si fuera una sucursal real.
                    'branch_raw'  => (string)($b->branch_name ?? ''),
                    'notes'       => (string)($b->notes ?? ''),
                    'type'        => (string)($b->type ?? 'custom'),
                    'hours'       => round($this->service->blockMinutes($b->start_time, $b->end_time) / 60, 2),
                    // la extra se atribuye al bloque (y sucursal) que la genera
                    'extra_hours' => $day['block_extra'][(int)($b->id ?? 0)] ?? 0,
                    // el badge Feriado marca el BLOQUE cuya sucursal tiene el feriado
                    'is_holiday'  => $blockHolidayFn($b),
                    // bloque nocturno heredado partido virtualmente en lectura:
                    // editar/borrar opera sobre el registro original completo,
                    // por eso el modal precarga los datos ORIGINALES (no la mitad)
                    'nocturnal_legacy' => !empty($b->nocturnal_legacy),
                    'virtual_part'     => $b->virtual_part ?? null,
                    'orig_date'        => (string)($b->orig_date ?? $date),
                    'orig_start'       => isset($b->orig_start) ? (string)$b->orig_start : substr($b->start_time, 0, 5),
                    'orig_end'         => isset($b->orig_end) ? (string)$b->orig_end : substr($b->end_time, 0, 5),
                ];
            }
        }
        return $records;
    }

    private function pickEmployee($employees, $selectedId){
        foreach ($employees as $e) {
            if ($e->id === $selectedId) {
                return $e;
            }
        }
        return $employees[0];
    }

    // ─────────────── Duplicación / masiva: preview y ejecución ───────────────

    /**
     * Vista previa de duplicación sobre pares [origen, destino] arbitrarios.
     * Varios orígenes al mismo destino se combinan en una sola fila (el destino
     * se guarda UNA vez con todos los bloques). Con $branchFilter solo se copian
     * los bloques registrados en esa sucursal (modo masivo por sucursal), y los
     * empleados sin horas ahí quedan fuera.
     * rows: [{employee, src_dates, dest_date, blocks, is_holiday,
     *         status: copy|conflict|blocked, status_reason}]
     */
    private function buildDuplicatePreview($targets, array $pairs, $branchFilter = null){
        $srcDates = array_column($pairs, 0);
        $destDates = array_unique(array_column($pairs, 1));
        sort($srcDates);
        sort($destDates);
        // Feriados por bloque: nacionales para todos; locales según la sucursal
        // de cada horario a copiar.
        $holidayCtx = $this->service->buildHolidayContext($this->orgCompanyIds(), $destDates[0], end($destDates));

        $rows = [];
        foreach ($targets as $emp) {
            $srcMap = $this->service->getBlocksForRange($emp->id, $srcDates[0], end($srcDates));
            $byDest = [];
            foreach ($pairs as $pair) {
                [$srcDate, $destDate] = $pair;
                $work = array_values(array_filter($srcMap[$srcDate] ?? [], function ($b) use ($branchFilter) {
                    if (!in_array($b->type, RegistroHorasService::WORK_TYPES, true) || !$b->start_time || !$b->end_time) {
                        return false;
                    }
                    return $branchFilter === null || $b->branch_name === $branchFilter;
                }));
                if (empty($work)) {
                    continue;
                }
                if (!isset($byDest[$destDate])) {
                    $byDest[$destDate] = ['srcs' => [], 'work' => []];
                }
                $byDest[$destDate]['srcs'][] = $srcDate;
                $byDest[$destDate]['work'] = array_merge($byDest[$destDate]['work'], $work);
            }
            if (empty($byDest)) {
                continue;
            }
            $classified = $this->service->classifyDates($emp->id, array_keys($byDest));
            $empCompanyId = (int)($emp->company_id ?? 0) ?: (int)adminCompanyId();
            ksort($byDest);
            foreach ($byDest as $destDate => $info) {
                if (in_array($destDate, $classified['blocked'], true)) {
                    $status = 'blocked';
                } elseif (isset($classified['conflict'][$destDate])) {
                    $status = 'conflict';
                } else {
                    $status = 'copy';
                }
                $anyHoliday = false;
                foreach ($info['work'] as $wb) {
                    if ($this->service->blockHolidayName($holidayCtx, $destDate, $empCompanyId, $wb->branch_id ?? null, $wb->branch_name ?? null) !== null) {
                        $anyHoliday = true;
                        break;
                    }
                }
                $rows[] = [
                    'employee'      => $emp,
                    'src_dates'     => $info['srcs'],
                    'dest_date'     => $destDate,
                    'is_holiday'    => $anyHoliday,
                    'status'        => $status,
                    'status_reason' => $classified['blocked_reason'][$destDate] ?? null,
                    'blocks'        => array_map(function ($b) {
                        return [
                            'start'  => substr($b->start_time, 0, 5),
                            'end'    => substr($b->end_time, 0, 5),
                            'branch' => $b->branch_name ?: '',
                            'notes'  => $b->notes ?: '',
                        ];
                    }, $info['work']),
                ];
            }
        }
        return $rows;
    }

    /**
     * $overwriteIds: modo sucursal — ids de empleados con permiso de
     * sobrescritura (checkbox por empleado en la verificación); null = usa el
     * checkbox global $overwrite para todos.
     * $keep: claves "empId|fechaDestino" de las filas marcadas "Mantener";
     * null = se mantienen todas.
     */
    private function executeDuplicate($previewRows, $overwrite, $overwriteIds = null, $keep = null){
        $days = 0; $skipped = 0; $emps = [];
        foreach ($previewRows as $row) {
            if ($keep !== null && !in_array($row['employee']->id . '|' . $row['dest_date'], $keep, true)) {
                $skipped++;
                continue;
            }
            $allowOverwrite = $overwriteIds === null
                ? $overwrite
                : in_array((int)$row['employee']->id, $overwriteIds, true);
            if ($row['status'] === 'blocked' || ($row['status'] === 'conflict' && !$allowOverwrite)) {
                $skipped++;
                continue;
            }
            if ($this->service->saveDay($row['employee']->id, $row['dest_date'], $row['blocks'], $row['status'] === 'conflict')) {
                $days++;
                $emps[$row['employee']->id] = true;
            } else {
                $skipped++;
            }
        }
        return ['days' => $days, 'emps' => count($emps), 'skipped' => $skipped];
    }

    /** Vista previa de carga masiva: por empleado, días con conflicto/bloqueo/feriado. */
    private function buildMassivePreview($targets, $req){
        $dates = $this->dateRange($req['start_date'], $req['end_date']);
        $holidayCtx = $this->service->buildHolidayContext($this->orgCompanyIds(), $dates[0], end($dates));
        $rows = [];
        foreach ($targets as $emp) {
            $classified = $this->service->classifyDates($emp->id, $dates);
            // Feriados del rango para la sucursal donde se van a cargar las horas.
            $holidays = [];
            foreach ($dates as $d) {
                if ($this->service->blockHolidayName($holidayCtx, $d, (int)($emp->company_id ?? 0) ?: (int)adminCompanyId(), null, $req['branch_name']) !== null) {
                    $holidays[] = $d;
                }
            }
            $rows[] = [
                'employee' => $emp,
                'dates'    => $dates,
                'blocked'  => $classified['blocked'],
                'conflict' => array_keys($classified['conflict']),
                'holidays' => $holidays,
            ];
        }
        return $rows;
    }

    /**
     * Ejecuta la carga masiva armando el plan COMPLETO por empleado antes de
     * guardar: cada fecha se persiste una sola vez con todos sus bloques
     * (el principal del día + la cola de un turno nocturno del día anterior),
     * evitando que un overwrite posterior borre segmentos recién insertados.
     */
    private function executeMassive($previewRows, $req){
        $days = 0; $skipped = 0; $emps = [];
        foreach ($previewRows as $row) {
            $emp = $row['employee'];
            $plan = [];
            $anchors = []; // fecha ancla => tenía conflicto (habilita overwrite)
            foreach ($row['dates'] as $date) {
                if (in_array($date, $row['blocked'], true)) {
                    $skipped++;
                    continue;
                }
                $isConflict = in_array($date, $row['conflict'], true);
                if ($isConflict && !$req['overwrite']) {
                    $skipped++;
                    continue;
                }
                $anchors[$date] = $isConflict;
                foreach ($this->service->splitRange($date, $req['start_time'], $req['end_time']) as $seg) {
                    $plan[$seg[0]][] = ['start' => $seg[1], 'end' => $seg[2], 'branch' => $req['branch_name']];
                }
            }
            ksort($plan);
            // Reclasificar TODAS las fechas del plan (las colas de un turno
            // nocturno caen fuera de las anclas — incluida end_date+1— y
            // también deben respetar vacaciones/licencia/guardia).
            if (!empty($plan)) {
                $fresh = $this->service->classifyDates($emp->id, array_keys($plan));
                foreach ($fresh['blocked'] as $blockedDate) {
                    if (isset($plan[$blockedDate])) {
                        unset($plan[$blockedDate]);
                        unset($anchors[$blockedDate]);
                        $skipped++;
                    }
                }
            }
            $okAll = true;
            foreach ($plan as $segDate => $segBlocks) {
                $overwrite = !empty($anchors[$segDate]) && $req['overwrite'];
                $okAll = $this->service->saveDay($emp->id, $segDate, $segBlocks, $overwrite) && $okAll;
            }
            if (!empty($plan)) {
                if ($okAll) {
                    $days += count($anchors);
                    $emps[$emp->id] = true;
                } else {
                    $skipped++;
                }
            }
        }
        return ['days' => $days, 'emps' => count($emps), 'skipped' => $skipped];
    }

    // ─────────────────────── Datos de ejemplo (maqueta) ───────────────────────

    /** Semana de ejemplo con la MISMA estructura que buildWeekSchedule. */
    private function sampleWeekSchedule($employees){
        $schedule = [];
        foreach ($employees as $i => $emp) {
            $days = [];
            for ($dow = 1; $dow <= 7; $dow++) {
                $days[$dow] = ['ranges' => [], 'absent' => null];
            }
            if ($emp->state !== 'Activo') {
                for ($dow = 1; $dow <= 7; $dow++) {
                    $days[$dow]['absent'] = $emp->state;
                }
                $schedule[$emp->id] = ['days' => $days];
                continue;
            }
            $morning = ($i % 2) === 0;
            $range = $morning ? '08:00 – 16:00' : '14:00 – 22:00';
            for ($dow = 1; $dow <= 6; $dow++) {
                if ($dow === 6) {
                    if ($morning) $days[$dow]['ranges'][] = '09:00 – 14:00';
                    continue;
                }
                if ($dow === 3 && ($i % 3) === 0) {
                    $days[$dow]['ranges'][] = '08:00 – 12:00';
                    $days[$dow]['ranges'][] = '17:00 – 21:00';
                } else {
                    $days[$dow]['ranges'][] = $range;
                }
            }
            $schedule[$emp->id] = ['days' => $days];
        }
        return $schedule;
    }

    /** Horarios de ejemplo de un mes para la vista "Por empleado". */
    private function sampleMonthRecords($org){
        if ($org === 'moderna') {
            $b1 = 'Farmacia Moderna 1'; $b2 = 'Farmacia Moderna 2'; $b24 = 'Marcellino';
        } else {
            $b1 = 'Ecofarma Central'; $b2 = 'Cruz V Centro'; $b24 = 'Cruz V Catedral';
        }
        $samples = [
            [1,  '08:00', '16:00', $b1,  8, 0, false],
            [2,  '08:00', '17:00', $b1,  9, 1, false],
            [3,  '14:00', '22:00', $b1,  8, 0, false],
            [5,  '09:00', '15:00', $b24, 6, 1, false],
            [6,  '08:00', '16:00', $b1,  8, 8, true],
            [8,  '08:00', '12:00', $b1,  4, 0, false],
            [8,  '17:00', '21:00', $b2,  4, 0, false],
            [9,  '08:00', '16:00', $b1,  8, 0, false],
            [12, '23:00', '23:59', $b24, 1, 0, false],
            [13, '00:00', '06:00', $b24, 6, 1, false],
        ];
        $records = [];
        $year  = (int)date('Y');
        $month = (int)date('m');
        foreach ($samples as $s) {
            $records[] = (object)[
                'date'        => sprintf('%04d-%02d-%02d', $year, $month, $s[0]),
                'start_time'  => $s[1],
                'end_time'    => $s[2],
                'branch_name' => $s[3],
                'hours'       => $s[4],
                'extra_hours' => $s[5],
                'is_holiday'  => $s[6],
            ];
        }
        return $records;
    }

    private function render($view, $data = []){
        if (file_exists('../app/views/' . $view . '.php')) {
            require_once '../app/views/' . $view . '.php';
        } else {
            die('La vista no existe');
        }
    }
}
