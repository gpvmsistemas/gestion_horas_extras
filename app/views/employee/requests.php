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
?>

<div class="emp-page-header">
    <a href="<?php echo URLROOT; ?>/employee/index" class="emp-back-btn"><i class="fas fa-arrow-left"></i></a>
    <div>
        <h1 class="emp-page-title">Mis solicitudes</h1>
        <p class="emp-page-subtitle">Cambios de turno y ausencias</p>
    </div>
</div>

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

<div class="emp-req-tabs" role="tablist">
    <button type="button" role="tab" id="tab-swap" class="emp-req-tab <?php echo $activeTab === 'swap' ? 'active' : ''; ?>" data-tab="swap" aria-controls="empPanelSwap" aria-selected="<?php echo $activeTab === 'swap' ? 'true' : 'false'; ?>">
        <i class="fas fa-exchange-alt me-1"></i> Cambio de turno
    </button>
    <button type="button" role="tab" id="tab-absence" class="emp-req-tab <?php echo $activeTab === 'absence' ? 'active' : ''; ?>" data-tab="absence" aria-controls="empPanelAbsence" aria-selected="<?php echo $activeTab === 'absence' ? 'true' : 'false'; ?>">
        <i class="fas fa-file-medical me-1"></i> Licencias / ausencias
    </button>
</div>

<div id="empPanelSwap" class="emp-req-panel" role="tabpanel" aria-labelledby="tab-swap" <?php echo $activeTab !== 'swap' ? 'hidden' : ''; ?>>

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
        Tu usuario no tiene empresa asignada. Pedí a administración que te asignen una (Servicios Sociales, Casa Paviotti, A.M.S.S.I o Ecofarma).
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

</div><!-- /empPanelSwap -->

<div id="empPanelAbsence" class="emp-req-panel" role="tabpanel" aria-labelledby="tab-absence" <?php echo $activeTab !== 'absence' ? 'hidden' : ''; ?>>

<?php if (!empty($data['vacation_ready']) && $data['vacation_pending'] !== null && function_exists('employee_portal_can') && employee_portal_can('vacation_balance')): ?>
<div class="alert alert-info small py-2 mb-3">
    <i class="fas fa-umbrella-beach me-1"></i>
    Tenés <strong><?php echo vacation_format_days($data['vacation_pending']); ?></strong> días de vacaciones pendientes (total períodos abiertos).
</div>
<?php endif; ?>

<button class="emp-collapse-btn" id="btnNuevaSolicitud" aria-expanded="false" aria-controls="formNuevaSolicitud">
    <span><i class="fas fa-plus-circle me-2"></i>Nueva licencia / ausencia</span>
    <i class="fas fa-chevron-down emp-collapse-chevron"></i>
