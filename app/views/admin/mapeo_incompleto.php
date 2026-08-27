<?php require APPROOT . '/views/inc/header.php'; ?>

<div class="admin-page-head">
    <div class="admin-page-brand">
        <div class="admin-page-icon"><i class="fas fa-unlink"></i></div>
        <div class="admin-page-meta">
            <h2 class="page-title">Pendientes de identificación</h2>
            <p class="page-subtitle mb-0">Solo fichadas sin persona asignada: vinculá únicamente a quienes usan reloj.</p>
        </div>
    </div>
    <div class="admin-page-actions d-flex gap-2">
        <a href="<?php echo URLROOT; ?>/admin/sync" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-sync me-1"></i> Sincronizar
        </a>
    </div>
</div>

<form method="get" class="row g-2 align-items-end mb-3">
    <div class="col-auto">
        <label class="form-label small mb-0">Últimos días</label>
        <select name="days" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach ([30, 60, 90, 180] as $d): ?>
            <option value="<?php echo $d; ?>" <?php echo (int)$data['since_days'] === $d ? 'selected' : ''; ?>><?php echo $d; ?> días</option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php if (empty($data['legajos'])): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle me-1"></i> No hay legajos sin mapear en el período seleccionado.
</div>
<?php else: ?>
<div class="card border shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Legajo</th>
                    <th>Nombre en reloj</th>
                    <th>Dispositivo</th>
                    <th>Marcas</th>
                    <th>Última marca</th>
                    <th>Vincular a</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($data['legajos'] as $leg): ?>
            <tr>
                <td><code><?php echo htmlspecialchars($leg->employee_id); ?></code></td>
                <td><?php echo htmlspecialchars($leg->person_name ?: '—'); ?></td>
                <td><?php echo htmlspecialchars($leg->device_name ?: '—'); ?></td>
                <td><?php echo (int)$leg->punch_count; ?></td>
                <td class="text-nowrap small"><?php echo date('d/m/Y H:i', strtotime($leg->last_seen)); ?></td>
                <td colspan="2">
                    <form method="post" action="<?php echo URLROOT; ?>/admin/saveMappingFromApi" class="row g-1 align-items-center">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($leg->employee_id); ?>">
                        <input type="hidden" name="device_name" value="<?php echo htmlspecialchars($leg->device_name ?: 'API'); ?>">
                        <div class="col-md-7">
                            <select name="user_id" class="form-select form-select-sm" required>
                                <option value="">— Empleado —</option>
                                <?php foreach ($data['all_users'] as $u): ?>
                                <option value="<?php echo (int)$u->id; ?>"
                                    <?php
                                    foreach (($leg->suggestions ?? []) as $sug) {
                                        if ((int)$sug['id'] === (int)$u->id) { echo ' selected'; break; }
                                    }
                                    ?>>
                                    <?php echo htmlspecialchars($u->full_name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="fas fa-link me-1"></i> Vincular
                            </button>
                        </div>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require APPROOT . '/views/inc/footer.php'; ?>
