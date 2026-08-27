<div class="tab-pane fade" id="tab-incidents">
    <p class="text-muted small mb-3">
        <i class="fas fa-lock me-1"></i>
        Registro interno de RRHH. Las incidencias <strong>no son visibles</strong> para el empleado en su portal.
    </p>

    <?php if (empty($data['incidents_ready'])): ?>
    <div class="alert alert-warning small">
        Ejecutá <code>migration_employee_incidents.sql</code> en la base de datos para habilitar este módulo (ver MIGRATIONS.md).
    </div>
    <?php else: ?>

    <div class="incident-form-panel">
        <h6 class="fw-semibold mb-3"><i class="fas fa-plus-circle me-1"></i>Nueva incidencia</h6>
        <form method="post"
              action="<?php echo URLROOT; ?>/admin/addEmployeeIncident/<?php echo (int)$data['user']->id; ?>"
              enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label small mb-0">Tipo</label>
                    <select name="incident_type" class="form-select form-select-sm" required>
                        <?php foreach (($data['incident_types'] ?? []) as $key => $label): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-0">Fecha del hecho</label>
                    <input type="date" name="incident_date" class="form-control form-control-sm" required
                           value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-0">Título (opcional)</label>
                    <input type="text" name="title" class="form-control form-control-sm" maxlength="255"
                           placeholder="Ej. Atraso reiterado">
                </div>
                <div class="col-12">
                    <label class="form-label small mb-0">Descripción / detalle</label>
                    <textarea name="description" class="form-control form-control-sm" rows="3" required
                              placeholder="Motivo, acuerdos, días de suspensión, referencia interna…"></textarea>
                </div>
                <div class="col-md-8">
                    <label class="form-label small mb-0">Adjunto (opcional)</label>
                    <input type="file" name="attachment" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    <div class="form-text">PDF, imagen o Word. Máx. 10 MB (ej. telegrama de despido).</div>
                </div>
                <div class="col-12 incident-suspension-options" style="display:none">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="deactivate_user" value="1" id="deactivate_user">
                        <label class="form-check-label small" for="deactivate_user">Desactivar usuario (bloquear login)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="notify_hr_email" value="1" id="notify_hr_email" checked>
                        <label class="form-check-label small" for="notify_hr_email">Notificar por email a administradores RRHH</label>
                    </div>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-save me-1"></i>Registrar incidencia
                    </button>
                </div>
            </div>
        </form>
        <script>
        (function(){
            var sel = document.querySelector('#tab-incidents select[name="incident_type"]');
            var box = document.querySelector('#tab-incidents .incident-suspension-options');
            if (!sel || !box) return;
            function toggle(){ box.style.display = sel.value === 'suspension' ? 'block' : 'none'; }
            sel.addEventListener('change', toggle);
            toggle();
        })();
        </script>
    </div>

    <h6 class="fw-semibold mb-2">Historial</h6>
    <?php if (empty($data['incidents'])): ?>
    <div class="text-center py-4 text-muted border rounded">
        Sin incidencias registradas para este empleado.
    </div>
    <?php else: ?>
    <?php foreach ($data['incidents'] as $inc): ?>
    <div class="incident-card">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
            <div>
                <span class="badge bg-<?php echo employee_incident_type_badge($inc->incident_type); ?>">
                    <?php echo htmlspecialchars(employee_incident_type_label($inc->incident_type)); ?>
                </span>
                <?php if (!empty($inc->title)): ?>
                <strong class="ms-2"><?php echo htmlspecialchars($inc->title); ?></strong>
                <?php endif; ?>
            </div>
            <?php if (!in_array($inc->workflow_status ?? 'draft', ['notified','received','refused'], true)): ?><form method="post"
                  action="<?php echo URLROOT; ?>/admin/deleteEmployeeIncident/<?php echo (int)$data['user']->id; ?>/<?php echo (int)$inc->id; ?>"
                  class="d-inline"
                  onsubmit="return confirm('¿Eliminar esta incidencia y su adjunto?');">
                <?php echo csrf_field(); ?>
                <button type="submit" class="btn btn-outline-danger btn-sm" title="Eliminar">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </form><?php endif; ?>
        </div>
        <p class="mb-2 small"><?php echo nl2br(htmlspecialchars($inc->description)); ?></p>
        <div class="incident-meta d-flex flex-wrap gap-3 align-items-center">
            <span class="badge bg-secondary"><?php echo htmlspecialchars($inc->workflow_status ?? 'draft'); ?></span>
            <span><i class="fas fa-calendar me-1"></i><?php echo date('d/m/Y', strtotime($inc->incident_date)); ?></span>
            <span><i class="fas fa-user-shield me-1"></i><?php echo htmlspecialchars($inc->admin_name); ?></span>
            <span><i class="fas fa-clock me-1"></i>Cargado <?php echo date('d/m/Y H:i', strtotime($inc->created_at)); ?></span>
            <?php if (!empty($inc->attachment_path)): ?>
            <a href="<?php echo htmlspecialchars(admin_employee_incident_stream_url((int)$data['user']->id, (int)$inc->id)); ?>"
               target="_blank" rel="noopener"
               class="btn btn-outline-secondary btn-sm py-0">
                <i class="fas fa-paperclip me-1"></i>
                <?php echo htmlspecialchars($inc->attachment_original_name ?: 'Ver adjunto'); ?>
            </a>
            <a href="<?php echo htmlspecialchars(admin_employee_incident_download_url((int)$data['user']->id, (int)$inc->id)); ?>"
               class="btn btn-outline-secondary btn-sm py-0" title="Descargar">
                <i class="fas fa-download"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php if (($inc->workflow_status ?? 'draft') === 'draft' && access_can('discipline.review')): ?><form class="mt-2" method="post" action="<?php echo URLROOT; ?>/discipline/review/<?php echo (int)$inc->id; ?>"><?php echo csrf_field(); ?><button class="btn btn-sm btn-outline-primary">Revisar</button></form><?php elseif (($inc->workflow_status ?? '') === 'reviewed' && access_can('discipline.review')): ?><form class="mt-2" method="post" action="<?php echo URLROOT; ?>/discipline/notify/<?php echo (int)$inc->id; ?>"><?php echo csrf_field(); ?><button class="btn btn-sm btn-primary">Notificar al empleado</button></form><?php endif; ?>
        <?php if (($inc->workflow_status ?? 'draft') !== 'void' && access_can('discipline.review')): ?><form class="d-flex gap-2 mt-2" method="post" action="<?php echo URLROOT; ?>/discipline/void/<?php echo (int)$inc->id; ?>"><?php echo csrf_field(); ?><input class="form-control form-control-sm" name="reason" placeholder="Motivo de anulación" required><button class="btn btn-sm btn-outline-danger">Anular</button></form><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    <?php endif; ?>
</div>
