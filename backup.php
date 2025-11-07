<?php
// Incluir configuración segura de sesión primero
require_once 'inc/sesion_segura.php';
iniciarSesionSegura();

require_once 'inc/conexion.php';
require_once 'inc/funciones.php';

requireAuth();
requireAdmin();

$pdo = obtenerConexion();
$titulo_pagina = 'Respaldo de Base de Datos';
$icono_titulo = 'fas fa-database';

// Configuración de backup
define('BACKUP_PATH', __DIR__ . '/../backups/');
define('MAX_BACKUP_FILES', 10);

// Crear directorio de backups si no existe
if (!file_exists(BACKUP_PATH)) {
    mkdir(BACKUP_PATH, 0755, true);
}

// Procesar acciones de backup
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Token de seguridad inválido.';
        redirigir('backup.php');
    }
    
    $accion = $_POST['accion'] ?? '';
    
    switch ($accion) {
        case 'crear_backup':
            crearBackup($pdo);
            break;
            
        case 'descargar_backup':
            $archivo = $_POST['archivo'] ?? '';
            descargarBackup($archivo);
            break;
            
        case 'eliminar_backup':
            $archivo = $_POST['archivo'] ?? '';
            eliminarBackup($archivo);
            break;
    }
}

// Obtener lista de backups
$backups = obtenerListaBackups();

// Breadcrumb
$breadcrumb = generarBreadcrumb([
    ['text' => 'Respaldo de BD', 'url' => 'backup.php']
]);

require_once 'inc/header.php';
?>

<div class="row">
    <div class="col-12">
        <!-- Información del Sistema -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-info-circle me-2"></i>Información del Sistema
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Base de Datos:</strong> <?= DB_NAME ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Servidor:</strong> <?= DB_HOST ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Total Tablas:</strong> <?= contarTablas($pdo) ?>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-4">
                        <strong>Tamaño BD:</strong> <?= obtenerTamañoBaseDatos($pdo) ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Último Backup:</strong> 
                        <?= !empty($backups) ? formato_fecha($backups[0]['fecha'], true) : 'Nunca' ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Backups Guardados:</strong> <?= count($backups) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Crear Backup -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-plus-circle me-2"></i>Crear Nuevo Respaldo
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Recomendación:</strong> Realice respaldos regularmente antes de realizar cambios importantes en el sistema.
                </div>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                    <input type="hidden" name="accion" value="crear_backup">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Opciones de Respaldo</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="incluir_datos" id="incluir_datos" checked>
                                    <label class="form-check-label" for="incluir_datos">
                                        Incluir datos de las tablas
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="comprimir" id="comprimir" checked>
                                    <label class="form-check-label" for="comprimir">
                                        Comprimir archivo (GZIP)
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-success w-100 h-100">
                                <i class="fas fa-database me-2"></i>Generar Respaldo
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de Backups -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history me-2"></i>Respaldos Existentes
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($backups)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-database fa-3x text-muted mb-3"></i>
                        <h6>No hay respaldos disponibles</h6>
                        <p class="text-muted">Genere su primer respaldo de la base de datos.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Archivo</th>
                                    <th>Fecha</th>
                                    <th>Tamaño</th>
                                    <th>Tablas</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backups as $backup): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-file-archive me-2 text-muted"></i>
                                            <code><?= htmlspecialchars($backup['nombre']) ?></code>
                                        </td>
                                        <td><?= formato_fecha($backup['fecha'], true) ?></td>
                                        <td><?= formato_tamaño_archivo($backup['tamaño']) ?></td>
                                        <td>
                                            <span class="badge bg-info"><?= $backup['tablas'] ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                                                    <input type="hidden" name="accion" value="descargar_backup">
                                                    <input type="hidden" name="archivo" value="<?= htmlspecialchars($backup['nombre']) ?>">
                                                    <button type="submit" class="btn btn-outline-primary" title="Descargar">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar este respaldo?')">
                                                    <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                                                    <input type="hidden" name="accion" value="eliminar_backup">
                                                    <input type="hidden" name="archivo" value="<?= htmlspecialchars($backup['nombre']) ?>">
                                                    <button type="submit" class="btn btn-outline-danger" title="Eliminar">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <small class="text-muted">
                            Se mantienen máximo <?= MAX_BACKUP_FILES ?> archivos de respaldo. Los más antiguos se eliminan automáticamente.
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Información de Seguridad -->
        <div class="card mt-4">
            <div class="card-header bg-warning">
                <h5 class="card-title mb-0 text-dark">
                    <i class="fas fa-shield-alt me-2"></i>Recomendaciones de Seguridad
                </h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li><i class="fas fa-check text-success me-2"></i> Almacene los respaldos en ubicación segura</li>
                    <li><i class="fas fa-check text-success me-2"></i> Realice respaldos regularmente (semanalmente)</li>
                    <li><i class="fas fa-check text-success me-2"></i> Verifique la integridad de los archivos de respaldo</li>
                    <li><i class="fas fa-check text-success me-2"></i> No almacene respaldos en el mismo servidor</li>
                    <li><i class="fas fa-check text-success me-2"></i> Utilice conexiones seguras para transferir respaldos</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'inc/footer.php';

