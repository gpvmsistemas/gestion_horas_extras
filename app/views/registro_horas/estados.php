<?php require APPROOT . '/views/inc/header.php'; ?>

<?php $_rhActive = 'estados'; require APPROOT . '/views/registro_horas/partials/nav_tabs.php'; ?>
<?php require APPROOT . '/views/registro_horas/partials/mock_banner.php'; ?>

<?php
$_statusOptions = ['guardia' => 'Guardia', 'vacaciones' => 'Vacaciones', 'licencia' => 'Licencia'];
$_today = date('Y-m-d');
?>

<div class="row g-3">
    <div class="col-lg-4">
        <section class="admin-surface h-100">
            <div class="admin-surface-head">
                <div>
                    <h3 class="admin-surface-title"><i class="fas fa-user-shield"></i>Declarar estado</h3>
                    <p class="admin-surface-subtitle">Guardia, vacaciones o licencia por período.</p>
                </div>
            </div>
            <div class="admin-surface-body">
                <?php if ($data['realMode'] && $data['statusReady']): ?>
                <form action="<?php echo URLROOT; ?>/registroHoras/estados" method="post">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="create">
                <?php else: ?>
                <form action="<?php echo URLROOT; ?>/registroHoras/mockSubmit" method="post">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="mock_back" value="estados">
                <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Empleado</label>
                        <select name="employee_id" class="form-select" required>
                            <option value="" selected disabled>Seleccionar empleado…</option>
                            <?php foreach ($data['employees'] as $emp): ?>
                            <option value="<?php echo (int)$emp->id; ?>"><?php echo htmlspecialchars($emp->name . ' — ' . $emp->branch); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label d-block">Estado</label>
                        <div class="btn-group w-100" role="group" aria-label="Estado">
                            <?php $_first = true; foreach ($_statusOptions as $_val => $_label): ?>
                            <input type="radio" class="btn-check" name="status" id="st_<?php echo $_val; ?>" value="<?php echo $_val; ?>" <?php echo $_first ? 'checked' : ''; $_first = false; ?>>
                            <label class="btn btn-outline-primary" for="st_<?php echo $_val; ?>"><?php echo $_label; ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Desde</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Hasta</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notas (opcional)</label>
                        <input type="text" name="notes" class="form-control" maxlength="255" placeholder="Motivo, referencia…">
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Registrar período</button>
                </form>
                <div class="alert alert-warning py-2 small mt-3 mb-0">
                    <i class="fas fa-shield-alt me-1"></i>
                    Mientras dura el período, la carga de horas (individual, masiva y duplicaciones) <strong>omite esos días automáticamente</strong> y lo informa en cada verificación previa.
                </div>
                <?php if ($data['realMode'] && !$data['statusReady']): ?>
                <div class="alert alert-danger py-2 small mt-3 mb-0">
                    Falta correr <code>scripts/migration_estados_empleado.sql</code> en esta base.
                </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="col-lg-8">
        <section class="admin-surface h-100">
            <div class="admin-surface-head">
                <div>
                    <h3 class="admin-surface-title"><i class="fas fa-calendar-alt"></i>Períodos vigentes y futuros</h3>
                    <p class="admin-surface-subtitle">Los períodos pasados dejan de bloquear la carga y salen de esta lista.</p>
                </div>
            </div>
            <div class="admin-surface-body is-tight">
                <div class="table-responsive">
                <table class="table table-striped admin-table mb-0 align-middle">
                    <thead><tr><th>Empleado</th><th>Estado</th><th>Desde</th><th>Hasta</th><th>Notas</th><th class="text-end">Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($data['periods'] as $p):
                            $emp = $data['employeesById'][(int)$p->user_id] ?? null;
                            if (!$emp) continue;
                            $label = RegistroHorasService::statusLabel($p->status);
                            $vigente = $p->start_date <= $_today && $_today <= $p->end_date;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($emp->name); ?></td>
                            <td>
                                <span class="badge <?php echo org_state_badge_class($label); ?>"><?php echo htmlspecialchars($label); ?></span>
                                <?php if ($vigente): ?><span class="badge bg-dark ms-1">Hoy</span><?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($p->start_date)); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($p->end_date)); ?></td>
                            <td class="small text-muted"><?php echo htmlspecialchars((string)($p->notes ?? '')); ?></td>
                            <td class="text-end">
                                <form action="<?php echo URLROOT; ?>/registroHoras/estados" method="post" class="d-inline rh-status-delete">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="period_id" value="<?php echo (int)$p->id; ?>">
                                    <input type="hidden" name="employee_id" value="<?php echo (int)$p->user_id; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar período"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($data['periods'])): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">
                            <i class="fas fa-user-check d-block fs-4 mb-2"></i>
                            Sin períodos declarados: todos los empleados figuran Activos.
                        </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.rh-status-delete').forEach(function (f) {
        f.addEventListener('submit', function (ev) {
            if (!window.Swal) return;
            ev.preventDefault();
            Swal.fire({
                title: '¿Eliminar el período?',
                text: 'Los días vuelven a quedar habilitados para cargar horas.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then(function (r) { if (r.isConfirmed) f.submit(); });
        });
    });
});
</script>

<script src="<?php echo URLROOT; ?>/js/rh-tooltip.js"></script>
<?php require APPROOT . '/views/inc/footer.php'; ?>
