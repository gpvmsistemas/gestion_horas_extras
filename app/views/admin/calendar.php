<?php require APPROOT . '/views/inc/header.php';

$calendar = $data['calendar'] ?? null;
$selectedUser = $data['selected_user'] ?? null;
$openDay = $_GET['day'] ?? '';
$viewMode = ($data['view_mode'] ?? 'grid') === 'list' ? 'list' : 'grid';
$listFilter = ($data['list_filter'] ?? '') === 'alerts' ? 'alerts' : '';
$monthStats = $data['month_stats'] ?? null;
$employeeNav = $data['employee_nav'] ?? ['prev' => null, 'next' => null];

$calQs = function ($extra = []) use ($data, $viewMode, $listFilter) {
    $p = array_merge([
        'month' => $data['month'],
        'user_id' => (int)$data['user_id'],
        'view' => $viewMode,
    ], $extra);
    if ($listFilter === 'alerts') {
        $p['filter'] = 'alerts';
    }
    return http_build_query($p);
};

$todayMonth = date('Y-m');
$todayDay = date('Y-m-d');
?>

<div class="admin-page-head">
    <div class="admin-page-brand">
        <div class="admin-page-icon"><i class="fas fa-calendar"></i></div>
        <div class="admin-page-meta">
            <h2 class="page-title">Calendario mensual</h2>
            <p class="page-subtitle mb-0">Roadmap del empleado: plan, fichadas, vacaciones, licencias e incidencias</p>
        </div>
    </div>
    <div class="admin-page-actions d-flex flex-wrap gap-2">
        <a href="<?php echo URLROOT; ?>/admin/dashboard" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Dashboard
        </a>
        <a href="<?php echo URLROOT; ?>/admin/weeklyPlanner" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-calendar-week me-1"></i> Planificador
        </a>
        <?php if ($selectedUser): ?>
        <a href="<?php echo URLROOT; ?>/admin/employeeProfile/<?php echo (int)$data['user_id']; ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-id-card me-1"></i> Ficha completa
        </a>
        <?php endif; ?>
    </div>
</div>

<form method="get" action="<?php echo URLROOT; ?>/admin/calendar" class="cal-toolbar mb-3" id="calFilterForm">
    <input type="hidden" name="view" value="<?php echo htmlspecialchars($viewMode); ?>">
    <?php if ($listFilter === 'alerts'): ?>
    <input type="hidden" name="filter" value="alerts">
    <?php endif; ?>
    <div class="row g-2 align-items-end">
        <div class="col-lg-5">
            <label class="form-label small fw-semibold mb-1">Empleado</label>
            <div class="input-group input-group-sm">
                <?php if (!empty($employeeNav['prev'])): ?>
                <a href="<?php echo URLROOT; ?>/admin/calendar?<?php echo $calQs(['user_id' => (int)$employeeNav['prev']->id]); ?>"
                   class="btn btn-outline-secondary" title="<?php echo htmlspecialchars($employeeNav['prev']->full_name); ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                <select name="user_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($data['employees'] as $emp): ?>
                    <option value="<?php echo (int)$emp->id; ?>" <?php echo (int)$data['user_id'] === (int)$emp->id ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($emp->full_name); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($employeeNav['next'])): ?>
                <a href="<?php echo URLROOT; ?>/admin/calendar?<?php echo $calQs(['user_id' => (int)$employeeNav['next']->id]); ?>"
                   class="btn btn-outline-secondary" title="<?php echo htmlspecialchars($employeeNav['next']->full_name); ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-3 col-lg-2">
            <label class="form-label small fw-semibold mb-1">Mes</label>
            <input type="month" name="month" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['month']); ?>">
        </div>
        <div class="col-6 col-md-2 col-lg-1">
            <button type="submit" class="btn btn-primary btn-sm w-100" aria-label="Aplicar filtros" title="Aplicar filtros"><i class="fas fa-search"></i></button>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <a href="<?php echo URLROOT; ?>/admin/calendar?<?php echo $calQs(['month' => $todayMonth, 'day' => $todayDay]); ?>"
               class="btn btn-outline-secondary btn-sm w-100">Hoy</a>
        </div>
        <?php if ($calendar && $selectedUser): ?>
        <div class="col-md-4 col-lg-2">
            <div class="btn-group cal-view-toggle w-100" role="group">
                <a href="<?php echo URLROOT; ?>/admin/calendar?<?php echo $calQs(['view' => 'grid']); ?>"
                   class="btn btn-sm btn-outline-secondary <?php echo $viewMode === 'grid' ? 'active' : ''; ?>" title="Grilla">
                    <i class="fas fa-th"></i>
                </a>
                <a href="<?php echo URLROOT; ?>/admin/calendar?<?php echo $calQs(['view' => 'list']); ?>"
                   class="btn btn-sm btn-outline-secondary <?php echo $viewMode === 'list' ? 'active' : ''; ?>" title="Lista">
                    <i class="fas fa-list"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</form>

