<div class="dropdown topbar-notify topbar-notify-employee">
    <button type="button"
            class="topbar-notify-btn dropdown-toggle<?php echo $_empUnreadNotifications > 0 ? ' has-alerts' : ''; ?>"
            id="empNotifyBtn"
            data-bs-toggle="dropdown"
            data-bs-auto-close="outside"
            aria-label="Mis notificaciones">
        <i class="fas fa-bell"></i>
        <?php if ($_empUnreadNotifications > 0): ?>
        <span class="topbar-notify-badge" id="empNotifyBadge"><?php echo $_empUnreadNotifications > 99 ? '99+' : (int)$_empUnreadNotifications; ?></span>
        <?php endif; ?>
    </button>
    <div class="dropdown-menu dropdown-menu-end topbar-notify-menu" aria-labelledby="empNotifyBtn">
        <div class="topbar-notify-menu-head">
            <strong>Mis notificaciones</strong>
            <?php if ($_empUnreadNotifications > 0): ?>
            <span class="badge bg-primary" id="empNotifyMenuCount"><?php echo (int)$_empUnreadNotifications; ?></span>
            <?php endif; ?>
        </div>
        <?php if (empty($_empNotificationsPreview)): ?>
        <div class="topbar-notify-empty">
            <i class="fas fa-check-circle text-success me-2"></i>Sin notificaciones
        </div>
        <?php else: ?>
        <div class="topbar-notify-list">
            <?php foreach ($_empNotificationsPreview as $n):
                $nHref = function_exists('employee_portal_safe_link_url')
                    ? employee_portal_safe_link_url($n->link_url ?? '', URLROOT . '/employee/notifications')
                    : URLROOT . '/employee/notifications';
                $isUnread = empty($n->read_at);
            ?>
            <a href="<?php echo htmlspecialchars($nHref, ENT_QUOTES, 'UTF-8'); ?>"
               class="topbar-notify-item emp-notif-item<?php echo $isUnread ? ' is-unread' : ''; ?>"
               data-notif-id="<?php echo (int)$n->id; ?>">
                <span class="topbar-notify-item-body">
                    <span class="topbar-notify-item-name"><?php echo htmlspecialchars($n->title); ?></span>
                    <?php if (!empty($n->body)): ?>
                    <span class="topbar-notify-item-meta"><?php echo htmlspecialchars(mb_substr(strip_tags($n->body), 0, 60)); ?></span>
                    <?php endif; ?>
                </span>
                <?php if ($isUnread): ?><span class="topbar-notify-item-pill">Nuevo</span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="topbar-notify-footer d-grid gap-2">
            <a href="<?php echo URLROOT; ?>/employee/notifications" class="btn btn-sm btn-primary w-100">Ver todas</a>
            <?php if ($_empUnreadNotifications > 0): ?>
            <form method="post" action="<?php echo URLROOT; ?>/employee/markAllNotificationsRead">
                <?php echo csrf_field(); ?>
                <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Marcar todas leídas</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
