<?php require APPROOT . '/views/inc/header.php'; ?>

<?php
$data = $data ?? [];
$diasES  = ['Monday'=>'Lun','Tuesday'=>'Mar','Wednesday'=>'Mié','Thursday'=>'Jue','Friday'=>'Vie','Saturday'=>'Sáb','Sunday'=>'Dom'];
$mesesES = ['January'=>'Enero','February'=>'Febrero','March'=>'Marzo','April'=>'Abril','May'=>'Mayo','June'=>'Junio',
            'July'=>'Julio','August'=>'Agosto','September'=>'Septiembre','October'=>'Octubre','November'=>'Noviembre','December'=>'Diciembre'];

$primerNombre = explode(' ', $_SESSION['user_full_name'] ?? 'Empleado')[0];
$birthDate = $data['user']->birth_date ?? null;
$esCumple  = $birthDate && date('m-d') === date('m-d', strtotime($birthDate));
$saludo    = $esCumple ? '¡Feliz cumpleaños!' : 'Hola';
$pendingOvertimeCount = count($data['recentOvertime'] ?? []);
$usesCpTasks = !empty($data['uses_cp_tasks']);
$showCpHome = !empty($data['show_cp_home']);
$showOvertimeHome = !empty($data['show_overtime_home']);
$cpPendingTotal = (float)($data['cp_pending_total'] ?? 0);
$cpPendingCount = (int)($data['cp_pending_count'] ?? 0);
$vacationPending = $data['vacation_pending'] ?? null;
$userPhoto = !empty($data['user']->profile_picture) ? $data['user']->profile_picture : 'default.png';
$userInitial = mb_strtoupper(mb_substr($primerNombre, 0, 1, 'UTF-8'), 'UTF-8');
?>

<!-- ══ HERO GREETING ══ -->
<div class="emp-hero">
    <div class="emp-hero-text">
        <?php if ($usesCpTasks && function_exists('company_brand_logo_url')): ?>
        <img src="<?php echo htmlspecialchars(company_brand_logo_url()); ?>" alt="" class="emp-hero-company-logo">
        <?php endif; ?>
        <p class="emp-hero-greeting"><?php echo $saludo; ?> <?php echo $esCumple ? '🎂' : '👋'; ?></p>
        <h1 class="emp-hero-name"><?php echo htmlspecialchars($primerNombre); ?></h1>
        <?php if ($usesCpTasks && !empty($_SESSION['user_company_name'])): ?>
        <p class="emp-hero-company-name small text-muted mb-0"><?php echo htmlspecialchars($_SESSION['user_company_name']); ?></p>
        <?php endif; ?>
        <p class="emp-hero-date"><?php
            $dn = date('l'); $mn = date('F');
            echo ($diasES[$dn] ?? $dn) . ', ' . date('j') . ' de ' . ($mesesES[$mn] ?? $mn);
        ?></p>
    </div>
    <div class="emp-hero-avatar">
        <img src="<?php echo URLROOT; ?>/uploads/avatars/<?php echo htmlspecialchars($userPhoto); ?>"
             alt="<?php echo htmlspecialchars($primerNombre); ?>"
             class="emp-hero-avatar-image"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
        <div class="emp-hero-avatar" style="display:none;">
            <?php echo $userInitial; ?>
        </div>
    </div>
</div>

<?php require APPROOT . '/views/inc/partials/pwa_install_settings.php'; ?>

<?php if ($showCpHome): ?>
<a href="<?php echo URLROOT; ?>/cpTask/index" class="emp-cp-hero-cta text-decoration-none">
    <span class="emp-cp-hero-cta-icon" aria-hidden="true"><i class="fas fa-clipboard-list"></i></span>
    <span class="emp-cp-hero-cta-body">
        <span class="emp-cp-hero-cta-title">Cargar</span>
        <span class="emp-cp-hero-cta-sub">
            <?php if ($cpPendingCount > 0): ?>
                Tenés <?php echo $cpPendingCount; ?> pendiente<?php echo $cpPendingCount === 1 ? '' : 's'; ?> · <?php echo cp_format_money($cpPendingTotal); ?>
            <?php else: ?>
                Registrá una tarea de Casa Paviotti
            <?php endif; ?>
        </span>
    </span>
    <span class="emp-cp-hero-cta-arrow" aria-hidden="true"><i class="fas fa-chevron-right"></i></span>
</a>
<?php endif; ?>

