<?php
require APPROOT . '/views/inc/header.php';
$viewData=$data??[]; $users=$viewData['users']??[]; $companies=$viewData['companies']??[];
$companyFilter=(int)($viewData['company_filter']??0); $recordReady=!empty($viewData['employee_record_ready']);
$branchFilter=(int)($viewData['branch_filter']??0); $activeBranch=$viewData['active_branch']??null;
$totalUsers=count($users); $accessActive=count(array_filter($users,fn($u)=>(int)$u->is_active===1));
$laborActive=count(array_filter($users,fn($u)=>($u->employment_status??'')==='activo'));
$incomplete=$recordReady?count(array_filter($users,fn($u)=>(int)($u->record_percent??0)<70)):0;
$multiBranch=count(array_filter($users,fn($u)=>(int)($u->branch_count??0)>1));
$roleLabels=['admin'=>'Administrador','supervisor'=>'Supervisor','empleado'=>'Empleado'];
$employmentLabels=['preingreso'=>'Preingreso','activo'=>'Activo','licencia'=>'Licencia','suspendido'=>'Suspendido','finalizado'=>'Finalizado'];
$workModeLabels=['presencial'=>'Presencial','hibrido'=>'Híbrido','remoto'=>'Remoto'];
?>
<div class="admin-users-page">
<div class="admin-page-head">
    <div class="admin-page-brand"><div class="admin-page-icon"><i class="fas fa-users"></i></div><div class="admin-page-meta"><h1 class="page-title">Equipo y legajos</h1><p class="page-subtitle mb-0">Accesos, relación laboral, destino operativo y completitud documental.</p></div></div>
    <div class="admin-page-actions d-flex flex-wrap gap-2">
        <?php if($recordReady): ?><a href="<?php echo URLROOT; ?>/admin/employeeCatalogs" class="btn btn-outline-primary btn-sm"><i class="fas fa-layer-group me-1"></i> Catálogos</a><?php endif; ?>
        <?php if(function_exists('vacation_module_ready')&&vacation_module_ready()): ?><a href="<?php echo URLROOT; ?>/vacationAdmin/liquidateCompanyBatch<?php echo $companyFilter>0?'?company_id='.$companyFilter:''; ?>" class="btn btn-success btn-sm" title="Crear períodos para empleados activos"><i class="fas fa-bolt me-1"></i> Liquidar vacaciones</a><?php endif; ?>
        <a href="<?php echo URLROOT; ?>/admin/createUser" class="btn btn-primary px-4 fw-bold"><i class="fas fa-user-plus me-1"></i> Nuevo usuario</a>
    </div>
</div>
<?php if(!$recordReady): ?><div class="alert alert-warning d-flex align-items-center gap-2" role="status"><i class="fas fa-triangle-exclamation"></i><div>El listado funciona en modo compatible. Ejecutá <code>migration_employee_record_complete.sql</code> para habilitar puesto, estado laboral y completitud.</div></div><?php endif; ?>
<?php if ($activeBranch): ?><div class="alert alert-info d-flex align-items-center gap-2" role="status"><i class="fas fa-store"></i><div>Mostrando personas asignadas a la sucursal <strong><?php echo htmlspecialchars($activeBranch->name); ?></strong>.</div></div><?php endif; ?>

<div class="admin-kpi-grid users-kpi-grid">
    <div class="admin-kpi-card"><div class="admin-kpi-icon is-total"><i class="fas fa-id-badge"></i></div><div><div class="admin-kpi-value"><?php echo $totalUsers; ?></div><div class="admin-kpi-label">Personas</div></div></div>
    <div class="admin-kpi-card"><div class="admin-kpi-icon users-kpi-access"><i class="fas fa-key"></i></div><div><div class="admin-kpi-value"><?php echo $accessActive; ?></div><div class="admin-kpi-label">Accesos habilitados</div></div></div>
    <div class="admin-kpi-card"><div class="admin-kpi-icon users-kpi-labor"><i class="fas fa-briefcase"></i></div><div><div class="admin-kpi-value"><?php echo $recordReady?$laborActive:'—'; ?></div><div class="admin-kpi-label">Relaciones activas</div></div></div>
    <div class="admin-kpi-card"><div class="admin-kpi-icon users-kpi-alert"><i class="fas fa-clipboard-check"></i></div><div><div class="admin-kpi-value"><?php echo $recordReady?$incomplete:'—'; ?></div><div class="admin-kpi-label">Legajos por completar</div></div></div>
    <div class="admin-kpi-card"><div class="admin-kpi-icon users-kpi-branch"><i class="fas fa-code-branch"></i></div><div><div class="admin-kpi-value"><?php echo $recordReady?$multiBranch:'—'; ?></div><div class="admin-kpi-label">Multisucursal</div></div></div>
