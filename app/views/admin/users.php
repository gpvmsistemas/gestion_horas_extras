<?php
require APPROOT . '/views/inc/header.php';
$viewData = $data ?? [];
$users = $viewData['users'] ?? [];
$companies = $viewData['companies'] ?? [];
$companyFilter = (int)($viewData['company_filter'] ?? 0);
$recordReady = !empty($viewData['employee_record_ready']);
$branchFilter = (int)($viewData['branch_filter'] ?? 0);
$activeBranch = $viewData['active_branch'] ?? null;
$listQuery = $viewData['list_query'] ?? ['q' => '', 'filter' => 'all', 'city' => '', 'branch' => '', 'page' => 1, 'company_id' => $companyFilter];
$kpis = $viewData['list_kpis'] ?? [];
$totalUsers = (int)($kpis['total'] ?? count($users));
$accessActive = (int)($kpis['access_active'] ?? 0);
$laborActive = $recordReady ? ($kpis['labor_active'] ?? 0) : null;
$incomplete = $recordReady ? ($kpis['incomplete'] ?? 0) : null;
$multiBranch = $recordReady ? ($kpis['multi_branch'] ?? 0) : null;
$listTotal = (int)($viewData['list_total'] ?? count($users));
$listPage = (int)($viewData['list_page'] ?? 1);
$listPages = (int)($viewData['list_pages'] ?? 1);
$roleLabels = ['admin' => 'Administrador', 'supervisor' => 'Supervisor', 'empleado' => 'Empleado'];
$employmentLabels = ['preingreso' => 'Preingreso', 'activo' => 'Activo', 'licencia' => 'Licencia', 'suspendido' => 'Suspendido', 'finalizado' => 'Finalizado'];
$workModeLabels = ['presencial' => 'Presencial', 'hibrido' => 'Híbrido', 'remoto' => 'Remoto'];
$activeFilter = (string)($listQuery['filter'] ?? 'all');
$usersUrl = static function (array $overrides = []) use ($listQuery, $companyFilter) {
    $query = array_merge($listQuery, $overrides);
    if (!array_key_exists('company_id', $overrides)) {
        $query['company_id'] = $companyFilter;
    }
    $clean = [];
    foreach (['company_id', 'q', 'filter', 'city', 'branch', 'page'] as $key) {
        if (!array_key_exists($key, $query)) {
            continue;
        }
        $value = is_scalar($query[$key]) ? trim((string)$query[$key]) : '';
        if ($value === '' || ($key === 'filter' && $value === 'all') || ($key === 'page' && (int)$value <= 1) || ($key === 'company_id' && (int)$value <= 0)) {
            continue;
        }
        $clean[$key] = $value;
    }
    $qs = http_build_query($clean);
    return URLROOT . '/admin/users' . ($qs !== '' ? '?' . $qs : '');
};
$returnQuery = http_build_query(array_filter([
    'company_id' => $companyFilter > 0 ? $companyFilter : null,
    'q' => $listQuery['q'] !== '' ? $listQuery['q'] : null,
    'filter' => ($listQuery['filter'] ?? 'all') !== 'all' ? $listQuery['filter'] : null,
    'city' => ($listQuery['city'] ?? '') !== '' ? $listQuery['city'] : null,
    'branch' => ($listQuery['branch'] ?? '') !== '' ? $listQuery['branch'] : null,
    'page' => $listPage > 1 ? $listPage : null,
], static function ($v) { return $v !== null && $v !== ''; }));
$chipFilters = [
    'all' => 'Todos',
    'access-active' => 'Acceso activo',
    'labor-active' => 'Laboral activo',
    'incomplete' => 'Ficha incompleta',
    'multibranch' => 'Multisucursal',
    'admin' => 'Admins / RRHH',
    'supervisor' => 'Encargados',
    'empleado' => 'Operarios',
];
?>
<div class="admin-users-page">
<div class="admin-page-head">
    <div class="admin-page-brand"><div class="admin-page-icon"><i class="fas fa-users"></i></div><div class="admin-page-meta"><h1 class="page-title">Equipo y legajos</h1><p class="page-subtitle mb-0">Accesos, relación laboral, destino operativo y completitud documental.</p></div></div>
    <div class="admin-page-actions d-flex flex-wrap gap-2">
        <?php if ($recordReady): ?><a href="<?php echo URLROOT; ?>/admin/employeeCatalogs" class="btn btn-outline-primary btn-sm"><i class="fas fa-layer-group me-1"></i> Catálogos</a><?php endif; ?>
        <?php if (function_exists('vacation_module_ready') && vacation_module_ready()): ?><a href="<?php echo URLROOT; ?>/vacationAdmin/panel" class="btn btn-success btn-sm" title="Panel de vacaciones / liquidar períodos"><i class="fas fa-bolt me-1"></i> Vacaciones</a><?php endif; ?>
        <a href="<?php echo URLROOT; ?>/admin/createUser" class="btn btn-primary px-4 fw-bold"><i class="fas fa-user-plus me-1"></i> Nuevo usuario</a>
    </div>
