<?php
// =============================================
// CONEXIÓN PDO A BASE DE DATOS MYSQL
// =============================================

// Incluir configuración
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            $this->handleException($e);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    private function handleException(PDOException $e) {
        if (APP_DEBUG) {
            die("Error de conexión a la base de datos: " . $e->getMessage());
        } else {
            error_log("Error de BD: " . $e->getMessage());
            die("Error del sistema. Por favor, contacte al administrador.");
        }
    }
    
    // Método para ejecutar consultas preparadas
    public function ejecutarConsulta($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->handleException($e);
            return false;
        }
    }
    
    // Método para obtener un solo registro
    public function obtenerUno($sql, $params = []) {
        $stmt = $this->ejecutarConsulta($sql, $params);
        return $stmt->fetch();
    }
    
    // Método para obtener todos los registros
    public function obtenerTodos($sql, $params = []) {
        $stmt = $this->ejecutarConsulta($sql, $params);
        return $stmt->fetchAll();
    }
    
    // Método para obtener el último ID insertado
    public function ultimoId() {
        return $this->pdo->lastInsertId();
    }
    
    // Método para iniciar una transacción
    public function comenzarTransaccion() {
        return $this->pdo->beginTransaction();
    }
    
    // Método para confirmar una transacción
    public function confirmarTransaccion() {
        return $this->pdo->commit();
    }
    
    // Método para revertir una transacción
    public function revertirTransaccion() {
        return $this->pdo->rollBack();
    }
}

// Función helper para obtener la conexión
function obtenerConexion() {
    return Database::getInstance()->getConnection();
}

// Probar conexión (solo en modo debug)
if (APP_DEBUG && php_sapi_name() !== 'cli') {
    try {
        $pdo = obtenerConexion();
        echo "<!-- Conexión a BD establecida correctamente -->";
    } catch (Exception $e) {
        echo "<!-- Error de conexión: " . $e->getMessage() . " -->";
    }
}
?>