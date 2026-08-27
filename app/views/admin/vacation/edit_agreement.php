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
            <p class="page-subtitle mb-0">Datos del convenio y reglas de días por antigüedad.</p>
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

<?php require APPROOT . '/views/inc/footer.php'; ?>