</div>
<?php if (!$recordReady): ?><div class="alert alert-warning d-flex align-items-center gap-2" role="status"><i class="fas fa-triangle-exclamation"></i><div>El listado funciona en modo compatible. Ejecutá <code>migration_employee_record_complete.sql</code> para habilitar puesto, estado laboral y completitud.</div></div><?php endif; ?>
<?php if ($activeBranch): ?><div class="alert alert-info d-flex align-items-center gap-2" role="status"><i class="fas fa-store"></i><div>Mostrando personas asignadas a la sucursal <strong><?php echo htmlspecialchars($activeBranch->name); ?></strong>.</div></div><?php endif; ?>

<div class="admin-kpi-grid users-kpi-grid">
    <div class="admin-kpi-card"><div class="admin-kpi-icon is-total"><i class="fas fa-id-badge"></i></div><div><div class="admin-kpi-value"><?php echo $totalUsers; ?></div><div class="admin-kpi-label">Personas</div></div></div>
    <div class="admin-kpi-card"><div class="admin-kpi-icon users-kpi-access"><i class="fas fa-key"></i></div><div><div class="admin-kpi-value"><?php echo $accessActive; ?></div><div class="admin-kpi-label">Accesos habilitados</div></div></div>
    <div class="admin-kpi-card"><div class="admin-kpi-icon users-kpi-labor"><i class="fas fa-briefcase"></i></div><div><div class="admin-kpi-value"><?php echo $recordReady ? (int)$laborActive : '—'; ?></div><div class="admin-kpi-label">Relaciones activas</div></div></div>
    <div class="admin-kpi-card"><div class="admin-kpi-icon users-kpi-alert"><i class="fas fa-clipboard-check"></i></div><div><div class="admin-kpi-value"><?php echo $recordReady ? (int)$incomplete : '—'; ?></div><div class="admin-kpi-label">Legajos por completar</div></div></div>
    <div class="admin-kpi-card"><div class="admin-kpi-icon users-kpi-branch"><i class="fas fa-code-branch"></i></div><div><div class="admin-kpi-value"><?php echo $recordReady ? (int)$multiBranch : '—'; ?></div><div class="admin-kpi-label">Multisucursal</div></div></div>
</div>

