<?php require APPROOT . '/views/inc/header.php'; ?>

<?php $_rhActive = 'duplicar'; require APPROOT . '/views/registro_horas/partials/nav_tabs.php'; ?>
<?php require APPROOT . '/views/registro_horas/partials/mock_banner.php'; ?>

<?php
$_preview = $data['preview'] ?? null;
$_req = $data['previewReq'] ?? null;
$_mode = $_req['mode'] ?? 'individual';
// Pares tipiados previamente (para rehidratar el armador tras la verificación).
$_pairs = [];
if ($_req && !empty($_req['pair_src'])) {
    foreach ($_req['pair_src'] as $_i => $_ps) {
        $_pairs[] = ['src' => $_ps, 'dest' => $_req['pair_dest'][$_i] ?? ''];
    }
}
// Nombres de ejemplo para el panel ilustrativo del modo maqueta.
$_dupConflict = $data['employees'][2] ?? $data['employees'][0];
$_dupAbsent   = null;
foreach ($data['employees'] as $_e) {
    if ($_e->state !== 'Activo') { $_dupAbsent = $_e; break; }
}
$_isSucursal = $_mode === 'sucursal';
?>

<div class="row g-3">
    <div class="col-lg-5">
        <section class="admin-surface h-100">
            <div class="admin-surface-head">
                <div>
                    <h3 class="admin-surface-title"><i class="fas fa-clone"></i>Duplicar horarios</h3>
                    <p class="admin-surface-subtitle">Semana a semana, pares de fechas libres o masiva por sucursal — siempre con verificación previa.</p>
                </div>
            </div>
            <div class="admin-surface-body">
                <?php if ($data['realMode']): ?>
                <form action="<?php echo URLROOT; ?>/registroHoras/duplicar" method="post" id="dupForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="preview">
                <?php else: ?>
                <form action="<?php echo URLROOT; ?>/registroHoras/mockSubmit" method="post" id="dupForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="mock_back" value="duplicar">
                <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label d-block">Modo</label>
                        <div class="btn-group flex-wrap" role="group" aria-label="Modo de duplicación">
                            <input type="radio" class="btn-check dup-mode" name="mode" id="modeIndividual" value="individual" <?php echo $_mode === 'individual' ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-primary btn-sm" for="modeIndividual"><i class="fas fa-user me-1"></i>Semana · individual</label>
                            <input type="radio" class="btn-check dup-mode" name="mode" id="modeMasivo" value="masivo" <?php echo $_mode === 'masivo' ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-primary btn-sm" for="modeMasivo"><i class="fas fa-users me-1"></i>Semana · todos</label>
                            <input type="radio" class="btn-check dup-mode" name="mode" id="modePares" value="pares" <?php echo $_mode === 'pares' ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-primary btn-sm" for="modePares"><i class="fas fa-random me-1"></i>Pares de fechas</label>
                            <input type="radio" class="btn-check dup-mode" name="mode" id="modeSucursal" value="sucursal" <?php echo $_mode === 'sucursal' ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-primary btn-sm" for="modeSucursal"><i class="fas fa-store me-1"></i>Por sucursal</label>
                        </div>
                    </div>

                    <div class="mb-3 dup-field" data-modes="individual,pares" id="dupEmployeeField">
                        <label class="form-label">Empleado</label>
                        <select name="employee_id" class="form-select">
                            <?php foreach ($data['employees'] as $emp): ?>
                            <option value="<?php echo (int)$emp->id; ?>" <?php echo ($_req && (int)$_req['employee_id'] === $emp->id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp->name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 dup-field" data-modes="sucursal">
                        <label class="form-label">Sucursal origen de los horarios</label>
                        <select name="branch_name" class="form-select">
                            <option value="" selected disabled>Elegir…</option>
                            <?php foreach ($data['branchesByCity'] as $group => $branches): ?>
                            <optgroup label="<?php echo htmlspecialchars($group); ?>">
                                <?php foreach ($branches as $b): ?>
                                <option value="<?php echo htmlspecialchars($b); ?>" <?php echo ($_req && ($_req['branch_name'] ?? '') === $b) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($b); ?>
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Se copia lo trabajado en esa sucursal a todos los empleados que registraron horas ahí en las fechas origen.</div>
                    </div>

                    <div class="row dup-field" data-modes="individual,masivo">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Semana origen</label>
                            <input type="week" name="src_week" class="form-control" value="<?php echo htmlspecialchars($_req['src_week'] ?? date('o-\WW')); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Semana destino</label>
                            <input type="week" name="dest_week" class="form-control" value="<?php echo htmlspecialchars($_req['dest_week'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="mb-3 dup-field" data-modes="pares,sucursal">
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span>Pares de fechas (origen → destino)</span>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="dupAddPair"><i class="fas fa-plus me-1"></i>Agregar par</button>
                        </label>
                        <div id="dupPairRows" class="d-flex flex-column gap-2"></div>
                        <div class="form-text">Cualquier fecha a cualquier fecha; el mismo origen puede repetirse hacia varios destinos. Máx. 31 pares. Los turnos nocturnos se copian por su día de inicio: incluí también el día siguiente si querés copiar la cola 00:00–fin.</div>
                    </div>

                    <div class="form-check mb-3 dup-field" data-modes="individual,masivo,pares">
                        <input class="form-check-input" type="checkbox" name="overwrite" value="1" id="dupOverwrite" <?php echo ($_req && $_req['overwrite']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="dupOverwrite">Sobrescribir horarios existentes en el destino</label>
                    </div>
                    <div class="alert alert-info py-2 small mb-3 dup-field" data-modes="sucursal">
                        <i class="fas fa-hand-pointer me-1"></i>
                        En este modo la sobrescritura se decide <strong>empleado por empleado</strong> en la verificación previa.
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i><?php echo $data['realMode'] ? 'Verificar antes de duplicar' : 'Verificar antes de duplicar (maqueta)'; ?>
                    </button>
                </form>
            </div>
        </section>
    </div>

    <div class="col-lg-7">
        <section class="admin-surface h-100">
            <div class="admin-surface-head">
                <div>
                    <h3 class="admin-surface-title"><i class="fas fa-clipboard-check"></i>Verificación previa</h3>
                    <p class="admin-surface-subtitle"><?php echo $_preview !== null ? 'Resultado real para las fechas destino.' : 'Qué encontrará el sistema en el destino' . ($data['realMode'] ? '.' : ' (ejemplo).'); ?></p>
                </div>
            </div>
            <div class="admin-surface-body">
                <?php if ($_preview !== null): ?>
                    <?php if (empty($_preview)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="far fa-calendar-times fs-4 d-block mb-2"></i>
                        Las fechas origen no tienen horarios<?php echo $_isSucursal ? ' en esa sucursal' : ''; ?> para copiar.
                    </div>
                    <?php else: ?>
                    <form action="<?php echo URLROOT; ?>/registroHoras/duplicar" method="post">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="execute">
                        <input type="hidden" name="mode" value="<?php echo htmlspecialchars($_req['mode']); ?>">
                        <input type="hidden" name="employee_id" value="<?php echo (int)$_req['employee_id']; ?>">
                        <input type="hidden" name="src_week" value="<?php echo htmlspecialchars($_req['src_week']); ?>">
                        <input type="hidden" name="dest_week" value="<?php echo htmlspecialchars($_req['dest_week']); ?>">
                        <input type="hidden" name="branch_name" value="<?php echo htmlspecialchars($_req['branch_name'] ?? ''); ?>">
                        <?php foreach ($_pairs as $_p): ?>
                        <input type="hidden" name="pair_src[]" value="<?php echo htmlspecialchars($_p['src']); ?>">
                        <input type="hidden" name="pair_dest[]" value="<?php echo htmlspecialchars($_p['dest']); ?>">
                        <?php endforeach; ?>
                        <?php if ($_req['overwrite']): ?><input type="hidden" name="overwrite" value="1"><?php endif; ?>

                        <input type="hidden" name="keep_present" value="1">
                        <div class="table-responsive mb-3">
                            <table class="table table-striped admin-table mb-0">
                                <thead><tr>
                                    <th style="width:36px;" title="Mantener"><i class="fas fa-check"></i></th>
                                    <th>Empleado</th><th>Origen</th><th>Destino</th><th>Horarios a copiar</th><th>Estado</th>
                                    <?php if ($_isSucursal): ?><th class="text-center">Sobrescribir</th><?php endif; ?>
                                </tr></thead>
                                <tbody>
                                    <?php foreach ($_preview as $row): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input" name="keep[]"
                                                   value="<?php echo (int)$row['employee']->id . '|' . htmlspecialchars($row['dest_date']); ?>"
                                                   <?php echo $row['status'] === 'blocked' ? 'disabled' : 'checked'; ?>
                                                   title="Mantener esta copia">
                                        </td>
                                        <td><?php echo htmlspecialchars($row['employee']->name); ?></td>
                                        <td class="small text-muted">
                                            <?php echo implode(' + ', array_map(fn($d) => date('d/m', strtotime($d)), $row['src_dates'] ?? [])); ?>
                                        </td>
                                        <td>
                                            <?php echo date('d/m', strtotime($row['dest_date'])); ?>
                                            <?php if ($row['is_holiday']): ?><span class="badge bg-danger ms-1">Feriado</span><?php endif; ?>
                                        </td>
                                        <td>
                                            <?php foreach ($row['blocks'] as $b): ?>
                                            <span class="badge bg-light text-dark border me-1"><?php echo $b['start'] . ' – ' . $b['end']; ?><?php echo $b['branch'] !== '' ? ' · ' . htmlspecialchars($b['branch']) : ''; ?></span>
                                            <?php endforeach; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['status'] === 'copy'): ?>
                                                <span class="badge bg-success">Se copia</span>
                                            <?php elseif ($row['status'] === 'conflict'): ?>
                                                <span class="badge bg-warning text-dark" title="Ya hay horarios ese día"><?php echo (!$_isSucursal && !empty($_req['overwrite'])) ? 'Se sobrescribe' : 'Conflicto'; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-info text-dark">Se omite (<?php echo htmlspecialchars(mb_strtolower($row['status_reason'] ?? 'licencia')); ?>)</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($_isSucursal): ?>
                                        <td class="text-center">
                                            <?php if ($row['status'] === 'conflict'): ?>
                                            <input type="checkbox" class="form-check-input dup-ow-emp" name="overwrite_ids[]"
                                                   value="<?php echo (int)$row['employee']->id; ?>"
                                                   data-emp="<?php echo (int)$row['employee']->id; ?>"
                                                   <?php echo in_array((int)$row['employee']->id, $_req['overwrite_ids'] ?? [], true) ? 'checked' : ''; ?>>
                                            <?php else: ?>
                                            <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-copy me-1"></i>Confirmar duplicación</button>
                            <a href="<?php echo URLROOT; ?>/registroHoras/duplicar" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
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
                        <span><i class="fas fa-umbrella-beach text-info me-2"></i><?php echo htmlspecialchars($_dupAbsent->name); ?> está en <?php echo htmlspecialchars(mb_strtolower($_dupAbsent->state)); ?> en las fechas destino</span>
                        <span class="badge bg-info text-dark">Se omite</span>
                    </li>
                    <?php endif; ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-star text-danger me-2"></i>Los feriados del destino se marcan — sus horas se clasifican como extra</span>
                        <span class="badge bg-danger">Feriado</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-store text-primary me-2"></i>Por sucursal: la sobrescritura se decide empleado por empleado</span>
                        <span class="badge bg-primary">Nuevo</span>
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
    // Mostrar/ocultar campos según el modo elegido.
    function refreshMode() {
        var mode = (document.querySelector('.dup-mode:checked') || {}).value || 'individual';
        document.querySelectorAll('.dup-field').forEach(function (f) {
            f.style.display = f.dataset.modes.split(',').indexOf(mode) >= 0 ? '' : 'none';
        });
    }
    document.querySelectorAll('.dup-mode').forEach(function (r) { r.addEventListener('change', refreshMode); });

    // Armador de pares origen → destino.
    var rows = document.getElementById('dupPairRows');
    var initialPairs = <?php echo json_encode(
        array_values(array_filter($_pairs, fn($p) => $p['src'] !== '' || $p['dest'] !== '')),
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    ); ?>;
    function addPair(src, dest) {
        if (!rows || rows.children.length >= 31) return;
        var div = document.createElement('div');
        div.className = 'input-group input-group-sm';
        div.innerHTML =
            '<input type="date" name="pair_src[]" class="form-control" value="' + (src || '') + '" aria-label="Fecha origen">' +
            '<span class="input-group-text"><i class="fas fa-arrow-right"></i></span>' +
            '<input type="date" name="pair_dest[]" class="form-control" value="' + (dest || '') + '" aria-label="Fecha destino">' +
            '<button type="button" class="btn btn-outline-danger dup-del-pair" title="Quitar par"><i class="fas fa-times"></i></button>';
        div.querySelector('.dup-del-pair').addEventListener('click', function () {
            div.remove();
            if (rows.children.length === 0) addPair('', '');
        });
        rows.appendChild(div);
    }
    var addBtn = document.getElementById('dupAddPair');
    if (addBtn) addBtn.addEventListener('click', function () { addPair('', ''); });
    if (rows) {
        if (initialPairs.length) {
            initialPairs.forEach(function (p) { addPair(p.src, p.dest); });
        } else {
            addPair('', '');
        }
    }

    // Por sucursal: sincronizar los checkboxes de sobrescritura del mismo empleado.
    document.querySelectorAll('.dup-ow-emp').forEach(function (c) {
        c.addEventListener('change', function () {
            document.querySelectorAll('.dup-ow-emp[data-emp="' + c.dataset.emp + '"]').forEach(function (o) { o.checked = c.checked; });
        });
    });

    refreshMode();
});
</script>

<?php require APPROOT . '/views/inc/footer.php'; ?>
