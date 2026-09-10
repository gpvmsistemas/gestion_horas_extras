<?php
require APPROOT . '/views/inc/header.php';
$filters = $data['filters'];
$report = $data['report'];
$stats = $data['stats'];
$currentPeriod = (string)($data['current_period'] ?? date('Y'));
$query = $filters;
unset($query['page'], $query['export'], $query['company_ids']);
$reportUrl = static function (array $overrides = []) use ($filters) {
    $query = array_merge($filters, $overrides);
    unset($query['export'], $query['company_ids']);
    $clean = [];
    foreach ($query as $key => $value) {
        if (is_bool($value)) {
            if ($value) {
                $clean[$key] = 1;
            }
            continue;
        }
        $value = is_scalar($value) ? trim((string)$value) : '';
        if ($value === '' || ($value === '0' && in_array($key, ['company_id', 'agreement_id', 'area_id'], true))) {
            continue;
        }
        if ($key === 'balance_status' && $value === 'both') {
            continue;
        }
        if ($key === 'page' && (int)$value <= 1) {
            continue;
        }
        if ($key === 'per_page' && (int)$value === 50) {
            continue;
        }
        $clean[$key] = $value;
    }
    $qs = http_build_query($clean);
    return URLROOT . '/vacationAdmin/reports' . ($qs !== '' ? '?' . $qs : '');
};
$csvUrl = URLROOT . '/vacationAdmin/exportVacationBalancesCsv?' . http_build_query(array_filter($query, static function ($v, $k) {
    if ($k === 'page' || $k === 'per_page') {
        return false;
    }
    if (is_bool($v)) {
        return $v;
    }
    return $v !== '' && $v !== null;
}, ARRAY_FILTER_USE_BOTH));
$returnQuery = http_build_query(array_filter($query, static function ($v) {
    if (is_bool($v)) {
        return $v;
    }
    return $v !== '' && $v !== null && $v !== 0 && $v !== '0';
}));
$focus = 'all';
if (!empty($filters['no_hire'])) {
    $focus = 'no_hire';
} elseif (!empty($filters['no_agreement'])) {
    $focus = 'no_agreement';
} elseif (!empty($filters['no_liquidation'])) {
    $focus = 'no_liquidation';
} elseif (!empty($filters['expiring_only'])) {
    $focus = 'expiring';
} elseif (!empty($filters['historical_only'])) {
    $focus = 'historical';
} elseif (($filters['balance_status'] ?? 'both') === 'with') {
    $focus = 'with';
} elseif (($filters['balance_status'] ?? 'both') === 'without') {
    $focus = 'without';
}
$resetFocus = [
    'balance_status' => 'both',
    'no_agreement' => false,
    'no_liquidation' => false,
    'no_hire' => false,
    'historical_only' => false,
    'expiring_only' => false,
    'page' => 1,
];
$chipFilters = [
    'all' => ['Todos', $resetFocus],
    'with' => ['Con saldo', array_merge($resetFocus, ['balance_status' => 'with'])],
    'without' => ['Sin saldo', array_merge($resetFocus, ['balance_status' => 'without'])],
    'no_liquidation' => ['Sin liquidar ' . $currentPeriod, array_merge($resetFocus, ['no_liquidation' => true])],
    'no_agreement' => ['Sin convenio', array_merge($resetFocus, ['no_agreement' => true])],
    'no_hire' => ['Sin ingreso', array_merge($resetFocus, ['no_hire' => true])],
    'expiring' => ['Por vencer', array_merge($resetFocus, ['expiring_only' => true])],
    'historical' => ['Históricos', array_merge($resetFocus, ['historical_only' => true])],
];
$typeLabels = ['annual'=>'Anual', 'historical'=>'Histórico', 'conventional_credit'=>'Crédito convencional'];
$modeLabels = function_exists('vacation_day_count_modes') ? vacation_day_count_modes() : [];
$batchCompany = (int)$filters['company_id'] > 0 ? (int)$filters['company_id'] : (int)adminCompanyId();
?>

