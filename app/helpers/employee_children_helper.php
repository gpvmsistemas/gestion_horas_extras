<?php

/** Etiqueta de edad en español a partir de una fecha de nacimiento. */
function employee_child_age_label($birthDate, $asOf = null) {
    $parts = employee_child_age_parts($birthDate, $asOf);
    if ($parts === null) {
        return '';
    }
    $n = (int)$parts['value'];
    $unit = (string)$parts['unit'];
    if ($unit === 'year') {
        return $n === 1 ? '1 año' : $n . ' años';
    }
    if ($unit === 'month') {
        return $n === 1 ? '1 mes' : $n . ' meses';
    }
    return $n === 1 ? '1 día' : $n . ' días';
}

/** Partes de edad para uso en JS/API. */
function employee_child_age_parts($birthDate, $asOf = null) {
    $birthDate = trim((string)$birthDate);
    if ($birthDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
        return null;
    }
    try {
        $birth = new DateTime($birthDate);
        $asOf = $asOf instanceof DateTimeInterface ? DateTime::createFromInterface($asOf) : new DateTime('today');
        if ($birth > $asOf) {
            return null;
        }
        $diff = $birth->diff($asOf);
        if ($diff->y >= 1) {
            return ['value' => $diff->y, 'unit' => 'year'];
        }
        if ($diff->m >= 1) {
            return ['value' => $diff->m, 'unit' => 'month'];
        }
        $days = max(0, (int)$diff->days);
        return ['value' => $days, 'unit' => 'day'];
    } catch (Throwable $e) {
        return null;
    }
}

function employee_child_birth_display($birthDate) {
    $birthDate = trim((string)$birthDate);
    if ($birthDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
        return '—';
    }
    $ts = strtotime($birthDate);
    return $ts ? date('d/m/Y', $ts) : '—';
}

function employee_child_sex_label($sex) {
    return EmployeeChild::sexLabel($sex);
}