<section class="users-control-panel" aria-labelledby="users-filter-title">
    <div class="users-control-head"><div><h2 id="users-filter-title" class="h6 mb-1">Encontrar personas</h2><p class="small text-muted mb-0">La búsqueda y los filtros se aplican en el servidor.</p></div><span class="users-result-count" aria-live="polite"><?php echo $listTotal === 1 ? '1 resultado' : $listTotal . ' resultados'; ?></span></div>
    <form method="get" action="<?php echo URLROOT; ?>/admin/users" class="users-search-row" id="usersFilterForm">
        <?php if ($companyFilter > 0): ?><input type="hidden" name="company_id" value="<?php echo $companyFilter; ?>"><?php endif; ?>
        <?php if ($activeFilter !== 'all'): ?><input type="hidden" name="filter" value="<?php echo htmlspecialchars($activeFilter); ?>"><?php endif; ?>
        <?php if (($listQuery['city'] ?? '') !== ''): ?><input type="hidden" name="city" value="<?php echo htmlspecialchars($listQuery['city']); ?>"><?php endif; ?>
        <?php if (($listQuery['branch'] ?? '') !== ''): ?><input type="hidden" name="branch" value="<?php echo htmlspecialchars($listQuery['branch']); ?>"><?php endif; ?>
        <label class="users-search-box" for="userSearch"><i class="fas fa-search" aria-hidden="true"></i>
            <input type="search" id="userSearch" name="q" class="form-control" value="<?php echo htmlspecialchars($listQuery['q'] ?? ''); ?>" placeholder="Nombre, usuario, legajo, puesto, área o sucursal…" autocomplete="off">
            <button type="submit" class="users-search-submit">Buscar</button>
        </label>
        <div class="admin-filter-group" aria-label="Estado y tipo">
            <?php foreach ($chipFilters as $filterKey => $filterLabel):
                if (!$recordReady && in_array($filterKey, ['labor-active', 'incomplete', 'multibranch'], true)) continue;
                $chipUrl = $usersUrl(['filter' => $filterKey, 'page' => 1]);
            ?>
            <a href="<?php echo htmlspecialchars($chipUrl); ?>" class="admin-filter-chip<?php echo $activeFilter === $filterKey ? ' active' : ''; ?>"<?php echo $activeFilter === $filterKey ? ' aria-current="true"' : ''; ?>><?php echo htmlspecialchars($filterLabel); ?></a>
            <?php endforeach; ?>
        </div>
    </form>
    <?php if (function_exists('org_is_moderna') && org_is_moderna()):
        $orgCities = org_branches_by_city();
        $selectedCity = (string)($listQuery['city'] ?? '');
        $cityBranches = ($selectedCity !== '' && isset($orgCities[$selectedCity])) ? $orgCities[$selectedCity] : [];
    ?>
    <form method="get" action="<?php echo URLROOT; ?>/admin/users" class="users-company-filter" id="usersPlaceForm">
        <?php if ($companyFilter > 0): ?><input type="hidden" name="company_id" value="<?php echo $companyFilter; ?>"><?php endif; ?>
        <?php if (($listQuery['q'] ?? '') !== ''): ?><input type="hidden" name="q" value="<?php echo htmlspecialchars($listQuery['q']); ?>"><?php endif; ?>
        <?php if ($activeFilter !== 'all'): ?><input type="hidden" name="filter" value="<?php echo htmlspecialchars($activeFilter); ?>"><?php endif; ?>
        <span class="admin-toolbar-label"><i class="fas fa-city me-1"></i>Ciudad</span>
        <select name="city" id="orgCityFilter" class="form-select form-select-sm d-inline-block me-2" style="max-width:180px;width:auto;" onchange="this.form.querySelector('[name=branch]').value=''; this.form.submit();">
            <option value="">Todas</option>
            <?php foreach (array_keys($orgCities) as $orgCity): ?>
            <option value="<?php echo htmlspecialchars($orgCity); ?>" <?php echo $selectedCity === $orgCity ? 'selected' : ''; ?>><?php echo htmlspecialchars($orgCity); ?></option>
            <?php endforeach; ?>
        </select>
        <span class="admin-toolbar-label"><i class="fas fa-store me-1"></i>Sucursal</span>
        <select name="branch" id="orgBranchFilter" class="form-select form-select-sm d-inline-block" style="max-width:210px;width:auto;" <?php echo $selectedCity === '' ? 'disabled' : ''; ?> title="<?php echo $selectedCity === '' ? 'Elegí primero una ciudad' : ''; ?>" onchange="this.form.submit();">
            <option value="">Todas</option>
            <?php foreach ($cityBranches as $branchName): ?>
            <option value="<?php echo htmlspecialchars($branchName); ?>" <?php echo ($listQuery['branch'] ?? '') === $branchName ? 'selected' : ''; ?>><?php echo htmlspecialchars($branchName); ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn btn-sm btn-outline-primary ms-2">Filtrar</button></noscript>
    </form>
    <?php else: ?>
    <div class="users-company-filter"><span class="admin-toolbar-label"><i class="fas fa-building me-1"></i>Empresa</span><div class="admin-filter-group"><a href="<?php echo htmlspecialchars($usersUrl(['company_id' => 0, 'page' => 1])); ?>" class="admin-filter-chip <?php echo $companyFilter === 0 ? 'active' : ''; ?>">Todas</a><?php foreach ($companies as $co): ?><a href="<?php echo htmlspecialchars($usersUrl(['company_id' => (int)$co->id, 'page' => 1])); ?>" class="admin-filter-chip <?php echo $companyFilter === (int)$co->id ? 'active' : ''; ?>"><?php echo htmlspecialchars($co->name); ?></a><?php endforeach; ?></div></div>
    <?php endif; ?>
