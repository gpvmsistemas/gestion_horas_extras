<?php require APPROOT . '/views/inc/header.php'; ?>

<?php $_rhActive = 'horarios'; require APPROOT . '/views/registro_horas/partials/nav_tabs.php'; ?>
<?php require APPROOT . '/views/registro_horas/partials/mock_banner.php'; ?>

<?php
// ── Preparación de datos (maqueta): agrupar por sucursal → fecha, como view_hours de hoursapp ──
$weekdaysEs = [1=>'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
$byBranch = [];
$workedDays = [];
$holidayDays = [];
$totHours = 0; $totExtras = 0;
foreach ($data['records'] as $r) {
    $byBranch[$r->branch_name][$r->date][] = $r;
    $d = (int)date('j', strtotime($r->date));
    $workedDays[$d] = true;
    if ($r->is_holiday) $holidayDays[$d] = true;
    $totHours += $r->hours;
    $totExtras += $r->extra_hours;
}
$monthValue = $data['monthValue'] ?? date('Y-m');
$year = (int)substr($monthValue, 0, 4); $month = (int)substr($monthValue, 5, 2);
$firstOfMonth = sprintf('%04d-%02d-01', $year, $month);
$daysInMonth = (int)date('t', strtotime($firstOfMonth));
$firstDow = (int)date('N', strtotime($firstOfMonth)); // 1=Lun
$today = ($monthValue === date('Y-m')) ? (int)date('j') : 0;
$mesesEs = [1=>'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$monthLabel = $mesesEs[$month] . ' ' . $year;
?>

<style>
/* Calendario mensual del Registro de Horas (tokens del tema activo) */
.rh-cal { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
.rh-cal-head { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--clr-admin-muted); text-align: center; padding: .2rem 0; }
.rh-cal-day { min-height: 52px; border: 1px solid var(--clr-admin-border); border-radius: .55rem; padding: .3rem .45rem; font-size: .8rem; font-weight: 600; color: var(--clr-admin-text); background: #fff; }
.rh-cal-day.is-empty { border: none; background: transparent; }
.rh-cal-day.is-worked { background: var(--clr-primary-l); border-color: rgba(var(--clr-primary-rgb), .35); color: var(--clr-primary-d); }
.rh-cal-day.is-holiday { background: #fde2e2; border-color: rgba(239, 68, 68, .4); color: #b91c1c; }
.rh-cal-day.is-today { box-shadow: 0 0 0 2px var(--clr-warning); }
.rh-cal-day .rh-cal-hrs { display: block; font-size: .68rem; font-weight: 500; color: inherit; opacity: .8; }
.rh-legend { display: flex; flex-wrap: wrap; gap: 1rem; justify-content: center; font-size: .8rem; margin-top: .9rem; }
.rh-legend span i { font-size: .6rem; margin-right: .3rem; }
.rh-day-card { background: var(--page-bg); border-radius: .75rem; padding: .85rem 1rem; }
.rh-day-card.is-holiday { border-left: 4px solid var(--clr-danger); }
.rh-range-row { display: flex; align-items: center; justify-content: space-between; background: #fff; border: 1px solid var(--clr-admin-border); border-radius: .55rem; padding: .4rem .8rem; }
</style>

<section class="admin-surface">
    <div class="admin-surface-body">
        <?php if ($data['realMode']): ?>
        <form action="<?php echo URLROOT; ?>/registroHoras/horarios" method="get" class="row g-2 align-items-end">
        <?php else: ?>
        <form action="<?php echo URLROOT; ?>/registroHoras/mockSubmit" method="post" class="row g-2 align-items-end">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="mock_back" value="horarios">
        <?php endif; ?>
            <div class="col-md-5">
                <label class="form-label mb-1">Empleado</label>
                <select name="employee_id" class="form-select form-select-sm">
                    <?php foreach ($data['employees'] as $emp): ?>
                    <option value="<?php echo (int)$emp->id; ?>" <?php echo $emp->id === $data['selected']->id ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($emp->name); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1">Mes</label>
                <input type="month" name="month" class="form-control form-control-sm" value="<?php echo htmlspecialchars($monthValue); ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-search me-1"></i>Consultar</button>
            </div>
        </form>
    </div>
</section>

<?php if ($data['realMode'] && empty($data['records'])): ?>
<section class="admin-surface">
    <div class="admin-surface-body text-center text-muted py-5">
        <i class="far fa-calendar-times fs-3 d-block mb-2"></i>
        <?php echo htmlspecialchars($data['selected']->name); ?> no tiene horarios cargados en <?php echo htmlspecialchars($monthValue); ?>.
        <div class="mt-2"><a href="<?php echo URLROOT; ?>/registroHoras/carga" class="btn btn-sm btn-primary"><i class="fas fa-plus-circle me-1"></i>Cargar horario</a></div>
    </div>
</section>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <section class="admin-surface h-100">
            <div class="admin-surface-head">
                <div>
                    <h3 class="admin-surface-title"><i class="fas fa-calendar"></i>Calendario — <?php echo htmlspecialchars($monthLabel); ?></h3>
                    <p class="admin-surface-subtitle"><?php echo htmlspecialchars($data['selected']->name); ?></p>
                </div>
            </div>
            <div class="admin-surface-body">
                <div class="rh-cal">
                    <?php foreach (['Lu','Ma','Mi','Ju','Vi','Sá','Do'] as $dh): ?>
                    <div class="rh-cal-head"><?php echo $dh; ?></div>
                    <?php endforeach; ?>
                    <?php for ($i = 1; $i < $firstDow; $i++): ?>
                    <div class="rh-cal-day is-empty"></div>
                    <?php endfor; ?>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++):
                        $classes = [];
                        if (isset($holidayDays[$d]))      $classes[] = 'is-holiday';
                        elseif (isset($workedDays[$d]))   $classes[] = 'is-worked';
                        if ($d === $today)                $classes[] = 'is-today';
                        $dayHours = 0;
                        foreach ($data['records'] as $r) {
                            if ((int)date('j', strtotime($r->date)) === $d) $dayHours += $r->hours;
                        }
                    ?>
                    <div class="rh-cal-day <?php echo implode(' ', $classes); ?>">
                        <?php echo $d; ?>
                        <?php if ($dayHours > 0): ?><span class="rh-cal-hrs"><?php echo number_format($dayHours, 0); ?> hs</span><?php endif; ?>
                    </div>
                    <?php endfor; ?>
                </div>
                <div class="rh-legend text-muted">
                    <span><i class="fas fa-circle" style="color:var(--clr-primary)"></i>Días trabajados</span>
                    <span><i class="fas fa-circle" style="color:var(--clr-danger)"></i>Feriados / licencias</span>
                    <span><i class="fas fa-circle" style="color:var(--clr-warning)"></i>Hoy</span>
                </div>
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
            </div>
            <div class="admin-surface-body d-flex flex-column gap-4">
                <?php foreach ($byBranch as $branch => $dates): ?>
                <div>
                    <h6 class="fw-bold mb-3"><i class="fas fa-map-marker-alt me-1 text-muted"></i><?php echo htmlspecialchars($branch); ?></h6>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($dates as $date => $dayRecords):
                            $dowN = (int)date('N', strtotime($date));
                            $isHoliday = false; $dayTotal = 0; $dayExtras = 0;
                            foreach ($dayRecords as $r) { if ($r->is_holiday) $isHoliday = true; $dayTotal += $r->hours; $dayExtras += $r->extra_hours; }
                        ?>
                        <div class="rh-day-card <?php echo $isHoliday ? 'is-holiday' : ''; ?>">
                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                                <div class="fw-semibold">
                                    <?php echo date('d/m/Y', strtotime($date)); ?> — <?php echo $weekdaysEs[$dowN]; ?>
                                    <?php if ($isHoliday): ?><span class="badge bg-danger ms-1"><i class="fas fa-star me-1"></i>Feriado</span><?php endif; ?>
                                    <?php if ($dowN >= 6): ?><span class="badge bg-secondary ms-1">Fin de semana</span><?php endif; ?>
                                </div>
                                <div class="fw-bold">
                                    Total: <?php echo number_format($dayTotal, 0); ?> hs
                                    <?php if ($dayExtras > 0): ?><span class="text-warning ms-1">(+<?php echo number_format($dayExtras, 0); ?> extra)</span><?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($dayRecords as $r): ?>
                                <div class="rh-range-row">
                                    <span><i class="far fa-clock me-2 text-muted"></i><?php echo $r->start_time; ?> – <?php echo $r->end_time; ?></span>
                                    <span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Disponible al integrar datos"><i class="fas fa-pen"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" disabled title="Disponible al integrar datos"><i class="fas fa-trash"></i></button>
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
            Total de horas trabajadas del mes: <?php echo number_format($totHours, 0, ',', '.'); ?> hs
        </div>
        <div class="text-muted">
            Extras: <span class="fw-semibold text-warning"><?php echo number_format($totExtras, 0, ',', '.'); ?> hs</span>
            <?php echo $data['org'] === 'moderna' ? '(umbral diario 8/5 + feriados)' : '(clasificación 50/100 de la suite)'; ?>
        </div>
    </div>
</section>

<?php require APPROOT . '/views/inc/footer.php'; ?>
