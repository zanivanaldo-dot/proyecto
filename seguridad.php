<?php
require_once 'inc/sesion_segura.php';
iniciarSesionSegura();

require_once 'inc/conexion.php';
require_once 'inc/funciones.php';

requireAuth();
requireAdmin();
$pdo = obtenerConexion();
$titulo_pagina = 'Configuración de Seguridad';
$icono_titulo = 'fas fa-shield-alt';

// Breadcrumb
$breadcrumb = generarBreadcrumb([
    ['text' => 'Seguridad', 'url' => 'seguridad.php']
]);

require_once 'inc/header.php';
?>

<div class="row">
    <div class="col-12">
        <!-- Estado de Seguridad -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-check-circle me-2"></i>Estado de Seguridad
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <?php if (is_dir(BACKUP_PATH) && is_writable(BACKUP_PATH)): ?>
                                    <i class="fas fa-check text-success me-2"></i>
                                <?php else: ?>
                                    <i class="fas fa-times text-danger me-2"></i>
                                <?php endif; ?>
                                Directorio de backups accesible
                            </li>
                            <li class="mb-2">
                                <?php if (function_exists('password_hash')): ?>
                                    <i class="fas fa-check text-success me-2"></i>
                                <?php else: ?>
                                    <i class="fas fa-times text-danger me-2"></i>
                                <?php endif; ?>
                                Hash de contraseñas disponible
                            </li>
                            <li class="mb-2">
                                <?php if (ini_get('display_errors') == '0' || !APP_DEBUG): ?>
                                    <i class="fas fa-check text-success me-2"></i>
                                <?php else: ?>
                                    <i class="fas fa-times text-warning me-2"></i>
                                <?php endif; ?>
                                Errores ocultos en producción
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <?php if (file_exists(__DIR__ . '/.htaccess')): ?>
                                    <i class="fas fa-check text-success me-2"></i>
                                <?php else: ?>
                                    <i class="fas fa-times text-danger me-2"></i>
                                <?php endif; ?>
                                Protección .htaccess activa
                            </li>
                            <li class="mb-2">
                                <?php if (UPLOAD_MAX_SIZE <= 5 * 1024 * 1024): ?>
                                    <i class="fas fa-check text-success me-2"></i>
                                <?php else: ?>
                                    <i class="fas fa-times text-warning me-2"></i>
                                <?php endif; ?>
                                Límite de uploads configurado
                            </li>
                            <li class="mb-2">
                                <?php if (session_status() === PHP_SESSION_ACTIVE): ?>
                                    <i class="fas fa-check text-success me-2"></i>
                                <?php else: ?>
                                    <i class="fas fa-times text-danger me-2"></i>
                                <?php endif; ?>
                                Sesiones activas
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Configuración de Seguridad -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-cogs me-2"></i>Configuración de Seguridad
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="formSeguridad">
                    <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Límite de Intentos de Login</label>
                            <input type="number" class="form-control" value="5" min="3" max="10" readonly>
                            <small class="form-text text-muted">Máximo número de intentos fallidos antes del bloqueo temporal</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Duración de Sesión (minutos)</label>
                            <input type="number" class="form-control" value="60" min="15" max="480" readonly>
                            <small class="form-text text-muted">Tiempo de inactividad antes de cerrar sesión</small>
                        </div>
                        
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="https_forced" checked disabled>
                                <label class="form-check-label" for="https_forced">
                                    Forzar conexión HTTPS
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="backup_auto" checked disabled>
                                <label class="form-check-label" for="backup_auto">
                                    Backup automático semanal
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary" disabled>
                                <i class="fas fa-save me-2"></i>Guardar Configuración
                            </button>
                            <small class="text-muted ms-2">(Configuración avanzada requiere acceso al servidor)</small>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recomendaciones de Seguridad -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list-check me-2"></i>Lista de Verificación de Seguridad
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Configuración del Servidor</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check-circle text-success me-2"></i> Usar HTTPS siempre</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i> Mantener PHP actualizado</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i> Configurar firewall</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i> Limitar permisos de archivos</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i> Monitorear logs del sistema</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Configuración de la Aplicación</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check-circle text-success me-2"></i> Validar todas las entradas</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i> Usar prepared statements</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i> Implementar CSRF protection</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i> Hash de contraseñas seguras</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i> Limitar tamaños de upload</li>
                        </ul>
                    </div>
                </div>
                
                <div class="alert alert-warning mt-4">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Importante:</strong> Esta configuración debe revisarse regularmente y actualizarse 
                    según las mejores prácticas de seguridad más recientes.
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'inc/footer.php';
?>