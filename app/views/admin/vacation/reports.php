<?php
require APPROOT . '/views/inc/header.php';
$filters = $data['filters'];
$report = $data['report'];
$stats = $data['stats'];
$query = $filters;
unset($query['page'], $query['export']);
$csvUrl = URLROOT . '/vacationAdmin/exportVacationBalancesCsv?' . http_build_query($query);
$typeLabels = ['annual'=>'Anual', 'historical'=>'Histórico', 'conventional_credit'=>'Crédito convencional'];
$modeLabels = function_exists('vacation_day_count_modes') ? vacation_day_count_modes() : [];
?>

<div class="admin-page-head">
    <div class="admin-page-brand">
        <div class="admin-page-icon"><i class="fas fa-umbrella-beach"></i></div>
        <div class="admin-page-meta">
            <h1 class="page-title">Vacaciones pendientes</h1>
            <p class="page-subtitle mb-0">Saldos consolidados de todas las empresas, con detalle por período.</p>
        </div>
    </div>
    <a href="<?php echo htmlspecialchars($csvUrl); ?>" class="btn btn-success btn-sm"><i class="fas fa-file-csv me-1"></i>Exportar CSV filtrado</a>
</div>

<div class="admin-kpi-grid vac-report-kpi-grid mb-3">
    <?php foreach ([
        ['Empleados con saldo', $stats['employees_with_pending']],
        ['Días pendientes', vacation_format_days($stats['total_pending'])],
        ['Años anteriores', vacation_format_days($stats['historical_pending'])],
        ['Período ' . date('Y'), vacation_format_days($stats['current_pending'])],
        ['Créditos por vencer', $stats['expiring_credits']],
        ['Sin convenio', $stats['without_agreement']],
        ['Sin liquidación ' . date('Y'), $stats['without_current_liquidation']],
    ] as $kpi): ?>
    <div class="admin-kpi-card vac-report-kpi"><div class="vac-report-kpi-mark"></div><div><div class="admin-kpi-value"><?php echo htmlspecialchars((string)$kpi[1]); ?></div><div class="admin-kpi-label"><?php echo htmlspecialchars($kpi[0]); ?></div></div></div>
    <?php endforeach; ?>
</div>

<form method="get" action="<?php echo URLROOT; ?>/vacationAdmin/reports" class="admin-surface vac-report-filter mb-3">
    <div class="admin-surface-head"><div><span class="admin-section-eyebrow">Consulta personalizada</span><h2 class="admin-surface-title mb-0"><i class="fas fa-sliders-h"></i> Filtros del reporte</h2></div><span class="small text-muted">Refiná los resultados por empleado, período o saldo</span></div>
    <div class="admin-surface-body">
        <div class="row g-2">
            <div class="col-md-3"><label class="form-label small">Empresa</label><select name="company_id" class="form-select form-select-sm"><option value="0">Todas</option><?php foreach ($data['companies'] as $co): ?><option value="<?php echo (int)$co->id; ?>" <?php echo (int)$filters['company_id']===(int)$co->id?'selected':''; ?>><?php echo htmlspecialchars($co->name); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label small">Convenio</label><select name="agreement_id" class="form-select form-select-sm"><option value="0">Todos</option><?php foreach ($data['agreements'] as $ag): ?><option value="<?php echo (int)$ag->id; ?>" <?php echo (int)$filters['agreement_id']===(int)$ag->id?'selected':''; ?>><?php echo htmlspecialchars($ag->name); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label small">Área</label><select name="area_id" class="form-select form-select-sm"><option value="0">Todas</option><?php foreach ($data['areas'] as $area): ?><option value="<?php echo (int)$area->id; ?>" <?php echo (int)$filters['area_id']===(int)$area->id?'selected':''; ?>><?php echo htmlspecialchars($area->name); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label small">Empleado / DNI / CUIL</label><input name="search" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filters['search']); ?>"></div>
            <div class="col-md-2"><label class="form-label small">Período</label><input name="period" class="form-control form-control-sm" placeholder="2025" value="<?php echo htmlspecialchars($filters['period']); ?>"></div>
            <div class="col-md-2"><label class="form-label small">Tipo de saldo</label><select name="balance_type" class="form-select form-select-sm"><option value="">Todos</option><?php foreach ($typeLabels as $key=>$label): ?><option value="<?php echo $key; ?>" <?php echo $filters['balance_type']===$key?'selected':''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label small">Empleados</label><select name="active" class="form-select form-select-sm"><option value="active" <?php echo $filters['active']==='active'?'selected':''; ?>>Solo activos</option><option value="inactive" <?php echo $filters['active']==='inactive'?'selected':''; ?>>Solo inactivos</option><option value="all" <?php echo $filters['active']==='all'?'selected':''; ?>>Todos</option></select></div>
            <div class="col-md-2"><label class="form-label small">Saldo</label><select name="balance_status" class="form-select form-select-sm"><option value="with" <?php echo $filters['balance_status']==='with'?'selected':''; ?>>Con saldo</option><option value="without" <?php echo $filters['balance_status']==='without'?'selected':''; ?>>Sin saldo</option><option value="both" <?php echo $filters['balance_status']==='both'?'selected':''; ?>>Ambos</option></select></div>
            <div class="col-md-2"><label class="form-label small">Mínimo días</label><input type="number" step="0.5" min="0" name="min_days" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string)$filters['min_days']); ?>"></div>
            <div class="col-md-2"><label class="form-label small">Máximo días</label><input type="number" step="0.5" min="0" name="max_days" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string)$filters['max_days']); ?>"></div>
            <div class="col-md-3"><label class="form-label small">Orden</label><select name="sort" class="form-select form-select-sm"><?php foreach (['pending_desc'=>'Mayor saldo primero','pending_asc'=>'Menor saldo primero','name'=>'Empleado','company'=>'Empresa','agreement'=>'Convenio','oldest'=>'Período más antiguo','expiry'=>'Vencimiento más próximo'] as $key=>$label): ?><option value="<?php echo $key; ?>" <?php echo $filters['sort']===$key?'selected':''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3 d-flex align-items-end gap-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="historical_only" value="1" id="histOnly" <?php echo $filters['historical_only']?'checked':''; ?>><label class="form-check-label small" for="histOnly">Solo anteriores</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="expiring_only" value="1" id="expOnly" <?php echo $filters['expiring_only']?'checked':''; ?>><label class="form-check-label small" for="expOnly">Por vencer (90 días)</label></div></div>
            <div class="col-md-6 d-flex align-items-end gap-2"><button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-filter me-1"></i>Aplicar</button><a class="btn btn-outline-secondary btn-sm" href="<?php echo URLROOT; ?>/vacationAdmin/reports">Limpiar</a></div>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <?php foreach ([['Por empresa',$stats['by_company']],['Por convenio',$stats['by_agreement']]] as $group): ?>
    <div class="col-lg-6"><div class="admin-surface vac-report-summary h-100"><div class="admin-surface-head"><h2 class="admin-surface-title mb-0"><i class="fas fa-chart-pie"></i> <?php echo $group[0]; ?></h2></div><div class="admin-surface-body py-2"><?php foreach ($group[1] as $label=>$days): ?><div class="vac-report-summary-row"><span><?php echo htmlspecialchars($label); ?></span><strong><?php echo vacation_format_days($days); ?> días</strong></div><?php endforeach; ?></div></div></div>
    <?php endforeach; ?>
