<?php require APPROOT . '/views/inc/header.php'; ?>

<!-- ══ ENCABEZADO ══ -->
<div class="emp-page-header">
    <a href="<?php echo URLROOT; ?>/employee/index" class="emp-back-btn">
        <i class="fas fa-arrow-left"></i>
    </a>
    <div>
        <h1 class="emp-page-title">Sugerencias</h1>
        <p class="emp-page-subtitle">Tu opinión nos importa — es 100% anónima</p>
    </div>
</div>

<div class="emp-suggestion-hero">
    <i class="fas fa-lock emp-suggestion-icon"></i>
    <p>Este espacio es completamente <strong>anónimo</strong>. Tu nombre no queda registrado en ningún momento.</p>
</div>

<div class="emp-card emp-form-card">
    <form action="<?php echo URLROOT; ?>/suggestion/submit" method="post" id="formSugerencia">
        <?php echo csrf_field(); ?>
        <div class="emp-form-group">
            <label class="emp-label">Tu sugerencia o comentario</label>
            <textarea name="suggestion_text" class="emp-input emp-textarea" rows="6"
                      placeholder="Compartí ideas, mejoras, inquietudes..." required
                      id="txtSugerencia" maxlength="1000"></textarea>
            <div class="emp-char-counter">
                <span id="charCount">0</span> / 1000 caracteres
            </div>
        </div>
        <button type="submit" class="emp-btn-primary w-100">
            <i class="fas fa-paper-plane me-2"></i>Enviar anónimamente
        </button>
    </form>
</div>

<div class="emp-page-bottom-spacer d-lg-none" aria-hidden="true"></div>

<?php require APPROOT . '/views/inc/footer.php'; ?>

<script>
const txt = document.getElementById('txtSugerencia');
const counter = document.getElementById('charCount');
txt.addEventListener('input', function(){ counter.textContent = this.value.length; });
</script>

<?php if(isset($_SESSION['flash_success'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    if(typeof Swal !== 'undefined'){
        Swal.fire({title:'¡Enviado!',text:'Gracias por tu aporte.',icon:'success',timer:2500,showConfirmButton:false});
    }
});
</script>
<?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

