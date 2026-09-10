<?php
require APPROOT . '/views/inc/header.php';
$viewData = $data ?? [];
$userName = $_SESSION['user_full_name'] ?? 'Empleado';
$userPhoto = $_SESSION['user_profile_picture'] ?? 'default.png';
$userInitial = mb_strtoupper(mb_substr($userName, 0, 1, 'UTF-8'), 'UTF-8');
$shiftSwaps = $viewData['shiftSwaps'] ?? [];
$mySchedules = $viewData['mySchedules'] ?? [];
$colleagues = $viewData['colleagues'] ?? [];
$companyName = $viewData['company_name'] ?? ($_SESSION['user_company_name'] ?? null);
$hasCompany = !empty($viewData['has_company']) || !empty($_SESSION['user_company_id']);
$requests = $viewData['requests'] ?? [];
$requestTypes = $viewData['requestTypes'] ?? [];
$swapModuleReady = !empty($viewData['swap_module_ready']);
$activeTab = ($_GET['tab'] ?? 'swap') === 'absence' ? 'absence' : 'swap';
$preselectScheduleId = (int)($_GET['schedule_id'] ?? 0);
$agreementLeaveTypes = $viewData['agreement_leave_types'] ?? [];
$effectiveAgreement = $viewData['effective_agreement'] ?? null;
$hasAbsenceOptions = !empty($requestTypes) || !empty($agreementLeaveTypes);
$leaveCategories = function_exists('agreement_leave_categories') ? agreement_leave_categories() : [];
$absenceRequests = array_values(array_filter($requests, function ($r) {
    $n = mb_strtolower($r->type_name ?? '', 'UTF-8');
    if (strpos($n, 'cambio de turno') !== false || strpos($n, 'cambio turno') !== false || strpos($n, 'intercambio') !== false) {
        return false;
    }
    return true;
}));
?>

<div class="emp-page-header">
    <a href="<?php echo URLROOT; ?>/employee/index" class="emp-back-btn"><i class="fas fa-arrow-left"></i></a>
    <div>
        <?php if ($activeTab === 'absence'): ?>
        <h1 class="emp-page-title">Licencias y ausencias</h1>
        <p class="emp-page-subtitle">Avisá una falta, licencia médica o vacaciones</p>
        <?php else: ?>
        <h1 class="emp-page-title">Mis solicitudes</h1>
        <p class="emp-page-subtitle">Cambios de turno y ausencias</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($activeTab !== 'absence'): ?>
<div class="emp-user-chip">
    <img src="<?php echo URLROOT; ?>/uploads/avatars/<?php echo htmlspecialchars($userPhoto); ?>"
         alt="<?php echo htmlspecialchars($userName); ?>"
         class="emp-user-chip-avatar"
         onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
    <div class="emp-user-chip-fallback"><?php echo $userInitial; ?></div>
    <div>
        <p class="emp-user-chip-title">Centro de solicitudes</p>
        <p class="emp-user-chip-name"><?php echo htmlspecialchars($userName); ?></p>
        <p class="emp-user-chip-subtitle">
            <?php if ($companyName): ?>
            Empresa: <strong><?php echo htmlspecialchars($companyName); ?></strong> — solo podés intercambiar turnos con compañeros de tu empresa.
            <?php else: ?>
            Sin empresa asignada. Contactá a RR.HH. para poder solicitar cambios de turno.
            <?php endif; ?>
        </p>
    </div>
</div>
<?php endif; ?>

<div class="emp-req-tabs" role="tablist">
    <a href="<?php echo URLROOT; ?>/request/index?tab=swap" role="tab" id="tab-swap" class="emp-req-tab <?php echo $activeTab === 'swap' ? 'active' : ''; ?>" aria-selected="<?php echo $activeTab === 'swap' ? 'true' : 'false'; ?>">
        <i class="fas fa-exchange-alt"></i><span>Cambio de turno</span>
    </a>
    <a href="<?php echo URLROOT; ?>/request/index?tab=absence" role="tab" id="tab-absence" class="emp-req-tab <?php echo $activeTab === 'absence' ? 'active' : ''; ?>" aria-selected="<?php echo $activeTab === 'absence' ? 'true' : 'false'; ?>">
        <i class="fas fa-file-medical"></i><span>Licencias</span>
    </a>
