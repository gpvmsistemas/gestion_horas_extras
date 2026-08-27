<?php

function attendanceStatusMeta($status) {
    $map = [
        'ok'                 => ['label' => 'Correcto',           'badge' => 'success',  'icon' => 'fa-check-circle'],
        'flexible'           => ['label' => 'Control flexible',   'badge' => 'info',     'icon' => 'fa-route'],
        'no_clock'           => ['label' => 'Sin reloj',          'badge' => 'secondary','icon' => 'fa-user-slash'],
        'late'               => ['label' => 'Llegada tarde',      'badge' => 'warning',  'icon' => 'fa-clock'],
        'early_leave'        => ['label' => 'Salida anticipada',  'badge' => 'warning',  'icon' => 'fa-sign-out-alt'],
        'missing_out'        => ['label' => 'Sin salida',         'badge' => 'danger',   'icon' => 'fa-exclamation-triangle'],
        'no_show'            => ['label' => 'Ausente',            'badge' => 'danger',   'icon' => 'fa-user-times'],
        'unplanned_clocking' => ['label' => 'Sin turno planificado','badge' => 'info',    'icon' => 'fa-fingerprint'],
        'incomplete'         => ['label' => 'Marcas incompletas', 'badge' => 'secondary','icon' => 'fa-adjust'],
        'on_leave'           => ['label' => 'Licencia / permiso', 'badge' => 'primary',  'icon' => 'fa-umbrella-beach'],
    ];
    return $map[$status] ?? ['label' => ucfirst($status), 'badge' => 'secondary', 'icon' => 'fa-circle'];
}

function attendanceFormatMinutes($minutes) {
    if ($minutes === null) {
        return '—';
    }
    $sign = $minutes < 0 ? '−' : ($minutes > 0 ? '+' : '');
    $abs = abs((int)$minutes);
    $h = floor($abs / 60);
    $m = $abs % 60;
    if ($h > 0) {
        return $sign . $h . 'h ' . $m . 'm';
    }
    return $sign . $m . 'm';
}

function attendanceFormatTime($timeOrDatetime) {
    if (empty($timeOrDatetime)) {
        return '—';
    }
    if (strlen($timeOrDatetime) <= 8) {
        return substr($timeOrDatetime, 0, 5);
    }
    return date('H:i', strtotime($timeOrDatetime));
}

function attendanceJustificationTypeLabel($type) {
    $labels = AttendanceJustification::typeLabels();
    return $labels[$type] ?? $type;
}

function attendanceJustificationDetailHtml($just) {
    if (!$just) {
        return '';
    }
    $parts = ['<strong>' . htmlspecialchars(attendanceJustificationTypeLabel($just->justification_type)) . '</strong>'];
    if (!empty($just->leave_time)) {
        $parts[] = 'Salida: ' . attendanceFormatTime($just->leave_time);
    }
    if (isset($just->prior_notice)) {
        $parts[] = !empty($just->prior_notice) ? 'Con aviso previo' : 'Sin aviso previo registrado';
    }
    if (!empty($just->notes)) {
        $parts[] = nl2br(htmlspecialchars($just->notes));
    }
    return implode('<br>', $parts);
}

function attendanceJustifyCalendarUrl($userId, $workDate, $attendanceStatus = null) {
    $month = date('Y-m', strtotime($workDate));
    $url = URLROOT . '/admin/calendar?month=' . urlencode($month)
        . '&user_id=' . (int)$userId
        . '&day=' . urlencode($workDate);
    if ($attendanceStatus) {
        $suggest = AttendanceJustification::suggestTypeForStatus($attendanceStatus);
        $url .= '&suggest=' . urlencode($suggest);
    }
    return $url;
}

function attendanceCellClass($day) {
    if (empty($day['in_month'])) {
        return 'cal-cell cal-cell--empty';
    }
    $state = $day['context']['cell_state'] ?? null;
    if ($state) {
        $cls = 'cal-cell';
        if ($state !== 'neutral') {
            $cls .= ' cal-cell--' . preg_replace('/[^a-z_]/', '', $state);
        }
        if (!empty($day['is_today'])) {
            $cls .= ' cal-cell--today';
        }
        return $cls;
    }
    if (!empty($day['justification'])) {
        return 'cal-cell cal-cell--justified' . (!empty($day['is_today']) ? ' cal-cell--today' : '');
    }
    $att = $day['attendance'] ?? null;
    if ($att) {
        return 'cal-cell cal-cell--' . preg_replace('/[^a-z_]/', '', $att->status)
            . (!empty($day['is_today']) ? ' cal-cell--today' : '');
    }
    if (!empty($day['has_planned'])) {
        return 'cal-cell cal-cell--planned-only' . (!empty($day['is_today']) ? ' cal-cell--today' : '');
    }
    if (!empty($day['holiday'])) {
        return 'cal-cell cal-cell--holiday' . (!empty($day['is_today']) ? ' cal-cell--today' : '');
    }
    return 'cal-cell' . (!empty($day['is_today']) ? ' cal-cell--today' : '');
}

