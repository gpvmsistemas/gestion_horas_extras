<?php require APPROOT . '/views/inc/header.php';
$summary = $data['summary'];
$user = $data['user'];
?>

<div class="admin-page-head mb-4">
    <div class="admin-page-brand">
        <div class="admin-page-icon"><i class="fas fa-umbrella-beach"></i></div>
        <div class="admin-page-meta">
            <h2 class="page-title mb-0">Vacaciones — <?php echo htmlspecialchars($user->full_name); ?></h2>
            <p class="page-subtitle mb-0">Carga inicial, períodos y días ya tomados.</p>
        </div>
    </div>
    <div class="admin-page-actions">
        <a href="<?php echo URLROOT; ?>/admin/employeeProfile/<?php echo (int)$user->id; ?>" class="btn btn-outline-secondary btn-sm">Ficha</a>
    </div>
</div>

<div class="admin-kpi-grid mb-4" style="grid-template-columns:repeat(4,1fr);">
    <div class="admin-kpi-card">
        <div><div class="admin-kpi-value"><?php echo vacation_format_days($summary['total_pending']); ?></div><div class="admin-kpi-label">Total pendiente</div></div>
    </div>
    <div class="admin-kpi-card">
        <div><div class="admin-kpi-value"><?php echo (int)$summary['seniority_months']; ?></div><div class="admin-kpi-label">Meses antigüedad</div></div>
    </div>
    <div class="admin-kpi-card">
        <div><div class="admin-kpi-value"><?php echo $summary['rule'] ? (int)$summary['rule']->days_entitled : '—'; ?></div><div class="admin-kpi-label">Días regla actual</div></div>
    </div>
    <div class="admin-kpi-card">
        <div><div class="admin-kpi-value small"><?php echo $summary['agreement'] ? htmlspecialchars($summary['agreement']->code) : '—'; ?></div><div class="admin-kpi-label">Convenio</div></div>
    </div>
</div>