<div class="admin-page-head">
    <div class="admin-page-brand">
        <div class="admin-page-icon"><i class="fas fa-umbrella-beach"></i></div>
        <div class="admin-page-meta">
            <h1 class="page-title">Vacaciones</h1>
            <p class="page-subtitle mb-0">Nómina completa: saldos, liquidación del período y carga por empleado.</p>
        </div>
    </div>
    <div class="admin-page-actions d-flex flex-wrap gap-2">
        <a href="<?php echo URLROOT; ?>/vacationAdmin/tomadas" class="btn btn-outline-primary btn-sm"><i class="fas fa-plane-departure me-1"></i>Tomadas</a>
        <a href="<?php echo URLROOT; ?>/vacationAdmin/panel" class="btn btn-success btn-sm" title="Panel de vacaciones de la empresa activa"><i class="fas fa-bolt me-1"></i>Panel vacaciones</a>
        <a href="<?php echo htmlspecialchars($csvUrl); ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-csv me-1"></i>CSV</a>
    </div>
</div>

<div class="admin-kpi-grid vac-report-kpi-grid mb-3">
    <a class="admin-kpi-card vac-report-kpi<?php echo $focus === 'all' ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($reportUrl($resetFocus)); ?>">
        <div class="vac-report-kpi-mark"></div><div><div class="admin-kpi-value"><?php echo (int)$stats['employees_total']; ?></div><div class="admin-kpi-label">Personas</div></div>
    </a>
    <a class="admin-kpi-card vac-report-kpi<?php echo $focus === 'with' ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($reportUrl(array_merge($resetFocus, ['balance_status' => 'with']))); ?>">
        <div class="vac-report-kpi-mark"></div><div><div class="admin-kpi-value"><?php echo (int)$stats['employees_with_pending']; ?></div><div class="admin-kpi-label">Con saldo</div></div>
    </a>
    <div class="admin-kpi-card vac-report-kpi"><div class="vac-report-kpi-mark"></div><div><div class="admin-kpi-value"><?php echo vacation_format_days($stats['total_pending']); ?></div><div class="admin-kpi-label">Días pendientes</div></div></div>
    <a class="admin-kpi-card vac-report-kpi<?php echo $focus === 'no_liquidation' ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($reportUrl(array_merge($resetFocus, ['no_liquidation' => true]))); ?>">
        <div class="vac-report-kpi-mark"></div><div><div class="admin-kpi-value"><?php echo (int)$stats['without_current_liquidation']; ?></div><div class="admin-kpi-label">Sin liquidar <?php echo htmlspecialchars($currentPeriod); ?></div></div>
    </a>
    <a class="admin-kpi-card vac-report-kpi<?php echo $focus === 'no_agreement' ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($reportUrl(array_merge($resetFocus, ['no_agreement' => true]))); ?>">
        <div class="vac-report-kpi-mark"></div><div><div class="admin-kpi-value"><?php echo (int)$stats['without_agreement']; ?></div><div class="admin-kpi-label">Sin convenio</div></div>
    </a>
    <a class="admin-kpi-card vac-report-kpi<?php echo $focus === 'no_hire' ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($reportUrl(array_merge($resetFocus, ['no_hire' => true]))); ?>">
        <div class="vac-report-kpi-mark"></div><div><div class="admin-kpi-value"><?php echo (int)$stats['without_hire_date']; ?></div><div class="admin-kpi-label">Sin ingreso</div></div>
    </a>
    <a class="admin-kpi-card vac-report-kpi<?php echo $focus === 'expiring' ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($reportUrl(array_merge($resetFocus, ['expiring_only' => true]))); ?>">
        <div class="vac-report-kpi-mark"></div><div><div class="admin-kpi-value"><?php echo (int)$stats['expiring_credits']; ?></div><div class="admin-kpi-label">Créditos por vencer</div></div>
    </a>
</div>

