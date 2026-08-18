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
            $blocksMap = $this->service->getBlocksForUsers(array_column($data['employees'], 'id'), $monday, $sunday);
            $data['schedule'] = $this->buildWeekSchedule($data['employees'], $data['weekDays'], $blocksMap);
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
            $msg .= ' Omitidos por vacaciones/licencia: ' . implode(', ', array_map(function ($d) {
                return date('d/m', strtotime($d));
            }, $blocked)) . '.';
        }
        if ($failed > 0) {
            $_SESSION['flash_error'] = "No se pudieron guardar {$failed} día(s). " . $msg;
        } else {
            $_SESSION['flash_success'] = $msg;
        }
        redirect('registroHoras/horarios?employee_id=' . (int)$employee->id);
    }

    /** Horarios por empleado (equivale a view_hours). */
    public function horarios(){
        $data = $this->orgData();
        $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
        $data['monthValue'] = $month;

        if ($data['realMode']) {
            $selectedId = (int)($_GET['employee_id'] ?? 0);
            $data['selected'] = $this->pickEmployee($data['employees'], $selectedId);
            $first = $month . '-01';
            $last = date('Y-m-t', strtotime($first));
            $blocksMap = $this->service->getBlocksForRange($data['selected']->id, $first, $last);
            $holidays = $this->service->getHolidaysInRange(adminCompanyId(), $first, $last);
            $data['records'] = $this->buildMonthRecords($data['org'], $blocksMap, $holidays);
        } else {
            $data['selected'] = $data['employees'][0];
            $data['records'] = $this->sampleMonthRecords($data['org']);
        }
        $this->render('registro_horas/horarios', $data);
    }

    /** Duplicación de horarios entre semanas (individual o masiva), con verificación previa. */
    public function duplicar(){
        $data = $this->orgData();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $data['realMode']) {
            csrf_verify();
            $action = postString('action');
            $req = [
                'mode'      => postString('mode') === 'masivo' ? 'masivo' : 'individual',
                'employee_id' => (int)($_POST['employee_id'] ?? 0),
                'src_week'  => postString('src_week'),
                'dest_week' => postString('dest_week'),
                'overwrite' => !empty($_POST['overwrite']),
            ];
            $srcMonday = $this->weekMonday($req['src_week']);
            $destMonday = $this->weekMonday($req['dest_week']);
            if ($req['src_week'] === '' || $req['dest_week'] === '' || $srcMonday === $destMonday) {
                $_SESSION['flash_error'] = 'Elegí una semana de origen y una de destino distintas.';
                redirect('registroHoras/duplicar');
            }
            $targets = $req['mode'] === 'masivo'
                ? $data['employees']
                : array_values(array_filter($data['employees'], fn($e) => $e->id === $req['employee_id']));
            if (empty($targets)) {
                $_SESSION['flash_error'] = 'Empleado no válido.';
                redirect('registroHoras/duplicar');
            }

            $preview = $this->buildDuplicatePreview($targets, $srcMonday, $destMonday);
            if ($action === 'execute') {
                $result = $this->executeDuplicate($preview, $req['overwrite']);
                $_SESSION['flash_success'] = "Duplicación completada: {$result['days']} día(s) copiados a {$result['emps']} empleado(s)."
                    . ($result['skipped'] > 0 ? " {$result['skipped']} día(s) omitidos (licencias o sin permiso de sobrescritura)." : '');
                redirect('registroHoras/vistaGeneral?week=' . urlencode($req['dest_week']));
            }
            $data['preview'] = $preview;
            $data['previewReq'] = $req;
        }

        $this->render('registro_horas/duplicar', $data);
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
                'skip_inactive' => !empty($_POST['skip_inactive']),
                'employee_ids' => array_map('intval', (array)($_POST['employee_ids'] ?? [])),
            ];
            $timeOk = preg_match('/^\d{2}:\d{2}$/', $req['start_time']) && preg_match('/^\d{2}:\d{2}$/', $req['end_time']);
            $dateOk = preg_match('/^\d{4}-\d{2}-\d{2}$/', $req['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $req['end_date'])
                && $req['start_date'] <= $req['end_date']
                && count($this->dateRange($req['start_date'], $req['end_date'])) <= 93;
            $targets = array_values(array_filter($data['employees'], fn($e) => in_array($e->id, $req['employee_ids'], true)));
            if (!$timeOk || !$dateOk || empty($targets) || $req['branch_name'] === '') {
                $_SESSION['flash_error'] = 'Revisá sucursal, fechas (máx. 93 días), horario y empleados seleccionados.';
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

    /** Receptor de los formularios en modo maqueta (empresa sin nómina): no persiste nada. */
    public function mockSubmit(){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('registroHoras/carga');
        }
        csrf_verify();
        $back = postString('mock_back');
        $allowed = ['vistaGeneral', 'carga', 'horarios', 'duplicar', 'cargaMasiva'];
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

    /** Empleados activos reales de la empresa activa (null si el esquema no está migrado). */
    private function realEmployees(){
        if ($this->service === null) {
            return null;
        }
        $companyId = function_exists('adminCompanyId') ? (int)adminCompanyId() : 0;
        if ($companyId <= 0) {
            return null;
        }
        $rows = $this->service->getActiveEmployees($companyId);
        if (function_exists('filterStaffRowsByUserArea')) {
            $rows = filterStaffRowsByUserArea($rows);
        }
        $companyName = (string)($_SESSION['user_company_name'] ?? 'Empresa');
        return array_map(function ($u) use ($companyName) {
            // La sucursal del empleado es su área; sin área asignada agrupa bajo la empresa.
            $branch = !empty($u->area_name) ? $u->area_name : ($companyName . ' (sin sucursal)');
            return (object)[
                'id'      => (int)$u->id,
                'name'    => $u->full_name,
                'full_name' => $u->full_name,
                'branch'  => $branch,
                'city'    => $branch,
                'state'   => 'Activo',
                'cajero'  => false,
                'manager' => false,
            ];
        }, $rows);
    }

    private function resolveEmployee($employeeId){
        $u = $this->userModel->getUserById((int)$employeeId);
        if (!$u || $u->role !== 'empleado' || (int)$u->is_active !== 1) {
            return null;
        }
        if ((int)$u->company_id !== (int)adminCompanyId()) {
            return null;
        }
        if (function_exists('supervisorCanAccessUser') && !supervisorCanAccessUser($u)) {
            return null;
        }
        return $u;
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

    private function validateCargaInput($input, $employee){
        $errors = [];
        if (!$employee) {
            $errors[] = 'Empleado no válido para esta empresa.';
        }
        foreach (['start_date', 'end_date'] as $f) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input[$f])) {
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
            if (!preg_match('/^\d{2}:\d{2}$/', $input[$f])) {
                $errors[] = 'Horario inválido.';
            }
        }
        if ($input['start_time'] === $input['end_time']) {
            $errors[] = 'La entrada y la salida no pueden ser iguales.';
        }
        if ($input['branch_name'] === '') {
            $errors[] = 'Elegí una sucursal.';
        }
        if ($input['two_turns']) {
            if (!preg_match('/^\d{2}:\d{2}$/', $input['start_time_2']) || !preg_match('/^\d{2}:\d{2}$/', $input['end_time_2'])) {
                $errors[] = 'Horario del segundo turno inválido.';
            }
        }
        return $errors;
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
    private function buildWeekSchedule($employees, $weekDays, $blocksMap){
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
                $days[$dow] = ['ranges' => $ranges, 'absent' => $absent];
            }
            $schedule[$emp->id] = ['days' => $days];
        }
        return $schedule;
    }

    /** Registros del mes para "Por empleado" a partir de bloques reales. */
    private function buildMonthRecords($org, $blocksMap, $holidays){
        $records = [];
        foreach ($blocksMap as $date => $blocks) {
            $work = array_values(array_filter($blocks, function ($b) {
                return in_array($b->type, RegistroHorasService::WORK_TYPES, true) && $b->start_time && $b->end_time;
            }));
            if (empty($work)) {
                continue;
            }
            $isHoliday = isset($holidays[$date]);
            $day = $this->service->computeDay($org, $date, $work, $isHoliday);
            foreach ($work as $i => $b) {
                $records[] = (object)[
                    'date'        => $date,
                    'start_time'  => substr($b->start_time, 0, 5),
                    'end_time'    => substr($b->end_time, 0, 5),
                    'branch_name' => $b->branch_name ?: 'Sin sucursal',
                    'hours'       => round($this->service->blockMinutes($b->start_time, $b->end_time) / 60, 2),
                    // la extra del día se muestra sobre el último bloque (cálculo acumulativo diario)
                    'extra_hours' => ($i === count($work) - 1) ? $day['extra'] : 0,
                    'is_holiday'  => $isHoliday,
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
     * Vista previa de duplicación: por empleado y día destino, qué pasará.
     * rows: [{employee, dest_date, ranges, status: copy|conflict|blocked|empty, existing}]
     */
    private function buildDuplicatePreview($targets, $srcMonday, $destMonday){
        $srcDays = $this->weekDays($srcMonday);
        $destDays = $this->weekDays($destMonday);
        $companyId = (int)adminCompanyId();
        $holidays = $this->service->getHolidaysInRange($companyId, $destDays[0], end($destDays));

        $rows = [];
        foreach ($targets as $emp) {
            $srcMap = $this->service->getBlocksForRange($emp->id, $srcDays[0], end($srcDays));
            $classified = $this->service->classifyDates($emp->id, $destDays);
            foreach ($srcDays as $i => $srcDate) {
                $work = array_values(array_filter($srcMap[$srcDate] ?? [], function ($b) {
                    return in_array($b->type, RegistroHorasService::WORK_TYPES, true) && $b->start_time && $b->end_time;
                }));
                if (empty($work)) {
                    continue;
                }
                $destDate = $destDays[$i];
                if (in_array($destDate, $classified['blocked'], true)) {
                    $status = 'blocked';
                } elseif (isset($classified['conflict'][$destDate])) {
                    $status = 'conflict';
                } else {
                    $status = 'copy';
                }
                $rows[] = [
                    'employee'   => $emp,
                    'dest_date'  => $destDate,
                    'is_holiday' => isset($holidays[$destDate]),
                    'status'     => $status,
                    'blocks'     => array_map(function ($b) {
                        return [
                            'start'  => substr($b->start_time, 0, 5),
                            'end'    => substr($b->end_time, 0, 5),
                            'branch' => $b->branch_name ?: '',
                            'notes'  => $b->notes ?: '',
                        ];
                    }, $work),
                ];
            }
        }
        return $rows;
    }

    private function executeDuplicate($previewRows, $overwrite){
        $days = 0; $skipped = 0; $emps = [];
        foreach ($previewRows as $row) {
            if ($row['status'] === 'blocked' || ($row['status'] === 'conflict' && !$overwrite)) {
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

    /** Vista previa de carga masiva: por empleado, días con conflicto/bloqueo. */
    private function buildMassivePreview($targets, $req){
        $dates = $this->dateRange($req['start_date'], $req['end_date']);
        $rows = [];
        foreach ($targets as $emp) {
            $classified = $this->service->classifyDates($emp->id, $dates);
            $rows[] = [
                'employee' => $emp,
                'dates'    => $dates,
                'blocked'  => $classified['blocked'],
                'conflict' => array_keys($classified['conflict']),
            ];
        }
        return $rows;
    }

    private function executeMassive($previewRows, $req){
        $days = 0; $skipped = 0; $emps = [];
        foreach ($previewRows as $row) {
            $emp = $row['employee'];
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
                $blocks = [];
                foreach ($this->service->splitRange($date, $req['start_time'], $req['end_time']) as $seg) {
                    // los segmentos del día siguiente por cruce de medianoche se guardan en su fecha
                    $blocks[$seg[0]][] = ['start' => $seg[1], 'end' => $seg[2], 'branch' => $req['branch_name']];
                }
                $ok = true;
                foreach ($blocks as $segDate => $segBlocks) {
                    $ok = $this->service->saveDay($emp->id, $segDate, $segBlocks, $isConflict && $segDate === $date) && $ok;
                }
                if ($ok) {
                    $days++;
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