</div>

<div class="admin-surface vac-report-results">
    <div class="admin-surface-head d-flex justify-content-between"><h2 class="admin-surface-title mb-0"><i class="fas fa-users"></i> <?php echo (int)$report['total']; ?> empleado(s)</h2><span class="badge bg-light text-dark border">Página <?php echo (int)$report['page']; ?></span></div>
    <div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead class="table-light"><tr><th>Empleado</th><th>Empresa / área</th><th>Convenio</th><th class="text-end">Histórico</th><th class="text-end">Actual</th><th class="text-end">Total</th><th>Antigüedad</th><th></th></tr></thead><tbody>
    <?php if (empty($report['rows'])): ?><tr><td colspan="8" class="text-center text-muted py-4">No hay resultados para los filtros elegidos.</td></tr><?php endif; ?>
    <?php foreach ($report['rows'] as $row): $details=$report['details'][(int)$row->user_id]??[]; ?>
    <tr><td><strong><?php echo htmlspecialchars($row->full_name); ?></strong><div class="small text-muted"><?php echo htmlspecialchars($row->document_number ?: 'Sin documento'); ?></div></td><td><?php echo htmlspecialchars($row->company_name); ?><div class="small text-muted"><?php echo htmlspecialchars($row->area_name ?: 'Sin área'); ?></div></td><td><?php echo $row->agreement_name?htmlspecialchars($row->agreement_name):'<span class="text-danger">Sin convenio</span>'; ?></td><td class="text-end"><?php echo vacation_format_days($row->historical_pending); ?></td><td class="text-end"><?php echo vacation_format_days($row->current_pending); ?></td><td class="text-end"><strong><?php echo vacation_format_days($row->total_pending); ?></strong></td><td><?php echo $row->oldest_period?date('Y',strtotime($row->oldest_period)):'—'; ?><?php if($row->next_expiry): ?><div class="small text-warning">Vence <?php echo date('d/m/Y',strtotime($row->next_expiry)); ?></div><?php endif; ?></td><td class="text-end"><button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#vacDetail<?php echo (int)$row->user_id; ?>">Detalle</button> <a class="btn btn-sm btn-outline-primary" href="<?php echo URLROOT; ?>/admin/employeeProfile/<?php echo (int)$row->user_id; ?>#tab-vacation">Ficha</a> <a class="btn btn-sm btn-outline-success" href="<?php echo URLROOT; ?>/vacationAdmin/vacationSetup/<?php echo (int)$row->user_id; ?>">Ajustar</a></td></tr>
    <tr class="collapse" id="vacDetail<?php echo (int)$row->user_id; ?>"><td colspan="8" class="bg-light"><div class="d-flex flex-wrap gap-2"><?php foreach($details as $p): ?><span class="badge bg-white text-dark border p-2"><?php echo htmlspecialchars($p->period_label); ?> · <?php echo $typeLabels[$p->balance_type]??$p->balance_type; ?> · <strong><?php echo vacation_format_days($p->days_pending); ?></strong> · <?php echo htmlspecialchars($modeLabels[$p->count_mode_snapshot]??$p->count_mode_snapshot); ?><?php if($p->expires_at): ?> · vence <?php echo date('d/m/Y',strtotime($p->expires_at)); ?><?php endif; ?></span><?php endforeach; ?><?php if(!$details): ?><span class="text-muted small">Sin períodos abiertos para el filtro.</span><?php endif; ?></div></td></tr>
    <?php endforeach; ?></tbody></table></div>
</div>

<?php $pages=(int)ceil($report['total']/$report['per_page']); if($pages>1): ?><nav class="mt-3"><ul class="pagination pagination-sm justify-content-center"><?php for($p=1;$p<=$pages;$p++): $pageQuery=$query;$pageQuery['page']=$p; ?><li class="page-item <?php echo $p===$report['page']?'active':''; ?>"><a class="page-link" href="<?php echo URLROOT; ?>/vacationAdmin/reports?<?php echo htmlspecialchars(http_build_query($pageQuery)); ?>"><?php echo $p; ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>

<?php require APPROOT . '/views/inc/footer.php'; ?>