<form method="get" action="<?php echo URLROOT; ?>/vacationAdmin/reports" class="admin-surface vac-report-filter mb-3">
    <div class="admin-surface-head"><div><span class="admin-section-eyebrow">Consulta</span><h2 class="admin-surface-title mb-0"><i class="fas fa-sliders-h"></i> Filtros</h2></div><span class="small text-muted">Por defecto se lista toda la nómina activa</span></div>
    <div class="admin-surface-body">
        <div class="vac-report-company-row mb-3">
            <span class="admin-toolbar-label"><i class="fas fa-building me-1"></i>Empresa</span>
            <div class="admin-filter-group">
                <a href="<?php echo htmlspecialchars($reportUrl(['company_id' => 0, 'page' => 1])); ?>" class="admin-filter-chip<?php echo (int)$filters['company_id'] === 0 ? ' active' : ''; ?>">Todas</a>
                <?php foreach ($data['companies'] as $co): ?>
                <a href="<?php echo htmlspecialchars($reportUrl(['company_id' => (int)$co->id, 'page' => 1])); ?>" class="admin-filter-chip<?php echo (int)$filters['company_id'] === (int)$co->id ? ' active' : ''; ?>"><?php echo htmlspecialchars($co->name); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="admin-filter-group vac-report-focus mb-3" aria-label="Vista rápida">
            <?php foreach ($chipFilters as $key => $chip): ?>
            <a href="<?php echo htmlspecialchars($reportUrl($chip[1])); ?>" class="admin-filter-chip<?php echo $focus === $key ? ' active' : ''; ?>"><?php echo htmlspecialchars($chip[0]); ?></a>
            <?php endforeach; ?>
        </div>
        <div class="row g-2">
            <div class="col-md-3"><label class="form-label small">Convenio</label><select name="agreement_id" class="form-select form-select-sm"><option value="0">Todos</option><?php foreach ($data['agreements'] as $ag): ?><option value="<?php echo (int)$ag->id; ?>" <?php echo (int)$filters['agreement_id']===(int)$ag->id?'selected':''; ?>><?php echo htmlspecialchars($ag->name); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label small">Área</label><select name="area_id" class="form-select form-select-sm"><option value="0">Todas</option><?php foreach ($data['areas'] as $area): ?><option value="<?php echo (int)$area->id; ?>" <?php echo (int)$filters['area_id']===(int)$area->id?'selected':''; ?>><?php echo htmlspecialchars($area->name); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label small">Empleado / DNI / CUIL</label><input name="search" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filters['search']); ?>"></div>
            <div class="col-md-3"><label class="form-label small">Período</label><input name="period" class="form-control form-control-sm" placeholder="<?php echo htmlspecialchars($currentPeriod); ?>" value="<?php echo htmlspecialchars($filters['period']); ?>"></div>
            <input type="hidden" name="company_id" value="<?php echo (int)$filters['company_id']; ?>">
            <div class="col-md-2"><label class="form-label small">Tipo de saldo</label><select name="balance_type" class="form-select form-select-sm"><option value="">Todos</option><?php foreach ($typeLabels as $key=>$label): ?><option value="<?php echo $key; ?>" <?php echo $filters['balance_type']===$key?'selected':''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label small">Empleados</label><select name="active" class="form-select form-select-sm"><option value="active" <?php echo $filters['active']==='active'?'selected':''; ?>>Solo activos</option><option value="inactive" <?php echo $filters['active']==='inactive'?'selected':''; ?>>Solo inactivos</option><option value="all" <?php echo $filters['active']==='all'?'selected':''; ?>>Todos</option></select></div>
            <div class="col-md-2"><label class="form-label small">Saldo</label><select name="balance_status" class="form-select form-select-sm"><option value="both" <?php echo $filters['balance_status']==='both'?'selected':''; ?>>Toda la nómina</option><option value="with" <?php echo $filters['balance_status']==='with'?'selected':''; ?>>Con saldo</option><option value="without" <?php echo $filters['balance_status']==='without'?'selected':''; ?>>Sin saldo</option></select></div>
            <div class="col-md-2"><label class="form-label small">Mínimo días</label><input type="number" step="0.5" min="0" name="min_days" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string)$filters['min_days']); ?>"></div>
            <div class="col-md-2"><label class="form-label small">Máximo días</label><input type="number" step="0.5" min="0" name="max_days" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string)$filters['max_days']); ?>"></div>
            <div class="col-md-2"><label class="form-label small">Orden</label><select name="sort" class="form-select form-select-sm"><?php foreach (['pending_desc'=>'Mayor saldo primero','pending_asc'=>'Menor saldo primero','name'=>'Empleado','company'=>'Empresa','agreement'=>'Convenio','oldest'=>'Período más antiguo','expiry'=>'Vencimiento más próximo'] as $key=>$label): ?><option value="<?php echo $key; ?>" <?php echo $filters['sort']===$key?'selected':''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div>
            <div class="col-md-12 d-flex flex-wrap align-items-center gap-3">
                <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="historical_only" value="1" id="histOnly" <?php echo $filters['historical_only']?'checked':''; ?>><label class="form-check-label small" for="histOnly">Solo anteriores</label></div>
                <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="expiring_only" value="1" id="expOnly" <?php echo $filters['expiring_only']?'checked':''; ?>><label class="form-check-label small" for="expOnly">Por vencer (90 días)</label></div>
                <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="no_liquidation" value="1" id="noLiq" <?php echo !empty($filters['no_liquidation'])?'checked':''; ?>><label class="form-check-label small" for="noLiq">Sin liquidar <?php echo htmlspecialchars($currentPeriod); ?></label></div>
                <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="no_agreement" value="1" id="noAgr" <?php echo !empty($filters['no_agreement'])?'checked':''; ?>><label class="form-check-label small" for="noAgr">Sin convenio</label></div>
                <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="no_hire" value="1" id="noHire" <?php echo !empty($filters['no_hire'])?'checked':''; ?>><label class="form-check-label small" for="noHire">Sin fecha de ingreso</label></div>
                <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-filter me-1"></i>Aplicar</button>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo URLROOT; ?>/vacationAdmin/reports">Limpiar</a>
            </div>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <?php foreach ([['Por empresa',$stats['by_company']],['Por convenio',$stats['by_agreement']]] as $group): ?>
    <div class="col-lg-6"><div class="admin-surface vac-report-summary h-100"><div class="admin-surface-head"><h2 class="admin-surface-title mb-0"><i class="fas fa-chart-pie"></i> <?php echo $group[0]; ?></h2></div><div class="admin-surface-body py-2"><?php if (empty($group[1])): ?><p class="small text-muted mb-0">Sin datos para el contexto actual.</p><?php endif; ?><?php foreach ($group[1] as $label=>$days): ?><div class="vac-report-summary-row"><span><?php echo htmlspecialchars($label); ?></span><strong><?php echo vacation_format_days($days); ?> días</strong></div><?php endforeach; ?></div></div></div>
    <?php endforeach; ?>
