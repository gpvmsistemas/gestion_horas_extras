<?php
require APPROOT . '/views/inc/header.php'; 
?>

<div class="card login-card">
    <div class="card-body p-4 p-sm-5">

        <!-- Logo / ícono -->
        <div class="text-center mb-4 login-brand-block">
            <img src="<?php echo URLROOT; ?>/img/pym.png"
                 alt="Identidad visual de RRHH"
                 class="login-brand-logo mb-3"
                 width="246"
                 height="112">
            <h1 class="h2 fw-bold mb-0 login-brand-title">RRHH</h1>
            <p class="text-muted small mt-1">Gestión de personal y RRHH</p>
        </div>

        <form action="<?php echo URLROOT; ?>/login/process" method="post">
            <?php echo csrf_field(); ?>
            <div class="form-floating mb-3">
                <input type="password" name="login_data" class="form-control" id="login_data"
                       placeholder=" " required autocomplete="current-password">
                <label for="login_data"><i class="fas fa-key me-1 text-muted"></i> usuario+contraseña</label>
            </div>

            <?php if(isset($data['error']) && !empty($data['error'])): ?>
                <div class="alert alert-danger py-2 d-flex align-items-center gap-2 mb-3">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($data['error']); ?>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary w-100 btn-lg fw-bold">
                <i class="fas fa-sign-in-alt me-2"></i>Ingresar
            </button>
        </form>

        <p class="text-center text-muted small mt-4 mb-0">
            Formato: <kbd class="bg-light text-muted border">usuario<span style="color:#e91e8c;">+</span>contraseña</kbd>
        </p>
    </div>
</div>

<?php require APPROOT . '/views/inc/footer.php'; ?>
