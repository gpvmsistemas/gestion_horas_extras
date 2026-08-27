<?php
require APPROOT . '/views/inc/header.php';
$v = $values ?? [];
$tab = $tab ?? 'general';
$tabs = [
    'general' => ['label' => 'General', 'icon' => 'fa-sliders-h'],
    'mail' => ['label' => 'Correo', 'icon' => 'fa-envelope'],
    'casapav' => ['label' => 'Casa Paviotti', 'icon' => 'fa-clipboard-list'],
    'attendance' => ['label' => 'Asistencia', 'icon' => 'fa-user-clock'],
    'integrations' => ['label' => 'Integraciones', 'icon' => 'fa-plug'],
    'overtime' => ['label' => 'Horas extras', 'icon' => 'fa-clock'],
    'salary_advance' => ['label' => 'Adelantos de sueldo', 'icon' => 'fa-hand-holding-usd'],
    'employee' => ['label' => 'Portal empleado', 'icon' => 'fa-users'],
    'security' => ['label' => 'Seguridad', 'icon' => 'fa-shield-alt'],
];
$mail = $mail_settings ?? null;
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1">Configuración del sistema</h1>
        <p class="text-muted small mb-0">Variables globales de la aplicación. Desbloqueado hasta <?php echo date('H:i', (int)$_SESSION['system_config_unlocked_until']); ?>.</p>
    </div>
    <form method="post" action="<?php echo URLROOT; ?>/systemConfig/lock" class="mb-0">
        <?php echo csrf_field(); ?>
        <button type="submit" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-lock me-1"></i>Bloquear
        </button>
    </form>
</div>

