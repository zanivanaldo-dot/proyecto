<?php
// =============================================
// CONFIGURACIÓN DEL SISTEMA DE GESTIÓN DE ALQUILERES
// =============================================

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'gestion_alquileres');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configuración de la aplicación
define('APP_NAME', 'Sistema de Gestión de Alquileres');
define('APP_VERSION', '1.0');

// SEGURIDAD: Configuración de entorno
// Cambiar a 'false' en producción
define('APP_DEBUG', true);

// Configuración de sesión
define('SESSION_TIMEOUT', 3600); // 1 hora en segundos
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Solo en HTTPS

// Configuración de archivos - SEGURIDAD MEJORADA
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png']);
define('ALLOWED_MIME_TYPES', [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png'
]);

// Rutas de la aplicación
define('BASE_URL', 'http://localhost/gestion_alquileres');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('COMPROBANTES_PATH', UPLOAD_PATH . 'comprobantes/');
define('DOCUMENTOS_PATH', UPLOAD_PATH . 'documentos/');
define('BACKUP_PATH', __DIR__ . '/../backups/');
define('LOG_PATH', __DIR__ . '/../logs/');

// Crear directorios si no existen con permisos seguros
$directorios = [
    UPLOAD_PATH,
    COMPROBANTES_PATH,
    DOCUMENTOS_PATH,
    BACKUP_PATH,
    LOG_PATH
];

foreach ($directorios as $directorio) {
    if (!file_exists($directorio)) {
        mkdir($directorio, 0750, true);
    }
}

// Configuración de headers de seguridad
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Configuración de monedas
$monedas = [
    'ARS' => ['symbol' => '$', 'name' => 'Pesos Argentinos'],
    'USD' => ['symbol' => 'US$', 'name' => 'Dólares Estadounidenses']
];

// Configuración de tipos de unidades
$tipos_unidad = [
    'departamento' => 'Departamento',
    'oficina' => 'Oficina', 
    'local' => 'Local Comercial'
];

// Configuración de estados de contrato
$estados_contrato = [
    'activo' => 'Activo',
    'finalizado' => 'Finalizado',
    'renovado' => 'Renovado'
];

// SEGURIDAD: Configuración de errores según el entorno
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH . 'php_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH . 'php_errors.log');
}

// SEGURIDAD: Validar HTTPS en producción
if (!APP_DEBUG && empty($_SERVER['HTTPS'])) {
    //header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    //exit;
}

// SEGURIDAD: Headers adicionales
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
?>