</div>

<div id="panel-swap" class="emp-req-panel" role="tabpanel" aria-labelledby="tab-swap" <?php echo $activeTab !== 'swap' ? 'hidden' : ''; ?>>

<?php if (!$swapModuleReady): ?>
<div class="alert alert-warning small mb-3">
    <i class="fas fa-exclamation-triangle me-1"></i>
    La tabla <code>shift_swaps</code> no tiene el formato nuevo. Si ya corriste la migración y sigue el aviso, ejecutá
    <strong>migration_shift_swaps_fix.sql</strong> en MySQL (recrea la tabla correctamente).
</div>
<?php endif; ?>

<section class="emp-card emp-form-card">
    <h2 class="emp-section-title mb-3"><i class="fas fa-exchange-alt me-2" style="color:var(--clr-primary)"></i>Solicitar cambio de turno</h2>
    <p class="small text-muted mb-3">Elegí <strong>tu turno</strong> y <strong>con quién</strong> querés intercambiar<?php echo $companyName ? ' en <strong>' . htmlspecialchars($companyName) . '</strong>' : ''; ?>. Al aprobar, el sistema cruza los turnos del mismo día si existen en la planificación. Ver <a href="<?php echo URLROOT; ?>/employee/misHorarios">Mis horarios</a>.</p>

    <?php if (!$hasCompany): ?>
    <div class="alert alert-warning small mb-0">
        <i class="fas fa-building me-1"></i>
        Tu usuario no tiene empresa asignada. Pedí a administración que te asignen la sociedad que corresponde.
    </div>
    <?php elseif (empty($colleagues)): ?>
    <div class="emp-empty py-3">
        <i class="fas fa-users"></i>
        <p>No hay otros empleados activos en tu empresa para intercambiar turno.</p>
    </div>
    <?php else: ?>
    <form action="<?php echo URLROOT; ?>/request/createShiftSwap" method="post" class="emp-swap-form" id="empSwapForm">
        <?php echo csrf_field(); ?>
        <div class="emp-form-group emp-swap-colleague-select">
            <label class="emp-label" for="accepter_user_id">Cambiar turno con (compañero)</label>
            <select name="accepter_user_id" id="accepter_user_id" class="emp-input emp-input-highlight" required>
                <option value="">— Elegí un compañero de trabajo —</option>
                <?php foreach ($colleagues as $c): ?>
                <option value="<?php echo (int)$c->id; ?>"><?php echo htmlspecialchars($c->full_name); ?></option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted d-block mt-1"><?php echo count($colleagues); ?> compañero(s) en <?php echo htmlspecialchars($companyName ?: 'tu empresa'); ?></small>
        </div>
        <div class="emp-form-group">
            <label class="emp-label" for="proposer_schedule_id">Mi turno (el que cedo)</label>
            <?php if (empty($mySchedules)): ?>
            <p class="small text-warning mb-2"><i class="fas fa-calendar-xmark me-1"></i>No tenés turnos desde hoy en los próximos 60 días. Si en <a href="<?php echo URLROOT; ?>/employee/misHorarios">Mis horarios</a> ves turnos pasados, pedí a tu supervisor que cargue la planificación futura.</p>
            <select name="proposer_schedule_id" id="proposer_schedule_id" class="emp-input" disabled>
                <option value="">— Sin turnos disponibles —</option>
            </select>
            <?php else: ?>
            <select name="proposer_schedule_id" id="proposer_schedule_id" class="emp-input" required>
                <option value="">— Seleccioná tu turno —</option>
                <?php foreach ($mySchedules as $s): ?>
                <option value="<?php echo (int)$s->id; ?>" <?php echo ($preselectScheduleId === (int)$s->id) ? 'selected' : ''; ?>>
                    <?php
                    $dn = date('d/m/Y', strtotime($s->schedule_date));
                    $nm = employee_schedule_entry_label($s);
                    echo htmlspecialchars($dn . ' · ' . $nm . ' (' . substr($s->start_time, 0, 5) . '–' . substr($s->end_time, 0, 5) . ')');
                    ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </div>
        <div class="emp-form-group">
            <label class="emp-label">Motivo / comentario (opcional)</label>
            <textarea name="notes" class="emp-input emp-textarea" rows="2" placeholder="Ej. necesito el turno de la tarde por trámite personal"></textarea>
        </div>
        <button type="submit" class="emp-btn-primary w-100" <?php echo empty($mySchedules) ? 'disabled' : ''; ?>>
            <i class="fas fa-paper-plane me-2"></i>Enviar solicitud de cambio
        </button>
    </form>
    <?php endif; ?>