</button>
<div id="formNuevaSolicitud" class="emp-collapse-body" hidden>
    <div class="emp-card emp-form-card" style="margin-top:0;border-radius:0 0 var(--card-r) var(--card-r)">
        <?php if (empty($requestTypes)): ?>
        <p class="text-muted small mb-0">No hay tipos de ausencia habilitados. Consultá a RRHH.</p>
        <?php else: ?>
        <form action="<?php echo URLROOT; ?>/request/create" method="post" id="employeeAbsenceForm" data-vacation-preview-url="<?php echo URLROOT; ?>/request/vacationPreview">
            <?php echo csrf_field(); ?>
            <div class="emp-form-group">
                <label class="emp-label">Tipo</label>
                <select name="request_type_id" id="employeeRequestType" class="emp-input" required>
                    <?php foreach ($requestTypes as $type): ?>
                    <option value="<?php echo (int)$type->id; ?>" data-vacation="<?php echo stripos($type->name, 'vacac') !== false ? '1' : '0'; ?>"><?php echo htmlspecialchars($type->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="emp-form-row">
                <div class="emp-form-group">
                    <label class="emp-label">Desde</label>
                    <input type="date" name="start_date" id="employeeRequestStart" class="emp-input" required>
                </div>
                <div class="emp-form-group">
                    <label class="emp-label">Hasta (opcional)</label>
                    <input type="date" name="end_date" id="employeeRequestEnd" class="emp-input">
                </div>
            </div>
            <div class="emp-form-group">
                <label class="emp-label">Motivo</label>
                <textarea name="reason" class="emp-input emp-textarea" rows="3" required placeholder="Describí brevemente el motivo"></textarea>
            </div>
            <div class="alert alert-info small" id="employeeVacationEstimate" style="display:none"></div>
            <button type="submit" class="emp-btn-primary w-100"><i class="fas fa-paper-plane me-2"></i>Enviar</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="emp-section-title mt-4"><i class="fas fa-list-alt me-2" style="color:var(--clr-primary)"></i>Licencias y ausencias</div>
<?php
$absenceRequests = array_values(array_filter($requests, function ($r) {
    $n = mb_strtolower($r->type_name ?? '', 'UTF-8');
    if (strpos($n, 'cambio de turno') !== false || strpos($n, 'cambio turno') !== false || strpos($n, 'intercambio') !== false) {
        return false;
    }
    return true;
}));
?>
<?php if (empty($absenceRequests)): ?>
<div class="emp-card"><div class="emp-empty"><i class="fas fa-folder-open"></i><p>Sin solicitudes registradas</p></div></div>
<?php else: ?>
<?php foreach ($absenceRequests as $req):
    $bgc = '#e2e8f0'; $txtc = '#475569';
    if ($req->status === 'Aprobado') { $bgc = '#d1fae5'; $txtc = '#065f46'; }
    if ($req->status === 'Rechazado') { $bgc = '#fee2e2'; $txtc = '#991b1b'; }
?>
<div class="emp-request-card">
    <div class="emp-request-type" style="background:<?php echo htmlspecialchars($req->color ?? '#e91e8c'); ?>">
        <?php echo htmlspecialchars($req->type_name); ?>
    </div>
    <div class="emp-request-body">
        <div class="emp-request-dates">
            <i class="fas fa-calendar-day me-1"></i>
            <?php echo date('d/m/Y', strtotime($req->start_date)); ?>
            <?php if ($req->end_date && $req->end_date !== $req->start_date): ?>
            <span class="mx-1">→</span><?php echo date('d/m/Y', strtotime($req->end_date)); ?>
            <?php endif; ?>
        </div>
        <?php if (!empty($req->reason)): ?>
        <div class="emp-request-reason"><?php echo htmlspecialchars($req->reason); ?></div>
        <?php endif; ?>
    </div>
    <div class="emp-request-status">
        <span class="badge rounded-pill" style="background:<?php echo $bgc; ?>;color:<?php echo $txtc; ?>"><?php echo htmlspecialchars($req->status); ?></span>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div><!-- /empPanelAbsence -->

<div style="height:80px" class="d-lg-none"></div>

<?php require APPROOT . '/views/inc/footer.php'; ?>

<script>
(function() {
    var absenceForm = document.getElementById('employeeAbsenceForm');
    var requestType = document.getElementById('employeeRequestType');
    var requestStart = document.getElementById('employeeRequestStart');
    var requestEnd = document.getElementById('employeeRequestEnd');
    var estimate = document.getElementById('employeeVacationEstimate');
    var previewTimer = null;

    function refreshVacationEstimate() {
        if (!absenceForm || !requestType || !estimate) return;
        var option = requestType.options[requestType.selectedIndex];
        if (!option || option.dataset.vacation !== '1' || !requestStart.value) {
            estimate.style.display = 'none';
            return;
        }
        clearTimeout(previewTimer);
        previewTimer = setTimeout(function () {
            var fd = new FormData();
            var csrf = absenceForm.querySelector('input[name="csrf_token"]');
            if (csrf) fd.append('csrf_token', csrf.value);
            fd.append('start_date', requestStart.value);
            fd.append('end_date', requestEnd.value || requestStart.value);
            estimate.style.display = 'block';
            estimate.textContent = 'Calculando días y saldo…';
            fetch(absenceForm.dataset.vacationPreviewUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(p){
                    if (!p.ok) {
                        estimate.className = 'alert alert-warning small';
                        estimate.textContent = p.message || 'No se pudo calcular.';
                        return;
                    }
                    estimate.className = 'alert alert-info small';
                    estimate.innerHTML = 'Solicitud: <strong>' + p.days + ' días computables</strong>. Disponible: <strong>'
                        + p.total_available + '</strong>. Saldo posterior estimado: <strong>' + p.remaining_after
                        + '</strong>. Los días no solicitados seguirán pendientes.';
                })
                .catch(function(){ estimate.textContent = 'No se pudo calcular la vista previa.'; });
        }, 200);
    }
    [requestType, requestStart, requestEnd].forEach(function(el){ if(el) el.addEventListener('change', refreshVacationEstimate); });

    var tabs = document.querySelectorAll('.emp-req-tab[data-tab]');
    var panels = {
        swap: document.getElementById('empPanelSwap'),
        absence: document.getElementById('empPanelAbsence')
    };

    function showTab(name) {
        tabs.forEach(function(tab) {
            var on = tab.dataset.tab === name;
            tab.classList.toggle('active', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        Object.keys(panels).forEach(function(key) {
            if (panels[key]) panels[key].hidden = key !== name;
        });
        if (name === 'swap') {
            var sel = document.getElementById('accepter_user_id');
            if (sel) sel.focus();
        }
        var url = new URL(window.location.href);
        url.searchParams.set('tab', name);
        history.replaceState(null, '', url.pathname + '?' + url.searchParams.toString());
    }

    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            showTab(tab.dataset.tab);
        });
    });

    document.getElementById('btnNuevaSolicitud')?.addEventListener('click', function() {
        var body = document.getElementById('formNuevaSolicitud');
        if (!body) return;
        var expanded = this.getAttribute('aria-expanded') === 'true';
        this.setAttribute('aria-expanded', !expanded);
        this.querySelector('.emp-collapse-chevron').style.transform = expanded ? '' : 'rotate(180deg)';
        body.hidden = expanded;
    });
})();
</script>
