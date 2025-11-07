<?php
session_start();
require_once 'inc/config.php';
require_once 'inc/conexion.php';
require_once 'inc/funciones.php';

requireAuth();

$pdo = obtenerConexion();
$titulo_pagina = 'Gestión de Contratos';
$icono_titulo = 'fas fa-file-contract';

// Configuración de paginación
$por_pagina = 10;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $por_pagina;

// Búsqueda y filtros
$busqueda = isset($_GET['busqueda']) ? sanitizar($_GET['busqueda']) : '';
$filtro_estado = isset($_GET['estado']) ? sanitizar($_GET['estado']) : '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    // Verificar token CSRF
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Token de seguridad inválido.';
        redirigir('contratos.php');
    }
    
    switch ($accion) {
        case 'crear':
            $inquilino_id = (int)($_POST['inquilino_id'] ?? 0);
            $unidad_id = (int)($_POST['unidad_id'] ?? 0);
            $fecha_inicio = sanitizar($_POST['fecha_inicio'] ?? '');
            $fecha_vencimiento = sanitizar($_POST['fecha_vencimiento'] ?? '');
            $monto_alquiler = (float)($_POST['monto_alquiler'] ?? 0);
            $moneda = sanitizar($_POST['moneda'] ?? 'ARS');
            $dia_vencimiento = (int)($_POST['dia_vencimiento'] ?? 1);
            $deposito_garantia = (float)($_POST['deposito_garantia'] ?? 0);
            
            // Validaciones
            $errores = [];
            
            if (empty($inquilino_id) || empty($unidad_id)) {
                $errores[] = 'Inquilino y unidad son obligatorios.';
            }
            
            if (empty($fecha_inicio) || empty($fecha_vencimiento)) {
                $errores[] = 'Las fechas de inicio y vencimiento son obligatorias.';
            }
            
            if ($monto_alquiler <= 0) {
                $errores[] = 'El monto del alquiler debe ser mayor a 0.';
            }
            
            if ($dia_vencimiento < 1 || $dia_vencimiento > 31) {
                $errores[] = 'El día de vencimiento debe ser entre 1 y 31.';
            }
            
            // Verificar que la unidad no tenga un contrato activo
            $sql = "SELECT id FROM contratos WHERE unidad_id = ? AND estado = 'activo'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$unidad_id]);
            if ($stmt->fetch()) {
                $errores[] = 'La unidad ya tiene un contrato activo.';
            }
            
            if (empty($errores)) {
                try {
                    $pdo->beginTransaction();
                    
                    $sql = "INSERT INTO contratos (inquilino_id, unidad_id, fecha_inicio, fecha_vencimiento, 
                            monto_alquiler, moneda, dia_vencimiento, deposito_garantia, estado) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'activo')";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$inquilino_id, $unidad_id, $fecha_inicio, $fecha_vencimiento, 
                                    $monto_alquiler, $moneda, $dia_vencimiento, $deposito_garantia]);
                    
                    $nuevo_id = $pdo->lastInsertId();
                    registrarLog($pdo, "Creó contrato ID: $nuevo_id para unidad $unidad_id");
                    
                    $pdo->commit();
                    $_SESSION['success'] = 'Contrato creado exitosamente.';
                    redirigir('contratos.php');
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Error al crear contrato: " . $e->getMessage());
                    $_SESSION['error'] = 'Error al crear el contrato.';
                }
            } else {
                $_SESSION['error'] = implode('<br>', $errores);
            }
            break;
            
        case 'editar':
            $inquilino_id = (int)($_POST['inquilino_id'] ?? 0);
            $unidad_id = (int)($_POST['unidad_id'] ?? 0);
            $fecha_inicio = sanitizar($_POST['fecha_inicio'] ?? '');
            $fecha_vencimiento = sanitizar($_POST['fecha_vencimiento'] ?? '');
            $monto_alquiler = (float)($_POST['monto_alquiler'] ?? 0);
            $moneda = sanitizar($_POST['moneda'] ?? 'ARS');
            $dia_vencimiento = (int)($_POST['dia_vencimiento'] ?? 1);
            $deposito_garantia = (float)($_POST['deposito_garantia'] ?? 0);
            $estado = sanitizar($_POST['estado'] ?? 'activo');
            
            // Validaciones
            $errores = [];
            
            if (empty($inquilino_id) || empty($unidad_id)) {
                $errores[] = 'Inquilino y unidad son obligatorios.';
            }
            
            if (empty($fecha_inicio) || empty($fecha_vencimiento)) {
                $errores[] = 'Las fechas de inicio y vencimiento son obligatorias.';
            }
            
            if ($monto_alquiler <= 0) {
                $errores[] = 'El monto del alquiler debe ser mayor a 0.';
            }
            
            if ($dia_vencimiento < 1 || $dia_vencimiento > 31) {
                $errores[] = 'El día de vencimiento debe ser entre 1 y 31.';
            }
            
            // Verificar que la unidad no tenga otro contrato activo (excluyendo el actual)
            $sql = "SELECT id FROM contratos WHERE unidad_id = ? AND estado = 'activo' AND id != ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$unidad_id, $id]);
            if ($stmt->fetch()) {
                $errores[] = 'La unidad ya tiene otro contrato activo.';
            }
            
            if (empty($errores)) {
                try {
                    $pdo->beginTransaction();
                    
                    $sql = "UPDATE contratos SET inquilino_id = ?, unidad_id = ?, fecha_inicio = ?, 
                            fecha_vencimiento = ?, monto_alquiler = ?, moneda = ?, dia_vencimiento = ?, 
                            deposito_garantia = ?, estado = ? 
                            WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$inquilino_id, $unidad_id, $fecha_inicio, $fecha_vencimiento, 
                                    $monto_alquiler, $moneda, $dia_vencimiento, $deposito_garantia, $estado, $id]);
                    
                    registrarLog($pdo, "Editó contrato ID: $id para unidad $unidad_id");
                    $pdo->commit();
                    $_SESSION['success'] = 'Contrato actualizado exitosamente.';
                    redirigir('contratos.php');
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Error al actualizar contrato: " . $e->getMessage());
                    $_SESSION['error'] = 'Error al actualizar el contrato.';
                }
            } else {
                $_SESSION['error'] = implode('<br>', $errores);
            }
            break;
            
        case 'eliminar':
            try {
                $pdo->beginTransaction();
                
                // Obtener datos para el log
                $sql = "SELECT unidad_id FROM contratos WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                $contrato = $stmt->fetch();
                
                $sql = "DELETE FROM contratos WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                
                registrarLog($pdo, "Eliminó contrato ID: $id de unidad {$contrato['unidad_id']}");
                $pdo->commit();
                $_SESSION['success'] = 'Contrato eliminado exitosamente.';
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Error al eliminar contrato: " . $e->getMessage());
                $_SESSION['error'] = 'Error al eliminar el contrato.';
            }
            break;
            
        case 'renovar':
            $contrato_id = (int)($_POST['id'] ?? 0);
            $nueva_fecha_vencimiento = sanitizar($_POST['nueva_fecha_vencimiento'] ?? '');
            $nuevo_monto = (float)($_POST['nuevo_monto'] ?? 0);
            $nueva_moneda = sanitizar($_POST['nueva_moneda'] ?? 'ARS');
            
            // Validaciones
            $errores = [];
            
            if (empty($nueva_fecha_vencimiento)) {
                $errores[] = 'La nueva fecha de vencimiento es obligatoria.';
            }
            
            if ($nuevo_monto <= 0) {
                $errores[] = 'El nuevo monto debe ser mayor a 0.';
            }
            
            if (empty($errores)) {
                try {
                    $pdo->beginTransaction();
                    
                    // Obtener datos del contrato actual
                    $sql = "SELECT * FROM contratos WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$contrato_id]);
                    $contrato_actual = $stmt->fetch();
                    
                    if (!$contrato_actual) {
                        throw new Exception('Contrato no encontrado.');
                    }
                    
                    // Marcar contrato actual como renovado
                    $sql = "UPDATE contratos SET estado = 'renovado' WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$contrato_id]);
                    
                    // Crear nuevo contrato
                    $sql = "INSERT INTO contratos (inquilino_id, unidad_id, fecha_inicio, fecha_vencimiento, 
                            monto_alquiler, moneda, dia_vencimiento, deposito_garantia, estado) 
                            VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, 'activo')";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$contrato_actual['inquilino_id'], $contrato_actual['unidad_id'], 
                                    $nueva_fecha_vencimiento, $nuevo_monto, $nueva_moneda, 
                                    $contrato_actual['dia_vencimiento'], $contrato_actual['deposito_garantia']]);
                    
                    $nuevo_id = $pdo->lastInsertId();
                    registrarLog($pdo, "Renovó contrato ID: $contrato_id a nuevo ID: $nuevo_id");
                    
                    $pdo->commit();
                    $_SESSION['success'] = 'Contrato renovado exitosamente.';
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log("Error al renovar contrato: " . $e->getMessage());
                    $_SESSION['error'] = 'Error al renovar el contrato.';
                }
            } else {
                $_SESSION['error'] = implode('<br>', $errores);
            }
            break;
    }
    
    redirigir('contratos.php');
}