</section>

<div class="emp-section-title mt-4"><i class="fas fa-history me-2" style="color:var(--clr-primary)"></i>Cambios de turno</div>
<?php if (empty($shiftSwaps)): ?>
<div class="emp-card"><div class="emp-empty"><i class="fas fa-inbox"></i><p>Sin solicitudes de cambio de turno</p></div></div>
<?php else: ?>
<?php foreach ($shiftSwaps as $sw):
    $st = $sw->status;
    $bg = '#fff3cd'; $tx = '#8a5600';
    if ($st === 'Aprobado') { $bg = '#d1fae5'; $tx = '#065f46'; }
    if ($st === 'Rechazado') { $bg = '#fee2e2'; $tx = '#991b1b'; }
    $isProposer = ((int)$sw->proposer_user_id === (int)$_SESSION['user_id']);
?>
<div class="emp-request-card emp-swap-card">
    <div class="emp-request-type" style="background:#6366f1"><i class="fas fa-exchange-alt"></i></div>
    <div class="emp-request-body">
        <strong style="font-size:.85rem">Cambio de turno</strong>
        <div class="small text-muted mt-1">
            <?php if ($isProposer): ?>
            Mi turno: <strong><?php echo date('d/m', strtotime($sw->proposer_date)); ?></strong>
            <?php echo htmlspecialchars($sw->proposer_shift_name ?: 'Turno'); ?>
            (<?php echo substr($sw->proposer_start, 0, 5); ?>–<?php echo substr($sw->proposer_end, 0, 5); ?>)
            <br>Con <strong><?php echo htmlspecialchars($sw->accepter_name); ?></strong>
            <?php if (!empty($sw->accepter_date)): ?>
            <br>Intercambio aplicado: <?php echo date('d/m', strtotime($sw->accepter_date)); ?>
            <?php echo htmlspecialchars($sw->accepter_shift_name ?: 'Turno'); ?>
            (<?php echo substr($sw->accepter_start, 0, 5); ?>–<?php echo substr($sw->accepter_end, 0, 5); ?>)
            <?php elseif ($st === 'Pendiente'): ?>
            <br><span class="text-muted">Su turno se define al aprobar (mismo día en planificación)</span>
            <?php endif; ?>
            <?php else: ?>
            <strong><?php echo htmlspecialchars($sw->proposer_name); ?></strong> solicita cambio de turno contigo.
            <?php endif; ?>
        </div>
        <?php if (!empty($sw->notes)): ?>
        <div class="emp-request-reason small"><?php echo htmlspecialchars($sw->notes); ?></div>
        <?php endif; ?>
        <?php if ($st === 'Aprobado'): ?>
        <div class="small text-success mt-1"><i class="fas fa-check-circle me-1"></i>Reflejado en tu calendario de horarios</div>
        <?php endif; ?>
    </div>
    <div class="emp-request-status">
        <span class="badge rounded-pill" style="background:<?php echo $bg; ?>;color:<?php echo $tx; ?>"><?php echo htmlspecialchars($st); ?></span>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div><!-- /panel-swap -->

<div id="panel-absence" class="emp-absence" role="tabpanel" aria-labelledby="tab-absence" <?php echo $activeTab !== 'absence' ? 'hidden' : ''; ?>>

<?php if (!empty($data['vacation_ready']) && $data['vacation_pending'] !== null && function_exists('employee_portal_can') && employee_portal_can('vacation_balance')): ?>
<div class="emp-absence-stat">
    <div class="emp-absence-stat-icon"><i class="fas fa-umbrella-beach"></i></div>
    <div>
        <p class="emp-absence-stat-value"><?php echo vacation_format_days($data['vacation_pending']); ?></p>
        <p class="emp-absence-stat-label">días de vacaciones disponibles</p>
        <a class="small" href="<?php echo htmlspecialchars(vacation_planilla_employee_url()); ?>" target="_blank" rel="noopener">Imprimir planilla</a>
    </div>
