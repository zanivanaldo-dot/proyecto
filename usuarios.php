<?php
session_start();
require_once 'inc/config.php';
require_once 'inc/conexion.php';
require_once 'inc/funciones.php';

// Verificar permisos
requireAuth();
requireAdmin();

$pdo = obtenerConexion();
$titulo_pagina = 'Gestión de Usuarios';
$icono_titulo = 'fas fa-users';

// Configuración de paginación
$por_pagina = 10;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $por_pagina;

// Búsqueda y filtros
$busqueda = isset($_GET['busqueda']) ? sanitizar($_GET['busqueda']) : '';
$filtro_rol = isset($_GET['rol']) ? sanitizar($_GET['rol']) : '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    // Verificar token CSRF
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Token de seguridad inválido.';
        redirigir('usuarios.php');
    }
    
    switch ($accion) {
        case 'crear':
            $nombre = sanitizar($_POST['nombre'] ?? '');
            $apellido = sanitizar($_POST['apellido'] ?? '');
            $email = sanitizar($_POST['email'] ?? '');
            $rol = sanitizar($_POST['rol'] ?? 'usuario');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Validaciones
            $errores = [];
            
            if (empty($nombre) || empty($apellido) || empty($email)) {
                $errores[] = 'Todos los campos son obligatorios.';
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errores[] = 'El email no es válido.';
            }
            
            if (empty($password)) {
                $errores[] = 'La contraseña es obligatoria.';
            } elseif (strlen($password) < 6) {
                $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
            } elseif ($password !== $confirm_password) {
                $errores[] = 'Las contraseñas no coinciden.';
            }
            
            // Verificar email único
            $sql = "SELECT id FROM usuarios WHERE email = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errores[] = 'El email ya está registrado.';
            }
            
            if (empty($errores)) {
                try {
                    $pdo->beginTransaction();
                    
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "INSERT INTO usuarios (nombre, apellido, email, password_hash, rol) 
                            VALUES (?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nombre, $apellido, $email, $password_hash, $rol]);
                    
                    $nuevo_id = $pdo->lastInsertId();
                    registrarLog($pdo, "Creó usuario ID: $nuevo_id ($nombre $apellido)");
                    
                    $pdo->commit();
                    $_SESSION['success'] = 'Usuario creado exitosamente.';
                    redirigir('usuarios.php');
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Error al crear usuario: " . $e->getMessage());
                    $_SESSION['error'] = 'Error al crear el usuario.';
                }
            } else {
                $_SESSION['error'] = implode('<br>', $errores);
            }
            break;
            
        case 'editar':
            $nombre = sanitizar($_POST['nombre'] ?? '');
            $apellido = sanitizar($_POST['apellido'] ?? '');
            $email = sanitizar($_POST['email'] ?? '');
            $rol = sanitizar($_POST['rol'] ?? 'usuario');
            $password = $_POST['password'] ?? '';
            
            // Validaciones
            $errores = [];
            
            if (empty($nombre) || empty($apellido) || empty($email)) {
                $errores[] = 'Todos los campos son obligatorios.';
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errores[] = 'El email no es válido.';
            }
            
            // Verificar email único (excluyendo el actual)
            $sql = "SELECT id FROM usuarios WHERE email = ? AND id != ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                $errores[] = 'El email ya está registrado.';
            }
            
            if (!empty($password) && strlen($password) < 6) {
                $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
            }
            
            if (empty($errores)) {
                try {
                    $pdo->beginTransaction();
                    
                    if (!empty($password)) {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "UPDATE usuarios SET nombre = ?, apellido = ?, email = ?, rol = ?, password_hash = ? 
                                WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$nombre, $apellido, $email, $rol, $password_hash, $id]);
                    } else {
                        $sql = "UPDATE usuarios SET nombre = ?, apellido = ?, email = ?, rol = ? 
                                WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$nombre, $apellido, $email, $rol, $id]);
                    }
                    
                    registrarLog($pdo, "Editó usuario ID: $id ($nombre $apellido)");
                    $pdo->commit();
                    $_SESSION['success'] = 'Usuario actualizado exitosamente.';
                    redirigir('usuarios.php');
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Error al actualizar usuario: " . $e->getMessage());
                    $_SESSION['error'] = 'Error al actualizar el usuario.';
                }
            } else {
                $_SESSION['error'] = implode('<br>', $errores);
            }
            break;
            
        case 'eliminar':
            // No permitir eliminarse a sí mismo
            if ($id == $_SESSION['usuario_id']) {
                $_SESSION['error'] = 'No puede eliminarse a sí mismo.';
                redirigir('usuarios.php');
            }
            
            try {
                $pdo->beginTransaction();
                
                // Obtener datos para el log
                $sql = "SELECT nombre, apellido FROM usuarios WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                $usuario = $stmt->fetch();
                
                $sql = "DELETE FROM usuarios WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                
                registrarLog($pdo, "Eliminó usuario ID: $id ({$usuario['nombre']} {$usuario['apellido']})");
                $pdo->commit();
                $_SESSION['success'] = 'Usuario eliminado exitosamente.';
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Error al eliminar usuario: " . $e->getMessage());
                $_SESSION['error'] = 'Error al eliminar el usuario.';
            }
            break;
    }
    
    redirigir('usuarios.php');
}

