<?php require APPROOT . '/views/inc/header.php'; ?>


<div class="d-flex align-items-center gap-3 mb-4">
    <div style="background:var(--clr-primary-l);border-radius:1rem;padding:.75rem 1rem;">
        <i class="fas fa-cloud-download-alt fa-lg" style="color:var(--clr-primary);"></i>
    </div>
    <div>
        <h2 class="page-title mb-0">Sincronización de Marcaciones</h2>
        <p class="page-subtitle mb-0">API Relojes OpenAPI <?php echo defined('CLOCK_API_VERSION') ? CLOCK_API_VERSION : 'v1'; ?> · JWT</p>
    </div>
    <a href="<?php echo URLROOT; ?>/admin/controlAsistencia" class="btn btn-outline-primary ms-auto">
        <i class="fas fa-user-clock me-1"></i> Control de asistencia
    </a>
</div>

<div class="card shadow mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="fas fa-sync-alt" style="color:var(--clr-primary);"></i>
        <h5 class="mb-0">Importar desde API</h5>
        <span class="badge ms-auto" style="background:var(--clr-primary);">API Relojes · JWT</span>
    </div>
    <div class="card-body">
        <div class="alert mb-4 d-flex gap-3 align-items-start"
             style="background:var(--clr-primary-xl,#fff0f9);border-left:4px solid var(--clr-primary);border-radius:.5rem;">
            <i class="fas fa-info-circle mt-1" style="color:var(--clr-primary);"></i>
            <?php require APPROOT . '/views/admin/partials/api_sync_help.php'; ?>
        </div>
        <div class="alert alert-secondary small mb-4">
            <strong>Sincronización automática (cron):</strong>
            <code>php <?php echo dirname(APPROOT); ?>/cli/sync_marcaciones.php --days=2 --all-companies</code>
            <br>En Windows Task Scheduler, programá la misma línea diariamente (ej. 6:00). Opción <code>--no-email</code> omite el resumen de alertas por correo.
        </div>
        <form action="<?php echo URLROOT; ?>/admin/runApiSync" method="post">
            <?php echo csrf_field(); ?>
            <div class="row g-3 align-items-end">
                <div class="col-sm-6 col-md-3">
                    <label class="form-label fw-semibold">Fecha desde</label>
                    <input type="date" name="api_start_date" class="form-control"
                           value="<?php echo date('Y-m-d', strtotime('-1 week')); ?>" required>
                </div>
                <div class="col-sm-6 col-md-3">
                    <label class="form-label fw-semibold">Fecha hasta</label>
                    <input type="date" name="api_end_date" class="form-control"
                           value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-sm-12 col-md-4">
                    <label class="form-label fw-semibold">
                        ID de empleado en reloj
                        <span class="fw-normal text-muted">(vacío = todos)</span>
                    </label>
                    <input type="text" name="api_employee_id" class="form-control" placeholder="Ej: 12345">
                </div>
                <div class="col-sm-12 col-md-2">
                    <button type="submit" class="btn w-100 fw-bold text-white"
                            style="background:linear-gradient(135deg,var(--clr-primary),#c2185b);">
                        <i class="fas fa-cloud-download-alt me-1"></i>Sincronizar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="alert alert-light border"><strong>Después de sincronizar:</strong> revisá <a href="<?php echo URLROOT; ?>/admin/mapeoIncompleto">Pendientes de identificación</a> para vincular solo las personas que usan reloj. Los demás empleados no requieren un ID.</div>

<?php require APPROOT . '/views/inc/footer.php'; ?>
