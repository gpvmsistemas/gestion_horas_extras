<?php
require APPROOT . '/views/inc/header.php';
$period = (string)($data['period_label'] ?? vacation_default_target_period_label());
$periodOptions = $data['period_options'] ?? vacation_target_period_options();
$preview = $data['preview'] ?? ['rows' => [], 'stats' => []];
$stats = $preview['stats'] ?? [];
$rows = $preview['rows'] ?? [];
$filter = (string)($data['filter'] ?? 'all');
$report = $data['report'] ?? null;
$companyId = (int)($data['company_id'] ?? 0);
$nextYear = (string)((int)date('Y') + 1);
$toCreate = (int)($stats['to_create'] ?? $stats['ready'] ?? 0);
$alreadyLiq = (int)($stats['already_liquidated'] ?? 0);
$canCreate = $toCreate > 0;
$canRecalc = $alreadyLiq > 0 || $canCreate;
$statusLabels = [
    'ready' => ['Listo', 'success'],
    'exists' => ['Liquidado', 'secondary'],
    'exists_with_takes' => ['Liquidado · tomas', 'info'],
    'missing_hire' => ['Sin ingreso', 'warning'],
    'missing_agreement' => ['Sin convenio', 'warning'],
    'blocked' => ['Bloqueado', 'danger'],
];
$filteredRows = array_values(array_filter($rows, static function ($r) use ($filter) {
    $st = $r['status'] ?? '';
    if ($filter === 'ready') {
        return $st === 'ready';
    }
    if ($filter === 'blocked') {
        return in_array($st, ['missing_hire', 'missing_agreement', 'blocked'], true);
    }
    if ($filter === 'liquidated') {
        return in_array($st, ['exists', 'exists_with_takes'], true);
    }
    return true;
}));
$panelQs = static function (array $overrides = []) use ($period, $filter) {
    $q = array_merge([
        'period' => $period,
        'filter' => $filter,
    ], $overrides);
    $clean = [];
    foreach ($q as $k => $v) {
        if ($v === null || $v === '' || ($k === 'filter' && $v === 'all')) {
            continue;
        }
        $clean[$k] = $v;
    }
    $qs = http_build_query($clean);
    return URLROOT . '/vacationAdmin/panel' . ($qs !== '' ? '?' . $qs : '');
};
?>

<div class="admin-page-head mb-4">
    <div class="admin-page-brand">
        <div class="admin-page-icon"><i class="fas fa-umbrella-beach"></i></div>
        <div class="admin-page-meta">
            <h2 class="page-title mb-0">Vacaciones</h2>
            <p class="page-subtitle mb-0">
                <?php echo htmlspecialchars($data['company_name'] ?? ''); ?>
                · liquidar períodos y abrir el año siguiente según convenio
            </p>
        </div>
    </div>
    <div class="admin-page-actions d-flex flex-wrap gap-2">
        <a href="<?php echo URLROOT; ?>/vacationAdmin/agreements" class="btn btn-outline-secondary btn-sm">Convenios</a>
        <a href="<?php echo URLROOT; ?>/vacationAdmin/reports<?php echo $companyId > 0 ? '?company_id=' . $companyId : ''; ?>" class="btn btn-outline-secondary btn-sm">Saldos</a>
        <a href="<?php echo URLROOT; ?>/vacationAdmin/tomadas<?php echo $companyId > 0 ? '?company_id=' . $companyId : ''; ?>" class="btn btn-outline-secondary btn-sm">Tomadas</a>
    </div>
</div>

<div class="alert alert-light border small mb-4">
    <strong>Cómo se calculan los días</strong>
    Antigüedad solo con <code>hire_date</code> al <strong>31 de diciembre</strong> del año del período.
    Los días salen de la escala del <strong>convenio efectivo</strong> (empleado → área → default de empresa).
    Período calendario: 1 ene – 31 dic.
</div>