// =============================================================================
// FUNCIONES DE BACKUP
// =============================================================================

function crearBackup($pdo) {
    try {
        $nombre_archivo = 'backup_' . DB_NAME . '_' . date('Y-m-d_His') . '.sql';
        $ruta_completa = BACKUP_PATH . $nombre_archivo;
        
        // Obtener todas las tablas
        $tablas = obtenerTablas($pdo);
        $contenido = "";
        
        // Cabecera del backup
        $contenido .= "-- Backup generado automáticamente\n";
        $contenido .= "-- Sistema: " . APP_NAME . "\n";
        $contenido .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
        $contenido .= "-- Base de Datos: " . DB_NAME . "\n\n";
        $contenido .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        // Backup de cada tabla
        foreach ($tablas as $tabla) {
            $contenido .= "--\n-- Estructura para tabla: `{$tabla}`\n--\n";
            
            // Obtener estructura de la tabla
            $stmt = $pdo->query("SHOW CREATE TABLE `{$tabla}`");
            $create_table = $stmt->fetch();
            $contenido .= "DROP TABLE IF EXISTS `{$tabla}`;\n";
            $contenido .= $create_table['Create Table'] . ";\n\n";
            
            // Obtener datos de la tabla
            $contenido .= "--\n-- Volcado de datos para tabla: `{$tabla}`\n--\n";
            
            $stmt = $pdo->query("SELECT * FROM `{$tabla}`");
            $registros = $stmt->fetchAll();
            
            if (!empty($registros)) {
                $columnas = array_keys($registros[0]);
                $contenido .= "INSERT INTO `{$tabla}` (`" . implode('`, `', $columnas) . "`) VALUES\n";
                
                $valores = [];
                foreach ($registros as $registro) {
                    $valores_fila = [];
                    foreach ($registro as $valor) {
                        if ($valor === null) {
                            $valores_fila[] = 'NULL';
                        } else {
                            $valores_fila[] = "'" . addslashes($valor) . "'";
                        }
                    }
                    $valores[] = "(" . implode(', ', $valores_fila) . ")";
                }
                
                $contenido .= implode(",\n", $valores) . ";\n\n";
            }
        }
        
        $contenido .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        // Guardar archivo
        if (file_put_contents($ruta_completa, $contenido)) {
            // Comprimir si está habilitado
            if (isset($_POST['comprimir']) && $_POST['comprimir'] === 'on') {
                $ruta_comprimida = $ruta_completa . '.gz';
                if (comprimirArchivo($ruta_completa, $ruta_comprimida)) {
                    unlink($ruta_completa); // Eliminar original
                    $nombre_archivo .= '.gz';
                }
            }
            
            // Limpiar backups antiguos
            limpiarBackupsAntiguos();
            
            $_SESSION['success'] = 'Respaldo creado exitosamente: ' . $nombre_archivo;
            registrarLog($pdo, "Creó respaldo de BD: $nombre_archivo");
        } else {
            throw new Exception('No se pudo crear el archivo de respaldo');
        }
        
    } catch (Exception $e) {
        error_log("Error al crear backup: " . $e->getMessage());
        $_SESSION['error'] = 'Error al crear el respaldo: ' . $e->getMessage();
    }
    
    redirigir('backup.php');
}