</div>
<?php endif; ?>

<?php if (!$effectiveAgreement && function_exists('agreement_leave_types_ready') && agreement_leave_types_ready()): ?>
<div class="emp-absence-alert">
    <i class="fas fa-info-circle"></i>
    <p>Sin convenio en tu ficha. Pedí a RRHH que lo carguen para ver tus licencias.</p>
</div>
<?php elseif (!empty($agreementLeaveTypes)): ?>
<div class="emp-absence-alert is-info">
    <i class="fas fa-heartbeat"></i>
    <div>
        <strong>Enfermedad</strong>
        <p>Solo avisá que estás enfermo/a: queda <em>registrado al instante</em>, sin esperar aprobación. El certificado médico (frente y dorso) podés subirlo después.</p>
    </div>
</div>
<?php endif; ?>

<section class="emp-card emp-absence-form-card" id="nueva-solicitud">
    <div class="emp-absence-form-head">
        <h2><i class="fas fa-plus-circle"></i> Nueva solicitud</h2>
        <?php if ($effectiveAgreement): ?>
        <span class="emp-absence-convenio"><?php echo htmlspecialchars($effectiveAgreement->code); ?></span>
        <?php endif; ?>
    </div>
    <?php if (!$hasAbsenceOptions): ?>
    <p class="text-muted small mb-0">No hay tipos de ausencia habilitados. Consultá a RRHH.</p>
    <?php else: ?>
    <form action="<?php echo URLROOT; ?>/request/create" method="post" enctype="multipart/form-data" id="employeeAbsenceForm" class="emp-absence-form" data-vacation-preview-url="<?php echo URLROOT; ?>/request/vacationPreview">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="request_type_id" id="employeeRequestTypeId" value="">
        <input type="hidden" name="agreement_leave_type_id" id="employeeAgreementLeaveTypeId" value="">
        <div class="emp-form-group">
            <label class="emp-label" for="employeeAbsenceSelection">Motivo</label>
            <select id="employeeAbsenceSelection" class="emp-input" required>
                <option value="">Elegí el motivo</option>
                <?php if (!empty($requestTypes)): ?>
                <optgroup label="Vacaciones y otras">
                    <?php foreach ($requestTypes as $type): ?>
                    <option value="rt:<?php echo (int)$type->id; ?>" data-vacation="<?php echo stripos($type->name, 'vacac') !== false ? '1' : '0'; ?>">
                        <?php echo htmlspecialchars($type->name); ?>
                    </option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
                <?php if (!empty($agreementLeaveTypes)): ?>
                <optgroup label="Licencias del convenio">
                    <?php foreach ($agreementLeaveTypes as $leave): ?>
                    <option value="alt:<?php echo (int)$leave->id; ?>"
                            data-requires-cert="<?php echo !empty($leave->requires_certificate) ? '1' : '0'; ?>"
                            data-auto-register="<?php echo agreement_leave_type_requires_approval($leave) ? '0' : '1'; ?>"
                            data-max-event="<?php echo htmlspecialchars((string)($leave->max_days_per_event ?? '')); ?>"
                            data-max-year="<?php echo htmlspecialchars((string)($leave->max_days_per_year ?? '')); ?>">
                        <?php echo htmlspecialchars($leave->name); ?>
                        <?php if (!empty($leave->requires_certificate)): ?> · certificado<?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
            </select>
            <p class="emp-absence-hint" id="employeeLeaveHint" hidden></p>
        </div>
        <div class="emp-absence-dates">
            <div class="emp-form-group">
                <label class="emp-label" for="employeeRequestStart">Desde</label>
                <input type="date" name="start_date" id="employeeRequestStart" class="emp-input" required>
            </div>
            <div class="emp-form-group">
                <label class="emp-label" for="employeeRequestEnd">Hasta</label>
                <input type="date" name="end_date" id="employeeRequestEnd" class="emp-input" placeholder="Opcional">
                <small class="text-muted">Dejá vacío si es un solo día</small>
            </div>
        </div>
        <div class="emp-form-group">
            <label class="emp-label" for="employeeRequestReason">Comentario</label>
            <textarea name="reason" id="employeeRequestReason" class="emp-input emp-textarea" rows="2" required placeholder="Ej. enfermedad, trámite personal…"></textarea>
        </div>
        <div class="emp-absence-cert" id="employeeCertificateGroup" hidden>
            <p class="emp-absence-cert-title"><i class="fas fa-file-medical"></i> Certificado médico <span>(opcional ahora)</span></p>
            <div class="emp-absence-cert-files">
                <div class="emp-absence-cert-file">
                    <label class="emp-label" for="employeeRequestCertificate">Frente</label>
                    <input type="file" name="certificate" id="employeeRequestCertificate" class="emp-input emp-file-input" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
                </div>
                <div class="emp-absence-cert-file">
                    <label class="emp-label" for="employeeRequestCertificateBack">Dorso</label>
                    <input type="file" name="certificate_back" id="employeeRequestCertificateBack" class="emp-input emp-file-input" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
                </div>
            </div>
            <p class="emp-absence-hint">Podés enviar la solicitud ahora y subir frente y dorso después desde tu historial.</p>
        </div>
        <div class="emp-absence-preview" id="employeeVacationEstimate" hidden></div>
        <button type="submit" class="emp-btn-primary emp-absence-submit"><i class="fas fa-paper-plane"></i> Enviar solicitud</button>
    </form>
    <?php endif; ?>
