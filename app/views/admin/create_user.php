<?php
// ----------------------------------------------------------------------
// ARCHIVO: app/views/admin/create_user.php (CORREGIDO PARA PHP 5.4)
// Se ha reemplazado el operador '??' por 'isset()' para compatibilidad.
// ----------------------------------------------------------------------

require APPROOT . '/views/inc/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header">
                <h4 class="mb-0">
                    <a href="<?php echo URLROOT; ?>/admin/users" class="btn btn-light me-2" title="Volver a la lista"><i class="fas fa-arrow-left"></i></a>
                    Crear Nuevo Usuario
                </h4>
            </div>
            <div class="card-body">
                <!-- El formulario necesita 'enctype="multipart/form-data"' para poder subir archivos -->
                <form action="<?php echo URLROOT; ?>/admin/createUser" method="post" enctype="multipart/form-data" autocomplete="off">
                    <?php echo csrf_field(); ?>
                    <?php
                    $nameSource = isset($data) ? $data : [];
                    require APPROOT . '/views/admin/partials/user_name_fields.php';
                    ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Nombre de Usuario <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="username" class="form-control <?php echo (isset($data['errors']['username'])) ? 'is-invalid' : ''; ?>" value="<?php echo isset($data['username']) ? htmlspecialchars($data['username']) : ''; ?>" autocomplete="off" required>
                            <div class="invalid-feedback"><?php echo isset($data['errors']['username']) ? $data['errors']['username'] : ''; ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="employee_group" class="form-label">Grupo organizacional <span class="text-danger">*</span></label>
                            <?php $selectedGroup = User::normalizeOrganizationGroup($data['employee_group'] ?? 'paviotti'); ?>
                            <select name="employee_group" id="employee_group" class="form-select" required>
                                <?php foreach (User::organizationGroupOptions() as $groupKey => $groupLabel): ?>
                                <option value="<?php echo htmlspecialchars($groupKey); ?>" <?php echo $selectedGroup === $groupKey ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($groupLabel); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Se usa para segmentar comunicaciones internas.</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Contraseña <span class="text-danger">*</span></label>
                            <input type="password" name="password" id="password" class="form-control <?php echo (isset($data['errors']['password'])) ? 'is-invalid' : ''; ?>" autocomplete="new-password" required>
                            <div class="invalid-feedback"><?php echo isset($data['errors']['password']) ? $data['errors']['password'] : ''; ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirmar Contraseña <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (isset($data['errors']['confirm_password'])) ? 'is-invalid' : ''; ?>" autocomplete="new-password" required>
                            <div class="invalid-feedback"><?php echo isset($data['errors']['confirm_password']) ? $data['errors']['confirm_password'] : ''; ?></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="company_id" class="form-label">Empresa <span class="text-danger">*</span></label>
                            <select name="company_id" id="company_id" class="form-select <?php echo (isset($data['errors']['company_id'])) ? 'is-invalid' : ''; ?>" autocomplete="off" required>
                                <option value="">— Seleccioná empresa —</option>
                                <?php
                                $selectedCompany = isset($data['company_id'])
                                    ? (int)$data['company_id']
                                    : (int)($data['default_company_id'] ?? 0);
                                foreach (($data['companies'] ?? []) as $co):
                                ?>
                                <option value="<?php echo (int)$co->id; ?>" <?php echo $selectedCompany === (int)$co->id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($co->name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback"><?php echo isset($data['errors']['company_id']) ? $data['errors']['company_id'] : ''; ?></div>
                            <small class="text-muted">Define con quién puede intercambiar turnos (misma empresa).</small>
                        </div>
                    </div>
                    <?php require APPROOT . '/views/admin/partials/user_branch_field.php'; ?>
                    <?php require APPROOT . '/views/admin/partials/user_attendance_control_field.php'; ?>
                    <?php require APPROOT . '/views/admin/partials/user_area_field.php'; ?>
                    <div class="row">
                         <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Rol</label>
                            <select name="role" id="role" class="form-select" autocomplete="off">
                                <option value="empleado" selected>Empleado</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="profile_picture" class="form-label">Foto de Perfil (Opcional)</label>
                            <input type="file" name="profile_picture" class="form-control <?php echo (isset($data['errors']['picture'])) ? 'is-invalid' : ''; ?>">
                            <div class="invalid-feedback"><?php echo isset($data['errors']['picture']) ? $data['errors']['picture'] : ''; ?></div>
                        </div>
                    </div>

                    <?php
                    $profileExtendedReady = (new User())->isProfileExtendedReady();
                    $profileSource = isset($data) ? $data : [];
                    require APPROOT . '/views/admin/partials/user_profile_fields.php';
                    ?>
                    
                    <?php if (isset($data['errors']['general'])): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($data['errors']['general']); ?></div>
                    <?php endif; ?>

                    <?php
                    $source = isset($data) ? $data : [];
                    require APPROOT . '/views/admin/partials/user_employment_fields.php';
                    ?>

                    <?php require APPROOT . '/views/admin/partials/user_complete_record_fields.php'; ?>

                    <hr>
                    <button type="submit" class="btn btn-success w-100">Guardar Usuario</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require APPROOT . '/views/inc/footer.php'; ?>
