<?php require APPROOT . '/views/inc/header.php';
$calendar = $data['calendar'] ?? null;
$month = $data['month'];
$userId = (int)$_SESSION['user_id'];
?>

<div class="container py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h4 mb-1">Mi mes</h2>
            <p class="text-muted small mb-0">Turnos, fichadas, licencias y vacaciones del mes</p>
        </div>
        <form method="get" action="<?php echo URLROOT; ?>/employee/miMes" class="d-flex gap-2">
            <input type="month" name="month" class="form-control form-control-sm" value="<?php echo htmlspecialchars($month); ?>">
            <button type="submit" class="btn btn-primary btn-sm">Ver</button>
        </form>
    </div>

    <?php if ($data['vacation_pending'] !== null && function_exists('employee_portal_can') && employee_portal_can('vacation_balance')): ?>
    <div class="alert alert-info py-2 small mb-3">
        Vacaciones pendientes: <strong><?php echo vacation_format_days($data['vacation_pending']); ?></strong>
        — <a href="<?php echo URLROOT; ?>/request/index">Solicitar licencia/vacaciones</a>
        · <a href="<?php echo htmlspecialchars(vacation_planilla_employee_url()); ?>" target="_blank" rel="noopener">Imprimir planilla</a>
    </div>
    <?php endif; ?>

    <?php if (!$calendar): ?>
    <div class="alert alert-warning">No hay datos de calendario para este mes.</div>
    <?php else: ?>

    <?php if (!empty($data['month_stats'])): $st = $data['month_stats']; ?>
    <div class="row g-2 mb-3">
        <div class="col-4"><div class="border rounded p-2 text-center"><div class="fw-bold text-success"><?php echo (int)$st['ok']; ?></div><div class="small text-muted">OK</div></div></div>
        <div class="col-4"><div class="border rounded p-2 text-center"><div class="fw-bold text-warning"><?php echo (int)$st['alerts']; ?></div><div class="small text-muted">Alertas</div></div></div>
        <div class="col-4"><div class="border rounded p-2 text-center"><div class="fw-bold text-primary"><?php echo (int)$st['vacation']; ?></div><div class="small text-muted">Vacaciones</div></div></div>
    </div>
    <?php endif; ?>

    <div class="cal-month-card">
        <div class="d-flex justify-content-between align-items-center mb-2 px-2">
            <a href="<?php echo URLROOT; ?>/employee/miMes?month=<?php echo urlencode($calendar['prev_month']); ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-left"></i></a>
            <strong><?php echo htmlspecialchars($calendar['month_label']); ?></strong>
            <a href="<?php echo URLROOT; ?>/employee/miMes?month=<?php echo urlencode($calendar['next_month']); ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-right"></i></a>
        </div>
        <div class="cal-grid">
            <div class="cal-weekday">Lun</div><div class="cal-weekday">Mar</div><div class="cal-weekday">Mié</div>
            <div class="cal-weekday">Jue</div><div class="cal-weekday">Vie</div><div class="cal-weekday">Sáb</div><div class="cal-weekday">Dom</div>
            <?php foreach ($calendar['weeks'] as $week): ?>
                <?php foreach ($week as $day): ?>
                <?php if (empty($day['in_month'])): ?>
                <div class="cal-cell cal-cell--empty"></div>
                <?php else:
                    $cellClass = attendanceCellClass($day);
                    $ctx = $day['context'] ?? [];
                ?>
                <div class="<?php echo $cellClass; ?>" title="<?php echo htmlspecialchars($ctx['summary'] ?? ''); ?>">
                    <span class="cal-cell-day"><?php echo (int)$day['day']; ?></span>
                    <?php foreach ($ctx['chips'] ?? [] as $chip) {
                        echo calendarChipHtml($chip);
                    } ?>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <p class="small text-muted mt-3 mb-0">
        ¿Necesitás corregir algo? <a href="<?php echo URLROOT; ?>/request/index">Hacé una solicitud</a> o consultá con RRHH.
    </p>
    <?php endif; ?>
</div>

<?php require APPROOT . '/views/inc/footer.php'; ?>
