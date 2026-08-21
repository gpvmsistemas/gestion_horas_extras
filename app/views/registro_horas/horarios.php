<?php require APPROOT . '/views/inc/header.php'; ?>

<?php $_rhActive = 'horarios'; require APPROOT . '/views/registro_horas/partials/nav_tabs.php'; ?>
<?php require APPROOT . '/views/registro_horas/partials/mock_banner.php'; ?>

<?php
// ── Preparación de datos: agrupar por sucursal → fecha, como view_hours de hoursapp ──
$weekdaysEs = [1=>'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
$byBranch = [];
$workedDays = [];
$holidayDays = [];
$totHours = 0; $totExtras = 0;
$totKinds = []; // origen de las extras del período (feriado/umbral/overtime)
foreach ($data['records'] as $r) {
    $byBranch[$r->branch_name][$r->date][] = $r;
    $d = (int)date('j', strtotime($r->date));
    $workedDays[$d] = true;
    if ($r->is_holiday) $holidayDays[$d] = true;
    $totHours += $r->hours;
    $totExtras += $r->extra_hours;
    if ($r->extra_hours > 0 && ($r->extra_kind ?? '') !== '') {
        $totKinds[$r->extra_kind] = ($totKinds[$r->extra_kind] ?? 0) + $r->extra_hours;
    }
}
// Desglose humano del origen de las extras ("Feriado: 8 h · Umbral: 1.5 h").
$_kindLabels = ['feriado' => 'Feriado', 'umbral' => 'Excedente del umbral diario', 'overtime' => 'Bloques de horas extra'];
$_extraBreakdown = function (array $kinds) use ($_kindLabels) {
    $parts = [];
    foreach ($_kindLabels as $k => $label) {
        if (!empty($kinds[$k])) {
            $parts[] = $label . ': ' . rh_fmt_horas($kinds[$k]) . ' h';
        }
    }
    return implode(' · ', $parts);
};
$filters = $data['filters'] ?? ['month' => date('Y-m'), 'from' => '', 'to' => '', 'city' => '', 'branch' => '', 'range_mode' => false];
// null es deliberado: rango libre que abarca más de un mes ⇒ sin calendario.
$monthValue = array_key_exists('monthValue', $data) ? $data['monthValue'] : date('Y-m');
$mesesEs = [1=>'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
// Días del mes bloqueados por estado declarado (Guardia/Vacaciones/Licencia):
// en el calendario tienen prioridad sobre feriado y trabajo, como en hoursapp.
$statusDays = [];
if ($monthValue !== null) {
    $mFirst = $monthValue . '-01';
    $mLast = date('Y-m-t', strtotime($mFirst));
    foreach (($data['statusPeriods'] ?? []) as $p) {
        $cursor = max($p->start_date, $mFirst);
        $stop = min($p->end_date, $mLast);
        while ($cursor <= $stop) {
            $statusDays[(int)date('j', strtotime($cursor))] = RegistroHorasService::statusLabel($p->status);
            $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
        }
    }
}
// D6: precisión fraccional visible — 8 se muestra "8", 7.5 se muestra "7.5".
if (!function_exists('rh_fmt_horas')) {
    function rh_fmt_horas($v) {
        $s = rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
        return $s === '' ? '0' : $s;
    }
}
$monthLabel = $monthValue !== null
    ? $mesesEs[(int)substr($monthValue, 5, 2)] . ' ' . (int)substr($monthValue, 0, 4)
    : date('d/m/Y', strtotime($data['rangeStart'] ?? 'today')) . ' – ' . date('d/m/Y', strtotime($data['rangeEnd'] ?? 'today'));
$hasPlaceFilter = ($filters['city'] !== '' || $filters['branch'] !== '');
// Tooltip rico del calendario (port de showTooltip de view_hours.js):
// fecha → sucursales → rangos (+extras) + feriado; estados por día bloqueado.
$dayTips = [];
foreach ($data['records'] as $r) {
    $dayTips[$r->date]['branches'][$r->branch_name][] = $r->start_time . ' – ' . $r->end_time
        . ($r->extra_hours > 0 ? ' (+' . rh_fmt_horas($r->extra_hours) . ' extra)' : '');
    if ($r->is_holiday && !empty($r->holiday_name ?? '')) {
        $dayTips[$r->date]['holiday'] = $r->holiday_name;
    }
}
$statusTips = [];
if ($monthValue !== null) {
    foreach ($statusDays as $dNum => $label) {
        $statusTips[sprintf('%s-%02d', $monthValue, $dNum)] = $label;
    }
}
?>

<section class="admin-surface">
    <div class="admin-surface-body">
        <?php if ($data['realMode']): ?>
        <form action="<?php echo URLROOT; ?>/registroHoras/horarios" method="get" class="row g-2 align-items-end">
        <?php else: ?>
        <form action="<?php echo URLROOT; ?>/registroHoras/mockSubmit" method="post" class="row g-2 align-items-end">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="mock_back" value="horarios">
        <?php endif; ?>
            <div class="col-lg-3 col-md-6">
                <label class="form-label mb-1">Empleado</label>
                <select name="employee_id" class="form-select form-select-sm">
                    <?php foreach ($data['employees'] as $emp): ?>
                    <option value="<?php echo (int)$emp->id; ?>" <?php echo $emp->id === $data['selected']->id ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($emp->name); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label mb-1">Mes</label>
                <input type="month" name="month" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filters['month']); ?>">
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label mb-1">Rango libre <span class="text-muted fw-normal">(pisa al mes)</span></label>
                <div class="input-group input-group-sm">
                    <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($filters['from']); ?>" aria-label="Desde">
                    <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($filters['to']); ?>" aria-label="Hasta">
                    <?php if ($filters['range_mode']): ?>
                    <a class="btn btn-outline-secondary" title="Limpiar rango"
                       href="<?php echo URLROOT; ?>/registroHoras/horarios?employee_id=<?php echo (int)$data['selected']->id; ?>&month=<?php echo htmlspecialchars($filters['month']); ?>"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label mb-1">Ciudad / Sucursal</label>
                <div class="input-group input-group-sm">
                    <select name="city" id="rhFilterCity" class="form-select" aria-label="Ciudad">
                        <option value="">Todas</option>
                        <?php foreach (array_keys($data['branchesByCity']) as $cityName): ?>
                        <option value="<?php echo htmlspecialchars($cityName); ?>" <?php echo $filters['city'] === $cityName ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cityName); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="branch" id="rhFilterBranch" class="form-select" aria-label="Sucursal">
                        <option value="">Todas</option>
                        <?php foreach ($data['branchesByCity'] as $cityName => $branches): ?>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?php echo htmlspecialchars($b); ?>" data-city="<?php echo htmlspecialchars($cityName); ?>" <?php echo $filters['branch'] === $b ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b); ?>
                            </option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-lg-1 col-md-6">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-search"></i></button>
            </div>
        </form>
    </div>
