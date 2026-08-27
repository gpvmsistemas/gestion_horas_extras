<?php
$record = $data['employee_record'] ?? ['ready' => false];
$assignment = $record['assignment'] ?? null;
$address = $record['address'] ?? null;
$coverage = $record['coverage'] ?? null;
$label = function ($value) { return $value !== null && $value !== '' ? htmlspecialchars((string)$value) : '—'; };
?>
<div class="tab-pane fade" id="tab-record">
<?php if (empty($record['ready'])): ?>
    <div class="alert alert-warning mb-0">El legajo ampliado requiere <code>migration_employee_record_complete.sql</code>.</div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
        <div><h3 class="h6 mb-1">Legajo laboral integral</h3><p class="text-muted small mb-0">Datos laborales, domicilio restringido y cobertura vigente.</p></div>
        <a class="btn btn-sm btn-outline-primary" href="<?php echo URLROOT; ?>/admin/editUser/<?php echo (int)$data['user']->id; ?>">Editar legajo</a>
    </div>
    <div class="row g-3">
        <div class="col-lg-6"><div class="border rounded p-3 h-100"><h4 class="h6">Relación principal</h4><dl class="row small mb-0">
            <dt class="col-5 text-muted">Legajo</dt><dd class="col-7"><?php echo $label($assignment->employee_number ?? null); ?></dd>
            <dt class="col-5 text-muted">Empresa</dt><dd class="col-7"><?php echo $label($record['assignments'][0]->company_name ?? null); ?></dd>
            <dt class="col-5 text-muted">Puesto</dt><dd class="col-7"><?php echo $label($record['assignments'][0]->position_name ?? null); ?></dd>
            <dt class="col-5 text-muted">Área</dt><dd class="col-7"><?php echo $label($record['assignments'][0]->area_name ?? null); ?></dd>
            <dt class="col-5 text-muted">Supervisor</dt><dd class="col-7"><?php echo $label($record['assignments'][0]->supervisor_name ?? null); ?></dd>
            <dt class="col-5 text-muted">Estado</dt><dd class="col-7"><?php echo $label($assignment->status ?? null); ?></dd>
            <dt class="col-5 text-muted">Contratación</dt><dd class="col-7"><?php echo $label($assignment->employment_type ?? null); ?></dd>
            <dt class="col-5 text-muted">Modalidad</dt><dd class="col-7"><?php echo $label($assignment->work_mode ?? null); ?></dd>
            <dt class="col-5 text-muted">Centro de costo</dt><dd class="col-7"><?php echo $label($assignment->cost_center ?? null); ?></dd>
        </dl></div></div>
        <?php if (isAdmin()): ?><div class="col-lg-6"><div class="border rounded p-3 h-100"><h4 class="h6">Cobertura médica</h4><dl class="row small mb-0">
            <dt class="col-5 text-muted">Entidad</dt><dd class="col-7"><?php echo $label($coverage->insurer_name ?? null); ?></dd>
            <dt class="col-5 text-muted">Plan</dt><dd class="col-7"><?php echo $label($coverage->plan_name ?? null); ?></dd>
            <dt class="col-5 text-muted">Afiliado</dt><dd class="col-7"><?php echo $label($coverage->affiliate_number ?? null); ?></dd>
            <dt class="col-5 text-muted">Carácter</dt><dd class="col-7"><?php echo $label($coverage->member_role ?? null); ?></dd>
            <dt class="col-5 text-muted">Estado</dt><dd class="col-7"><?php echo $label($coverage->status ?? null); ?></dd>
            <dt class="col-5 text-muted">Aportes derivados</dt><dd class="col-7"><?php echo !empty($coverage->contribution_redirected) ? 'Sí' : 'No'; ?></dd>
        </dl></div></div><?php endif; ?>
        <?php if (isAdmin()): ?><div class="col-12"><div class="border rounded p-3"><div class="d-flex justify-content-between gap-2"><h4 class="h6">Domicilio particular</h4><span class="badge bg-secondary"><?php echo $label($address->verification_status ?? 'pendiente'); ?></span></div>
            <p class="mb-1"><?php echo $label($address->original_text ?? null); ?></p>
            <?php if (isset($address->latitude, $address->longitude)): ?><p class="small text-muted mb-0">Coordenadas restringidas: <?php echo htmlspecialchars($address->latitude); ?>, <?php echo htmlspecialchars($address->longitude); ?> · <a target="_blank" rel="noopener noreferrer" href="https://www.openstreetmap.org/?mlat=<?php echo urlencode($address->latitude); ?>&mlon=<?php echo urlencode($address->longitude); ?>#map=18/<?php echo urlencode($address->latitude); ?>/<?php echo urlencode($address->longitude); ?>">Verificar en mapa</a></p><?php endif; ?>
            <p class="small text-warning mt-2 mb-0"><i class="fas fa-lock me-1"></i>Información personal de uso exclusivo de RR. HH.; no utilizar como control automático de asistencia.</p>
        </div></div><?php endif; ?>
    </div>
    <?php if (count($record['assignments'] ?? []) > 1): ?><h4 class="h6 mt-4">Historial / otras empresas</h4><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Empresa</th><th>Legajo</th><th>Puesto</th><th>Desde</th><th>Hasta</th><th>Estado</th></tr></thead><tbody><?php foreach($record['assignments'] as $row): ?><tr><td><?php echo $label($row->company_name); ?></td><td><?php echo $label($row->employee_number); ?></td><td><?php echo $label($row->position_name); ?></td><td><?php echo $label($row->start_date); ?></td><td><?php echo $label($row->end_date); ?></td><td><?php echo $label($row->status); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    <?php if ((new HrSuite())->ready()): $hrm=new HrSuite();$uid=(int)$data['user']->id;$employeePpe=$hrm->query('SELECT pd.*,pi.name item_name FROM ppe_deliveries pd JOIN ppe_items pi ON pi.id=pd.item_id WHERE pd.user_id=? ORDER BY pd.delivered_on DESC',[$uid]);$employeeAssets=$hrm->query('SELECT a.*,ac.name category_name FROM assets a JOIN asset_categories ac ON ac.id=a.category_id WHERE a.current_custodian_user_id=? ORDER BY a.asset_tag',[$uid]); ?>
    <div class="row g-3 mt-2"><div class="col-lg-6"><div class="border rounded p-3 h-100"><h4 class="h6">EPP y ropa entregados</h4><?php foreach($employeePpe as $p):?><div class="small border-bottom py-2"><strong><?php echo htmlspecialchars($p->item_name);?></strong> · <?php echo htmlspecialchars($p->delivered_on);?> <span class="badge bg-secondary"><?php echo htmlspecialchars($p->status);?></span></div><?php endforeach;?><?php if(!$employeePpe):?><span class="small text-muted">Sin entregas.</span><?php endif;?></div></div><div class="col-lg-6"><div class="border rounded p-3 h-100"><h4 class="h6">Activos bajo custodia</h4><?php foreach($employeeAssets as $a):?><div class="small border-bottom py-2"><strong><?php echo htmlspecialchars($a->asset_tag);?></strong> · <?php echo htmlspecialchars($a->category_name);?> <span class="badge bg-secondary"><?php echo htmlspecialchars($a->status);?></span></div><?php endforeach;?><?php if(!$employeeAssets):?><span class="small text-muted">Sin activos vigentes.</span><?php endif;?></div></div></div>
    <?php endif; ?>
<?php endif; ?>
</div>