<ul class="nav nav-tabs mb-4 flex-nowrap overflow-auto">
    <?php foreach ($tabs as $key => $meta): ?>
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === $key ? 'active' : ''; ?>"
           href="<?php echo URLROOT; ?>/systemConfig?tab=<?php echo urlencode($key); ?>">
            <i class="fas <?php echo $meta['icon']; ?> me-1"></i><?php echo htmlspecialchars($meta['label']); ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<?php if ($tab === 'general'): ?>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="<?php echo URLROOT; ?>/systemConfig/save">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="group" value="general">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nombre del sitio</label>
                    <input type="text" name="sitename" class="form-control" value="<?php echo htmlspecialchars($v['sitename']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Empresa por defecto (altas)</label>
                    <input type="text" name="default_company_name" class="form-control" value="<?php echo htmlspecialchars($v['default_company_name']); ?>">
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="app_debug" id="app_debug" value="1" <?php echo $v['app_debug'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="app_debug">Modo depuración (errores en pantalla)</label>
                    </div>
                    <p class="text-danger small mb-0">Usar solo en desarrollo local.</p>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-4">Guardar</button>
        </form>
    </div>
</div>

<?php elseif ($tab === 'mail'): ?>
<?php if (!$mail): ?>
<div class="alert alert-warning">Ejecutá <code>migration_notifications_paystubs.sql</code> para habilitar correo.</div>
<?php else: ?>
<div class="d-flex flex-wrap justify-content-end gap-2 mb-3">
    <form method="post" action="<?php echo URLROOT; ?>/systemConfig/mailTest" class="d-flex flex-wrap gap-2">
        <?php echo csrf_field(); ?>
        <input type="email" name="test_email" class="form-control form-control-sm" style="min-width:200px"
               placeholder="Email de prueba" value="<?php echo htmlspecialchars($default_test_email ?? ''); ?>">
        <button type="submit" class="btn btn-outline-primary btn-sm">Probar envío</button>
    </form>
</div>
<?php if (!empty($mail_available)): ?>
<span class="badge bg-success mb-3">SMTP activo</span>
<?php else: ?>
<span class="badge bg-secondary mb-3">SMTP inactivo o incompleto</span>
<?php endif; ?>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="<?php echo URLROOT; ?>/systemConfig/save">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="group" value="mail">
            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" name="is_enabled" id="is_enabled" value="1"
                    <?php echo $mail && (int)$mail->is_enabled ? 'checked' : ''; ?>>
                <label class="form-check-label" for="is_enabled">Activar envío por correo</label>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Servidor SMTP</label>
                    <input type="text" name="smtp_host" class="form-control" value="<?php echo htmlspecialchars($mail->smtp_host ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Puerto</label>
                    <input type="number" name="smtp_port" class="form-control" value="<?php echo (int)($mail->smtp_port ?? 587); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cifrado</label>
                    <select name="smtp_encryption" class="form-select">
                        <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'Ninguno'] as $k => $lbl): ?>
                        <option value="<?php echo $k; ?>" <?php echo ($mail->smtp_encryption ?? 'tls') === $k ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Usuario SMTP</label>
                    <input type="text" name="smtp_user" class="form-control" value="<?php echo htmlspecialchars($mail->smtp_user ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="smtp_password" class="form-control" placeholder="Vacío = no cambiar" autocomplete="new-password">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email remitente</label>
                    <input type="email" name="from_email" class="form-control" value="<?php echo htmlspecialchars($mail->from_email ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nombre remitente</label>
                    <input type="text" name="from_name" class="form-control" value="<?php echo htmlspecialchars($mail->from_name ?? app_name()); ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-4">Guardar</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php elseif ($tab === 'casapav'): ?>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="<?php echo URLROOT; ?>/systemConfig/save">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="group" value="casapav">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Cantidad de extintos en el select</label>
                    <input type="number" name="cp_deceased_list_limit" class="form-control" min="10" max="100"
                           value="<?php echo (int)$v['cp_deceased_list_limit']; ?>">
                    <div class="form-text">Lista <code>#cp_deceased_select</code> (10–100).</div>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch mt-4 pt-2">
                        <input class="form-check-input" type="checkbox" name="cp_duplicate_check_enabled" value="1"
                            <?php echo $v['cp_duplicate_check_enabled'] ? 'checked' : ''; ?>>
                        <label class="form-check-label">Validar tareas duplicadas por extinto</label>
                    </div>
                </div>
                <div class="col-12"><hr><h2 class="h6">Base de datos extintos</h2></div>
                <div class="col-md-6">
                    <label class="form-label">Host</label>
                    <input type="text" name="extintos_db_host" class="form-control" value="<?php echo htmlspecialchars($v['extintos_db_host']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Base de datos</label>
                    <input type="text" name="extintos_db_name" class="form-control" value="<?php echo htmlspecialchars($v['extintos_db_name']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Usuario</label>
                    <input type="text" name="extintos_db_user" class="form-control" value="<?php echo htmlspecialchars($v['extintos_db_user']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="extintos_db_pass" class="form-control" placeholder="Vacío = no cambiar" autocomplete="new-password">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tabla sepelio (armar, realizar…)</label>
                    <input type="text" name="cp_extintos_table_sepulio" class="form-control" value="<?php echo htmlspecialchars($v['cp_extintos_table_sepulio']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tabla tanatopraxia</label>
                    <input type="text" name="cp_extintos_table_tanato" class="form-control" value="<?php echo htmlspecialchars($v['cp_extintos_table_tanato']); ?>">
                </div>
                <div class="col-12"><hr><h2 class="h6">Cierre y visibilidad admin</h2></div>
                <div class="col-md-4">
                    <label class="form-label">Recargo al cierre (%)</label>
                    <input type="number" name="cp_closure_markup_pct" class="form-control" step="0.1" min="0" max="100"
                           value="<?php echo htmlspecialchars($v['cp_closure_markup_pct']); ?>">
                    <div class="form-text">Se suma al importe cargado por empleado (ej. 19,5 → $100.000 + $19.500).</div>
                </div>
                <div class="col-md-8">
                    <div class="form-check form-switch mt-4 pt-2">
                        <input class="form-check-input" type="checkbox" name="cp_extras_visible_admin" value="1"
                            <?php echo !empty($v['cp_extras_visible_admin']) ? 'checked' : ''; ?>>
                        <label class="form-check-label">Administrador RRHH ve menú Extras CP</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="cp_extras_visible_supervisor" value="1"
                            <?php echo !empty($v['cp_extras_visible_supervisor']) ? 'checked' : ''; ?>>
                        <label class="form-check-label">Encargado / supervisor ve menú Extras CP</label>
                    </div>
                    <p class="small text-muted mb-0">La visibilidad por empresa y área se configura en Empresas y Capacitación → Áreas.</p>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-4">Guardar</button>
        </form>
    </div>
