<?php
// ----------------------------------------------------------------------
// ARCHIVO: app/Core.php (VERSIÓN COMPLETA Y FINAL)
// ----------------------------------------------------------------------

/*
 * Clase Principal de la Aplicación (Router)
 * Crea la URL y carga el controlador correspondiente.
 * Formato de la URL: /controlador/metodo/parametros
 */
class Core {
    // Propiedades con valores por defecto para la página de inicio.
    protected $currentController = 'LoginController';
    protected $currentMethod = 'index';
    protected $params = [];

    public function __construct(){
        // Obtiene la URL parseada.
        $url = $this->getUrl();

        // 1. Busca el controlador en la carpeta app/controllers.
        // El primer elemento de la URL ($url[0]) es el nombre del controlador.
        if(isset($url[0]) && file_exists('../app/controllers/' . ucwords($url[0]) . 'Controller.php')){
            // Si el archivo del controlador existe, se establece como el controlador actual.
            $this->currentController = ucwords($url[0]) . 'Controller';
            // Se elimina el controlador del array $url para dejar solo los parámetros.
            unset($url[0]);
        }
        
        // Carga el archivo del controlador (ya sea el por defecto o el de la URL).
        require_once '../app/controllers/'. $this->currentController . '.php';
        
        // Crea una instancia del controlador. E.g., $LoginController = new LoginController;
        $this->currentController = new $this->currentController;

        // 2. Busca el método en el controlador.
        // El segundo elemento de la URL ($url[1]) es el nombre del método.
        if(isset($url[1])){
            // is_callable (y no method_exists) para excluir métodos privados/protegidos.
            if(is_callable([$this->currentController, $url[1]])){
                $this->currentMethod = $url[1];
                // Se elimina el método del array $url.
                unset($url[1]);
            }
        }
        
        // 3. Obtiene los parámetros de la URL.
        // Lo que queda en el array $url son los parámetros.
        $this->params = $url ? array_values($url) : [];
        
        // 4. Llama al método del controlador con los parámetros.
        if (!is_callable([$this->currentController, $this->currentMethod])) {
            http_response_code(404);
            exit('Página no encontrada.');
        }
        call_user_func_array([$this->currentController, $this->currentMethod], $this->params);
    }
    
    /**
     * Obtiene y parsea la URL desde el parámetro 'url' definido en public/.htaccess
     * @return array La URL descompuesta en un array.
     */
    public function getUrl(){
        if (!isset($_GET['url'])) {
            $pathInfo = (string)($_SERVER['PATH_INFO'] ?? '');
            if ($pathInfo === '') {
                $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
                $req = str_replace('\\', '/', (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''));
                if ($script !== '' && strpos($req, $script) === 0) {
                    $pathInfo = substr($req, strlen($script));
                }
            }
            if ($pathInfo !== '' && $pathInfo !== '/') {
                $_GET['url'] = ltrim($pathInfo, '/');
            } else {
                return [];
            }
        }
        $url = rtrim((string)$_GET['url'], '/');
        if ($url === '') {
            return [];
        }
        $parts = explode('/', $url);
        $safe = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || $part === '.' || $part === '..') {
                continue;
            }
            $part = preg_replace('/[^a-zA-Z0-9_-]/', '', $part);
            if ($part === '') {
                continue;
            }
            $safe[] = $part;
        }
        return $safe;
    }
}
