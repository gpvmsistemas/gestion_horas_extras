<?php
$roadmapCalendar = $data['roadmap_calendar'] ?? null;
$roadmapMonth = $data['roadmap_month'] ?? date('Y-m');
$roadmapStats = $data['roadmap_stats'] ?? null;
$userId = (int)$data['user']->id;
$profileQs = function ($extra = []) use ($roadmapMonth, $userId) {
    return http_build_query(array_merge(['roadmap_month' => $roadmapMonth], $extra));
};
?>
<div class="tab-pane fade show active" id="tab-roadmap">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="text-muted small mb-0">
            Mismo roadmap que <a href="<?php echo URLROOT; ?>/admin/calendar?month=<?php echo urlencode($roadmapMonth); ?>&user_id=<?php echo $userId; ?>">Calendario mensual</a>.
        </p>
        <div class="d-flex gap-2 align-items-center">
            <input type="month" class="form-control form-control-sm" value="<?php echo htmlspecialchars($roadmapMonth); ?>"
                   onchange="window.location='<?php echo URLROOT; ?>/admin/employeeProfile/<?php echo $userId; ?>?roadmap_month='+this.value+'#tab-roadmap'">
        </div>
    </div>

    <?php // Operador Ecofarma: concepto exclusivo de Paviotti — se decide por la
          // organización del EMPLEADO de la ficha, no por la del admin logueado.
          $_fichaEsModerna = function_exists('org_group_of_company')
              && org_group_of_company((int)($data['user']->company_id ?? 0)) === 'moderna'; ?>
    <?php if (!$_fichaEsModerna && !empty($data['ecofarma_commissions_url'])): ?>
    <div class="alert alert-light border small py-2 mb-3">
        <i class="fas fa-pills me-1 text-primary"></i>
        Operador Ecofarma: <strong><?php echo htmlspecialchars($data['user']->plex_operator_name); ?></strong>
        <a href="<?php echo htmlspecialchars($data['ecofarma_commissions_url']); ?>" class="ms-2">Ver comisiones</a>
    </div>
    <?php elseif (!$_fichaEsModerna && !empty($data['plex_operator_ready'])): ?>
    <p class="small text-muted mb-3">Vinculá el operador Ecofarma en <a href="<?php echo URLROOT; ?>/admin/editUser/<?php echo $userId; ?>">editar usuario</a>.</p>
    <?php endif; ?>

    <?php if (!$roadmapCalendar): ?>
    <div class="alert alert-warning small">No se pudo cargar el calendario. Verificá migraciones de asistencia.</div>
    <?php else: ?>

    <?php if ($roadmapStats): ?>
    <div class="cal-month-stats row g-2 mb-3">
        <div class="col-6 col-md-4 col-lg">
            <div class="cal-stat-card cal-stat-card--alert">
                <span class="cal-stat-value"><?php echo (int)$roadmapStats['alerts']; ?></span>
                <span class="cal-stat-label">Alertas</span>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <div class="cal-stat-card cal-stat-card--ok">
                <span class="cal-stat-value"><?php echo (int)$roadmapStats['ok']; ?></span>
                <span class="cal-stat-label">OK</span>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <div class="cal-stat-card cal-stat-card--vacation">
                <span class="cal-stat-value"><?php echo (int)$roadmapStats['vacation']; ?></span>
                <span class="cal-stat-label">Vacaciones</span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="cal-month-card">
        <div class="cal-month-nav mb-2">
            <?php
            $prev = $roadmapCalendar['prev_month'];
            $next = $roadmapCalendar['next_month'];
            ?>
            <a href="<?php echo URLROOT; ?>/admin/employeeProfile/<?php echo $userId; ?>?roadmap_month=<?php echo urlencode($prev); ?>#tab-roadmap" class="btn btn-sm btn-light border"><i class="fas fa-chevron-left"></i></a>
            <h3 class="cal-month-title mb-0 flex-grow-1 text-center"><?php echo htmlspecialchars($roadmapCalendar['month_label']); ?></h3>
            <a href="<?php echo URLROOT; ?>/admin/employeeProfile/<?php echo $userId; ?>?roadmap_month=<?php echo urlencode($next); ?>#tab-roadmap" class="btn btn-sm btn-light border"><i class="fas fa-chevron-right"></i></a>
        </div>
        <div class="cal-grid">
            <div class="cal-weekday">Lun</div><div class="cal-weekday">Mar</div><div class="cal-weekday">Mié</div>
            <div class="cal-weekday">Jue</div><div class="cal-weekday">Vie</div><div class="cal-weekday">Sáb</div><div class="cal-weekday">Dom</div>
            <?php foreach ($roadmapCalendar['weeks'] as $week): ?>
                <?php foreach ($week as $day): ?>
                <?php if (empty($day['in_month'])): ?>
                <div class="cal-cell cal-cell--empty"></div>
                <?php else:
                    $cellClass = attendanceCellClass($day);
                    $ctx = $day['context'] ?? [];
                ?>
                <a href="<?php echo URLROOT; ?>/admin/calendar?month=<?php echo urlencode($roadmapMonth); ?>&user_id=<?php echo $userId; ?>&day=<?php echo urlencode($day['date']); ?>"
                   class="<?php echo $cellClass; ?> text-decoration-none" title="<?php echo htmlspecialchars($ctx['summary'] ?? ''); ?>">
                    <span class="cal-cell-day"><?php echo (int)$day['day']; ?></span>
                    <?php foreach ($ctx['chips'] ?? [] as $chip) {
                        echo calendarChipHtml($chip);
                    } ?>
                </a>
                <?php endif; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
