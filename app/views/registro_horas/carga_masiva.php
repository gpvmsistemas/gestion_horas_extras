<?php require APPROOT . '/views/inc/header.php'; ?>

<?php $_rhActive = 'cargaMasiva'; require APPROOT . '/views/registro_horas/partials/nav_tabs.php'; ?>
<?php require APPROOT . '/views/registro_horas/partials/mock_banner.php'; ?>

<?php
$_preview = $data['preview'] ?? null;
$_req = $data['previewReq'] ?? null;
$_firstGroup   = array_key_first($data['branchesByCity']);
$_firstBranch  = $data['branchesByCity'][$_firstGroup][0] ?? '';
$_selBranch    = $_req['branch_name'] ?? $_firstBranch;
$_conflictEmp  = $data['employees'][2] ?? null;
?>

<?php if ($_preview !== null): ?>
<section class="admin-surface" style="border-left:4px solid var(--clr-warning);">
    <div class="admin-surface-head">
        <div>
            <h3 class="admin-surface-title"><i class="fas fa-clipboard-check"></i>Verificación previa de la carga masiva</h3>
            <p class="admin-surface-subtitle">
                <?php echo htmlspecialchars($_req['branch_name']); ?> ·
                <?php echo date('d/m', strtotime($_req['start_date'])); ?> al <?php echo date('d/m', strtotime($_req['end_date'])); ?> ·
                <?php echo htmlspecialchars($_req['start_time'] . ' – ' . $_req['end_time']); ?>
            </p>
        </div>
    </div>
    <div class="admin-surface-body">
        <div class="table-responsive mb-3">
            <table class="table table-striped admin-table mb-0">
                <thead><tr><th>Empleado</th><th class="text-center">Días a cargar</th><th class="text-center">Con conflicto</th><th class="text-center">Licencia/vacaciones</th><th class="text-center">Feriados</th><th>Resultado esperado</th></tr></thead>
                <tbody>
                    <?php foreach ($_preview as $row):
                        $total = count($row['dates']);
                        $nConf = count($row['conflict']);
                        $nBlock = count($row['blocked']);
                        $nHoli = count($row['holidays'] ?? []);
                        $nSave = $total - $nBlock - (!$_req['overwrite'] ? $nConf : 0);
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['employee']->name); ?></td>
                        <td class="text-center"><?php echo $total; ?></td>
                        <td class="text-center"><?php echo $nConf > 0 ? '<span class="badge bg-warning text-dark">' . $nConf . '</span>' : '—'; ?></td>
                        <td class="text-center"><?php echo $nBlock > 0 ? '<span class="badge bg-info text-dark">' . $nBlock . '</span>' : '—'; ?></td>
                        <td class="text-center"><?php echo $nHoli > 0 ? '<span class="badge bg-danger" title="' . htmlspecialchars(implode(', ', array_map(fn($d) => date('d/m', strtotime($d)), $row['holidays']))) . '">' . $nHoli . '</span>' : '—'; ?></td>
                        <td class="small">
                            Se cargan <?php echo max(0, $nSave); ?> día(s)<?php
                                if ($nConf > 0) echo $_req['overwrite'] ? ', sobrescribiendo los ' . $nConf . ' en conflicto' : ', omitiendo los ' . $nConf . ' en conflicto';
                                if ($nBlock > 0) echo ', ' . $nBlock . ' omitidos por licencia';
                            ?>.
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <form action="<?php echo URLROOT; ?>/registroHoras/cargaMasiva" method="post" class="d-flex gap-2 flex-wrap">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="execute">
            <?php foreach (['branch_name','start_date','end_date','start_time','end_time'] as $_f): ?>
            <input type="hidden" name="<?php echo $_f; ?>" value="<?php echo htmlspecialchars((string)$_req[$_f]); ?>">
            <?php endforeach; ?>
            <?php if ($_req['overwrite']): ?><input type="hidden" name="overwrite" value="1"><?php endif; ?>
            <?php foreach ($_req['employee_ids'] as $_eid): ?>
            <input type="hidden" name="employee_ids[]" value="<?php echo (int)$_eid; ?>">
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-layer-group me-1"></i>Confirmar carga masiva</button>
            <a href="<?php echo URLROOT; ?>/registroHoras/cargaMasiva" class="btn btn-outline-secondary">Cancelar</a>
        </form>
    </div>
</section>
<?php endif; ?>

