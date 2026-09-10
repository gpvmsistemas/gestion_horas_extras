<?php
require APPROOT . '/views/inc/header.php';
$ag = $data['agreement'];
$isNew = !empty($data['is_new']);
$id = $isNew ? 0 : (int)$ag->id;
?>

<div class="admin-page-head mb-4">
    <div class="admin-page-brand">
        <div class="admin-page-icon"><i class="fas fa-file-contract"></i></div>
        <div class="admin-page-meta">
            <h2 class="page-title mb-0"><?php echo $isNew ? 'Nuevo convenio' : 'Editar convenio'; ?></h2>
            <p class="page-subtitle mb-0">Datos del convenio, reglas de vacaciones y licencias convencionales.</p>
        </div>
    </div>
    <a href="<?php echo URLROOT; ?>/vacationAdmin/agreements" class="btn btn-outline-secondary btn-sm">Volver al listado</a>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card border shadow-sm">
            <div class="card-header"><strong>Datos del convenio</strong></div>
            <div class="card-body">
                <form method="post" action="<?php echo URLROOT; ?>/vacationAdmin/saveAgreement">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <div class="mb-2">
                        <label class="form-label small">Código (único)</label>
                        <input type="text" name="code" class="form-control form-control-sm" required maxlength="40"
                               placeholder="Ej. CEC, FARMACIA_UOCRA"
                               value="<?php echo htmlspecialchars($ag->code ?? ''); ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Nombre</label>
                        <input type="text" name="name" class="form-control form-control-sm" required
                               placeholder="Ej. Empleados de Comercio"
                               value="<?php echo htmlspecialchars($ag->name ?? ''); ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Descripción</label>
                        <textarea name="description" class="form-control form-control-sm" rows="2"><?php echo htmlspecialchars($ag->description ?? ''); ?></textarea>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label small">Jurisdicción</label><input name="jurisdiction" class="form-control form-control-sm" value="<?php echo htmlspecialchars($ag->jurisdiction ?? ''); ?>"></div>
                        <div class="col-6"><label class="form-label small">Referencia legal</label><input name="legal_reference" class="form-control form-control-sm" value="<?php echo htmlspecialchars($ag->legal_reference ?? ''); ?>"></div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small">Mes inicio período</label>
                            <select name="period_start_month" class="form-select form-select-sm">
                                <?php
                                $months = [1=>'Enero',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic'];
                                $sel = (int)($ag->period_start_month ?? 1);
                                foreach ($months as $n => $label):
                                ?>
                                <option value="<?php echo $n; ?>" <?php echo $sel === $n ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Vacaciones: enero (1)</small>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Día inicio</label>
                            <input type="number" name="period_start_day" min="1" max="28" class="form-control form-control-sm"
                                   value="<?php echo (int)($ag->period_start_day ?? 1); ?>">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-4"><label class="form-label small">Aviso (días)</label><input type="number" name="notice_days" min="0" class="form-control form-control-sm" value="<?php echo (int)($ag->notice_days ?? 30); ?>"></div>
                        <div class="col-4"><label class="form-label small">Mínimo solicitud</label><input type="number" step="0.5" min="1" name="minimum_request_days" class="form-control form-control-sm" value="<?php echo htmlspecialchars($ag->minimum_request_days ?? 7); ?>"></div>
                        <div class="col-4"><label class="form-label small">Inicio</label><select name="start_rule" class="form-select form-select-sm"><option value="lct" <?php echo ($ag->start_rule??'lct')==='lct'?'selected':''; ?>>LCT</option><option value="monday_or_next_business" <?php echo ($ag->start_rule??'')==='monday_or_next_business'?'selected':''; ?>>Lunes/sig. hábil</option></select></div>
                        <div class="col-12"><label class="form-label small">Fraccionamiento</label><select name="split_policy" class="form-select form-select-sm"><option value="lct_7" <?php echo ($ag->split_policy??'lct_7')==='lct_7'?'selected':''; ?>>Tramos mínimos de 7 días</option><option value="soecra_14_plus_7" <?php echo ($ag->split_policy??'')==='soecra_14_plus_7'?'selected':''; ?>>SOECRA 14 + remanente 7</option></select></div>
                    </div>
                    <?php if (!$isNew): ?>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="agActive"
                            <?php echo !isset($ag->is_active) || $ag->is_active ? 'checked' : ''; ?>>
                        <label class="form-check-label small" for="agActive">Activo</label>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary btn-sm">Guardar convenio</button>
                </form>
            </div>
        </div>
    </div>

    <?php if (!$isNew): ?>
    <div class="col-lg-7">
        <div class="card border shadow-sm mb-3">
            <div class="card-header"><strong>Reglas por antigüedad</strong></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Meses min</th><th>Meses max</th><th>Días</th><th>Conteo</th><th>Notas</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($data['rules'])): ?>
                    <tr><td colspan="5" class="text-muted text-center py-3">Sin reglas. Agregá la primera abajo.</td></tr>
                    <?php else: foreach ($data['rules'] as $r): ?>
                    <tr>
                        <td><?php echo (int)$r->min_months; ?></td>
                        <td><?php echo $r->max_months !== null ? (int)$r->max_months : '∞'; ?></td>
                        <td><strong><?php echo (int)$r->days_entitled; ?></strong></td>
                        <td><?php echo htmlspecialchars($data['day_count_modes'][$r->day_count_mode] ?? $r->day_count_mode); ?></td>
                        <td class="small"><?php echo htmlspecialchars($r->notes ?? ''); ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border shadow-sm">
            <div class="card-header"><strong>Agregar regla</strong></div>
            <div class="card-body">
                <form method="post" action="<?php echo URLROOT; ?>/vacationAdmin/saveAgreementRule/<?php echo $id; ?>">
                    <?php echo csrf_field(); ?>
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label small">Desde (meses)</label>
                            <input type="number" name="min_months" class="form-control form-control-sm" value="0" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Hasta (meses)</label>
                            <input type="number" name="max_months" class="form-control form-control-sm" placeholder="vacío = ∞">
                            <small class="text-muted">Ej. 59 = &lt;5 años</small>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Días</label>
                            <input type="number" name="days_entitled" class="form-control form-control-sm" required min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Conteo</label>
                            <select name="day_count_mode" class="form-select form-select-sm">
                                <?php foreach ($data['day_count_modes'] as $k => $lbl): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($lbl); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Notas</label>
                            <input type="text" name="notes" class="form-control form-control-sm" placeholder="Ej. 5 a 9 años de antigüedad">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Mínimo tramo</label>
                            <input type="number" name="min_consecutive_days" class="form-control form-control-sm" min="1" value="7">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="allows_carryover" value="1" id="carry" checked>
                                <label class="form-check-label small" for="carry">Permite acumular días entre períodos</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-outline-primary btn-sm">Agregar regla</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (!$isNew && !empty($data['leave_types_ready'])): ?>