<?php if (!empty($data['prode_open'])): ?>
<a href="<?php echo URLROOT; ?>/prode/index" class="emp-cp-hero-cta text-decoration-none mb-3" style="background:linear-gradient(135deg,#0d6efd,#1e40af);color:#fff;border:none">
    <span class="emp-cp-hero-cta-icon" aria-hidden="true">⚽</span>
    <span class="emp-cp-hero-cta-body">
        <span class="emp-cp-hero-cta-title">Copa del mundo 2026</span>
        <span class="emp-cp-hero-cta-sub" style="color:rgba(255,255,255,.85)">
            <?php if (!empty($data['prode_submitted'])): ?>
                Pronósticos confirmados · <?php echo (int)($data['prode_filled'] ?? 0); ?>/<?php echo (int)($data['prode_total'] ?? 0); ?> partidos
            <?php else: ?>
                Completá tus pronósticos · <?php echo (int)($data['prode_filled'] ?? 0); ?>/<?php echo (int)($data['prode_total'] ?? 0); ?> cargados
            <?php endif; ?>
        </span>
    </span>
    <span class="emp-cp-hero-cta-arrow" aria-hidden="true"><i class="fas fa-chevron-right"></i></span>
</a>
<?php endif; ?>

<!-- ══ STATS RÁPIDAS ══ -->
<div class="emp-stats-row">
    <div class="emp-stat-card">
        <i class="fas fa-file-alt emp-stat-icon" style="color:var(--clr-warning)"></i>
        <span class="emp-stat-value"><?php echo $data['pendingRequestsCount']; ?></span>
        <span class="emp-stat-label">Solicitudes pendientes</span>
    </div>
    <div class="emp-stat-card">
        <i class="fas fa-business-time emp-stat-icon" style="color:var(--clr-primary)"></i>
        <span class="emp-stat-value"><?php echo count($data['upcomingSchedule'] ?? []); ?></span>
        <span class="emp-stat-label">Turnos próximos</span>
    </div>
    <?php if ($showCpHome || $showOvertimeHome): ?>
    <div class="emp-stat-card">
        <?php if ($showCpHome): ?>
        <i class="fas fa-clipboard-list emp-stat-icon" style="color:#2563eb"></i>
        <span class="emp-stat-value"><?php echo $cpPendingCount; ?></span>
        <span class="emp-stat-label">Extras pendientes</span>
        <?php else: ?>
        <i class="fas fa-history emp-stat-icon" style="color:var(--clr-info)"></i>
        <span class="emp-stat-value"><?php echo $pendingOvertimeCount; ?></span>
        <span class="emp-stat-label">Horas recientes</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($vacationPending !== null && function_exists('vacation_format_days')): ?>
<div class="alert alert-light border py-2 small mb-3">
    <i class="fas fa-umbrella-beach text-success me-1"></i>
    Tenés <strong><?php echo vacation_format_days($vacationPending); ?></strong> días de vacaciones pendientes.
    <a href="<?php echo htmlspecialchars(vacation_planilla_employee_url()); ?>" target="_blank" rel="noopener">Imprimir planilla</a>
</div>
<?php endif; ?>

<?php if (!empty($data['recentUpdates'])): ?>
<div class="emp-section-title mt-3"><i class="fas fa-bell me-2" style="color:var(--clr-info)"></i>Actualizaciones recientes</div>
<div class="emp-card mb-3">
    <?php foreach ($data['recentUpdates'] as $upd):
        $stClass = $upd->status === 'Aprobado' ? 'success' : ($upd->status === 'Rechazado' ? 'danger' : 'secondary');
    ?>
    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
        <span><?php echo htmlspecialchars($upd->label); ?></span>
        <span class="badge bg-<?php echo $stClass; ?>"><?php echo htmlspecialchars($upd->status); ?></span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<a href="<?php echo URLROOT; ?>/employee/profile" class="emp-user-chip text-decoration-none text-body">
    <img src="<?php echo URLROOT; ?>/uploads/avatars/<?php echo htmlspecialchars($userPhoto); ?>"
         alt="<?php echo htmlspecialchars($primerNombre); ?>"
         class="emp-user-chip-avatar"
         onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
    <div class="emp-user-chip-fallback"><?php echo $userInitial; ?></div>
    <div>
        <p class="emp-user-chip-title">Tu perfil activo</p>
        <p class="emp-user-chip-name"><?php echo htmlspecialchars($data['user']->full_name ?? $primerNombre); ?></p>
        <p class="emp-user-chip-subtitle"><?php echo $data['pendingRequestsCount']; ?> solicitudes pendientes · <?php echo count($data['upcomingSchedule'] ?? []); ?> turnos próximos</p>
    </div>
</a>

