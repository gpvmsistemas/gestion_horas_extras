<?php
/**
 * Campos de datos personales (crear / editar usuario).
 * $profileSource: objeto user o array con valores del POST.
 */
$ps = $profileSource ?? null;
$pf = function ($key, $default = '') use ($ps) {
    if (is_array($ps)) {
        return isset($ps[$key]) ? (string)$ps[$key] : $default;
    }
    if (is_object($ps) && isset($ps->$key) && $ps->$key !== null) {
        return (string)$ps->$key;
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
<hr class="my-3">
<h6 class="mb-3 text-muted"><i class="fas fa-id-card me-1"></i> Datos personales y contacto</h6>

<?php if (empty($profileExtendedReady)): ?>
<div class="alert alert-warning small">
    Faltan columnas de perfil en la base de datos. Ejecutá
    <code>migration_users_profile_extended.sql</code> en phpMyAdmin (ver <code>MIGRATIONS.md</code>).
</div>
<?php else: ?>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" name="email" id="email" class="form-control <?php echo isset($data['errors']['email']) ? 'is-invalid' : ''; ?>"
               value="<?php echo htmlspecialchars($pf('email')); ?>" autocomplete="off">
        <?php if (!empty($data['errors']['email'])): ?>
        <div class="invalid-feedback"><?php echo htmlspecialchars($data['errors']['email']); ?></div>
        <?php endif; ?>
    </div>
    <div class="col-md-6 mb-3">
        <label for="phone_number" class="form-label">Teléfono / WhatsApp</label>
        <input type="tel" name="phone_number" id="phone_number" class="form-control"
               value="<?php echo htmlspecialchars($pf('phone_number')); ?>" placeholder="Ej. 11 2345-6789" autocomplete="off">
    </div>
</div>

<div class="mb-3">
    <label for="address" class="form-label">Dirección</label>
    <input type="text" name="address" id="address" class="form-control"
           value="<?php echo htmlspecialchars($pf('address')); ?>" placeholder="Calle, número, localidad" autocomplete="off">
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="document_number" class="form-label">DNI / Documento</label>
        <input type="text" name="document_number" id="document_number" class="form-control"
               value="<?php echo htmlspecialchars($pf('document_number')); ?>" autocomplete="off">
    </div>
    <div class="col-md-4 mb-3">
        <label for="cuil" class="form-label">CUIL</label>
        <input type="text" name="cuil" id="cuil" class="form-control"
               value="<?php echo htmlspecialchars($pf('cuil')); ?>" placeholder="20-12345678-9" autocomplete="off">
    </div>
    <div class="col-md-4 mb-3">
        <label for="birth_date" class="form-label">Fecha de nacimiento</label>
        <input type="date" name="birth_date" id="birth_date" class="form-control"
               value="<?php echo htmlspecialchars($birthVal); ?>" autocomplete="off">
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="sex" class="form-label">Sexo <small class="text-muted">(registro legal / administrativo)</small></label>
        <select name="sex" id="sex" class="form-select" autocomplete="off">
            <?php foreach ($sexOpts as $val => $label): ?>
            <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $currentSex === $val ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($label); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label for="gender" class="form-label">Género <small class="text-muted">(identidad)</small></label>
        <select name="gender" id="gender" class="form-select" autocomplete="off">
            <?php foreach ($genderOpts as $val => $label): ?>
            <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $currentGender === $val ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($label); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<?php $personalReady = (new User())->isPersonalFileReady(); ?>
<?php if ($personalReady): ?>
<div class="row">
    <div class="col-md-4 mb-3">
        <label for="marital_status" class="form-label">Estado civil</label>
        <select name="marital_status" id="marital_status" class="form-select" autocomplete="off">
            <?php $currentMarital = (string)$pf('marital_status'); ?>
            <?php foreach (User::maritalStatusOptions() as $val => $label): ?>
            <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $currentMarital === $val ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($label); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-8 mb-3">
        <label for="hr_notes" class="form-label">Observaciones de RRHH <small class="text-muted">(referencias, detalle interno)</small></label>
        <textarea name="hr_notes" id="hr_notes" class="form-control" rows="2" autocomplete="off"><?php echo htmlspecialchars((string)$pf('hr_notes')); ?></textarea>
    </div>
</div>
<?php
$employeeChildrenReady = !empty($employeeChildrenReady ?? ($data['employee_children_ready'] ?? false));
$employeeChildren = $employeeChildren ?? ($data['employee_children'] ?? []);
$hasChildren = array_key_exists('has_children', $data ?? []) ? !empty($data['has_children']) : count($employeeChildren) > 0;
$childrenUi = 'admin';
require APPROOT . '/views/inc/partials/employee_children_fields.php';
?>
<?php endif; ?>

<h6 class="mb-2 text-muted small text-uppercase">Contacto de emergencia</h6>
<div class="row">
    <div class="col-md-5 mb-3">
        <label for="emergency_contact_name" class="form-label">Nombre</label>
        <input type="text" name="emergency_contact_name" id="emergency_contact_name" class="form-control"
               value="<?php echo htmlspecialchars($pf('emergency_contact_name')); ?>" autocomplete="off">
    </div>
    <?php if ($personalReady): ?>
    <div class="col-md-3 mb-3">
        <label for="emergency_contact_relationship" class="form-label">Parentesco</label>
        <input type="text" name="emergency_contact_relationship" id="emergency_contact_relationship" class="form-control"
               placeholder="Madre, hermano, pareja…" maxlength="60"
               value="<?php echo htmlspecialchars((string)$pf('emergency_contact_relationship')); ?>" autocomplete="off">
    </div>
    <?php endif; ?>
    <div class="col-md-4 mb-3">
        <label for="emergency_contact_phone" class="form-label">Teléfono</label>
        <input type="tel" name="emergency_contact_phone" id="emergency_contact_phone" class="form-control"
               value="<?php echo htmlspecialchars($pf('emergency_contact_phone')); ?>" autocomplete="off">
    </div>
</div>

<?php endif; ?>
