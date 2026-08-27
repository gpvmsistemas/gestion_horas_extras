<?php require APPROOT . '/views/inc/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0"><i class="fas fa-bell me-2"></i>Mis notificaciones</h1>
    <?php
    $hasUnread = false;
    foreach ($data['items'] as $n) {
        if (empty($n->read_at)) { $hasUnread = true; break; }
    }
    if ($hasUnread):
    ?>
    <form method="post" action="<?php echo URLROOT; ?>/employee/markAllNotificationsRead">
        <?php echo csrf_field(); ?>
        <button type="submit" class="btn btn-sm btn-outline-secondary">Marcar todas leídas</button>
    </form>
    <?php endif; ?>
</div>

<div class="list-group shadow-sm">
    <?php foreach ($data['items'] as $n):
        $href = function_exists('employee_portal_safe_link_url')
            ? employee_portal_safe_link_url($n->link_url ?? '', '#')
            : '#';
    ?>
    <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"
       class="list-group-item list-group-item-action emp-notif-item<?php echo empty($n->read_at) ? ' list-group-item-primary' : ''; ?>"
       data-notif-id="<?php echo (int)$n->id; ?>">
        <div class="d-flex w-100 justify-content-between">
            <h2 class="h6 mb-1"><?php echo htmlspecialchars($n->title); ?></h2>
            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($n->created_at)); ?></small>
        </div>
        <?php if (!empty($n->body)): ?>
        <p class="mb-1 small text-muted"><?php echo htmlspecialchars(strip_tags($n->body)); ?></p>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
    <?php if (empty($data['items'])): ?>
    <div class="list-group-item text-muted text-center py-4">No tenés notificaciones.</div>
    <?php endif; ?>
</div>

<script src="<?php echo URLROOT; ?>/js/employee-notifications.js"></script>
<?php require APPROOT . '/views/inc/footer.php'; ?>
