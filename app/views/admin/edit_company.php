<?php require APPROOT . '/views/inc/header.php';

$company = $data['company'];
$usesCp = !empty($data['uses_cp_tasks']);
$showCol = !empty($data['show_overtime_column']);
$showOt = (int)($company->show_overtime ?? 1) === 1;
$showCpCol = !empty($data['show_cp_extras_column']);
$showCp = (int)($company->show_cp_extras ?? 1) === 1;
$location = $data['location'] ?? null;
$branding = $data['branding'] ?? null;
$locationReady = !empty($data['location_ready']);
$branches = $data['branches'] ?? [];
$branchesReady = !empty($data['branches_ready']);
$policyAccess = new AccessControl();
$policyReady = $policyAccess->isReady() && access_can_manage_company((int)$company->id);
$featureLabels = AccessControl::portalFeatures();
$policyTemplateOptions = [];
$policyTemplates = [];
if ($branchesReady && $policyReady) {
    $policyCompanyModel = new Company();
    foreach ($policyCompanyModel->getAllCompanies() as $policyCompany) {
        $policyTemplateOptions[] = [(int)$policyCompany->id . ':0', $policyCompany->name . ' (política general)'];
        $policyTemplates[] = [(int)$policyCompany->id, 0, $policyCompany->name . ' (política general)'];
        foreach ($policyCompanyModel->getBranches((int)$policyCompany->id, false) as $policyBranch) {
            $policyTemplateOptions[] = [(int)$policyCompany->id . ':' . (int)$policyBranch->id, $policyCompany->name . ' · ' . $policyBranch->name];
            $policyTemplates[] = [(int)$policyCompany->id, (int)$policyBranch->id, $policyCompany->name . ' · ' . $policyBranch->name];
        }
    }
}
$savedLocalities = array_map(function ($branch) { return $branch->locality ?? ''; }, $branches);
$localityOptions = array_values(array_filter(array_unique(array_merge(['Villa María', 'Villa Nueva', 'San Francisco'], $savedLocalities, [$location->locality ?? '']))));
$provinceOptions = ['Córdoba'];

function company_location_options($options, $selected, $placeholder) {
    echo '<option value="">' . htmlspecialchars($placeholder) . '</option>';
    foreach ($options as $option) {
        echo '<option value="' . htmlspecialchars($option) . '"' . ((string)$selected === (string)$option ? ' selected' : '') . '>'
            . htmlspecialchars($option) . '</option>';
    }
}
?>