</section>

<?php if ($data['realMode'] && empty($data['records'])): ?>
<section class="admin-surface">
    <div class="admin-surface-body text-center text-muted py-5">
        <i class="far fa-calendar-times fs-3 d-block mb-2"></i>
        <?php echo htmlspecialchars($data['selected']->name); ?> no tiene horarios en <?php echo htmlspecialchars($monthLabel); ?><?php echo $hasPlaceFilter ? ' con los filtros elegidos' : ''; ?>.
        <div class="mt-2 d-flex gap-2 justify-content-center">
            <a href="<?php echo URLROOT; ?>/registroHoras/carga" class="btn btn-sm btn-primary"><i class="fas fa-plus-circle me-1"></i>Cargar horario</a>
            <?php if ($hasPlaceFilter): ?>
            <a href="<?php echo URLROOT; ?>/registroHoras/horarios?employee_id=<?php echo (int)$data['selected']->id; ?>&month=<?php echo htmlspecialchars($filters['month']); ?>" class="btn btn-sm btn-outline-secondary">Quitar filtros</a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <section class="admin-surface h-100">
            <div class="admin-surface-head">
                <div>
                    <h3 class="admin-surface-title"><i class="fas fa-calendar"></i><?php echo $monthValue !== null ? 'Calendario — ' : 'Período — '; ?><?php echo htmlspecialchars($monthLabel); ?></h3>
                    <p class="admin-surface-subtitle">
                        <?php echo htmlspecialchars($data['selected']->name); ?>
                        <?php if ($hasPlaceFilter): ?> · filtro: <?php echo htmlspecialchars($filters['branch'] !== '' ? $filters['branch'] : $filters['city']); ?><?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="admin-surface-body">
                <?php if ($monthValue !== null):
                    $year = (int)substr($monthValue, 0, 4); $month = (int)substr($monthValue, 5, 2);
                    $firstOfMonth = sprintf('%04d-%02d-01', $year, $month);
                    $daysInMonth = (int)date('t', strtotime($firstOfMonth));
                    $firstDow = (int)date('N', strtotime($firstOfMonth));
                    $today = ($monthValue === date('Y-m')) ? (int)date('j') : 0;
                ?>
                <div class="rh-cal">
                    <?php foreach (['Lu','Ma','Mi','Ju','Vi','Sá','Do'] as $dh): ?>
                    <div class="rh-cal-head"><?php echo $dh; ?></div>
                    <?php endforeach; ?>
                    <?php for ($i = 1; $i < $firstDow; $i++): ?>
                    <div class="rh-cal-day is-empty"></div>
                    <?php endfor; ?>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++):
                        $classes = [];
                        $cellIso = sprintf('%s-%02d', $monthValue, $d);
                        if (isset($statusDays[$d])) {
                            $classes[] = 'is-status';
                        } elseif (isset($holidayDays[$d])) {
                            $classes[] = 'is-holiday';
                        } elseif (isset($workedDays[$d])) {
                            $classes[] = 'is-worked';
                        }
                        if ($d === $today) $classes[] = 'is-today';
                        $dayHours = 0;
                        foreach ($data['records'] as $r) {
                            if ((int)date('j', strtotime($r->date)) === $d) $dayHours += $r->hours;
                        }
                    ?>
                    <div class="rh-cal-day <?php echo implode(' ', $classes); ?>" data-cal-date="<?php echo $cellIso; ?>"
                         <?php echo isset($statusDays[$d]) ? 'aria-label="' . htmlspecialchars($statusDays[$d] . ' — este día no se pueden registrar horas') . '"' : ''; ?>>
                        <?php echo $d; ?>
                        <?php if (isset($statusDays[$d])): ?><span class="rh-cal-hrs"><?php echo htmlspecialchars($statusDays[$d]); ?></span>
                        <?php elseif ($dayHours > 0): ?><span class="rh-cal-hrs"><?php echo rh_fmt_horas($dayHours); ?> hs</span><?php endif; ?>
                    </div>
                    <?php endfor; ?>
                </div>
                <div class="rh-legend text-muted">
                    <span><i class="fas fa-circle" style="color:var(--clr-primary)"></i>Trabajado</span>
                    <span><i class="fas fa-circle" style="color:var(--clr-danger)"></i>Feriado</span>
                    <span><i class="fas fa-circle" style="color:#9ca3af"></i>Estado (bloqueado)</span>
                    <span><i class="fas fa-circle" style="color:var(--clr-warning)"></i>Hoy</span>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="admin-kpi p-3 border rounded">
                                <div class="fs-4 fw-bold"><?php echo count(array_unique(array_map(fn($r) => $r->date, $data['records']))); ?></div>
                                <div class="small text-muted">días con horario</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="admin-kpi p-3 border rounded">
                                <div class="fs-4 fw-bold"><?php echo rh_fmt_horas($totHours); ?></div>
                                <div class="small text-muted">horas del período</div>
                            </div>
                        </div>
                    </div>
                    <div class="form-text mt-3">El calendario se muestra cuando el rango cae dentro de un solo mes.</div>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="col-lg-7">
        <section class="admin-surface h-100">
            <div class="admin-surface-head">
                <div>
                    <h3 class="admin-surface-title"><i class="fas fa-store"></i>Registros por sucursal</h3>
                    <p class="admin-surface-subtitle">Detalle por fecha, con cada rango horario del día.</p>
                </div>
                <?php if ($data['realMode'] && !empty($data['records'])): ?>
                <div class="admin-surface-actions d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="rhToggleSelect">
                        <i class="fas fa-check-square me-1"></i>Seleccionar
                    </button>
                    <button type="button" class="btn btn-sm btn-danger d-none" id="rhDeleteSelected">
                        <i class="fas fa-trash me-1"></i>Eliminar (<span id="rhSelCount">0</span>)
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <div class="admin-surface-body d-flex flex-column gap-4" id="rhDayCards">
                <?php foreach ($byBranch as $branch => $dates): ?>
                <div>
                    <h6 class="fw-bold mb-3"><i class="fas fa-map-marker-alt me-1 text-muted"></i><?php echo htmlspecialchars($branch); ?></h6>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($dates as $date => $dayRecords):
                            $dowN = (int)date('N', strtotime($date));
                            $isHoliday = false; $dayTotal = 0; $dayExtras = 0; $dayKinds = [];
                            foreach ($dayRecords as $r) {
                                if ($r->is_holiday) $isHoliday = true;
                                $dayTotal += $r->hours;
                                $dayExtras += $r->extra_hours;
                                if ($r->extra_hours > 0 && ($r->extra_kind ?? '') !== '') {
                                    $dayKinds[$r->extra_kind] = ($dayKinds[$r->extra_kind] ?? 0) + $r->extra_hours;
                                }
                            }
                        ?>
                        <div class="rh-day-card <?php echo $isHoliday ? 'is-holiday' : ''; ?>" data-date="<?php echo htmlspecialchars($date); ?>">
                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                                <div class="fw-semibold">
                                    <?php echo date('d/m/Y', strtotime($date)); ?> — <?php echo $weekdaysEs[$dowN]; ?>
                                    <?php if ($isHoliday): ?><span class="badge bg-danger ms-1"><i class="fas fa-star me-1"></i>Feriado</span><?php endif; ?>
                                    <?php if ($dowN >= 6): ?><span class="badge bg-secondary ms-1">Fin de semana</span><?php endif; ?>
                                </div>
                                <div class="fw-bold">
                                    Total: <?php echo rh_fmt_horas($dayTotal); ?> hs
                                    <?php if ($dayExtras > 0): $_bd = $_extraBreakdown($dayKinds); ?>
                                    <span class="text-warning ms-1" <?php echo $_bd !== '' ? 'title="Origen de las extras del día — ' . htmlspecialchars($_bd) . '" data-rh-variant="warning"' : ''; ?>>(+<?php echo rh_fmt_horas($dayExtras); ?> extra)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($dayRecords as $r): $rid = (int)($r->id ?? 0); ?>
                                <div class="rh-range-row">
                                    <span>
                                        <?php if ($data['realMode'] && $rid > 0): ?>
                                        <input type="checkbox" class="form-check-input rh-row-check me-2" value="<?php echo $rid; ?>" aria-label="Seleccionar bloque">
                                        <?php endif; ?>
                                        <i class="far fa-clock me-2 text-muted"></i><?php echo $r->start_time; ?> – <?php echo $r->end_time; ?>
                                        <?php if ($r->extra_hours > 0): $_rz = (string)($r->extra_reason ?? ''); ?>
                                        <span class="badge bg-warning text-dark ms-2"
                                              <?php echo $_rz !== '' ? 'title="' . htmlspecialchars($_rz) . '" data-rh-variant="' . (($r->extra_kind ?? '') === 'feriado' ? 'danger' : 'warning') . '"' : ''; ?>>+<?php echo rh_fmt_horas($r->extra_hours); ?> extra</span>
                                        <?php endif; ?>
                                        <?php if (!empty($r->nocturnal_legacy)): ?>
                                        <i class="fas fa-moon text-primary ms-2" title="Turno nocturno heredado sin dividir: editar o borrar afecta el turno completo (ambos días)."></i>
                                        <?php endif; ?>
                                        <?php if (($r->type ?? '') === 'shift'): ?>
                                        <span class="badge bg-light text-dark border ms-2" title="Proviene del planificador semanal; al editarlo pasa a ser un horario propio de este módulo.">Planificador</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="text-nowrap">
                                        <?php if ($data['realMode'] && $rid > 0): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary rh-edit-btn"
                                                title="Editar bloque"
                                                data-id="<?php echo $rid; ?>"
                                                data-date="<?php echo htmlspecialchars((string)($r->orig_date ?? $r->date)); ?>"
                                                data-start="<?php echo htmlspecialchars((string)($r->orig_start ?? $r->start_time)); ?>"
                                                data-end="<?php echo htmlspecialchars((string)($r->orig_end ?? $r->end_time)); ?>"
                                                data-branch="<?php echo htmlspecialchars((string)($r->branch_raw ?? $r->branch_name)); ?>"
                                                data-notes="<?php echo htmlspecialchars((string)($r->notes ?? '')); ?>"
                                                data-nocturnal="<?php echo !empty($r->nocturnal_legacy) ? '1' : '0'; ?>"><i class="fas fa-pen"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-danger rh-del-btn"
                                                title="Eliminar bloque"
                                                data-id="<?php echo $rid; ?>"
                                                data-label="<?php echo htmlspecialchars(date('d/m', strtotime($r->date)) . ' ' . $r->start_time . '–' . $r->end_time . ' · ' . $r->branch_name); ?>"><i class="fas fa-trash"></i></button>
                                        <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Disponible con nómina real"><i class="fas fa-pen"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" disabled title="Disponible con nómina real"><i class="fas fa-trash"></i></button>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</div>

