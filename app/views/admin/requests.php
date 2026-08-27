<?php
require APPROOT . '/views/inc/header.php';

$GLOBALS['requests_review_page'] = true;

$viewData = $data ?? [];
$requests = $viewData['requests'] ?? [];
$openRequestId = (int)($viewData['open_request_id'] ?? 0);

$isInQueue = function ($request) {
    return $request->status === 'Pendiente' && empty($request->admin_dismissed_at);
};

$pendingQueue = array_values(array_filter($requests, $isInQueue));
$pendingCount = count($pendingQueue);
$approvedCount = count(array_filter($requests, fn($r) => $r->status === 'Aprobado'));
$rejectedCount = count(array_filter($requests, fn($r) => $r->status === 'Rechazado'));
$companyFilter = (int)($viewData['company_id'] ?? 0);
$companyName = $viewData['company_name'] ?? '';
$companies = $viewData['companies'] ?? [];
$pendingShiftSwaps = $viewData['pending_shift_swaps'] ?? [];
$pendingShiftCount = count($pendingShiftSwaps);

$pendingForJs = [];
foreach ($pendingQueue as $request) {
    $endDate = $request->end_date ?: $request->start_date;
    $pendingForJs[] = [
        'id' => (int)$request->id,
        'full_name' => $request->full_name,
        'type_name' => $request->type_name,
        'start_date' => $request->start_date,
        'end_date' => $endDate,
        'reason' => $request->reason ?? '',
        'admin_notes' => $request->admin_notes ?? '',
        'profile_picture' => $request->profile_picture ?? 'default.png',
        'certificate_url' => !empty($request->certificate_path)
            ? admin_request_certificate_stream_url((int)$request->id)
            : null,
        'vacation_preview' => $request->vacation_preview ?? null,
    ];
}
?>

<div class="admin-page-head">
    <div class="admin-page-brand">
        <div class="admin-page-icon"><i class="fas fa-file-signature"></i></div>
        <div class="admin-page-meta">
            <h2 class="page-title">Solicitudes</h2>
            <p class="page-subtitle mb-0">Vista operativa para aprobar, rechazar y revisar ausencias del equipo.</p>
        </div>
    </div>
    <div class="admin-page-actions">
        <span class="admin-status-pill is-pending"><?php echo $pendingCount; ?> ausencias pendientes</span>
        <?php if ($pendingShiftCount > 0): ?>
        <span class="admin-status-pill is-pending"><?php echo $pendingShiftCount; ?> cambios de turno</span>
        <?php endif; ?>
        <span class="admin-status-pill is-success"><?php echo $approvedCount; ?> aprobadas</span>
    </div>
</div>

<?php if (!empty($companies)): ?>
<div class="admin-toolbar flex-wrap mb-3">
    <span class="admin-toolbar-label"><i class="fas fa-building me-1"></i>Empresa activa</span>
    <div class="admin-filter-group">
        <?php foreach ($companies as $co): ?>
        <a href="<?php echo URLROOT; ?>/admin/requests?company_id=<?php echo (int)$co->id; ?>"
           class="admin-filter-chip <?php echo $companyFilter === (int)$co->id ? 'active' : ''; ?>">
            <?php echo htmlspecialchars($co->name); ?>
        </a>
        <?php endforeach; ?>
    </div>
    <p class="small text-muted mb-0 w-100">
        Mostrando solicitudes de <strong><?php echo htmlspecialchars($companyName ?: '—'); ?></strong>.
        Los empleados de otra empresa no aparecen hasta cambiar la empresa (arriba o en el selector del header).
    </p>
</div>
<?php endif; ?>

<?php $swapModuleReady = !empty($viewData['swap_module_ready']); ?>
<?php if (!$swapModuleReady): ?>
<div class="alert alert-warning small mb-3">
    <i class="fas fa-exclamation-triangle me-1"></i>
    La tabla <code>shift_swaps</code> no tiene el formato nuevo. Ejecutá <strong>migration_shift_swaps_fix.sql</strong> y luego <strong>migration_shift_swaps_accepter_null.sql</strong>.