<?php if ($calendar && $selectedUser): ?>

<?php if ($monthStats): ?>
<div class="cal-month-stats row g-2 mb-3">
    <div class="col-6 col-md-4 col-lg">
        <div class="cal-stat-card cal-stat-card--alert">
            <span class="cal-stat-value"><?php echo (int)$monthStats['alerts']; ?></span>
            <span class="cal-stat-label">Alertas RRHH</span>
            <span class="cal-stat-hint">Tarde, ausente, salida… sin justificar</span>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="cal-stat-card cal-stat-card--ok">
            <span class="cal-stat-value"><?php echo (int)$monthStats['ok']; ?></span>
            <span class="cal-stat-label">En horario</span>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="cal-stat-card">
            <span class="cal-stat-value"><?php echo (int)$monthStats['justified']; ?></span>
            <span class="cal-stat-label">Justificados</span>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="cal-stat-card cal-stat-card--vacation">
            <span class="cal-stat-value"><?php echo (int)$monthStats['vacation']; ?></span>
            <span class="cal-stat-label">Vacaciones</span>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="cal-stat-card cal-stat-card--leave">
            <span class="cal-stat-value"><?php echo (int)$monthStats['leave']; ?></span>
            <span class="cal-stat-label">Licencias</span>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="cal-stat-card">
            <span class="cal-stat-value"><?php echo (int)$monthStats['incidents']; ?></span>
            <span class="cal-stat-label">Días con incidencia</span>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="cal-toolbar-meta mb-3 d-flex flex-wrap align-items-center gap-3">
    <?php if ($data['vacation_pending'] !== null): ?>
    <span class="cal-vacation-pill">
        <i class="fas fa-umbrella-beach me-1"></i>
        Vacaciones pendientes: <strong><?php echo vacation_format_days($data['vacation_pending']); ?></strong>
    </span>
    <a href="<?php echo URLROOT; ?>/admin/employeeProfile/<?php echo (int)$data['user_id']; ?>#tab-vacation" class="small">Períodos</a>
    <a href="<?php echo URLROOT; ?>/vacationAdmin/vacationSetup/<?php echo (int)$data['user_id']; ?>" class="small">Saldos</a>
    <?php endif; ?>
    <?php if (!empty($selectedUser->clock_id)): ?>
    <span class="small text-muted"><i class="fas fa-fingerprint me-1"></i>Reloj: <?php echo htmlspecialchars($selectedUser->clock_id); ?></span>
    <?php else: ?>
    <span class="small text-warning"><i class="fas fa-unlink me-1"></i>Sin ID de reloj — <a href="<?php echo URLROOT; ?>/admin/mapeoApi">mapear</a></span>
    <?php endif; ?>
</div>

<div class="cal-legend mb-3">
    <span class="cal-legend-item"><i class="cal-dot cal-dot--ok"></i> OK</span>
    <span class="cal-legend-item"><i class="cal-dot cal-dot--late"></i> Tarde / alerta</span>
    <span class="cal-legend-item"><i class="cal-dot cal-dot--no_show"></i> Ausente</span>
    <span class="cal-legend-item"><i class="cal-dot" style="background:#0ea5e9"></i> Vacaciones</span>
    <span class="cal-legend-item"><i class="cal-dot" style="background:#1d4ed8"></i> Licencia</span>
    <span class="cal-legend-item"><i class="cal-dot cal-dot--justified"></i> Justificado</span>
    <span class="cal-legend-item"><i class="fas fa-gavel text-warning"></i> Incidencia</span>
    <span class="cal-legend-item"><i class="fas fa-exchange-alt" style="color:#7c3aed"></i> Cambio turno</span>
    <span class="cal-legend-item"><i class="cal-dot cal-dot--holiday"></i> Feriado</span>