function descargarBackup($archivo) {
    $ruta_archivo = BACKUP_PATH . $archivo;
    
    if (!file_exists($ruta_archivo) || !is_file($ruta_archivo)) {
        $_SESSION['error'] = 'El archivo de respaldo no existe.';
        redirigir('backup.php');
    }
    
    // Verificar que el archivo esté en el directorio de backups
    if (strpos(realpath($ruta_archivo), realpath(BACKUP_PATH)) !== 0) {
        $_SESSION['error'] = 'Acceso denegado al archivo.';
        redirigir('backup.php');
    }
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $archivo . '"');
    header('Content-Length: ' . filesize($ruta_archivo));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    readfile($ruta_archivo);
    exit;
}

function eliminarBackup($archivo) {
    $ruta_archivo = BACKUP_PATH . $archivo;
    
    if (!file_exists($ruta_archivo) || !is_file($ruta_archivo)) {
        $_SESSION['error'] = 'El archivo de respaldo no existe.';
        redirigir('backup.php');
    }
    
    // Verificar que el archivo esté en el directorio de backups
    if (strpos(realpath($ruta_archivo), realpath(BACKUP_PATH)) !== 0) {
        $_SESSION['error'] = 'Acceso denegado al archivo.';
        redirigir('backup.php');
    }
    
    if (unlink($ruta_archivo)) {
        $_SESSION['success'] = 'Respaldo eliminado: ' . $archivo;
        registrarLog($pdo, "Eliminó respaldo: $archivo");
    } else {
        $_SESSION['error'] = 'Error al eliminar el respaldo.';
    }
    
    redirigir('backup.php');
}

function obtenerTablas($pdo) {
    $stmt = $pdo->query("SHOW TABLES");
    $tablas = [];
    while ($fila = $stmt->fetch(PDO::FETCH_NUM)) {
        $tablas[] = $fila[0];
    }
    return $tablas;
}

function contarTablas($pdo) {
    $stmt = $pdo->query("SHOW TABLES");
    return $stmt->rowCount();
}

function obtenerTamañoBaseDatos($pdo) {
    $sql = "SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
            FROM information_schema.TABLES 
            WHERE table_schema = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([DB_NAME]);
    $resultado = $stmt->fetch();
    
    return $resultado['size_mb'] . ' MB';
}

function obtenerListaBackups() {
    $backups = [];
    
    if (!is_dir(BACKUP_PATH)) {
        return $backups;
    }
    
    $archivos = scandir(BACKUP_PATH);
    
    foreach ($archivos as $archivo) {
        if ($archivo === '.' || $archivo === '..') continue;
        
        $ruta_completa = BACKUP_PATH . $archivo;
        if (is_file($ruta_completa)) {
            // Extraer información del nombre del archivo
            if (preg_match('/backup_([^_]+)_(\d{4}-\d{2}-\d{2}_\d{6})/', $archivo, $matches)) {
                $fecha = DateTime::createFromFormat('Y-m-d_His', $matches[2]);
                $backups[] = [
                    'nombre' => $archivo,
                    'fecha' => $fecha ? $fecha->format('Y-m-d H:i:s') : filemtime($ruta_completa),
                    'tamaño' => filesize($ruta_completa),
                    'tablas' => 'Todas' // En una implementación real, podrías contar las tablas
                ];
            }
        }
    }
    
    // Ordenar por fecha (más reciente primero)
    usort($backups, function($a, $b) {
        return strtotime($b['fecha']) - strtotime($a['fecha']);
    });
    
    return $backups;
}

function comprimirArchivo($origen, $destino) {
    if (!function_exists('gzopen')) {
        return false;
    }
    
    $gz = gzopen($destino, 'w9');
    if (!$gz) {
        return false;
    }
    
    $archivo = fopen($origen, 'rb');
    if (!$archivo) {
        gzclose($gz);
        return false;
    }
    
    while (!feof($archivo)) {
        gzwrite($gz, fread($archivo, 1024 * 512));
    }
    
    fclose($archivo);
    gzclose($gz);
    
    return true;
}

function limpiarBackupsAntiguos() {
    $backups = obtenerListaBackups();
    
    if (count($backups) > MAX_BACKUP_FILES) {
        $excedente = array_slice($backups, MAX_BACKUP_FILES);
        
        foreach ($excedente as $backup) {
            $ruta_archivo = BACKUP_PATH . $backup['nombre'];
            if (file_exists($ruta_archivo)) {
                unlink($ruta_archivo);
            }
        }
    }
}

function formato_tamaño_archivo($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>