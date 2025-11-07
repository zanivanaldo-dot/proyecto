<?php
// Incluir configuración segura de sesión primero
require_once 'inc/sesion_segura.php';
iniciarSesionSegura();

require_once 'inc/conexion.php';
require_once 'inc/funciones.php';

if (isset($_SESSION['usuario_id'])) {
    $pdo = obtenerConexion();
    
    // Registrar log de cierre de sesión
    registrarEventoSeguridad($pdo, "Cerró sesión", "logout");
    
    // Destruir sesión de forma segura
    destruirSesionSegura();
}

// Redirigir al login
header('Location: login.php');
exit;
?>