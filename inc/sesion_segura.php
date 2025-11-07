<?php
// =============================================
// CONFIGURACIÓN SEGURA DE SESIONES
// =============================================

// Incluir configuración
require_once __DIR__ . '/config.php';

/**
 * Inicia una sesión segura
 * @return bool True si la sesión se inició correctamente
 */
function iniciarSesionSegura() {
    // Configurar parámetros de sesión seguros antes de iniciar
    if (session_status() === PHP_SESSION_NONE) {
        // Configurar cookies seguras
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 0); // Cambiar a 1 en producción con HTTPS
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        // Configurar tiempo de vida
        ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
        
        // Configurar parámetros de cookies
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? '',
            'secure' => false, // Cambiar a true en producción
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        
        // Iniciar sesión
        return session_start();
    }
    
    return true;
}

/**
 * Regenera el ID de sesión de forma segura
 * @return bool True si se regeneró correctamente
 */
function regenerarSidSeguro() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        // Eliminar el ID de sesión antiguo
        session_regenerate_id(true);
        return true;
    }
    return false;
}

/**
 * Verifica si la sesión ha expirado
 * @return bool True si la sesión ha expirado
 */
function sesionExpirada() {
    if (isset($_SESSION['ULTIMA_ACTIVIDAD']) && 
        (time() - $_SESSION['ULTIMA_ACTIVIDAD'] > SESSION_TIMEOUT)) {
        return true;
    }
    
    // Actualizar timestamp de última actividad
    $_SESSION['ULTIMA_ACTIVIDAD'] = time();
    return false;
}

/**
 * Destruye la sesión de forma segura
 */
function destruirSesionSegura() {
    // Destruir todas las variables de sesión
    $_SESSION = [];

    // Si se desea destruir la cookie, también
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Finalmente, destruir la sesión
    session_destroy();
}

/**
 * Verifica el fingerprint de la sesión
 * @return bool True si el fingerprint es válido
 */
function verificarFingerprintSesion() {
    $fingerprintActual = md5(
        $_SERVER['HTTP_USER_AGENT'] . 
        (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '') .
        (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : '')
    );
    
    if (!isset($_SESSION['fingerprint'])) {
        $_SESSION['fingerprint'] = $fingerprintActual;
        return true;
    }
    
    return $_SESSION['fingerprint'] === $fingerprintActual;
}

/**
 * Valida la sesión actual
 * @return bool True si la sesión es válida
 */
function validarSesion() {
    // Verificar si la sesión está activa
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    
    // Verificar si el usuario está autenticado
    if (!isset($_SESSION['usuario_id'])) {
        return false;
    }
    
    // Verificar si la sesión ha expirado
    if (sesionExpirada()) {
        destruirSesionSegura();
        return false;
    }
    
    // Verificar fingerprint
    if (!verificarFingerprintSesion()) {
        destruirSesionSegura();
        return false;
    }
    
    return true;
}
?>