<form method="get" action="<?php echo URLROOT; ?>/vacationAdmin/panel" class="card border shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Período objetivo</label>
                <select name="period" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($periodOptions as $opt): ?>
                    <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $period === $opt ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($opt); ?>
                        <?php if ($opt === $nextYear): ?> (año siguiente)<?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Filtro</label>
                <select name="filter" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Todos</option>
                    <option value="ready" <?php echo $filter === 'ready' ? 'selected' : ''; ?>>Solo listos (sin liquidar)</option>
                    <option value="liquidated" <?php echo $filter === 'liquidated' ? 'selected' : ''; ?>>Ya liquidados</option>
                    <option value="blocked" <?php echo $filter === 'blocked' ? 'selected' : ''; ?>>Bloqueados</option>
                </select>
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-outline-primary btn-sm">Actualizar preview</button>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars($panelQs(['period' => $nextYear, 'filter' => 'all'])); ?>">
                    Abrir vista <?php echo htmlspecialchars($nextYear); ?>
                </a>
            </div>
        </div>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-2"><div class="admin-kpi-card"><div><div class="admin-kpi-value"><?php echo (int)($stats['total_active'] ?? 0); ?></div><div class="admin-kpi-label">Activos</div></div></div></div>
    <div class="col-6 col-md-2"><div class="admin-kpi-card"><div><div class="admin-kpi-value text-success"><?php echo (int)($stats['to_create'] ?? $stats['ready'] ?? 0); ?></div><div class="admin-kpi-label">A crear</div></div></div></div>
    <div class="col-6 col-md-2"><div class="admin-kpi-card"><div><div class="admin-kpi-value"><?php echo (int)($stats['already_liquidated'] ?? 0); ?></div><div class="admin-kpi-label">Ya liquidados</div></div></div></div>
    <div class="col-6 col-md-2"><div class="admin-kpi-card"><div><div class="admin-kpi-value text-warning"><?php echo (int)($stats['no_hire'] ?? 0); ?></div><div class="admin-kpi-label">Sin ingreso</div></div></div></div>
    <div class="col-6 col-md-2"><div class="admin-kpi-card"><div><div class="admin-kpi-value text-warning"><?php echo (int)($stats['no_agreement'] ?? 0); ?></div><div class="admin-kpi-label">Sin convenio</div></div></div></div>
    <div class="col-6 col-md-2"><div class="admin-kpi-card"><div><div class="admin-kpi-value"><?php echo vacation_format_days($stats['days_total'] ?? 0); ?></div><div class="admin-kpi-label">Días del preview</div></div></div></div>
</div>

<div class="card border shadow-sm mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <strong>Liquidar período <?php echo htmlspecialchars($period); ?></strong>
        <span class="small text-muted">Solo empleados activos · fechas 01/01–31/12 del año</span>
    </div>
    <div class="card-body">
        <form method="post" action="<?php echo URLROOT; ?>/vacationAdmin/panel" class="row g-3 align-items-end"
              onsubmit="return confirm('¿Liquidar el período <?php echo htmlspecialchars($period, ENT_QUOTES); ?> para los empleados seleccionados?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="period_label" value="<?php echo htmlspecialchars($period); ?>">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Alcance</label>
                <select name="only_missing" id="vacOnlyMissing" class="form-select">
                    <option value="1" selected>Solo faltantes (recomendado)</option>
                    <option value="0">Recalcular existentes (corrige fechas/días; conserva tomas)</option>
                </select>
            </div>
            <div class="col-md-8 d-flex flex-wrap gap-2 align-items-center">
                <button type="submit" name="action" value="liquidate" id="vacLiqBtn" class="btn btn-primary"
                    data-can-create="<?php echo $canCreate ? '1' : '0'; ?>"
                    data-can-recalc="<?php echo $canRecalc ? '1' : '0'; ?>"
                    <?php echo $canCreate ? '' : 'disabled'; ?>>
                    <i class="fas fa-bolt me-1"></i>Liquidar período <?php echo htmlspecialchars($period); ?>
                </button>
                <?php if ($period !== $nextYear): ?>
                <a class="btn btn-success" href="<?php echo htmlspecialchars($panelQs(['period' => $nextYear])); ?>">
                    <i class="fas fa-calendar-plus me-1"></i>Preparar <?php echo htmlspecialchars($nextYear); ?>
                </a>
                <?php endif; ?>
                <?php if (!$canCreate && !$canRecalc): ?>
                <span class="small text-muted">No hay empleados liquidables (faltan ingreso/convenio o no hay activos).</span>
                <?php elseif (!$canCreate): ?>
                <span class="small text-muted" id="vacLiqHint">No hay faltantes: cambiá a «Recalcular» solo si necesitás corregir fechas/días.</span>
                <?php endif; ?>
            </div>
        </form>
        <script>
        (function () {
            var sel = document.getElementById('vacOnlyMissing');
            var btn = document.getElementById('vacLiqBtn');
            if (!sel || !btn) return;
            var sync = function () {
                var onlyMissing = sel.value === '1';
                var ok = onlyMissing ? btn.getAttribute('data-can-create') === '1' : btn.getAttribute('data-can-recalc') === '1';
                btn.disabled = !ok;
            };
            sel.addEventListener('change', sync);
            sync();
        })();
        </script>
    </div>
