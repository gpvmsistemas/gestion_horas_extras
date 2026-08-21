<?php
require APPROOT . '/views/inc/header.php';
$devices = $data['devices'] ?? [];
$branches = $data['branches'] ?? [];
$activeDevices = count(array_filter($devices, static fn($device) => !empty($device->is_active)));
$assignedDevices = count(array_filter($devices, static fn($device) => trim((string)($device->scopes ?? '')) !== ''));
?>

<div class="admin-page-head clock-page-head">
    <div class="admin-page-brand">
        <div class="admin-page-icon"><i class="fas fa-clock"></i></div>
        <div class="admin-page-meta"><h1 class="page-title mb-0">Relojes y sucursales</h1><p class="page-subtitle mb-0">Administrá los dispositivos y definí dónde puede operar cada reloj.</p></div>
    </div>
    <div class="admin-page-actions">
        <a href="<?php echo URLROOT; ?>/admin/marcacionesTodas" class="btn btn-outline-primary"><i class="fas fa-fingerprint me-1"></i> Ver marcaciones</a>
        <a href="<?php echo URLROOT; ?>/admin/sync" class="btn btn-primary"><i class="fas fa-sync-alt me-1"></i> Sincronizar</a>
    </div>
</div>

<div class="admin-kpi-grid clock-kpi-grid">
    <div class="admin-kpi-card"><div class="admin-kpi-icon is-total"><i class="fas fa-server"></i></div><div><div class="admin-kpi-value"><?php echo count($devices); ?></div><div class="admin-kpi-label">Dispositivos</div></div></div>
    <div class="admin-kpi-card"><div class="admin-kpi-icon clock-kpi-active"><i class="fas fa-check-circle"></i></div><div><div class="admin-kpi-value"><?php echo $activeDevices; ?></div><div class="admin-kpi-label">Activos</div></div></div>
    <div class="admin-kpi-card"><div class="admin-kpi-icon clock-kpi-branches"><i class="fas fa-code-branch"></i></div><div><div class="admin-kpi-value"><?php echo $assignedDevices; ?></div><div class="admin-kpi-label">Con sucursales</div></div></div>
</div>

<div class="row g-4 clock-devices-layout">
    <div class="col-xl-8"><section class="admin-surface h-100">
        <div class="admin-surface-head"><h2 class="admin-surface-title mb-0"><i class="fas fa-list-ul"></i> Dispositivos registrados</h2><span class="badge bg-light text-dark border"><?php echo count($devices); ?> en total</span></div>
        <div class="admin-surface-body is-tight"><div class="table-responsive"><table class="table table-hover align-middle mb-0 admin-table clock-devices-table">
            <thead><tr><th>API / identificador</th><th>Nombre visible</th><th>Sucursales habilitadas</th><th>Estado</th></tr></thead><tbody>
            <?php foreach ($devices as $device): ?><tr>
                <td><code class="clock-device-code"><?php echo htmlspecialchars($device->external_name); ?></code></td>
                <td><strong><?php echo htmlspecialchars($device->display_name); ?></strong></td>
                <td><span class="clock-device-scope"><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($device->scopes ?: 'Sin asignar'); ?></span></td>
                <td><span class="status-pill <?php echo $device->is_active ? 'is-yes' : 'is-no'; ?>"><i class="fas <?php echo $device->is_active ? 'fa-check' : 'fa-times'; ?>"></i><?php echo $device->is_active ? 'Activo' : 'Inactivo'; ?></span></td>
            </tr><?php endforeach; ?>
            <?php if (empty($devices)): ?><tr><td colspan="4"><div class="admin-empty py-5"><i class="fas fa-clock"></i><p class="mb-1">Todavía no hay relojes registrados.</p><span class="small text-muted">Podés cargarlos desde el formulario o crearlos mediante una sincronización.</span></div></td></tr><?php endif; ?>
            </tbody></table></div></div>
    </section></div>
    <div class="col-xl-4"><section class="admin-surface clock-device-form-panel">
        <div class="admin-surface-head"><div><span class="admin-section-eyebrow">Configuración</span><h2 class="admin-surface-title mb-0"><i class="fas fa-plus-circle"></i> Agregar o actualizar</h2></div></div>
        <div class="admin-surface-body"><form method="post" action="<?php echo URLROOT; ?>/admin/saveClockDevice">
            <?php echo csrf_field(); ?>
            <div class="mb-3"><label class="form-label">Identificador recibido de la API</label><input required class="form-control" name="external_name" placeholder="Ej. ECOFARMA"><div class="form-text">Debe coincidir exactamente con el nombre informado por el reloj.</div></div>
            <div class="mb-3"><label class="form-label">Nombre visible</label><input class="form-control" name="display_name" placeholder="Ej. Reloj Ecofarma Central"></div>
            <div class="mb-3"><label class="form-label">Sucursales habilitadas</label><select name="branch_ids[]" class="form-select clock-branch-select" multiple size="8"><?php foreach ($branches as $branch): ?><option value="<?php echo (int)$branch->id; ?>"><?php echo htmlspecialchars(($branch->company_name ?? '') . ' — ' . $branch->name . ' (' . $branch->locality . ')'); ?></option><?php endforeach; ?></select><div class="form-text"><i class="fas fa-info-circle me-1"></i>Usá Ctrl/Cmd para seleccionar varias. Sin asignación no se adjudicará automáticamente a una empresa.</div></div>
            <div class="clock-active-switch"><div><strong>Dispositivo activo</strong><span>Permitir su uso en nuevas sincronizaciones</span></div><div class="form-check form-switch m-0"><input checked class="form-check-input" type="checkbox" name="is_active" id="clock-active"><label class="visually-hidden" for="clock-active">Activo</label></div></div>
            <button class="btn btn-primary w-100 mt-4" type="submit"><i class="fas fa-save me-1"></i> Guardar reloj</button>
        </form></div>
    </section></div>
</div>

<?php require APPROOT . '/views/inc/footer.php'; ?>
