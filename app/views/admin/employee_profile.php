<?php require APPROOT . '/views/inc/header.php';

$clockMap = $GLOBALS['CLOCK_DEVICE_MAP'] ?? [];
$diasCorto = ['Sun'=>'Dom','Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mié','Thu'=>'Jue','Fri'=>'Vie','Sat'=>'Sáb'];

$byDay = [];
foreach ($data['clockings'] as $c) {
    $day = date('Y-m-d', strtotime($c->event_time));
    $byDay[$day][] = $c;
}
foreach ($byDay as $day => &$items) {
    usort($items, fn($a, $b) => strtotime($a->event_time) - strtotime($b->event_time));
}
unset($items);
krsort($byDay);

$daySummaries = $data['day_summaries'] ?? [];
$totalHorasPeriodo = 0;
foreach ($byDay as $day => $_) {
    $h = $daySummaries[$day]['total_hours'] ?? null;
    if ($h !== null) {
        $totalHorasPeriodo += (float)$h;
    }
}
$entradas = count(array_filter($data['clockings'], fn($c) => ($c->direction ?? '') === 'P10'));
$salidas  = count(array_filter($data['clockings'], fn($c) => ($c->direction ?? '') === 'P20'));
?>

<style>
.emp-prof .emp-side-card { border: 1px solid #e2e8f0; border-radius: .75rem; background: #fff; }
.emp-prof .emp-avatar { width: 88px; height: 88px; object-fit: cover; border: 3px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
.emp-prof .jornada-table thead th {
    font-size: .7rem; text-transform: uppercase; letter-spacing: .04em;
    color: #64748b; font-weight: 600; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
}
.emp-prof .jornada-table tbody td { vertical-align: middle; font-size: .875rem; padding: .65rem .75rem; }
.emp-prof .jornada-table .col-horas { font-size: 1.1rem; font-weight: 700; color: #0f172a; white-space: nowrap; }
.emp-prof .jornada-table .col-hora-in { color: #15803d; font-weight: 600; font-variant-numeric: tabular-nums; }
.emp-prof .jornada-table .col-hora-out { color: #0369a1; font-weight: 600; font-variant-numeric: tabular-nums; }
.emp-prof .jornada-table tr.day-row { cursor: pointer; }
.emp-prof .jornada-table tr.day-row:hover { background: #f8fafc; }
.emp-prof .jornada-table tr.day-row.is-open { background: #f1f5f9; }
.emp-prof .marcas-detail { background: #fafbfc; border-top: 1px solid #e2e8f0; }
.emp-prof .marcas-detail .marc-line {
    display: flex; align-items: center; gap: .75rem; padding: .35rem 0;
    font-size: .8rem; border-bottom: 1px solid #f1f5f9;
}
.emp-prof .marcas-detail .marc-line:last-child { border-bottom: none; }
.emp-prof .marc-dup-badge { font-size: .65rem; }
.emp-prof .nav-tabs .nav-link { font-size: .875rem; color: #64748b; border: none; padding: .75rem 1rem; }
.emp-prof .nav-tabs .nav-link.active { color: var(--clr-primary); border-bottom: 2px solid var(--clr-primary); font-weight: 600; background: transparent; }
.emp-prof .emp-contact-actions { display: flex; gap: .5rem; margin-top: .5rem; }
.emp-prof .emp-contact-actions .btn { flex: 1; font-size: .75rem; padding: .35rem .5rem; }
.emp-prof .emp-contact-actions .btn-whatsapp {
    background: #25d366;
    border-color: #25d366;
    color: #fff;
}
.emp-prof .emp-contact-actions .btn-whatsapp:hover {
    background: #1da851;
    border-color: #1da851;
    color: #fff;
}
.emp-prof #schedule-calendar {
    min-height: 420px;
    padding: .5rem;
}
.emp-prof #schedule-calendar .fc-view-harness-active {
    min-height: 380px;
}
.emp-prof #tab-sched .fc .fc-scrollgrid {
    border-radius: .5rem;
    overflow: hidden;
}
.emp-prof .sched-cal-legend {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem 1rem;
    font-size: .75rem;
    color: #475569;
    margin-bottom: .75rem;
}
.emp-prof .sched-cal-legend span {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
}
.emp-prof .sched-cal-legend i {
    width: 14px;
    height: 14px;
    border-radius: 3px;
    display: inline-block;
    flex-shrink: 0;
}
.emp-prof .sched-leg-plan { background: #dbeafe; border: 2px solid #1d4ed8; }
.emp-prof .sched-leg-in { background: #059669; }
.emp-prof .sched-leg-out { background: #0891b2; }
.emp-prof .sched-leg-abs { background: #fee2e2; border: 2px dashed #ef4444; }
.emp-prof .sched-leg-alert { background: #ffedd5; border: 1px solid #f59e0b; }
.emp-prof .sched-leg-lic { background: rgba(167, 139, 250, .55); }
.emp-prof .sched-leg-swap { background: rgba(251, 191, 36, .55); }
.emp-prof .sched-leg-just { background: #ede9fe; border-left: 3px solid #7c3aed; }
.emp-prof .incident-card {
    border: 1px solid #e2e8f0;
    border-radius: .5rem;
    padding: 1rem;
    margin-bottom: .75rem;
    background: #fff;
}
.emp-prof .incident-card:last-child { margin-bottom: 0; }
.emp-prof .incident-meta { font-size: .75rem; color: #64748b; }
.emp-prof .incident-form-panel {
    border: 1px dashed #cbd5e1;
    border-radius: .5rem;
    padding: 1rem;
    background: #f8fafc;
    margin-bottom: 1.25rem;
}

/* Eventos FullCalendar — ficha empleado */
#schedule-calendar .fc-daygrid-event.fc-ev-planned {
    background: #dbeafe !important;
    border: 1px solid #93c5fd !important;
    border-left: 4px solid #1d4ed8 !important;
    color: #1e3a8a !important;
    font-weight: 600;
    font-size: .72rem !important;
    line-height: 1.25 !important;
    padding: 3px 6px !important;
    margin: 1px 2px 2px !important;
    border-radius: 4px !important;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    box-shadow: none !important;
}
#schedule-calendar .fc-ev-planned .fc-event-main,
#schedule-calendar .fc-ev-planned .fc-event-title,
#schedule-calendar .fc-ev-planned .fc-event-time {
    color: #1e3a8a !important;
}
#schedule-calendar .fc-ev-planned .fc-event-time {
    display: none !important;
}
#schedule-calendar .fc-ev-clock-in {
    background: #059669 !important;
    border-color: #047857 !important;
    color: #fff !important;
}
#schedule-calendar .fc-ev-clock-out {
    background: #0891b2 !important;
    border-color: #0e7490 !important;
    color: #fff !important;
}
#schedule-calendar .fc-ev-clock-other {
    background: #64748b !important;
    border-color: #475569 !important;
    color: #fff !important;
}
#schedule-calendar .fc-ev-absence {
    background: #fee2e2 !important;
    border: 2px dashed #ef4444 !important;
    color: #991b1b !important;
    font-weight: 700;
}
#schedule-calendar .fc-ev-alert {
    background: #ffedd5 !important;
    border-color: #f59e0b !important;
    color: #9a3412 !important;
    font-weight: 600;
}
#schedule-calendar .fc-ev-unplanned {
    background: #ecfeff !important;
    border-color: #06b6d4 !important;
    color: #0e7490 !important;
}
#schedule-calendar .fc-ev-justified {
    background: #ede9fe !important;
    border-color: #7c3aed !important;
    color: #5b21b6 !important;
    border-left-width: 4px !important;
}
/* Licencias y cambios de turno: solo pintar el día, sin etiqueta escrita */
#schedule-calendar .fc-bg-event.fc-ev-license,
#schedule-calendar .fc-bg-event.fc-ev-swap {
    opacity: 0.42 !important;
    border: none !important;
}
#schedule-calendar .fc-ev-license .fc-event-title,
#schedule-calendar .fc-ev-swap .fc-event-title,
#schedule-calendar .fc-bg-event .fc-event-title {
    display: none !important;
    font-size: 0 !important;
    line-height: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
}
#schedule-calendar .fc-daygrid-day:has(.fc-ev-license) .fc-daygrid-day-number {
    font-weight: 700;
}
#schedule-calendar .fc-daygrid-day:has(.fc-ev-swap) .fc-daygrid-day-number {
    font-weight: 700;
}
#schedule-calendar .fc-daygrid-event:not(.fc-bg-event):not(.fc-ev-planned) {
    font-size: .7rem;
    padding: 2px 5px;
    margin: 1px 2px;
    border-radius: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
