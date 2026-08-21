<?php require APPROOT . '/views/inc/header.php'; ?>

<?php $_rhActive = 'porSucursal'; require APPROOT . '/views/registro_horas/partials/nav_tabs.php'; ?>
<?php require APPROOT . '/views/registro_horas/partials/mock_banner.php'; ?>

<?php
$_ps = $data['ps'];
$_rows = $data['psSections']['rows'];
$_holCols = $data['psSections']['holidays'];
$_schedules = $data['psSchedules'];
$_totalEmps = 0;
foreach ($_rows as $_r) { $_totalEmps += count($_r); }
$_fmtEx = function ($v) { $s = rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.'); return $s === '' ? '0' : $s; };
?>

<section class="admin-surface">
    <div class="admin-surface-body">
        <?php if ($data['realMode']): ?>
        <form action="<?php echo URLROOT; ?>/registroHoras/porSucursal" method="get" class="row g-2 align-items-end">
        <?php else: ?>
        <form action="<?php echo URLROOT; ?>/registroHoras/mockSubmit" method="post" class="row g-2 align-items-end">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="mock_back" value="porSucursal">
        <?php endif; ?>
            <div class="col-lg-3 col-md-6">
                <label class="form-label mb-1 d-block">Modo</label>
                <div class="btn-group btn-group-sm w-100" role="group" aria-label="Modo">
                    <input type="radio" class="btn-check" name="modo" id="psModoCompleta" value="completa" <?php echo $_ps['modo'] === 'completa' ? 'checked' : ''; ?>>
                    <label class="btn btn-outline-primary" for="psModoCompleta">Semana completa</label>
                    <input type="radio" class="btn-check" name="modo" id="psModoSemana" value="semana" <?php echo $_ps['modo'] === 'semana' ? 'checked' : ''; ?>>
                    <label class="btn btn-outline-primary" for="psModoSemana">Lu a Vi</label>
                    <input type="radio" class="btn-check" name="modo" id="psModoFinde" value="finde" <?php echo $_ps['modo'] === 'finde' ? 'checked' : ''; ?>>
                    <label class="btn btn-outline-primary" for="psModoFinde">Fin de semana</label>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label mb-1">Ciudad / Sucursal</label>
                <div class="input-group input-group-sm">
                    <select name="city" id="psFilterCity" class="form-select" aria-label="Ciudad">
                        <option value="">Todas</option>
                        <?php foreach (array_keys($data['branchesByCity']) as $cityName): ?>
                        <option value="<?php echo htmlspecialchars($cityName); ?>" <?php echo $_ps['city'] === $cityName ? 'selected' : ''; ?>><?php echo htmlspecialchars($cityName); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="branch" id="psFilterBranch" class="form-select" aria-label="Sucursal">
                        <option value="">Todas</option>
                        <?php foreach ($data['branchesByCity'] as $cityName => $branches): ?>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?php echo htmlspecialchars($b); ?>" data-city="<?php echo htmlspecialchars($cityName); ?>" <?php echo $_ps['branch'] === $b ? 'selected' : ''; ?>><?php echo htmlspecialchars($b); ?></option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label mb-1">Desde / Hasta</label>
                <div class="input-group input-group-sm">
                    <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($_ps['from']); ?>" aria-label="Desde">
                    <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($_ps['to']); ?>" aria-label="Hasta">
                </div>
            </div>
            <div class="col-lg-2 col-md-4">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-search me-1"></i>Filtrar</button>
            </div>
            <div class="col-lg-1 col-md-2">
                <a class="btn btn-sm btn-outline-secondary w-100" title="Semana actual"
                   href="<?php echo URLROOT; ?>/registroHoras/porSucursal?modo=<?php echo $_ps['modo']; ?>"><i class="fas fa-rotate-left"></i></a>
            </div>
        </form>
        <?php if ($_ps['multiWeek'] && $_ps['modo'] !== 'finde'): ?>
        <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i>El rango abarca más de una semana: cada columna agrupa todas las fechas de ese día de semana (los horarios muestran su fecha).</div>
        <?php endif; ?>
    </div>
</section>

<?php if (empty($_rows)): ?>
<section class="admin-surface">
    <div class="admin-surface-body text-center text-muted py-5">
        <i class="far fa-calendar-times fs-3 d-block mb-2"></i>
        Sin horas registradas en el período<?php echo ($_ps['branch'] !== '' || $_ps['city'] !== '') ? ' con los filtros elegidos' : ''; ?>.
        <div class="mt-2"><a href="<?php echo URLROOT; ?>/registroHoras/carga" class="btn btn-sm btn-primary"><i class="fas fa-plus-circle me-1"></i>Cargar horario</a></div>
    </div>
</section>
<?php else: ?>
<section class="admin-surface">
    <div class="admin-surface-head">
        <div>
            <h3 class="admin-surface-title"><i class="fas fa-store"></i>Horarios por sucursal</h3>
            <p class="admin-surface-subtitle">
                <?php echo date('d/m/Y', strtotime($_ps['from'])); ?> al <?php echo date('d/m/Y', strtotime($_ps['to'])); ?> ·
                <?php echo count($_rows); ?> sucursal(es) · <?php echo $_totalEmps; ?> colaborador(es) ·
                horas registradas en cada sucursal, venga de donde venga el colaborador.
            </p>
        </div>
    </div>
    <div class="admin-surface-body is-tight">
        <div class="table-responsive">
        <table class="table admin-table mb-0 align-middle">
            <thead>
                <tr>
                    <th style="min-width:170px;">Sucursal</th>
                    <th style="min-width:180px;">Colaboradores</th>
                    <?php foreach ($_ps['columns'] as $_col): ?>
                    <th class="text-center">
                        <?php echo $_col['label']; ?>
                        <?php if ($_col['sub'] !== ''): ?><div class="fw-normal" style="font-size:.72rem;opacity:.8;"><?php echo $_col['sub']; ?></div><?php endif; ?>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($_rows as $_branch => $_emps): $_n = count($_emps); $_first = true; ?>
                    <?php foreach ($_emps as $_row): $_emp = $_row['employee']; $_absent = $_row['absent']; ?>
                    <tr class="<?php echo $_absent !== null ? 'table-secondary' : ''; ?>">
                        <?php if ($_first): $_first = false; ?>
                        <td rowspan="<?php echo $_n; ?>" class="align-top">
                            <div class="fw-bold" style="color:var(--clr-primary-d);"><i class="fas fa-store me-1"></i><?php echo htmlspecialchars($_branch); ?></div>
                            <?php if (!empty($_schedules[$_branch])): ?>
                            <div class="small text-muted mt-1 p-2 rounded" style="background:var(--page-bg);border-left:3px solid var(--clr-primary);">
                                <i class="far fa-clock me-1"></i><?php echo htmlspecialchars($_schedules[$_branch]); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td>
                            <?php if ($data['realMode']): ?>
                            <a class="fw-semibold text-decoration-none" href="<?php echo URLROOT; ?>/registroHoras/horarios?employee_id=<?php echo (int)$_emp->id; ?>">
                                <i class="fas fa-user me-1 text-muted"></i><?php echo htmlspecialchars($_emp->name); ?>
                            </a>
                            <?php else: ?>
                            <span class="fw-semibold"><i class="fas fa-user me-1 text-muted"></i><?php echo htmlspecialchars($_emp->name); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($_emp->manager)): ?><i class="fas fa-user-tie ms-1" style="color:var(--clr-primary);" title="Encargado"></i><?php endif; ?>
                            <?php if (!empty($_emp->cajero)): ?><i class="fas fa-cash-register ms-1 text-success" title="Cajero"></i><?php endif; ?>
                            <?php if ($_absent !== null): ?>
                            <span class="badge <?php echo org_state_badge_class($_absent); ?> ms-1"
                                  title="Hasta: <?php echo !empty($_row['absent_until']) ? date('d/m/Y', strtotime($_row['absent_until'])) : 'sin definir'; ?>"><?php echo htmlspecialchars($_absent); ?></span>
                            <?php endif; ?>
                        </td>
                        <?php foreach ($_ps['columns'] as $_key => $_col):
                            $_hol = $_holCols[$_branch][$_key] ?? null;
                        ?>
                        <td class="text-center" <?php echo $_hol ? 'style="background:#fde2e2;" title="Feriado: ' . htmlspecialchars($_hol) . '"' : ''; ?>>
                            <?php if ($_absent !== null): ?>
                                <span class="badge bg-light text-muted border"><?php echo htmlspecialchars($_absent); ?></span>
                            <?php elseif (!empty($_row['cells'][$_key])): ?>
                                <?php foreach ($_row['cells'][$_key] as $_c): ?>
                                <span class="badge bg-light text-dark border d-block mb-1" style="font-weight:600;">
                                    <?php if ($_ps['multiWeek'] && $_ps['modo'] !== 'finde'): ?><span class="text-muted fw-normal"><?php echo date('d/m', strtotime($_c['date'])); ?> · </span><?php endif; ?>
                                    <?php echo $_c['start'] . ' – ' . $_c['end']; ?>
                                    <?php if ($_c['extra'] > 0): ?><span class="text-warning fw-bold">(+<?php echo $_fmtEx($_c['extra']); ?>)</span><?php endif; ?>
                                </span>
                                <?php endforeach; ?>
                            <?php else:
                                $_dayBlocked = null;
                                foreach ($_col['dates'] as $_d) {
                                    if (isset($_row['blocked_days'][$_d])) { $_dayBlocked = $_row['blocked_days'][$_d]; break; }
                                }
                            ?>
                                <?php if ($_dayBlocked): ?>
                                <span class="badge <?php echo org_state_badge_class($_dayBlocked['label']); ?>"
                                      title="Hasta: <?php echo date('d/m/Y', strtotime($_dayBlocked['until'])); ?>"><?php echo htmlspecialchars($_dayBlocked['label']); ?></span>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var citySel = document.getElementById('psFilterCity');
    var branchSel = document.getElementById('psFilterBranch');
    function refresh() {
        if (!citySel || !branchSel) return;
        var city = citySel.value, current = branchSel.value, visible = false;
        Array.prototype.forEach.call(branchSel.options, function (o) {
            if (o.value === '') return;
            var show = city === '' || o.dataset.city === city;
            o.hidden = !show; o.disabled = !show;
            if (show && o.value === current) visible = true;
        });
        if (!visible) branchSel.value = '';
    }
    if (citySel) citySel.addEventListener('change', refresh);
    refresh();
});
</script>

<script src="<?php echo URLROOT; ?>/js/rh-tooltip.js"></script>
<?php require APPROOT . '/views/inc/footer.php'; ?>