<?php if ($data['realMode']): ?>
<form action="<?php echo URLROOT; ?>/registroHoras/cargaMasiva" method="post">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="preview">
<?php else: ?>
<form action="<?php echo URLROOT; ?>/registroHoras/mockSubmit" method="post">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="mock_back" value="cargaMasiva">
<?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="admin-surface h-100">
                <div class="admin-surface-head">
                    <div>
                        <h3 class="admin-surface-title"><i class="fas fa-sliders-h"></i>Parámetros</h3>
                        <p class="admin-surface-subtitle">Mismo horario para varios empleados a la vez.</p>
                    </div>
                </div>
                <div class="admin-surface-body">
                    <div class="mb-3">
                        <label class="form-label">Sucursal</label>
                        <select name="branch_name" class="form-select">
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
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Desde</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($_req['start_date'] ?? ''); ?>" <?php echo $data['realMode'] ? 'required' : ''; ?>>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Hasta</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($_req['end_date'] ?? ''); ?>" <?php echo $data['realMode'] ? 'required' : ''; ?>>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Entrada</label>
                            <input type="time" name="start_time" class="form-control" value="<?php echo htmlspecialchars($_req['start_time'] ?? '08:00'); ?>">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Salida</label>
                            <input type="time" name="end_time" class="form-control" value="<?php echo htmlspecialchars($_req['end_time'] ?? '16:00'); ?>">
                        </div>
                    </div>
                    <div class="form-text mb-2">Los días con vacaciones o licencia aprobada se omiten automáticamente y se detallan en la verificación.</div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="overwrite" value="1" id="mOverwrite" <?php echo ($_req && $_req['overwrite']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="mOverwrite">Sobrescribir horarios existentes</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i><?php echo $data['realMode'] ? 'Verificar antes de cargar' : 'Cargar a seleccionados (maqueta)'; ?>
                    </button>
                </div>
            </section>
        </div>

        <div class="col-lg-8">
            <section class="admin-surface h-100">
                <div class="admin-surface-head">
                    <div>
                        <h3 class="admin-surface-title"><i class="fas fa-users"></i>Empleados</h3>
                        <p class="admin-surface-subtitle"><?php echo $data['realMode'] ? 'Nómina real de ' . htmlspecialchars($data['companyName']) . '.' : 'Datos de ejemplo.'; ?></p>
                    </div>
                </div>
                <div class="admin-surface-body is-tight">
                    <div class="table-responsive">
                    <table class="table table-striped admin-table mb-0">
                        <thead>
                            <tr>
                                <th style="width:36px;"><input type="checkbox" class="form-check-input" id="mCheckAll" checked aria-label="Seleccionar todos"></th>
                                <th>Empleado</th><th>Sucursal</th><th>Estado</th><th>Observación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['employees'] as $emp):
                                $blocked = $emp->state !== 'Activo'; ?>
                            <tr class="<?php echo $blocked ? 'table-warning' : ''; ?>">
                                <td>
                                    <input type="checkbox" class="form-check-input m-check-row" name="employee_ids[]"
                                           value="<?php echo (int)$emp->id; ?>" <?php echo $blocked ? '' : 'checked'; ?>>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($emp->name); ?>
                                    <?php if (!empty($emp->manager)): ?><span class="badge bg-primary ms-1">Encargado</span><?php endif; ?>
                                    <?php if (!empty($emp->cajero)): ?><span class="badge bg-light text-dark border ms-1">Cajero</span><?php endif; ?>
                                </td>
                                <td class="small text-muted"><?php echo htmlspecialchars($emp->branch); ?></td>
                                <td><span class="badge <?php echo org_state_badge_class($emp->state); ?>"><?php echo htmlspecialchars($emp->state); ?></span></td>
                                <td class="small text-muted">
                                    <?php if ($blocked): ?>
                                        <i class="fas fa-exclamation-triangle text-warning me-1"></i>Sus días con licencia/vacaciones se omiten automáticamente
                                    <?php elseif (!$data['realMode'] && $_conflictEmp && $emp->id === $_conflictEmp->id): ?>
                                        <i class="fas fa-info-circle me-1"></i>Ya tiene horario el 18/08 (conflicto)
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($data['employees'])): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Sin empleados en la empresa activa.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </section>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var all = document.getElementById('mCheckAll');
    if (all) {
        all.addEventListener('change', function () {
            document.querySelectorAll('.m-check-row').forEach(function (c) { c.checked = all.checked; });
        });
    }
});
</script>

<?php require APPROOT . '/views/inc/footer.php'; ?>