</div>
<?php endif; ?>
<?php if (!empty($pendingShiftSwaps)): ?>
<section class="admin-surface mb-3" id="shift-swaps">
    <div class="admin-surface-head">
        <div>
            <h3 class="admin-surface-title"><i class="fas fa-exchange-alt"></i>Cambios de turno pendientes</h3>
            <p class="admin-surface-subtitle">Al aprobar, se busca el turno del compañero el mismo día y se intercambia en la planificación.</p>
        </div>
    </div>
    <div class="admin-surface-body">
        <div class="admin-mini-list">
            <?php foreach ($pendingShiftSwaps as $sw): ?>
            <div class="admin-mini-item" style="flex-wrap:wrap">
                <div class="flex-grow-1 min-width-0">
                    <strong><?php echo htmlspecialchars($sw->proposer_name); ?></strong>
                    <span class="text-muted"> ↔ </span>
                    <strong><?php echo htmlspecialchars($sw->accepter_name); ?></strong>
                    <div class="small text-muted">
                        <strong><?php echo htmlspecialchars($sw->proposer_name); ?></strong> cede
                        <?php echo date('d/m', strtotime($sw->proposer_date)); ?>
                        <?php echo htmlspecialchars($sw->proposer_shift_name ?: 'Turno'); ?>
                        (<?php echo substr($sw->proposer_start, 0, 5); ?>–<?php echo substr($sw->proposer_end, 0, 5); ?>)
                        · con <strong><?php echo htmlspecialchars($sw->accepter_name); ?></strong>
                        <?php if (empty($sw->accepter_date)): ?>
                        <br><em>Turno del compañero: se asigna al aprobar (mismo día)</em>
                        <?php else: ?>
                        <br>⇄ <?php echo date('d/m', strtotime($sw->accepter_date)); ?>
                        <?php echo htmlspecialchars($sw->accepter_shift_name ?: 'Turno'); ?>
                        (<?php echo substr($sw->accepter_start, 0, 5); ?>–<?php echo substr($sw->accepter_end, 0, 5); ?>)
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($sw->notes)): ?>
                    <div class="small"><?php echo htmlspecialchars($sw->notes); ?></div>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-1">
                    <form method="post" action="<?php echo URLROOT; ?>/admin/approveShiftSwap/<?php echo (int)$sw->id; ?>" class="d-inline"
                          onsubmit="return confirm('¿Aprobar el cambio de turno? Se actualizará la planificación de ambos empleados.');">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn btn-sm btn-success">Aprobar</button>
                    </form>
                    <form method="post" action="<?php echo URLROOT; ?>/admin/rejectShiftSwap/<?php echo (int)$sw->id; ?>" class="d-inline"
                          onsubmit="return confirm('¿Rechazar el cambio de turno?');">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger">Rechazar</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<div class="admin-kpi-grid">
    <div class="admin-kpi-card">
        <div class="admin-kpi-icon" style="background:#fff3cd;color:#8a5600"><i class="fas fa-inbox"></i></div>
        <div><div class="admin-kpi-value"><?php echo $pendingCount; ?></div><div class="admin-kpi-label">Pendientes</div></div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-icon" style="background:#d1fae5;color:#065f46"><i class="fas fa-check-circle"></i></div>
        <div><div class="admin-kpi-value"><?php echo $approvedCount; ?></div><div class="admin-kpi-label">Aprobadas</div></div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-icon" style="background:#fee2e2;color:#991b1b"><i class="fas fa-ban"></i></div>
        <div><div class="admin-kpi-value"><?php echo $rejectedCount; ?></div><div class="admin-kpi-label">Rechazadas</div></div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-icon" style="background:#eef2ff;color:#4338ca"><i class="fas fa-calendar-week"></i></div>
        <div><div class="admin-kpi-value"><?php echo count($requests); ?></div><div class="admin-kpi-label">Totales</div></div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <section class="admin-surface h-100">
            <div class="admin-surface-head">
                <div>
                    <h3 class="admin-surface-title"><i class="fas fa-calendar-alt"></i>Calendario de solicitudes</h3>
                    <p class="admin-surface-subtitle">Cruza ausencias aprobadas y pendientes en una sola vista.</p>
                </div>
            </div>
            <div class="admin-surface-body">
                <div id="calendar"></div>
            </div>
        </section>
    </div>
    <div class="col-lg-4">
        <section class="admin-surface h-100">
            <div class="admin-surface-head">
                <div>
                    <h3 class="admin-surface-title"><i class="fas fa-bell"></i>Prioridad inmediata</h3>
                    <p class="admin-surface-subtitle">Clic en una solicitud para revisarla.</p>
                </div>
            </div>
            <div class="admin-surface-body">
                <?php if ($pendingCount === 0): ?>
                    <div class="admin-empty py-4">
                        <i class="fas fa-check-double"></i>
                        Sin ausencias pendientes en bandeja.
                        <?php if ($pendingShiftCount > 0): ?>
                        <p class="small mb-0 mt-2">
                            Hay <a href="#shift-swaps"><?php echo $pendingShiftCount; ?> cambio(s) de turno</a> pendiente(s) arriba.
                        </p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="admin-mini-list" id="pendingRequestList">
                        <?php foreach ($pendingQueue as $request): ?>
                            <button type="button"
                                    class="admin-mini-item admin-mini-item--action"
                                    data-request-id="<?php echo (int)$request->id; ?>"
                                    aria-label="Revisar solicitud de <?php echo htmlspecialchars($request->full_name); ?>">
                                <div class="flex-grow-1 min-width-0 text-start">
                                    <strong><?php echo htmlspecialchars($request->full_name); ?></strong>
                                    <div class="small text-muted">
                                        <?php echo htmlspecialchars($request->type_name); ?>
                                        · <?php echo date('d/m', strtotime($request->start_date)); ?>
                                        <?php if ($request->end_date && $request->end_date !== $request->start_date): ?>
                                        – <?php echo date('d/m', strtotime($request->end_date)); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="admin-status-pill is-pending">Pendiente</span>
                                <i class="fas fa-chevron-right admin-mini-item-chevron text-muted"></i>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="admin-note-panel mt-3">
                    <div class="admin-note-title">Acciones rápidas</div>
                    <p class="admin-note-text mb-0">Aprobar, rechazar, descartar de la bandeja o adjuntar certificado desde el panel lateral.</p>
                </div>
            </div>
        </section>
    </div>
