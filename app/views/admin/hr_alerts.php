<?php require APPROOT . '/views/inc/header.php';
$stats = $data['attendance_stats'] ?? null;
?>

<div class="admin-page-head">
    <div class="admin-page-brand">
        <div class="admin-page-icon"><i class="fas fa-bell"></i></div>
        <div class="admin-page-meta">
            <h2 class="page-title">Centro de alertas RRHH</h2>
            <p class="page-subtitle mb-0">Vista consolidada para la operación diaria</p>
        </div>
    </div>
    <form method="get" class="d-flex gap-2 align-items-end">
        <div>
            <label class="form-label small mb-0">Fecha</label>
            <input type="date" name="date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['work_date']); ?>">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Ver</button>
    </form>
</div>

<?php if (!empty($data['october_liquidation_reminder'])): ?>
<div class="alert alert-info">
    <i class="fas fa-umbrella-beach me-1"></i>
    <strong>Octubre–diciembre:</strong> podés abrir las vacaciones del <strong>año siguiente</strong> (calendario 1 ene–31 dic) según cada convenio.
    <a href="<?php echo URLROOT; ?>/vacationAdmin/panel?period=<?php echo urlencode((string)((int)date('Y') + 1)); ?>" class="alert-link">Panel de vacaciones</a>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="admin-kpi-card">
            <div class="admin-kpi-value text-danger"><?php echo count($data['attendance_alerts']); ?></div>
            <div class="admin-kpi-label">Asistencia hoy</div>
            <a href="<?php echo URLROOT; ?>/admin/attendance" class="small">Ver módulo</a>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-kpi-card">
            <div class="admin-kpi-value text-warning"><?php echo (int)$data['pending_requests_count']; ?></div>
            <div class="admin-kpi-label">Solicitudes pendientes</div>
            <a href="<?php echo URLROOT; ?>/admin/requests" class="small">Revisar</a>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-kpi-card">
            <div class="admin-kpi-value"><?php echo (int)$data['unmapped_count']; ?></div>
            <div class="admin-kpi-label">Legajos sin mapear</div>
            <a href="<?php echo URLROOT; ?>/admin/mapeoIncompleto" class="small">Panel mapeo</a>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-kpi-card">
            <div class="admin-kpi-value"><?php echo $stats ? (int)$stats->count_ok : '—'; ?></div>
            <div class="admin-kpi-label">OK asistencia (día)</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Asistencia — <?php echo date('d/m/Y', strtotime($data['work_date'])); ?></div>
            <div class="card-body p-0">
                <?php if (empty($data['attendance_alerts'])): ?>
                <p class="text-muted p-3 mb-0">Sin alertas de asistencia para esta fecha.</p>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                <?php foreach ($data['attendance_alerts'] as $a):
                    $meta = attendanceStatusMeta($a->status);
                ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><?php echo htmlspecialchars($a->full_name); ?></span>
                    <span class="badge bg-<?php echo $meta['badge']; ?>"><?php echo htmlspecialchars($meta['label']); ?></span>
                </li>
                <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Solicitudes pendientes</div>
            <div class="card-body p-0">
                <?php if (empty($data['pending_requests'])): ?>
                <p class="text-muted p-3 mb-0">No hay solicitudes pendientes.</p>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                <?php foreach (array_slice($data['pending_requests'], 0, 12) as $req): ?>
                <li class="list-group-item">
                    <strong><?php echo htmlspecialchars($req->full_name ?? ''); ?></strong> —
                    <?php echo htmlspecialchars($req->type_name ?? 'Solicitud'); ?>
                    <span class="small text-muted">(<?php echo date('d/m', strtotime($req->start_date)); ?>)</span>
                </li>
                <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border shadow-sm">
            <div class="card-header bg-white fw-semibold">Vacaciones — alertas</div>
            <div class="card-body small">
                <?php $va = $data['vacation_alerts']; ?>
                <?php if (empty($va['ready'])): ?>
                <p class="text-muted mb-0">Módulo de vacaciones no configurado.</p>
                <?php else: ?>
                <p class="mb-1"><strong>Sin fecha de ingreso:</strong> <?php echo count($va['no_hire_date']); ?></p>
                <p class="mb-1"><strong>Sin convenio:</strong> <?php echo count($va['no_agreement']); ?></p>
                <p class="mb-2"><strong>Saldo bajo (&lt;3 días):</strong> <?php echo count($va['low_balance']); ?></p>
                <a href="<?php echo URLROOT; ?>/vacationAdmin/panel">Panel de vacaciones</a>
                · <a href="<?php echo URLROOT; ?>/vacationAdmin/reports">Saldos</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border shadow-sm">
            <div class="card-header bg-white fw-semibold">Incidencias recientes</div>
            <div class="card-body p-0">
                <?php if (empty($data['recent_incidents'])): ?>
                <p class="text-muted p-3 mb-0">Sin incidencias recientes.</p>
                <?php else: ?>
                <ul class="list-group list-group-flush small">
                <?php foreach ($data['recent_incidents'] as $inc): ?>
                <li class="list-group-item">
                    <a href="<?php echo URLROOT; ?>/admin/employeeProfile/<?php echo (int)$inc->user_id; ?>#tab-incidents">
                        <?php echo htmlspecialchars($inc->employee_name); ?>
                    </a>
                    — <?php echo htmlspecialchars(employee_incident_type_label($inc->incident_type)); ?>
                    (<?php echo date('d/m/Y', strtotime($inc->incident_date)); ?>)
                </li>
                <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require APPROOT . '/views/inc/footer.php'; ?>
