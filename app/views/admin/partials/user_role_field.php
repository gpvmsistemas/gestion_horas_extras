<?php
/**
 * Select de perfil: 5 alcances si user_access_scopes está instalado;
 * si no, el rol de cuenta legacy. El POST se normaliza en el controlador.
 */
$accessReady = (new AccessControl())->isReady();
$posted = $data['access_role'] ?? $data['role'] ?? null;
$selected = '';
if (is_string($posted) && $posted !== '') {
    $selected = $posted;
}
if ($selected === '' && !empty($data['user'])) {
    $scope = $accessReady ? (new AccessControl())->currentScopeForUser((int)$data['user']->id) : null;
    $selected = $scope->access_role ?? ($data['user']->role ?? 'empleado');
}
if ($accessReady) {
    if (!isset(AccessControl::roles()[$selected])) {
        $selected = AccessControl::accessRoleFromLegacyRole($selected ?: 'empleado');
    }
    $options = AccessControl::roles();
    $help = 'Este perfil es el que vale para permisos. El rol de cuenta (admin / supervisor / empleado) se sincroniza solo.';
} else {
    if (!in_array($selected, ['admin', 'supervisor', 'empleado'], true)) {
        $selected = AccessControl::legacyRoleFromAccessRole($selected ?: 'operario');
    }
    $options = [
        'empleado' => 'Empleado',
        'supervisor' => 'Supervisor (jefe de área)',
        'admin' => 'Admin',
    ];
    $help = 'Rol de la cuenta. Los perfiles por empresa se habilitan con migration_access_control_scopes.sql.';
}
?>
<div class="mb-3">
    <label for="role" class="form-label"><?php echo $accessReady ? 'Perfil de acceso' : 'Rol'; ?></label>
    <select name="role" id="role" class="form-select">
        <?php foreach ($options as $value => $label): ?>
        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $selected === $value ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($label); ?>
        </option>
        <?php endforeach; ?>
    </select>
    <div class="form-text"><?php echo htmlspecialchars($help); ?></div>
</div>