</div>

<?php elseif ($tab === 'attendance'): ?>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="<?php echo URLROOT; ?>/systemConfig/save">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="group" value="attendance">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Tolerancia llegada tarde (minutos)</label>
                    <input type="number" name="attendance_late_tolerance_min" class="form-control" min="0" max="120"
                           value="<?php echo (int)$v['attendance_late_tolerance_min']; ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tolerancia salida anticipada (minutos)</label>
                    <input type="number" name="attendance_early_leave_tolerance_min" class="form-control" min="0" max="120"
                           value="<?php echo (int)$v['attendance_early_leave_tolerance_min']; ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-4">Guardar</button>
        </form>
    </div>
</div>

<?php elseif ($tab === 'integrations'): ?>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="<?php echo URLROOT; ?>/systemConfig/save">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="group" value="integrations">
            <h2 class="h6 mb-3">API Relojes</h2>
            <div class="row g-3 mb-4">
                <div class="col-md-12">
                    <label class="form-label">URL base</label>
                    <input type="url" name="clock_api_base_url" class="form-control" value="<?php echo htmlspecialchars($v['clock_api_base_url']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email API</label>
                    <input type="text" name="clock_api_email" class="form-control" value="<?php echo htmlspecialchars($v['clock_api_email']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contraseña API</label>
                    <input type="password" name="clock_api_password" class="form-control" placeholder="Vacío = no cambiar" autocomplete="new-password">
                </div>
            </div>
            <?php if (!function_exists('org_is_moderna') || !org_is_moderna()): ?>
            <h2 class="h6 mb-3">Ecofarma</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Obra social por defecto</label>
                    <input type="text" name="ecofarma_default_obra_social" class="form-control" value="<?php echo htmlspecialchars($v['ecofarma_default_obra_social']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Comisión % por defecto</label>
                    <input type="number" name="ecofarma_default_comision_pct" class="form-control" min="0" max="100"
                           value="<?php echo (int)$v['ecofarma_default_comision_pct']; ?>">
                </div>
            </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary mt-4">Guardar</button>
        </form>
    </div>
</div>

<?php elseif ($tab === 'overtime'): ?>
<div class="card shadow-sm">
    <div class="card-body">
        <p class="text-muted small mb-3">
            Permisos por <strong>rol</strong> en el panel RRHH. La visibilidad por <strong>empresa</strong> se edita en Admin → Empresas;
            por <strong>área</strong> en Capacitación → Áreas. Los empleados además respetan la pestaña Portal empleado.
        </p>
        <form method="post" action="<?php echo URLROOT; ?>/systemConfig/save">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="group" value="overtime">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="overtime_visible_admin" id="overtime_visible_admin" value="1"
                    <?php echo setting_bool('overtime_visible_admin', true) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="overtime_visible_admin">
                    <strong>Administrador RRHH</strong>
                    <span class="d-block small text-muted">Dashboard, cierres, planificador y ficha empleado (horas 50%/100%).</span>
                </label>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="overtime_visible_supervisor" id="overtime_visible_supervisor" value="1"
                    <?php echo setting_bool('overtime_visible_supervisor', true) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="overtime_visible_supervisor">
                    <strong>Supervisor / encargado de área</strong>
                    <span class="d-block small text-muted">Mismo módulo, acotado al área asignada al supervisor.</span>
                </label>
            </div>
            <p class="small text-muted mb-0">Empleados: configuración en pestaña <em>Portal empleado</em> + empresa/área del usuario.</p>
            <button type="submit" class="btn btn-primary mt-4">Guardar</button>
        </form>
    </div>
</div>

