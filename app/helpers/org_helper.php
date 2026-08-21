<?php
// ----------------------------------------------------------------------
// ARCHIVO: app/helpers/org_helper.php
// Organización del usuario (Paviotti/Ecofarma vs Moderna) — "Suite P&M".
//
// La fuente definitiva será users.employee_group (rama LAUTARO), expuesta
// en sesión al loguear como $_SESSION['user_employee_group']. Mientras esa
// migración no esté aplicada, el staff puede SIMULAR la organización con
// un selector en la barra superior ($_SESSION['sim_employee_group']).
//
// FASE ACTUAL del módulo Registro de Horas: maquetas estáticas (sin tablas
// nuevas ni escrituras). La taxonomía Moderna replica constants.py de
// hoursapp-moderna y pasará a `areas` (empresa Moderna) al integrar la base.
// ----------------------------------------------------------------------

/** Grupos válidos (mismo enum que users.employee_group en la rama LAUTARO). */
function org_valid_groups() {
    return ['paviotti', 'moderna'];
}

/** La columna companies.organization_group (migración de Lautaro) ya existe. */
function org_company_group_ready() {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $db = new Database();
        $db->query("SHOW COLUMNS FROM companies LIKE 'organization_group'");
        $ready = (bool)$db->single();
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

/** organization_group de la empresa activa en sesión ('' si no se puede resolver). */
function org_active_company_group() {
    $companyId = (int)($_SESSION['user_company_id'] ?? 0);
    if ($companyId <= 0 || !org_company_group_ready()) {
        return '';
    }
    static $cache = [];
    if (!array_key_exists($companyId, $cache)) {
        try {
            $db = new Database();
            $db->query('SELECT organization_group FROM companies WHERE id = ? LIMIT 1');
            $row = $db->single([$companyId]);
            $cache[$companyId] = $row ? (string)$row->organization_group : '';
        } catch (Throwable $e) {
            $cache[$companyId] = '';
        }
    }
    return $cache[$companyId];
}

/**
 * Organización efectiva del usuario logueado.
 * Prioridad: users.employee_group real en sesión > organization_group de la
 * empresa activa (selector de Contexto de la suite) > simulador demo > 'paviotti'.
 */
function org_current_group() {
    if (!empty($_SESSION['user_employee_group'])
        && in_array($_SESSION['user_employee_group'], org_valid_groups(), true)) {
        return $_SESSION['user_employee_group'];
    }
    if (function_exists('isStaffAdmin') && isStaffAdmin()) {
        $companyGroup = org_active_company_group();
        if (in_array($companyGroup, org_valid_groups(), true)) {
            return $companyGroup;
        }
        if (!empty($_SESSION['sim_employee_group'])
            && in_array($_SESSION['sim_employee_group'], org_valid_groups(), true)) {
            return $_SESSION['sim_employee_group'];
        }
    }
    return 'paviotti';
}

function org_is_moderna() {
    return org_current_group() === 'moderna';
}

/** El simulador solo aplica mientras no exista el dato real en sesión. */
function org_simulator_available() {
    return function_exists('isStaffAdmin') && isStaffAdmin()
        && empty($_SESSION['user_employee_group']);
}

function org_set_simulated_group($group) {
    $group = in_array($group, org_valid_groups(), true) ? $group : 'paviotti';
    $_SESSION['sim_employee_group'] = $group;
    return $group;
}

/** Registro de Horas: módulo compartido — lo usan ambas organizaciones. */
function org_hours_registry_enabled() {
    return function_exists('isStaffAdmin') && isStaffAdmin();
}

/** Moderna no usa el módulo de recibos de sueldo (alcance confirmado). */
function org_hides_pay_stubs() {
    return org_is_moderna();
}

/** Moderna no ve los módulos de negocio propios de Paviotti (Ecofarma/CP/PRODE). */
function org_hides_paviotti_business_modules() {
    return org_is_moderna();
}

/** Nombre de marca según organización (Moderna pisa la marca por empresa). */
function org_brand_name($fallback) {
    return org_is_moderna() ? 'Moderna' : $fallback;
}

function org_brand_subtitle($fallback) {
    return org_is_moderna() ? 'Red Farmacias · Suite P&M' : $fallback;
}

function org_display_label($group = null) {
    $group = $group ?: org_current_group();
    return $group === 'moderna' ? 'Moderna' : 'Paviotti/Ecofarma';
}

/**
 * Id de la empresa "Moderna" en companies (null si aún no existe).
 * Al entrar a la vista Moderna, la empresa activa de sesión pasa a esta,
 * de modo que TODAS las pantallas de la suite (usuarios, turnos, feriados,
 * solicitudes, dashboard...) quedan aisladas de los datos de Paviotti.
 */
function org_moderna_company_id() {
    static $id = -1;
    if ($id !== -1) {
        return $id;
    }
    try {
        $db = new Database();
        if (org_company_group_ready()) {
            $db->query("SELECT id FROM companies WHERE organization_group = 'moderna' ORDER BY id LIMIT 1");
        } else {
            $db->query("SELECT id FROM companies WHERE name = 'Moderna' LIMIT 1");
        }
        $row = $db->single();
        $id = $row ? (int)$row->id : null;
    } catch (Throwable $e) {
        $id = null;
    }
    return $id;
}

/** Clase extra de <body> que activa el tema visual Moderna (style.css). */
function org_body_class() {
    return org_is_moderna() ? 'org-moderna' : '';
}

/** Logo según organización (Moderna usa el logo de hoursapp). */
function org_brand_logo_url($fallback) {
    return org_is_moderna() ? (URLROOT . '/img/moderna-logo.png') : $fallback;
}

// ----------------------------------------------------------------------
// Taxonomía de sucursales por organización (fase maqueta)
// ----------------------------------------------------------------------

/**
 * Moderna — Ciudad => sucursales. Fuente: hoursapp-moderna/app/constants.py
 * (taxonomía definitiva confirmada por RRHH de Moderna).
 */
function moderna_branches_by_city() {
    return [
        'Villa María' => [
            'Farmacia Moderna 1', 'Farmacia Moderna 2', 'Farmacia Moderna 7', 'Farmacia Moderna 8',
            'Farmacia Marañon', 'Farmacia del Subnivel', 'Farmacia del Condor',
            'Farmacia Palermo', 'Farmacia Plaza', 'Marcellino', 'Farmacia Domenino',
            'Callcenter', 'Administración', 'Obras Sociales', 'Sistemas', 'Recursos Humanos',
            'Drogueria Central', 'Distribuidora FCF', 'Comercial y MKT',
        ],
        'Córdoba'       => ['Farmacia Moderna 5', 'Farmacia Oulton'],
        'Bell Ville'    => ['Farmacia Moderna 4', 'Farmacia Moderna 11', 'Farmacia Alladio', 'Farmacia Muscatelo'],
        'Marcos Juarez' => ['Farmacia Moderna 3', 'Farmacia Moderna 10'],
        'Leones'        => ['Farmacia Novofarma'],
        'La Carlota'    => ['Farmacia Moderna 9'],
        'Villa Dolores' => ['Santa Teresita', 'General Paz'],
        'Saira'         => ['Farmacia Moderna 12'],
    ];
}

/** Moderna — sucursales 24 h (cruce de medianoche en grillas/reportes). */
function moderna_branches_24h() {
    return [
        'Marcellino',
        'Farmacia Moderna 4', 'Farmacia Alladio', 'Farmacia Moderna 11', 'Farmacia Muscatelo',
        'Farmacia Novofarma',
    ];
}

/**
 * Paviotti/Ecofarma — sucursales de ejemplo para la maqueta, agrupadas por
 * empresa (nombres inspirados en los dispositivos de reloj ya sincronizados).
 * Al integrar, saldrán de companies/areas reales.
 */
function paviotti_sample_branches_by_group() {
    return [
        'Ecofarma' => ['Ecofarma Central', 'Cruz V Centro', 'Cruz V Catedral', 'Cruz V Jujuy'],
        'Servicios Sociales' => ['Sede Sociales'],
        'A.M.S.S.I' => ['Oficina AMSSI'],
        'La Naturaleza' => ['Administración La Naturaleza'],
    ];
}

/** Sucursales agrupadas según la organización efectiva. */
function org_branches_by_city($group = null) {
    $group = $group ?: org_current_group();
    return $group === 'moderna' ? moderna_branches_by_city() : paviotti_sample_branches_by_group();
}

/** Empleados de ejemplo por organización (datos ficticios, no reales). */
function org_sample_employees($group = null) {
    $group = $group ?: org_current_group();
    if ($group === 'moderna') {
        return [
            (object)['id' => 1, 'name' => 'Gutiérrez, Ana',    'branch' => 'Farmacia Moderna 1', 'city' => 'Villa María', 'state' => 'Activo',     'cajero' => true,  'manager' => false],
            (object)['id' => 2, 'name' => 'Díaz, Bruno',       'branch' => 'Farmacia Moderna 1', 'city' => 'Villa María', 'state' => 'Activo',     'cajero' => false, 'manager' => true],
            (object)['id' => 3, 'name' => 'Suárez, Carla',     'branch' => 'Marcellino',         'city' => 'Villa María', 'state' => 'Activo',     'cajero' => true,  'manager' => false],
            (object)['id' => 4, 'name' => 'Moyano, Diego',     'branch' => 'Marcellino',         'city' => 'Villa María', 'state' => 'Licencia',   'cajero' => false, 'manager' => false],
            (object)['id' => 5, 'name' => 'Ferreyra, Elena',   'branch' => 'Farmacia Oulton',    'city' => 'Córdoba',     'state' => 'Activo',     'cajero' => true,  'manager' => false],
            (object)['id' => 6, 'name' => 'Ríos, Facundo',     'branch' => 'Farmacia Alladio',   'city' => 'Bell Ville',  'state' => 'Vacaciones', 'cajero' => false, 'manager' => false],
            (object)['id' => 7, 'name' => 'Ledesma, Gabriela', 'branch' => 'Farmacia Novofarma', 'city' => 'Leones',      'state' => 'Activo',     'cajero' => true,  'manager' => true],
            (object)['id' => 8, 'name' => 'Peralta, Hernán',   'branch' => 'Farmacia Moderna 9', 'city' => 'La Carlota',  'state' => 'Activo',     'cajero' => false, 'manager' => false],
        ];
    }
    return [
        (object)['id' => 1, 'name' => 'Acosta, Julieta',   'branch' => 'Ecofarma Central',            'city' => 'Ecofarma',           'state' => 'Activo',     'cajero' => true,  'manager' => false],
        (object)['id' => 2, 'name' => 'Benítez, Marcos',   'branch' => 'Ecofarma Central',            'city' => 'Ecofarma',           'state' => 'Activo',     'cajero' => false, 'manager' => true],
        (object)['id' => 3, 'name' => 'Córdoba, Natalia',  'branch' => 'Cruz V Centro',               'city' => 'Ecofarma',           'state' => 'Activo',     'cajero' => true,  'manager' => false],
        (object)['id' => 4, 'name' => 'Domínguez, Óscar',  'branch' => 'Cruz V Catedral',             'city' => 'Ecofarma',           'state' => 'Licencia',   'cajero' => false, 'manager' => false],
        (object)['id' => 5, 'name' => 'Espíndola, Paula',  'branch' => 'Cruz V Jujuy',                'city' => 'Ecofarma',           'state' => 'Activo',     'cajero' => true,  'manager' => false],
        (object)['id' => 6, 'name' => 'Figueroa, Ramiro',  'branch' => 'Sede Sociales',               'city' => 'Servicios Sociales', 'state' => 'Vacaciones', 'cajero' => false, 'manager' => false],
        (object)['id' => 7, 'name' => 'Giménez, Sofía',    'branch' => 'Oficina AMSSI',               'city' => 'A.M.S.S.I',          'state' => 'Activo',     'cajero' => false, 'manager' => true],
        (object)['id' => 8, 'name' => 'Herrera, Tomás',    'branch' => 'Administración La Naturaleza','city' => 'La Naturaleza',      'state' => 'Activo',     'cajero' => false, 'manager' => false],
    ];
}

/** Badge Bootstrap para el estado de un empleado de ejemplo. */
function org_state_badge_class($state) {
    switch ($state) {
        case 'Activo':     return 'bg-success';
        case 'Vacaciones': return 'bg-info text-dark';
        case 'Licencia':   return 'bg-warning text-dark';
        case 'Guardia':    return 'bg-primary';
        default:           return 'bg-secondary';
    }
}