</div>

<div class="cal-month-card">
    <div class="cal-month-nav">
        <a href="<?php echo URLROOT; ?>/admin/calendar?<?php echo $calQs(['month' => $calendar['prev_month']]); ?>" class="btn btn-sm btn-light border">
            <i class="fas fa-chevron-left"></i>
        </a>
        <h3 class="cal-month-title mb-0"><?php echo htmlspecialchars($calendar['month_label']); ?></h3>
        <a href="<?php echo URLROOT; ?>/admin/calendar?<?php echo $calQs(['month' => $calendar['next_month']]); ?>" class="btn btn-sm btn-light border">
            <i class="fas fa-chevron-right"></i>
        </a>
    </div>

    <?php if ($viewMode === 'list'): ?>
    <div class="cal-list-toolbar px-3 pt-2 pb-0 d-flex flex-wrap gap-2 align-items-center">
        <?php if ($listFilter === 'alerts'): ?>
        <a href="<?php echo URLROOT; ?>/admin/calendar?<?php echo $calQs(['filter' => '']); ?>" class="btn btn-sm btn-warning">
            <i class="fas fa-filter me-1"></i> Solo alertas (activo)
        </a>
        <?php else: ?>
        <a href="<?php echo URLROOT; ?>/admin/calendar?<?php echo $calQs(['filter' => 'alerts']); ?>" class="btn btn-sm btn-outline-warning">
            <i class="fas fa-filter me-1"></i> Ver solo alertas
        </a>
        <?php endif; ?>
        <span class="small text-muted ms-auto"><?php echo (int)$monthStats['days']; ?> días en el mes</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover cal-list-table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Resumen</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $weekdays = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
            foreach ($calendar['days_list'] as $day):
                if ($listFilter === 'alerts' && !calendarDayNeedsAttention($day)) {
                    continue;
                }
                $dow = $weekdays[(int)date('w', strtotime($day['date']))] ?? '';
                $rowClass = '';
                if (!empty($day['is_today'])) {
                    $rowClass = 'table-primary';
                } elseif (calendarDayNeedsAttention($day)) {
                    $rowClass = 'cal-list-row--alert';
                }
            ?>
            <tr class="<?php echo $rowClass; ?>">
                <td class="text-nowrap fw-semibold"><?php echo $dow . ' ' . date('d/m', strtotime($day['date'])); ?></td>
                <td class="cal-list-summary"><?php echo htmlspecialchars($day['context']['summary'] ?? ''); ?></td>
                <td><?php echo attendanceListStatusBadge($day); ?></td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary cal-day-open"
                            data-bs-toggle="offcanvas" data-bs-target="#calDayPanel"
                            data-date="<?php echo htmlspecialchars($day['date']); ?>"
                            data-day="<?php echo (int)$day['day']; ?>"
                            data-summary="<?php echo htmlspecialchars($day['context']['summary'] ?? ''); ?>">
                        Detalle
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="cal-grid">
        <div class="cal-weekday">Lun</div><div class="cal-weekday">Mar</div><div class="cal-weekday">Mié</div>
        <div class="cal-weekday">Jue</div><div class="cal-weekday">Vie</div><div class="cal-weekday">Sáb</div><div class="cal-weekday">Dom</div>
        <?php foreach ($calendar['weeks'] as $week): ?>
            <?php foreach ($week as $day): ?>
            <?php if (empty($day['in_month'])): ?>
            <div class="cal-cell cal-cell--empty"></div>
            <?php else:
                $cellClass = attendanceCellClass($day);
                if (!empty($day['incidents'])) {
                    $cellClass .= ' cal-cell--has-incident';
                }
                $ctx = $day['context'] ?? [];
                $att = $day['attendance'] ?? null;
                $just = $day['justification'] ?? null;
            ?>
            <button type="button" class="<?php echo $cellClass; ?> cal-day-open"
                    data-bs-toggle="offcanvas" data-bs-target="#calDayPanel"
                    data-date="<?php echo htmlspecialchars($day['date']); ?>"
                    data-day="<?php echo (int)$day['day']; ?>"
                    data-summary="<?php echo htmlspecialchars($ctx['summary'] ?? ''); ?>"
                    title="<?php echo htmlspecialchars($ctx['summary'] ?? ''); ?>">
                <span class="cal-cell-day"><?php echo (int)$day['day']; ?></span>
                <?php foreach ($ctx['chips'] ?? [] as $chip) {
                    echo calendarChipHtml($chip);
                } ?>
                <?php if ($just): ?>
                <span class="cal-cell-status cal-cell-status--justified"><i class="fas fa-file-medical"></i></span>
                <?php elseif (!empty($ctx['show_attendance_dot']) && $att): ?>
                <span class="cal-cell-status cal-cell-status--<?php echo htmlspecialchars($att->status); ?>"></span>
                <?php endif; ?>
            </button>
            <?php endif; endforeach; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="offcanvas offcanvas-end cal-offcanvas" tabindex="-1" id="calDayPanel" aria-labelledby="calDayPanelLabel">
    <div class="offcanvas-header border-bottom">
        <div>
            <h5 class="offcanvas-title mb-0" id="calDayPanelLabel">Detalle del día</h5>
            <p class="small text-muted mb-0" id="calDayPanelSubtitle"></p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body" id="calDayPanelBody">
        <p class="text-muted">Seleccioná un día en el calendario.</p>
    </div>
