<?php
$selected = $selected ?? ['target_all' => false, 'company_ids' => [], 'area_ids' => [], 'employee_groups' => [], 'user_ids' => []];
$targetingJson = notification_targeting_json([
    'companies' => $companies,
    'areas' => $areas,
    'users' => $users,
    'picker_users' => $picker_users ?? $users,
]);
?>
<div class="card shadow-sm mb-4 notif-target-card">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span>Destinatarios</span>
        <span class="badge bg-primary" id="recipientCountBadge">0 seleccionados</span>
    </div>
    <div class="card-body">
        <div class="form-check mb-3">
            <input type="checkbox" name="target_all" value="1" class="form-check-input" id="targetAll"
                <?php echo !empty($selected['target_all']) ? 'checked' : ''; ?>>
            <label class="form-check-label fw-semibold" for="targetAll">Todas las empresas (todos los empleados activos)</label>
        </div>

        <div id="targetDetails" class="<?php echo !empty($selected['target_all']) ? 'opacity-50' : ''; ?>">
            <p class="small text-muted mb-2">Filtrá por empresa y/o área, actualizá la vista previa y destildá quién no debe recibir el mensaje.</p>
            <p class="small text-muted mb-2"><strong>Área global</strong> (ej. Sistemas · Todas las empresas): incluye empleados de <em>cualquier</em> empresa que tengan esa área en su perfil. La empresa del empleado y la del área no tienen que coincidir.</p>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Empresas</label>
                    <div class="notif-filter-box">
                        <?php foreach ($companies as $co): ?>
                        <div class="form-check">
                            <input type="checkbox" name="company_ids[]" value="<?php echo (int)$co->id; ?>"
                                class="form-check-input notif-filter-company" id="co<?php echo (int)$co->id; ?>"
                                <?php echo in_array((int)$co->id, $selected['company_ids'], true) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="co<?php echo (int)$co->id; ?>"><?php echo htmlspecialchars($co->name); ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Áreas</label>
                    <div class="notif-filter-box">
                        <?php foreach ($areas as $ar): ?>
                        <div class="form-check">
                            <input type="checkbox" name="area_ids[]" value="<?php echo (int)$ar->id; ?>"
                                class="form-check-input notif-filter-area" id="ar<?php echo (int)$ar->id; ?>"
                                <?php echo in_array((int)$ar->id, $selected['area_ids'], true) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ar<?php echo (int)$ar->id; ?>">
                                <?php echo htmlspecialchars($ar->name); ?>
                                <span class="text-muted small">(<?php echo htmlspecialchars(Area::scopeLabel($ar)); ?>)</span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Grupo organizacional</label>
                <div class="notif-filter-box">
                    <?php foreach (User::organizationGroupOptions() as $groupKey => $groupLabel): ?>
                    <div class="form-check">
                        <input type="checkbox" name="employee_groups[]" value="<?php echo htmlspecialchars($groupKey); ?>"
                            class="form-check-input notif-filter-group" id="group<?php echo htmlspecialchars($groupKey); ?>"
                            <?php echo in_array($groupKey, $selected['employee_groups'] ?? [], true) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="group<?php echo htmlspecialchars($groupKey); ?>"><?php echo htmlspecialchars($groupLabel); ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary mb-3" id="btnUpdateRecipients">
                <i class="fas fa-sync-alt me-1"></i>Actualizar vista previa
            </button>
        </div>

        <div class="notif-recipient-preview">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <input type="search" class="form-control form-control-sm" id="recipientSearch" placeholder="Buscar en la lista…" style="max-width:220px">
                <button type="button" class="btn btn-sm btn-link" id="btnSelectAllRecipients">Todos</button>
                <button type="button" class="btn btn-sm btn-link" id="btnSelectNoneRecipients">Ninguno</button>
            </div>
            <div id="recipientPreviewEmpty" class="text-muted small py-3 text-center border rounded">
                Marcá filtros y pulsá «Actualizar vista previa», o agregá empleados manualmente.
            </div>
            <div id="recipientPreviewList" class="notif-recipient-list border rounded"></div>
            <div class="mt-3">
                <label class="form-label small fw-semibold">Agregar persona manualmente</label>
                <p class="small text-muted mb-1">Todos los empleados activos (cualquier empresa). Incluye admins para pruebas.</p>
                <input type="search" class="form-control form-control-sm mb-2" id="addRecipientSearch" placeholder="Buscar por nombre, empresa o área…">
                <div class="input-group input-group-sm">
                    <select class="form-select" id="addRecipientSelect">
                        <option value="">Elegir…</option>
                    </select>
                    <button type="button" class="btn btn-outline-secondary" id="btnAddRecipient">Agregar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.NOTIF_TARGETING = <?php echo json_script_safe($targetingJson); ?>;
</script>
