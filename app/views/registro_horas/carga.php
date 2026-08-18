<?php require APPROOT . '/views/inc/header.php'; ?>

<?php $_rhActive = 'carga'; require APPROOT . '/views/registro_horas/partials/nav_tabs.php'; ?>
<?php require APPROOT . '/views/registro_horas/partials/mock_banner.php'; ?>

<?php $_c = $data['conflict'] ?? null; $_in = $_c['input'] ?? []; ?>
<?php if ($_c): ?>
<section class="admin-surface" style="border-left:4px solid var(--clr-warning);">
    <div class="admin-surface-head">
        <div>
            <h3 class="admin-surface-title"><i class="fas fa-exclamation-triangle"></i>Verificación: ya hay horarios en el rango</h3>
            <p class="admin-surface-subtitle"><?php echo htmlspecialchars($_c['employee']->full_name); ?> tiene horas cargadas en <?php echo count($_c['conflicts']); ?> de los días elegidos. Decidí cómo continuar.</p>
        </div>
    </div>
    <div class="admin-surface-body">
        <div class="table-responsive mb-3">
            <table class="table table-striped admin-table mb-0">
                <thead><tr><th>Día en conflicto</th><th>Horarios ya cargados</th></tr></thead>
                <tbody>
                    <?php foreach ($_c['conflicts'] as $cDate => $cBlocks): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($cDate)); ?></td>
                        <td>
                            <?php foreach ($cBlocks as $cb): ?>
                            <span class="badge bg-light text-dark border me-1"><?php echo substr($cb->start_time, 0, 5) . ' – ' . substr($cb->end_time, 0, 5); ?><?php echo $cb->branch_name ? ' · ' . htmlspecialchars($cb->branch_name) : ''; ?></span>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($_c['blocked'])): ?>
        <div class="alert alert-warning py-2 small mb-3">
            <i class="fas fa-umbrella-beach me-1"></i>
            Días omitidos por vacaciones/licencia: <?php echo implode(', ', array_map(fn($d) => date('d/m', strtotime($d)), $_c['blocked'])); ?>.
        </div>
        <?php endif; ?>
        <form action="<?php echo URLROOT; ?>/registroHoras/store" method="post" class="d-flex flex-wrap gap-2 align-items-center">
            <?php echo csrf_field(); ?>
            <?php foreach (['employee_id','start_date','end_date','start_time','end_time','branch_name','start_time_2','end_time_2','branch_name_2'] as $_f): ?>
            <input type="hidden" name="<?php echo $_f; ?>" value="<?php echo htmlspecialchars((string)($_in[$_f] ?? '')); ?>">
            <?php endforeach; ?>
            <?php if (!empty($_in['two_turns'])): ?><input type="hidden" name="two_turns" value="1"><?php endif; ?>
            <button type="submit" name="confirm_mode" value="overwrite" class="btn btn-warning">
                <i class="fas fa-sync-alt me-1"></i>Sobrescribir esos días
            </button>
            <button type="submit" name="confirm_mode" value="append" class="btn btn-outline-primary">
                <i class="fas fa-plus me-1"></i>Agregar como turno adicional
            </button>
            <a href="<?php echo URLROOT; ?>/registroHoras/carga" class="btn btn-outline-secondary">Cancelar</a>
        </form>
    </div>