</div>

<section class="users-control-panel" aria-labelledby="users-filter-title">
    <div class="users-control-head"><div><h2 id="users-filter-title" class="h6 mb-1">Encontrar personas</h2><p class="small text-muted mb-0">Los filtros se combinan con la búsqueda.</p></div><span id="visibleUsersCount" class="users-result-count" aria-live="polite"><?php echo $totalUsers; ?> resultados</span></div>
    <div class="users-search-row">
        <label class="users-search-box" for="userSearch"><i class="fas fa-search" aria-hidden="true"></i><input type="search" id="userSearch" class="form-control" placeholder="Nombre, usuario, legajo, puesto, área o sucursal…" autocomplete="off"></label>
        <div class="admin-filter-group" aria-label="Estado y tipo">
            <button type="button" class="admin-filter-chip active" data-filter="all">Todos</button><button type="button" class="admin-filter-chip" data-filter="access-active">Acceso activo</button><button type="button" class="admin-filter-chip" data-filter="labor-active">Laboral activo</button><button type="button" class="admin-filter-chip" data-filter="incomplete">Ficha incompleta</button><button type="button" class="admin-filter-chip" data-filter="multibranch">Multisucursal</button><button type="button" class="admin-filter-chip" data-filter="admin">Admins</button><button type="button" class="admin-filter-chip" data-filter="supervisor">Supervisores</button><button type="button" class="admin-filter-chip" data-filter="empleado">Empleados</button>
        </div>
    </div>
    <?php if (function_exists('org_is_moderna') && org_is_moderna()):
        // Suite P&M: Moderna unifica — se filtra por ciudad y sucursal (cascada).
        // Matchea contra las sucursales reales de cada persona (branch_names).
        $orgCities = org_branches_by_city(); ?>
    <div class="users-company-filter">
        <span class="admin-toolbar-label"><i class="fas fa-city me-1"></i>Ciudad</span>
        <select id="orgCityFilter" class="form-select form-select-sm d-inline-block me-2" style="max-width:180px;width:auto;">
            <option value="" selected>Todas</option>
            <?php foreach (array_keys($orgCities) as $orgCity): ?>
            <option value="<?php echo htmlspecialchars($orgCity); ?>"><?php echo htmlspecialchars($orgCity); ?></option>
            <?php endforeach; ?>
        </select>
        <span class="admin-toolbar-label"><i class="fas fa-store me-1"></i>Sucursal</span>
        <select id="orgBranchFilter" class="form-select form-select-sm d-inline-block" style="max-width:210px;width:auto;" disabled title="Elegí primero una ciudad">
            <option value="" selected>Todas</option>
        </select>
        <script>window.ORG_BRANCHES_BY_CITY = <?php echo json_encode($orgCities, JSON_UNESCAPED_UNICODE); ?>;</script>
    </div>
    <?php else: ?>
    <div class="users-company-filter"><span class="admin-toolbar-label"><i class="fas fa-building me-1"></i>Empresa</span><div class="admin-filter-group"><a href="<?php echo URLROOT; ?>/admin/users" class="admin-filter-chip <?php echo $companyFilter===0?'active':''; ?>">Todas</a><?php foreach($companies as $co): ?><a href="<?php echo URLROOT; ?>/admin/users?company_id=<?php echo (int)$co->id; ?>" class="admin-filter-chip <?php echo $companyFilter===(int)$co->id?'active':''; ?>"><?php echo htmlspecialchars($co->name); ?></a><?php endforeach; ?></div></div>
    <?php endif; ?>
</section>

