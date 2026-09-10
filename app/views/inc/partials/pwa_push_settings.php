<?php
if (!function_exists('pwa_push_ready') || !pwa_push_ready()) {
    return;
}
$pushCount = 0;
if (function_exists('push_subscriptions_ready') && push_subscriptions_ready()) {
    $pushCount = (new PushSubscription())->countByUserId((int)$_SESSION['user_id']);
}
?>
<div class="card border-0 shadow-sm mb-4" id="pwaPushSettingsCard">
    <div class="card-body">
        <h2 class="h6 mb-2"><i class="fas fa-bell me-2 text-primary"></i>Notificaciones en el celular</h2>
        <p class="small text-muted mb-3">Recibí avisos al instante cuando RR. HH. publique novedades, recibos o comunicados.</p>
        <p class="mb-3 small" id="pwaPushStatusText">
            <?php if ($pushCount > 0): ?>
            <span class="text-success"><i class="fas fa-check-circle me-1"></i>Activadas en este dispositivo o en otro equipo.</span>
            <?php else: ?>
            <span class="text-secondary">Todavía no activaste las notificaciones push.</span>
            <?php endif; ?>
        </p>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-primary" id="pwaPushSettingsEnable">Activar notificaciones</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="pwaPushSettingsDisable">Desactivar en este dispositivo</button>
        </div>
        <p class="small text-muted mt-3 mb-0">
            En iPhone: agregá el sitio a la pantalla de inicio antes de activar las notificaciones.
        </p>
    </div>
</div>
