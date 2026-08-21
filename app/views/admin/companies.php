<?php require APPROOT . '/views/inc/header.php';
$showOtCol = !empty($data['show_overtime_column']);
$showCpCol = !empty($data['show_cp_extras_column']);
$locationReady = !empty($data['location_ready']);
$policyTemplatesReady = !empty($data['policy_templates_ready']);
$organizationGroupReady = !empty($data['organization_group_ready']);
$companies = $data['companies'] ?? [];
$locatedCompanies = count(array_filter($companies, function ($company) { return !empty($company->locality) && !empty($company->province); }));
?>

<div class="companies-page">
    <header class="companies-hero">
        <div>
            <div class="companies-eyebrow">Estructura organizacional</div>
            <h1>Empresas</h1>
            <p>Administrá identidades, ubicaciones, sucursales y módulos disponibles.</p>
        </div>
        <div class="companies-hero-stats" aria-label="Resumen de empresas">
            <div><strong><?php echo count($companies); ?></strong><span>Registradas</span></div>
            <?php if ($locationReady): ?><div><strong><?php echo $locatedCompanies; ?></strong><span>Con ubicación</span></div><?php endif; ?>
        </div>
    </header>

    <div class="companies-layout">
        <section class="companies-list-panel" aria-labelledby="companiesListTitle">
            <div class="companies-panel-head">
                <div><small>Directorio</small><h2 id="companiesListTitle">Empresas registradas</h2><p>Seleccioná una empresa para configurar su estructura y permisos.</p></div>
                <span class="companies-count-pill"><?php echo count($companies); ?> total</span>
            </div>
            <div class="companies-grid">
                <?php foreach ($companies as $company):
                    $otLabel = $showOtCol && function_exists('company_overtime_label') ? company_overtime_label($company) : '';
                    $cpLabel = $showCpCol && function_exists('company_cp_extras_label') ? company_cp_extras_label($company) : '';
                    $companyLogoUrl = function_exists('company_brand_logo_url') ? company_brand_logo_url((int)$company->id) : (URLROOT . '/img/logo-paviotti.png');
                    $companyColor = function_exists('company_brand_color') ? company_brand_color((int)$company->id) : '#1d4ed8';
                    $stateClass = function ($label) {
                        if ($label === 'Sí') return 'is-yes';
                        if ($label === 'No') return 'is-no';
                        return 'is-neutral';
                    };
                ?>
                <article class="company-directory-card" style="--company-card-color:<?php echo htmlspecialchars($companyColor); ?>">
                    <div class="company-directory-top">
                        <span class="company-directory-logo"><img src="<?php echo htmlspecialchars($companyLogoUrl); ?>" alt="Logo de <?php echo htmlspecialchars($company->name); ?>"></span>
                        <span class="company-directory-id">#<?php echo (int)$company->id; ?></span>
                    </div>
                    <h3><?php echo htmlspecialchars($company->name); ?></h3>
                    <?php if ($organizationGroupReady): ?><span class="badge text-bg-light border align-self-start"><?php echo htmlspecialchars(Company::organizationGroupOptions()[$company->organization_group ?? 'paviotti'] ?? 'PAVIOTTI'); ?></span><?php endif; ?>
                    <?php if ($locationReady): ?>
                    <p class="company-directory-location <?php echo empty($company->locality) || empty($company->province) ? 'is-missing' : ''; ?>">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo !empty($company->locality) && !empty($company->province)
                            ? htmlspecialchars($company->locality . ', ' . $company->province)
                            : 'Ubicación sin definir'; ?>
                    </p>
                    <?php endif; ?>
                    <?php if ($showOtCol || $showCpCol): ?>
                    <div class="company-directory-features">
                        <?php if ($showOtCol): ?><div><span>Horas extras</span><b class="company-state <?php echo $stateClass($otLabel); ?>"><?php echo htmlspecialchars($otLabel ?: '—'); ?></b></div><?php endif; ?>
                        <?php if ($showCpCol): ?><div><span>Extras CP</span><b class="company-state <?php echo $stateClass($cpLabel); ?>"><?php echo htmlspecialchars($cpLabel ?: '—'); ?></b></div><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <a href="<?php echo URLROOT; ?>/admin/editCompany/<?php echo (int)$company->id; ?>" class="company-directory-action">
                        <span><i class="fas fa-sliders-h me-2"></i>Configurar empresa</span><i class="fas fa-arrow-right"></i>
                    </a>
                </article>
                <?php endforeach; ?>
                <?php if (empty($companies)): ?>
                <div class="companies-empty"><i class="fas fa-building"></i><strong>No hay empresas registradas</strong><span>Creá la primera desde el panel lateral.</span></div>
                <?php endif; ?>
            </div>
        </section>

        <aside class="companies-create-panel" aria-labelledby="createCompanyTitle">
            <div class="companies-create-head"><span><i class="fas fa-plus"></i></span><div><small>Nueva organización</small><h2 id="createCompanyTitle">Crear empresa</h2></div></div>
            <div class="companies-create-body">
                <p>Podrás agregar ubicación, sucursales y módulos después de crearla.</p>
                <form action="<?php echo URLROOT; ?>/admin/createCompany" method="post">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label for="company_name" class="form-label">Nombre de la empresa</label>
                        <div class="companies-input-wrap"><i class="fas fa-building"></i><input type="text" name="company_name" id="company_name" class="form-control" placeholder="Ej. Mi empresa" required></div>
                    </div>
                    <?php if ($organizationGroupReady): ?>
                    <div class="mb-3">
                        <label for="organization_group" class="form-label">Grupo organizacional</label>
                        <select name="organization_group" id="organization_group" class="form-select" required>
                            <?php foreach (Company::organizationGroupOptions() as $groupKey => $groupLabel): ?><option value="<?php echo htmlspecialchars($groupKey); ?>"><?php echo htmlspecialchars($groupLabel); ?></option><?php endforeach; ?>
                        </select>
                        <div class="form-text">Define si la empresa pertenece al grupo PAVIOTTI o MODERNA.</div>
                    </div>
                    <?php endif; ?>
                    <?php if ($policyTemplatesReady): ?>
                    <div class="mb-3">
                        <label for="policy_source_company_id" class="form-label">Permisos iniciales</label>
                        <select name="policy_source_company_id" id="policy_source_company_id" class="form-select">
                            <option value="0">Sin política propia (usar herencia global)</option>
                            <?php foreach ($data['companies'] as $company): ?>
                            <option value="<?php echo (int)$company->id; ?>">Copiar permisos de <?php echo htmlspecialchars($company->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Después podés personalizarlos por empresa o sucursal.</div>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary w-100 companies-create-button"><i class="fas fa-plus me-2"></i>Crear empresa</button>
                </form>
            </div>
        </aside>
    </div>
</div>

<?php require APPROOT . '/views/inc/footer.php'; ?>
