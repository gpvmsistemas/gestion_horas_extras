<?php
require APPROOT . '/views/inc/header.php';

$viewData   = $data ?? [];
$users      = $viewData['users'] ?? [];
$companies  = $viewData['companies'] ?? [];
$companyFilter = (int)($viewData['company_filter'] ?? 0);
$totalUsers = count($users);
$activos    = count(array_filter($users, fn($u) => $u->is_active == 1));
$inactivos  = $totalUsers - $activos;
$admins     = count(array_filter($users, fn($u) => $u->role === 'admin'));
$empleados  = count(array_filter($users, fn($u) => $u->role === 'empleado'));
?>

<div class="admin-users-page">
<div class="admin-page-head">
    <div class="admin-page-brand">
        <div class="admin-page-icon"><i class="fas fa-users"></i></div>
        <div class="admin-page-meta">
            <h2 class="page-title">Usuarios</h2>
            <p class="page-subtitle mb-0">Gestión del equipo, estados y accesos del sistema.</p>
        </div>
    </div>
    <div class="admin-page-actions d-flex flex-wrap gap-2">
        <?php if (function_exists('vacation_module_ready') && vacation_module_ready()): ?>
        <a href="<?php echo URLROOT; ?>/vacationAdmin/liquidateCompanyBatch<?php echo $companyFilter > 0 ? '?company_id=' . $companyFilter : ''; ?>"
           class="btn btn-success btn-sm" title="Crear período y calcular vacaciones para empleados activos">
            <i class="fas fa-bolt me-1"></i> Liquidar vacaciones (activos)
        </a>
        <?php endif; ?>
        <a href="<?php echo URLROOT; ?>/admin/createUser" class="btn btn-primary px-4 fw-bold">
            <i class="fas fa-user-plus me-1"></i> Nuevo usuario
        </a>
    </div>
</div>

<div class="admin-kpi-grid">
    <div class="admin-kpi-card">
        <div class="admin-kpi-icon is-total"><i class="fas fa-users"></i></div>
        <div>
            <div class="admin-kpi-value"><?php echo $totalUsers; ?></div>
            <div class="admin-kpi-label">Total</div>
        </div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-icon" style="background:#d1fae5;color:#065f46"><i class="fas fa-user-check"></i></div>
        <div>
            <div class="admin-kpi-value"><?php echo $activos; ?></div>
            <div class="admin-kpi-label">Activos</div>
        </div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-icon" style="background:#fee2e2;color:#991b1b"><i class="fas fa-user-slash"></i></div>
        <div>
            <div class="admin-kpi-value"><?php echo $inactivos; ?></div>
            <div class="admin-kpi-label">Inactivos</div>
        </div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-icon" style="background:#eef2ff;color:#4338ca"><i class="fas fa-user-shield"></i></div>
        <div>
            <div class="admin-kpi-value"><?php echo $admins; ?></div>
            <div class="admin-kpi-label">Admins</div>
        </div>
    </div>
</div>