function attendanceListStatusBadge($day) {
    if (empty($day['context'])) {
        return '<span class="badge bg-secondary">—</span>';
    }
    $ctx = $day['context'];
    if (!empty($day['holiday'])) {
        return '<span class="badge bg-secondary">Feriado</span>';
    }
    if ($ctx['has_vacation']) {
        return '<span class="badge bg-info text-dark">Vacaciones</span>';
    }
    if ($ctx['has_leave']) {
        return '<span class="badge bg-primary">Licencia</span>';
    }
    if (!empty($day['justification'])) {
        return '<span class="badge bg-purple" style="background:#8b5cf6">Justificado</span>';
    }
    $att = $day['attendance'] ?? null;
    if ($att) {
        return attendanceStatusBadge($att->status);
    }
    if ($ctx['has_shift']) {
        return '<span class="badge bg-light text-dark border">Planificado</span>';
    }
    return '<span class="badge bg-light text-muted border">Sin datos</span>';
}

function attendanceStatusBadge($status) {
    $meta = attendanceStatusMeta($status);
    return '<span class="badge att-badge bg-' . htmlspecialchars($meta['badge']) . '">'
        . '<i class="fas ' . htmlspecialchars($meta['icon']) . ' me-1"></i>'
        . htmlspecialchars($meta['label']) . '</span>';
}

/**
 * Estadísticas del mes para toolbar del calendario admin.
 */
function calendarComputeMonthStats(array $daysList) {
    $stats = [
        'days' => 0,
        'ok' => 0,
        'alerts' => 0,
        'no_show' => 0,
        'justified' => 0,
        'vacation' => 0,
        'leave' => 0,
        'incidents' => 0,
        'holidays' => 0,
    ];
    foreach ($daysList as $day) {
        if (empty($day['in_month'])) {
            continue;
        }
        $stats['days']++;
        if (!empty($day['holiday'])) {
            $stats['holidays']++;
        }
        $ctx = $day['context'] ?? [];
        if (!empty($ctx['has_vacation'])) {
            $stats['vacation']++;
            continue;
        }
        if (!empty($ctx['has_leave'])) {
            $stats['leave']++;
            continue;
        }
        if (!empty($day['incidents'])) {
            $stats['incidents']++;
        }
        if (!empty($day['justification'])) {
            $stats['justified']++;
        }
        $att = $day['attendance'] ?? null;
        if ($att) {
            if ($att->status === 'ok') {
                $stats['ok']++;
            } elseif ($att->status === 'no_show') {
                $stats['no_show']++;
                if (empty($day['justification'])) {
                    $stats['alerts']++;
                }
            } elseif (in_array($att->status, ['late', 'early_leave', 'missing_out', 'incomplete', 'unplanned_clocking'], true)) {
                if (empty($day['justification'])) {
                    $stats['alerts']++;
                }
                if ($att->status === 'late') {
                    $stats['alerts']++;
                }
            }
        }
        // late already counted in alerts branch - fix double count for late
    }
    // Recalculate alerts cleanly
    $stats['alerts'] = 0;
    foreach ($daysList as $day) {
        if (empty($day['in_month'])) {
            continue;
        }
        $ctx = $day['context'] ?? [];
        if (!empty($ctx['has_vacation']) || !empty($ctx['has_leave']) || !empty($day['justification'])) {
            continue;
        }
        $att = $day['attendance'] ?? null;
        if ($att && in_array($att->status, ['late', 'no_show', 'early_leave', 'missing_out', 'incomplete'], true)) {
            $stats['alerts']++;
        }
    }
    return $stats;
}

/** HTML chip compacto para celda del calendario. */
function calendarChipHtml(array $chip) {
    $kind = $chip['kind'] ?? '';
    $label = htmlspecialchars($chip['label'] ?? '');
    $detail = htmlspecialchars($chip['detail'] ?? '');
    $title = $detail !== '' ? ' title="' . $detail . '"' : '';

    switch ($kind) {
        case 'shift':
            $color = htmlspecialchars($chip['color'] ?? '#1d4ed8');
            return '<span class="cal-cell-shift" style="--shift-color:' . $color . '"' . $title . '>' . $label . '</span>';
        case 'holiday':
            return '<span class="cal-cell-tag cal-cell-tag--holiday"' . $title . '>Feriado</span>';
        case 'vacation':
            return '<span class="cal-cell-tag cal-cell-tag--vacation"><i class="fas fa-umbrella-beach me-1"></i>Vac.</span>';
        case 'leave':
            return '<span class="cal-cell-tag cal-cell-tag--leave">Licencia</span>';
        case 'swap':
            return '<span class="cal-cell-tag cal-cell-tag--swap" title="Cambio de turno"><i class="fas fa-exchange-alt"></i></span>';
        case 'incident':
            return '<span class="cal-cell-tag cal-cell-tag--incident" title="' . $detail . '"><i class="fas fa-gavel"></i></span>';
        case 'request':
            return '<span class="cal-cell-tag cal-cell-tag--request" title="' . $label . '"><i class="fas fa-file-alt"></i></span>';
        case 'cp_task':
            return '<span class="cal-cell-tag cal-cell-tag--cp"' . $title . '><i class="fas fa-clipboard-list me-1"></i>' . $label . '</span>';
        default:
            return '';
    }
}

/** ¿Día requiere atención RRHH (lista / filtro alertas)? */
function calendarDayNeedsAttention(array $day) {
    if (empty($day['in_month']) || !empty($day['justification'])) {
        return false;
    }
    $ctx = $day['context'] ?? [];
    if (!empty($ctx['has_vacation']) || !empty($ctx['has_leave'])) {
        return false;
    }
    $att = $day['attendance'] ?? null;
    return $att && in_array($att->status, ['late', 'no_show', 'early_leave', 'missing_out', 'incomplete'], true);
}
