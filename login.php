<?php
// Incluir configuración segura de sesión primero
require_once 'inc/sesion_segura.php';
iniciarSesionSegura();

require_once 'inc/config.php';
require_once 'inc/conexion.php';
require_once 'inc/funciones.php';

// Si el usuario ya está logueado, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$pdo = obtenerConexion();
$error = '';

// Procesar el formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizar($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $recordar = isset($_POST['recordar']);

    // Validar campos
    if (empty($email) || empty($password)) {
        $error = 'Por favor, complete todos los campos.';
    } elseif (!validarEmail($email)) {
        $error = 'El email no tiene un formato válido.';
    } else {
        // Verificar si el usuario puede intentar login (límite de intentos)
        if (!puedeIntentarLogin($pdo, $email)) {
            $error = 'Demasiados intentos fallidos. Espere 15 minutos antes de intentar nuevamente.';
        } else {
            // Buscar usuario en la base de datos
            $sql = "SELECT id, nombre, email, password, rol, activo, intentos_fallidos, ultimo_intento 
                    FROM usuarios 
                    WHERE email = ? AND activo = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($password, $usuario['password'])) {
                // Login exitoso - resetear intentos fallidos
                $sql_update = "UPDATE usuarios SET intentos_fallidos = 0, ultimo_intento = NOW() WHERE id = ?";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([$usuario['id']]);

                // Establecer variables de sesión
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['usuario_rol'] = $usuario['rol'];
                $_SESSION['ULTIMA_ACTIVIDAD'] = time();
                $_SESSION['fingerprint'] = md5(
                    $_SERVER['HTTP_USER_AGENT'] . 
                    ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') .
                    ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '')
                );

                // Regenerar ID de sesión para prevenir fixation attacks
                regenerarSidSeguro();

                // Registrar log de seguridad
                registrarEventoSeguridad($pdo, "Login exitoso", "login_exitoso");

                // Redirigir al dashboard
                header('Location: index.php');
                exit;
            } else {
                // Login fallido - incrementar contador
                if ($usuario) {
                    $nuevos_intentos = $usuario['intentos_fallidos'] + 1;
                    $sql_update = "UPDATE usuarios SET intentos_fallidos = ?, ultimo_intento = NOW() WHERE id = ?";
                    $stmt_update = $pdo->prepare($sql_update);
                    $stmt_update->execute([$nuevos_intentos, $usuario['id']]);
                }

                $error = 'Credenciales incorrectas.';
                registrarEventoSeguridad($pdo, "Login fallido para: $email", "login_fallido");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?= APP_NAME ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            max-width: 400px;
            width: 100%;
            margin: 0 auto;
            animation: fadeIn 0.8s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .card-header {
            background: var(--primary-color);
            border-radius: 15px 15px 0 0 !important;
            padding: 2rem 2rem 1rem;
            text-align: center;
            border: none;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .logo {
            color: white;
            margin-bottom: 1rem;
        }
        
        .logo i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .logo h1 {
            font-size: 1.5rem;
            margin: 0;
            font-weight: 300;
        }
        
        .logo .version {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .input-group-text {
            background: var(--primary-color);
            border: 2px solid #e9ecef;
            border-right: none;
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--secondary-color), #2980b9);
            border: none;
            border-radius: 8px;
            padding: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        .form-check-input:checked {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        .password-toggle {
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .password-toggle:hover {
            color: var(--secondary-color);
        }
        
        .features-list {
            list-style: none;
            padding: 0;
            margin: 2rem 0 0 0;
        }
        
        .features-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .features-list li:last-child {
            border-bottom: none;
        }
        
        .features-list i {
            color: var(--secondary-color);
            margin-right: 0.5rem;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 2rem;
            color: #6c757d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <div class="logo">
                    <i class="fas fa-building"></i>
                    <h1><?= APP_NAME ?></h1>
                    <div class="version">v<?= APP_VERSION ?></div>
                </div>
            </div>
            <div class="card-body">
                <h4 class="text-center mb-4" style="color: var(--primary-color);">
                    <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                </h4>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="formLogin" novalidate>
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                   placeholder="usuario@ejemplo.com" required
                                   pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                            <div class="invalid-feedback">
                                Por favor ingrese un email válido.
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Ingrese su contraseña" required
                                   minlength="6">
                            <button type="button" class="btn btn-outline-secondary password-toggle" 
                                    onclick="togglePassword('password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="invalid-feedback">
                                La contraseña debe tener al menos 6 caracteres.
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="recordar" name="recordar">
                        <label class="form-check-label" for="recordar">Recordar sesión</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-3">
                        <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                    </button>
                    
                    <div class="text-center">
                        <a href="recuperar_password.php" class="text-decoration-none">
                            <i class="fas fa-key me-1"></i>¿Olvidó su contraseña?
                        </a>
                    </div>
                </form>

                <!-- Información del sistema -->
                <div class="login-footer">
                    <div class="row text-center">
                        <div class="col-12">
                            <small>
                                <i class="fas fa-shield-alt me-1"></i>
                                Sistema Seguro | 
                                <span id="fecha-actual"></span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Características del sistema (solo en pantallas grandes) -->
        <div class="d-none d-md-block mt-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-center mb-3">
                        <i class="fas fa-star me-2"></i>Características del Sistema
                    </h6>
                    <ul class="features-list">
                        <li><i class="fas fa-check-circle"></i> Gestion de contratos y pagos</li>
                        <li><i class="fas fa-check-circle"></i> Control de expensas y reservas</li>
                        <li><i class="fas fa-check-circle"></i> Reportes y estadísticas</li>
                        <li><i class="fas fa-check-circle"></i> Backup automático de datos</li>
                        <li><i class="fas fa-check-circle"></i> Interfaz responsive y segura</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Mostrar fecha actual
        document.getElementById('fecha-actual').textContent = new Date().toLocaleDateString('es-AR');
        
        // Toggle password visibility
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Validación del formulario con Bootstrap
        (function () {
            'use strict'
            const form = document.getElementById('formLogin');
            
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
            }, false);
            
            // Validación en tiempo real
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            
            email.addEventListener('input', function() {
                if (email.validity.valid) {
                    email.classList.remove('is-invalid');
                    email.classList.add('is-valid');
                } else {
                    email.classList.remove('is-valid');
                    email.classList.add('is-invalid');
                }
            });
            
            password.addEventListener('input', function() {
                if (password.validity.valid) {
                    password.classList.remove('is-invalid');
                    password.classList.add('is-valid');
                } else {
                    password.classList.remove('is-valid');
                    password.classList.add('is-invalid');
                }
            });
        })();
        
        // Efectos de focus mejorados
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                if (this.value === '') {
                    this.parentElement.classList.remove('focused');
                }
            });
        });
        
        // Prevenir envío múltiple del formulario
        let formSubmitted = false;
        document.getElementById('formLogin').addEventListener('submit', function(e) {
            if (formSubmitted) {
                e.preventDefault();
                return;
            }
            
            formSubmitted = true;
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Iniciando sesión...';
            submitBtn.disabled = true;
        });
        
        // Auto-focus en el campo de email
        document.getElementById('email').focus();
    </script>
</body>
</html>