<form method="post" action="<?php echo URLROOT; ?>/vacationAdmin/saveVacationSetup/<?php echo (int)$user->id; ?>">
    <?php echo csrf_field(); ?>

    <div class="card border shadow-sm mb-3" id="vacEmployeeCard">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <strong>Datos del empleado</strong>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnCalcVacation" data-url="<?php echo URLROOT; ?>/vacationAdmin/calculateVacationPreview/<?php echo (int)$user->id; ?>">
                <i class="fas fa-calculator me-1"></i>Calcular vacaciones
            </button>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label small">Inicio plan de prueba <span class="text-muted">(opc.)</span></label>
                    <input type="date" name="probation_start_date" id="vacProbation" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($user->probation_start_date ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Ingreso formal <span class="text-danger">*</span></label>
                    <input type="date" name="hire_date" id="vacHireDate" class="form-control form-control-sm" required
                           value="<?php echo htmlspecialchars($user->hire_date ?? ''); ?>">
                    <small class="text-muted">Antigüedad y vacaciones (no usa la fecha de prueba).</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Convenio (override)</label>
                    <select name="agreement_id" id="vacAgreement" class="form-select form-select-sm">
                        <option value="0">— Usar default de empresa —</option>
                        <?php foreach ($data['agreements'] as $ag): ?>
                        <option value="<?php echo (int)$ag->id; ?>" <?php echo (int)($user->agreement_id ?? 0) === (int)$ag->id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ag->name); ?> (<?php echo htmlspecialchars($ag->code); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Calcular para período</label>
                    <input type="text" id="vacCalcPeriod" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($data['suggested_period']); ?>" placeholder="2026">
                    <small class="text-muted">Período anual; la antigüedad se calcula al 31 de diciembre.</small>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="liquidate_current" value="1" id="liqCur">
                        <label class="form-check-label small" for="liqCur">Liquidar período actual al guardar</label>
                    </div>
                </div>
            </div>
            <div id="vacCalcResult" class="alert alert-info small mt-3 mb-0 d-none" role="status"></div>
        </div>
    </div>

    <div class="card border shadow-sm mb-3">
        <div class="card-header"><strong>Períodos (carga inicial)</strong></div>
        <div class="card-body">
            <p class="small text-muted">Indicá cuántos días <strong>correspondían</strong> y cuántos <strong>ya se tomaron</strong> en cada año.</p>
            <?php
            $periodRows = array_values(array_filter($summary['periods'], function($period) {
                return !isset($period->balance_type) || $period->balance_type === 'annual';
            }));
            if (empty($periodRows)) {
                $periodRows = [(object)[
                    'period_label' => $data['suggested_period'],
                    'days_entitled' => 0,
                    'days_taken' => 0,
                    'days_pending' => 0,
                ]];
            }
            $i = 0;
            foreach ($periodRows as $p):
            ?>
            <div class="row g-2 mb-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Período</label>
                    <input type="text" name="periods[<?php echo $i; ?>][period_label]" class="form-control form-control-sm vac-period-label"
                           value="<?php echo htmlspecialchars($p->period_label); ?>" placeholder="2025" <?php echo $i === 0 ? 'id="vacFirstPeriodLabel"' : ''; ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Corresponden</label>
                    <input type="number" step="0.5" min="0" name="periods[<?php echo $i; ?>][days_entitled]" class="form-control form-control-sm vac-days-entitled"
                           value="<?php echo htmlspecialchars($p->days_entitled); ?>" <?php echo $i === 0 ? 'id="vacFirstDaysEntitled"' : ''; ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Ya tomados</label>
                    <input type="number" step="0.5" min="0" name="periods[<?php echo $i; ?>][days_taken]" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($p->days_taken); ?>">
                </div>
                <div class="col-md-3">
                    <span class="small text-muted">Pendiente: <strong><?php echo vacation_format_days(vacation_period_pending($p)); ?></strong></span>
                </div>
            </div>
            <?php $i++; endforeach; ?>
            <div class="row g-2 mb-0">
                <div class="col-md-3">
                    <input type="text" name="periods[<?php echo $i; ?>][period_label]" class="form-control form-control-sm" placeholder="Nuevo período ej. 2024">
                </div>
                <div class="col-md-3">
                    <input type="number" step="0.5" min="0" name="periods[<?php echo $i; ?>][days_entitled]" class="form-control form-control-sm" placeholder="Corresponden">
                </div>
                <div class="col-md-3">
                    <input type="number" step="0.5" min="0" name="periods[<?php echo $i; ?>][days_taken]" class="form-control form-control-sm" placeholder="Tomados">
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Guardar</button>
</form>

<div class="row g-3 mt-1">
    <div class="col-lg-4">
        <form method="post" action="<?php echo URLROOT; ?>/vacationAdmin/addHistoricalBalance/<?php echo (int)$user->id; ?>" class="card border shadow-sm h-100">
            <?php echo csrf_field(); ?>
            <div class="card-header"><strong>Reconocer saldo histórico</strong></div>
            <div class="card-body">
                <p class="small text-muted">Carga deuda de años anteriores en un saldo separado, sin vencimiento.</p>
                <div class="row g-2"><div class="col-5"><label class="form-label small">Año</label><input type="number" name="year" min="1970" max="<?php echo date('Y'); ?>" value="<?php echo date('Y')-1; ?>" class="form-control form-control-sm" required></div><div class="col-7"><label class="form-label small">Días reconocidos</label><input type="number" name="days" min="0.5" step="0.5" class="form-control form-control-sm" required></div></div>
                <label class="form-label small mt-2">Motivo / respaldo</label><textarea name="reason" rows="2" class="form-control form-control-sm" required></textarea>
                <button class="btn btn-outline-primary btn-sm mt-2" type="submit">Registrar saldo</button>
            </div>
        </form>
    </div>
    <div class="col-lg-4">
        <form method="post" action="<?php echo URLROOT; ?>/vacationAdmin/addConventionalCredit/<?php echo (int)$user->id; ?>" class="card border shadow-sm h-100">
            <?php echo csrf_field(); ?>
            <div class="card-header"><strong>Crédito convencional</strong></div>
            <div class="card-body">
                <p class="small text-muted">Para créditos separados con vencimiento, como el transitorio UTEDYC.</p>
                <div class="row g-2"><div class="col-4"><label class="form-label small">Año</label><input type="number" name="year" value="<?php echo date('Y'); ?>" class="form-control form-control-sm" required></div><div class="col-4"><label class="form-label small">Días</label><input type="number" name="days" min="0.5" step="0.5" class="form-control form-control-sm" required></div><div class="col-4"><label class="form-label small">Vence</label><input type="date" name="expires_at" class="form-control form-control-sm" required></div></div>
                <label class="form-label small mt-2">Motivo / respaldo</label><textarea name="reason" rows="2" class="form-control form-control-sm" required></textarea>
                <button class="btn btn-outline-warning btn-sm mt-2" type="submit">Registrar crédito</button>
            </div>
        </form>
    </div>
    <div class="col-lg-4">
        <form method="post" action="<?php echo URLROOT; ?>/vacationAdmin/convertBalance/<?php echo (int)$user->id; ?>" class="card border shadow-sm h-100">
            <?php echo csrf_field(); ?>
            <div class="card-header"><strong>Conversión auditada</strong></div>
            <div class="card-body">
                <p class="small text-muted">Obligatoria si un saldo cambia entre días corridos y hábiles.</p>
                <label class="form-label small">Período</label><select name="period_id" class="form-select form-select-sm" required><option value="">Seleccionar</option><?php foreach($summary['periods'] as $period): ?><option value="<?php echo (int)$period->id; ?>"><?php echo htmlspecialchars($period->period_label); ?> · <?php echo vacation_format_days(vacation_period_pending($period)); ?> días · <?php echo htmlspecialchars($period->count_mode_snapshot ?? 'calendar'); ?></option><?php endforeach; ?></select>
                <div class="row g-2 mt-1"><div class="col-7"><label class="form-label small">Nueva unidad</label><select name="target_mode" class="form-select form-select-sm" required><?php foreach(vacation_day_count_modes() as $key=>$label): ?><option value="<?php echo $key; ?>"><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select></div><div class="col-5"><label class="form-label small">Saldo convertido</label><input type="number" name="target_pending" min="0" step="0.5" class="form-control form-control-sm" required></div></div>
                <label class="form-label small mt-2">Fundamento</label><textarea name="reason" rows="2" class="form-control form-control-sm" required></textarea>
                <button class="btn btn-outline-danger btn-sm mt-2" type="submit">Guardar conversión</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('btnCalcVacation');
    var box = document.getElementById('vacCalcResult');
    if (!btn || !box) return;

    btn.addEventListener('click', function() {
        var hire = document.getElementById('vacHireDate');
        if (!hire || !hire.value) {
            box.className = 'alert alert-warning small mt-3 mb-0';
            box.classList.remove('d-none');
            box.textContent = 'Indicá la fecha de ingreso formal.';
            return;
        }
        btn.disabled = true;
        box.className = 'alert alert-secondary small mt-3 mb-0';
        box.classList.remove('d-none');
        box.textContent = 'Calculando…';

        var fd = new FormData();
        fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
        fd.append('hire_date', hire.value);
        fd.append('agreement_id', document.getElementById('vacAgreement').value);
        var periodInp = document.getElementById('vacCalcPeriod');
        if (periodInp && periodInp.value) {
            fd.append('period_label', periodInp.value.trim());
        }
        var firstLbl = document.getElementById('vacFirstPeriodLabel');
        if (firstLbl && firstLbl.value && !periodInp.value) {
            fd.append('period_label', firstLbl.value.trim());
        }

        fetch(btn.getAttribute('data-url'), { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.disabled = false;
                if (!data.ok) {
                    box.className = 'alert alert-warning small mt-3 mb-0';
                    box.textContent = data.message || 'No se pudo calcular.';
                    return;
                }
                box.className = 'alert alert-success small mt-3 mb-0';
                var html = '<strong>' + data.days_entitled + ' días</strong> corresponden · Período <strong>' + data.period_label + '</strong><br>';
                html += data.message;
                if (data.rule_notes) {
                    html += '<br><span class="text-muted">Regla: ' + data.rule_range + ' — ' + data.rule_notes + ' · Conteo: ' + data.day_count_mode_label + '</span>';
                }
                html += '<br><button type="button" class="btn btn-link btn-sm p-0 mt-1" id="vacApplyCalc">Aplicar a la primera fila de períodos</button>';
                box.innerHTML = html;

                var applyBtn = document.getElementById('vacApplyCalc');
                if (applyBtn) {
                    applyBtn.addEventListener('click', function() {
                        var lbl = document.getElementById('vacFirstPeriodLabel');
                        var ent = document.getElementById('vacFirstDaysEntitled');
                        if (lbl) lbl.value = data.period_label;
                        if (ent) ent.value = data.days_entitled;
                        if (periodInp) periodInp.value = data.period_label;
                    });
                }
            })
            .catch(function() {
                btn.disabled = false;
                box.className = 'alert alert-danger small mt-3 mb-0';
                box.textContent = 'Error de conexión al calcular.';
            });
    });
});
</script>

<?php if (!empty($data['movements'])): ?>
<div class="card border shadow-sm mt-4">
    <div class="card-header"><strong>Últimos movimientos</strong></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Fecha</th><th>Período</th><th>Tipo</th><th>Días</th><th>Origen</th><th>Notas</th></tr></thead>
            <tbody>
            <?php foreach ($data['movements'] as $m): ?>
            <tr>
                <td><?php echo date('d/m/Y H:i', strtotime($m->created_at)); ?></td>
                <td><?php echo htmlspecialchars($m->period_label); ?></td>
                <td><?php echo htmlspecialchars($m->movement_type); ?></td>
                <td><?php echo vacation_format_days($m->days); ?></td>
                <td><?php echo htmlspecialchars($m->source); ?></td>
                <td class="small"><?php echo htmlspecialchars($m->notes ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require APPROOT . '/views/inc/footer.php'; ?>