<div class="edit-company-page">
    <header class="edit-company-hero">
        <div class="edit-company-hero-main">
            <a href="<?php echo URLROOT; ?>/admin/companies" class="edit-company-back" aria-label="Volver a empresas"><i class="fas fa-arrow-left"></i></a>
            <div>
                <div class="edit-company-eyebrow">Configuración organizacional · ID <?php echo (int)$company->id; ?></div>
                <h1><?php echo htmlspecialchars($company->name); ?></h1>
                <p>Identidad, sedes operativas y módulos visibles para esta empresa.</p>
            </div>
        </div>
        <span class="edit-company-branch-count"><i class="fas fa-store"></i><strong><?php echo count($branches); ?></strong> <?php echo count($branches) === 1 ? 'sucursal' : 'sucursales'; ?></span>
    </header>

    <form method="post" enctype="multipart/form-data" action="<?php echo URLROOT; ?>/admin/editCompany/<?php echo (int)$company->id; ?>" class="edit-company-form">
                    <?php echo csrf_field(); ?>

                    <section class="edit-company-section edit-company-identity">
                        <div class="edit-company-section-head"><span><i class="fas fa-building"></i></span><div><small>Identidad</small><h2>Información de la empresa</h2><p>Nombre con el que se identifica en todo el sistema.</p></div></div>
                        <div class="edit-company-section-body">
                        <label class="form-label" for="company_name">Nombre</label>
                        <input type="text" name="company_name" id="company_name" class="form-control" required value="<?php echo htmlspecialchars($company->name); ?>">
                        <?php if ($branding !== null): ?>
                        <div class="company-brand-editor mt-4">
                            <div class="company-brand-preview" style="--company-brand-preview:<?php echo htmlspecialchars($branding->brand_color ?? '#E91E8C'); ?>"><img src="<?php echo htmlspecialchars(company_brand_logo_url((int)$company->id)); ?>" alt="Logo actual de <?php echo htmlspecialchars($company->name); ?>"></div>
                            <div class="company-brand-fields"><div><label class="form-label" for="brand_color">Color principal</label><input type="color" class="form-control form-control-color" id="brand_color" name="brand_color" value="<?php echo htmlspecialchars($branding->brand_color ?? '#E91E8C'); ?>" title="Color principal de la empresa"></div><div><label class="form-label" for="company_logo">Logo de la empresa</label><input type="file" class="form-control" id="company_logo" name="company_logo" accept="image/png,image/jpeg,image/gif,image/webp"><div class="form-text">PNG, JPG, GIF o WEBP · máximo 2 MB.</div></div></div>
                        </div>
                        <?php endif; ?>
                        </div>
                    </section>

                    <?php if ($locationReady): ?>
                    <section class="edit-company-section">
                        <div class="edit-company-section-head"><span><i class="fas fa-map-marker-alt"></i></span><div><small>Localización</small><h2>Ubicación principal</h2><p>Referencia general para reglas geográficas sin sucursal específica.</p></div></div>
                        <div class="edit-company-section-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="locality">Localidad / ciudad</label>
                                <select name="locality" id="locality" class="form-select">
                                    <?php company_location_options($localityOptions, $location->locality ?? '', 'Seleccioná una localidad'); ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="province">Provincia</label>
                                <select name="province" id="province" class="form-select">
                                    <?php company_location_options($provinceOptions, $location->province ?? '', 'Seleccioná una provincia'); ?>
                                </select>
                            </div>
                        </div>
                        </div>
                    </section>
                    <?php endif; ?>

                    <?php if ($branchesReady): ?>
                    <section class="edit-company-section">
                        <div class="edit-company-section-head edit-company-section-head-action"><span><i class="fas fa-store-alt"></i></span><div><small>Operación</small><h2>Sucursales y localidades</h2><p>Las sedes conservan su historial aunque sean desactivadas.</p></div>
                            <button type="button" class="btn btn-primary flex-shrink-0" id="addBranch"><i class="fas fa-plus me-2"></i>Agregar sucursal</button>
                        </div>
                        <div class="edit-company-section-body"><div id="branchesList" class="vstack gap-3">
                            <?php foreach ($branches as $index => $branch): ?>
                            <div class="branch-row">
                                <input type="hidden" name="branches[<?php echo (int)$index; ?>][id]" value="<?php echo (int)$branch->id; ?>">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-4"><label class="form-label small">Sucursal</label><input type="text" class="form-control" name="branches[<?php echo (int)$index; ?>][name]" value="<?php echo htmlspecialchars($branch->name); ?>" placeholder="Ej. Ecofarma Central"></div>
                                    <div class="col-md-3"><label class="form-label small">Localidad</label><select class="form-select" name="branches[<?php echo (int)$index; ?>][locality]"><?php company_location_options($localityOptions, $branch->locality, 'Seleccioná'); ?></select></div>
                                    <div class="col-md-3"><label class="form-label small">Provincia</label><select class="form-select" name="branches[<?php echo (int)$index; ?>][province]"><?php company_location_options($provinceOptions, $branch->province, 'Seleccioná'); ?></select></div>
                                    <div class="col-md-2 d-flex gap-2 align-items-center pb-1"><label class="branch-active-control"><input class="form-check-input branch-active-input" type="checkbox" name="branches[<?php echo (int)$index; ?>][is_active]" value="1" <?php echo !empty($branch->is_active) ? 'checked' : ''; ?>><span class="branch-state-label"></span></label><button type="button" class="btn btn-sm btn-outline-danger remove-branch" aria-label="Quitar sucursal"><i class="fas fa-trash-alt"></i></button></div>
                                </div>
                                <?php if ($policyReady): ?><div class="d-flex justify-content-end mt-2"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#branchPermissions<?php echo (int)$branch->id; ?>">Configurar permisos de esta sucursal</button></div><?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <template id="branchTemplate"><div class="branch-row"><input type="hidden" name="branches[__INDEX__][id]" value="0"><div class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label small">Sucursal</label><input type="text" class="form-control" name="branches[__INDEX__][name]" placeholder="Ej. Ecofarma Central"></div><div class="col-md-2"><label class="form-label small">Localidad</label><select class="form-select" name="branches[__INDEX__][locality]"><?php company_location_options($localityOptions, '', 'Seleccioná'); ?></select></div><div class="col-md-2"><label class="form-label small">Provincia</label><select class="form-select" name="branches[__INDEX__][province]"><?php company_location_options($provinceOptions, 'Córdoba', 'Seleccioná'); ?></select></div><div class="col-md-3"><label class="form-label small">Permisos iniciales</label><select class="form-select" name="branches[__INDEX__][policy_template]"><option value="inherit">Heredar de esta empresa</option><?php foreach ($policyTemplateOptions as $policyTemplate): ?><option value="<?php echo htmlspecialchars($policyTemplate[0]); ?>">Copiar: <?php echo htmlspecialchars($policyTemplate[1]); ?></option><?php endforeach; ?></select></div><div class="col-md-2 d-flex gap-2 align-items-center pb-1"><label class="branch-active-control"><input class="form-check-input branch-active-input" type="checkbox" name="branches[__INDEX__][is_active]" value="1" checked><span class="branch-state-label"></span></label><button type="button" class="btn btn-sm btn-outline-danger remove-branch" aria-label="Quitar sucursal"><i class="fas fa-trash-alt"></i></button></div></div></div></template>
                        </div>
                    </section>
                    <?php endif; ?>

                    <?php if ($showCol): ?><section class="edit-company-section"><div class="edit-company-section-head"><span><i class="fas fa-toggle-on"></i></span><div><small>Visibilidad</small><h2>Módulos disponibles</h2><p>Definí qué herramientas aparecen para esta empresa.</p></div></div><div class="edit-company-section-body"><?php if ($usesCp): ?><p class="small text-muted mb-3">Esta empresa usa <strong>extras por tarea</strong> (Casa Paviotti). El módulo de horas extras 50%/100% no aplica.</p><?php if ($showCpCol): ?><label class="company-feature-toggle" for="show_cp_extras"><span><strong>Extras Casa Paviotti</strong><small>Visible en el portal y la administración.</small></span><input class="form-check-input company-feature-input" type="checkbox" name="show_cp_extras" id="show_cp_extras" value="1" <?php echo $showCp ? 'checked' : ''; ?>><b class="company-feature-state"></b></label><?php endif; ?><?php else: ?><label class="company-feature-toggle" for="show_overtime"><span><strong>Horas extras 50% / 100%</strong><small>Visible para empleados, supervisores y dashboard.</small></span><input class="form-check-input company-feature-input" type="checkbox" name="show_overtime" id="show_overtime" value="1" <?php echo $showOt ? 'checked' : ''; ?>><b class="company-feature-state"></b></label><?php endif; ?></div></section><?php endif; ?>
                    <div class="edit-company-actions"><a href="<?php echo URLROOT; ?>/admin/companies" class="btn btn-outline-secondary">Cancelar</a><button type="submit" class="btn btn-primary"><i class="fas fa-check me-2"></i>Guardar cambios</button></div>
    </form>
                <?php if ($policyReady && !empty($branches)): foreach ($branches as $policyBranch): require APPROOT . '/views/admin/partials/branch_portal_permissions_modal.php'; endforeach; endif; ?>
                <?php require APPROOT . '/views/admin/partials/company_portal_permissions.php'; ?>