<div id="usersGrid" class="admin-user-grid">
<?php foreach($users as $u):
    $isAccessActive=(int)$u->is_active===1; $role=(string)$u->role; $laborStatus=(string)($u->employment_status?:'sin_definir');
    $percent=(int)($u->record_percent??0); $isIncomplete=$recordReady&&$percent<70; $initials='';
    foreach(array_slice(preg_split('/\s+/',trim($u->full_name)),0,2) as $part) $initials.=mb_strtoupper(mb_substr($part,0,1));
    $searchText=strtolower(implode(' ',[$u->full_name,$u->username,$u->employee_number??'',$u->position_name??'',$u->area_name??'',$u->branch_names??'',$u->company_name??'']));
?>
<article class="user-card-wrap" data-role="<?php echo htmlspecialchars($role); ?>" data-access="<?php echo $isAccessActive?'active':'inactive'; ?>" data-labor="<?php echo htmlspecialchars($laborStatus); ?>" data-incomplete="<?php echo $isIncomplete?'1':'0'; ?>" data-branches="<?php echo (int)($u->branch_count??0); ?>" data-branch-names="<?php echo htmlspecialchars(mb_strtolower((string)($u->branch_names??''),'UTF-8'),ENT_QUOTES,'UTF-8'); ?>" data-name="<?php echo htmlspecialchars($searchText,ENT_QUOTES,'UTF-8'); ?>">
    <div class="admin-user-card <?php echo !$isAccessActive?'is-inactive':''; ?>">
        <div class="users-status-rail is-<?php echo htmlspecialchars($laborStatus); ?>" aria-hidden="true"></div>
        <div class="admin-user-top">
            <div class="position-relative flex-shrink-0"><img src="<?php echo htmlspecialchars(avatar_url($u->profile_picture??'')); ?>" alt="" class="admin-avatar-circle <?php echo $isAccessActive?'is-active':''; ?> users-list-avatar" onerror="this.hidden=true;this.nextElementSibling.hidden=false;"><span class="admin-avatar-fallback <?php echo $role==='admin'?'is-admin':'is-employee'; ?> <?php echo $isAccessActive?'is-active':''; ?> users-list-avatar" hidden><?php echo htmlspecialchars($initials); ?></span><span class="users-access-dot <?php echo $isAccessActive?'is-active':'is-inactive'; ?>" title="Acceso <?php echo $isAccessActive?'habilitado':'deshabilitado'; ?>"></span></div>
            <div class="admin-user-meta"><div class="users-name-line"><p class="admin-user-name"><?php echo htmlspecialchars($u->full_name); ?></p><?php if(!empty($u->employee_number)): ?><span class="users-legajo">#<?php echo htmlspecialchars($u->employee_number); ?></span><?php endif; ?></div><div class="admin-user-handle">@<?php echo htmlspecialchars($u->username); ?> · <?php echo htmlspecialchars($roleLabels[$role]??ucfirst($role)); ?></div><div class="users-primary-role"><?php echo htmlspecialchars($u->position_name?:'Puesto sin definir'); ?></div><div class="users-company-line"><i class="fas fa-building" aria-hidden="true"></i><span><?php echo htmlspecialchars($u->company_name?:'Sin empresa'); ?></span><?php if(!empty($u->area_name)): ?><span class="users-dot-sep">•</span><span><?php echo htmlspecialchars($u->area_name); ?></span><?php endif; ?></div></div>
        </div>
        <div class="users-operational-grid"><div><span>Estado laboral</span><strong class="users-labor-state is-<?php echo htmlspecialchars($laborStatus); ?>"><?php echo htmlspecialchars($employmentLabels[$laborStatus]??'Sin definir'); ?></strong></div><div><span>Modalidad</span><strong><?php echo htmlspecialchars($workModeLabels[$u->work_mode??'']??'—'); ?></strong></div><div class="users-branches"><span>Sucursales</span><strong title="<?php echo htmlspecialchars($u->branch_names??''); ?>"><?php echo (int)($u->branch_count??0)>0?(int)$u->branch_count.' · '.htmlspecialchars($u->branch_names):'Sin asignar'; ?></strong></div></div>
        <?php if($recordReady): ?><div class="users-completeness <?php echo $percent<70?'is-low':($percent<100?'is-mid':'is-complete'); ?>"><div class="d-flex justify-content-between align-items-center"><span>Ficha completa</span><strong><?php echo $percent; ?>%</strong></div><progress value="<?php echo $percent; ?>" max="100" aria-label="Completitud del legajo: <?php echo $percent; ?>%"></progress></div><?php endif; ?>
        <div class="admin-user-actions"><a href="<?php echo URLROOT; ?>/admin/employeeProfile/<?php echo (int)$u->id; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-address-card me-1"></i> Ficha</a><a href="<?php echo URLROOT; ?>/admin/editUser/<?php echo (int)$u->id; ?>" class="btn btn-sm btn-primary"><i class="fas fa-pen me-1"></i> Completar</a><form method="post" action="<?php echo URLROOT; ?>/admin/toggleUserStatus/<?php echo (int)$u->id; ?>" class="d-inline"><?php echo csrf_field(); ?><button type="submit" class="admin-icon-btn <?php echo $isAccessActive?'is-danger':'is-success'; ?> toggle-status-btn" data-action="<?php echo $isAccessActive?'desactivar':'activar'; ?>" title="<?php echo $isAccessActive?'Desactivar acceso':'Activar acceso'; ?>" aria-label="<?php echo $isAccessActive?'Desactivar':'Activar'; ?> acceso de <?php echo htmlspecialchars($u->full_name); ?>"><i class="fas <?php echo $isAccessActive?'fa-user-slash':'fa-user-check'; ?>"></i></button></form></div>
    </div>
