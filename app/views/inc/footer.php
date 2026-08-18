<?php
// app/views/inc/footer.php
$_usesCpNav = isset($_usesCpNav) ? $_usesCpNav : (function_exists('current_user_uses_casapav_tasks') && current_user_uses_casapav_tasks());
$_uri = $_SERVER['REQUEST_URI'] ?? '';
?>
    </main><!-- /.page-content o .login-wrapper -->

<?php if(isLoggedIn()): ?>
    <footer class="app-footer">
        <?php
        // Suite P&M: en la vista Moderna el footer lleva la marca completa.
        $_footerBrand = function_exists('company_brand_display_name') ? company_brand_display_name() : SITENAME;
        if (function_exists('org_is_moderna') && org_is_moderna()) {
            $_footerBrand = 'Red Farmacias Moderna';
        }
        ?>
        <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($_footerBrand); ?> &mdash; Todos los derechos reservados.</p>
    </footer>

<?php if(hasRole('empleado')): ?>
<!-- ══ BOTTOM NAV MOBILE (solo empleados) ══ -->
<nav class="emp-bottom-nav d-lg-none" aria-label="Navegación principal">
    <a href="<?php echo URLROOT; ?>/employee/index"
       class="emp-bnav-item <?php echo (strpos($_uri, '/employee/index') !== false) ? 'active' : ''; ?>">
        <i class="fas fa-home"></i><span>Inicio</span>
    </a>
    <?php if ($_usesCpNav && function_exists('employee_portal_show_cp_extras_nav') && employee_portal_show_cp_extras_nav()): ?>
    <a href="<?php echo URLROOT; ?>/cpTask/index"
       class="emp-bnav-item <?php echo (strpos($_uri, '/cpTask') !== false) ? 'active' : ''; ?>">
        <i class="fas fa-clipboard-list"></i><span>Extras</span>
    </a>
    <?php elseif (function_exists('employee_portal_show_overtime_nav') && employee_portal_show_overtime_nav()): ?>
    <a href="<?php echo URLROOT; ?>/employee/dashboard"
       class="emp-bnav-item <?php echo (strpos($_uri, '/employee/dashboard') !== false) ? 'active' : ''; ?>">
        <i class="fas fa-plus-circle"></i><span>Horas+</span>
    </a>
    <?php endif; ?>
    <a href="<?php echo URLROOT; ?>/employee/misHorarios"
       class="emp-bnav-item <?php echo (strpos($_uri, '/employee/misHorarios') !== false) ? 'active' : ''; ?>">
        <i class="fas fa-calendar-alt"></i><span>Horarios</span>
    </a>
    <a href="<?php echo URLROOT; ?>/request/index"
       class="emp-bnav-item <?php echo (strpos($_uri, '/request/index') !== false) ? 'active' : ''; ?>">
        <i class="fas fa-file-alt"></i><span>Solicitudes</span>
    </a>
    <a href="<?php echo URLROOT; ?>/login/logout"
       class="emp-bnav-item emp-bnav-item--logout"
       aria-label="Cerrar sesión">
        <i class="fas fa-sign-out-alt"></i><span>Salir</span>
    </a>
</nav>
<?php endif; ?>

</div><!-- /#contentWrapper -->
<?php else: ?>
    <footer class="auth-footer">
        <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITENAME; ?></p>
    </footer>
<?php endif; ?>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- DataTables Buttons -->
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<!-- FullCalendar -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/@fullcalendar/bootstrap5@6.1.11/index.global.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar/locales/es.js'></script>
<!-- DataTables i18n (mismo origen; evita CORS del CDN en localhost) -->
<script>window.DATATABLES_LANG_ES = { url: <?php echo json_encode(URLROOT . '/js/datatables-es-ES.json'); ?> };</script>
<!-- Script personalizado -->
<script src="<?php echo URLROOT; ?>/js/main.js"></script>
<?php if (hasRole('empleado') && function_exists('notifications_is_ready') && notifications_is_ready()): ?>
<script>window.EMP_ANNOUNCEMENTS = { urlRoot: <?php echo json_encode(URLROOT); ?> };</script>
<script src="<?php echo URLROOT; ?>/js/employee-announcements.js"></script>
<script src="<?php echo URLROOT; ?>/js/employee-notifications.js"></script>
<?php endif; ?>
<?php if (!empty($GLOBALS['requests_review_page'])): ?>
<script src="<?php echo URLROOT; ?>/js/requests-review.js?v=2"></script>
<?php endif; ?>
<?php if (!empty($GLOBALS['salary_advance_admin_page'])): ?>
<script src="<?php echo URLROOT; ?>/js/salary-advance-admin.js"></script>
<?php endif; ?>
<!-- Sidebar toggle JS (custom, sin Bootstrap offcanvas) -->
<script>
(function () {
    var sidebar    = document.getElementById('appSidebar');
    var overlay    = document.getElementById('sidebarOverlay');
    var openBtn    = document.getElementById('sidebarMobileToggle');
    var closeBtn   = document.getElementById('sidebarCloseBtn');
    var desktopBtn = document.getElementById('sidebarDesktopToggle');

    function openSidebar() {
        if (sidebar)  sidebar.classList.add('is-open');
        if (overlay)  overlay.classList.add('is-active');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        if (sidebar)  sidebar.classList.remove('is-open');
        if (overlay)  overlay.classList.remove('is-active');
        document.body.style.overflow = '';
    }

    if (openBtn)   openBtn.addEventListener('click', openSidebar);
    if (closeBtn)  closeBtn.addEventListener('click', closeSidebar);
    if (overlay)   overlay.addEventListener('click', closeSidebar);

    // Colapsar sidebar en desktop
    if (desktopBtn) {
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            document.body.classList.add('sidebar-collapsed');
        }
        desktopBtn.addEventListener('click', function () {
            document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed',
                document.body.classList.contains('sidebar-collapsed'));
        });
    }

    // Cerrar sidebar mobile si se redimensiona a desktop
    window.addEventListener('resize', function () {
        if (window.innerWidth >= 992) closeSidebar();
    });
}());
</script>
</body>
</html>
