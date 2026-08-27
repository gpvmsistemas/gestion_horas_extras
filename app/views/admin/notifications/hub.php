<?php require APPROOT . '/views/inc/header.php'; ?>
<link rel="stylesheet" href="<?php echo URLROOT; ?>/css/notifications.css">

<div class="page-header mb-4">
    <h1 class="h3 mb-1"><i class="fas fa-bell me-2 text-primary"></i>Notificaciones</h1>
    <p class="text-muted mb-0">Avisos modales, campana y recibos de sueldo. El correo SMTP se configura en <a href="<?php echo URLROOT; ?>/systemConfig?tab=mail">Configuración → Correo</a>.</p>
</div>

<div class="row g-3 notif-hub-grid">
    <div class="col-md-6 col-lg-3">
        <a href="<?php echo URLROOT; ?>/notificationsAdmin/announcements" class="notif-hub-card">
            <i class="fas fa-bullhorn"></i>
            <h2>Avisos</h2>
            <p>Modales al ingresar del empleado</p>
        </a>
    </div>
    <div class="col-md-6 col-lg-3">
        <a href="<?php echo URLROOT; ?>/notificationsAdmin/broadcasts" class="notif-hub-card">
            <i class="fas fa-bell"></i>
            <h2>Notificaciones</h2>
            <p>Campana en el navbar</p>
        </a>
    </div>
    <?php if (!(function_exists('org_hides_pay_stubs') && org_hides_pay_stubs())): ?>
    <div class="col-md-6 col-lg-3">
        <a href="<?php echo URLROOT; ?>/notificationsAdmin/payStubs" class="notif-hub-card">
            <i class="fas fa-file-invoice-dollar"></i>
            <h2>Recibos</h2>
            <p>Carga y firma digital</p>
        </a>
    </div>
    <?php endif; ?>
    <?php if (function_exists('surveys_is_ready') && surveys_is_ready()): ?>
    <div class="col-md-6 col-lg-3">
        <a href="<?php echo URLROOT; ?>/surveyAdmin/index" class="notif-hub-card">
            <i class="fas fa-poll"></i>
            <h2>Encuestas</h2>
            <p>Formularios anónimos o identificados</p>
        </a>
    </div>
    <?php endif; ?>
</div>

<?php require APPROOT . '/views/inc/footer.php'; ?>