<?php elseif ($tab === 'salary_advance'): ?>
<div class="card shadow-sm">
    <div class="card-body">
        <p class="text-muted small mb-3">
            Reglas del módulo de adelantos de sueldo. La visibilidad en el portal del empleado se configura en la pestaña
            <a href="<?php echo URLROOT; ?>/systemConfig?tab=employee">Portal empleado</a> (<code>employee_show_salary_advance</code>).
        </p>
        <?php if (!function_exists('salary_advance_is_ready') || !salary_advance_is_ready()): ?>
        <div class="alert alert-warning small">
            Ejecutá <code>migration_salary_advances.sql</code> y <code>migration_salary_advance_settings.sql</code> (ver MIGRATIONS.md #38).
        </div>
        <?php endif; ?>
        <form method="post" action="<?php echo URLROOT; ?>/systemConfig/save">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="group" value="salary_advance">
            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" name="salary_advance_enabled" id="salary_advance_enabled" value="1"
                    <?php echo !empty($v['salary_advance_enabled']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="salary_advance_enabled">
                    <strong>Módulo habilitado</strong>
                    <span class="d-block small text-muted">Activa adelantos en admin y empleados.</span>
                </label>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Solicitudes anuales máximas</label>
                    <input type="number" name="salary_advance_max_annual" class="form-control" min="1" max="12"
                           value="<?php echo (int)$v['salary_advance_max_annual']; ?>">
                    <div class="form-text">Pendientes + aprobadas por año calendario.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Monto mínimo (pesos)</label>
                    <input type="number" name="salary_advance_min_amount" class="form-control" min="0"
                           value="<?php echo (int)$v['salary_advance_min_amount']; ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cuotas máximas (empleado al solicitar)</label>
                    <input type="number" name="salary_advance_max_installments_employee" class="form-control" min="1" max="24"
                           value="<?php echo (int)$v['salary_advance_max_installments_employee']; ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cuotas máximas (RRHH al aprobar con override)</label>
                    <input type="number" name="salary_advance_max_installments_hr" class="form-control" min="1" max="24"
                           value="<?php echo (int)$v['salary_advance_max_installments_hr']; ?>">
                </div>
            </div>
            <div class="form-check form-switch mt-4 mb-3">
                <input class="form-check-input" type="checkbox" name="salary_advance_one_pending_only" id="salary_advance_one_pending_only" value="1"
                    <?php echo !empty($v['salary_advance_one_pending_only']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="salary_advance_one_pending_only">Solo una solicitud pendiente por empleado</label>
            </div>
            <button type="submit" class="btn btn-primary mt-2">Guardar</button>
        </form>
    </div>
</div>

<?php elseif ($tab === 'employee'): ?>
<div class="card shadow-sm">
    <div class="card-body">
        <p class="text-muted small">Controla qué ven los empleados en <code>/employee/index</code>, menú lateral y app móvil. Si desactivás un módulo, la ruta directa también queda bloqueada.</p>
        <form method="post" action="<?php echo URLROOT; ?>/systemConfig/save">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="group" value="employee">
            <?php foreach (employee_portal_all_settings() as $key => $meta): ?>
            <div class="form-check form-switch mb-3 pb-2 border-bottom">
                <input class="form-check-input" type="checkbox" name="<?php echo htmlspecialchars($key); ?>" id="<?php echo htmlspecialchars($key); ?>" value="1"
                    <?php echo setting_bool($key, true) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="<?php echo htmlspecialchars($key); ?>">
                    <strong><?php echo htmlspecialchars($meta['label']); ?></strong>
                    <span class="d-block small text-muted"><?php echo htmlspecialchars($meta['hint']); ?></span>
                </label>
            </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary mt-2">Guardar</button>
        </form>
    </div>
</div>

<?php elseif ($tab === 'security'): ?>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="<?php echo URLROOT; ?>/systemConfig/save">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="group" value="security">
            <p class="text-muted small">Cambiá la clave que se pide al entrar a este panel.</p>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Clave actual</label>
                    <input type="password" name="current_pin" class="form-control" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nueva clave</label>
                    <input type="password" name="new_pin" class="form-control" autocomplete="new-password">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Confirmar nueva clave</label>
                    <input type="password" name="new_pin_confirm" class="form-control" autocomplete="new-password">
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-4">Cambiar clave</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require APPROOT . '/views/inc/footer.php'; ?>
