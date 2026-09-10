<?php require APPROOT . '/views/inc/header.php';
$user = $data['user'];
$photo = !empty($user->profile_picture) ? $user->profile_picture : 'default.png';
$profileExtendedReady = !empty($data['profile_extended_ready']);
$personalFileReady = !empty($data['personal_file_ready']);
$pf = function ($key, $default = '') use ($user) {
    if (is_object($user) && isset($user->$key) && $user->$key !== null) {
        return (string)$user->$key;
    }
    return $default;
};
$sexOpts = User::sexOptions();
$genderOpts = User::genderOptions();
$currentSex = $pf('sex');
$currentGender = $pf('gender');
$birthVal = $pf('birth_date');
if ($birthVal !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $birthVal)) {
    $birthVal = substr($birthVal, 0, 10);
}
?>
<div class="emp-page-header">
    <a href="<?php echo URLROOT; ?>/employee/index" class="emp-back-btn" aria-label="Volver al inicio"><i class="fas fa-arrow-left"></i></a>
    <div>
        <h1 class="emp-page-title">Mi perfil</h1>
        <p class="emp-page-subtitle">Datos personales, foto y contraseña</p>
    </div>
</div>

<div class="emp-card emp-profile-hero">
    <div class="emp-profile-avatar-wrap">
        <img src="<?php echo URLROOT; ?>/uploads/avatars/<?php echo htmlspecialchars($photo); ?>"
             alt=""
             class="emp-profile-avatar-preview rounded-circle"
             onerror="this.src='<?php echo URLROOT; ?>/img/default-avatar.svg'">
    </div>
    <p class="emp-profile-name"><?php echo htmlspecialchars($pf('full_name')); ?></p>
    <p class="emp-profile-meta"><?php echo htmlspecialchars($data['company_name'] ?? '—'); ?></p>
</div>