#schedule-calendar .fc-daygrid-event .fc-event-time {
    display: none !important;
}
</style>

<div class="emp-prof">
    <div class="admin-page-head mb-4">
        <div class="admin-page-brand">
            <div class="admin-page-icon"><i class="fas fa-user"></i></div>
            <div class="admin-page-meta">
                <h2 class="page-title mb-0"><?php echo htmlspecialchars($data['user']->full_name); ?></h2>
                <p class="page-subtitle mb-0">Ficha del empleado · <?php echo ucfirst($data['user']->role); ?></p>
            </div>
        </div>
        <div class="admin-page-actions d-flex gap-2">
            <a href="<?php echo URLROOT; ?>/admin/users" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Volver</a>
            <a href="<?php echo URLROOT; ?>/admin/editUser/<?php echo $data['user']->id; ?>" class="btn btn-outline-primary btn-sm">Editar</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-3">
            <div class="emp-side-card p-3 text-center mb-3">
                <img src="<?php echo htmlspecialchars(avatar_url($data['user']->profile_picture ?? '')); ?>"
                     alt="" class="rounded-circle emp-avatar mb-2"
                     onerror="this.onerror=null;this.src='<?php echo htmlspecialchars(avatar_default_url(), ENT_QUOTES); ?>';">
                <div class="fw-semibold"><?php echo htmlspecialchars($data['user']->full_name); ?></div>
                <div class="text-muted small"><?php echo htmlspecialchars($data['user']->username); ?></div>
            </div>
            <div class="emp-side-card p-3 small">
                <div class="text-muted text-uppercase fw-semibold mb-2" style="font-size:.65rem;">Datos</div>
                <p class="mb-1"><span class="text-muted">Email</span><br><?php echo htmlspecialchars($data['user']->email ?? '—'); ?></p>
                <?php if (!empty($data['user']->address)): ?>
                <p class="mb-1"><span class="text-muted">Dirección</span><br><?php echo htmlspecialchars($data['user']->address); ?></p>
                <?php endif; ?>
                <?php if (!empty($data['user']->document_number)): ?>
                <p class="mb-1"><span class="text-muted">DNI</span><br><?php echo htmlspecialchars($data['user']->document_number); ?></p>
                <?php endif; ?>
                <?php if (!empty($data['user']->cuil)): ?>
                <p class="mb-1"><span class="text-muted">CUIL</span><br><?php echo htmlspecialchars($data['user']->cuil); ?></p>
                <?php endif; ?>
                <?php if (!empty($data['user']->birth_date)): ?>
                <p class="mb-1"><span class="text-muted">Nacimiento</span><br><?php echo date('d/m/Y', strtotime($data['user']->birth_date)); ?></p>
                <?php endif; ?>
                <?php if (!empty($data['user']->sex)): ?>
                <p class="mb-1"><span class="text-muted">Sexo</span><br><?php echo htmlspecialchars(User::sexLabel($data['user']->sex)); ?></p>
                <?php endif; ?>
                <?php if (!empty($data['user']->gender)): ?>
                <p class="mb-1"><span class="text-muted">Género</span><br><?php echo htmlspecialchars(User::genderLabel($data['user']->gender)); ?></p>
                <?php endif; ?>
                <?php
                $empPhone = trim((string)($data['user']->phone_number ?? ''));
                $empHasPhone = phone_has_valid($empPhone);
                ?>
                <p class="mb-1">
                    <span class="text-muted">Teléfono</span><br>
                    <?php if ($empPhone !== ''): ?>
                        <?php echo htmlspecialchars($empPhone); ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                    <?php if ($empHasPhone): ?>
                    <div class="emp-contact-actions">
                        <a href="<?php echo htmlspecialchars(phone_tel_href($empPhone)); ?>"
                           class="btn btn-sm btn-outline-primary"
                           title="Llamar">
                            <i class="fas fa-phone me-1"></i>Llamar
                        </a>
                        <a href="<?php echo htmlspecialchars(phone_whatsapp_href($empPhone)); ?>"
                           class="btn btn-sm btn-whatsapp"
                           target="_blank"
                           rel="noopener noreferrer"
                           title="Abrir WhatsApp">
                            <i class="fab fa-whatsapp me-1"></i>WhatsApp
                        </a>
                    </div>
                    <?php endif; ?>
                </p>
                <hr class="my-2">
                <p class="mb-1"><span class="text-muted">Tarifa/h</span> $<?php echo number_format($data['user']->hourly_rate, 2); ?></p>
                <p class="mb-0"><span class="text-muted">Límite sem.</span> <?php echo $data['user']->weekly_hour_limit; ?> h</p>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="card border shadow-sm">
                <div class="card-header bg-white border-bottom px-0 pt-2">
                    <ul class="nav nav-tabs px-3" role="tablist">
                        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-roadmap" id="tab-roadmap-link">Roadmap</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-marc">Marcaciones</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-sched">Horario</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-record">Legajo</a></li>
                        <?php if (!empty($data['overtime_tab_enabled'])): ?>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-he">Horas extras</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-req">Solicitudes</a></li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tab-incidents" id="tab-incidents-link">
                                Incidencias
                                <?php if (!empty($data['incidents'])): ?>
                                <span class="badge bg-secondary ms-1"><?php echo count($data['incidents']); ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tab-vacation" id="tab-vacation-link">
                                Vacaciones
                                <?php if (!empty($data['vacation_summary']['total_pending'])): ?>
                                <span class="badge bg-info ms-1"><?php echo vacation_format_days($data['vacation_summary']['total_pending']); ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <?php require APPROOT . '/views/admin/partials/employee_roadmap_tab.php'; ?>
                        <?php require APPROOT . '/views/admin/partials/employee_complete_record_tab.php'; ?>
                        <div class="tab-pane fade" id="tab-marc">
                            <div class="admin-kpi-grid mb-4" style="grid-template-columns:repeat(4,1fr);">
                                <div class="admin-kpi-card">
                                    <div class="admin-kpi-icon" style="background:#f1f5f9;color:#475569"><i class="fas fa-calendar-day"></i></div>
                                    <div>
                                        <div class="admin-kpi-value"><?php echo count($byDay); ?></div>
                                        <div class="admin-kpi-label">Días</div>
                                    </div>
                                </div>
                                <div class="admin-kpi-card">
                                    <div class="admin-kpi-icon" style="background:#d1fae5;color:#15803d"><i class="fas fa-sign-in-alt"></i></div>
                                    <div>
                                        <div class="admin-kpi-value"><?php echo $entradas; ?></div>
                                        <div class="admin-kpi-label">Entradas</div>
                                    </div>
                                </div>
                                <div class="admin-kpi-card">
                                    <div class="admin-kpi-icon" style="background:#e0f2fe;color:#0369a1"><i class="fas fa-sign-out-alt"></i></div>
                                    <div>
                                        <div class="admin-kpi-value"><?php echo $salidas; ?></div>
                                        <div class="admin-kpi-label">Salidas</div>
                                    </div>
                                </div>
                                <div class="admin-kpi-card">
                                    <div class="admin-kpi-icon" style="background:var(--clr-primary-l);color:var(--clr-primary)"><i class="fas fa-clock"></i></div>
                                    <div>
                                        <div class="admin-kpi-value"><?php echo number_format($totalHorasPeriodo, 1, ',', '.'); ?></div>
                                        <div class="admin-kpi-label">Horas calc.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <p class="text-muted small mb-0">
                                    Resumen por día. Si hay marcas duplicadas, se usa la <strong>primera entrada</strong> y la <strong>última salida</strong>.
                                </p>
                                <a href="<?php echo URLROOT; ?>/admin/marcacionesTodas?employee_id=<?php echo urlencode($data['user']->clock_id ?? ''); ?>"
                                   class="btn btn-sm btn-outline-secondary">Listado completo</a>
                            </div>

                            <?php if (empty($byDay)): ?>
                                <div class="text-center py-5 text-muted border rounded">
                                    <p class="mb-2">Sin marcaciones en el período.</p>
                                    <a href="<?php echo URLROOT; ?>/admin/sync" class="btn btn-primary btn-sm">Sincronizar</a>
                                </div>
                            <?php else: ?>
                            <div class="table-responsive border rounded">
                                <table class="table jornada-table mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width:28px"></th>
                                            <th>Fecha</th>
                                            <th>Entrada</th>
                                            <th>Salida</th>
                                            <th class="text-end">Horas</th>
                                            <th class="text-center">Marcas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($byDay as $day => $items):
                                        $summary = $daySummaries[$day] ?? [];
                                        $ts = strtotime($day);
                                        $fechaLbl = $diasCorto[date('D', $ts)] . ' ' . date('d/m/Y', $ts);
                                        $hIn  = !empty($summary['entry_time']) ? substr($summary['entry_time'], 0, 5) : '—';
                                        $hOut = !empty($summary['exit_time']) ? substr($summary['exit_time'], 0, 5) : '—';
                                        $horas = $summary['total_hours'] ?? null;
                                        $calcMethod = $summary['calc_method'] ?? null;
                                        $dayId = 'det-' . str_replace('-', '', $day);
                                        $dupNote = ($calcMethod === 'first_last');
                                    ?>
                                        <tr class="day-row" data-bs-toggle="collapse" data-bs-target="#<?php echo $dayId; ?>" aria-expanded="false">
                                            <td class="text-muted"><i class="fas fa-chevron-right fa-xs day-chevron"></i></td>
                                            <td>
                                                <?php echo $fechaLbl; ?>
                                                <?php if ($dupNote): ?>
                                                <span class="badge bg-warning text-dark marc-dup-badge ms-1" title="Marcas desbalanceadas: 1ª entrada y última salida">Ajuste</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="col-hora-in"><?php echo $hIn; ?></td>
                                            <td class="col-hora-out"><?php echo $hOut; ?></td>
                                            <td class="text-end col-horas">
                                                <?php echo $horas !== null ? number_format((float)$horas, 2, ',', '.') . ' h' : '—'; ?>
                                            </td>
                                            <td class="text-center text-muted"><?php echo count($items); ?></td>
                                        </tr>
                                        <tr class="collapse" id="<?php echo $dayId; ?>">
                                            <td colspan="6" class="p-0">
                                                <div class="marcas-detail px-3 py-2">
                                                    <?php foreach ($items as $c):
                                                        $isIn = (($c->direction ?? '') === 'P10');
                                                        $isOut = (($c->direction ?? '') === 'P20');
                                                    ?>
                                                    <div class="marc-line">
                                                        <span class="text-muted" style="min-width:42px;font-variant-numeric:tabular-nums;">
                                                            <?php echo date('H:i', strtotime($c->event_time)); ?>
                                                        </span>
                                                        <?php if ($isIn): ?>
                                                            <span class="badge bg-success bg-opacity-10 text-success">Entrada</span>
                                                        <?php elseif ($isOut): ?>
                                                            <span class="badge bg-primary bg-opacity-10 text-primary">Salida</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary bg-opacity-10 text-secondary">—</span>
                                                        <?php endif; ?>
                                                        <?php echo marcClockBadge($c->device_name ?? null, $clockMap); ?>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="tab-pane fade" id="tab-sched">
                            <div class="sched-cal-legend">
                                <span><i class="sched-leg-plan"></i> Turno planificado</span>
                                <span><i class="sched-leg-in"></i> Entrada (marca)</span>
                                <span><i class="sched-leg-out"></i> Salida (marca)</span>
                                <span><i class="sched-leg-abs"></i> Ausente</span>
                                <span><i class="sched-leg-alert"></i> Tarde / alerta</span>
                                <span><i class="sched-leg-lic"></i> Licencia (día pintado)</span>
                                <span><i class="sched-leg-swap"></i> Cambio de turno (día pintado)</span>
                                <span><i class="sched-leg-just"></i> Justificado</span>
                            </div>
                            <div id="schedule-calendar"></div>
                        </div>

                        <?php if (!empty($data['overtime_tab_enabled'])): ?>
                        <div class="tab-pane fade" id="tab-he">
                            <?php if (!empty($data['pending_overtime_count'])): ?>
                            <div class="d-flex justify-content-end mb-3">
                                <a href="<?php echo URLROOT; ?>/admin/employeeDetails/<?php echo (int)$data['user']->id; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-edit me-1"></i>Gestionar pendientes (<?php echo (int)$data['pending_overtime_count']; ?>)
                                </a>
                            </div>
                            <?php endif; ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light"><tr><th>Fecha</th><th>50%</th><th>100%</th><th>Estado</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($data['overtime'] as $entry): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($entry->entry_date)); ?></td>
                                            <td><?php echo $entry->hours_50; ?></td>
                                            <td><?php echo $entry->hours_100; ?></td>
                                            <td><?php echo htmlspecialchars(overtimeStatusLabel($entry->status ?? '')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php require __DIR__ . '/employee_profile_requests_tab.php'; ?>

                        <?php require __DIR__ . '/employee_profile_incidents_tab.php'; ?>
                        <?php require __DIR__ . '/employee_profile_vacation_tab.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require APPROOT . '/views/inc/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.day-row').forEach(function(row) {
        var target = row.getAttribute('data-bs-target');
        if (!target) return;
        var el = document.querySelector(target);
        if (!el) return;
        el.addEventListener('show.bs.collapse', function() {
            row.classList.add('is-open');
            row.querySelector('.day-chevron').classList.replace('fa-chevron-right', 'fa-chevron-down');
        });
        el.addEventListener('hide.bs.collapse', function() {
            row.classList.remove('is-open');
            row.querySelector('.day-chevron').classList.replace('fa-chevron-down', 'fa-chevron-right');
        });
    });

    var calEl = document.getElementById('schedule-calendar');
    var scheduleCal = null;

    function initScheduleCalendar() {
        if (!calEl || scheduleCal || typeof window.FullCalendar === 'undefined') return;
        scheduleCal = new window.FullCalendar.Calendar(calEl, {
            initialView: 'dayGridMonth',
            locale: 'es',
            themeSystem: 'bootstrap5',
            height: 'auto',
            expandRows: true,
            headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
            buttonIcons: {
                prev: 'chevron-left',
                next: 'chevron-right'
            },
            buttonText: { today: 'Hoy' },
            events: <?php echo $data['calendarEvents']; ?>,
            displayEventTime: false,
            eventOrder: 'order',
            dayMaxEvents: 5,
            moreLinkText: function(n) { return '+' + n + ' más'; },
            eventDisplay: 'block',
            eventDidMount: function(info) {
                var hint = info.event.extendedProps && info.event.extendedProps.hint;
                if (hint) {
                    info.el.setAttribute('title', hint);
                }
                if (info.event.display === 'background') {
                    var t = info.el.querySelector('.fc-event-title');
                    if (t) {
                        t.textContent = '';
                        t.style.display = 'none';
                    }
                }
            }
        });
        scheduleCal.render();
    }

    var schedTabBtn = document.querySelector('a[data-bs-toggle="tab"][href="#tab-sched"]');
    if (schedTabBtn) {
        schedTabBtn.addEventListener('shown.bs.tab', function() {
            initScheduleCalendar();
            if (scheduleCal) {
                setTimeout(function() { scheduleCal.updateSize(); }, 80);
            }
        });
    }

    if (window.location.hash === '#tab-roadmap' || /roadmap_month=/.test(window.location.search)) {
        var roadTab = document.querySelector('a[href="#tab-roadmap"]');
        if (roadTab && typeof bootstrap !== 'undefined') {
            bootstrap.Tab.getOrCreateInstance(roadTab).show();
        }
    }
    if (window.location.hash === '#tab-incidents') {
        var incTab = document.querySelector('a[href=\"#tab-incidents\"]');
        if (incTab && typeof bootstrap !== 'undefined') {
            bootstrap.Tab.getOrCreateInstance(incTab).show();
        }
    }
    if (window.location.hash === '#tab-vacation') {
        var vacTab = document.querySelector('a[href=\"#tab-vacation\"]');
        if (vacTab && typeof bootstrap !== 'undefined') {
            bootstrap.Tab.getOrCreateInstance(vacTab).show();
        }
    }
    if (window.location.hash === '#tab-req') {
        var reqTab = document.querySelector('a[href=\"#tab-req\"]');
        if (reqTab && typeof bootstrap !== 'undefined') {
            bootstrap.Tab.getOrCreateInstance(reqTab).show();
        }
    }
});
</script>