</div>
<?php if ($branchesReady): ?><script>(() => { const list = document.getElementById('branchesList'); const template = document.getElementById('branchTemplate'); let nextIndex = <?php echo count($branches); ?>; const syncBranch = row => { const input=row.querySelector('.branch-active-input'), label=row.querySelector('.branch-state-label'); if(!input||!label)return; label.textContent=input.checked?'Sí':'No'; row.classList.toggle('is-inactive',!input.checked); }; const syncAll=()=>list.querySelectorAll('.branch-row').forEach(syncBranch); document.getElementById('addBranch').addEventListener('click', () => { list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', nextIndex++)); syncAll(); list.lastElementChild.scrollIntoView({behavior:'smooth',block:'center'}); }); list.addEventListener('change',event=>{if(event.target.matches('.branch-active-input'))syncBranch(event.target.closest('.branch-row'));}); list.addEventListener('click', (event) => { const button = event.target.closest('.remove-branch'); if (button) button.closest('.branch-row').remove(); }); syncAll(); })();</script><?php endif; ?>
<script>(()=>{document.querySelectorAll('.company-feature-input').forEach(input=>{const sync=()=>{const state=input.closest('.company-feature-toggle').querySelector('.company-feature-state');state.textContent=input.checked?'Sí':'No';state.classList.toggle('is-no',!input.checked);};input.addEventListener('change',sync);sync();});})();</script>
<?php require APPROOT . '/views/inc/footer.php'; ?>
