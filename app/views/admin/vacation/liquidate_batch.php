<?php require APPROOT . '/views/inc/header.php';
$preview = $data['preview'] ?? [];
$report = $data['report'] ?? null;
?>

<div class="admin-page-head mb-4">
    <div class="admin-page-brand">
        <div class="admin-page-icon"><i class="fas fa-users-cog"></i></div>
        <div class="admin-page-meta">
            <h2 class="page-title mb-0">Liquidación masiva de vacaciones</h2>
            <p class="page-subtitle mb-0"><?php echo htmlspecialchars($data['company_name'] ?? ''); ?> · solo empleados <strong>activos</strong></p>
        </div>
    </div>
    <div class="admin-page-actions">
        <a href="<?php echo URLROOT; ?>/vacationAdmin/agreements" class="btn btn-outline-secondary btn-sm">Convenios</a>
        <a href="<?php echo URLROOT; ?>/admin/users?company_id=<?php echo (int)$data['company_id']; ?>" class="btn btn-outline-secondary btn-sm">Usuarios</a>
    </div>
</div>

<div class="alert alert-light border small mb-4">
    <strong>Qué hace esta acción</strong>
    <ul class="mb-0 mt-2">
        <li>Crea o actualiza el año indicado (ej. <strong>2026</strong>) para cada empleado <strong>activo</strong> de la empresa.</li>
        <li>Calcula automáticamente los días que <strong>corresponden</strong> según ingreso formal, convenio y antigüedad al 31 de diciembre.</li>
        <li><strong>No incluye</strong> usuarios <span class="badge bg-danger">Inactivos</span> (baja por despido, renuncia, etc.).</li>
        <li>Los días ya tomados de períodos anteriores se mantienen; el saldo total pendiente suma todos los períodos abiertos (FIFO al consumir).</li>
    </ul>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="admin-kpi-card">
            <div><div class="admin-kpi-value"><?php echo (int)($preview['total_active'] ?? 0); ?></div><div class="admin-kpi-label">Empleados activos</div></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-kpi-card">
            <div><div class="admin-kpi-value text-success"><?php echo (int)($preview['ready'] ?? 0); ?></div><div class="admin-kpi-label">Listos para liquidar</div></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-kpi-card">
            <div><div class="admin-kpi-value text-warning"><?php echo (int)($preview['no_hire_date'] ?? 0); ?></div><div class="admin-kpi-label">Sin ingreso formal</div></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-kpi-card">
            <div><div class="admin-kpi-value text-warning"><?php echo (int)($preview['no_agreement'] ?? 0); ?></div><div class="admin-kpi-label">Sin convenio</div></div>
        </div>
    </div>
</div>

<div class="card border shadow-sm mb-4">
    <div class="card-header"><strong>Ejecutar liquidación</strong></div>
    <div class="card-body">
        <form method="post" action="<?php echo URLROOT; ?>/vacationAdmin/liquidateCompanyBatch?company_id=<?php echo (int)$data['company_id']; ?>"
              onsubmit="return confirm('¿Liquidar el período para todos los empleados activos listos de esta empresa?');">
            <?php echo csrf_field(); ?>
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Período a liquidar</label>
                    <input type="text" name="period_label" class="form-control" required
                           value="<?php echo htmlspecialchars($data['suggested_period']); ?>"
                           placeholder="2026-2027">
                    <small class="text-muted">Año calendario, por ejemplo 2026.</small>
                </div>
                <div class="col-md-8">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-bolt me-1"></i>Liquidar período — todos los activos
                    </button>
                    <span class="small text-muted ms-2">Procesará hasta <?php echo (int)($preview['ready'] ?? 0); ?> empleado(s).</span>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($report && !empty($report['details'])): ?>
<div class="card border shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Resultado — <?php echo htmlspecialchars($report['period_label'] ?? ''); ?></strong>
        <span>
            <span class="badge bg-success"><?php echo (int)$report['liquidated']; ?> OK</span>
            <span class="badge bg-secondary"><?php echo (int)$report['skipped']; ?> omitidos</span>
            <?php if ((int)$report['failed'] > 0): ?>
            <span class="badge bg-danger"><?php echo (int)$report['failed']; ?> errores</span>
            <?php endif; ?>
        </span>
    </div>
    <div class="table-responsive" style="max-height:420px;overflow:auto;">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light sticky-top">
                <tr><th>Empleado</th><th>Estado</th><th>Detalle</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($report['details'] as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td>
                    <?php if ($row['status'] === 'ok'): ?>
                    <span class="badge bg-success">Liquidado</span>
                    <?php elseif ($row['status'] === 'skipped'): ?>
                    <span class="badge bg-secondary">Omitido</span>
                    <?php else: ?>
                    <span class="badge bg-danger">Error</span>
                    <?php endif; ?>
                </td>
                <td class="small"><?php echo htmlspecialchars($row['message']); ?></td>
                <td class="text-end">
                    <?php if (!empty($row['user_id'])): ?>
                    <a href="<?php echo URLROOT; ?>/vacationAdmin/vacationSetup/<?php echo (int)$row['user_id']; ?>" class="btn btn-sm btn-outline-primary">Ficha vac.</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require APPROOT . '/views/inc/footer.php'; ?>