</div>

<!-- Panel lateral: revisión de solicitud (data-bs-scroll para no bloquear scroll del body si hace falta) -->
<div class="offcanvas offcanvas-end req-review-panel" tabindex="-1" id="requestReviewPanel" aria-labelledby="requestReviewPanelLabel" data-bs-scroll="true" data-bs-backdrop="true">
    <div class="offcanvas-header border-bottom">
        <div>
            <h5 class="offcanvas-title mb-0" id="requestReviewPanelLabel">Revisar solicitud</h5>
            <p class="small text-muted mb-0" id="reqReviewSubtitle"></p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="<?php echo URLROOT; ?>/admin/processRequest" enctype="multipart/form-data" id="requestReviewForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="request_id" id="reqReviewId" value="">

            <div class="req-review-person mb-3" id="reqReviewPerson"></div>

            <div class="req-review-meta mb-3" id="reqReviewMeta"></div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Motivo del empleado</label>
                <div class="req-review-reason" id="reqReviewReason"></div>
            </div>

            <div class="alert alert-info small mb-3" id="reqVacationPreview" style="display:none"></div>

            <div class="mb-3" id="reqVacationExceptionBlock" style="display:none">
                <label for="reqVacationException" class="form-label small fw-semibold">Justificación de excepción</label>
                <textarea name="vacation_exception_reason" id="reqVacationException" class="form-control form-control-sm" rows="2" placeholder="Obligatoria si no cumple aviso, inicio o fraccionamiento convencional"></textarea>
                <div class="form-text">Quedan registrados administrador, fecha y motivo.</div>
            </div>

            <div class="mb-3" id="reqReviewCertBlock" style="display:none">
                <label class="form-label small fw-semibold">Certificado adjunto</label>
                <div id="reqReviewCertLink"></div>
            </div>

            <div class="mb-3">
                <label for="reqReviewNotes" class="form-label small fw-semibold">Notas internas (admin)</label>
                <textarea name="admin_notes" id="reqReviewNotes" class="form-control form-control-sm" rows="2" placeholder="Observaciones visibles solo para administración"></textarea>
            </div>

            <div class="mb-4">
                <label for="reqReviewCertificate" class="form-label small fw-semibold">Adjuntar certificado</label>
                <input type="file" name="certificate" id="reqReviewCertificate" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp">
                <div class="form-text">PDF o imagen. Opcional al aprobar o guardar.</div>
            </div>

            <div class="req-review-actions d-grid gap-2">
                <button type="submit" name="action" value="approve" class="btn btn-success">
                    <i class="fas fa-check me-1"></i> Aprobar solicitud
                </button>
                <button type="submit" name="action" value="reject" class="btn btn-outline-danger">
                    <i class="fas fa-times me-1"></i> Rechazar
                </button>
                <button type="submit" name="action" value="save_certificate" class="btn btn-outline-primary">
                    <i class="fas fa-paperclip me-1"></i> Guardar certificado / notas
                </button>
                <button type="submit" name="action" value="dismiss" class="btn btn-outline-secondary">
                    <i class="fas fa-eye-slash me-1"></i> Descartar de prioridad
                </button>
            </div>
        </form>
    </div>