</section>

<div id="usersGrid" class="admin-user-grid">
<?php foreach ($users as $u):
    $isAccessActive = (int)$u->is_active === 1;
    $role = (string)$u->role;
    $laborStatus = (string)($u->employment_status ?: 'sin_definir');
    $percent = (int)($u->record_percent ?? 0);
    $initials = '';
    foreach (array_slice(preg_split('/\s+/', trim($u->full_name)), 0, 2) as $part) $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    $accessLabel = $u->access_role_label ?? ($roleLabels[$role] ?? ucfirst($role));
?>
<article class="user-card-wrap">
    <div class="admin-user-card <?php echo !$isAccessActive ? 'is-inactive' : ''; ?>">
        <div class="users-status-rail is-<?php echo htmlspecialchars($laborStatus); ?>" aria-hidden="true"></div>
        <div class="admin-user-top">
            <div class="position-relative flex-shrink-0"><img src="<?php echo htmlspecialchars(avatar_url($u->profile_picture ?? '')); ?>" alt="" class="admin-avatar-circle <?php echo $isAccessActive ? 'is-active' : ''; ?> users-list-avatar" onerror="this.hidden=true;this.nextElementSibling.hidden=false;"><span class="admin-avatar-fallback <?php echo $role === 'admin' ? 'is-admin' : 'is-employee'; ?> <?php echo $isAccessActive ? 'is-active' : ''; ?> users-list-avatar" hidden><?php echo htmlspecialchars($initials); ?></span><span class="users-access-dot <?php echo $isAccessActive ? 'is-active' : 'is-inactive'; ?>" title="Acceso <?php echo $isAccessActive ? 'habilitado' : 'deshabilitado'; ?>"></span></div>
            <div class="admin-user-meta"><div class="users-name-line"><p class="admin-user-name"><?php echo htmlspecialchars($u->full_name); ?></p><?php if (!empty($u->employee_number)): ?><span class="users-legajo">#<?php echo htmlspecialchars($u->employee_number); ?></span><?php endif; ?></div><div class="admin-user-handle">@<?php echo htmlspecialchars($u->username); ?> · <?php echo htmlspecialchars($accessLabel); ?><?php if (!empty($u->role_drift)): ?><span class="users-role-drift" title="El rol de cuenta todavía no coincidía con el perfil de acceso. Se alinea al guardar la ficha o los permisos.">cuenta: <?php echo htmlspecialchars($roleLabels[$role] ?? ucfirst($role)); ?></span><?php endif; ?></div><div class="users-primary-role"><?php echo htmlspecialchars($u->position_name ?: 'Puesto sin definir'); ?></div><div class="users-company-line"><i class="fas fa-building" aria-hidden="true"></i><span><?php echo htmlspecialchars($u->company_name ?: 'Sin empresa'); ?></span><?php if (!empty($u->area_name)): ?><span class="users-dot-sep">•</span><span><?php echo htmlspecialchars($u->area_name); ?></span><?php endif; ?></div></div>
        </div>
        <div class="users-operational-grid"><div><span>Estado laboral</span><strong class="users-labor-state is-<?php echo htmlspecialchars($laborStatus); ?>"><?php echo htmlspecialchars($employmentLabels[$laborStatus] ?? 'Sin definir'); ?></strong></div><div><span>Modalidad</span><strong><?php echo htmlspecialchars($workModeLabels[$u->work_mode ?? ''] ?? '—'); ?></strong></div><div class="users-branches"><span>Sucursales</span><strong title="<?php echo htmlspecialchars($u->branch_names ?? ''); ?>"><?php echo (int)($u->branch_count ?? 0) > 0 ? (int)$u->branch_count . ' · ' . htmlspecialchars($u->branch_names) : 'Sin asignar'; ?></strong></div></div>
        <?php if ($recordReady): ?><div class="users-completeness <?php echo $percent < 70 ? 'is-low' : ($percent < 100 ? 'is-mid' : 'is-complete'); ?>"><div class="d-flex justify-content-between align-items-center"><span>Ficha completa</span><strong><?php echo $percent; ?>%</strong></div><progress value="<?php echo $percent; ?>" max="100" aria-label="Completitud del legajo: <?php echo $percent; ?>%"></progress></div><?php endif; ?>
        <div class="admin-user-actions"><a href="<?php echo URLROOT; ?>/admin/employeeProfile/<?php echo (int)$u->id; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-address-card me-1"></i> Ficha</a><a href="<?php echo URLROOT; ?>/admin/editUser/<?php echo (int)$u->id; ?>" class="btn btn-sm btn-primary"><i class="fas fa-pen me-1"></i> Completar</a><form method="post" action="<?php echo URLROOT; ?>/admin/toggleUserStatus/<?php echo (int)$u->id; ?>" class="d-inline"><?php echo csrf_field(); ?><?php if ($returnQuery !== ''): ?><input type="hidden" name="return_query" value="<?php echo htmlspecialchars($returnQuery); ?>"><?php endif; ?><button type="submit" class="admin-icon-btn <?php echo $isAccessActive ? 'is-danger' : 'is-success'; ?> toggle-status-btn" data-action="<?php echo $isAccessActive ? 'desactivar' : 'activar'; ?>" title="<?php echo $isAccessActive ? 'Desactivar acceso' : 'Activar acceso'; ?>" aria-label="<?php echo $isAccessActive ? 'Desactivar' : 'Activar'; ?> acceso de <?php echo htmlspecialchars($u->full_name); ?>"><i class="fas <?php echo $isAccessActive ? 'fa-user-slash' : 'fa-user-check'; ?>"></i></button></form></div>
    </div>
