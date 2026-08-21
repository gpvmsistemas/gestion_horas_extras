<?php require APPROOT . '/views/inc/header.php'; ?>

<?php $_rhActive = 'borradoMasivo'; require APPROOT . '/views/registro_horas/partials/nav_tabs.php'; ?>
<?php require APPROOT . '/views/registro_horas/partials/mock_banner.php'; ?>

<?php
$_preview = $data['preview'] ?? null;
$_req = $data['previewReq'] ?? null;
$_selBranch = $_req['branch_name'] ?? '';
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
                <?php echo date('d/m/Y', strtotime($_req['start_date'])); ?> al <?php echo date('d/m/Y', strtotime($_req['end_date'])); ?> ·
                <?php echo (int)$_preview['total']; ?> bloque(s) de <?php echo count($_preview['rows']); ?> colaborador(es)
                — esto <strong>no se puede deshacer</strong>.
            </p>
        </div>
    </div>
    <div class="admin-surface-body">
        <form action="<?php echo URLROOT; ?>/registroHoras/borradoMasivo" method="post" id="rhMassDeleteForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="execute">
            <?php foreach (['branch_name','start_date','end_date'] as $_f): ?>
            <input type="hidden" name="<?php echo $_f; ?>" value="<?php echo htmlspecialchars((string)$_req[$_f]); ?>">
            <?php endforeach; ?>

            <div class="mb-3">
                <div class="fw-semibold small text-muted mb-1">Fechas del rango (desmarcá la que quieras excluir):</div>
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
                        (colas 00:00–fin de turnos que cruzan medianoche cargados en el rango; si no, quedan sueltos).
                    </label>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($_preview['intruders'])): ?>
            <div class="alert alert-warning py-2 small mb-3">
                <i class="fas fa-exclamation-triangle me-1"></i>
                La primera fecha incluye tramos 00:00–fin de turnos nocturnos <strong>cargados el día anterior</strong> (fuera del rango):
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

<div class="row g-3">
    <div class="col-lg-5">
        <section class="admin-surface h-100">
            <div class="admin-surface-head">
                <div>
                    <h3 class="admin-surface-title"><i class="fas fa-eraser"></i>Borrado masivo</h3>
                    <p class="admin-surface-subtitle">Revierte una carga errónea sin recargar nada encima.</p>
                </div>
            </div>
            <div class="admin-surface-body">
                <?php if ($data['realMode']): ?>
                <form action="<?php echo URLROOT; ?>/registroHoras/borradoMasivo" method="post">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="preview">
                <?php else: ?>
                <form action="<?php echo URLROOT; ?>/registroHoras/mockSubmit" method="post">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="mock_back" value="borradoMasivo">
                <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Sucursal</label>
                        <select name="branch_name" class="form-select" required>
                            <option value="" selected disabled>Elegir…</option>
                            <?php foreach ($data['branchesByCity'] as $group => $branches): ?>
                            <optgroup label="<?php echo htmlspecialchars($group); ?>">
                                <?php foreach ($branches as $b): ?>
                                <option value="<?php echo htmlspecialchars($b); ?>" <?php echo $b === $_selBranch ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($b); ?>
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Solo se borran los bloques registrados en esa sucursal.</div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Desde</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($_req['start_date'] ?? ''); ?>" <?php echo $data['realMode'] ? 'required' : ''; ?>>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Hasta</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($_req['end_date'] ?? ''); ?>" <?php echo $data['realMode'] ? 'required' : ''; ?>>
                            <div class="form-text">Máx. 93 días por operación.</div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline-danger w-100">
                        <i class="fas fa-search me-1"></i><?php echo $data['realMode'] ? 'Verificar antes de borrar' : 'Verificar (maqueta)'; ?>
                    </button>
                </form>
            </div>
        </section>
    </div>

    <div class="col-lg-7">
        <section class="admin-surface h-100">
            <div class="admin-surface-head">
                <div>
                    <h3 class="admin-surface-title"><i class="fas fa-shield-alt"></i>Cómo funciona</h3>
                    <p class="admin-surface-subtitle">Acción destructiva con red de seguridad completa.</p>
                </div>
            </div>
            <div class="admin-surface-body">
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-clipboard-check text-primary me-2"></i>Siempre hay verificación previa: qué bloques, de quién y en qué fechas</span>
                        <span class="badge bg-primary">Paso 1</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-user-minus text-primary me-2"></i>Podés excluir colaboradores o fechas puntuales antes de confirmar</span>
                        <span class="badge bg-primary">Paso 2</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-keyboard text-danger me-2"></i>La ejecución exige tipear <strong>ELIMINAR</strong> (validado también en el servidor)</span>
                        <span class="badge bg-danger">Paso 3</span>
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-umbrella-beach text-info me-2"></i>Las <strong>vacaciones y licencias nunca se borran</strong>; solo bloques de horario de trabajo.
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-moon text-primary me-2"></i>Los turnos que cruzan medianoche se detectan: la cola 00:00–fin del día siguiente se ofrece para borrar junto con su cabeza.
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-fingerprint text-muted me-2"></i>Cada borrado masivo queda registrado en la auditoría de la suite.
                    </li>
                </ul>
            </div>
        </section>
    </div>
</div>

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
        var syncBtn = function () {
            executeBtn.disabled = confirmInput.value.trim() !== 'ELIMINAR';
        };
        confirmInput.addEventListener('input', syncBtn);
        syncBtn();
    }
});
</script>

<?php require APPROOT . '/views/inc/footer.php'; ?>