</div>

<section class="admin-surface">
    <div class="admin-surface-head">
        <div>
            <h3 class="admin-surface-title"><i class="fas fa-list-ul"></i>Detalle completo</h3>
            <p class="admin-surface-subtitle">Historial y acciones disponibles por solicitud.</p>
        </div>
    </div>
    <div class="admin-surface-body is-tight">
        <div class="table-responsive">
            <table class="table table-striped admin-table mb-0">
                <thead>
                    <tr>
                        <th>Empleado</th>
                        <th>Tipo</th>
                        <th>Fechas</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="6" class="text-center text-muted">No hay solicitudes para mostrar.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td>
                                    <div class="admin-person-cell">
                                        <img src="<?php echo URLROOT; ?>/uploads/avatars/<?php echo htmlspecialchars($request->profile_picture ?? 'default.png'); ?>"
                                             alt="<?php echo htmlspecialchars($request->full_name); ?>"
                                             onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';">
                                        <span class="admin-avatar-fallback" style="display:none;width:34px;height:34px;font-size:.74rem;"><?php echo mb_strtoupper(mb_substr($request->full_name, 0, 1, 'UTF-8'), 'UTF-8'); ?></span>
                                        <span class="admin-person-name"><?php echo htmlspecialchars($request->full_name); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($request->type_name); ?></td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($request->start_date)); ?>
                                    – <?php echo date('d/m/Y', strtotime($request->end_date ?: $request->start_date)); ?>
                                </td>
                                <td><?php echo htmlspecialchars($request->reason); ?></td>
                                <td>
                                    <?php if ($request->status === 'Aprobado'): ?>
                                        <span class="admin-status-pill is-success">Aprobado</span>
                                    <?php elseif ($request->status === 'Rechazado'): ?>
                                        <span class="admin-status-pill is-danger">Rechazado</span>
                                    <?php elseif (!empty($request->admin_dismissed_at)): ?>
                                        <span class="admin-status-pill is-muted">Descartada</span>
                                    <?php else: ?>
                                        <span class="admin-status-pill is-pending">Pendiente</span>
                                    <?php endif; ?>
                                    <?php if (!empty($request->certificate_path)): ?>
                                        <a href="<?php echo htmlspecialchars(admin_request_certificate_stream_url((int)$request->id)); ?>"
                                           target="_blank" rel="noopener" class="small d-block mt-1" title="Ver certificado"><i class="fas fa-paperclip"></i> Cert.</a>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?php if ($isInQueue($request)): ?>
                                        <button type="button" class="admin-icon-btn is-info js-open-request-review" data-request-id="<?php echo (int)$request->id; ?>" title="Revisar">
                                            <i class="fas fa-gavel"></i>
                                        </button>
                                    <?php endif; ?>
                                    <a href="<?php echo URLROOT; ?>/admin/editRequest/<?php echo $request->id; ?>" class="admin-icon-btn is-info" title="Editar"><i class="fas fa-pencil-alt"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
window.REQUESTS_PENDING = <?php echo json_encode($pendingForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.REQUESTS_OPEN_ID = <?php echo (int)$openRequestId; ?>;
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    if (!calendarEl || typeof window.FullCalendar === 'undefined') return;
    var calendar = new window.FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        events: <?php echo $viewData['calendarEvents'] ?? '[]'; ?>,
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false }
    });
    calendar.render();
});
</script>

<?php require APPROOT . '/views/inc/footer.php'; ?>
