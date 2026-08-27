<?php require APPROOT . '/views/inc/header.php'; ?>

<?php $_rhActive = 'vistaGeneral'; require APPROOT . '/views/registro_horas/partials/nav_tabs.php'; ?>
<?php require APPROOT . '/views/registro_horas/partials/mock_banner.php'; ?>

<?php
// Agrupar empleados por sucursal, encargados primero (mismo orden que branch_hours de hoursapp).
$byBranch = [];
foreach ($data['employees'] as $emp) {
    $byBranch[$emp->branch][] = $emp;
}
foreach ($byBranch as $branch => $emps) {
    usort($emps, function ($a, $b) {
        if ($a->manager !== $b->manager) return $a->manager ? -1 : 1;
        return strcmp($a->name, $b->name);
    });
    $byBranch[$branch] = $emps;
}
$weekdaysShort = [1 => 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
?>

<section class="admin-surface">
    <div class="admin-surface-body">
        <?php if ($data['realMode']): ?>
        <form action="<?php echo URLROOT; ?>/registroHoras/vistaGeneral" method="get" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label mb-1">Semana</label>
                <input type="week" name="week" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['weekValue']); ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-search me-1"></i>Consultar</button>
            </div>
        </form>
        <?php else: ?>
        <form action="<?php echo URLROOT; ?>/registroHoras/mockSubmit" method="post" class="row g-2 align-items-end">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="mock_back" value="vistaGeneral">
            <div class="col-md-5">
                <label class="form-label mb-1">Sucursal</label>
                <select name="branch_name" class="form-select form-select-sm">
                    <option value="" selected>Todas</option>
                    <?php foreach ($data['branchesByCity'] as $group => $branches): ?>
                    <optgroup label="<?php echo htmlspecialchars($group); ?>">
                        <?php foreach ($branches as $b): ?>
                        <option value="<?php echo htmlspecialchars($b); ?>"><?php echo htmlspecialchars($b); ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1">Semana</label>
                <input type="week" name="week" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['weekValue']); ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-search me-1"></i>Consultar</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</section>

<?php foreach ($byBranch as $branch => $emps): ?>
<section class="admin-surface">
    <div class="admin-surface-head">
        <div>
            <h3 class="admin-surface-title"><i class="fas fa-store"></i><?php echo htmlspecialchars($branch); ?></h3>
            <p class="admin-surface-subtitle"><?php echo count($emps); ?> empleado<?php echo count($emps) === 1 ? '' : 's'; ?> · encargados primero.</p>
        </div>
    </div>
    <div class="admin-surface-body is-tight">
        <div class="table-responsive">
        <table class="table table-striped admin-table mb-0 align-middle">
            <thead>
                <tr>
                    <th style="min-width:190px;">Empleado</th>
                    <?php foreach ($data['weekDays'] as $i => $day): ?>
                    <th class="text-center">
                        <?php echo $weekdaysShort[$i + 1]; ?>
                        <div class="fw-normal" style="font-size:.72rem;opacity:.8;"><?php echo date('d/m', strtotime($day)); ?></div>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($emps as $emp):
                    $empDays = $data['schedule'][$emp->id]['days'] ?? [];
                ?>
                <tr>
                    <td>
                        <span class="fw-semibold"><?php echo htmlspecialchars($emp->name); ?></span>
                        <?php if (!empty($emp->manager)): ?><span class="badge bg-primary ms-1">Encargado</span><?php endif; ?>
                        <?php if (!empty($emp->cajero)): ?><span class="badge bg-light text-dark border ms-1">Cajero</span><?php endif; ?>
                    </td>
                    <?php for ($dow = 1; $dow <= 7; $dow++):
                        $cell = $empDays[$dow] ?? ['ranges' => [], 'absent' => null];
                    ?>
                    <td class="text-center">
                        <?php if (!empty($cell['absent'])): ?>
                            <span class="badge <?php echo org_state_badge_class($cell['absent']); ?>"><?php echo htmlspecialchars($cell['absent']); ?></span>
                        <?php elseif (empty($cell['ranges'])): ?>
                            <span class="text-muted">—</span>
                        <?php else: foreach ($cell['ranges'] as $range): ?>
                            <span class="badge bg-light text-dark border d-block mb-1" style="font-weight:600;"><?php echo htmlspecialchars($range); ?></span>
                        <?php endforeach; endif; ?>
                    </td>
                    <?php endfor; ?>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($emps)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Sin empleados para mostrar.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>
<?php endforeach; ?>

<?php if ($data['realMode']): ?>
<div class="text-muted small mt-2">
    <i class="fas fa-info-circle me-1"></i>
    Semana del <?php echo date('d/m', strtotime($data['weekDays'][0])); ?> al <?php echo date('d/m', strtotime(end($data['weekDays']))); ?> — datos reales de <?php echo htmlspecialchars($data['companyName']); ?>. Los días sin horario muestran “—”.
</div>
<?php endif; ?>

<script src="<?php echo URLROOT; ?>/js/rh-tooltip.js"></script>
<?php require APPROOT . '/views/inc/footer.php'; ?>
