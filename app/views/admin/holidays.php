<?php require APPROOT . '/views/inc/header.php'; ?>
<?php
$scopeLabels = ['national' => 'Nacional', 'province' => 'Provincial', 'locality' => 'Localidad'];
$rulesReady = !empty($data['scoped_rules_ready']);
?>
<div class="admin-page-head mb-3">
    <div><h2 class="page-title mb-1">Feriados</h2><p class="text-muted mb-0">Los manuales aplican a la empresa activa. Las reglas automáticas aplican por alcance geográfico.</p></div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow mb-3">
            <div class="card-header"><h5 class="mb-0">Feriados manuales de la empresa activa</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Fecha</th><th>Nombre</th><th></th></tr></thead><tbody>
                <?php foreach($data['holidays'] as $holiday): ?><tr>
                    <td><?php echo date('d/m/Y', strtotime($holiday->holiday_date)); ?></td><td><?php echo htmlspecialchars($holiday->name); ?></td><td class="text-end"><form method="post" action="<?php echo URLROOT; ?>/admin/deleteHoliday/<?php echo $holiday->id; ?>" onsubmit="return confirm('¿Eliminar este feriado manual?');"><?php echo csrf_field(); ?><button class="btn btn-sm btn-outline-danger">Eliminar</button></form></td>
                </tr><?php endforeach; ?>
                <?php if (empty($data['holidays'])): ?><tr><td colspan="3" class="text-muted text-center py-3">Sin feriados manuales.</td></tr><?php endif; ?>
                </tbody></table></div>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header"><h5 class="mb-0">Reglas automáticas por alcance</h5></div>
            <div class="card-body p-0">
                <?php if (!$rulesReady): ?><div class="alert alert-warning m-3">Falta ejecutar <code>migration_holiday_locations.sql</code>.</div>
                <?php else: ?><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Fecha anual</th><th>Nombre</th><th>Alcance</th><th>Ubicación</th><th></th></tr></thead><tbody>
                <?php foreach($data['scoped_rules'] as $rule): ?><tr>
                    <td><?php echo htmlspecialchars($rule->month_day); ?></td><td><?php echo htmlspecialchars($rule->name); ?></td><td><span class="badge bg-info text-dark"><?php echo $scopeLabels[$rule->scope_type] ?? $rule->scope_type; ?></span></td>
                    <td><?php echo htmlspecialchars(trim(($rule->locality ? $rule->locality . ', ' : '') . ($rule->province ?? '')) ?: 'Todo el país'); ?></td>
                    <td class="text-end"><form method="post" action="<?php echo URLROOT; ?>/admin/deleteScopedHolidayRule/<?php echo (int)$rule->id; ?>" onsubmit="return confirm('¿Eliminar esta regla automática?');"><?php echo csrf_field(); ?><button class="btn btn-sm btn-outline-danger">Eliminar</button></form></td>
                </tr><?php endforeach; ?>
                <?php if (empty($data['scoped_rules'])): ?><tr><td colspan="5" class="text-muted text-center py-3">Sin reglas automáticas.</td></tr><?php endif; ?>
                </tbody></table></div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow mb-3"><div class="card-header"><h5 class="mb-0">Añadir feriado manual</h5></div><div class="card-body"><form action="<?php echo URLROOT; ?>/admin/holidays" method="post"><?php echo csrf_field(); ?><div class="mb-3"><label class="form-label">Nombre</label><input type="text" name="name" class="form-control" required></div><div class="mb-3"><label class="form-label">Fecha</label><input type="date" name="holiday_date" class="form-control" required></div><button class="btn btn-primary w-100">Guardar para esta empresa</button></form></div></div>
        <?php if ($rulesReady): ?><div class="card shadow"><div class="card-header"><h5 class="mb-0">Añadir regla automática</h5></div><div class="card-body"><form action="<?php echo URLROOT; ?>/admin/holidays" method="post"><?php echo csrf_field(); ?><input type="hidden" name="holiday_action" value="create_scoped_rule"><div class="mb-3"><label class="form-label">Nombre</label><input type="text" name="name" class="form-control" required></div><div class="mb-3"><label class="form-label">Se repite cada año el</label><input type="date" name="rule_date" class="form-control" required></div><input type="hidden" name="month_day" id="month_day"><div class="mb-3"><label class="form-label">Alcance</label><select name="scope_type" id="scope_type" class="form-select"><option value="national">Nacional</option><option value="province">Toda una provincia</option><option value="locality" selected>Una localidad</option></select></div><div class="mb-3 scope-place"><label class="form-label">Provincia</label><input type="text" name="province" class="form-control" placeholder="Ej. Córdoba"></div><div class="mb-3 scope-locality"><label class="form-label">Localidad</label><input type="text" name="locality" class="form-control" placeholder="Ej. Villa María"></div><button class="btn btn-success w-100">Guardar regla</button></form></div></div><?php endif; ?>
    </div>
</div>
<script>
document.querySelectorAll('form').forEach(function(form){ if(form.querySelector('[name="holiday_action"]')) form.addEventListener('submit', function(){ var d=form.querySelector('[name="rule_date"]').value; form.querySelector('#month_day').value=d ? d.slice(5) : ''; }); });
document.getElementById('scope_type')?.addEventListener('change', function(e){ document.querySelectorAll('.scope-place').forEach(function(el){el.style.display=e.target.value==='national'?'none':''}); document.querySelectorAll('.scope-locality').forEach(function(el){el.style.display=e.target.value==='locality'?'':'none'}); });
</script>
<?php require APPROOT . '/views/inc/footer.php'; ?>
