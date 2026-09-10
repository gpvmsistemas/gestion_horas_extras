<?php require APPROOT . '/views/inc/header.php'; ?>

<?php
$createName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
$createCompanyId = isset($data['company_id'])
    ? (int)$data['company_id']
    : (int)($data['default_company_id'] ?? 0);
$createCompanyLabel = 'Sin empresa asignada';
foreach (($data['companies'] ?? []) as $co) {
    if ((int)$co->id === $createCompanyId) {
        $createCompanyLabel = $co->name;
        break;
    }
}
$createRolePosted = $data['access_role'] ?? $data['role'] ?? 'empleado';
$createRoleLabel = AccessControl::accessRoleLabel(
    isset(AccessControl::roles()[$createRolePosted]) ? $createRolePosted : AccessControl::accessRoleFromLegacyRole($createRolePosted),
    $createRolePosted
);
?>

<div class="edit-user-page">
    <header class="edit-user-hero">
        <div class="edit-user-hero-main">
            <a href="<?php echo URLROOT; ?>/admin/users" class="edit-user-back" aria-label="Volver al listado de usuarios">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <div class="edit-user-eyebrow">Alta de personal</div>
                <h1><?php echo $createName !== '' ? htmlspecialchars($createName) : 'Nuevo usuario'; ?></h1>
                <div class="edit-user-meta">
                    <span><i class="fas fa-building"></i><?php echo htmlspecialchars($createCompanyLabel); ?></span>
                    <span><i class="fas fa-shield-alt"></i><?php echo htmlspecialchars($createRoleLabel); ?></span>
                </div>
            </div>
        </div>
    </header>

    <form action="<?php echo URLROOT; ?>/admin/createUser" method="post" enctype="multipart/form-data" autocomplete="off" class="edit-user-form">
        <?php echo csrf_field(); ?>
        <aside class="edit-user-aside" aria-label="Resumen del usuario">
            <div class="edit-user-identity-card">
                <div class="edit-user-avatar-wrap">
                    <img id="avatarPreview"
                         src="<?php echo htmlspecialchars(avatar_default_url()); ?>"
                         alt="Foto de perfil"
                         class="edit-user-avatar"
                         onerror="this.onerror=null;this.src='<?php echo htmlspecialchars(avatar_default_url(), ENT_QUOTES); ?>';">
                    <span class="edit-user-avatar-status" title="Usuario nuevo"></span>
                </div>
                <strong><?php echo $createName !== '' ? htmlspecialchars($createName) : 'Nuevo usuario'; ?></strong>
                <span class="edit-user-role-pill"><?php echo htmlspecialchars($createRoleLabel); ?></span>
                <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg,image/png,image/webp"
                       class="visually-hidden <?php echo isset($data['errors']['picture']) ? 'is-invalid' : ''; ?>">
                <label for="profile_picture" class="edit-user-upload-label"><i class="fas fa-camera"></i>Agregar foto</label>
                <small>JPG, PNG o WEBP · máximo 2 MB · opcional</small>
                <?php if (isset($data['errors']['picture'])): ?>
                    <div class="text-danger small mt-2" role="alert"><?php echo htmlspecialchars($data['errors']['picture']); ?></div>
                <?php endif; ?>
            </div>

            <nav class="edit-user-section-nav" aria-label="Secciones del formulario">
                <a href="#datos-personales"><i class="fas fa-user"></i><span>Datos personales</span></a>
                <a href="#organizacion"><i class="fas fa-sitemap"></i><span>Organización</span></a>
                <a href="#acceso"><i class="fas fa-key"></i><span>Acceso y rol</span></a>
                <a href="#configuracion-laboral"><i class="fas fa-briefcase"></i><span>Configuración laboral</span></a>
            </nav>

            <button type="submit" class="btn btn-primary edit-user-save-aside">
                <i class="fas fa-user-plus me-2"></i>Crear usuario
            </button>
        </aside>

        <div class="edit-user-content">
            <?php if (!empty($data['errors'])): ?>
            <div class="alert alert-danger" role="alert">
                <strong>No se pudo crear el usuario.</strong>
                <?php if (!empty($data['errors']['general'])): ?>
                    <div><?php echo htmlspecialchars($data['errors']['general']); ?></div>
                <?php endif; ?>
                <ul class="mb-0 mt-2">
                    <?php foreach ($data['errors'] as $field => $message): ?>
                        <?php if ($field === 'general' || $message === '') continue; ?>
                        <li><?php echo htmlspecialchars($message); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <section class="edit-user-section" id="datos-personales">
                <div class="edit-user-section-heading">
                    <span class="edit-user-section-icon"><i class="fas fa-user"></i></span>
                    <div><span>Identidad</span><h2>Datos personales</h2><p>Información básica y medios de contacto del empleado.</p></div>
                </div>
                <div class="edit-user-section-body">
                    <h6 class="mb-3 text-muted">Información principal</h6>
                    <?php
                    $nameSource = isset($data) ? $data : [];
                    require APPROOT . '/views/admin/partials/user_name_fields.php';
                    ?>
                    <?php
                    $profileExtendedReady = (new User())->isProfileExtendedReady();
                    $profileSource = isset($data) ? $data : [];
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
                    <p class="small text-muted mb-2">
                        Los empleados solo intercambian turnos con compañeros de la misma empresa.
                    </p>
                    <?php if (empty($data['companies'])): ?>
                    <div class="alert alert-warning small mb-3">
                        No hay empresas en el sistema. Ejecutá <code>migration_companies_grupo.sql</code> en MySQL o
                        <a href="<?php echo URLROOT; ?>/admin/companies">creá empresas aquí</a>.
                    </div>
                    <?php else: ?>
                    <div class="mb-3">
                        <label for="company_id" class="form-label">Empresa <span class="text-danger">*</span></label>
                        <select name="company_id" id="company_id" class="form-select <?php echo isset($data['errors']['company_id']) ? 'is-invalid' : ''; ?>" autocomplete="off" required>
                            <option value="">— Seleccioná empresa —</option>
                            <?php foreach ($data['companies'] as $co): ?>
                            <option value="<?php echo (int)$co->id; ?>" <?php echo $createCompanyId === (int)$co->id ? 'selected' : ''; ?>>
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
                        <?php $selectedGroup = User::normalizeOrganizationGroup($data['employee_group'] ?? 'paviotti'); ?>
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
                    <div><span>Seguridad</span><h2>Acceso y rol</h2><p>Usuario, contraseña y perfil de permisos.</p></div>
                </div>
                <div class="edit-user-section-body">
                    <div class="mb-3">
                        <label for="username" class="form-label">Usuario de acceso <span class="text-danger">*</span></label>
                        <input type="text" name="username" id="username" class="form-control <?php echo isset($data['errors']['username']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($data['username'] ?? ''); ?>" autocomplete="off" required>
                        <div class="invalid-feedback"><?php echo htmlspecialchars($data['errors']['username'] ?? ''); ?></div>
                    </div>
                    <?php require APPROOT . '/views/admin/partials/user_role_field.php'; ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Contraseña <span class="text-danger">*</span></label>
                            <input type="password" name="password" id="password" class="form-control <?php echo isset($data['errors']['password']) ? 'is-invalid' : ''; ?>" autocomplete="new-password" required>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($data['errors']['password'] ?? ''); ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirmar contraseña <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo isset($data['errors']['confirm_password']) ? 'is-invalid' : ''; ?>" autocomplete="new-password" required>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($data['errors']['confirm_password'] ?? ''); ?></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="edit-user-section" id="configuracion-laboral">
                <div class="edit-user-section-heading">
                    <span class="edit-user-section-icon"><i class="fas fa-briefcase"></i></span>
                    <div><span>Relación laboral</span><h2>Configuración laboral</h2><p>Convenio, ingreso, legajo ampliado, domicilio y cobertura.</p></div>
                </div>
                <div class="edit-user-section-body">
                    <?php
                    $source = isset($data) ? $data : [];
                    require APPROOT . '/views/admin/partials/user_employment_fields.php';
                    ?>
                    <?php require APPROOT . '/views/admin/partials/user_complete_record_fields.php'; ?>
                    <div class="edit-user-mobile-actions">
                        <a href="<?php echo URLROOT; ?>/admin/users" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus me-2"></i>Crear usuario</button>
                    </div>
                </div>
            </section>
        </div>
    </form>
</div>

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
