<?php
// ----------------------------------------------------------------------
// ARCHIVO 1: app/models/Database.php (VERSIÓN CORREGIDA Y ROBUSTA)
// Se han modificado resultSet() y single() para que acepten parámetros.
// ----------------------------------------------------------------------

class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;

    private $dbh; // Database Handler
    private $stmt;
    private $error;

    /**
     * Un único PDO por petición, compartido por todas las instancias.
     * Con ATTR_PERSISTENT todas las instancias ya compartían la MISMA conexión
     * MySQL, pero cada `new Database()` creaba un objeto PDO distinto sobre
     * ella: al destruirse cualquiera de esos objetos mientras había una
     * transacción abierta, PDO hacía ROLLBACK de la conexión compartida y el
     * commit del que la abrió fallaba con "There is no active transaction"
     * (ej.: User::createUser → saveBranchAssignments → new Company()).
     * Mantener el handle vivo en un static elimina ese efecto y evita repetir
     * SET NAMES / sql_mode en cada instancia.
     */
    private static $sharedHandle = null;

    public function __construct(){
        if (self::$sharedHandle instanceof PDO) {
            $this->dbh = self::$sharedHandle;
            return;
        }
        // DB_PORT es opcional (definible en config.local.php); default 3306.
        $port = defined('DB_PORT') ? (string)DB_PORT : '3306';
        $dsn = 'mysql:host=' . $this->host . ';port=' . $port . ';dbname=' . $this->dbname . ';charset=utf8mb4';
        $options = array(
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        );

        try{
            $this->dbh = new PDO($dsn, $this->user, $this->pass, $options);
            $this->dbh->exec("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");
            // Modo SQL de la sesión igual al entorno donde se validó la suite
            // (MariaDB sin STRICT_TRANS_TABLES). En MySQL 8 el default estricto
            // + ONLY_FULL_GROUP_BY convierte en fatales ('' en columnas
            // numéricas, GROUP BY parciales) lo que acá es comportamiento
            // esperado. Sesión solamente: no afecta a otros consumidores.
            $this->dbh->exec("SET SESSION sql_mode = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'");
            self::$sharedHandle = $this->dbh;
        } catch(PDOException $e){
            $this->error = $e->getMessage();
            die('Error de Conexión: ' . $this->error);
        }
    }

    public function query($sql){
        $this->stmt = $this->dbh->prepare($sql);
    }

    public function bind($param, $value, $type = null){
        if(is_null($type)){
            switch(true){
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }
        $this->stmt->bindValue($param, $value, $type);
    }

    public function execute($params = null){
        return is_null($params) ? $this->stmt->execute() : $this->stmt->execute($params);
    }

    /**
     * ACTUALIZADO: Ahora puede aceptar un array de parámetros.
     */
    public function resultSet($params = null){
        $this->execute($params);
        return $this->stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * ACTUALIZADO: Ahora puede aceptar un array de parámetros.
     */
    public function single($params = null){
        $this->execute($params);
        return $this->stmt->fetch(PDO::FETCH_OBJ);
    }

    public function rowCount(){
        return $this->stmt->rowCount();
    }
    
    public function lastInsertId(){
        return $this->dbh->lastInsertId();
    }
    
    public function beginTransaction(){
        return $this->dbh->beginTransaction();
    }

    public function commit(){
        return $this->dbh->commit();
    }

    public function inTransaction(){
        return $this->dbh->inTransaction();
    }

    public function rollBack(){
        if ($this->dbh->inTransaction()) {
            return $this->dbh->rollBack();
        }
        return false;
    }
}