</section>

<?php if (function_exists('agreement_leave_types_ready') && agreement_leave_types_ready() && $effectiveAgreement && !empty($agreementLeaveTypes)): ?>
<details class="emp-absence-catalog">
    <summary>
        <span><i class="fas fa-book-medical"></i> Licencias de tu convenio</span>
        <span class="emp-absence-catalog-count"><?php echo count($agreementLeaveTypes); ?></span>
    </summary>
    <ul class="emp-absence-license-list">
        <?php foreach ($agreementLeaveTypes as $leave): ?>
        <li class="emp-absence-license-item">
            <strong><?php echo htmlspecialchars($leave->name); ?></strong>
            <span>
                <?php echo htmlspecialchars($leaveCategories[$leave->category] ?? $leave->category); ?>
                <?php if (!empty($leave->requires_certificate)): ?> · Certificado<?php endif; ?>
            </span>
            <?php if (!empty($leave->description)): ?>
            <p><?php echo htmlspecialchars($leave->description); ?></p>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
</details>
<?php endif; ?>

<section class="emp-absence-history">
    <h3 class="emp-absence-history-title"><i class="fas fa-history"></i> Mis solicitudes</h3>
<?php if (empty($absenceRequests)): ?>
    <div class="emp-card emp-absence-empty">
        <i class="fas fa-inbox"></i>
        <p>Todavía no registraste ausencias</p>
    </div>
<?php else: ?>
<?php foreach ($absenceRequests as $req):
    $statusClass = 'is-pending';
    if ($req->status === 'Aprobado') { $statusClass = 'is-approved'; }
    if ($req->status === 'Rechazado') { $statusClass = 'is-rejected'; }
    $statusLabel = function_exists('employee_request_status_label') ? employee_request_status_label($req) : $req->status;
    $canUploadCert = !empty($req->agreement_leave_requires_certificate)
        && (($req->status === 'Pendiente') || ($req->status === 'Aprobado' && !agreement_leave_type_requires_approval($req)));
    $needsCert = $canUploadCert && empty($req->certificate_path) && empty($req->certificate_back_path);
    $needsCertComplete = $canUploadCert && (empty($req->certificate_path) || empty($req->certificate_back_path));
