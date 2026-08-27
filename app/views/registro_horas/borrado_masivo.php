<?php require APPROOT . '/views/inc/header.php'; ?>

<?php $_rhActive = 'borradoMasivo'; require APPROOT . '/views/registro_horas/partials/nav_tabs.php'; ?>
<?php require APPROOT . '/views/registro_horas/partials/mock_banner.php'; ?>

<?php
$_preview = $data['preview'] ?? null;
$_req = $data['previewReq'] ?? null;
$_selBranch = $_req['branch_name'] ?? '';
$_selDates = $_req['dates'] ?? [];
$_weekShort = [1=>'lun','mar','mié','jue','vie','sáb','dom'];
$_fmtH = function ($v) { $s = rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.'); return $s === '' ? '0' : $s; };
?>

<?php if ($_preview !== null): ?>
<section class="admin-surface" style="border-left:4px solid var(--clr-danger);">
    <div class="admin-surface-head">
        <div>
            <h3 class="admin-surface-title"><i class="fas fa-clipboard-check"></i>Verificación previa del borrado masivo</h3>
            <p class="admin-surface-subtitle">
                <?php echo htmlspecialchars($_selBranch); ?> ·
                <?php echo count($_selDates); ?> fecha(s) seleccionada(s)
                (<?php echo date('d/m', strtotime($_selDates[0])); ?>–<?php echo date('d/m', strtotime(end($_selDates))); ?>) ·
                <?php echo (int)$_preview['total']; ?> bloque(s) de <?php echo count($_preview['rows']); ?> colaborador(es)
                — esto <strong>no se puede deshacer</strong>.
            </p>
        </div>
    </div>
    <div class="admin-surface-body">
        <form action="<?php echo URLROOT; ?>/registroHoras/borradoMasivo" method="post" id="rhMassDeleteForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="execute">
            <input type="hidden" name="branch_name" value="<?php echo htmlspecialchars($_selBranch); ?>">
            <?php foreach ($_selDates as $_d): ?>
            <input type="hidden" name="dates[]" value="<?php echo htmlspecialchars($_d); ?>">
            <?php endforeach; ?>

            <div class="mb-3">
                <div class="fw-semibold small text-muted mb-1">Fechas seleccionadas (desmarcá la que quieras excluir):</div>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($_preview['dateCounts'] as $_d => $_n):
                        $_dow = (int)date('N', strtotime($_d));
                        $_hol = $_preview['holidays'][$_d] ?? null;
                    ?>
                    <?php if ($_n > 0): ?>
                    <label class="badge bg-light text-dark border d-inline-flex align-items-center gap-1" style="cursor:pointer;font-weight:500;">
                        <input type="checkbox" class="form-check-input m-0 bm-date" name="include_dates[]" value="<?php echo $_d; ?>" checked>
                        <?php echo $_weekShort[$_dow] . ' ' . date('d/m', strtotime($_d)); ?>
                        <span class="badge bg-danger">×<?php echo $_n; ?></span>
                        <?php if ($_hol): ?><i class="fas fa-star text-danger" title="Feriado: <?php echo htmlspecialchars($_hol); ?>"></i><?php endif; ?>
                    </label>
                    <?php else: ?>
                    <span class="badge bg-light text-muted border" style="font-weight:400;"><?php echo $_weekShort[$_dow] . ' ' . date('d/m', strtotime($_d)); ?> · sin horas</span>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="table-responsive mb-3">
                <table class="table table-striped admin-table mb-0 align-middle">
                    <thead><tr>
                        <th style="width:36px;"><input type="checkbox" class="form-check-input" id="bmAllEmp" checked aria-label="Todos"></th>
                        <th>Colaborador</th><th>Estado</th><th class="text-center">Bloques</th><th class="text-center">Horas</th><th>Detalle por fecha</th><th>Avisos</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($_preview['rows'] as $_r): ?>
                        <tr class="<?php echo $_r['active'] ? '' : 'table-secondary'; ?>">
                            <td><input type="checkbox" class="form-check-input bm-emp" name="include_employee_ids[]" value="<?php echo (int)$_r['user_id']; ?>" checked></td>
                            <td><?php echo htmlspecialchars($_r['name']); ?></td>
                            <td>
                                <span class="badge <?php echo $_r['active'] ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $_r['active'] ? 'Activo' : 'Inactivo'; ?></span>
                            </td>
                            <td class="text-center"><span class="badge bg-danger"><?php echo (int)$_r['count']; ?></span></td>
                            <td class="text-center"><?php echo $_fmtH($_r['minutes'] / 60); ?></td>
                            <td class="small">
                                <?php $_chips = [];
                                foreach ($_r['dates'] as $_d => $_n) {
                                    $_lbl = date('d/m', strtotime($_d)) . ($_n > 1 ? ' ×' . $_n : '');
                                    $_chips[] = isset($_preview['holidays'][$_d]) ? '<span class="text-danger fw-semibold">' . $_lbl . '</span>' : $_lbl;
                                }
                                echo implode(' · ', $_chips); ?>
                            </td>
                            <td class="small">
                                <?php if ($_r['night']): ?><i class="fas fa-moon text-primary me-1" title="Incluye tramos de turnos que cruzan medianoche"></i><?php endif; ?>
                                <?php if (!empty($_r['types']['shift'])): ?><i class="fas fa-calendar-alt text-warning me-1" title="Incluye <?php echo (int)$_r['types']['shift']; ?> turno(s) del planificador"></i><?php endif; ?>
                                <?php if (!empty($_r['types']['overtime'])): ?><i class="fas fa-bolt text-warning" title="Incluye <?php echo (int)$_r['types']['overtime']; ?> bloque(s) de horas extra"></i><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-text mb-3">Desmarcá los colaboradores o fechas que quieras excluir del borrado. Las vacaciones y licencias nunca se tocan.</div>

            <?php $_tailCount = array_sum(array_map('count', $_preview['tails'])); ?>
            <?php if ($_tailCount > 0): ?>
            <div class="alert alert-primary py-2 small mb-3">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" name="delete_night_tails" value="1" id="bmTails" checked>
                    <label class="form-check-label" for="bmTails">
                        <i class="fas fa-moon me-1"></i>Borrar también <strong><?php echo $_tailCount; ?> tramo(s) nocturno(s)</strong> del día siguiente
                        (colas 00:00–fin de turnos que cruzan medianoche en las fechas elegidas; si no, quedan sueltas).
                    </label>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($_preview['intruders'])): ?>
            <div class="alert alert-warning py-2 small mb-3">
                <i class="fas fa-exclamation-triangle me-1"></i>
                Alguna de las fechas seleccionadas incluye tramos 00:00–fin de turnos nocturnos <strong>cargados el día anterior</strong> (fuera de la selección):
                al borrarlos, la cabeza de esos turnos queda suelta el día previo. Excluí la fecha o al colaborador si no corresponde.
            </div>
            <?php endif; ?>

            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label mb-1">Escribí <strong>ELIMINAR</strong> para confirmar</label>
                    <input type="text" name="confirm_text" id="bmConfirmText" class="form-control" autocomplete="off" placeholder="ELIMINAR">
                </div>
                <div class="col-md-7 d-flex gap-2">
                    <?php /* Sin disabled en el HTML: sin JS el servidor valida
                            confirm_text igual; el JS lo deshabilita al cargar. */ ?>
                    <button type="submit" class="btn btn-danger flex-grow-1" id="bmExecuteBtn">
                        <i class="fas fa-trash-alt me-1"></i>Eliminar permanentemente
                    </button>
                    <a href="<?php echo URLROOT; ?>/registroHoras/borradoMasivo" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </div>
        </form>
    </div>