</div>

<div class="admin-surface vac-report-results">
    <div class="admin-surface-head d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="admin-surface-title mb-0"><i class="fas fa-users"></i> <?php echo (int)$report['total']; ?> empleado(s)</h2>
        <span class="badge bg-light text-dark border">Página <?php echo (int)$report['page']; ?></span>
    </div>
    <div class="table-responsive"><table class="table table-hover mb-0 align-middle vac-report-table"><thead class="table-light"><tr><th>Empleado</th><th>Empresa / área</th><th>Convenio</th><th>Estado</th><th class="text-end">Histórico</th><th class="text-end">Actual</th><th class="text-end">Total</th><th></th></tr></thead><tbody>
    <?php if (empty($report['rows'])): ?><tr><td colspan="8" class="text-center text-muted py-4">No hay resultados para los filtros elegidos.</td></tr><?php endif; ?>
    <?php foreach ($report['rows'] as $row):
        $details = $report['details'][(int)$row->user_id] ?? [];
        $pending = (float)$row->total_pending;
        $hasHire = !empty($row->hire_date);
        $hasAgreement = !empty($row->agreement_name);
        $hasLiq = !empty($row->has_current_liquidation);
    ?>
    <tr class="<?php echo $pending <= 0 ? 'vac-report-row-zero' : ''; ?>">
        <td>
            <strong><?php echo htmlspecialchars($row->full_name); ?></strong>
            <div class="small text-muted"><?php echo htmlspecialchars($row->document_number ?: 'Sin documento'); ?><?php if (empty($row->is_active)): ?> · inactivo<?php endif; ?></div>
        </td>
        <td><?php echo htmlspecialchars($row->company_name); ?><div class="small text-muted"><?php echo htmlspecialchars($row->area_name ?: 'Sin área'); ?></div></td>
        <td><?php echo $hasAgreement ? htmlspecialchars($row->agreement_name) : '<span class="text-danger">Sin convenio</span>'; ?></td>
        <td class="vac-report-status">
            <?php if ($pending > 0): ?><span class="status-pill is-yes">Saldo</span><?php else: ?><span class="status-pill">Sin saldo</span><?php endif; ?>
            <?php if (!$hasLiq): ?><span class="status-pill is-no">Sin liquidar <?php echo htmlspecialchars($currentPeriod); ?></span><?php endif; ?>
            <?php if (!$hasHire): ?><span class="status-pill is-no">Sin ingreso</span><?php endif; ?>
            <?php if (!$hasAgreement): ?><span class="status-pill is-no">Sin convenio</span><?php endif; ?>
            <?php if (!empty($row->next_expiry)): ?><div class="small text-warning mt-1">Vence <?php echo date('d/m/Y', strtotime($row->next_expiry)); ?></div><?php endif; ?>
        </td>
        <td class="text-end"><?php echo vacation_format_days($row->historical_pending); ?></td>
        <td class="text-end"><?php echo vacation_format_days($row->current_pending); ?></td>
        <td class="text-end"><strong><?php echo vacation_format_days($pending); ?></strong><?php if ($row->oldest_period): ?><div class="small text-muted"><?php echo date('Y', strtotime($row->oldest_period)); ?></div><?php endif; ?></td>
        <td class="text-end vac-report-actions">
            <div class="d-flex flex-wrap justify-content-end gap-1">
                <form method="post" action="<?php echo URLROOT; ?>/vacationAdmin/liquidateUser/<?php echo (int)$row->user_id; ?>" class="d-inline" onsubmit="return confirm('¿Liquidar el período <?php echo htmlspecialchars($currentPeriod); ?> para <?php echo htmlspecialchars($row->full_name, ENT_QUOTES); ?>?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="period_label" value="<?php echo htmlspecialchars($currentPeriod); ?>">
                    <?php if ($returnQuery !== ''): ?><input type="hidden" name="return_query" value="<?php echo htmlspecialchars($returnQuery); ?>"><?php endif; ?>
                    <button type="submit" class="btn btn-sm btn-success" <?php echo $hasHire ? '' : 'disabled'; ?> title="<?php echo $hasHire ? 'Liquidar período ' . htmlspecialchars($currentPeriod) : 'Indicá la fecha de ingreso antes de liquidar'; ?>"><i class="fas fa-bolt me-1"></i>Liquidar</button>
                </form>
                <a class="btn btn-sm btn-primary" href="<?php echo URLROOT; ?>/vacationAdmin/vacationSetup/<?php echo (int)$row->user_id; ?>" title="Cargar períodos, histórico o crédito"><i class="fas fa-plus me-1"></i>Cargar</a>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#vacDetail<?php echo (int)$row->user_id; ?>">Detalle</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars(vacation_planilla_staff_url((int)$row->user_id)); ?>" target="_blank" rel="noopener">Planilla</a>
                <a class="btn btn-sm btn-outline-primary" href="<?php echo URLROOT; ?>/admin/employeeProfile/<?php echo (int)$row->user_id; ?>#tab-vacation">Ficha</a>
            </div>
        </td>
    </tr>
    <tr class="collapse" id="vacDetail<?php echo (int)$row->user_id; ?>"><td colspan="8" class="bg-light"><div class="d-flex flex-wrap gap-2"><?php foreach ($details as $p): ?><span class="badge bg-white text-dark border p-2"><?php echo htmlspecialchars($p->period_label); ?> · <?php echo $typeLabels[$p->balance_type] ?? $p->balance_type; ?> · <strong><?php echo vacation_format_days($p->days_pending); ?></strong> · <?php echo htmlspecialchars($modeLabels[$p->count_mode_snapshot] ?? $p->count_mode_snapshot); ?><?php if ($p->expires_at): ?> · vence <?php echo date('d/m/Y', strtotime($p->expires_at)); ?><?php endif; ?></span><?php endforeach; ?><?php if (!$details): ?><span class="text-muted small"><?php echo $hasLiq ? 'Sin períodos abiertos para el filtro.' : 'Sin saldo pendiente en períodos abiertos. Si falta un período, usá Liquidar o Cargar.'; ?></span><?php endif; ?></div></td></tr>
    <?php endforeach; ?></tbody></table></div>
</div>

<?php $pages = (int)ceil($report['total'] / max(1, $report['per_page'])); if ($pages > 1): ?><nav class="mt-3" aria-label="Páginas del reporte"><ul class="pagination pagination-sm justify-content-center"><?php for ($p = 1; $p <= $pages; $p++): ?><li class="page-item <?php echo $p === (int)$report['page'] ? 'active' : ''; ?>"><a class="page-link" href="<?php echo htmlspecialchars($reportUrl(['page' => $p])); ?>"><?php echo $p; ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>

<?php require APPROOT . '/views/inc/footer.php'; ?>