</section>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <section class="admin-surface h-100">
            <div class="admin-surface-head">
                <div>
                    <h3 class="admin-surface-title"><i class="fas fa-plus-circle"></i>Cargar horario planificado</h3>
                    <p class="admin-surface-subtitle">Individual o por rango de fechas, con turno partido opcional.</p>
                </div>
            </div>
            <div class="admin-surface-body">
                <?php if ($data['realMode']): ?>
                <form action="<?php echo URLROOT; ?>/registroHoras/store" method="post">
                    <?php echo csrf_field(); ?>
                <?php else: ?>
                <form action="<?php echo URLROOT; ?>/registroHoras/mockSubmit" method="post">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="mock_back" value="carga">
                <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Empleado</label>
                        <select name="employee_id" class="form-select" required>
                            <option value="" selected disabled>Seleccionar empleado…</option>
                            <?php foreach ($data['employees'] as $emp): ?>
                            <option value="<?php echo (int)$emp->id; ?>">
                                <?php echo htmlspecialchars($emp->name . ' — ' . $emp->branch); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Desde</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hasta</label>
                            <input type="date" name="end_date" class="form-control" required>
                            <div class="form-text">Se carga el mismo horario para cada día del rango.</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Entrada</label>
                            <input type="time" name="start_time" class="form-control" value="08:00" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Salida</label>
                            <input type="time" name="end_time" class="form-control" value="16:00" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Sucursal</label>
                            <select name="branch_name" class="form-select" required>
                                <option value="" selected disabled>Elegir…</option>
                                <?php foreach ($data['branchesByCity'] as $group => $branches): ?>
                                <optgroup label="<?php echo htmlspecialchars($group); ?>">
                                    <?php foreach ($branches as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b); ?>"><?php echo htmlspecialchars($b); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="rhTwoTurns" name="two_turns" value="1">
                        <label class="form-check-label" for="rhTwoTurns">Segundo turno el mismo día (turno partido)</label>
                    </div>

                    <div id="rhSecondTurn" class="border rounded p-3 mb-3 bg-light" style="display:none;">
                        <div class="fw-semibold mb-2"><i class="fas fa-clone me-1"></i>Segundo turno</div>
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Entrada</label>
                                <input type="time" name="start_time_2" class="form-control" value="17:00">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Salida</label>
                                <input type="time" name="end_time_2" class="form-control" value="21:00">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Sucursal (puede ser otra)</label>
                                <select name="branch_name_2" class="form-select">
                                    <option value="" selected>Misma sucursal</option>
                                    <?php foreach ($data['branchesByCity'] as $group => $branches): ?>
                                    <optgroup label="<?php echo htmlspecialchars($group); ?>">
                                        <?php foreach ($branches as $b): ?>
                                        <option value="<?php echo htmlspecialchars($b); ?>"><?php echo htmlspecialchars($b); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save me-1"></i><?php echo $data['realMode'] ? 'Guardar horario' : 'Guardar horario (maqueta)'; ?>
                    </button>
                </form>
            </div>
        </section>
    </div>

    <div class="col-lg-5">
        <?php if ($data['org'] === 'moderna'): ?>
        <section class="admin-surface">
            <div class="admin-surface-head">
                <div>
                    <h3 class="admin-surface-title"><i class="fas fa-info-circle"></i>Cálculo de extras (lógica Moderna)</h3>
                    <p class="admin-surface-subtitle">Reglas que aplicará el guardado real.</p>
                </div>
            </div>
            <div class="admin-surface-body">
                <ul class="mb-3">
                    <li>Umbral diario acumulado: <strong>8 h</strong> de lunes a viernes, <strong>5 h</strong> sábados y domingos. Lo que excede el umbral cuenta como horas extra.</li>
                    <li><strong>Feriado</strong> (nacional o local de la ciudad): todas las horas del día son extra.</li>
                    <li>Turno que cruza medianoche: se divide en dos días (hasta 23:59 y desde 00:00), cada uno con su umbral.</li>
                    <li>Con dos turnos el mismo día, el excedente se asigna al turno que lo genera.</li>
                </ul>
                <div class="alert alert-warning mb-0 py-2 small">
                    <i class="fas fa-shield-alt me-1"></i>
                    Antes de guardar, el sistema validará: estado del empleado (vacaciones/licencia bloquean el rango) y conflictos con horarios ya cargados, pidiendo confirmación para sobrescribir.
                </div>
            </div>
        </section>

        <section class="admin-surface">
            <div class="admin-surface-head">
                <div>
                    <h3 class="admin-surface-title"><i class="fas fa-store"></i>Sucursales 24 h</h3>
                    <p class="admin-surface-subtitle">Cobertura de medianoche en grillas y reportes.</p>
                </div>
            </div>
            <div class="admin-surface-body">
                <?php foreach (moderna_branches_24h() as $b24): ?>
                <span class="badge bg-secondary me-1 mb-1"><?php echo htmlspecialchars($b24); ?></span>
                <?php endforeach; ?>
            </div>
        </section>
        <?php else: ?>
        <section class="admin-surface">
            <div class="admin-surface-head">
                <div>
                    <h3 class="admin-surface-title"><i class="fas fa-info-circle"></i>Registro de horas para Paviotti/Ecofarma</h3>
                    <p class="admin-surface-subtitle">El módulo que le faltaba a la suite.</p>
                </div>
            </div>
            <div class="admin-surface-body">
                <ul class="mb-3">
                    <li>Suma la <strong>carga de horarios por sucursal</strong> (individual, por rango, duplicada y masiva).</li>
                    <li>Convive con el <a href="<?php echo URLROOT; ?>/admin/weeklyPlanner">Planificador Semanal</a>: la sucursal funciona como agrupador operativo dentro de cada empresa.</li>
                    <li>Las <strong>horas extra siguen la clasificación 50 % / 100 % vigente</strong> de la suite (domingos, feriados, sábado tarde y nocturnas) — este módulo no la cambia.</li>
                    <li>Turnos partidos y turnos que cruzan medianoche quedan soportados desde la carga.</li>
                </ul>
                <div class="alert alert-warning mb-0 py-2 small">
                    <i class="fas fa-shield-alt me-1"></i>
                    Antes de guardar, el sistema validará licencias/vacaciones aprobadas del empleado y conflictos con horarios ya cargados, pidiendo confirmación para sobrescribir.
                </div>
            </div>
        </section>
        <section class="admin-surface">
            <div class="admin-surface-head">
                <div>
                    <h3 class="admin-surface-title"><i class="fas fa-store"></i>Sucursales (ejemplo)</h3>
                    <p class="admin-surface-subtitle">En la integración saldrán de las empresas y áreas reales.</p>
                </div>
            </div>
            <div class="admin-surface-body">
                <?php foreach ($data['branchesByCity'] as $group => $branches): ?>
                    <?php foreach ($branches as $b): ?>
                    <span class="badge bg-secondary me-1 mb-1"><?php echo htmlspecialchars($b); ?></span>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var chk = document.getElementById('rhTwoTurns');
    var box = document.getElementById('rhSecondTurn');
    if (chk && box) {
        chk.addEventListener('change', function () {
            box.style.display = chk.checked ? '' : 'none';
        });
    }
});
</script>

<?php require APPROOT . '/views/inc/footer.php'; ?>