// Obtener usuarios con filtros
$where_conditions = [];
$params = [];

if (!empty($busqueda)) {
    $where_conditions[] = "(nombre LIKE ? OR apellido LIKE ? OR email LIKE ?)";
    $like_param = "%$busqueda%";
    $params[] = $like_param;
    $params[] = $like_param;
    $params[] = $like_param;
}

if (!empty($filtro_rol)) {
    $where_conditions[] = "rol = ?";
    $params[] = $filtro_rol;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Consulta para contar total
$sql_count = "SELECT COUNT(*) as total FROM usuarios $where_sql";
$stmt = $pdo->prepare($sql_count);
$stmt->execute($params);
$total_usuarios = $stmt->fetch()['total'];
$total_paginas = ceil($total_usuarios / $por_pagina);

// Consulta para obtener usuarios
$sql = "SELECT id, nombre, apellido, email, rol, creado_en, ultimo_acceso 
        FROM usuarios 
        $where_sql 
        ORDER BY creado_en DESC 
        LIMIT $offset, $por_pagina";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

// Breadcrumb
$breadcrumb = generarBreadcrumb([
    ['text' => 'Usuarios', 'url' => 'usuarios.php']
]);

// Acciones del título
$acciones_titulo = '
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUsuario">
        <i class="fas fa-plus me-1"></i>Nuevo Usuario
    </button>
';

require_once 'inc/header.php';
?>

<div class="row">
    <div class="col-12">
        <!-- Filtros y Búsqueda -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <input type="text" class="form-control" name="busqueda" placeholder="Buscar por nombre, apellido o email..." 
                               value="<?= htmlspecialchars($busqueda) ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="rol">
                            <option value="">Todos los roles</option>
                            <option value="admin" <?= $filtro_rol === 'admin' ? 'selected' : '' ?>>Administrador</option>
                            <option value="usuario" <?= $filtro_rol === 'usuario' ? 'selected' : '' ?>>Usuario</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="fas fa-search me-1"></i>Buscar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de Usuarios -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($usuarios)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5>No se encontraron usuarios</h5>
                        <p class="text-muted"><?= empty($busqueda) && empty($filtro_rol) ? 'No hay usuarios registrados.' : 'Intente con otros filtros.' ?></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th>Último Acceso</th>
                                    <th>Registrado</th>
                                    <th width="120">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($usuario['email']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $usuario['rol'] === 'admin' ? 'warning' : 'secondary' ?>">
                                                <?= $usuario['rol'] === 'admin' ? 'Administrador' : 'Usuario' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($usuario['ultimo_aceso']): ?>
                                                <?= date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Nunca</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($usuario['creado_en'])) ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" 
                                                        onclick="editarUsuario(<?= htmlspecialchars(json_encode($usuario)) ?>)"
                                                        title="Editar usuario">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($usuario['id'] != $_SESSION['usuario_id']): ?>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="eliminarUsuario(<?= $usuario['id'] ?>, '<?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']) ?>')"
                                                            title="Eliminar usuario">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-outline-secondary" disabled title="No puede eliminarse a sí mismo">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <?php if ($total_paginas > 1): ?>
                        <nav aria-label="Paginación de usuarios">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])) ?>">Anterior</a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                    <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?= $pagina >= $total_paginas ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])) ?>">Siguiente</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                    
                    <div class="text-muted text-center">
                        Mostrando <?= count($usuarios) ?> de <?= $total_usuarios ?> usuarios
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Crear/Editar Usuario -->
<div class="modal fade" id="modalUsuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="formUsuario" data-validar>
                <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                <input type="hidden" name="accion" id="accion" value="crear">
                <input type="hidden" name="id" id="usuario_id" value="">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label">Nombre *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" 
                                   data-validar="requerido" required>
                            <div class="invalid-feedback">El nombre es obligatorio.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="apellido" class="form-label">Apellido *</label>
                            <input type="text" class="form-control" id="apellido" name="apellido" 
                                   data-validar="requerido" required>
                            <div class="invalid-feedback">El apellido es obligatorio.</div>
                        </div>
                        
                        <div class="col-12">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   data-validar="email" required>
                            <div class="invalid-feedback">Ingrese un email válido.</div>
                        </div>
                        
                        <div class="col-12">
                            <label for="rol" class="form-label">Rol *</label>
                            <select class="form-select" id="rol" name="rol" required>
                                <option value="usuario">Usuario</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        
                        <div class="col-12" id="campo-password">
                            <label for="password" class="form-label">Contraseña *</label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   data-validar="requerido" required>
                            <div class="invalid-feedback">La contraseña es obligatoria.</div>
                        </div>
                        
                        <div class="col-12" id="campo-confirm-password">
                            <label for="confirm_password" class="form-label">Confirmar Contraseña *</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   data-validar="requerido" required>
                            <div class="invalid-feedback">Debe confirmar la contraseña.</div>
                        </div>
                        
                        <div class="col-12" id="campo-password-opcional" style="display: none;">
                            <label for="password_editar" class="form-label">Nueva Contraseña (opcional)</label>
                            <input type="password" class="form-control" id="password_editar" name="password">
                            <div class="form-text">Dejar en blanco para mantener la contraseña actual.</div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Confirmación de Eliminación -->