</article>
<?php endforeach; ?>
</div>
<div id="noUsersMsg" class="admin-empty d-none" role="status"><i class="fas fa-search"></i><strong>No encontramos personas</strong><span>Probá otra búsqueda o cambiá el filtro.</span></div>
</div>
<script>
(function(){
    const cards=Array.from(document.querySelectorAll('.user-card-wrap')),search=document.getElementById('userSearch'),filters=document.querySelectorAll('[data-filter]'),empty=document.getElementById('noUsersMsg'),count=document.getElementById('visibleUsersCount');
    // Suite P&M (vista Moderna): cascada Ciudad → Sucursal, matchea contra
    // las sucursales reales de cada tarjeta (data-branch-names).
    const cityFilter=document.getElementById('orgCityFilter'),branchFilter=document.getElementById('orgBranchFilter');
    let active='all';
    function branchMatch(card){
        if(!cityFilter||!window.ORG_BRANCHES_BY_CITY)return true;
        const city=cityFilter.value,branch=(branchFilter&&branchFilter.value)||'';
        if(!city&&!branch)return true;
        const names=card.dataset.branchNames||'';
        if(branch)return names.includes(branch.toLocaleLowerCase('es'));
        const list=window.ORG_BRANCHES_BY_CITY[city]||[];
        return list.some(b=>names.includes(b.toLocaleLowerCase('es')));
    }
    function apply(){
        const q=(search.value||'').toLocaleLowerCase('es').trim();let visible=0;
        cards.forEach(card=>{
            const matches=active==='all'||(active==='access-active'&&card.dataset.access==='active')||(active==='labor-active'&&card.dataset.labor==='activo')||(active==='incomplete'&&card.dataset.incomplete==='1')||(active==='multibranch'&&Number(card.dataset.branches)>1)||card.dataset.role===active;
            const show=matches&&branchMatch(card)&&(!q||card.dataset.name.includes(q));
            card.hidden=!show;if(show)visible++;
        });
        empty.classList.toggle('d-none',visible>0);
        count.textContent=visible+(visible===1?' resultado':' resultados');
    }
    filters.forEach(button=>button.addEventListener('click',()=>{filters.forEach(item=>item.classList.remove('active'));button.classList.add('active');active=button.dataset.filter;apply();}));
    search.addEventListener('input',apply);
    if(cityFilter&&branchFilter&&window.ORG_BRANCHES_BY_CITY){
        cityFilter.addEventListener('change',()=>{
            const city=cityFilter.value;
            branchFilter.innerHTML='<option value="" selected>Todas</option>';
            if(city&&window.ORG_BRANCHES_BY_CITY[city]){
                window.ORG_BRANCHES_BY_CITY[city].forEach(b=>{const opt=document.createElement('option');opt.value=b;opt.textContent=b;branchFilter.appendChild(opt);});
                branchFilter.disabled=false;branchFilter.title='';
            }else{
                branchFilter.disabled=true;branchFilter.title='Elegí primero una ciudad';
            }
            apply();
        });
        branchFilter.addEventListener('change',apply);
    }
    apply();
})();
</script>
<?php require APPROOT . '/views/inc/footer.php'; ?>