// Obtener contratos con filtros
$where_conditions = [];
$params = [];

if (!empty($busqueda)) {
    $where_conditions[] = "(i.nombre LIKE ? OR i.apellido LIKE ? OR u.numero LIKE ?)";
    $like_param = "%$busqueda%";
    $params[] = $like_param;
    $params[] = $like_param;
    $params[] = $like_param;
}

if (!empty($filtro_estado)) {
    $where_conditions[] = "c.estado = ?";
    $params[] = $filtro_estado;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Consulta para contar total
$sql_count = "SELECT COUNT(*) as total 
              FROM contratos c
              INNER JOIN inquilinos i ON c.inquilino_id = i.id
              INNER JOIN unidades u ON c.unidad_id = u.id
              $where_sql";
$stmt = $pdo->prepare($sql_count);
$stmt->execute($params);
$total_contratos = $stmt->fetch()['total'];
$total_paginas = ceil($total_contratos / $por_pagina);

// Consulta para obtener contratos
$sql = "SELECT c.*, i.nombre as inquilino_nombre, i.apellido as inquilino_apellido, 
               u.numero as unidad_numero, u.tipo as unidad_tipo, u.descripcion as unidad_descripcion,
               e.nombre as edificio_nombre
        FROM contratos c
        INNER JOIN inquilinos i ON c.inquilino_id = i.id
        INNER JOIN unidades u ON c.unidad_id = u.id
        INNER JOIN edificios e ON u.edificio_id = e.id
        $where_sql 
        ORDER BY c.creado_en DESC 
        LIMIT $offset, $por_pagina";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contratos = $stmt->fetchAll();

// Obtener listas para selects
$inquilinos = $pdo->query("SELECT id, nombre, apellido FROM inquilinos ORDER BY nombre, apellido")->fetchAll();
$unidades = $pdo->query("SELECT id, numero, tipo, descripcion FROM unidades WHERE activo = 1 ORDER BY numero")->fetchAll();

// Breadcrumb
$breadcrumb = generarBreadcrumb([
    ['text' => 'Contratos', 'url' => 'contratos.php']
]);

// Acciones del título
$acciones_titulo = '
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalContrato">
        <i class="fas fa-plus me-1"></i>Nuevo Contrato
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
                        <input type="text" class="form-control" name="busqueda" placeholder="Buscar por inquilino o unidad..." 
                               value="<?= htmlspecialchars($busqueda) ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="estado">
                            <option value="">Todos los estados</option>
                            <option value="activo" <?= $filtro_estado === 'activo' ? 'selected' : '' ?>>Activo</option>
                            <option value="finalizado" <?= $filtro_estado === 'finalizado' ? 'selected' : '' ?>>Finalizado</option>
                            <option value="renovado" <?= $filtro_estado === 'renovado' ? 'selected' : '' ?>>Renovado</option>
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

        <!-- Lista de Contratos -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($contratos)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-file-contract fa-3x text-muted mb-3"></i>
                        <h5>No se encontraron contratos</h5>
                        <p class="text-muted"><?= empty($busqueda) && empty($filtro_estado) ? 'No hay contratos registrados.' : 'Intente con otros filtros.' ?></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Inquilino</th>
                                    <th>Unidad</th>
                                    <th>Fechas</th>
                                    <th>Monto</th>
                                    <th>Estado</th>
                                    <th width="150">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contratos as $contrato): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($contrato['inquilino_nombre'] . ' ' . $contrato['inquilino_apellido']) ?></strong>
                                        </td>
                                        <td>
                                            <div><strong><?= htmlspecialchars($contrato['unidad_numero']) ?></strong> (<?= htmlspecialchars($contrato['unidad_tipo']) ?>)</div>
                                            <small class="text-muted"><?= htmlspecialchars($contrato['edificio_nombre']) ?></small>
                                        </td>
                                        <td>
                                            <div>Inicio: <?= date('d/m/Y', strtotime($contrato['fecha_inicio'])) ?></div>
                                            <div>Vence: <?= date('d/m/Y', strtotime($contrato['fecha_vencimiento'])) ?></div>
                                        </td>
                                        <td>
                                            <strong><?= formato_moneda($contrato['monto_alquiler'], $contrato['moneda']) ?></strong>
                                            <div class="text-muted">Día: <?= $contrato['dia_vencimiento'] ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $contrato['estado'] === 'activo' ? 'success' : 
                                                ($contrato['estado'] === 'finalizado' ? 'secondary' : 'info') 
                                            ?>">
                                                <?= ucfirst($contrato['estado']) ?>
                                            </span>
                                            <?php if ($contrato['estado'] === 'activo'): ?>
                                                <?php
                                                    $dias_restantes = floor((strtotime($contrato['fecha_vencimiento']) - time()) / (60 * 60 * 24));
                                                    if ($dias_restantes <= 30) {
                                                        echo '<div class="text-warning"><small><i class="fas fa-exclamation-triangle"></i> ' . $dias_restantes . ' días</small></div>';
                                                    }
                                                ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" 
                                                        onclick="editarContrato(<?= htmlspecialchars(json_encode($contrato)) ?>)"
                                                        title="Editar contrato">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($contrato['estado'] === 'activo'): ?>
                                                    <button type="button" class="btn btn-outline-info" 
                                                            onclick="renovarContrato(<?= $contrato['id'] ?>, <?= htmlspecialchars(json_encode($contrato)) ?>)"
                                                            title="Renovar contrato">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        onclick="eliminarContrato(<?= $contrato['id'] ?>, '<?= htmlspecialchars($contrato['inquilino_nombre'] . ' ' . $contrato['inquilino_apellido']) ?>')"
                                                        title="Eliminar contrato">
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
                        <nav aria-label="Paginación de contratos">
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
                        Mostrando <?= count($contratos) ?> de <?= $total_contratos ?> contratos
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Crear/Editar Contrato -->
<div class="modal fade" id="modalContrato" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formContrato" data-validar>
                <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                <input type="hidden" name="accion" id="accion" value="crear">
                <input type="hidden" name="id" id="contrato_id" value="">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">Nuevo Contrato</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="inquilino_id" class="form-label">Inquilino *</label>
                            <select class="form-select" id="inquilino_id" name="inquilino_id" required>
                                <option value="">Seleccionar inquilino</option>
                                <?php foreach ($inquilinos as $inquilino): ?>
                                    <option value="<?= $inquilino['id'] ?>"><?= htmlspecialchars($inquilino['nombre'] . ' ' . $inquilino['apellido']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Seleccione un inquilino.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="unidad_id" class="form-label">Unidad *</label>
                            <select class="form-select" id="unidad_id" name="unidad_id" required>
                                <option value="">Seleccionar unidad</option>
                                <?php foreach ($unidades as $unidad): ?>
                                    <option value="<?= $unidad['id'] ?>"><?= htmlspecialchars($unidad['numero'] . ' - ' . $unidad['tipo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Seleccione una unidad.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="fecha_inicio" class="form-label">Fecha Inicio *</label>
                            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                            <div class="invalid-feedback">La fecha de inicio es obligatoria.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="fecha_vencimiento" class="form-label">Fecha Vencimiento *</label>
                            <input type="date" class="form-control" id="fecha_vencimiento" name="fecha_vencimiento" required>
                            <div class="invalid-feedback">La fecha de vencimiento es obligatoria.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="monto_alquiler" class="form-label">Monto Alquiler *</label>
                            <input type="number" step="0.01" class="form-control" id="monto_alquiler" name="monto_alquiler" required>
                            <div class="invalid-feedback">El monto es obligatorio.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="moneda" class="form-label">Moneda *</label>
                            <select class="form-select" id="moneda" name="moneda" required>
                                <option value="ARS">Pesos Argentinos (ARS)</option>
                                <option value="USD">Dólares Estadounidenses (USD)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="dia_vencimiento" class="form-label">Día de Vencimiento *</label>
                            <input type="number" min="1" max="31" class="form-control" id="dia_vencimiento" name="dia_vencimiento" value="1" required>
                            <div class="invalid-feedback">El día debe ser entre 1 y 31.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="deposito_garantia" class="form-label">Depósito Garantía</label>
                            <input type="number" step="0.01" class="form-control" id="deposito_garantia" name="deposito_garantia" value="0">
                        </div>
                        
                        <div class="col-12" id="campo-estado" style="display: none;">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="activo">Activo</option>
                                <option value="finalizado">Finalizado</option>
                                <option value="renovado">Renovado</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Contrato</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Renovar Contrato -->
<div class="modal fade" id="modalRenovarContrato" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="formRenovarContrato">
                <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                <input type="hidden" name="accion" value="renovar">
                <input type="hidden" name="id" id="contrato_id_renovar">
                
                <div class="modal-header">
                    <h5 class="modal-title">Renovar Contrato</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="nueva_fecha_vencimiento" class="form-label">Nueva Fecha Vencimiento *</label>
                            <input type="date" class="form-control" id="nueva_fecha_vencimiento" name="nueva_fecha_vencimiento" required>
                        </div>
                        
                        <div class="col-12">
                            <label for="nuevo_monto" class="form-label">Nuevo Monto *</label>
                            <input type="number" step="0.01" class="form-control" id="nuevo_monto" name="nuevo_monto" required>
                        </div>
                        
                        <div class="col-12">
                            <label for="nueva_moneda" class="form-label">Nueva Moneda *</label>
                            <select class="form-select" id="nueva_moneda" name="nueva_moneda" required>
                                <option value="ARS">Pesos Argentinos (ARS)</option>
                                <option value="USD">Dólares Estadounidenses (USD)</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Renovar Contrato</button>
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
                <p>¿Está seguro de que desea eliminar el contrato de <strong id="nombreInquilinoEliminar"></strong>?</p>
                <p class="text-danger"><small>Esta acción no se puede deshacer.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" id="formEliminarContrato">
                    <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" id="contrato_id_eliminar">
                    <button type="submit" class="btn btn-danger">Eliminar Contrato</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$scripts_extra = '
<script>
function editarContrato(contrato) {
    document.getElementById("modalTitulo").textContent = "Editar Contrato";
    document.getElementById("accion").value = "editar";
    document.getElementById("contrato_id").value = contrato.id;
    document.getElementById("inquilino_id").value = contrato.inquilino_id;
    document.getElementById("unidad_id").value = contrato.unidad_id;
    document.getElementById("fecha_inicio").value = contrato.fecha_inicio;
    document.getElementById("fecha_vencimiento").value = contrato.fecha_vencimiento;
    document.getElementById("monto_alquiler").value = contrato.monto_alquiler;
    document.getElementById("moneda").value = contrato.moneda;
    document.getElementById("dia_vencimiento").value = contrato.dia_vencimiento;
    document.getElementById("deposito_garantia").value = contrato.deposito_garantia;
    document.getElementById("estado").value = contrato.estado;
    
    document.getElementById("campo-estado").style.display = "block";
    
    var modal = new bootstrap.Modal(document.getElementById("modalContrato"));
    modal.show();
}

function renovarContrato(id, contrato) {
    document.getElementById("contrato_id_renovar").value = id;
    document.getElementById("nuevo_monto").value = contrato.monto_alquiler;
    document.getElementById("nueva_moneda").value = contrato.moneda;
    
    // Calcular nueva fecha de vencimiento (1 año desde hoy)
    let hoy = new Date();
    let nuevaFecha = new Date(hoy.getFullYear() + 1, hoy.getMonth(), hoy.getDate());
    document.getElementById("nueva_fecha_vencimiento").value = nuevaFecha.toISOString().split(\'T\')[0];
    
    var modal = new bootstrap.Modal(document.getElementById("modalRenovarContrato"));
    modal.show();
}

function eliminarContrato(id, nombre) {
    document.getElementById("nombreInquilinoEliminar").textContent = nombre;
    document.getElementById("contrato_id_eliminar").value = id;
    
    var modal = new bootstrap.Modal(document.getElementById("modalConfirmarEliminar"));
    modal.show();
}

// Resetear modal cuando se cierre
document.getElementById("modalContrato").addEventListener("hidden.bs.modal", function() {
    document.getElementById("formContrato").reset();
    document.getElementById("modalTitulo").textContent = "Nuevo Contrato";
    document.getElementById("accion").value = "crear";
    document.getElementById("contrato_id").value = "";
    document.getElementById("campo-estado").style.display = "none";
});
</script>
';

require_once 'inc/footer.php';
?>