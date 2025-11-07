<?php
session_start();
require_once 'inc/config.php';
require_once 'inc/conexion.php';
require_once 'inc/funciones.php';

requireAuth();

$pdo = obtenerConexion();
$titulo_pagina = 'Gestión de Inquilinos';
$icono_titulo = 'fas fa-users';

// Configuración de paginación
$por_pagina = 10;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $por_pagina;

// Búsqueda y filtros
$busqueda = isset($_GET['busqueda']) ? sanitizar($_GET['busqueda']) : '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    // Verificar token CSRF
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Token de seguridad inválido.';
        redirigir('inquilinos.php');
    }
    
    switch ($accion) {
        case 'crear':
            $nombre = sanitizar($_POST['nombre'] ?? '');
            $apellido = sanitizar($_POST['apellido'] ?? '');
            $dni = sanitizar($_POST['dni'] ?? '');
            $email = sanitizar($_POST['email'] ?? '');
            $telefono = sanitizar($_POST['telefono'] ?? '');
            $direccion = sanitizar($_POST['direccion'] ?? '');
            
            // Validaciones
            $errores = [];
            
            if (empty($nombre) || empty($apellido) || empty($dni)) {
                $errores[] = 'Nombre, apellido y DNI son obligatorios.';
            }
            
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errores[] = 'El email no es válido.';
            }
            
            // Verificar DNI único
            $sql = "SELECT id FROM inquilinos WHERE dni = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$dni]);
            if ($stmt->fetch()) {
                $errores[] = 'El DNI ya está registrado.';
            }
            
            if (empty($errores)) {
                try {
                    $pdo->beginTransaction();
                    
                    $sql = "INSERT INTO inquilinos (nombre, apellido, dni, email, telefono, direccion) 
                            VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nombre, $apellido, $dni, $email, $telefono, $direccion]);
                    
                    $nuevo_id = $pdo->lastInsertId();
                    registrarLog($pdo, "Creó inquilino ID: $nuevo_id ($nombre $apellido)");
                    
                    $pdo->commit();
                    $_SESSION['success'] = 'Inquilino creado exitosamente.';
                    redirigir('inquilinos.php');
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Error al crear inquilino: " . $e->getMessage());
                    $_SESSION['error'] = 'Error al crear el inquilino.';
                }
            } else {
                $_SESSION['error'] = implode('<br>', $errores);
            }
            break;
            
        case 'editar':
            $nombre = sanitizar($_POST['nombre'] ?? '');
            $apellido = sanitizar($_POST['apellido'] ?? '');
            $dni = sanitizar($_POST['dni'] ?? '');
            $email = sanitizar($_POST['email'] ?? '');
            $telefono = sanitizar($_POST['telefono'] ?? '');
            $direccion = sanitizar($_POST['direccion'] ?? '');
            
            // Validaciones
            $errores = [];
            
            if (empty($nombre) || empty($apellido) || empty($dni)) {
                $errores[] = 'Nombre, apellido y DNI son obligatorios.';
            }
            
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errores[] = 'El email no es válido.';
            }
            
            // Verificar DNI único (excluyendo el actual)
            $sql = "SELECT id FROM inquilinos WHERE dni = ? AND id != ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$dni, $id]);
            if ($stmt->fetch()) {
                $errores[] = 'El DNI ya está registrado.';
            }
            
            if (empty($errores)) {
                try {
                    $pdo->beginTransaction();
                    
                    $sql = "UPDATE inquilinos SET nombre = ?, apellido = ?, dni = ?, email = ?, telefono = ?, direccion = ? 
                            WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nombre, $apellido, $dni, $email, $telefono, $direccion, $id]);
                    
                    registrarLog($pdo, "Editó inquilino ID: $id ($nombre $apellido)");
                    $pdo->commit();
                    $_SESSION['success'] = 'Inquilino actualizado exitosamente.';
                    redirigir('inquilinos.php');
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Error al actualizar inquilino: " . $e->getMessage());
                    $_SESSION['error'] = 'Error al actualizar el inquilino.';
                }
            } else {
                $_SESSION['error'] = implode('<br>', $errores);
            }
            break;
            
        case 'eliminar':
            try {
                $pdo->beginTransaction();
                
                // Obtener datos para el log
                $sql = "SELECT nombre, apellido FROM inquilinos WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                $inquilino = $stmt->fetch();
                
                // Verificar si tiene contratos activos
                $sql = "SELECT COUNT(*) as total FROM contratos WHERE inquilino_id = ? AND estado = 'activo'";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                $tiene_contratos = $stmt->fetch()['total'];
                
                if ($tiene_contratos > 0) {
                    $_SESSION['error'] = 'No se puede eliminar el inquilino porque tiene contratos activos.';
                    $pdo->rollBack();
                    redirigir('inquilinos.php');
                }
                
                $sql = "DELETE FROM inquilinos WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                
                registrarLog($pdo, "Eliminó inquilino ID: $id ({$inquilino['nombre']} {$inquilino['apellido']})");
                $pdo->commit();
                $_SESSION['success'] = 'Inquilino eliminado exitosamente.';
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Error al eliminar inquilino: " . $e->getMessage());
                $_SESSION['error'] = 'Error al eliminar el inquilino.';
            }
            break;
    }
    
    redirigir('inquilinos.php');
}

// Obtener inquilinos con filtros
$where_conditions = [];
$params = [];