<section class="admin-surface">
    <div class="admin-surface-body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="fs-6 fw-bold">
            Total de horas trabajadas del período: <?php echo rh_fmt_horas($totHours); ?> hs
        </div>
        <div class="text-muted">
            <?php $_bdTot = $_extraBreakdown($totKinds); ?>
            Extras: <span class="fw-semibold text-warning" <?php echo $_bdTot !== '' ? 'title="Origen de las extras del período — ' . htmlspecialchars($_bdTot) . '" data-rh-variant="warning"' : ''; ?>><?php echo rh_fmt_horas($totExtras); ?> hs</span>
            <?php echo $data['org'] === 'moderna' ? '(umbral diario 8/5 + feriados)' : '(clasificación 50/100 de la suite)'; ?>
            <?php if ($hasPlaceFilter): ?><span class="ms-1">· solo <?php echo htmlspecialchars($filters['branch'] !== '' ? $filters['branch'] : $filters['city']); ?></span><?php endif; ?>
        </div>
    </div>
</section>

<?php if ($data['realMode']): ?>
<!-- Modal de edición de bloque -->
<div class="modal fade" id="rhEditModal" tabindex="-1" aria-labelledby="rhEditModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" action="<?php echo URLROOT; ?>/registroHoras/editarBloque" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="block_id" id="rhEditId">
            <?php foreach (['month','from','to','city','branch'] as $_bf): ?>
            <input type="hidden" name="back_<?php echo $_bf; ?>" value="<?php echo htmlspecialchars((string)$filters[$_bf]); ?>">
            <?php endforeach; ?>
            <div class="modal-header">
                <h5 class="modal-title" id="rhEditModalTitle"><i class="fas fa-pen me-2"></i>Editar bloque de horario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-12 mb-3">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="schedule_date" id="rhEditDate" class="form-control" required>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">Entrada</label>
                        <input type="time" name="start_time" id="rhEditStart" class="form-control" required>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">Salida</label>
                        <input type="time" name="end_time" id="rhEditEnd" class="form-control" required>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Sucursal</label>
                        <select name="branch_name" id="rhEditBranch" class="form-select" required>
                            <option value="" disabled>Elegir…</option>
                            <?php foreach ($data['branchesByCity'] as $group => $branches): ?>
                            <optgroup label="<?php echo htmlspecialchars($group); ?>">
                                <?php foreach ($branches as $b): ?>
                                <option value="<?php echo htmlspecialchars($b); ?>"><?php echo htmlspecialchars($b); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notas (opcional)</label>
                        <input type="text" name="notes" id="rhEditNotes" class="form-control" maxlength="255">
                    </div>
                </div>
                <div class="form-text mt-2">
                    Si la salida queda antes que la entrada, el turno cruza medianoche y se divide automáticamente en dos días.
                </div>
                <div class="alert alert-primary py-2 small mt-2 mb-0 d-none" id="rhEditNocturnalNote">
                    <i class="fas fa-moon me-1"></i>Este bloque es un turno nocturno heredado: la edición reemplaza el turno completo (ambos días).
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- Form oculto de borrado (individual y selección múltiple) -->
<form action="<?php echo URLROOT; ?>/registroHoras/eliminarBloque" method="post" id="rhDeleteForm" class="d-none">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="employee_id" value="<?php echo (int)$data['selected']->id; ?>">
    <?php foreach (['month','from','to','city','branch'] as $_bf): ?>
    <input type="hidden" name="back_<?php echo $_bf; ?>" value="<?php echo htmlspecialchars((string)$filters[$_bf]); ?>">
    <?php endforeach; ?>
    <span id="rhDeleteIds"></span>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Modal de edición.
    var editModalEl = document.getElementById('rhEditModal');
    var editModal = (editModalEl && window.bootstrap) ? new bootstrap.Modal(editModalEl) : null;
    document.querySelectorAll('.rh-edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!editModal) return;
            document.getElementById('rhEditId').value = btn.dataset.id;
            document.getElementById('rhEditDate').value = btn.dataset.date;
            document.getElementById('rhEditStart').value = btn.dataset.start;
            document.getElementById('rhEditEnd').value = btn.dataset.end;
            document.getElementById('rhEditNotes').value = btn.dataset.notes || '';
            var branchField = document.getElementById('rhEditBranch');
            branchField.value = btn.dataset.branch;
            if (btn.dataset.branch !== '' && branchField.value !== btn.dataset.branch) {
                // Sucursal fuera del catálogo actual: agregarla para no perderla.
                var extra = new Option(btn.dataset.branch + ' (actual)', btn.dataset.branch, true, true);
                branchField.appendChild(extra);
            }
            // Bloque sin sucursal: obligar a elegir una real (nunca persistir
            // la etiqueta de presentación "Sin sucursal").
            if (btn.dataset.branch === '') branchField.value = '';
            document.getElementById('rhEditNocturnalNote').classList.toggle('d-none', btn.dataset.nocturnal !== '1');
            editModal.show();
        });
    });

    // Borrado (individual y por selección).
    var deleteForm = document.getElementById('rhDeleteForm');
    var deleteIds = document.getElementById('rhDeleteIds');
    function submitDelete(ids, label) {
        deleteIds.innerHTML = '';
        ids.forEach(function (id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'block_ids[]';
            input.value = id;
            deleteIds.appendChild(input);
        });
        var text = ids.length === 1
            ? 'Se eliminará el bloque ' + (label || '') + '.'
            : 'Se eliminarán ' + ids.length + ' bloques seleccionados.';
        if (window.Swal) {
            Swal.fire({
                title: '¿Eliminar?',
                text: text + ' Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then(function (r) { if (r.isConfirmed) deleteForm.submit(); });
        } else if (confirm(text)) {
            deleteForm.submit();
        }
    }
    document.querySelectorAll('.rh-del-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            submitDelete([btn.dataset.id], btn.dataset.label);
        });
    });

    // Modo selección múltiple.
    var toggleBtn = document.getElementById('rhToggleSelect');
    var delSelBtn = document.getElementById('rhDeleteSelected');
    var cards = document.getElementById('rhDayCards');
    var countEl = document.getElementById('rhSelCount');
    function selectedIds() {
        // Las dos mitades de un nocturno heredado comparten id: contar únicos.
        var ids = Array.prototype.map.call(document.querySelectorAll('.rh-row-check:checked'), function (c) { return c.value; });
        return ids.filter(function (v, i) { return ids.indexOf(v) === i; });
    }
    function refreshCount() {
        var n = selectedIds().length;
        if (countEl) countEl.textContent = n;
        if (delSelBtn) delSelBtn.disabled = n === 0;
    }
    if (toggleBtn && cards) {
        toggleBtn.addEventListener('click', function () {
            var on = cards.classList.toggle('rh-select-mode');
            toggleBtn.innerHTML = on
                ? '<i class="fas fa-times me-1"></i>Cancelar selección'
                : '<i class="fas fa-check-square me-1"></i>Seleccionar';
            if (delSelBtn) delSelBtn.classList.toggle('d-none', !on);
            if (!on) document.querySelectorAll('.rh-row-check').forEach(function (c) { c.checked = false; });
            refreshCount();
        });
    }
    document.querySelectorAll('.rh-row-check').forEach(function (c) { c.addEventListener('change', refreshCount); });
    if (delSelBtn) {
        delSelBtn.addEventListener('click', function () {
            var ids = selectedIds();
            if (ids.length > 0) submitDelete(ids, null);
        });
    }
});
</script>
<?php endif; ?>