</div>

<div id="cal-day-templates" class="d-none">
<?php foreach ($calendar['days_list'] as $day): ?>
<div id="cal-tpl-<?php echo str_replace('-', '', $day['date']); ?>">
    <?php require APPROOT . '/views/admin/partials/calendar_day_detail.php'; ?>
</div>
<?php endforeach; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var panelEl = document.getElementById('calDayPanel');
    var panelBody = document.getElementById('calDayPanelBody');
    var panelTitle = document.getElementById('calDayPanelLabel');
    var panelSubtitle = document.getElementById('calDayPanelSubtitle');
    var employeeName = <?php echo json_script_safe($selectedUser->full_name ?? ''); ?>;

    function loadDayPanel(btn) {
        if (!btn) return;
        var date = btn.getAttribute('data-date');
        var day = btn.getAttribute('data-day');
        var summary = btn.getAttribute('data-summary') || '';
        var tpl = document.getElementById('cal-tpl-' + date.replace(/-/g, ''));
        panelTitle.textContent = 'Día ' + day;
        panelSubtitle.textContent = employeeName + (summary ? ' · ' + summary : '');
        panelBody.innerHTML = tpl ? tpl.innerHTML : '<p class="text-muted">Sin datos.</p>';
        if (typeof window.calBindJustifyForm === 'function') {
            window.calBindJustifyForm(panelBody);
        }
    }

    panelEl.addEventListener('show.bs.offcanvas', function(e) {
        var btn = e.relatedTarget;
        if (btn && btn.classList.contains('cal-day-open')) {
            loadDayPanel(btn);
        }
    });

    window.calBindJustifyForm = function(root) {
        var earlyTypes = ['early_leave_medical','early_leave_errand','early_leave_authorized'];
        root.querySelectorAll('.cal-justify-form').forEach(function(form) {
            var sel = form.querySelector('.cal-justify-type');
            var box = form.querySelector('.cal-leave-fields');
            if (!sel || !box) return;
            function toggle() {
                var show = earlyTypes.indexOf(sel.value) !== -1;
                box.style.display = show ? 'block' : 'none';
                var lt = box.querySelector('.cal-leave-time');
                if (lt) lt.required = show;
            }
            sel.addEventListener('change', toggle);
            toggle();
        });
    };

    <?php if ($openDay && preg_match('/^\d{4}-\d{2}-\d{2}$/', $openDay)): ?>
    var openBtn = document.querySelector('.cal-day-open[data-date="<?php echo htmlspecialchars($openDay); ?>"]');
    if (openBtn) {
        loadDayPanel(openBtn);
        bootstrap.Offcanvas.getOrCreateInstance(panelEl).show();
    }
    <?php endif; ?>
});
</script>

<?php else: ?>
<div class="alert alert-info">
    <i class="fas fa-user-clock me-1"></i>
    Seleccioná un empleado activo para ver su calendario unificado.
</div>
<?php endif; ?>

<?php require APPROOT . '/views/inc/footer.php'; ?>
