<?php
/**
 * Banner opt-in para activar notificaciones push (portal empleado).
 */
if (!function_exists('pwa_push_ready') || !pwa_push_ready()) {
    return;
}
?>
<div id="pwaPushBanner" class="alert alert-info border-0 shadow-sm mb-3 mx-3 mx-lg-4 mt-3 d-none" role="region" aria-label="Activar notificaciones">
    <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3">
        <div class="flex-grow-1">
            <strong><i class="fas fa-bell me-2"></i>Recibí avisos al instante</strong>
            <p class="mb-0 small mt-1">Activá las notificaciones para enterarte cuando RR. HH. publique novedades, recibos o comunicados.</p>
        </div>
        <div class="d-flex gap-2 flex-shrink-0">
            <button type="button" class="btn btn-sm btn-primary" id="pwaPushEnableBtn">Activar</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="pwaPushDismissBtn">Ahora no</button>
        </div>
    </div>
</div>