?>
<article class="emp-absence-card <?php echo $statusClass; ?>">
    <header class="emp-absence-card-head">
        <span class="emp-absence-card-type"><?php echo htmlspecialchars($req->type_name); ?></span>
        <span class="emp-absence-card-status"><?php echo htmlspecialchars($statusLabel); ?></span>
    </header>
    <p class="emp-absence-card-dates">
        <i class="fas fa-calendar-day"></i>
        <?php echo date('d/m/Y', strtotime($req->start_date)); ?>
        <?php if ($req->end_date && $req->end_date !== $req->start_date): ?>
        <span>→</span><?php echo date('d/m/Y', strtotime($req->end_date)); ?>
        <?php endif; ?>
    </p>
    <?php if (function_exists('vacation_is_vacation_request') && vacation_is_vacation_request($req)): ?>
    <p class="mb-2">
        <a class="small" href="<?php echo htmlspecialchars(vacation_planilla_employee_url((int)$req->id)); ?>" target="_blank" rel="noopener">
            <i class="fas fa-print"></i> Imprimir planilla
        </a>
    </p>
    <?php endif; ?>
    <?php if (!empty($req->reason)): ?>
    <p class="emp-absence-card-reason"><?php echo htmlspecialchars($req->reason); ?></p>
    <?php endif; ?>
    <?php if ($needsCert): ?>
    <p class="emp-absence-card-warn"><i class="fas fa-exclamation-circle"></i> Falta certificado médico (frente y dorso)</p>
    <?php elseif ($needsCertComplete && ($req->certificate_path || $req->certificate_back_path)): ?>
    <p class="emp-absence-card-warn is-soft"><i class="fas fa-info-circle"></i> Completá frente y dorso del certificado cuando los tengas</p>
    <?php endif; ?>
    <?php if (!empty($req->certificate_path) || !empty($req->certificate_back_path)): ?>
    <div class="emp-absence-cert-gallery">
        <p class="emp-absence-cert-gallery-title"><i class="fas fa-file-image"></i> Certificados</p>
        <div class="emp-absence-cert-chips">
        <?php if (!empty($req->certificate_path)): ?>
            <?php if (function_exists('upload_filename_is_image') && upload_filename_is_image($req->certificate_path)): ?>
            <button type="button" class="emp-absence-cert-chip js-emp-cert-lightbox"
                    data-cert-url="<?php echo htmlspecialchars(request_certificate_stream_url((int)$req->id)); ?>"
                    data-cert-label="Certificado — Frente">
                <span class="emp-absence-cert-chip-thumb">
                    <img src="<?php echo htmlspecialchars(request_certificate_stream_url((int)$req->id)); ?>" alt="" loading="lazy">
                </span>
                <span class="emp-absence-cert-chip-text">
                    <span class="emp-absence-cert-chip-label">Frente</span>
                    <span class="emp-absence-cert-chip-hint">Tocá para ampliar</span>
                </span>
            </button>
            <?php else: ?>
            <a href="<?php echo htmlspecialchars(request_certificate_stream_url((int)$req->id)); ?>" target="_blank" rel="noopener" class="emp-absence-cert-file-link"><i class="fas fa-paperclip"></i> Frente</a>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (!empty($req->certificate_back_path)): ?>
            <?php if (function_exists('upload_filename_is_image') && upload_filename_is_image($req->certificate_back_path)): ?>
            <button type="button" class="emp-absence-cert-chip js-emp-cert-lightbox"
                    data-cert-url="<?php echo htmlspecialchars(request_certificate_back_stream_url((int)$req->id)); ?>"
                    data-cert-label="Certificado — Dorso">
                <span class="emp-absence-cert-chip-thumb">
                    <img src="<?php echo htmlspecialchars(request_certificate_back_stream_url((int)$req->id)); ?>" alt="" loading="lazy">
                </span>
                <span class="emp-absence-cert-chip-text">
                    <span class="emp-absence-cert-chip-label">Dorso</span>
                    <span class="emp-absence-cert-chip-hint">Tocá para ampliar</span>
                </span>
            </button>
            <?php else: ?>
            <a href="<?php echo htmlspecialchars(request_certificate_back_stream_url((int)$req->id)); ?>" target="_blank" rel="noopener" class="emp-absence-cert-file-link"><i class="fas fa-paperclip"></i> Dorso</a>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($canUploadCert): ?>
    <details class="emp-absence-cert-upload"<?php echo ($needsCert || $needsCertComplete) ? ' open' : ''; ?>>
        <summary><i class="fas fa-upload"></i> <?php echo empty($req->certificate_path) && empty($req->certificate_back_path) ? 'Subir certificado (frente y dorso)' : 'Actualizar certificado'; ?></summary>
        <form action="<?php echo URLROOT; ?>/request/uploadCertificate/<?php echo (int)$req->id; ?>" method="post" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="emp-absence-cert-files">
                <div class="emp-absence-cert-file">
                    <label for="cert-front-<?php echo (int)$req->id; ?>">Frente</label>
                    <input type="file" name="certificate" id="cert-front-<?php echo (int)$req->id; ?>" class="emp-input emp-file-input" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
                </div>
                <div class="emp-absence-cert-file">
                    <label for="cert-back-<?php echo (int)$req->id; ?>">Dorso</label>
                    <input type="file" name="certificate_back" id="cert-back-<?php echo (int)$req->id; ?>" class="emp-input emp-file-input" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
                </div>
            </div>
            <button type="submit" class="emp-btn-primary emp-absence-submit-sm">Guardar certificado</button>
        </form>
    </details>
    <?php endif; ?>