<script src="<?php echo URLROOT; ?>/js/rh-tooltip.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ── Tooltip rico del calendario + click para ir al registro del día ──
    // (port de showTooltip/scrollToDateRecord de view_hours.js de hoursapp)
    var DAY_TIPS = <?php echo json_encode($dayTips, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var STATUS_TIPS = <?php echo json_encode($statusTips, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var DIAS_ES = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    function dowIso(iso) {
        var p = iso.split('-').map(Number);
        return ((new Date(p[0], p[1] - 1, p[2]).getDay() + 6) % 7) + 1;
    }
    var calBox = document.querySelector('.rh-cal');
    if (calBox && window.RhTooltip) {
        var esc = RhTooltip.esc;
        function tipHtml(iso) {
            var head = '<div class="rh-tip-fecha">' + DIAS_ES[dowIso(iso)] + ' ' + iso.slice(8, 10) + '/' + iso.slice(5, 7) + '/' + iso.slice(0, 4) + '</div>';
            if (STATUS_TIPS[iso]) {
                return head + '<div class="rh-tip-suc">Estado: ' + esc(STATUS_TIPS[iso]) + '</div>'
                    + '<div class="rh-tip-hint">Este día no se pueden registrar horas</div>';
            }
            var tip = DAY_TIPS[iso];
            if (!tip) return null;
            var html = head;
            Object.keys(tip.branches).forEach(function (b) {
                html += '<div class="rh-tip-suc"><i class="fas fa-map-marker-alt me-1"></i>' + esc(b) + '</div>';
                tip.branches[b].forEach(function (r) {
                    html += '<div class="rh-tip-rango"><i class="far fa-clock me-1"></i>' + esc(r) + '</div>';
                });
            });
            if (tip.holiday) {
                html += '<div class="rh-tip-feriado"><i class="fas fa-star me-1"></i>Feriado: ' + esc(tip.holiday) + '</div>';
            }
            html += '<div class="rh-tip-hint">Click para ver el registro</div>';
            return html;
        }
        calBox.addEventListener('mouseover', function (ev) {
            var c = ev.target.closest && ev.target.closest('[data-cal-date]');
            if (!c) return;
            var html = tipHtml(c.dataset.calDate);
            if (html) {
                RhTooltip.show(c, html);
                if (DAY_TIPS[c.dataset.calDate]) c.style.cursor = 'pointer';
            }
        });
        calBox.addEventListener('mouseout', function () { RhTooltip.hide(); });
        calBox.addEventListener('click', function (ev) {
            var c = ev.target.closest && ev.target.closest('[data-cal-date]');
            if (!c || !DAY_TIPS[c.dataset.calDate]) return;
            RhTooltip.hide();
            var card = document.querySelector('.rh-day-card[data-date="' + c.dataset.calDate + '"]');
            if (card) {
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                card.style.outline = '2px solid var(--clr-warning)';
                setTimeout(function () { card.style.outline = ''; }, 1600);
            }
        });
    }

    // Selects encadenados ciudad → sucursal del filtro (también en maqueta).
    var citySel = document.getElementById('rhFilterCity');
    var branchSel = document.getElementById('rhFilterBranch');
    function refreshBranchOptions() {
        if (!citySel || !branchSel) return;
        var city = citySel.value;
        var current = branchSel.value;
        var currentVisible = false;
        Array.prototype.forEach.call(branchSel.options, function (o) {
            if (o.value === '') return;
            var show = city === '' || o.dataset.city === city;
            o.hidden = !show;
            o.disabled = !show;
            if (show && o.value === current) currentVisible = true;
        });
        if (!currentVisible) branchSel.value = '';
    }
    if (citySel) citySel.addEventListener('change', refreshBranchOptions);
    refreshBranchOptions();
});
</script>

<?php require APPROOT . '/views/inc/footer.php'; ?>
