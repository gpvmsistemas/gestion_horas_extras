<?php
$policyAccess = new AccessControl();
if (!$policyAccess->isReady() || !access_can_manage_company((int)$company->id)) return;
$featureLabels = AccessControl::portalFeatures();
$policyScope = (object)['id'=>0,'name'=>'Empresa completa'];
$policyTemplates = [];
$policyCompanyModel = new Company();
foreach ($policyCompanyModel->getAllCompanies() as $templateCompany) {
    $policyTemplates[] = [(int)$templateCompany->id, 0, $templateCompany->name . ' (política general)'];
    foreach ($policyCompanyModel->getBranches((int)$templateCompany->id, false) as $templateBranch) {
        $policyTemplates[] = [(int)$templateCompany->id, (int)$templateBranch->id, $templateCompany->name . ' · ' . $templateBranch->name];
    }
}
?>
<section class="edit-company-section company-policy-section">
    <h6 class="mb-1">Permisos generales de la empresa</h6>
    <p class="small text-muted">Esta es la política base. Cada sucursal puede heredarla o definir sus propios permisos desde su ficha.</p>
    <?php $branchId = 0; $policyRows = $policyAccess->getPolicies((int)$company->id, null); ?>
    <form method="post" action="<?php echo URLROOT; ?>/access/savePolicies" class="border-top pt-3 mt-3">
        <?php echo csrf_field(); ?><input type="hidden" name="company_id" value="<?php echo (int)$company->id; ?>"><input type="hidden" name="branch_id" value="<?php echo $branchId; ?>">
        <div class="d-flex justify-content-between align-items-center mb-2"><strong><?php echo htmlspecialchars($policyScope->name); ?></strong><button class="btn btn-sm btn-outline-primary">Guardar</button></div>
        <div class="row g-2">
            <?php foreach ($featureLabels as $key => $label): $value = $policyRows[$key] ?? ''; ?>
            <div class="col-md-6"><label class="small form-label mb-1"><?php echo htmlspecialchars($label); ?></label><select class="form-select form-select-sm policy-state-select" name="features[<?php echo htmlspecialchars($key); ?>]"><option value="" <?php echo $value===''?'selected':''; ?>>Heredar</option><option value="allow" <?php echo $value==='allow'?'selected':''; ?>>Sí</option><option value="deny" <?php echo $value==='deny'?'selected':''; ?>>No</option></select></div>
            <?php endforeach; ?>
        </div>
    </form>
    <form method="post" action="<?php echo URLROOT; ?>/access/copyPolicies" class="d-flex flex-wrap align-items-end gap-2 mt-2">
        <?php echo csrf_field(); ?><input type="hidden" name="company_id" value="<?php echo (int)$company->id; ?>"><input type="hidden" name="branch_id" value="<?php echo $branchId; ?>">
        <div><label class="small form-label mb-1">Copiar permisos desde</label><select class="form-select form-select-sm" name="policy_template_source"><option value="">Elegí una empresa o sucursal</option><?php foreach ($policyTemplates as $template): ?><option value="<?php echo (int)$template[0]; ?>:<?php echo (int)$template[1]; ?>"><?php echo htmlspecialchars($template[2]); ?></option><?php endforeach; ?></select></div>
        <button class="btn btn-sm btn-outline-secondary" type="submit">Copiar configuración</button>
    </form>
</section>
<script>(()=>{document.querySelectorAll('.policy-state-select').forEach(select=>{const sync=()=>{select.classList.toggle('is-yes',select.value==='allow');select.classList.toggle('is-no',select.value==='deny');};select.addEventListener('change',sync);sync();});})();</script>