<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="card border shadow-sm mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <strong><i class="fas fa-file-medical me-1"></i>Licencias del convenio</strong>
                <span class="small text-muted">Catálogo que verá el empleado según su encuadramiento.</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Condiciones</th>
                            <th>Referencia</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($data['leave_types'])): ?>
                    <tr><td colspan="7" class="text-muted text-center py-3">Sin licencias. Agregá la primera abajo o ejecutá la migración con catálogo precargado.</td></tr>
                    <?php else: foreach ($data['leave_types'] as $leave): ?>
                    <tr class="<?php echo empty($leave->is_active) ? 'table-secondary' : ''; ?>">
                        <td><code><?php echo htmlspecialchars($leave->code); ?></code></td>
                        <td>
                            <strong><?php echo htmlspecialchars($leave->name); ?></strong>
                            <?php if (!empty($leave->description)): ?>
                            <div class="small text-muted"><?php echo htmlspecialchars($leave->description); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?php echo htmlspecialchars($data['leave_categories'][$leave->category] ?? $leave->category); ?></td>
                        <td class="small"><?php echo htmlspecialchars(agreement_leave_type_badge($leave)); ?></td>
                        <td class="small"><?php echo htmlspecialchars($leave->legal_reference ?? ''); ?></td>
                        <td><?php echo !empty($leave->is_active) ? '<span class="badge bg-success">Activa</span>' : '<span class="badge bg-secondary">Inactiva</span>'; ?></td>
                        <td class="text-end text-nowrap">
                            <button type="button" class="btn btn-outline-secondary btn-sm js-edit-leave-type"
                                    data-leave='<?php echo htmlspecialchars(json_encode([
                                        'id' => (int)$leave->id,
                                        'code' => $leave->code,
                                        'name' => $leave->name,
                                        'description' => $leave->description ?? '',
                                        'legal_reference' => $leave->legal_reference ?? '',
                                        'category' => $leave->category,
                                        'is_paid' => (int)!empty($leave->is_paid),
                                        'requires_certificate' => (int)!empty($leave->requires_certificate),
                                        'requires_approval' => (int)(!isset($leave->requires_approval) || !empty($leave->requires_approval)),
                                        'max_days_per_event' => $leave->max_days_per_event,
                                        'max_days_per_year' => $leave->max_days_per_year,
                                        'min_notice_days' => $leave->min_notice_days,
                                        'day_count_mode' => $leave->day_count_mode,
                                        'sort_order' => (int)$leave->sort_order,
                                        'is_active' => (int)!empty($leave->is_active),
                                        'notes' => $leave->notes ?? '',
                                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>'
                                    title="Editar"><i class="fas fa-pen"></i></button>
                            <form method="post" action="<?php echo URLROOT; ?>/vacationAdmin/deleteAgreementLeaveType/<?php echo $id; ?>/<?php echo (int)$leave->id; ?>" class="d-inline" onsubmit="return confirm('¿Eliminar esta licencia del convenio?');">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Eliminar"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border shadow-sm">
            <div class="card-header"><strong id="leaveFormTitle">Agregar licencia</strong></div>
            <div class="card-body">
                <form method="post" action="<?php echo URLROOT; ?>/vacationAdmin/saveAgreementLeaveType/<?php echo $id; ?>" id="agreementLeaveForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" id="leaveFormId" value="0">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label small">Código</label>
                            <input type="text" name="code" id="leaveFormCode" class="form-control form-control-sm" maxlength="40" required placeholder="ENFERMEDAD">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small">Nombre</label>
                            <input type="text" name="name" id="leaveFormName" class="form-control form-control-sm" required placeholder="Enfermedad">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Categoría</label>
                            <select name="category" id="leaveFormCategory" class="form-select form-select-sm">
                                <?php foreach ($data['leave_categories'] as $key => $label): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Descripción</label>
                            <input type="text" name="description" id="leaveFormDescription" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Referencia legal</label>
                            <input type="text" name="legal_reference" id="leaveFormLegalRef" class="form-control form-control-sm" placeholder="LCT art. 158 / CCT art. X">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Máx. por evento</label>
                            <input type="number" step="0.5" min="0" name="max_days_per_event" id="leaveFormMaxEvent" class="form-control form-control-sm" placeholder="Ej. 3">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Máx. por año</label>
                            <input type="number" step="0.5" min="0" name="max_days_per_year" id="leaveFormMaxYear" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Aviso previo (días)</label>
                            <input type="number" min="0" name="min_notice_days" id="leaveFormMinNotice" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Orden</label>
                            <input type="number" name="sort_order" id="leaveFormSort" class="form-control form-control-sm" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Conteo de días</label>
                            <select name="day_count_mode" id="leaveFormDayMode" class="form-select form-select-sm">
                                <?php foreach ($data['day_count_modes'] as $k => $lbl): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($lbl); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8 d-flex align-items-end gap-3 flex-wrap">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_paid" value="1" id="leavePaid" checked>
                                <label class="form-check-label small" for="leavePaid">Con goce de sueldo</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="requires_certificate" value="1" id="leaveCert">
                                <label class="form-check-label small" for="leaveCert">Requiere certificado</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="requires_approval" value="1" id="leaveApproval" checked>
                                <label class="form-check-label small" for="leaveApproval">Requiere aprobación RRHH</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="leaveActive" checked>
                                <label class="form-check-label small" for="leaveActive">Activa</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Notas internas</label>
                            <input type="text" name="notes" id="leaveFormNotes" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-outline-primary btn-sm" id="leaveFormSubmit">Guardar licencia</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="leaveFormReset" hidden>Nueva licencia</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php elseif (!$isNew && empty($data['leave_types_ready'])): ?>
