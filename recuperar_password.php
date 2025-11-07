<?php
// Incluir configuración segura de sesión primero
require_once 'inc/sesion_segura.php';
iniciarSesionSegura();

require_once 'inc/config.php';

// Si el usuario ya está logueado, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .recovery-container {
            max-width: 400px;
            width: 100%;
            margin: 0 auto;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .card-header {
            background: #2c3e50;
            border-radius: 15px 15px 0 0 !important;
            padding: 2rem;
            text-align: center;
            border: none;
            color: white;
        }
    </style>
</head>
<body>
    <div class="recovery-container">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-key fa-2x mb-3"></i>
                <h4>Recuperar Contraseña</h4>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Esta funcionalidad está en desarrollo. Por favor contacte al administrador del sistema.
                </div>
                
                <form>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" placeholder="usuario@ejemplo.com" disabled>
                    </div>
                    
                    <button type="button" class="btn btn-primary w-100 mb-3" disabled>
                        <i class="fas fa-paper-plane me-2"></i>Enviar Enlace de Recuperación
                    </button>
                    
                    <div class="text-center">
                        <a href="login.php" class="text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i>Volver al Login
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>