<?php require APPROOT . '/views/inc/header.php';
$user = $data['user'];
$photo = !empty($user->profile_picture) ? $user->profile_picture : 'default.png';
?>
<div class="emp-page-header">
    <a href="<?php echo URLROOT; ?>/employee/index" class="emp-back-btn"><i class="fas fa-arrow-left"></i></a>
    <div>
        <h1 class="emp-page-title">Mi perfil</h1>
        <p class="emp-page-subtitle">Contraseña y foto de perfil</p>
    </div>
</div>

<div class="emp-card emp-form-card">
    <form method="post" action="<?php echo URLROOT; ?>/employee/updateProfile" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <div class="text-center mb-3">
            <img src="<?php echo URLROOT; ?>/uploads/avatars/<?php echo htmlspecialchars($photo); ?>"
                 alt="" class="rounded-circle" width="80" height="80" style="object-fit:cover"
                 onerror="this.src='<?php echo URLROOT; ?>/img/default-avatar.svg'">
        </div>
        <div class="emp-form-group">
            <label class="emp-label">Nombre</label>
            <input type="text" class="emp-input" value="<?php echo htmlspecialchars($user->full_name); ?>" disabled>
        </div>
        <div class="emp-form-group">
            <label class="emp-label">Empresa</label>
            <input type="text" class="emp-input" value="<?php echo htmlspecialchars($data['company_name'] ?? '—'); ?>" disabled>
        </div>
        <div class="emp-form-group">
            <label class="emp-label">Nueva foto (opcional)</label>
            <input type="file" name="profile_picture" class="emp-input" accept="image/*">
        </div>
        <div class="emp-form-group">
            <label class="emp-label">Nueva contraseña (opcional)</label>
            <input type="password" name="password" class="emp-input" autocomplete="new-password">
        </div>
        <div class="emp-form-group">
            <label class="emp-label">Confirmar contraseña</label>
            <input type="password" name="password_confirm" class="emp-input" autocomplete="new-password">
        </div>
        <button type="submit" class="emp-btn-primary w-100">Guardar cambios</button>
    </form>
</div>

<div class="emp-card mt-3">
    <form method="post" action="<?php echo URLROOT; ?>/login/logout">
        <?php echo csrf_field(); ?>
    <button type="submit" class="btn btn-outline-danger w-100">
        <i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión
    </button>
    </form>
</div>

<div style="height:80px" class="d-lg-none">
<?php require APPROOT . '/views/inc/footer.php'; ?>