if (!empty($busqueda)) {
    $where_conditions[] = "(nombre LIKE ? OR apellido LIKE ? OR dni LIKE ? OR email LIKE ?)";
    $like_param = "%$busqueda%";
    $params[] = $like_param;
    $params[] = $like_param;
    $params[] = $like_param;
    $params[] = $like_param;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Consulta para contar total
$sql_count = "SELECT COUNT(*) as total FROM inquilinos $where_sql";
$stmt = $pdo->prepare($sql_count);
$stmt->execute($params);
$total_inquilinos = $stmt->fetch()['total'];
$total_paginas = ceil($total_inquilinos / $por_pagina);

// Consulta para obtener inquilinos
$sql = "SELECT id, nombre, apellido, dni, email, telefono, direccion, creado_en 
        FROM inquilinos 
        $where_sql 
        ORDER BY creado_en DESC 
        LIMIT $offset, $por_pagina";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inquilinos = $stmt->fetchAll();

// Breadcrumb
$breadcrumb = generarBreadcrumb([
    ['text' => 'Inquilinos', 'url' => 'inquilinos.php']
]);

// Acciones del título
$acciones_titulo = '
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalInquilino">
        <i class="fas fa-plus me-1"></i>Nuevo Inquilino
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
                    <div class="col-md-8">
                        <input type="text" class="form-control" name="busqueda" placeholder="Buscar por nombre, apellido, DNI o email..." 
                               value="<?= htmlspecialchars($busqueda) ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="fas fa-search me-1"></i>Buscar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de Inquilinos -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($inquilinos)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5>No se encontraron inquilinos</h5>
                        <p class="text-muted"><?= empty($busqueda) ? 'No hay inquilinos registrados.' : 'Intente con otros filtros.' ?></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nombre y Apellido</th>
                                    <th>DNI</th>
                                    <th>Contacto</th>
                                    <th>Dirección</th>
                                    <th>Registrado</th>
                                    <th width="120">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inquilinos as $inquilino): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($inquilino['nombre'] . ' ' . $inquilino['apellido']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($inquilino['dni']) ?></td>
                                        <td>
                                            <?php if ($inquilino['email']): ?>
                                                <div><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($inquilino['email']) ?></div>
                                            <?php endif; ?>
                                            <?php if ($inquilino['telefono']): ?>
                                                <div><i class="fas fa-phone me-1"></i><?= htmlspecialchars($inquilino['telefono']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($inquilino['direccion']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($inquilino['creado_en'])) ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" 
                                                        onclick="editarInquilino(<?= htmlspecialchars(json_encode($inquilino)) ?>)"
                                                        title="Editar inquilino">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        onclick="eliminarInquilino(<?= $inquilino['id'] ?>, '<?= htmlspecialchars($inquilino['nombre'] . ' ' . $inquilino['apellido']) ?>')"
                                                        title="Eliminar inquilino">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <?php if ($total_paginas > 1): ?>
                        <nav aria-label="Paginación de inquilinos">
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
                        Mostrando <?= count($inquilinos) ?> de <?= $total_inquilinos ?> inquilinos
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Crear/Editar Inquilino -->
<div class="modal fade" id="modalInquilino" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formInquilino" data-validar>
                <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                <input type="hidden" name="accion" id="accion" value="crear">
                <input type="hidden" name="id" id="inquilino_id" value="">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">Nuevo Inquilino</h5>
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
                        
                        <div class="col-md-6">
                            <label for="dni" class="form-label">DNI *</label>
                            <input type="text" class="form-control" id="dni" name="dni" 
                                   data-validar="requerido" required>
                            <div class="invalid-feedback">El DNI es obligatorio.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   data-validar="email">
                            <div class="invalid-feedback">Ingrese un email válido.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="telefono" name="telefono">
                        </div>
                        
                        <div class="col-12">
                            <label for="direccion" class="form-label">Dirección</label>
                            <textarea class="form-control" id="direccion" name="direccion" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Inquilino</button>
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
                <p>¿Está seguro de que desea eliminar al inquilino <strong id="nombreInquilinoEliminar"></strong>?</p>
                <p class="text-danger"><small>Esta acción no se puede deshacer.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" id="formEliminarInquilino">
                    <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" id="inquilino_id_eliminar">
                    <button type="submit" class="btn btn-danger">Eliminar Inquilino</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$scripts_extra = '
<script>
function editarInquilino(inquilino) {
    document.getElementById("modalTitulo").textContent = "Editar Inquilino";
    document.getElementById("accion").value = "editar";
    document.getElementById("inquilino_id").value = inquilino.id;
    document.getElementById("nombre").value = inquilino.nombre;
    document.getElementById("apellido").value = inquilino.apellido;
    document.getElementById("dni").value = inquilino.dni;
    document.getElementById("email").value = inquilino.email;
    document.getElementById("telefono").value = inquilino.telefono;
    document.getElementById("direccion").value = inquilino.direccion;
    
    var modal = new bootstrap.Modal(document.getElementById("modalInquilino"));
    modal.show();
}

function eliminarInquilino(id, nombre) {
    document.getElementById("nombreInquilinoEliminar").textContent = nombre;
    document.getElementById("inquilino_id_eliminar").value = id;
    
    var modal = new bootstrap.Modal(document.getElementById("modalConfirmarEliminar"));
    modal.show();
}

// Resetear modal cuando se cierre
document.getElementById("modalInquilino").addEventListener("hidden.bs.modal", function() {
    document.getElementById("formInquilino").reset();
    document.getElementById("modalTitulo").textContent = "Nuevo Inquilino";
    document.getElementById("accion").value = "crear";
    document.getElementById("inquilino_id").value = "";
});
</script>
';

require_once 'inc/footer.php';
?>