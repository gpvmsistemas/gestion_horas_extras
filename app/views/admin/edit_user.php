<?php require APPROOT . '/views/inc/header.php'; ?>

<?php
$editUser = $data['user'];
$editRoleLabels = ['admin' => 'Administrador', 'supervisor' => 'Supervisor', 'empleado' => 'Empleado'];
$editRoleLabel = $editRoleLabels[$editUser->role] ?? ucfirst((string)$editUser->role);
$editCompanyLabel = !empty($data['current_company_name']) ? $data['current_company_name'] : 'Sin empresa asignada';
?>

<div class="edit-user-page">
    <header class="edit-user-hero">
        <div class="edit-user-hero-main">
            <a href="<?php echo URLROOT; ?>/admin/users" class="edit-user-back" aria-label="Volver al listado de usuarios">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <div class="edit-user-eyebrow">Legajo digital · ID <?php echo (int)$editUser->id; ?></div>
                <h1><?php echo htmlspecialchars($editUser->full_name); ?></h1>
                <div class="edit-user-meta">
                    <span><i class="fas fa-building"></i><?php echo htmlspecialchars($editCompanyLabel); ?></span>
                    <span><i class="fas fa-shield-alt"></i><?php echo htmlspecialchars($editRoleLabel); ?></span>
                    <span><i class="fas fa-at"></i><?php echo htmlspecialchars($editUser->username); ?></span>
                </div>
            </div>
        </div>
        <a href="<?php echo URLROOT; ?>/admin/employeeProfile/<?php echo (int)$editUser->id; ?>" class="btn btn-light edit-user-profile-link">
            <i class="fas fa-address-card me-2"></i>Ver ficha
        </a>
    </header>

    <form action="<?php echo URLROOT; ?>/admin/editUser/<?php echo (int)$editUser->id; ?>" method="post" enctype="multipart/form-data" autocomplete="off" class="edit-user-form">
        <?php echo csrf_field(); ?>
        <aside class="edit-user-aside" aria-label="Resumen del usuario">
            <div class="edit-user-identity-card">
                <div class="edit-user-avatar-wrap">
                        <img id="avatarPreview"
                             src="<?php echo htmlspecialchars(avatar_url($editUser->profile_picture ?? '')); ?>"
                             alt="Foto de perfil de <?php echo htmlspecialchars($editUser->full_name); ?>"
                             class="edit-user-avatar"
                             onerror="this.onerror=null;this.src='<?php echo htmlspecialchars(avatar_default_url(), ENT_QUOTES); ?>';">
                    <span class="edit-user-avatar-status <?php echo empty($editUser->is_active) ? 'is-inactive' : ''; ?>" title="Usuario <?php echo !empty($editUser->is_active) ? 'activo' : 'inactivo'; ?>"></span>
                </div>
                <strong><?php echo htmlspecialchars($editUser->full_name); ?></strong>
                <span class="edit-user-role-pill"><?php echo htmlspecialchars($editRoleLabel); ?></span>
                <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg,image/png,image/webp"
                       class="visually-hidden <?php echo isset($data['errors']['picture']) ? 'is-invalid' : ''; ?>">
                <label for="profile_picture" class="edit-user-upload-label"><i class="fas fa-camera"></i>Cambiar foto</label>
                <small>JPG, PNG o WEBP · máximo 2 MB</small>
                <?php if(isset($data['errors']['picture'])): ?>
                    <div class="text-danger small mt-2" role="alert"><?php echo htmlspecialchars($data['errors']['picture']); ?></div>
                <?php endif; ?>
            </div>

            <nav class="edit-user-section-nav" aria-label="Secciones del formulario">
                <a href="#datos-personales"><i class="fas fa-user"></i><span>Datos personales</span></a>
                <a href="#organizacion"><i class="fas fa-sitemap"></i><span>Organización</span></a>
                <a href="#acceso"><i class="fas fa-key"></i><span>Acceso y rol</span></a>
                <a href="#configuracion-laboral"><i class="fas fa-briefcase"></i><span>Configuración laboral</span></a>
                <a href="#relojes"><i class="fas fa-fingerprint"></i><span>Relojes</span></a>
            </nav>

            <button type="submit" class="btn btn-primary edit-user-save-aside">
                <i class="fas fa-check me-2"></i>Guardar cambios
            </button>
        </aside>

        <div class="edit-user-content">
            <section class="edit-user-section" id="datos-personales">
                <div class="edit-user-section-heading">
                    <span class="edit-user-section-icon"><i class="fas fa-user"></i></span>
                    <div><span>Identidad</span><h2>Datos personales</h2><p>Información básica y medios de contacto del empleado.</p></div>
                </div>
                <div class="edit-user-section-body">
                    <h6 class="mb-3 text-muted">Información principal</h6>
                    <?php
                    $nameSource = isset($data['user']) ? $data['user'] : null;
                    if (!empty($data['first_name']) || !empty($data['last_name'])) {
                        $nameSource = $data;
                    }
                    require APPROOT . '/views/admin/partials/user_name_fields.php';
                    ?>

                    <div class="mb-3">
                        <label class="form-label">Usuario de acceso</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($data['user']->username); ?>" disabled>
                        <small class="text-muted">El nombre de usuario no se modifica desde aquí.</small>
                    </div>

                    <?php
                    $profileExtendedReady = (new User())->isProfileExtendedReady();
                    $profileSource = $data['user'];
                    require APPROOT . '/views/admin/partials/user_profile_fields.php';
                    ?>
                </div>
            </section>

            <section class="edit-user-section" id="organizacion">
                <div class="edit-user-section-heading">
                    <span class="edit-user-section-icon"><i class="fas fa-sitemap"></i></span>
                    <div><span>Asignación</span><h2>Organización</h2><p>Empresa, sucursales, grupo y área de pertenencia.</p></div>
                </div>
                <div class="edit-user-section-body">
                    <h6 class="mb-2 text-muted"><i class="fas fa-building me-1"></i> Empresa</h6>
                    <?php
                    $selectedCompanyId = (int)($data['user']->company_id ?? 0);
                    $currentCompanyLabel = !empty($data['current_company_name'])
                        ? $data['current_company_name']
                        : 'Sin empresa asignada';
                    ?>
                    <p class="small text-muted mb-2">
                        Actual: <strong><?php echo htmlspecialchars($currentCompanyLabel); ?></strong>.
                        Los empleados solo intercambian turnos con compañeros de la misma empresa.
                    </p>
                    <?php if (empty($data['companies'])): ?>
                    <div class="alert alert-warning small mb-3">
                        No hay empresas en el sistema. Ejecutá <code>migration_companies_grupo.sql</code> en MySQL o
                        <a href="<?php echo URLROOT; ?>/admin/companies">creá empresas aquí</a>.
                    </div>
                    <?php else: ?>
                    <div class="mb-3">
                        <label for="company_id" class="form-label">Asignar / cambiar empresa <span class="text-danger">*</span></label>
                        <select name="company_id" id="company_id" class="form-select <?php echo isset($data['errors']['company_id']) ? 'is-invalid' : ''; ?>" required>
                            <option value="">— Seleccioná empresa —</option>
                            <?php foreach ($data['companies'] as $co): ?>
                            <option value="<?php echo (int)$co->id; ?>" <?php echo $selectedCompanyId === (int)$co->id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($co->name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($data['errors']['company_id'])): ?>
                        <div class="invalid-feedback d-block"><?php echo htmlspecialchars($data['errors']['company_id']); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php require APPROOT . '/views/admin/partials/user_branch_field.php'; ?>
                    <?php require APPROOT . '/views/admin/partials/user_attendance_control_field.php'; ?>

                    <div class="mb-3">
                        <label for="employee_group" class="form-label">Grupo organizacional <span class="text-danger">*</span></label>
                        <?php $selectedGroup = User::normalizeOrganizationGroup($data['user']->employee_group ?? 'paviotti'); ?>
                        <select name="employee_group" id="employee_group" class="form-select" required>
                            <?php foreach (User::organizationGroupOptions() as $groupKey => $groupLabel): ?>
                            <option value="<?php echo htmlspecialchars($groupKey); ?>" <?php echo $selectedGroup === $groupKey ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($groupLabel); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Sirve para segmentar comunicaciones y no reemplaza la empresa ni el área.</small>
                    </div>

                    <?php require APPROOT . '/views/admin/partials/user_area_field.php'; ?>
                </div>
            </section>

            <section class="edit-user-section" id="acceso">
                <div class="edit-user-section-heading">
                    <span class="edit-user-section-icon"><i class="fas fa-key"></i></span>
                    <div><span>Seguridad</span><h2>Acceso y rol</h2><p>Permisos del sistema y actualización opcional de contraseña.</p></div>
                </div>
                <div class="edit-user-section-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Rol</label>
                            <select name="role" id="role" class="form-select">
                                <option value="empleado" <?php if($data['user']->role == 'empleado') echo 'selected'; ?>>Empleado</option>
                                <option value="supervisor" <?php if($data['user']->role == 'supervisor') echo 'selected'; ?>>Supervisor (jefe de área)</option>
                                <option value="admin" <?php if($data['user']->role == 'admin') echo 'selected'; ?>>Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Nueva Contraseña</label>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Dejar en blanco para no cambiar" autocomplete="new-password">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirmar Contraseña</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" autocomplete="new-password">
                        </div>
                    </div>
                </div>
            </section>

            <section class="edit-user-section" id="configuracion-laboral">
                <div class="edit-user-section-heading">
                    <span class="edit-user-section-icon"><i class="fas fa-briefcase"></i></span>
                    <div><span>Relación laboral</span><h2>Configuración laboral</h2><p>Jornada, convenio, legajo ampliado, domicilio y cobertura.</p></div>
                </div>
                <div class="edit-user-section-body">
                    <h6 class="mb-3 text-muted">Configuración de Horarios</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="hourly_rate" class="form-label">Tarifa por Hora</label>
                            <input type="number" step="0.01" name="hourly_rate" id="hourly_rate" class="form-control" value="<?php echo htmlspecialchars($data['user']->hourly_rate); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="weekly_hour_limit" class="form-label">Límite de Horas Semanales</label>
                            <input type="number" step="0.25" name="weekly_hour_limit" id="weekly_hour_limit" class="form-control" value="<?php echo htmlspecialchars($data['user']->weekly_hour_limit); ?>">
                            <small class="form-text text-muted">Para cálculo de H. Extras (ej. 44).</small>
                        </div>
                    </div>

                    <?php
                    $source = isset($data['user']) ? $data['user'] : $data;
                    if (!empty($data['probation_start_date']) || !empty($data['hire_date'])) {
                        $source = (object)array_merge((array)$source, [
                            'probation_start_date' => $data['probation_start_date'] ?? '',
                            'hire_date' => $data['hire_date'] ?? '',
                            'agreement_id' => $data['agreement_id'] ?? 0,
                        ]);
                    }
                    require APPROOT . '/views/admin/partials/user_employment_fields.php';
                    ?>

                    <?php require APPROOT . '/views/admin/partials/user_complete_record_fields.php'; ?>
                </div>
            </section>

            <section class="edit-user-section" id="relojes">
                <div class="edit-user-section-heading">
                    <span class="edit-user-section-icon"><i class="fas fa-fingerprint"></i></span>
                    <div><span>Integraciones</span><h2>Relojes y operadores</h2><p>Vinculaciones externas utilizadas para marcaciones y comisiones.</p></div>
                </div>
                <div class="edit-user-section-body">
                    <h6 class="mb-1 text-muted">IDs de Empleado en Relojes</h6>
                    <p class="text-muted small mb-3">
                        Los IDs se gestionan desde la pantalla de mapeo al consultar la API.
                    </p>
                    <?php if (!empty($data['clock_mappings'])): ?>
                    <ul class="list-group list-group-flush mb-2">
                        <?php foreach ($data['clock_mappings'] as $clockName => $clockId): ?>
                        <li class="list-group-item d-flex align-items-center gap-2 px-0 py-1 border-0">
                            <i class="fas fa-circle-check" style="color:var(--clr-primary);"></i>
                            <strong><?php echo htmlspecialchars($clockName); ?></strong>:
                            <code><?php echo htmlspecialchars($clockId); ?></code>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="text-warning small"><i class="fas fa-exclamation-triangle me-1"></i>Sin IDs asignados.</p>
                    <?php endif; ?>
                    <a href="<?php echo URLROOT; ?>/admin/mapeoApi" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-link me-1"></i> Gestionar mapeos en la página de mapeo
                    </a>

                    <?php if (!empty($data['user']) && (new User())->isPlexOperatorReady()): ?>
                    <hr class="my-4">
                    <h6 class="mb-3 text-muted">Ecofarma — operador API</h6>
                    <div class="mb-3">
                        <label for="plex_operator_name" class="form-label">Nombre operador (resumen comisiones)</label>
                        <input type="text" name="plex_operator_name" id="plex_operator_name" class="form-control"
                               value="<?php echo htmlspecialchars($data['user']->plex_operator_name ?? ''); ?>"
                               placeholder="Como figura en la API de Ecofarma">
                        <div class="form-text">Debe coincidir con el nombre del operador en facturación ACOS.</div>
                    </div>
                    <?php endif; ?>

                    <div class="edit-user-mobile-actions">
                        <a href="<?php echo URLROOT; ?>/admin/users" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-check me-2"></i>Guardar cambios</button>
                    </div>
                </div>
            </section>
        </div>
    </form>
</div>

<?php require APPROOT . '/views/admin/partials/user_access_permissions.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const pictureInput = document.getElementById('profile_picture');
    const avatarPreview = document.getElementById('avatarPreview');
    if (!pictureInput || !avatarPreview) return;

    pictureInput.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (!file || !file.type.startsWith('image/')) return;

        const reader = new FileReader();
        reader.addEventListener('load', function () {
            if (typeof reader.result === 'string') avatarPreview.src = reader.result;
        });
        reader.readAsDataURL(file);
    });
});
</script>

<?php require APPROOT . '/views/inc/footer.php'; ?>