<div class="admin-toolbar flex-wrap">
    <?php if (function_exists('org_is_moderna') && org_is_moderna()):
        // Suite P&M: Moderna unifica — se filtra por ciudad y sucursal
        // (taxonomía de hoursapp; filtra la nómina cuando se migre con sucursal asignada).
        $orgCities = org_branches_by_city();
    ?>
    <span class="admin-toolbar-label"><i class="fas fa-city me-1"></i>Ciudad</span>
    <div class="mb-2 mb-md-0 me-2">
        <select id="orgCityFilter" class="form-select form-select-sm" style="min-width:160px">
            <option value="" selected>Todas</option>
            <?php foreach (array_keys($orgCities) as $orgCity): ?>
            <option value="<?php echo htmlspecialchars($orgCity); ?>"><?php echo htmlspecialchars($orgCity); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <span class="admin-toolbar-label"><i class="fas fa-store me-1"></i>Sucursal</span>
    <div class="mb-2 mb-md-0 me-2">
        <select id="orgBranchFilter" class="form-select form-select-sm" style="min-width:190px" disabled title="Elegí primero una ciudad">
            <option value="" selected>Todas</option>
        </select>
    </div>
    <script>window.ORG_BRANCHES_BY_CITY = <?php echo json_encode($orgCities, JSON_UNESCAPED_UNICODE); ?>;</script>
    <?php else: ?>
    <span class="admin-toolbar-label"><i class="fas fa-building me-1"></i>Empresa</span>
    <div class="admin-filter-group mb-2 mb-md-0">
        <a href="<?php echo URLROOT; ?>/admin/users" class="admin-filter-chip <?php echo $companyFilter === 0 ? 'active' : ''; ?>">Todas</a>
        <?php foreach ($companies as $co): ?>
        <a href="<?php echo URLROOT; ?>/admin/users?company_id=<?php echo (int)$co->id; ?>"
           class="admin-filter-chip <?php echo $companyFilter === (int)$co->id ? 'active' : ''; ?>">
            <?php echo htmlspecialchars($co->name); ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <span class="admin-toolbar-label"><i class="fas fa-filter me-1"></i>Estado</span>
    <div class="admin-filter-group">
        <button class="admin-filter-chip active" data-filter="all">Todos</button>
        <button class="admin-filter-chip" data-filter="activo">Activos</button>
        <button class="admin-filter-chip" data-filter="inactivo">Inactivos</button>
        <button class="admin-filter-chip" data-filter="admin">Admins</button>
        <button class="admin-filter-chip" data-filter="empleado">Empleados</button>
    </div>
    <input type="text" id="userSearch" class="form-control admin-search" placeholder="Buscar por nombre o usuario...">
</div>

<div id="usersGrid" class="admin-user-grid">
<?php foreach($users as $u):
    $isActive  = ($u->is_active == 1);
    $isAdmin   = ($u->role === 'admin');
    $initials  = '';
    $parts     = explode(' ', $u->full_name);
    foreach (array_slice($parts, 0, 2) as $p) $initials .= strtoupper(mb_substr($p, 0, 1));
    $fallbackClass = $isAdmin ? 'is-admin' : 'is-employee';
    $avatarActiveClass = $isActive ? 'is-active' : '';
    $roleLabel = $isAdmin ? 'Admin' : 'Empleado';