</section>
<?php endif; ?>

<?php if ($data['realMode']): ?>
<form action="<?php echo URLROOT; ?>/registroHoras/borradoMasivo" method="post" id="bmSelectForm">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="preview">
<?php else: ?>
<form action="<?php echo URLROOT; ?>/registroHoras/mockSubmit" method="post" id="bmSelectForm">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="mock_back" value="borradoMasivo">
<?php endif; ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <section class="admin-surface h-100">
                <div class="admin-surface-head">
                    <div>
                        <h3 class="admin-surface-title"><i class="fas fa-eraser"></i>Borrado masivo</h3>
                        <p class="admin-surface-subtitle">Elegí la sucursal y marcá en el calendario los días a eliminar.</p>
                    </div>
                </div>
                <div class="admin-surface-body">
                    <div class="row g-2 mb-2">
                        <div class="col-md-7">
                            <label class="form-label">Sucursal</label>
                            <select name="branch_name" id="bmBranch" class="form-select" required>
                                <option value="" <?php echo $_selBranch === '' ? 'selected' : ''; ?> disabled>Elegir…</option>
                                <?php foreach ($data['branchesByCity'] as $group => $branches): ?>
                                <optgroup label="<?php echo htmlspecialchars($group); ?>">
                                    <?php foreach ($branches as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b); ?>" <?php echo $b === $_selBranch ? 'selected' : ''; ?>><?php echo htmlspecialchars($b); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label d-block">Modo de selección</label>
                            <div class="btn-group btn-group-sm w-100" role="group" aria-label="Modo de selección">
                                <input type="radio" class="btn-check" name="bm_sel_mode" id="bmModoDia" value="single" checked>
                                <label class="btn btn-outline-danger" for="bmModoDia">Individual</label>
                                <input type="radio" class="btn-check" name="bm_sel_mode" id="bmModoSemana" value="week">
                                <label class="btn btn-outline-danger" for="bmModoSemana">Por semana</label>
                            </div>
                        </div>
                    </div>
                    <div class="alert d-none py-2 small" id="bmStatus" role="status"></div>
                    <div id="bmCal" class="d-none"></div>
                    <div class="rh-legend text-muted d-none" id="bmLegend">
                        <span><span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:var(--clr-primary);margin-right:.3rem;"></span>Con horas</span>
                        <span><span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:var(--clr-danger);margin-right:.3rem;"></span>Seleccionado para eliminar</span>
                        <span><i class="fas fa-star text-danger me-1"></i>Feriado</span>
                    </div>
                    <noscript>
                        <div class="row g-2 mt-1">
                            <div class="col-6">
                                <label class="form-label">Desde</label>
                                <input type="date" name="start_date" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Hasta</label>
                                <input type="date" name="end_date" class="form-control">
                                <div class="form-text">Sin JavaScript se usa este rango (máx. 93 días).</div>
                            </div>
                        </div>
                    </noscript>
                </div>
            </section>
        </div>
        <div class="col-lg-5">
            <section class="admin-surface h-100">
                <div class="admin-surface-head">
                    <div>
                        <h3 class="admin-surface-title"><i class="fas fa-list-check"></i>Días a eliminar <span class="badge bg-danger rounded-pill" id="bmCount">0</span></h3>
                        <p class="admin-surface-subtitle">Solo días con horas registradas en la sucursal.</p>
                    </div>
                    <div><button type="button" class="btn btn-sm btn-outline-secondary" id="bmClear"><i class="fas fa-broom me-1"></i>Limpiar</button></div>
                </div>
                <div class="admin-surface-body">
                    <ul class="list-group list-group-flush mb-3" id="bmList" style="max-height:300px;overflow-y:auto;">
                        <li class="list-group-item text-muted fst-italic">No hay días seleccionados.</li>
                    </ul>
                    <div class="alert alert-danger py-2 small">
                        <i class="fas fa-triangle-exclamation me-1"></i><strong>Acción destructiva</strong> — Esta acción elimina permanentemente los bloques de horas
                        seleccionados. Las vacaciones y licencias nunca se tocan. Esta acción no se puede deshacer.
                    </div>
                    <span id="bmHidden"></span>
                    <?php /* Sin disabled en el HTML: sin JS rige el fallback de
                            rango (noscript) y el servidor valida todo igual. */ ?>
                    <button type="submit" class="btn btn-outline-danger w-100" id="bmVerify">
                        <i class="fas fa-search me-1"></i><?php echo $data['realMode'] ? 'Verificar antes de borrar' : 'Verificar (maqueta)'; ?>
                    </button>
                </div>
            </section>
        </div>
    </div>
