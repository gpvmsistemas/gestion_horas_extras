<?php
// ----------------------------------------------------------------------
// ARCHIVO: app/controllers/LoginController.php (VERSIÓN COMPLETA Y FINAL)
// ----------------------------------------------------------------------

class LoginController {
    private $userModel;

    /**
     * El constructor se ejecuta al crear la instancia del controlador.
     * Carga el modelo 'User' para interactuar con la base de datos.
     */
    public function __construct(){
        $this->userModel = new User();
    }

    /**
     * Muestra la página de inicio de sesión (método por defecto).
     * @param array $data - Datos opcionales para pasar a la vista (ej. errores).
     */
    public function index($data = []){
        // Si el usuario ya está logueado, lo redirige a su panel correspondiente.
        if (isLoggedIn()) {
            if (isStaffAdmin()) {
                redirect('admin/dashboard');
            }
            redirect('employee/index');
        }
        
        // Carga la vista del formulario de login.
        $this->view('login/index', $data);
    }

    /**
     * Procesa los datos enviados desde el formulario de login.
     */
    public function process(){
        // Se asegura de que la solicitud sea de tipo POST.
        if($_SERVER['REQUEST_METHOD'] == 'POST'){
            csrf_verify();
            $loginData = postString('login_data');
            $data = [
                'login_data' => $loginData,
                'error' => ''
            ];

            // Valida el formato 'usuario+contraseña'.
            if(empty($loginData) || !strpos($loginData, '+')){
                 $data['error'] = 'Formato inválido. Use: usuario+contraseña';
                 $this->index($data);
                 return;
            }
            
            // Separa el string en usuario y contraseña.
            list($username, $password) = explode('+', $loginData, 2);

            // Intenta loguear al usuario a través del modelo.
            $loggedInUser = $this->userModel->login($username, $password);

            if($loggedInUser){
                // Comprueba si el usuario está inactivo.
                if($loggedInUser === 'inactive'){
                    $data['error'] = 'Este usuario ha sido desactivado.';
                    $this->index($data);
                } else {
                    // Si el login es exitoso, registra acceso y crea la sesión.
                    (new UserLoginLog())->log((int)$loggedInUser->id);
                    $this->createUserSession($loggedInUser);
                }
            }
            else {
                // Si las credenciales son incorrectas.
                $data['error'] = 'Usuario o Contraseña incorrectos.';
                $this->index($data);
            }
        }
        else {
            // Si se accede directamente a la URL, redirige al login.
            redirect('login');
        }
    }
    
    /**
     * Crea las variables de sesión para el usuario que ha iniciado sesión.
     * @param object $user - El objeto de usuario con los datos de la base de datos.
     */
    private function createUserSession($user){
        session_regenerate_id(true);
        if (function_exists('csrf_regenerate')) {
            csrf_regenerate();
        }
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_username'] = $user->username;
        $_SESSION['user_full_name'] = $user->full_name;
        $_SESSION['user_role'] = $user->role;
        $_SESSION['user_company_id'] = $user->company_id;
        $_SESSION['user_area_id'] = !empty($user->area_id) ? (int)$user->area_id : null;
        // Suite P&M: la organización del usuario define su vista (Paviotti o Moderna)
        // y bloquea el selector de contexto a las empresas de su grupo.
        $_SESSION['user_employee_group'] = (isset($user->employee_group)
            && in_array($user->employee_group, ['paviotti', 'moderna'], true))
            ? $user->employee_group : 'paviotti';
        $companyModel = new Company();
        $_SESSION['user_company_name'] = $companyModel->getNameById($user->company_id);
        $_SESSION['user_profile_picture'] = $user->profile_picture ?? 'default.png';
        if (function_exists('access_control_ready') && access_control_ready()) {
            $scope = (new AccessControl())->currentScopeForUser((int)$user->id);
            if ($scope) {
                $_SESSION['access_scope_id'] = (int)$scope->id;
                $_SESSION['user_company_id'] = (int)$scope->company_id;
                $_SESSION['user_branch_id'] = (int)($scope->branch_id ?? 0);
                $_SESSION['user_company_name'] = $companyModel->getNameById((int)$scope->company_id);
            }
        }

        if (function_exists('access_is_staff') ? access_is_staff() : ($user->role === 'admin' || $user->role === 'supervisor')) {
            redirect('admin/dashboard');
        }
        redirect('employee/index');
    }

    /**
     * Destruye la sesión del usuario para cerrar la sesión.
     */
    public function logout(){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(isLoggedIn() ? (isStaffAdmin() ? 'admin/dashboard' : 'employee/index') : 'login');
        }
        csrf_verify();
        unset($_SESSION['user_id']);
        unset($_SESSION['user_username']);
        unset($_SESSION['user_full_name']); // Limpia el nombre completo de la sesión
        unset($_SESSION['user_role']);
        unset($_SESSION['user_company_id']);
        unset($_SESSION['user_company_name']);
        unset($_SESSION['user_branch_id']);
        unset($_SESSION['access_scope_id']);
        unset($_SESSION['user_profile_picture']);
        session_destroy();
        redirect('login');
    }

    /**
     * Carga un archivo de vista y le pasa datos.
     * @param string $view - El nombre de la vista (ej. 'login/index').
     * @param array $data - Los datos a usar en la vista.
     */
    private function view($view, $data = []){
        if(file_exists('../app/views/' . $view . '.php')){
            require_once '../app/views/' . $view . '.php';
        } else {
            die('Error: La vista no existe: ' . $view);
        }
    }
}
?>
