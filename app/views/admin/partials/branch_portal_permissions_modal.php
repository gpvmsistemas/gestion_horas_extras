<?php
if (empty($policyReady) || empty($policyBranch)) return;
$branchPolicyRows = $policyAccess->getPolicies((int)$company->id, (int)$policyBranch->id);
$modalId = 'branchPermissions' . (int)$policyBranch->id;
?>
<div class="modal fade" id="<?php echo $modalId; ?>" tabindex="-1" aria-labelledby="<?php echo $modalId; ?>Label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><div><h5 class="modal-title" id="<?php echo $modalId; ?>Label"><?php echo htmlspecialchars($policyBranch->name); ?></h5><p class="small text-muted mb-0">Permisos de esta sucursal. Heredar toma la política general de <?php echo htmlspecialchars($company->name); ?>.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <form method="post" action="<?php echo URLROOT; ?>/access/savePolicies">
                    <?php echo csrf_field(); ?><input type="hidden" name="company_id" value="<?php echo (int)$company->id; ?>"><input type="hidden" name="branch_id" value="<?php echo (int)$policyBranch->id; ?>">
                    <div class="row g-3">
                        <?php foreach ($featureLabels as $key => $label): $value = $branchPolicyRows[$key] ?? ''; ?>
                        <div class="col-md-6"><label class="small form-label mb-1"><?php echo htmlspecialchars($label); ?></label><select class="form-select policy-state-select" name="features[<?php echo htmlspecialchars($key); ?>]"><option value="" <?php echo $value===''?'selected':''; ?>>Heredar</option><option value="allow" <?php echo $value==='allow'?'selected':''; ?>>Sí</option><option value="deny" <?php echo $value==='deny'?'selected':''; ?>>No</option></select></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="modal-footer px-0 pb-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar permisos</button></div>
                </form>
                <form method="post" action="<?php echo URLROOT; ?>/access/copyPolicies" class="border-top mt-4 pt-3 d-flex flex-wrap align-items-end gap-2">
                    <?php echo csrf_field(); ?><input type="hidden" name="company_id" value="<?php echo (int)$company->id; ?>"><input type="hidden" name="branch_id" value="<?php echo (int)$policyBranch->id; ?>">
                    <div class="flex-grow-1"><label class="small form-label mb-1">Copiar permisos desde</label><select class="form-select" name="policy_template_source"><option value="">Elegí una empresa o sucursal</option><?php foreach ($policyTemplates as $template): ?><option value="<?php echo (int)$template[0]; ?>:<?php echo (int)$template[1]; ?>"><?php echo htmlspecialchars($template[2]); ?></option><?php endforeach; ?></select></div>
                    <button class="btn btn-outline-secondary" type="submit">Copiar configuración</button>
                </form>
            </div>
        </div>
    </div>
</div>