</article>
<?php endforeach; ?>
</div>
<?php if (empty($users)): ?>
<div class="admin-empty" role="status"><i class="fas fa-search"></i><strong>No encontramos personas</strong><span>Probá otra búsqueda o cambiá el filtro.</span></div>
<?php endif; ?>
<?php if ($listPages > 1): ?>
<nav class="users-pagination" aria-label="Páginas del listado">
    <ul class="pagination pagination-sm justify-content-center mb-0">
        <?php if ($listPage > 1): ?>
        <li class="page-item"><a class="page-link" href="<?php echo htmlspecialchars($usersUrl(['page' => $listPage - 1])); ?>">Anterior</a></li>
        <?php endif; ?>
        <?php
        $from = max(1, $listPage - 2);
        $to = min($listPages, $listPage + 2);
        for ($p = $from; $p <= $to; $p++):
        ?>
        <li class="page-item <?php echo $p === $listPage ? 'active' : ''; ?>"><a class="page-link" href="<?php echo htmlspecialchars($usersUrl(['page' => $p])); ?>"><?php echo $p; ?></a></li>
        <?php endfor; ?>
        <?php if ($listPage < $listPages): ?>
        <li class="page-item"><a class="page-link" href="<?php echo htmlspecialchars($usersUrl(['page' => $listPage + 1])); ?>">Siguiente</a></li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>
</div>
<?php require APPROOT . '/views/inc/footer.php'; ?>