</div>

<?php if ($report && !empty($report['details'])): ?>
<div class="card border shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Resultado — <?php echo htmlspecialchars($report['period_label'] ?? ''); ?></strong>
        <span>
            <span class="badge bg-success"><?php echo (int)($report['liquidated'] ?? 0); ?> OK</span>
            <span class="badge bg-secondary"><?php echo (int)($report['skipped'] ?? 0); ?> omitidos</span>
            <?php if ((int)($report['failed'] ?? 0) > 0): ?>
            <span class="badge bg-danger"><?php echo (int)$report['failed']; ?> errores</span>
            <?php endif; ?>
        </span>
    </div>
    <div class="table-responsive" style="max-height:280px;overflow:auto;">
        <table class="table table-sm mb-0">
            <thead class="table-light sticky-top"><tr><th>Empleado</th><th>Estado</th><th>Detalle</th></tr></thead>
            <tbody>
            <?php foreach ($report['details'] as $r): ?>
            <tr>
                <td><?php echo htmlspecialchars($r['name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($r['status'] ?? ''); ?></td>
                <td class="small"><?php echo htmlspecialchars($r['message'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card border shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Roster · <?php echo htmlspecialchars($period); ?></strong>
        <span class="small text-muted"><?php echo count($filteredRows); ?> fila(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Empleado</th>
                    <th>Convenio</th>
                    <th class="text-end">Antigüedad</th>
                    <th>Regla</th>
                    <th class="text-end">Días</th>
                    <th>Conteo</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$filteredRows): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Sin empleados para este filtro.</td></tr>
            <?php else: foreach ($filteredRows as $row):
                $st = $row['status'] ?? '';
                $meta = $statusLabels[$st] ?? ['—', 'secondary'];
            ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                    <?php if (!empty($row['hire_date'])): ?>
                    <div class="small text-muted">Ingreso <?php echo date('d/m/Y', strtotime($row['hire_date'])); ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($row['agreement_code'])): ?>
                    <code><?php echo htmlspecialchars($row['agreement_code']); ?></code>
                    <div class="small text-muted"><?php echo htmlspecialchars($row['agreement_name'] ?? ''); ?></div>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-end"><?php echo $row['seniority_months'] !== null ? (int)$row['seniority_months'] . ' m' : '—'; ?></td>
                <td class="small"><?php echo htmlspecialchars($row['rule_range'] ?? '—'); ?><?php if (!empty($row['rule_notes'])): ?><div class="text-muted"><?php echo htmlspecialchars($row['rule_notes']); ?></div><?php endif; ?></td>
                <td class="text-end"><strong><?php echo $row['days_entitled'] !== null ? vacation_format_days($row['days_entitled']) : '—'; ?></strong><?php if ($row['existing_entitled'] !== null): ?><div class="small text-muted">actual <?php echo vacation_format_days($row['existing_entitled']); ?></div><?php endif; ?></td>
                <td class="small"><?php echo htmlspecialchars($row['day_count_mode_label'] ?? '—'); ?></td>
                <td>
                    <span class="badge bg-<?php echo $meta[1]; ?>"><?php echo htmlspecialchars($meta[0]); ?></span>
                    <?php if (!empty($row['message'])): ?><div class="small text-muted"><?php echo htmlspecialchars($row['message']); ?></div><?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
                    <?php if (in_array($st, ['ready', 'exists', 'exists_with_takes'], true)): ?>
                    <form method="post" action="<?php echo URLROOT; ?>/vacationAdmin/liquidateUser/<?php echo (int)$row['user_id']; ?>" class="d-inline"
                          onsubmit="return confirm('¿Liquidar <?php echo htmlspecialchars($period, ENT_QUOTES); ?> para <?php echo htmlspecialchars($row['full_name'], ENT_QUOTES); ?>?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="period_label" value="<?php echo htmlspecialchars($period); ?>">
                        <input type="hidden" name="return_to" value="panel">
                        <input type="hidden" name="return_period" value="<?php echo htmlspecialchars($period); ?>">
                        <button type="submit" class="btn btn-sm btn-success" title="Liquidar"><i class="fas fa-bolt"></i></button>
                    </form>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?php echo URLROOT; ?>/vacationAdmin/vacationSetup/<?php echo (int)$row['user_id']; ?>" title="Carga"><i class="fas fa-edit"></i></a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars(vacation_planilla_staff_url((int)$row['user_id'])); ?>" target="_blank" rel="noopener" title="Planilla"><i class="fas fa-print"></i></a>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require APPROOT . '/views/inc/footer.php'; ?>
