<?php
if (!function_exists('pwa_install_ui_enabled') || !pwa_install_ui_enabled()) {
    return;
}
?>
<div id="pwaInstallBanner" class="alert alert-secondary border-0 shadow-sm mb-3 pwa-install-banner" role="region" aria-label="Instalar aplicación">
    <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3">
        <div class="flex-grow-1">
            <strong><i class="fas fa-mobile-alt me-2"></i>Instalá la app en tu celular</strong>
            <p class="mb-0 small mt-1">Accedé más rápido desde la pantalla de inicio, como una aplicación nativa.</p>
        </div>
        <div class="d-flex gap-2 flex-shrink-0">
            <button type="button" class="btn btn-sm btn-primary" id="pwaInstallBtn">
                <i class="fas fa-download me-1"></i>Instalar app
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="pwaInstallDismissBtn">Cerrar</button>
        </div>
    </div>
</div>

<div id="pwaMobileInstallFab" class="pwa-mobile-install-fab d-lg-none" role="region" aria-label="Instalar aplicación">
    <button type="button" class="pwa-mobile-install-fab-btn" id="pwaMobileInstallBtn">
        <i class="fas fa-download me-2"></i>Instalar app
    </button>
</div>
