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
