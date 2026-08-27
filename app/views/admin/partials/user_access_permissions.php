<?php
$accessEditor = new AccessControl(); $accessUser = $data['user'] ?? null;
if (!$accessUser || !$accessEditor->isReady()) return;
$actorRole = access_current_role(); $canEditScopes = in_array($actorRole, ['administrador','rrhh'], true);
$scopes = $accessEditor->getScopesForUser((int)$accessUser->id); $companies = (new Company())->getAllCompanies();
$scopeCompany = (int)($accessUser->company_id ?? 0); $scopeBranch = (int)($accessUser->branch_id ?? 0);
$overrides = $accessEditor->getUserOverrides((int)$accessUser->id, $scopeCompany, $scopeBranch ?: null);
?>
<section class="edit-user-section" id="permisos">
 <div class="edit-user-section-heading"><span class="edit-user-section-icon"><i class="fas fa-user-shield"></i></span><div><span>Acceso</span><h2>Perfiles y permisos</h2><p>Alcance por empresa/sucursal y excepciones del portal.</p></div></div>
 <div class="edit-user-section-body">
 <?php if ($canEditScopes): ?>
 <form method="post" action="<?php echo URLROOT; ?>/access/saveUserScopes/<?php echo (int)$accessUser->id; ?>">
  <?php echo csrf_field(); ?><h6>Asignaciones de acceso</h6><p class="small text-muted">Una persona puede tener roles diferentes según la empresa o sucursal.</p>
  <div id="accessScopeRows">
  <?php foreach ($scopes as $i => $scope): ?>
   <div class="row g-2 border rounded p-2 mb-2"><div class="col-md-3"><select class="form-select form-select-sm" name="scopes[<?php echo $i; ?>][company_id]" required><?php foreach($companies as $co): ?><option value="<?php echo (int)$co->id; ?>" <?php echo (int)$scope->company_id===(int)$co->id?'selected':''; ?>><?php echo htmlspecialchars($co->name); ?></option><?php endforeach; ?></select></div><div class="col-md-2"><input class="form-control form-control-sm" name="scopes[<?php echo $i; ?>][branch_id]" value="<?php echo (int)$scope->branch_id; ?>" placeholder="ID sucursal"></div><div class="col-md-3"><select class="form-select form-select-sm" name="scopes[<?php echo $i; ?>][access_role]"><?php foreach(AccessControl::roles() as $key=>$label): ?><option value="<?php echo $key; ?>" <?php echo $scope->access_role===$key?'selected':''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div><div class="col-md-2"><label class="small"><input type="checkbox" name="scopes[<?php echo $i; ?>][is_primary]" value="1" <?php echo $scope->is_primary?'checked':''; ?>> Principal</label><label class="small ms-2"><input type="checkbox" name="scopes[<?php echo $i; ?>][is_active]" value="1" <?php echo $scope->is_active?'checked':''; ?>> Activa</label></div><div class="col-md-2"><input type="date" class="form-control form-control-sm" name="scopes[<?php echo $i; ?>][starts_on]" value="<?php echo htmlspecialchars($scope->starts_on ?? ''); ?>"></div></div>
  <?php endforeach; ?>
  </div><button class="btn btn-sm btn-primary">Guardar perfiles</button>
 </form>
 <?php foreach($scopes as $scope): $caps=$accessEditor->getScopeCapabilities((int)$scope->id); ?>
 <form method="post" action="<?php echo URLROOT; ?>/access/saveScopeCapabilities/<?php echo (int)$scope->id; ?>" class="border-top mt-4 pt-3">
  <?php echo csrf_field(); ?><h6>Capacidades · <?php echo htmlspecialchars($scope->company_name . ($scope->branch_name?' / '.$scope->branch_name:'')); ?></h6><p class="small text-muted">“Heredar” usa el perfil. Las capacidades patrimoniales de EPP y activos deben concederse expresamente.</p>
  <div class="row g-2"><?php foreach(AccessControl::capabilityLabels() as $key=>$label):$v=$caps[$key]??'';?><div class="col-md-6"><label class="small form-label mb-1"><?php echo htmlspecialchars($label);?></label><select class="form-select form-select-sm" name="capabilities[<?php echo htmlspecialchars($key);?>]"><option value="" <?php echo $v===''?'selected':'';?>>Heredar perfil</option><option value="allow" <?php echo $v==='allow'?'selected':'';?>>Permitir</option><option value="deny" <?php echo $v==='deny'?'selected':'';?>>Denegar</option></select></div><?php endforeach;?></div><button class="btn btn-sm btn-outline-primary mt-3">Guardar capacidades</button>
 </form><?php endforeach; ?>
 <?php endif; ?>
 <?php if (access_can_manage_company($scopeCompany, $scopeBranch)): ?>
 <form method="post" action="<?php echo URLROOT; ?>/access/saveUserOverrides/<?php echo (int)$accessUser->id; ?>" class="border-top mt-4 pt-3">
  <?php echo csrf_field(); ?><input type="hidden" name="company_id" value="<?php echo $scopeCompany; ?>"><input type="hidden" name="branch_id" value="<?php echo $scopeBranch; ?>"><h6>Excepciones individuales del portal</h6><div class="row g-2"><?php foreach(AccessControl::portalFeatures() as $key=>$label): $v=$overrides[$key]??''; ?><div class="col-md-6"><label class="small form-label mb-1"><?php echo htmlspecialchars($label); ?></label><select class="form-select form-select-sm" name="features[<?php echo $key; ?>]"><option value="" <?php echo $v===''?'selected':''; ?>>Heredar</option><option value="allow" <?php echo $v==='allow'?'selected':''; ?>>Sí</option><option value="deny" <?php echo $v==='deny'?'selected':''; ?>>No</option></select></div><?php endforeach; ?></div><button class="btn btn-sm btn-outline-primary mt-3">Guardar excepciones</button>
 </form><?php endif; ?>
 </div>
</section>
