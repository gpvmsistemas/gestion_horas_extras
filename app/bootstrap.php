<?php
// ----------------------------------------------------------------------
// ARCHIVO: app/bootstrap.php (VERSIÓN COMPLETA Y VERIFICADA)
// Este archivo carga la configuración y el autoloader de clases.
// ----------------------------------------------------------------------

// 1. Cargar el archivo de configuración principal
// Define constantes para la base de datos, rutas (URLROOT), etc.
require_once 'config/config.php';

// 2. Cargar Helpers
// Contiene funciones de ayuda globales, como redirect() y manejo de sesión.
require_once 'helpers/session_helper.php';
require_once 'helpers/encoding_helper.php';
require_once 'helpers/marcaciones_helper.php';
require_once 'helpers/attendance_helper.php';
require_once 'helpers/avatar_helper.php';
require_once 'helpers/auth_helper.php';
require_once 'helpers/calendar_events_helper.php';
require_once 'helpers/name_helper.php';
require_once 'helpers/learning_helper.php';
require_once 'helpers/overtime_helper.php';
require_once 'helpers/overtime_visibility_helper.php';
require_once 'helpers/notifications_helper.php';
require_once 'helpers/uploads_security_helper.php';
require_once 'helpers/incidents_helper.php';
require_once 'helpers/vacation_helper.php';
require_once 'helpers/peer_stars_helper.php';
require_once 'helpers/surveys_helper.php';
require_once 'helpers/prode_helper.php';
require_once 'helpers/prode_fixture_helper.php';
require_once 'helpers/cp_tasks_helper.php';
require_once 'helpers/cp_visibility_helper.php';
require_once 'helpers/company_brand_helper.php';
require_once 'helpers/system_settings_helper.php';
require_once 'helpers/employee_portal_helper.php';
require_once 'helpers/salary_advance_helper.php';
require_once 'helpers/access_control_helper.php';
require_once 'helpers/org_helper.php';

$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

require_once 'services/LearningAssignmentService.php';
require_once 'services/NotificationTargetService.php';
require_once 'services/AnnouncementDisplayService.php';
require_once 'services/MailService.php';

// 3. Autoload de Clases (Core, Controladores y Modelos)
// Esta función se ejecuta automáticamente cuando se intenta usar una clase
// que no ha sido incluida previamente.
spl_autoload_register(function($className){
    
    // Define las rutas donde pueden estar los archivos de las clases.
    // La ruta '../app/' es relativa a public/index.php, por lo que es correcta.
    $paths = [
        '../app/controllers/' . $className . '.php',
        '../app/models/' . $className . '.php',
        '../app/services/' . $className . '.php',
        '../app/' . $className . '.php'
    ];

    // Recorre las rutas y, si encuentra el archivo, lo incluye
    // para que la clase esté disponible.
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return; // Detiene la búsqueda una vez que encuentra y carga la clase.
        }
    }
});

require_once 'models/SystemSetting.php';
require_once 'services/SystemSettingsService.php';

if (function_exists('uploads_protect_sensitive_paths')) {
    uploads_protect_sensitive_paths();
}