<form method="post" action="<?php echo URLROOT; ?>/employee/updateProfile" enctype="multipart/form-data" class="emp-profile-form">
    <?php echo csrf_field(); ?>

    <div class="emp-card emp-form-section">
        <h2 class="emp-form-section-title"><i class="fas fa-lock"></i>Identidad</h2>
        <p class="emp-hint">El nombre lo gestiona RR. HH. Si hay un error, contactá al área de personal.</p>
        <div class="emp-form-group">
            <label class="emp-label">Nombre completo</label>
            <input type="text" class="emp-input" value="<?php echo htmlspecialchars($pf('full_name')); ?>" disabled readonly aria-readonly="true">
        </div>
        <div class="emp-form-group mb-0">
            <label class="emp-label">Empresa</label>
            <input type="text" class="emp-input" value="<?php echo htmlspecialchars($data['company_name'] ?? '—'); ?>" disabled readonly aria-readonly="true">
        </div>
    </div>

    <?php if (!$profileExtendedReady): ?>
    <div class="emp-card emp-form-section">
        <div class="alert alert-warning mb-0">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Los datos personales todavía no están habilitados en el servidor. Podés cambiar foto y contraseña; para el resto avisá a RR. HH.
        </div>
    </div>
    <?php else: ?>

    <div class="emp-card emp-form-section">
        <h2 class="emp-form-section-title"><i class="fas fa-address-book"></i>Contacto</h2>
        <div class="emp-form-group">
            <label class="emp-label" for="email">Email</label>
            <input type="email" name="email" id="email" class="emp-input"
                   value="<?php echo htmlspecialchars($pf('email')); ?>" autocomplete="email" inputmode="email">
        </div>
        <div class="emp-form-group">
            <label class="emp-label" for="phone_number">Teléfono / WhatsApp</label>
            <input type="tel" name="phone_number" id="phone_number" class="emp-input"
                   value="<?php echo htmlspecialchars($pf('phone_number')); ?>" placeholder="Ej. 11 2345-6789" autocomplete="tel" inputmode="tel">
        </div>
        <div class="emp-form-group mb-0">
            <label class="emp-label" for="address">Dirección</label>
            <input type="text" name="address" id="address" class="emp-input"
                   value="<?php echo htmlspecialchars($pf('address')); ?>" placeholder="Calle, número, localidad" autocomplete="street-address">
        </div>
    </div>

    <div class="emp-card emp-form-section">
        <h2 class="emp-form-section-title"><i class="fas fa-id-card"></i>Documentación</h2>
        <div class="emp-form-group">
            <label class="emp-label" for="document_number">DNI / Documento</label>
            <input type="text" name="document_number" id="document_number" class="emp-input"
                   value="<?php echo htmlspecialchars($pf('document_number')); ?>" autocomplete="off" inputmode="numeric">
        </div>
        <div class="emp-form-group">
            <label class="emp-label" for="cuil">CUIL</label>
            <input type="text" name="cuil" id="cuil" class="emp-input"
                   value="<?php echo htmlspecialchars($pf('cuil')); ?>" placeholder="20-12345678-9" autocomplete="off">
        </div>
        <div class="emp-form-group">
            <label class="emp-label" for="birth_date">Fecha de nacimiento</label>
            <input type="date" name="birth_date" id="birth_date" class="emp-input"
                   value="<?php echo htmlspecialchars($birthVal); ?>" autocomplete="bday">
        </div>
        <div class="emp-form-group">
            <label class="emp-label" for="sex">Sexo <span class="text-muted fw-normal">(registro legal)</span></label>
            <select name="sex" id="sex" class="emp-input" autocomplete="off">
                <?php foreach ($sexOpts as $val => $label): ?>
                <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $currentSex === $val ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($label); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="emp-form-group mb-0">
            <label class="emp-label" for="gender">Género <span class="text-muted fw-normal">(identidad)</span></label>
            <select name="gender" id="gender" class="emp-input" autocomplete="off">
                <?php foreach ($genderOpts as $val => $label): ?>
                <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $currentGender === $val ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($label); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if ($personalFileReady): ?>
    <div class="emp-card emp-form-section">
        <h2 class="emp-form-section-title"><i class="fas fa-users"></i>Situación familiar</h2>
        <div class="emp-form-group">
            <label class="emp-label" for="marital_status">Estado civil</label>
            <select name="marital_status" id="marital_status" class="emp-input" autocomplete="off">
                <?php $currentMarital = $pf('marital_status'); ?>
                <?php foreach (User::maritalStatusOptions() as $val => $label): ?>
                <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $currentMarital === $val ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($label); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
        $employeeChildrenReady = !empty($data['employee_children_ready']);
        $employeeChildren = $data['employee_children'] ?? [];
        $hasChildren = !empty($data['has_children']);
        $childrenUi = 'employee';
        $personalReady = $personalFileReady;
        require APPROOT . '/views/inc/partials/employee_children_fields.php';
        ?>
    </div>
    <?php endif; ?>

    <div class="emp-card emp-form-section">
        <h2 class="emp-form-section-title"><i class="fas fa-phone-alt"></i>Contacto de emergencia</h2>
        <div class="emp-form-group">
            <label class="emp-label" for="emergency_contact_name">Nombre</label>
            <input type="text" name="emergency_contact_name" id="emergency_contact_name" class="emp-input"
                   value="<?php echo htmlspecialchars($pf('emergency_contact_name')); ?>" autocomplete="off">
        </div>
        <?php if ($personalFileReady): ?>
        <div class="emp-form-group">
            <label class="emp-label" for="emergency_contact_relationship">Parentesco</label>
            <input type="text" name="emergency_contact_relationship" id="emergency_contact_relationship" class="emp-input"
                   placeholder="Madre, hermano, pareja…" maxlength="60"
                   value="<?php echo htmlspecialchars($pf('emergency_contact_relationship')); ?>" autocomplete="off">
        </div>
        <?php endif; ?>
        <div class="emp-form-group mb-0">
            <label class="emp-label" for="emergency_contact_phone">Teléfono</label>
            <input type="tel" name="emergency_contact_phone" id="emergency_contact_phone" class="emp-input"
                   value="<?php echo htmlspecialchars($pf('emergency_contact_phone')); ?>" autocomplete="tel" inputmode="tel">
        </div>
    </div>

    <?php endif; ?>

    <div class="emp-card emp-form-section">
        <h2 class="emp-form-section-title"><i class="fas fa-user-cog"></i>Cuenta</h2>
        <div class="emp-form-group emp-file-field">
            <label class="emp-label" for="profile_picture">Nueva foto (opcional)</label>
            <input type="file" name="profile_picture" id="profile_picture" class="emp-input" accept="image/*">
        </div>
        <div class="emp-form-group">
            <label class="emp-label" for="password">Nueva contraseña (opcional)</label>
            <input type="password" name="password" id="password" class="emp-input" autocomplete="new-password">
        </div>
        <div class="emp-form-group mb-0">
            <label class="emp-label" for="password_confirm">Confirmar contraseña</label>
            <input type="password" name="password_confirm" id="password_confirm" class="emp-input" autocomplete="new-password">
        </div>
    </div>

    <div class="emp-form-actions-sticky">
        <button type="submit" class="emp-btn-primary">
            <i class="fas fa-check me-1"></i>Guardar cambios
        </button>
    </div>
</form>

<?php require APPROOT . '/views/inc/partials/pwa_install_settings.php'; ?>

<div class="emp-card">
    <form method="post" action="<?php echo URLROOT; ?>/login/logout">
        <?php echo csrf_field(); ?>
        <button type="submit" class="emp-btn-danger">
            <i class="fas fa-sign-out-alt me-1"></i>Cerrar sesión
        </button>
    </form>
</div>

<div class="emp-page-bottom-spacer d-lg-none" aria-hidden="true"></div>
<?php require APPROOT . '/views/inc/footer.php'; ?>
