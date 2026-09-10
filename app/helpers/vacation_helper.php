<?php

function employment_fields_ready() {
    static $ready = null;
    if ($ready === null) {
        $ready = (new User())->isVacationProfileReady();
    }
    return $ready;
}

function vacation_module_ready() {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $db = new Database();
        $db->query("SHOW TABLES LIKE 'vacation_balance_periods'");
        $ready = (bool)$db->single();
        if ($ready) {
            $db->query("SHOW COLUMNS FROM vacation_balance_periods LIKE 'balance_type'");
            $ready = (bool)$db->single();
        }
        if ($ready) {
            $db->query("SHOW COLUMNS FROM vacation_balance_movements LIKE 'operation_key'");
            $ready = (bool)$db->single();
        }
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

function vacation_day_count_modes() {
    return [
        'weekdays' => 'Días hábiles (lun-vie)',
        'calendar' => 'Días corridos',
        'business_mon_sat' => 'Días hábiles (lun-sáb, sin feriados)',
    ];
}

function agreement_leave_types_ready() {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $db = new Database();
        $db->query("SHOW TABLES LIKE 'collective_agreement_leave_types'");
        $ready = (bool)$db->single();
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

function agreement_leave_categories() {
    return [
        'medical' => 'Médica / salud',
        'family' => 'Familiar',
        'maternity' => 'Maternidad',
        'paternity' => 'Paternidad',
        'study' => 'Estudio / examen',
        'gremial' => 'Gremial / sindical',
        'special' => 'Especial convencional',
        'other' => 'Otra',
    ];
}

function agreement_leave_type_supports_requires_approval() {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $db = new Database();
        $db->query("SHOW COLUMNS FROM collective_agreement_leave_types LIKE 'requires_approval'");
        $ready = (bool)$db->single();
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

/**
 * Si false, la licencia se registra al enviar (ej. enfermedad) sin aprobación de RRHH.
 */
function agreement_leave_type_requires_approval($leaveType) {
    if (!$leaveType) {
        return true;
    }
    if (agreement_leave_type_supports_requires_approval()) {
        if (isset($leaveType->requires_approval)) {
            return !empty($leaveType->requires_approval);
        }
        if (isset($leaveType->agreement_leave_requires_approval)) {
            return !empty($leaveType->agreement_leave_requires_approval);
        }
    }
    $code = strtoupper(trim((string)($leaveType->code ?? $leaveType->agreement_leave_code ?? '')));
    return $code !== 'ENFERMEDAD';
}

function employee_request_status_label($request) {
    if (!$request) {
        return '';
    }
    if (($request->status ?? '') === 'Aprobado' && !agreement_leave_type_requires_approval($request)) {
        return 'Registrado';
    }
    return (string)($request->status ?? '');
}

function agreement_leave_type_badge($leaveType) {
    if (!$leaveType) {
        return '';
    }
    $parts = [];
    if (!agreement_leave_type_requires_approval($leaveType)) {
        $parts[] = 'Solo aviso';
    }
    if (!empty($leaveType->is_paid)) {
        $parts[] = 'Con goce';
    } else {
        $parts[] = 'Sin goce';
    }
    if (!empty($leaveType->requires_certificate)) {
        $parts[] = 'Certificado';
    }
    if ($leaveType->max_days_per_event !== null && $leaveType->max_days_per_event !== '') {
        $parts[] = 'Máx. ' . vacation_format_days((float)$leaveType->max_days_per_event) . ' por evento';
    }
    return implode(' · ', $parts);
}

function vacation_dates_in_range($startDate, $endDate, $mode, $companyId = 0, $db = null, $branchId = 0) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate ?: $startDate);
    if ($end < $start) {
        return [];
    }
    $holidays = [];
    if ($mode === 'business_mon_sat' && (int)$companyId > 0) {
        foreach ((new Holiday($db))->getHolidaysForPeriod((int)$companyId, $start->format('Y-m-d'), $end->format('Y-m-d'), (int)$branchId) as $holiday) {
            $holidays[$holiday->holiday_date] = true;
        }
    }
    $dates = [];
    while ($start <= $end) {
        $date = $start->format('Y-m-d');
        $weekday = (int)$start->format('N');
        if ($mode === 'calendar'
            || ($mode === 'weekdays' && $weekday < 6)
            || ($mode === 'business_mon_sat' && $weekday <= 6 && !isset($holidays[$date]))) {
            $dates[] = $date;
        }
        $start->modify('+1 day');
    }
    return $dates;
}

function vacation_count_days_in_range($startDate, $endDate, $mode, $companyId = 0, $db = null, $branchId = 0) {
    return count(vacation_dates_in_range($startDate, $endDate, $mode, $companyId, $db, $branchId));
}

function vacation_schedule_entry($notes = '') {
    return ['type'=>'vacation','shift_id'=>null,'start_time'=>null,'end_time'=>null,
        'notes'=>$notes !== '' ? $notes : 'Vacaciones'];
}

function vacation_leave_schedule_entry($notes = '') {
    return ['type'=>'leave','shift_id'=>null,'start_time'=>null,'end_time'=>null,
        'notes'=>$notes !== '' ? $notes : 'Licencia'];
}

function vacation_planner_valid_types() {
    return ['shift','custom','overtime','vacation','leave'];
}

function vacation_period_label_from_start($periodStartDate) {
    $y = (int)date('Y', strtotime($periodStartDate));
    return date('m-d', strtotime($periodStartDate)) === '01-01' ? (string)$y : $y . '-' . ($y + 1);
}

function vacation_period_bounds($year, $startMonth = 1, $startDay = 1) {
    $startMonth = max(1, min(12, (int)$startMonth));
    $startDay = max(1, min(28, (int)$startDay));
    $periodStart = sprintf('%04d-%02d-%02d', (int)$year, $startMonth, $startDay);
    return [
        'period_start'=>$periodStart,
        'period_end'=>date('Y-m-d', strtotime($periodStart . ' +1 year -1 day')),
        'period_label'=>vacation_period_label_from_start($periodStart),
    ];
}

function vacation_period_for_date($referenceDate, $startMonth = 1, $startDay = 1) {
    $ref = new DateTime($referenceDate);
    $bounds = vacation_period_bounds((int)$ref->format('Y'), $startMonth, $startDay);
    if ($ref < new DateTime($bounds['period_start'])) {
        $bounds = vacation_period_bounds((int)$ref->format('Y') - 1, $startMonth, $startDay);
    }
    return $bounds;
}

/** Año de inicio a partir de un label `2027` o `2026-2027`. */
function vacation_period_year_from_label($periodLabel) {
    $label = trim((string)$periodLabel);
    if (preg_match('/^(\d{4})(?:-\d{4})?$/', $label, $m)) {
        return (int)$m[1];
    }
    return 0;
}

/**
 * Período operativo por defecto: desde octubre se sugiere el año siguiente
 * (abrir liquidación anticipada); el resto del año, el calendario vigente.
 */
function vacation_default_target_period_label($referenceDate = null) {
    $ts = $referenceDate ? strtotime($referenceDate) : time();
    if ($ts === false) {
        $ts = time();
    }
    // El label es el AÑO DE DEVENGO (antigüedad al 31/12 de ese año; se goza
    // al año siguiente, LCT 150/154). Aunque desde octubre ya se planifica el
    // goce, el período a liquidar sigue siendo el del año en curso: abrir el
    // año siguiente crearía saldo consumible hoy con antigüedad a fecha futura.
    return (string)date('Y', $ts);
}

/** Lista de labels de período para el hub (Y-2 … Y+1). */
function vacation_target_period_options($referenceDate = null) {
    $ts = $referenceDate ? strtotime($referenceDate) : time();
    if ($ts === false) {
        $ts = time();
    }
    $year = (int)date('Y', $ts);
    $opts = [];
    for ($y = $year - 2; $y <= $year + 1; $y++) {
        $opts[] = (string)$y;
    }
    return $opts;
}

function vacation_count_vacation_days_in_entries(array $entries) {
    $n = 0;
    foreach ($entries as $entry) {
        $type = is_array($entry) ? ($entry['type'] ?? '') : ($entry->type ?? '');
        if ($type === 'vacation') {
            $n++;
        }
    }
    return $n;
}

function vacation_format_days($days) {
    $d = (float)$days;
    return fmod($d, 1.0) === 0.0 ? (string)(int)$d : number_format($d, 1, ',', '.');
}

function vacation_period_pending($period) {
    $period = is_array($period) ? (object)$period : $period;
    if (isset($period->days_pending) && $period->days_pending !== '') {
        return (float)$period->days_pending;
    }
    return max(0, (float)($period->days_entitled ?? 0)
        + (float)($period->adjustment_days ?? 0)
        - (float)($period->days_taken ?? 0));
}

function vacation_balance_type_labels() {
    return [
        'annual' => 'Ordinario',
        'historical' => 'Histórico',
        'conventional_credit' => 'Crédito convencional',
    ];
}

function vacation_is_vacation_request($request) {
    if (!$request) {
        return false;
    }
    $name = mb_strtolower((string)($request->type_name ?? $request->name ?? ''), 'UTF-8');
    return (bool)preg_match('/vacaci[oó]n/u', $name);
}

function vacation_planilla_staff_url($userId, $requestId = 0, $asPdf = false) {
    $url = rtrim((string)URLROOT, '/') . '/admin/vacationPlanilla/' . (int)$userId;
    $query = [];
    if ((int)$requestId > 0) {
        $query['request_id'] = (int)$requestId;
    }
    if ($asPdf) {
        $query['format'] = 'pdf';
    }
    return $query ? $url . '?' . http_build_query($query) : $url;
}

function vacation_planilla_employee_url($requestId = 0, $asPdf = false) {
    $url = rtrim((string)URLROOT, '/') . '/request/vacationPlanilla';
    if ((int)$requestId > 0) {
        $url .= '/' . (int)$requestId;
    }
    return $asPdf ? $url . '?format=pdf' : $url;
}

function vacation_planilla_payload($userId, $request = null) {
    $userId = (int)$userId;
    $user = (new User())->getUserById($userId);
    if (!$user) {
        return null;
    }
    $entitlement = new VacationEntitlementService();
    $summary = $entitlement->getSummaryForUser($userId);
    $companyId = (int)($user->company_id ?? 0);
    $companyName = $companyId > 0 ? (new Company())->getNameById($companyId) : '';
    $areaName = '';
    if (!empty($user->area_id)) {
        $area = (new Area())->getById((int)$user->area_id);
        $areaName = $area ? (string)($area->name ?? '') : '';
    }
    $agreement = $summary['agreement'] ?? null;
    $rule = $summary['rule'] ?? null;
    $modes = vacation_day_count_modes();
    $modeKey = $rule->day_count_mode ?? 'calendar';
    $goce = null;
    if ($request && vacation_is_vacation_request($request)) {
        $endDate = $request->end_date ?: $request->start_date;
        $counted = $request->vacation_counted_days ?? null;
        if ($counted === null || $counted === '') {
            $counted = $entitlement->countDaysForUserRange($userId, $request->start_date, $endDate);
        }
        $snapshot = [];
        if (!empty($request->vacation_rule_snapshot)) {
            $decoded = json_decode($request->vacation_rule_snapshot, true);
            if (is_array($decoded)) {
                $snapshot = $decoded;
            }
        }
        $goce = [
            'request_id' => (int)$request->id,
            'start_date' => $request->start_date,
            'end_date' => $endDate,
            'days' => (float)$counted,
            'status' => (string)($request->status ?? ''),
            'reason' => (string)($request->reason ?? ''),
            'allocations' => $snapshot['allocations'] ?? [],
            'day_count_mode' => $snapshot['agreement_snapshot']['day_count_mode'] ?? $modeKey,
        ];
        if (!empty($goce['day_count_mode'])) {
            $modeKey = $goce['day_count_mode'];
        }
    }
    return [
        'user' => $user,
        'company_id' => $companyId,
        'company_name' => $companyName ?: (function_exists('app_name') ? app_name() : 'RRHH'),
        'area_name' => $areaName,
        'agreement' => $agreement,
        'rule' => $rule,
        'summary' => $summary,
        'goce' => $goce,
        'kind' => $goce ? 'goce' : 'liquidacion',
        'mode_label' => $modes[$modeKey] ?? $modeKey,
        'type_labels' => vacation_balance_type_labels(),
        'printed_at' => date('d/m/Y H:i'),
        'logo_url' => function_exists('company_brand_logo_url') ? company_brand_logo_url($companyId) : '',
        'brand_color' => function_exists('company_brand_color') ? company_brand_color($companyId) : '#222222',
        'is_staff' => function_exists('isStaffAdmin') && isStaffAdmin(),
    ];
}

function vacation_planilla_render($userId, $request = null, $asPdf = false) {
    $payload = vacation_planilla_payload($userId, $request);
    if (!$payload) {
        return false;
    }
    if ($asPdf) {
        vacation_planilla_send_pdf($payload);
        return true;
    }
    $data = $payload;
    require APPROOT . '/views/vacation/planilla.php';
    return true;
}

function vacation_planilla_send_pdf(array $payload) {
    $user = $payload['user'];
    $goce = $payload['goce'];
    $agreement = $payload['agreement'];
    $title = $goce ? 'Comunicacion de vacaciones' : 'Planilla de vacaciones — liquidacion / saldo';
    $slug = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$user->full_name);
    $filename = ($goce ? 'comunicacion_vacaciones_' : 'planilla_vacaciones_') . $slug . '.pdf';
    $lines = [
        $title,
        'Empresa: ' . $payload['company_name'],
        'Empleado: ' . $user->full_name,
        'Documento: ' . ($user->document_number ?: '—') . '  CUIL: ' . ($user->cuil ?: '—'),
        'Area: ' . ($payload['area_name'] !== '' ? $payload['area_name'] : '—'),
        'Ingreso formal: ' . (!empty($user->hire_date) ? date('d/m/Y', strtotime($user->hire_date)) : '—'),
        'Convenio: ' . ($agreement ? $agreement->code . ' — ' . $agreement->name : 'Sin convenio efectivo'),
        'Unidad de computo: ' . $payload['mode_label'],
        'Saldo pendiente total: ' . vacation_format_days($payload['summary']['total_pending'] ?? 0) . ' dias',
        str_repeat('-', 110),
        'Periodo | Tipo | Corresponden | Tomados | Pendientes',
        str_repeat('-', 110),
    ];
    $periods = $payload['summary']['periods'] ?? [];
    if (!$periods) {
        $lines[] = 'Sin periodos liquidados.';
    }
    foreach ($periods as $period) {
        $type = $payload['type_labels'][$period->balance_type ?? 'annual'] ?? ($period->balance_type ?? 'annual');
        $lines[] = $period->period_label . ' | ' . $type . ' | '
            . vacation_format_days($period->days_entitled) . ' | '
            . vacation_format_days($period->days_taken) . ' | '
            . vacation_format_days(vacation_period_pending($period));
    }
    if ($goce) {
        $lines[] = str_repeat('-', 110);
        $lines[] = 'Goce: ' . date('d/m/Y', strtotime($goce['start_date']))
            . ' al ' . date('d/m/Y', strtotime($goce['end_date']))
            . ' — ' . vacation_format_days($goce['days']) . ' dia(s) computables'
            . ' — estado ' . $goce['status'];
        if (!empty($goce['allocations'])) {
            $parts = [];
            foreach ($goce['allocations'] as $alloc) {
                $parts[] = ($alloc['period_label'] ?? '') . ': ' . vacation_format_days($alloc['days'] ?? 0);
            }
            $lines[] = 'Imputacion FIFO: ' . implode(' · ', $parts);
        }
    } else {
        $lines[] = str_repeat('-', 110);
        $lines[] = 'Periodo de goce a completar: desde ________ / ________ / ________  hasta ________ / ________ / ________';
        $lines[] = 'Dias computables: ________';
    }
    $lines[] = str_repeat('-', 110);
    $lines[] = 'El presente es informativo de dias de vacaciones. No liquida haberes ni sustituye el recibo de sueldo.';
    $lines[] = 'Firma del empleado: ____________________    Firma y sello RRHH: ____________________';
    $lines[] = 'Aclaracion / DNI: ____________________    Fecha: ' . date('d/m/Y');
    $lines[] = 'Emitido: ' . $payload['printed_at'];
    $pdf = (new SimplePdfService())->build($lines);
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $safeName . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, no-store');
    echo $pdf;
    exit;
}
