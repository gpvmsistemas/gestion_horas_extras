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
            Días omitidos por estado del empleado: <?php echo implode(', ', array_map(fn($d) => date('d/m', strtotime($d)), $_c['blocked'])); ?>.
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

<div id="rhLiveConfig" hidden
     data-url="<?php echo URLROOT; ?>/registroHoras/previewData"
     data-org="<?php echo htmlspecialchars($data['org']); ?>"
     data-real="<?php echo $data['realMode'] ? '1' : '0'; ?>"
     data-today="<?php echo date('Y-m-d'); ?>"></div>

<div class="row g-3">
    <div class="col-lg-6 col-xl-5">
        <section class="admin-surface">
            <div class="admin-surface-head">
                <div>
                    <h3 class="admin-surface-title"><i class="fas fa-plus-circle"></i>Cargar horario planificado</h3>
                    <p class="admin-surface-subtitle">Individual o por rango de fechas, con turno partido opcional.</p>
                </div>
            </div>
            <div class="admin-surface-body">
                <?php if ($data['realMode']): ?>
                <form action="<?php echo URLROOT; ?>/registroHoras/store" method="post" id="rhCargaForm">
                    <?php echo csrf_field(); ?>
                <?php else: ?>
                <form action="<?php echo URLROOT; ?>/registroHoras/mockSubmit" method="post" id="rhCargaForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="mock_back" value="carga">
                <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label" for="rhEmployee">Empleado</label>
                        <select name="employee_id" id="rhEmployee" class="form-select" required>
                            <option value="" <?php echo empty($_in['employee_id']) ? 'selected' : ''; ?> disabled>Seleccionar empleado…</option>
                            <?php foreach ($data['employees'] as $emp): ?>
                            <option value="<?php echo (int)$emp->id; ?>"
                                    data-branch="<?php echo htmlspecialchars($emp->branch); ?>"
                                    <?php echo (!empty($_in['employee_id']) && (int)$_in['employee_id'] === $emp->id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp->name . ' — ' . $emp->branch); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="rhStart">Desde</label>
                            <input type="date" name="start_date" id="rhStart" class="form-control" value="<?php echo htmlspecialchars((string)($_in['start_date'] ?? '')); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="rhEnd">Hasta</label>
                            <input type="date" name="end_date" id="rhEnd" class="form-control" value="<?php echo htmlspecialchars((string)($_in['end_date'] ?? '')); ?>" required>
                            <div class="form-text">Se carga el mismo horario para cada día del rango.</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="rhT1s">Entrada</label>
                            <input type="time" name="start_time" id="rhT1s" class="form-control" value="<?php echo htmlspecialchars((string)($_in['start_time'] ?? '08:00')); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="rhT1e">Salida</label>
                            <input type="time" name="end_time" id="rhT1e" class="form-control" value="<?php echo htmlspecialchars((string)($_in['end_time'] ?? '16:00')); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="rhBranch">Sucursal</label>
                            <select name="branch_name" id="rhBranch" class="form-select" required>
                                <option value="" <?php echo empty($_in['branch_name']) ? 'selected' : ''; ?> disabled>Elegir…</option>
                                <?php foreach ($data['branchesByCity'] as $group => $branches): ?>
                                <optgroup label="<?php echo htmlspecialchars($group); ?>">
                                    <?php foreach ($branches as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b); ?>" <?php echo (($_in['branch_name'] ?? '') === $b) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="rhTwoTurns" name="two_turns" value="1" <?php echo !empty($_in['two_turns']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="rhTwoTurns">Segundo turno el mismo día (turno partido)</label>
                    </div>

                    <?php /* Visible por defecto (sin JS queda usable); el JS lo oculta si el switch está apagado. */ ?>
                    <div id="rhSecondTurn" class="border rounded p-3 mb-3 bg-light">
                        <div class="fw-semibold mb-2"><i class="fas fa-clone me-1"></i>Segundo turno</div>
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label class="form-label" for="rhT2s">Entrada</label>
                                <input type="time" name="start_time_2" id="rhT2s" class="form-control" value="<?php echo htmlspecialchars((string)($_in['start_time_2'] ?? '17:00')); ?>">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label" for="rhT2e">Salida</label>
                                <input type="time" name="end_time_2" id="rhT2e" class="form-control" value="<?php echo htmlspecialchars((string)($_in['end_time_2'] ?? '21:00')); ?>">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label" for="rhBranch2">Sucursal (puede ser otra)</label>
                                <select name="branch_name_2" id="rhBranch2" class="form-select">
                                    <option value="">Misma sucursal</option>
                                    <?php foreach ($data['branchesByCity'] as $group => $branches): ?>
                                    <optgroup label="<?php echo htmlspecialchars($group); ?>">
                                        <?php foreach ($branches as $b): ?>
                                        <option value="<?php echo htmlspecialchars($b); ?>" <?php echo (($_in['branch_name_2'] ?? '') === $b) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" id="rhSubmitBtn">
                        <i class="fas fa-save me-1"></i><?php echo $data['realMode'] ? 'Guardar horario' : 'Guardar horario (maqueta)'; ?>
                    </button>
                </form>

                <details class="mt-3">
                    <summary class="fw-semibold" style="cursor:pointer;">¿Cómo se calculan las extras?</summary>
                    <?php if ($data['org'] === 'moderna'): ?>
                    <ul class="mt-2 mb-2 small">
                        <li>Umbral diario acumulado: <strong>8 h</strong> de lunes a viernes, <strong>5 h</strong> sábados y domingos. Lo que excede el umbral cuenta como horas extra.</li>
                        <li><strong>Feriado</strong> (nacional, de la empresa o local de la sucursal): todas las horas de ese bloque son extra y no consumen el umbral.</li>
                        <li>Turno que cruza medianoche: se divide en dos días (hasta 23:59 y desde 00:00), cada uno con su umbral.</li>
                        <li>Con dos turnos el mismo día, el excedente se asigna al turno que lo genera.</li>
                    </ul>
                    <div class="small text-muted mb-1">Sucursales 24 h: <?php echo htmlspecialchars(implode(' · ', moderna_branches_24h())); ?>.</div>
                    <?php else: ?>
                    <ul class="mt-2 mb-2 small">
                        <li>Este módulo carga los <strong>horarios por sucursal</strong>; las horas extra siguen la <strong>clasificación 50 % / 100 % vigente</strong> de la suite (domingos, feriados, sábado tarde y nocturnas).</li>
                        <li>Turnos partidos y turnos que cruzan medianoche quedan soportados desde la carga.</li>
                        <li>Convive con el <a href="<?php echo URLROOT; ?>/admin/weeklyPlanner">Planificador Semanal</a>.</li>
                    </ul>
                    <?php endif; ?>
                    <div class="alert alert-warning py-2 small mb-0">
                        <i class="fas fa-shield-alt me-1"></i>
                        Antes de guardar, el sistema valida estado del empleado (vacaciones, licencia o guardia bloquean esos días) y conflictos con horarios ya cargados, pidiendo confirmación para sobrescribir o agregar.
                    </div>
                </details>
            </div>
        </section>
    </div>

    <div class="col-lg-6 col-xl-7">
        <section class="admin-surface rh-live-sticky" id="rhLive" hidden data-js-only>
            <div class="admin-surface-head">
                <div>
                    <h3 class="admin-surface-title"><i class="fas fa-calendar-check"></i>Vista previa de la carga</h3>
                    <p class="admin-surface-subtitle" id="rhLiveWho">Elegí un empleado y el rango de fechas para ver la vista previa.</p>
                </div>
            </div>
            <div class="admin-surface-body">
                <div id="rhTotals" class="d-flex flex-wrap gap-2 mb-1" aria-live="polite" aria-atomic="true"></div>
                <div class="form-text mb-2">Estimación en vivo. El cálculo definitivo lo hace el servidor al guardar.</div>
                <fieldset id="rhScenario" class="mb-2" hidden>
                    <legend class="visually-hidden">Si hay conflicto</legend>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Escenario ante conflictos">
                        <input type="radio" class="btn-check" name="rh_scenario" id="rhScAppend" value="append" checked>
                        <label class="btn btn-outline-primary" for="rhScAppend">Si hay conflicto: agregar</label>
                        <input type="radio" class="btn-check" name="rh_scenario" id="rhScOverwrite" value="overwrite">
                        <label class="btn btn-outline-primary" for="rhScOverwrite">Sobrescribir</label>
                    </div>
                </fieldset>
                <div id="rhCalendars"></div>
                <div id="rhDayDetail" class="mt-2"></div>
                <div id="rhServerState" class="form-text mt-2" role="status"></div>
                <div class="rh-legend text-muted">
                    <span><i class="fas fa-circle" style="color:var(--clr-primary)"></i>Propuesto</span>
                    <span><i class="fas fa-circle" style="color:#eab308"></i>Con extras</span>
                    <span><i class="fas fa-circle" style="color:var(--clr-danger)"></i>Feriado</span>
                    <span><i class="fas fa-exclamation-triangle" style="color:var(--clr-warning)"></i>Conflicto</span>
                    <span><i class="fas fa-circle" style="color:#9ca3af"></i>Bloqueado</span>
                    <span><i class="fas fa-circle" style="color:var(--clr-warning)"></i>Hoy</span>
                </div>
            </div>
        </section>
        <noscript>
            <section class="admin-surface">
                <div class="admin-surface-head">
                    <div>
                        <h3 class="admin-surface-title"><i class="fas fa-info-circle"></i>Reglas de la carga</h3>
                        <p class="admin-surface-subtitle">La vista previa en vivo requiere JavaScript; el guardado valida todo igual.</p>
                    </div>
                </div>
                <div class="admin-surface-body">
                    <ul class="mb-0 small">
                        <li>Umbral Moderna: 8 h lunes a viernes / 5 h sábados y domingos; feriado ⇒ el bloque es 100 % extra.</li>
                        <li>Los turnos que cruzan medianoche se dividen en dos días.</li>
                        <li>Vacaciones, licencias y guardias bloquean esos días; los conflictos piden confirmación.</li>
                    </ul>
                </div>
            </section>
        </noscript>
    </div>
</div>

<script src="<?php echo URLROOT; ?>/js/registro-horas-carga.js" defer></script>

<?php require APPROOT . '/views/inc/footer.php'; ?>
