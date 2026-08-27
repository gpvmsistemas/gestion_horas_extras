<?php require APPROOT . '/views/inc/header.php'; ?>

<div class="admin-page-head">
    <div class="admin-page-brand">
        <div class="admin-page-icon"><i class="fas fa-file-contract"></i></div>
        <div class="admin-page-meta">
            <h2 class="page-title">Convenios colectivos</h2>
            <p class="page-subtitle mb-0">Un convenio por rubro; asigná el default a cada empresa del grupo.</p>
        </div>
    </div>
    <div class="admin-page-actions">
        <a href="<?php echo URLROOT; ?>/vacationAdmin/editAgreement" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i>Nuevo convenio
        </a>
    </div>
</div>

<div class="alert alert-light border small mb-4">
    <strong><i class="fas fa-info-circle me-1"></i>Cómo usarlo en tu grupo (varios rubros)</strong>
    <ul class="mb-0 mt-2">
        <li><strong>Servicios Sociales</strong> y <strong>Ecofarma</strong> son empresas distintas en el sistema → cada una puede tener un convenio distinto en el panel de la derecha.</li>
        <li>Los convenios Comercio, Farmacia Córdoba, SOECRA, UTEDYC y Sanidad ya vienen precargados; solo deben asignarse donde corresponda.</li>
        <li>Si un empleado es excepción, en <strong>Editar usuario</strong> o <strong>Carga vacaciones</strong> elegí otro convenio (override individual).</li>
        <li>Prioridad de asignación: empleado, luego área y finalmente convenio por defecto de la empresa.</li>
    </ul>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <?php foreach ($data['agreements'] as $ag): ?>
        <div class="card border shadow-sm mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong><?php echo htmlspecialchars($ag->name); ?></strong>
                    <span class="badge bg-secondary ms-1"><?php echo htmlspecialchars($ag->code); ?></span>
                    <?php if (empty($ag->is_active)): ?><span class="badge bg-warning text-dark">Inactivo</span><?php endif; ?>
                </div>
                <a href="<?php echo URLROOT; ?>/vacationAdmin/editAgreement/<?php echo (int)$ag->id; ?>" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-edit me-1"></i>Editar / reglas
                </a>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2">
                    Período de vacaciones: desde el
                    <?php
                    $mNames = ['','ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
                    echo (int)$ag->period_start_day . ' ' . ($mNames[(int)$ag->period_start_month] ?? '?');
                    ?>
                    de cada año. Los convenios precargados usan año calendario (1 ene–31 dic).
                </p>
                <?php if (empty($ag->rules)): ?>
                <p class="text-warning small mb-0">Sin reglas de antigüedad — <a href="<?php echo URLROOT; ?>/vacationAdmin/editAgreement/<?php echo (int)$ag->id; ?>">configurar</a>.</p>
                <?php else: ?>
                <table class="table table-sm mb-0">
                    <thead><tr><th>Antigüedad (meses)</th><th>Días/año</th><th>Conteo</th><th>Notas</th></tr></thead>
                    <tbody>
                    <?php foreach ($ag->rules as $rule): ?>
                    <tr>
                        <td><?php echo (int)$rule->min_months; ?> – <?php echo $rule->max_months !== null ? (int)$rule->max_months : '∞'; ?></td>
                        <td><strong><?php echo (int)$rule->days_entitled; ?></strong></td>
                        <td><?php echo htmlspecialchars(vacation_day_count_modes()[$rule->day_count_mode] ?? $rule->day_count_mode); ?></td>
                        <td class="small"><?php echo htmlspecialchars($rule->notes ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($data['agreements'])): ?>
        <p class="text-muted">No hay convenios. Creá el primero con «Nuevo convenio».</p>
        <?php endif; ?>
    </div>
    <div class="col-lg-5">
        <div class="card border shadow-sm border-primary">
            <div class="card-header bg-primary bg-opacity-10"><strong><i class="fas fa-building me-1"></i>Convenio por empresa (default)</strong></div>
            <div class="card-body">
                <p class="small text-muted">Los empleados de esa empresa heredan este convenio, salvo que tengan uno distinto en su ficha.</p>
                <form method="post" action="<?php echo URLROOT; ?>/vacationAdmin/saveCompanyDefault">
                    <?php echo csrf_field(); ?>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Empresa</label>
                        <select name="company_id" class="form-select form-select-sm" required>
                            <?php foreach ($data['companies'] as $co): ?>
                            <option value="<?php echo (int)$co->id; ?>" <?php echo (int)$data['company_id'] === (int)$co->id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($co->name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Convenio para esa empresa</label>
                        <select name="agreement_id" class="form-select form-select-sm" required>
                            <?php foreach ($data['agreements'] as $ag): ?>
                            <option value="<?php echo (int)$ag->id; ?>"
                                <?php
                                $def = $data['defaults'][(int)$data['company_id']] ?? null;
                                echo ($def && (int)$def->id === (int)$ag->id) ? 'selected' : '';
                                ?>>
                                <?php echo htmlspecialchars($ag->name); ?> (<?php echo htmlspecialchars($ag->code); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">Guardar default de empresa</button>
                </form>
                <hr>
                <p class="small fw-semibold mb-2">Resumen actual</p>
                <ul class="small mb-0 list-unstyled">
                    <?php foreach ($data['companies'] as $co): ?>
                    <li class="mb-1 py-1 border-bottom">
                        <span class="text-muted"><?php echo htmlspecialchars($co->name); ?></span><br>
                        <?php
                        $def = $data['defaults'][(int)$co->id] ?? null;
                        if ($def) {
                            echo '<strong>' . htmlspecialchars($def->name) . '</strong> <code>' . htmlspecialchars($def->code) . '</code>';
                        } else {
                            echo '<span class="text-danger">Sin convenio asignado</span>';
                        }
                        ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <?php if (function_exists('vacation_module_ready') && vacation_module_ready()): ?>
        <div class="card border shadow-sm border-success mt-3">
            <div class="card-header bg-success bg-opacity-10">
                <strong><i class="fas fa-bolt me-1"></i>Liquidación masiva anual</strong>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2">
                    Crea el período vigente y calcula vacaciones para <strong>todos los empleados activos</strong>
                    de la empresa seleccionada arriba. Excluye inactivos (despido / renuncia).
                </p>
                <a href="<?php echo URLROOT; ?>/vacationAdmin/liquidateCompanyBatch?company_id=<?php echo (int)$data['company_id']; ?>"
                   class="btn btn-success btn-sm w-100">
                    <i class="fas fa-users-cog me-1"></i>Liquidar empresa completa
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require APPROOT . '/views/inc/footer.php'; ?>