<!-- ══ ACCIONES RÁPIDAS ══ -->
<div class="emp-section-title"><i class="fas fa-bolt me-2" style="color:var(--clr-primary)"></i>Acceso rápido</div>
<div class="emp-quicklinks<?php echo $usesCpTasks ? ' emp-quicklinks--cp-first' : ''; ?>">
    <?php if (function_exists('employee_portal_show_cp_extras_nav') && employee_portal_show_cp_extras_nav()): ?>
    <a href="<?php echo URLROOT; ?>/cpTask/index" class="emp-quicklink-card emp-quicklink-card--featured" style="--qlc:#2563eb">
        <i class="fas fa-clipboard-list"></i>
        <span>Cargar Extras</span>
    </a>
    <?php endif; ?>
    <a href="<?php echo URLROOT; ?>/employee/misHorarios" class="emp-quicklink-card" style="--qlc:#e91e8c">
        <i class="fas fa-calendar-alt"></i>
        <span>Mis Horarios</span>
    </a>
    <a href="<?php echo URLROOT; ?>/request/index" class="emp-quicklink-card" style="--qlc:#06b6d4">
        <i class="fas fa-file-alt"></i>
        <span>Solicitudes</span>
    </a>
    <?php if (function_exists('employee_portal_show_overtime_nav') && employee_portal_show_overtime_nav()): ?>
    <a href="<?php echo URLROOT; ?>/employee/dashboard" class="emp-quicklink-card" style="--qlc:#f59e0b">
        <i class="fas fa-plus-circle"></i>
        <span>Horas Extras</span>
    </a>
    <?php endif; ?>
    <?php if (!empty($data['prode_open']) && employee_portal_can('prode')): ?>
    <a href="<?php echo URLROOT; ?>/prode/index" class="emp-quicklink-card" style="--qlc:#0d6efd">
        <i class="fas fa-futbol"></i>
        <span>PRODE</span>
    </a>
    <?php endif; ?>
    <?php if (employee_portal_can('suggestions')): ?>
    <a href="<?php echo URLROOT; ?>/suggestion/index" class="emp-quicklink-card" style="--qlc:#7c3aed">
        <i class="fas fa-lightbulb"></i>
        <span>Sugerencias</span>
    </a>
    <?php endif; ?>
    <a href="<?php echo URLROOT; ?>/employee/profile" class="emp-quicklink-card" style="--qlc:#64748b">
        <i class="fas fa-user"></i>
        <span>Mi perfil</span>
    </a>
</div>

<!-- ══ PRÓXIMOS TURNOS ══ -->
<div class="emp-section-title"><i class="fas fa-business-time me-2" style="color:var(--clr-primary)"></i>Próximos turnos</div>
<div class="emp-card">
    <?php if(empty($data['upcomingSchedule'])): ?>
    <div class="emp-empty">
        <i class="fas fa-calendar-xmark"></i>
        <p>No hay turnos programados esta semana</p>
    </div>
    <?php else: ?>
    <?php
    $shownDates = [];
    foreach($data['upcomingSchedule'] as $s):
        $dateKey = $s->schedule_date;
        if(!isset($shownDates[$dateKey])) $shownDates[$dateKey] = true;
    ?>
    <div class="emp-shift-item" style="border-left:4px solid <?php echo htmlspecialchars($s->color ?? '#e91e8c'); ?>">
        <div class="emp-shift-date">
            <?php
            $dn2 = date('l', strtotime($dateKey));
            $mn2 = date('F', strtotime($dateKey));
            echo ($diasES[$dn2] ?? $dn2) . ' ' . date('j', strtotime($dateKey)) . ' ' . ($mesesES[$mn2] ?? $mn2);
            ?>
        </div>
        <div class="emp-shift-info">
            <span class="emp-shift-name"><?php echo htmlspecialchars(employee_schedule_entry_label($s)); ?></span>
            <?php if($s->start_time && $s->end_time): ?>
            <span class="emp-shift-hours">
                <i class="fas fa-clock me-1"></i><?php echo substr($s->start_time,0,5); ?> – <?php echo substr($s->end_time,0,5); ?>
            </span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ══ HORAS EXTRAS RECIENTES ══ -->
<?php if($showOvertimeHome && !empty($data['recentOvertime'])): ?>
<div class="emp-section-title"><i class="fas fa-history me-2" style="color:var(--clr-primary)"></i>Horas extras recientes</div>
<div class="emp-card">
    <?php foreach($data['recentOvertime'] as $ot): ?>
    <div class="emp-ot-item">
        <div class="emp-ot-date"><?php echo date('d/m/Y', strtotime($ot->entry_date)); ?></div>
        <div class="emp-ot-detail">
            <span class="emp-ot-time"><?php echo date('H:i', strtotime($ot->start_time)) . ' – ' . date('H:i', strtotime($ot->end_time)); ?></span>
            <span class="emp-ot-reason"><?php echo htmlspecialchars($ot->reason); ?></span>
        </div>
        <div class="emp-ot-hours">
            <span><?php echo number_format($ot->hours_50 + $ot->hours_100, 1); ?>h</span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Espacio inferior para bottom nav en mobile -->
<div class="emp-page-bottom-spacer d-lg-none" aria-hidden="true"></div>

<?php require APPROOT . '/views/inc/footer.php'; ?>
