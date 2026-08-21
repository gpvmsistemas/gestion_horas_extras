<?php require APPROOT . '/views/inc/header.php'; ?>

<?php $_rhActive = 'duplicar'; require APPROOT . '/views/registro_horas/partials/nav_tabs.php'; ?>
<?php require APPROOT . '/views/registro_horas/partials/mock_banner.php'; ?>

<?php
$_preview = $data['preview'] ?? null;
$_req = $data['previewReq'] ?? null;
$weekdaysEs = [1=>'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
// Nombres de ejemplo para el panel ilustrativo del modo maqueta.
$_dupConflict = $data['employees'][2] ?? $data['employees'][0];
$_dupAbsent   = null;
foreach ($data['employees'] as $_e) {
    if ($_e->state !== 'Activo') { $_dupAbsent = $_e; break; }
}
?>

<div class="row g-3">
    <div class="col-lg-6">
        <section class="admin-surface h-100">
            <div class="admin-surface-head">
                <div>
                    <h3 class="admin-surface-title"><i class="fas fa-clone"></i>Copiar horarios entre semanas</h3>
                    <p class="admin-surface-subtitle">Individual por empleado o masiva; siempre con verificación previa.</p>
                </div>
            </div>
            <div class="admin-surface-body">
                <?php if ($data['realMode']): ?>
                <form action="<?php echo URLROOT; ?>/registroHoras/duplicar" method="post">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="preview">
                <?php else: ?>
                <form action="<?php echo URLROOT; ?>/registroHoras/mockSubmit" method="post">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="mock_back" value="duplicar">
                <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label d-block">Modo</label>
                        <div class="btn-group" role="group" aria-label="Modo de duplicación">
                            <input type="radio" class="btn-check" name="mode" id="modeIndividual" value="individual" <?php echo (!$_req || $_req['mode'] === 'individual') ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-primary" for="modeIndividual"><i class="fas fa-user me-1"></i>Individual</label>
                            <input type="radio" class="btn-check" name="mode" id="modeMasivo" value="masivo" <?php echo ($_req && $_req['mode'] === 'masivo') ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-primary" for="modeMasivo"><i class="fas fa-users me-1"></i>Masiva (todos)</label>
                        </div>
                    </div>

                    <div class="mb-3" id="dupEmployeeField">
                        <label class="form-label">Empleado</label>
                        <select name="employee_id" class="form-select">
                            <?php foreach ($data['employees'] as $emp): ?>
                            <option value="<?php echo (int)$emp->id; ?>" <?php echo ($_req && (int)$_req['employee_id'] === $emp->id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp->name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Semana origen</label>
                            <input type="week" name="src_week" class="form-control" value="<?php echo htmlspecialchars($_req['src_week'] ?? date('o-\WW')); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Semana destino</label>
                            <input type="week" name="dest_week" class="form-control" value="<?php echo htmlspecialchars($_req['dest_week'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="overwrite" value="1" id="dupOverwrite" <?php echo ($_req && $_req['overwrite']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="dupOverwrite">Sobrescribir horarios existentes en el destino</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i><?php echo $data['realMode'] ? 'Verificar antes de duplicar' : 'Verificar antes de duplicar (maqueta)'; ?>
                    </button>
                </form>
            </div>
        </section>
    </div>

    <div class="col-lg-6">
        <section class="admin-surface h-100">
            <div class="admin-surface-head">
                <div>
                    <h3 class="admin-surface-title"><i class="fas fa-clipboard-check"></i>Verificación previa</h3>
                    <p class="admin-surface-subtitle"><?php echo $_preview !== null ? 'Resultado real para la semana destino.' : 'Qué encontrará el sistema en el destino' . ($data['realMode'] ? '.' : ' (ejemplo).'); ?></p>
                </div>
            </div>
            <div class="admin-surface-body">
                <?php if ($_preview !== null): ?>
                    <?php if (empty($_preview)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="far fa-calendar-times fs-4 d-block mb-2"></i>
                        La semana origen no tiene horarios cargados para copiar.
                    </div>
                    <?php else: ?>
                    <div class="table-responsive mb-3">
                        <table class="table table-striped admin-table mb-0">
                            <thead><tr><th>Empleado</th><th>Día destino</th><th>Horarios a copiar</th><th>Estado</th></tr></thead>
                            <tbody>
                                <?php foreach ($_preview as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['employee']->name); ?></td>
                                    <td>
                                        <?php echo date('d/m', strtotime($row['dest_date'])); ?>
                                        <?php if ($row['is_holiday']): ?><span class="badge bg-danger ms-1">Feriado</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php foreach ($row['blocks'] as $b): ?>
                                        <span class="badge bg-light text-dark border me-1"><?php echo $b['start'] . ' – ' . $b['end']; ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'copy'): ?>
                                            <span class="badge bg-success">Se copia</span>
                                        <?php elseif ($row['status'] === 'conflict'): ?>
                                            <span class="badge bg-warning text-dark" title="Ya hay horarios ese día"><?php echo !empty($_req['overwrite']) ? 'Se sobrescribe' : 'Se omite (conflicto)'; ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-info text-dark">Se omite (licencia)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <form action="<?php echo URLROOT; ?>/registroHoras/duplicar" method="post" class="d-flex gap-2">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="execute">
                        <input type="hidden" name="mode" value="<?php echo htmlspecialchars($_req['mode']); ?>">
                        <input type="hidden" name="employee_id" value="<?php echo (int)$_req['employee_id']; ?>">
                        <input type="hidden" name="src_week" value="<?php echo htmlspecialchars($_req['src_week']); ?>">
                        <input type="hidden" name="dest_week" value="<?php echo htmlspecialchars($_req['dest_week']); ?>">
                        <?php if ($_req['overwrite']): ?><input type="hidden" name="overwrite" value="1"><?php endif; ?>
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-copy me-1"></i>Confirmar duplicación</button>
                        <a href="<?php echo URLROOT; ?>/registroHoras/duplicar" class="btn btn-outline-secondary">Cancelar</a>
                    </form>
                    <?php endif; ?>
                <?php else: ?>
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-exclamation-triangle text-warning me-2"></i><?php echo htmlspecialchars($_dupConflict->name); ?> ya tiene horarios el mar 18 y mié 19</span>
                        <span class="badge bg-warning text-dark">Conflicto</span>
                    </li>
                    <?php if ($_dupAbsent): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-umbrella-beach text-info me-2"></i><?php echo htmlspecialchars($_dupAbsent->name); ?> está en <?php echo htmlspecialchars(mb_strtolower($_dupAbsent->state)); ?> en la semana destino</span>
                        <span class="badge bg-info text-dark">Se omite</span>
                    </li>
                    <?php endif; ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-star text-danger me-2"></i>Los feriados del destino se marcan — sus horas se clasifican como extra</span>
                        <span class="badge bg-danger">Feriado</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-check text-success me-2"></i>Los días sin conflicto se copian directo</span>
                        <span class="badge bg-success">OK</span>
                    </li>
                </ul>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var indiv = document.getElementById('modeIndividual');
    var masivo = document.getElementById('modeMasivo');
    var empField = document.getElementById('dupEmployeeField');
    function refresh() {
        if (empField) empField.style.display = (masivo && masivo.checked) ? 'none' : '';
    }
    if (indiv) indiv.addEventListener('change', refresh);
    if (masivo) masivo.addEventListener('change', refresh);
    refresh();
});
</script>

<?php require APPROOT . '/views/inc/footer.php'; ?>