?>
<div class="user-card-wrap"
     data-status="<?php echo $isActive ? 'activo' : 'inactivo'; ?>"
     data-role="<?php echo $u->role; ?>"
     data-name="<?php echo htmlspecialchars(strtolower($u->full_name . ' ' . $u->username), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="admin-user-card <?php echo $isActive ? '' : 'is-inactive'; ?>">
        <div class="admin-user-top">
            <div class="position-relative">
                <img src="<?php echo URLROOT; ?>/uploads/avatars/<?php echo htmlspecialchars($u->profile_picture ?? 'default.png', ENT_QUOTES, 'UTF-8'); ?>"
                     alt="<?php echo htmlspecialchars($u->full_name); ?>"
                     class="admin-avatar-circle <?php echo $avatarActiveClass; ?>"
                     style="width:56px;height:56px;"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';">
                <span class="admin-avatar-fallback <?php echo $fallbackClass . ' ' . $avatarActiveClass; ?>"
                      style="display:none;width:56px;height:56px;">
                    <?php echo $initials; ?>
                </span>
                <span class="position-absolute bottom-0 end-0 rounded-circle border border-white"
                      style="width:13px;height:13px;background:<?php echo $isActive ? 'var(--clr-success)' : '#9e9e9e'; ?>;"></span>
            </div>

            <div class="admin-user-meta">
                <p class="admin-user-name"><?php echo htmlspecialchars($u->full_name); ?></p>
                <div class="admin-user-handle">@<?php echo htmlspecialchars($u->username); ?></div>
                <div class="admin-badge-row">
                    <?php if (!empty($u->company_name)): ?>
                    <span class="admin-soft-badge is-muted" title="Empresa"><?php echo htmlspecialchars($u->company_name); ?></span>
                    <?php endif; ?>
                    <span class="admin-soft-badge <?php echo $isAdmin ? 'is-blue' : 'is-primary'; ?>"><?php echo $roleLabel; ?></span>
                    <span class="admin-soft-badge <?php echo $isActive ? 'is-success' : 'is-danger'; ?>"><?php echo $isActive ? 'Activo' : 'Inactivo'; ?></span>
                </div>
            </div>
        </div>

        <div class="admin-user-actions">
                <a href="<?php echo URLROOT; ?>/admin/employeeProfile/<?php echo $u->id; ?>"
                   class="btn btn-sm btn-outline-primary"
                   title="Ver ficha">
                    <i class="fas fa-address-card me-1"></i> Ficha
                </a>
                <a href="<?php echo URLROOT; ?>/admin/editUser/<?php echo $u->id; ?>"
                   class="btn btn-sm btn-primary"
                   title="Editar">
                    <i class="fas fa-pen me-1"></i> Editar
                </a>
                <?php if($isActive): ?>
                <form method="post" action="<?php echo URLROOT; ?>/admin/toggleUserStatus/<?php echo $u->id; ?>" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="admin-icon-btn is-danger toggle-status-btn" data-action="desactivar" title="Desactivar" aria-label="Desactivar usuario">
                        <i class="fas fa-user-slash"></i>
                    </button>
                </form>
                <?php else: ?>
                <form method="post" action="<?php echo URLROOT; ?>/admin/toggleUserStatus/<?php echo $u->id; ?>" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="admin-icon-btn is-success toggle-status-btn" data-action="activar" title="Activar" aria-label="Activar usuario">
                        <i class="fas fa-user-check"></i>
                    </button>
                </form>
                <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<div id="noUsersMsg" class="admin-empty d-none">
    <i class="fas fa-search"></i>
    Ningún usuario coincide con la búsqueda.
</div>

</div><!-- .admin-users-page -->

<script>
(function(){
    const cards   = document.querySelectorAll('.user-card-wrap');
    const search  = document.getElementById('userSearch');
    const filters = document.querySelectorAll('[data-filter]');
    const noMsg   = document.getElementById('noUsersMsg');
    let activeFilter = 'all';

    function applyFilters() {
        const q = search.value.toLowerCase().trim();
        let visible = 0;
        cards.forEach(c => {
            const matchFilter = activeFilter === 'all'
                || c.dataset.status === activeFilter
                || c.dataset.role   === activeFilter;
            const matchSearch  = !q || c.dataset.name.includes(q);
            const show = matchFilter && matchSearch;
            c.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        noMsg.classList.toggle('d-none', visible > 0);
    }

    filters.forEach(btn => {
        btn.addEventListener('click', () => {
            filters.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeFilter = btn.dataset.filter;
            applyFilters();
        });
    });

    search.addEventListener('input', applyFilters);

    // Suite P&M (vista Moderna): selector Sucursal dependiente de Ciudad.
    // Por ahora es de interfaz: filtrará la nómina cuando los usuarios
    // migrados tengan sucursal asignada (data-branch en cada tarjeta).
    const cityFilter   = document.getElementById('orgCityFilter');
    const branchFilter = document.getElementById('orgBranchFilter');
    if (cityFilter && branchFilter && window.ORG_BRANCHES_BY_CITY) {
        cityFilter.addEventListener('change', () => {
            const city = cityFilter.value;
            branchFilter.innerHTML = '<option value="" selected>Todas</option>';
            if (city && window.ORG_BRANCHES_BY_CITY[city]) {
                window.ORG_BRANCHES_BY_CITY[city].forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b;
                    opt.textContent = b;
                    branchFilter.appendChild(opt);
                });
                branchFilter.disabled = false;
                branchFilter.title = '';
            } else {
                branchFilter.disabled = true;
                branchFilter.title = 'Elegí primero una ciudad';
            }
            applyFilters();
        });
        branchFilter.addEventListener('change', applyFilters);
    }
})();
</script>

<?php require APPROOT . '/views/inc/footer.php'; ?>
