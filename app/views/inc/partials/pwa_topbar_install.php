<?php
if (!function_exists('pwa_install_ui_enabled') || !pwa_install_ui_enabled()) {
    return;
}
?>
<button type="button"
        class="topbar-install-btn"
        id="pwaTopbarInstallBtn"
        aria-label="Instalar app en el celular"
        title="Instalar app">
    <i class="fas fa-download"></i>
    <span class="topbar-install-label">App</span>
</button>