</article>
<?php endforeach; ?>
<?php endif; ?>
</section>

</div><!-- /panel-absence -->

<div class="modal fade" id="empCertLightboxModal" tabindex="-1" aria-labelledby="empCertLightboxTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content emp-cert-lightbox">
            <div class="modal-header py-2 px-3">
                <h2 class="modal-title h6 mb-0" id="empCertLightboxTitle">Certificado</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body emp-cert-lightbox-body">
                <img id="empCertLightboxImage" src="" alt="" class="emp-cert-lightbox-img">
            </div>
            <div class="modal-footer py-2 px-3 justify-content-between">
                <a href="#" id="empCertLightboxOpen" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-external-link-alt me-1"></i> Abrir en pestaña
                </a>
                <button type="button" class="btn btn-sm btn-primary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="emp-page-bottom-spacer d-lg-none" aria-hidden="true"></div>

<?php require APPROOT . '/views/inc/footer.php'; ?>

<script>
(function() {
    var absenceForm = document.getElementById('employeeAbsenceForm');
    var absenceSelection = document.getElementById('employeeAbsenceSelection');
    var requestTypeId = document.getElementById('employeeRequestTypeId');
    var agreementLeaveTypeId = document.getElementById('employeeAgreementLeaveTypeId');
    var leaveHint = document.getElementById('employeeLeaveHint');
    var certificateGroup = document.getElementById('employeeCertificateGroup');
    var certificateInput = document.getElementById('employeeRequestCertificate');
    var requestStart = document.getElementById('employeeRequestStart');
    var requestEnd = document.getElementById('employeeRequestEnd');
    var estimate = document.getElementById('employeeVacationEstimate');
    var previewTimer = null;

    function syncAbsenceSelection() {
        if (!absenceSelection) return;
        var value = absenceSelection.value || '';
        var isVacation = false;
        if (requestTypeId) requestTypeId.value = '';
        if (agreementLeaveTypeId) agreementLeaveTypeId.value = '';
        if (leaveHint) {
            leaveHint.hidden = true;
            leaveHint.textContent = '';
        }
        if (certificateGroup) certificateGroup.hidden = true;
        if (certificateInput) {
            certificateInput.required = false;
            certificateInput.value = '';
        }
        if (value.indexOf('rt:') === 0 && requestTypeId) {
            requestTypeId.value = value.slice(3);
            var option = absenceSelection.options[absenceSelection.selectedIndex];
            isVacation = option && option.dataset.vacation === '1';
        } else if (value.indexOf('alt:') === 0 && agreementLeaveTypeId) {
            agreementLeaveTypeId.value = value.slice(4);
            var leaveOption = absenceSelection.options[absenceSelection.selectedIndex];
            if (leaveHint && leaveOption) {
                var hints = [];
                if (leaveOption.dataset.autoRegister === '1') {
                    hints.push('Solo avisás que estás enfermo/a: queda registrado al instante, sin esperar aprobación de RRHH.');
                }
                if (leaveOption.dataset.requiresCert === '1') {
                    hints.push('Podés enviar ahora sin certificado y adjuntarlo después desde tu historial (frente y dorso).');
                    if (certificateGroup) certificateGroup.hidden = false;
                    if (certificateInput) certificateInput.required = false;
                }
                if (leaveOption.dataset.maxEvent) hints.push('Máximo ' + leaveOption.dataset.maxEvent + ' día(s) por evento.');
                if (leaveOption.dataset.maxYear) hints.push('Máximo ' + leaveOption.dataset.maxYear + ' día(s) por año calendario.');
                if (hints.length) {
                    leaveHint.textContent = hints.join(' ');
                    leaveHint.hidden = false;
                }
            }
        }
        if (!isVacation && estimate) {
            estimate.hidden = true;
            estimate.textContent = '';
        }
        return isVacation;
    }

    function refreshVacationEstimate() {
        if (!absenceForm || !estimate) return;
        if (!syncAbsenceSelection()) return;
        if (!requestStart || !requestStart.value) {
            estimate.hidden = true;
            return;
        }
        clearTimeout(previewTimer);
        previewTimer = setTimeout(function () {
            var fd = new FormData();
            var csrf = absenceForm.querySelector('input[name="csrf_token"]');
            if (csrf) fd.append('csrf_token', csrf.value);
            fd.append('start_date', requestStart.value);
            fd.append('end_date', requestEnd.value || requestStart.value);
            estimate.hidden = false;
            estimate.className = 'emp-absence-preview is-loading';
            estimate.textContent = 'Calculando días y saldo…';
            fetch(absenceForm.dataset.vacationPreviewUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(p){
                    if (!p.ok) {
                        estimate.className = 'emp-absence-preview is-warning';
                        estimate.textContent = p.message || 'No se pudo calcular.';
                        return;
                    }
                    estimate.className = 'emp-absence-preview is-info';
                    estimate.innerHTML = 'Solicitud: <strong>' + p.days + ' días</strong> · Disponible: <strong>'
                        + p.total_available + '</strong> · Quedarían: <strong>' + p.remaining_after + '</strong>';
                })
                .catch(function(){
                    estimate.className = 'emp-absence-preview is-warning';
                    estimate.textContent = 'No se pudo calcular la vista previa.';
                });
        }, 200);
    }

    absenceForm?.addEventListener('submit', function(e) {
        syncAbsenceSelection();
        if (!requestTypeId?.value && !agreementLeaveTypeId?.value) {
            e.preventDefault();
            alert('Elegí un motivo para la solicitud.');
        }
    });
    [absenceSelection, requestStart, requestEnd].forEach(function(el){ if(el) el.addEventListener('change', refreshVacationEstimate); });

    if (absenceSelection) syncAbsenceSelection();

    var formAnchor = document.getElementById('nueva-solicitud');
    if (formAnchor && window.location.search.indexOf('tab=absence') !== -1) {
        var hasFlash = document.querySelector('.alert-success, .alert-danger, .flash-message');
        if (hasFlash) {
            formAnchor.scrollIntoView({behavior: 'smooth', block: 'start'});
        }
    }

    var certModalEl = document.getElementById('empCertLightboxModal');
    var certModalImg = document.getElementById('empCertLightboxImage');
    var certModalTitle = document.getElementById('empCertLightboxTitle');
    var certModalOpen = document.getElementById('empCertLightboxOpen');
    var certModal = certModalEl && typeof bootstrap !== 'undefined'
        ? bootstrap.Modal.getOrCreateInstance(certModalEl)
        : null;

    document.addEventListener('click', function (e) {
        var chip = e.target.closest('.js-emp-cert-lightbox');
        if (!chip || !certModal || !certModalImg) return;
        e.preventDefault();
        var url = chip.getAttribute('data-cert-url') || '';
        var label = chip.getAttribute('data-cert-label') || 'Certificado';
        if (!url) return;
        certModalImg.src = url;
        certModalImg.alt = label;
        if (certModalTitle) certModalTitle.textContent = label;
        if (certModalOpen) certModalOpen.href = url;
        certModal.show();
    });

    if (certModalEl) {
        certModalEl.addEventListener('hidden.bs.modal', function () {
            if (certModalImg) {
                certModalImg.removeAttribute('src');
                certModalImg.alt = '';
            }
        });
    }
})();
</script>