</form>

<script src="<?php echo URLROOT; ?>/js/rh-tooltip.js"></script>
<script src="<?php echo URLROOT; ?>/js/rh-cal-picker.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var all = document.getElementById('bmAllEmp');
    if (all) {
        all.addEventListener('change', function () {
            document.querySelectorAll('.bm-emp').forEach(function (c) { c.checked = all.checked; });
        });
    }
    var confirmInput = document.getElementById('bmConfirmText');
    var executeBtn = document.getElementById('bmExecuteBtn');
    if (confirmInput && executeBtn) {
        var syncBtn = function () { executeBtn.disabled = confirmInput.value.trim() !== 'ELIMINAR'; };
        confirmInput.addEventListener('input', syncBtn);
        syncBtn();
    }

    <?php if ($data['realMode']): ?>
    // ── Calendario de selección (port de delete_hours_massive) ──
    var URL_DATES = <?php echo json_encode(URLROOT . '/registroHoras/fechasConHoras', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var HOY = <?php echo json_encode(date('Y-m-d')); ?>;
    var INITIAL_DATES = <?php echo json_encode(array_values($_selDates), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var WEEK_SHORT = ['', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'];
    var branchSel = document.getElementById('bmBranch');
    var calBox = document.getElementById('bmCal');
    var status = document.getElementById('bmStatus');
    var legend = document.getElementById('bmLegend');
    var listEl = document.getElementById('bmList');
    var countEl = document.getElementById('bmCount');
    var hiddenBox = document.getElementById('bmHidden');
    var verifyBtn = document.getElementById('bmVerify');
    var picker = null;
    var loadedWindow = null; // {from, to}
    var holidayNames = {};
    // Datos ACUMULADOS entre ventanas: navegar más de ±6 meses recentra la
    // consulta, pero la selección y los días ya cargados no se pierden.
    var cumDates = {};
    var cumHols = {};
    verifyBtn.disabled = true; // con JS activo manda la selección del calendario

    function setStatus(cls, html) {
        status.className = 'alert py-2 small alert-' + cls;
        status.innerHTML = html;
        status.classList.remove('d-none');
    }
    function pad(n) { return String(n).padStart(2, '0'); }
    function dowIso(iso) {
        var p = iso.split('-').map(Number);
        return ((new Date(p[0], p[1] - 1, p[2]).getDay() + 6) % 7) + 1;
    }
    function ddmmyyyy(iso) { return iso.slice(8, 10) + '/' + iso.slice(5, 7) + '/' + iso.slice(0, 4); }
    function windowAround(ym) {
        var p = ym.split('-').map(Number);
        var f = new Date(p[0], p[1] - 1 - 6, 1);
        var t = new Date(p[0], p[1] - 1 + 7, 0);
        return {
            from: f.getFullYear() + '-' + pad(f.getMonth() + 1) + '-01',
            to: t.getFullYear() + '-' + pad(t.getMonth() + 1) + '-' + pad(t.getDate())
        };
    }

    function refreshPanel() {
        var sel = picker ? picker.getSelection('del') : [];
        countEl.textContent = sel.length;
        verifyBtn.disabled = sel.length === 0 || sel.length > 93;
        hiddenBox.innerHTML = '';
        listEl.innerHTML = '';
        if (!sel.length) {
            listEl.innerHTML = '<li class="list-group-item text-muted fst-italic">No hay días seleccionados.</li>';
            return;
        }
        sel.forEach(function (iso) {
            var input = document.createElement('input');
            input.type = 'hidden'; input.name = 'dates[]'; input.value = iso;
            hiddenBox.appendChild(input);
            var li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center py-1';
            var left = document.createElement('span');
            left.innerHTML = '<i class="far fa-calendar-check me-2 text-danger"></i>' + WEEK_SHORT[dowIso(iso)] + ' ' + ddmmyyyy(iso)
                + (holidayNames[iso] ? ' <span class="badge bg-danger-subtle text-danger border">Feriado</span>' : '');
            var x = document.createElement('button');
            x.type = 'button'; x.className = 'btn btn-sm btn-outline-danger py-0 px-1'; x.innerHTML = '&times;';
            x.title = 'Quitar';
            x.addEventListener('click', function () { picker.remove('del', iso); picker.refresh(); });
            li.appendChild(left); li.appendChild(x);
            listEl.appendChild(li);
        });
        if (sel.length > 93) {
            setStatus('warning', 'Máximo 93 fechas por operación: quitá ' + (sel.length - 93) + '.');
        }
    }

    function loadBranch(keepSelection) {
        var b = branchSel.value;
        if (!b) return;
        setStatus('info', '<span class="spinner-border spinner-border-sm me-1"></span>Cargando horarios de la sucursal…');
        // Ventana SIEMPRE explícita: los defaults del servidor se calculan con
        // strtotime('-6 months') y en días 29-31 derivan a otra fecha — la
        // ventana creída y la fetcheada deben ser idénticas.
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
                        groups: { del: { cls: 'pk-sel-del', onlyMarked: true } },
                        activeGroup: function () { return 'del'; },
                        mode: function () {
                            var r = document.querySelector('input[name="bm_sel_mode"]:checked');
                            return r ? r.value : 'single';
                        },
                        onChange: refreshPanel,
                        onMonthChange: function (ym) {
                            // Fuera de la ventana cargada (±6 meses): recentrar y recargar.
                            if (loadedWindow && (ym + '-01' < loadedWindow.from || ym + '-01' > loadedWindow.to)) {
                                loadedWindow = windowAround(ym);
                                loadBranch(true);
                            }
                        }
                    });
                }
                if (!keepSelection) picker.setSelection('del', []);
                picker.setData({ marked: cumDates, holidays: cumHols });
                calBox.classList.remove('d-none');
                legend.classList.remove('d-none');
                var n = Object.keys(cumDates).length;
                setStatus('success', 'Sucursal "' + b.replace(/</g, '&lt;') + '" seleccionada. ' + n + ' día(s) con horas registradas.');
                refreshPanel();
            })
            .catch(function () { setStatus('danger', 'Error de conexión al cargar horarios.'); });
    }
    branchSel.addEventListener('change', function () { loadedWindow = null; loadBranch(false); });
    var clearBtn = document.getElementById('bmClear');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (picker) picker.clear('del');
        });
    }

    // Rehidratación al volver de la verificación.
    if (branchSel.value) {
        loadBranch(true);
        if (INITIAL_DATES.length) {
            var wait = setInterval(function () {
                if (picker) {
                    clearInterval(wait);
                    picker.setSelection('del', INITIAL_DATES);
                    picker.refresh();
                }
            }, 120);
        }
    }
    <?php endif; ?>
});
</script>

<?php require APPROOT . '/views/inc/footer.php'; ?>
