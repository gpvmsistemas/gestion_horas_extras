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
                        <select name="branch_name" id="dupBranch" class="form-select">
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

                    <div class="mb-3 dup-field" data-modes="pares">
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span>Pares de fechas (origen → destino)</span>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="dupAddPair"><i class="fas fa-plus me-1"></i>Agregar par</button>
                        </label>
                        <div id="dupPairRows" class="d-flex flex-column gap-2"></div>
                        <div class="form-text">Cualquier fecha a cualquier fecha; el mismo origen puede repetirse hacia varios destinos. Máx. 31 pares. Los turnos nocturnos se copian por su día de inicio: incluí también el día siguiente si querés copiar la cola 00:00–fin.</div>
                    </div>

                    <div class="mb-3 dup-field" data-modes="sucursal" id="dupCalWrap">
                        <div class="alert d-none py-2 small" id="dupStatus" role="status"></div>
                        <div class="row g-2 mb-2">
                            <div class="col-sm-6">
                                <label class="form-label d-block mb-1">Selección</label>
                                <div class="btn-group btn-group-sm w-100" role="group" aria-label="Modo de selección">
                                    <input type="radio" class="btn-check" name="dup_sel_mode" id="dupSelDia" value="single" checked>
                                    <label class="btn btn-outline-primary" for="dupSelDia">Uno a uno</label>
                                    <input type="radio" class="btn-check" name="dup_sel_mode" id="dupSelSemana" value="week">
                                    <label class="btn btn-outline-primary" for="dupSelSemana">Por semana</label>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label d-block mb-1">Marcando</label>
                                <button type="button" class="btn btn-sm w-100 btn-primary" id="dupToggleDest">Marcar días DESTINO</button>
                            </div>
                        </div>
                        <div id="dupCal" class="d-none"></div>
                        <div class="rh-legend text-muted d-none" id="dupLegend">
                            <span><span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:var(--clr-primary);margin-right:.3rem;"></span>Fuente</span>
                            <span><span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#198754;margin-right:.3rem;"></span>Destino</span>
                            <span><i class="fas fa-star text-danger me-1"></i>Feriado</span>
                        </div>
                        <div class="row g-2 mt-1">
                            <div class="col-sm-6">
                                <div class="fw-semibold small">Fuente <span class="badge bg-primary rounded-pill" id="dupCountSrc">0</span></div>
                                <ul class="list-group list-group-flush small" id="dupListSrc" style="max-height:170px;overflow-y:auto;">
                                    <li class="list-group-item text-muted fst-italic py-1">No hay días FUENTE seleccionados.</li>
                                </ul>
                            </div>
                            <div class="col-sm-6">
                                <div class="fw-semibold small">Destino <span class="badge rounded-pill" style="background:#198754;" id="dupCountDest">0</span></div>
                                <ul class="list-group list-group-flush small" id="dupListDest" style="max-height:170px;overflow-y:auto;">
                                    <li class="list-group-item text-muted fst-italic py-1">No hay días DESTINO seleccionados.</li>
                                </ul>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div class="form-text mt-0">Fuente: solo días con horas en la sucursal. Los nocturnos se copian por su día de inicio.</div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="dupClearCal"><i class="fas fa-broom me-1"></i>Limpiar</button>
                        </div>
                        <span id="dupPairHidden"></span>
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

    <?php if ($data['realMode']): ?>
    // ── Modo POR SUCURSAL: calendario FUENTE/DESTINO con días marcados ──
    // (port de duplicate_hours_massive.js de hoursapp sobre RhCalPicker)
    (function () {
        var URL_DATES = <?php echo json_encode(URLROOT . '/registroHoras/fechasConHoras', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        var HOY = <?php echo json_encode(date('Y-m-d')); ?>;
        var WEEK_SHORT = ['', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'];
        var form = document.getElementById('dupForm');
        var branchSel = document.getElementById('dupBranch');
        var calBox = document.getElementById('dupCal');
        var status = document.getElementById('dupStatus');
        var legend = document.getElementById('dupLegend');
        var toggleBtn = document.getElementById('dupToggleDest');
        var pairBox = document.getElementById('dupPairHidden');
        if (!form || !branchSel || !calBox || typeof RhCalPicker === 'undefined') return;

        var picker = null;
        var modoDestino = false;
        var holidayNames = {};
        // Datos ACUMULADOS entre ventanas (navegar lejos no pierde selección).
        var cumDates = {};
        var cumHols = {};
        var pad = function (n) { return String(n).padStart(2, '0'); };
        function dowIso(iso) {
            var p = iso.split('-').map(Number);
            return ((new Date(p[0], p[1] - 1, p[2]).getDay() + 6) % 7) + 1;
        }
        function addDaysIso(iso, n) {
            var p = iso.split('-').map(Number);
            var d = new Date(p[0], p[1] - 1, p[2] + n);
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        }
        function mondayOf(iso) { return addDaysIso(iso, 1 - dowIso(iso)); }
        function ddmmyyyy(iso) { return iso.slice(8, 10) + '/' + iso.slice(5, 7) + '/' + iso.slice(0, 4); }
        function aviso(titulo, texto, icono) {
            if (window.Swal) Swal.fire(titulo, texto, icono || 'warning');
            else alert(titulo + ': ' + texto);
        }
        function selMode() {
            var r = document.querySelector('input[name="dup_sel_mode"]:checked');
            return r ? r.value : 'single';
        }
        function setStatus(cls, html) {
            status.className = 'alert py-2 small alert-' + cls;
            status.innerHTML = html;
            status.classList.remove('d-none');
        }
        function refreshToggle() {
            toggleBtn.textContent = modoDestino ? 'Volver a seleccionar FUENTE' : 'Marcar días DESTINO';
            toggleBtn.classList.toggle('btn-primary', !modoDestino);
            toggleBtn.classList.toggle('btn-success', modoDestino);
        }
        toggleBtn.addEventListener('click', function () {
            modoDestino = !modoDestino;
            // Volver a FUENTE descarta los destinos (comportamiento canónico).
            if (!modoDestino && picker) {
                picker.setSelection('dest', []);
                picker.refresh();
            }
            refreshToggle();
        });

        function renderLists() {
            if (!picker) return;
            var srcs = picker.getSelection('src');
            var dests = picker.getSelection('dest');
            document.getElementById('dupCountSrc').textContent = srcs.length;
            document.getElementById('dupCountDest').textContent = dests.length;
            var mk = function (listEl, items, group, icon, showHol) {
                listEl.innerHTML = '';
                if (!items.length) {
                    listEl.innerHTML = '<li class="list-group-item text-muted fst-italic py-1">No hay días ' + (group === 'src' ? 'FUENTE' : 'DESTINO') + ' seleccionados.</li>';
                    return;
                }
                items.forEach(function (iso) {
                    var li = document.createElement('li');
                    li.className = 'list-group-item d-flex justify-content-between align-items-center py-1';
                    li.innerHTML = '<span><i class="' + icon + ' me-1"></i>' + WEEK_SHORT[dowIso(iso)] + ' ' + ddmmyyyy(iso)
                        + ((showHol && holidayNames[iso]) ? ' <span class="badge bg-danger-subtle text-danger border">Feriado</span>' : '') + '</span>';
                    var x = document.createElement('button');
                    x.type = 'button'; x.className = 'btn btn-sm btn-outline-secondary py-0 px-1'; x.innerHTML = '&times;'; x.title = 'Quitar';
                    x.addEventListener('click', function () { picker.remove(group, iso); picker.refresh(); });
                    li.appendChild(x);
                    listEl.appendChild(li);
                });
            };
            mk(document.getElementById('dupListSrc'), srcs, 'src', 'far fa-calendar-check text-primary', false);
            mk(document.getElementById('dupListDest'), dests, 'dest', 'far fa-calendar-plus text-success', true);
        }

        // Semántica canónica de clicks (delegada al picker).
        function onDayClick(iso, api) {
            var mode = selMode();
            if (!modoDestino) {
                if (mode === 'week') {
                    // La semana FUENTE reemplaza toda la selección y vacía destinos.
                    var days = api.weekDaysOf(iso).filter(api.isMarked);
                    if (!days.length) return true;
                    api.setSelection('src', days);
                    api.setSelection('dest', []);
                } else {
                    if (api.has('src', iso)) api.remove('src', iso);
                    else if (api.isMarked(iso)) { api.remove('dest', iso); api.add('src', iso); }
                }
                return true;
            }
            // Modo DESTINO
            if (mode === 'week') {
                var srcs = picker.getSelection('src');
                if (!srcs.length) {
                    aviso('Falta la fuente', 'Primero seleccioná la semana FUENTE (en modo por semana).', 'error');
                    return true;
                }
                var week = api.weekDaysOf(iso);
                var anySel = week.some(function (d) { return api.has('dest', d); });
                if (anySel) {
                    week.forEach(function (d) { api.remove('dest', d); });
                } else {
                    var monday = mondayOf(iso);
                    srcs.forEach(function (s) {
                        var d = addDaysIso(monday, dowIso(s) - 1);
                        if (!api.has('src', d)) api.add('dest', d);
                    });
                }
            } else {
                if (api.has('dest', iso)) api.remove('dest', iso);
                else if (!api.has('src', iso)) api.add('dest', iso);
            }
            return true;
        }

        var loadedWindow = null;
        function windowAround(ym) {
            var p = ym.split('-').map(Number);
            var f = new Date(p[0], p[1] - 1 - 6, 1);
            var t = new Date(p[0], p[1] - 1 + 7, 0);
            return { from: f.getFullYear() + '-' + pad(f.getMonth() + 1) + '-01', to: t.getFullYear() + '-' + pad(t.getMonth() + 1) + '-' + pad(t.getDate()) };
        }
        function loadBranch(keepSelection) {
            var b = branchSel.value;
            if (!b) return;
            setStatus('info', '<span class="spinner-border spinner-border-sm me-1"></span>Cargando horarios de la sucursal…');
            // Ventana SIEMPRE explícita (los defaults del servidor derivan en días 29-31).
            if (!loadedWindow) loadedWindow = windowAround(HOY.slice(0, 7));
            var qs = URL_DATES + '?branch=' + encodeURIComponent(b) + '&from=' + loadedWindow.from + '&to=' + loadedWindow.to;
            fetch(qs, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok) {
                        setStatus('danger', data && data.error === 'schema' ? 'El módulo no está migrado en esta base.' : 'Error al cargar horarios.');
                        return;
                    }
                    if (!keepSelection) { cumDates = {}; cumHols = {}; }
                    Object.assign(cumDates, data.dates || {});
                    Object.assign(cumHols, data.holidays || {});
                    holidayNames = cumHols;
                    if (!picker) {
                        picker = RhCalPicker(calBox, {
                            month: HOY.slice(0, 7),
                            today: HOY,
                            groups: {
                                src:  { cls: 'pk-sel-src',  onlyMarked: true },
                                dest: { cls: 'pk-sel-dest', onlyMarked: false }
                            },
                            activeGroup: function () { return modoDestino ? 'dest' : 'src'; },
                            mode: selMode,
                            onDayClick: onDayClick,
                            onChange: renderLists,
                            onMonthChange: function (ym) {
                                if (loadedWindow && (ym + '-01' < loadedWindow.from || ym + '-01' > loadedWindow.to)) {
                                    loadedWindow = windowAround(ym);
                                    loadBranch(true);
                                }
                            }
                        });
                    }
                    if (!keepSelection) { picker.setSelection('src', []); picker.setSelection('dest', []); }
                    picker.setData({ marked: cumDates, holidays: cumHols });
                    calBox.classList.remove('d-none');
                    legend.classList.remove('d-none');
                    setStatus('success', 'Sucursal "' + b.replace(/</g, '&lt;') + '" seleccionada. ' + Object.keys(cumDates).length + ' día(s) con horas registradas.');
                    renderLists();
                })
                .catch(function () { setStatus('danger', 'Error de conexión al cargar horarios.'); });
        }
        branchSel.addEventListener('change', function () { loadedWindow = null; loadBranch(false); });
        var clearCalBtn = document.getElementById('dupClearCal');
        if (clearCalBtn) {
            clearCalBtn.addEventListener('click', function () {
                if (picker) picker.clear(); // vacía fuente y destino
            });
        }

        // Emparejamiento fuente→destino (cronológico) e inyección de pair_src[]/pair_dest[].
        function buildPairs() {
            var srcs = picker ? picker.getSelection('src') : [];
            var dests = picker ? picker.getSelection('dest') : [];
            if (!srcs.length || !dests.length) {
                aviso('Selección incompleta', 'Seleccioná al menos un día FUENTE y un día DESTINO.', 'warning');
                return null;
            }
            var pairs = [];
            if (selMode() === 'week') {
                // Semana(s) fuente replicadas a cada semana destino por día de semana.
                var destWeeks = {};
                dests.forEach(function (d) { destWeeks[mondayOf(d)] = true; });
                Object.keys(destWeeks).sort().forEach(function (monday) {
                    srcs.forEach(function (s) {
                        var d = addDaysIso(monday, dowIso(s) - 1);
                        if (dests.indexOf(d) >= 0 && d !== s) pairs.push([s, d]);
                    });
                });
                if (!pairs.length) {
                    aviso('Sin combinaciones', 'No hay pares fuente–destino válidos.', 'info');
                    return null;
                }
            } else {
                if (srcs.length === 1) {
                    dests.forEach(function (d) { if (d !== srcs[0]) pairs.push([srcs[0], d]); });
                } else if (srcs.length === dests.length) {
                    for (var i = 0; i < srcs.length; i++) {
                        if (srcs[i] !== dests[i]) pairs.push([srcs[i], dests[i]]);
                    }
                } else {
                    aviso('Cantidades distintas', 'Para "Uno a uno" elegí un único día FUENTE con varios DESTINO, o la misma cantidad de cada uno.', 'error');
                    return null;
                }
                if (!pairs.length) {
                    aviso('Sin combinaciones', 'No hay pares fuente–destino válidos (origen y destino no pueden ser el mismo día).', 'info');
                    return null;
                }
            }
            if (pairs.length > 31) {
                aviso('Demasiados pares', 'Máximo 31 pares por operación: reducí la selección (' + pairs.length + ').', 'warning');
                return null;
            }
            return pairs;
        }
        form.addEventListener('submit', function (ev) {
            var mode = (document.querySelector('.dup-mode:checked') || {}).value;
            var paresRows = document.getElementById('dupPairRows');
            if (mode !== 'sucursal') {
                // Sin restos del calendario en los otros modos.
                pairBox.innerHTML = '';
                return;
            }
            ev.preventDefault();
            if (!branchSel.value) {
                aviso('Falta la sucursal', 'Elegí la sucursal cuyos horarios querés duplicar.', 'warning');
                return;
            }
            var pairs = buildPairs();
            if (!pairs) return;
            // Las filas del armador de pares (modo "Pares") están ocultas por
            // CSS pero seguirían viajando en el POST: se deshabilitan justo
            // antes de enviar para que SOLO los pares del calendario cuenten.
            if (paresRows) {
                paresRows.querySelectorAll('input').forEach(function (i) { i.disabled = true; });
            }
            pairBox.innerHTML = '';
            pairs.forEach(function (p) {
                var a = document.createElement('input');
                a.type = 'hidden'; a.name = 'pair_src[]'; a.value = p[0];
                var b = document.createElement('input');
                b.type = 'hidden'; b.name = 'pair_dest[]'; b.value = p[1];
                pairBox.appendChild(a); pairBox.appendChild(b);
            });
            form.submit();
        });

        // Rehidratación al volver de la verificación en modo sucursal.
        var initialMode = (document.querySelector('.dup-mode:checked') || {}).value;
        if (initialMode === 'sucursal' && branchSel.value) {
            refreshToggle();
            loadBranch(true);
            if (initialPairs.length) {
                var srcSet = {}, destSet = {};
                initialPairs.forEach(function (p) { if (p.src) srcSet[p.src] = 1; if (p.dest) destSet[p.dest] = 1; });
                var wait = setInterval(function () {
                    if (picker) {
                        clearInterval(wait);
                        picker.setSelection('src', Object.keys(srcSet).sort());
                        picker.setSelection('dest', Object.keys(destSet).sort());
                        picker.refresh();
                    }
                }, 120);
            }
        } else {
            refreshToggle();
        }
    })();
    <?php endif; ?>
});
</script>
<script src="<?php echo URLROOT; ?>/js/rh-tooltip.js"></script>
<script src="<?php echo URLROOT; ?>/js/rh-cal-picker.js"></script>

<?php require APPROOT . '/views/inc/footer.php'; ?>
