<?php
if (!function_exists('pwa_install_ui_enabled') || !pwa_install_ui_enabled()) {
    return;
}
?>
<div class="card border-0 shadow-sm mb-4" id="pwaInstallSettingsCard">
    <div class="card-body">
        <h2 class="h6 mb-2"><i class="fas fa-download me-2 text-primary"></i>App en el celular</h2>
        <p class="small text-muted mb-3">Instalá RRHH en la pantalla de inicio para abrirla como una aplicación, más rápido y con acceso directo.</p>
        <p class="mb-3 small d-none text-success" id="pwaInstallActiveText">
            <i class="fas fa-check-circle me-1"></i>La app ya está instalada en este dispositivo.
        </p>
        <button type="button" class="btn btn-sm btn-primary" id="pwaInstallSettingsBtn">
            <i class="fas fa-plus-square me-1"></i>Instalar app
        </button>
    </div>
</div>