<div class="alert alert-warning small mt-3">
    Para administrar licencias por convenio ejecutá <code>migration_collective_agreement_leave_types.sql</code>.
</div>
<?php endif; ?>

<script>
(function () {
    var form = document.getElementById('agreementLeaveForm');
    if (!form) return;
    var title = document.getElementById('leaveFormTitle');
    var resetBtn = document.getElementById('leaveFormReset');
    function setField(id, value) {
        var el = document.getElementById(id);
        if (!el) return;
        el.value = value == null ? '' : value;
    }
    function setCheck(id, checked) {
        var el = document.getElementById(id);
        if (el) el.checked = !!checked;
    }
    function resetLeaveForm() {
        setField('leaveFormId', '0');
        ['leaveFormCode','leaveFormName','leaveFormDescription','leaveFormLegalRef','leaveFormMaxEvent','leaveFormMaxYear','leaveFormMinNotice','leaveFormNotes'].forEach(function (id) { setField(id, ''); });
        setField('leaveFormSort', '0');
        setField('leaveFormCategory', 'other');
        setField('leaveFormDayMode', 'calendar');
        setCheck('leavePaid', true);
        setCheck('leaveCert', false);
        setCheck('leaveApproval', true);
        setCheck('leaveActive', true);
        if (title) title.textContent = 'Agregar licencia';
        if (resetBtn) resetBtn.hidden = true;
    }
    document.querySelectorAll('.js-edit-leave-type').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var data = {};
            try { data = JSON.parse(btn.getAttribute('data-leave') || '{}'); } catch (e) { return; }
            setField('leaveFormId', data.id || 0);
            setField('leaveFormCode', data.code || '');
            setField('leaveFormName', data.name || '');
            setField('leaveFormDescription', data.description || '');
            setField('leaveFormLegalRef', data.legal_reference || '');
            setField('leaveFormCategory', data.category || 'other');
            setField('leaveFormMaxEvent', data.max_days_per_event ?? '');
            setField('leaveFormMaxYear', data.max_days_per_year ?? '');
            setField('leaveFormMinNotice', data.min_notice_days ?? '');
            setField('leaveFormSort', data.sort_order || 0);
            setField('leaveFormDayMode', data.day_count_mode || 'calendar');
            setField('leaveFormNotes', data.notes || '');
            setCheck('leavePaid', data.is_paid);
            setCheck('leaveCert', data.requires_certificate);
            setCheck('leaveApproval', data.requires_approval !== 0);
            setCheck('leaveActive', data.is_active);
            if (title) title.textContent = 'Editar licencia';
            if (resetBtn) resetBtn.hidden = false;
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
    resetBtn?.addEventListener('click', resetLeaveForm);
})();
</script>

<?php require APPROOT . '/views/inc/footer.php'; ?>