<div class="modal fade" id="modalConfirmarEliminar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Está seguro de que desea eliminar al usuario <strong id="nombreUsuarioEliminar"></strong>?</p>
                <p class="text-danger"><small>Esta acción no se puede deshacer.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" id="formEliminarUsuario">
                    <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" id="usuario_id_eliminar">
                    <button type="submit" class="btn btn-danger">Eliminar Usuario</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$scripts_extra = '
<script>
function editarUsuario(usuario) {
    document.getElementById("modalTitulo").textContent = "Editar Usuario";
    document.getElementById("accion").value = "editar";
    document.getElementById("usuario_id").value = usuario.id;
    document.getElementById("nombre").value = usuario.nombre;
    document.getElementById("apellido").value = usuario.apellido;
    document.getElementById("email").value = usuario.email;
    document.getElementById("rol").value = usuario.rol;
    
    // Cambiar campos de contraseña para edición
    document.getElementById("campo-password").style.display = "none";
    document.getElementById("campo-confirm-password").style.display = "none";
    document.getElementById("campo-password-opcional").style.display = "block";
    
    // Remover atributos required para edición
    document.getElementById("password").removeAttribute("required");
    document.getElementById("confirm_password").removeAttribute("required");
    
    var modal = new bootstrap.Modal(document.getElementById("modalUsuario"));
    modal.show();
}

function eliminarUsuario(id, nombre) {
    document.getElementById("nombreUsuarioEliminar").textContent = nombre;
    document.getElementById("usuario_id_eliminar").value = id;
    
    var modal = new bootstrap.Modal(document.getElementById("modalConfirmarEliminar"));
    modal.show();
}

// Resetear modal cuando se cierre
document.getElementById("modalUsuario").addEventListener("hidden.bs.modal", function() {
    document.getElementById("formUsuario").reset();
    document.getElementById("modalTitulo").textContent = "Nuevo Usuario";
    document.getElementById("accion").value = "crear";
    document.getElementById("usuario_id").value = "";
    
    // Restaurar campos de contraseña para creación
    document.getElementById("campo-password").style.display = "block";
    document.getElementById("campo-confirm-password").style.display = "block";
    document.getElementById("campo-password-opcional").style.display = "none";
    
    // Agregar atributos required para creación
    document.getElementById("password").setAttribute("required", "required");
    document.getElementById("confirm_password").setAttribute("required", "required");
});

// Validación de contraseñas coincidentes
document.getElementById("formUsuario").addEventListener("submit", function(e) {
    const password = document.getElementById("password").value;
    const confirmPassword = document.getElementById("confirm_password").value;
    const accion = document.getElementById("accion").value;
    
    if (accion === "crear" && password !== confirmPassword) {
        e.preventDefault();
        alert("Las contraseñas no coinciden.");
        return false;
    }
});
</script>
';

require_once 'inc/footer.php';
?>