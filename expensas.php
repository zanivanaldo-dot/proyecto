<?php
session_start();
require_once 'inc/config.php';
require_once 'inc/conexion.php';
require_once 'inc/funciones.php';

requireAuth();

$pdo = obtenerConexion();
$titulo_pagina = 'Gestión de Expensas';
$icono_titulo = 'fas fa-receipt';

// Configuración de paginación
$por_pagina = 10;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $por_pagina;

// Búsqueda y filtros
$busqueda = isset($_GET['busqueda']) ? sanitizar($_GET['busqueda']) : '';
$filtro_estado = isset($_GET['estado']) ? sanitizar($_GET['estado']) : '';
$filtro_mes = isset($_GET['mes']) ? sanitizar($_GET['mes']) : '';
$filtro_ano = isset($_GET['ano']) ? (int)$_GET['ano'] : date('Y');

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    // Verificar token CSRF
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Token de seguridad inválido.';
        redirigir('expensas.php');
    }
    
    switch ($accion) {
        case 'crear':
            $unidad_id = (int)($_POST['unidad_id'] ?? 0);
            $periodo_ano = (int)($_POST['periodo_ano'] ?? date('Y'));
            $periodo_mes = (int)($_POST['periodo_mes'] ?? date('n'));
            $monto_total = (float)($_POST['monto_total'] ?? 0);
            $detalle = sanitizar($_POST['detalle'] ?? '');
            $fecha_emision = sanitizar($_POST['fecha_emision'] ?? '');
            $fecha_vencimiento = sanitizar($_POST['fecha_vencimiento'] ?? '');
            
            // Validaciones
            $errores = [];
            
            if (empty($unidad_id)) {
                $errores[] = 'La unidad es obligatoria.';
            }
            
            if ($monto_total <= 0) {
                $errores[] = 'El monto total debe ser mayor a 0.';
            }
            
            if (empty($fecha_emision) || empty($fecha_vencimiento)) {
                $errores[] = 'Las fechas de emisión y vencimiento son obligatorias.';
            }
            
            // Verificar que no exista expensa para el mismo período y unidad
            $sql = "SELECT id FROM expensas WHERE unidad_id = ? AND periodo_ano = ? AND periodo_mes = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$unidad_id, $periodo_ano, $periodo_mes]);
            if ($stmt->fetch()) {
                $errores[] = 'Ya existe una expensa para esta unidad en el período seleccionado.';
            }
            
            if (empty($errores)) {
                try {
                    $pdo->beginTransaction();
                    
                    // Determinar estado inicial
                    $estado = 'pendiente';
                    if (strtotime($fecha_vencimiento) < time()) {
                        $estado = 'vencida';
                    }
                    
                    $sql = "INSERT INTO expensas (unidad_id, periodo_ano, periodo_mes, monto_total, 
                            detalle, fecha_emision, fecha_vencimiento, estado) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$unidad_id, $periodo_ano, $periodo_mes, $monto_total, 
                                    $detalle, $fecha_emision, $fecha_vencimiento, $estado]);
                    
                    $expensa_id = $pdo->lastInsertId();
                    
                    // Procesar movimientos de expensa
                    if (isset($_POST['movimientos']) && is_array($_POST['movimientos'])) {
                        foreach ($_POST['movimientos'] as $movimiento) {
                            if (!empty($movimiento['descripcion']) && $movimiento['monto'] > 0) {
                                $categoria_id = (int)($movimiento['categoria_id'] ?? 0);
                                $descripcion = sanitizar($movimiento['descripcion']);
                                $monto = (float)$movimiento['monto'];
                                
                                $sql_mov = "INSERT INTO movimientos_expensa (expensa_id, categoria_id, descripcion, monto) 
                                           VALUES (?, ?, ?, ?)";
                                $stmt_mov = $pdo->prepare($sql_mov);
                                $stmt_mov->execute([$expensa_id, $categoria_id, $descripcion, $monto]);
                            }
                        }
                    }
                    
                    registrarLog($pdo, "Creó expensa ID: $expensa_id para unidad $unidad_id");
                    $pdo->commit();
                    $_SESSION['success'] = 'Expensa creada exitosamente.';
                    redirigir('expensas.php');
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Error al crear expensa: " . $e->getMessage());
                    $_SESSION['error'] = 'Error al crear la expensa.';
                }
            } else {
                $_SESSION['error'] = implode('<br>', $errores);
            }
            break;
            
        case 'editar':
            $unidad_id = (int)($_POST['unidad_id'] ?? 0);
            $periodo_ano = (int)($_POST['periodo_ano'] ?? date('Y'));
            $periodo_mes = (int)($_POST['periodo_mes'] ?? date('n'));
            $monto_total = (float)($_POST['monto_total'] ?? 0);
            $detalle = sanitizar($_POST['detalle'] ?? '');
            $fecha_emision = sanitizar($_POST['fecha_emision'] ?? '');
            $fecha_vencimiento = sanitizar($_POST['fecha_vencimiento'] ?? '');
            $estado = sanitizar($_POST['estado'] ?? 'pendiente');
            
            // Validaciones
            $errores = [];
            
            if (empty($unidad_id)) {
                $errores[] = 'La unidad es obligatoria.';
            }
            
            if ($monto_total <= 0) {
                $errores[] = 'El monto total debe ser mayor a 0.';
            }
            
            if (empty($fecha_emision) || empty($fecha_vencimiento)) {
                $errores[] = 'Las fechas de emisión y vencimiento son obligatorias.';
            }
            
            // Verificar que no exista otra expensa para el mismo período y unidad
            $sql = "SELECT id FROM expensas WHERE unidad_id = ? AND periodo_ano = ? AND periodo_mes = ? AND id != ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$unidad_id, $periodo_ano, $periodo_mes, $id]);
            if ($stmt->fetch()) {
                $errores[] = 'Ya existe otra expensa para esta unidad en el período seleccionado.';
            }
            
            if (empty($errores)) {
                try {
                    $pdo->beginTransaction();
                    
                    $sql = "UPDATE expensas SET unidad_id = ?, periodo_ano = ?, periodo_mes = ?, monto_total = ?, 
                            detalle = ?, fecha_emision = ?, fecha_vencimiento = ?, estado = ? 
                            WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$unidad_id, $periodo_ano, $periodo_mes, $monto_total, 
                                    $detalle, $fecha_emision, $fecha_vencimiento, $estado, $id]);
                    
                    // Eliminar movimientos existentes y crear nuevos
                    $sql_del = "DELETE FROM movimientos_expensa WHERE expensa_id = ?";
                    $stmt_del = $pdo->prepare($sql_del);
                    $stmt_del->execute([$id]);
                    
                    // Procesar movimientos de expensa
                    if (isset($_POST['movimientos']) && is_array($_POST['movimientos'])) {
                        foreach ($_POST['movimientos'] as $movimiento) {
                            if (!empty($movimiento['descripcion']) && $movimiento['monto'] > 0) {
                                $categoria_id = (int)($movimiento['categoria_id'] ?? 0);
                                $descripcion = sanitizar($movimiento['descripcion']);
                                $monto = (float)$movimiento['monto'];
                                
                                $sql_mov = "INSERT INTO movimientos_expensa (expensa_id, categoria_id, descripcion, monto) 
                                           VALUES (?, ?, ?, ?)";
                                $stmt_mov = $pdo->prepare($sql_mov);
                                $stmt_mov->execute([$id, $categoria_id, $descripcion, $monto]);
                            }
                        }
                    }
                    
                    registrarLog($pdo, "Editó expensa ID: $id para unidad $unidad_id");
                    $pdo->commit();
                    $_SESSION['success'] = 'Expensa actualizada exitosamente.';
                    redirigir('expensas.php');
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Error al actualizar expensa: " . $e->getMessage());
                    $_SESSION['error'] = 'Error al actualizar la expensa.';
                }
            } else {
                $_SESSION['error'] = implode('<br>', $errores);
            }
            break;
            
        case 'eliminar':
            try {
                $pdo->beginTransaction();
                
                // Obtener datos para el log
                $sql = "SELECT unidad_id FROM expensas WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                $expensa = $stmt->fetch();
                
                // Eliminar movimientos primero
                $sql_del_mov = "DELETE FROM movimientos_expensa WHERE expensa_id = ?";
                $stmt_del_mov = $pdo->prepare($sql_del_mov);
                $stmt_del_mov->execute([$id]);
                
                // Eliminar expensa
                $sql_del = "DELETE FROM expensas WHERE id = ?";
                $stmt_del = $pdo->prepare($sql_del);
                $stmt_del->execute([$id]);
                
                registrarLog($pdo, "Eliminó expensa ID: $id de unidad {$expensa['unidad_id']}");
                $pdo->commit();
                $_SESSION['success'] = 'Expensa eliminada exitosamente.';
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Error al eliminar expensa: " . $e->getMessage());
                $_SESSION['error'] = 'Error al eliminar la expensa.';
            }
            break;
            
        case 'marcar_pagada':
            try {
                $pdo->beginTransaction();
                
                $sql = "UPDATE expensas SET estado = 'pagada' WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                
                registrarLog($pdo, "Marcó como pagada expensa ID: $id");
                $pdo->commit();
                $_SESSION['success'] = 'Expensa marcada como pagada.';
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Error al marcar expensa como pagada: " . $e->getMessage());
                $_SESSION['error'] = 'Error al marcar la expensa como pagada.';
            }
            break;
    }
    
    redirigir('expensas.php');
}

// Obtener expensas con filtros
$where_conditions = [];
$params = [];

if (!empty($busqueda)) {
    $where_conditions[] = "(u.numero LIKE ? OR e.detalle LIKE ?)";
    $like_param = "%$busqueda%";
    $params[] = $like_param;
    $params[] = $like_param;
}

if (!empty($filtro_estado)) {
    $where_conditions[] = "e.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_mes)) {
    $where_conditions[] = "e.periodo_mes = ?";
    $params[] = $filtro_mes;
}

$where_conditions[] = "e.periodo_ano = ?";
$params[] = $filtro_ano;

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Consulta para contar total
$sql_count = "SELECT COUNT(*) as total 
              FROM expensas e
              INNER JOIN unidades u ON e.unidad_id = u.id
              $where_sql";
$stmt = $pdo->prepare($sql_count);
$stmt->execute($params);
$total_expensas = $stmt->fetch()['total'];
$total_paginas = ceil($total_expensas / $por_pagina);

// Consulta para obtener expensas
$sql = "SELECT e.*, u.numero as unidad_numero, u.tipo as unidad_tipo,
               CONCAT(i.nombre, ' ', i.apellido) as inquilino_nombre,
               ed.nombre as edificio_nombre
        FROM expensas e
        INNER JOIN unidades u ON e.unidad_id = u.id
        INNER JOIN edificios ed ON u.edificio_id = ed.id
        LEFT JOIN contratos c ON u.id = c.unidad_id AND c.estado = 'activo'
        LEFT JOIN inquilinos i ON c.inquilino_id = i.id
        $where_sql 
        ORDER BY e.periodo_ano DESC, e.periodo_mes DESC, u.numero ASC
        LIMIT $offset, $por_pagina";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expensas = $stmt->fetchAll();

// Obtener listas para selects
$unidades = $pdo->query("SELECT id, numero, tipo FROM unidades WHERE activo = 1 ORDER BY numero")->fetchAll();
$categorias = $pdo->query("SELECT id, nombre FROM categorias_gasto ORDER BY nombre")->fetchAll();

// Breadcrumb
$breadcrumb = generarBreadcrumb([
    ['text' => 'Expensas', 'url' => 'expensas.php']
]);

// Acciones del título
$acciones_titulo = '
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalExpensa">
        <i class="fas fa-plus me-1"></i>Nueva Expensa
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
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="busqueda" placeholder="Buscar por unidad o detalle..." 
                               value="<?= htmlspecialchars($busqueda) ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="estado">
                            <option value="">Todos los estados</option>
                            <option value="pendiente" <?= $filtro_estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="pagada" <?= $filtro_estado === 'pagada' ? 'selected' : '' ?>>Pagada</option>
                            <option value="vencida" <?= $filtro_estado === 'vencida' ? 'selected' : '' ?>>Vencida</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="mes">
                            <option value="">Todos los meses</option>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= $filtro_mes == $i ? 'selected' : '' ?>>
                                    <?= nombreMes($i) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="ano">
                            <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++): ?>
                                <option value="<?= $i ?>" <?= $filtro_ano == $i ? 'selected' : '' ?>>
                                    <?= $i ?>
                                </option>
                            <?php endfor; ?>
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

        <!-- Resumen de Expensas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h6 class="card-title">Total Expensas</h6>
                        <h4><?= formato_moneda(array_sum(array_column($expensas, 'monto_total')), 'ARS') ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6 class="card-title">Pagadas</h6>
                        <h4><?= count(array_filter($expensas, fn($e) => $e['estado'] === 'pagada')) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body">
                        <h6 class="card-title">Pendientes</h6>
                        <h4><?= count(array_filter($expensas, fn($e) => $e['estado'] === 'pendiente')) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h6 class="card-title">Vencidas</h6>
                        <h4><?= count(array_filter($expensas, fn($e) => $e['estado'] === 'vencida')) ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Expensas -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($expensas)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                        <h5>No se encontraron expensas</h5>
                        <p class="text-muted"><?= empty($busqueda) && empty($filtro_estado) && empty($filtro_mes) ? 'No hay expensas registradas.' : 'Intente con otros filtros.' ?></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Período</th>
                                    <th>Unidad</th>
                                    <th>Inquilino</th>
                                    <th>Monto</th>
                                    <th>Vencimiento</th>
                                    <th>Estado</th>
                                    <th width="150">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expensas as $expensa): ?>
                                    <tr>
                                        <td>
                                            <strong><?= nombreMes($expensa['periodo_mes']) ?> <?= $expensa['periodo_ano'] ?></strong>
                                        </td>
                                        <td>
                                            <div><strong><?= htmlspecialchars($expensa['unidad_numero']) ?></strong> (<?= htmlspecialchars($expensa['unidad_tipo']) ?>)</div>
                                            <small class="text-muted"><?= htmlspecialchars($expensa['edificio_nombre']) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($expensa['inquilino_nombre']): ?>
                                                <?= htmlspecialchars($expensa['inquilino_nombre']) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Sin inquilino</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= formato_moneda($expensa['monto_total'], 'ARS') ?></strong>
                                        </td>
                                        <td>
                                            <?= formato_fecha($expensa['fecha_vencimiento']) ?>
                                            <?php if (strtotime($expensa['fecha_vencimiento']) < time() && $expensa['estado'] === 'pendiente'): ?>
                                                <div class="text-danger"><small><i class="fas fa-exclamation-triangle"></i> Vencida</small></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $expensa['estado'] === 'pagada' ? 'success' : 
                                                ($expensa['estado'] === 'pendiente' ? 'warning' : 'danger') 
                                            ?>">
                                                <?= ucfirst($expensa['estado']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" 
                                                        onclick="editarExpensa(<?= $expensa['id'] ?>)"
                                                        title="Editar expensa">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-info" 
                                                        onclick="verDetalle(<?= $expensa['id'] ?>)"
                                                        title="Ver detalle">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($expensa['estado'] === 'pendiente'): ?>
                                                    <button type="button" class="btn btn-outline-success" 
                                                            onclick="marcarPagada(<?= $expensa['id'] ?>)"
                                                            title="Marcar como pagada">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        onclick="eliminarExpensa(<?= $expensa['id'] ?>, '<?= htmlspecialchars($expensa['unidad_numero']) ?>')"
                                                        title="Eliminar expensa">
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
                        <nav aria-label="Paginación de expensas">
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
                        Mostrando <?= count($expensas) ?> de <?= $total_expensas ?> expensas
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Crear/Editar Expensa -->
<div class="modal fade" id="modalExpensa" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" id="formExpensa" data-validar>
                <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                <input type="hidden" name="accion" id="accion" value="crear">
                <input type="hidden" name="id" id="expensa_id" value="">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">Nueva Expensa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="unidad_id" class="form-label">Unidad *</label>
                            <select class="form-select" id="unidad_id" name="unidad_id" required>
                                <option value="">Seleccionar unidad</option>
                                <?php foreach ($unidades as $unidad): ?>
                                    <option value="<?= $unidad['id'] ?>"><?= htmlspecialchars($unidad['numero'] . ' - ' . $unidad['tipo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Seleccione una unidad.</div>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="periodo_mes" class="form-label">Mes *</label>
                            <select class="form-select" id="periodo_mes" name="periodo_mes" required>
                                <option value="">Seleccionar mes</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>" <?= $i == date('n') ? 'selected' : '' ?>>
                                        <?= nombreMes($i) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <div class="invalid-feedback">Seleccione un mes.</div>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="periodo_ano" class="form-label">Año *</label>
                            <select class="form-select" id="periodo_ano" name="periodo_ano" required>
                                <?php for ($i = date('Y') - 1; $i <= date('Y') + 1; $i++): ?>
                                    <option value="<?= $i ?>" <?= $i == date('Y') ? 'selected' : '' ?>>
                                        <?= $i ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <div class="invalid-feedback">Seleccione un año.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="fecha_emision" class="form-label">Fecha Emisión *</label>
                            <input type="date" class="form-control" id="fecha_emision" name="fecha_emision" 
                                   value="<?= date('Y-m-d') ?>" required>
                            <div class="invalid-feedback">La fecha de emisión es obligatoria.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="fecha_vencimiento" class="form-label">Fecha Vencimiento *</label>
                            <input type="date" class="form-control" id="fecha_vencimiento" name="fecha_vencimiento" required>
                            <div class="invalid-feedback">La fecha de vencimiento es obligatoria.</div>
                        </div>
                        
                        <div class="col-12">
                            <label for="detalle" class="form-label">Detalle General</label>
                            <textarea class="form-control" id="detalle" name="detalle" rows="2"></textarea>
                        </div>
                        
                        <!-- Movimientos de Expensa -->
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6>Detalle de Partidas</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="agregarMovimiento()">
                                    <i class="fas fa-plus me-1"></i>Agregar Partida
                                </button>
                            </div>
                            
                            <div id="movimientos-container">
                                <!-- Los movimientos se agregan dinámicamente aquí -->
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <strong>Total Calculado: <span id="total-calculado">$ 0,00</span></strong>
                                </div>
                                <div class="col-md-6">
                                    <label for="monto_total" class="form-label">Monto Total *</label>
                                    <input type="number" step="0.01" class="form-control" id="monto_total" name="monto_total" required readonly>
                                    <div class="invalid-feedback">El monto total es obligatorio.</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12" id="campo-estado" style="display: none;">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="pendiente">Pendiente</option>
                                <option value="pagada">Pagada</option>
                                <option value="vencida">Vencida</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Expensa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Ver Detalle -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de Expensa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalle-contenido">
                <!-- Contenido cargado dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
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
                <p>¿Está seguro de que desea eliminar la expensa de la unidad <strong id="unidadExpensaEliminar"></strong>?</p>
                <p class="text-danger"><small>Esta acción no se puede deshacer.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" id="formEliminarExpensa">
                    <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" id="expensa_id_eliminar">
                    <button type="submit" class="btn btn-danger">Eliminar Expensa</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Marcar como Pagada -->
<div class="modal fade" id="modalMarcarPagada" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Marcar como Pagada</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Está seguro de que desea marcar esta expensa como pagada?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" id="formMarcarPagada">
                    <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                    <input type="hidden" name="accion" value="marcar_pagada">
                    <input type="hidden" name="id" id="expensa_id_pagada">
                    <button type="submit" class="btn btn-success">Marcar como Pagada</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$scripts_extra = '
<script>
let contadorMovimientos = 0;

function agregarMovimiento() {
    contadorMovimientos++;
    const html = `
        <div class="movimiento-item border p-3 mb-3 rounded">
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label">Categoría</label>
                    <select class="form-select" name="movimientos[${contadorMovimientos}][categoria_id]">
                        <option value="">Seleccionar categoría</option>
                        ' . implode('', array_map(function($cat) {
                            return `<option value="${cat.id}">${cat.nombre}</option>`;
                        }, $categorias)) . '
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Descripción *</label>
                    <input type="text" class="form-control" name="movimientos[${contadorMovimientos}][descripcion]" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Monto *</label>
                    <input type="number" step="0.01" class="form-control movimiento-monto" name="movimientos[${contadorMovimientos}][monto]" required onchange="calcularTotal()">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="eliminarMovimiento(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    document.getElementById("movimientos-container").insertAdjacentHTML("beforeend", html);
}

function eliminarMovimiento(boton) {
    boton.closest(".movimiento-item").remove();
    calcularTotal();
}

function calcularTotal() {
    let total = 0;
    document.querySelectorAll(".movimiento-monto").forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    document.getElementById("monto_total").value = total.toFixed(2);
    document.getElementById("total-calculado").textContent = "    document.getElementById("total-calculado").textContent = "$ " + total.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function editarExpensa(id) {
    // Cargar datos de la expensa via AJAX
    fetch(`../inc/api.php?action=obtener_expensa&id=${id}`)
        .then(response => response.json())
        .then(expensa => {
            if (expensa.success) {
                const data = expensa.data;
                
                document.getElementById("modalTitulo").textContent = "Editar Expensa";
                document.getElementById("accion").value = "editar";
                document.getElementById("expensa_id").value = data.id;
                document.getElementById("unidad_id").value = data.unidad_id;
                document.getElementById("periodo_mes").value = data.periodo_mes;
                document.getElementById("periodo_ano").value = data.periodo_ano;
                document.getElementById("fecha_emision").value = data.fecha_emision;
                document.getElementById("fecha_vencimiento").value = data.fecha_vencimiento;
                document.getElementById("detalle").value = data.detalle;
                document.getElementById("monto_total").value = data.monto_total;
                document.getElementById("estado").value = data.estado;
                
                document.getElementById("campo-estado").style.display = "block";
                
                // Cargar movimientos
                document.getElementById("movimientos-container").innerHTML = "";
                contadorMovimientos = 0;
                
                if (data.movimientos && data.movimientos.length > 0) {
                    data.movimientos.forEach(mov => {
                        contadorMovimientos++;
                        const html = `
                            <div class="movimiento-item border p-3 mb-3 rounded">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label">Categoría</label>
                                        <select class="form-select" name="movimientos[${contadorMovimientos}][categoria_id]">
                                            <option value="">Seleccionar categoría</option>
                                            ' . implode('', array_map(function($cat) {
                                                return `<option value="${cat.id}">${cat.nombre}</option>`;
                                            }, $categorias)) . '
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">Descripción *</label>
                                        <input type="text" class="form-control" name="movimientos[${contadorMovimientos}][descripcion]" value="${mov.descripcion}" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Monto *</label>
                                        <input type="number" step="0.01" class="form-control movimiento-monto" name="movimientos[${contadorMovimientos}][monto]" value="${mov.monto}" required onchange="calcularTotal()">
                                    </div>
                                    <div class="col-md-1 d-flex align-items-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="eliminarMovimiento(this)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                        document.getElementById("movimientos-container").insertAdjacentHTML("beforeend", html);
                        
                        // Seleccionar la categoría correcta
                        const select = document.querySelector(`.movimiento-item:last-child select`);
                        if (select) {
                            select.value = mov.categoria_id;
                        }
                    });
                }
                
                calcularTotal();
                
                var modal = new bootstrap.Modal(document.getElementById("modalExpensa"));
                modal.show();
            } else {
                alert("Error al cargar los datos de la expensa.");
            }
        })
        .catch(error => {
            console.error("Error:", error);
            alert("Error al cargar los datos de la expensa.");
        });
}

function verDetalle(id) {
    // Cargar detalle de la expensa via AJAX
    fetch(`../inc/api.php?action=obtener_detalle_expensa&id=${id}`)
        .then(response => response.json())
        .then(detalle => {
            if (detalle.success) {
                const data = detalle.data;
                
                let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Unidad:</strong> ${data.unidad_numero} (${data.unidad_tipo})<br>
                            <strong>Período:</strong> ${data.periodo_mes}/${data.periodo_ano}<br>
                            <strong>Emisión:</strong> ${data.fecha_emision}<br>
                            <strong>Vencimiento:</strong> ${data.fecha_vencimiento}<br>
                            <strong>Estado:</strong> <span class="badge bg-${data.estado === 'pagada' ? 'success' : data.estado === 'pendiente' ? 'warning' : 'danger'}">${data.estado}</span>
                        </div>
                        <div class="col-md-6">
                            <strong>Monto Total:</strong> ${data.monto_total}<br>
                            ${data.detalle ? `<strong>Detalle:</strong> ${data.detalle}<br>` : ''}
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6>Partidas de la Expensa</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Categoría</th>
                                    <th>Descripción</th>
                                    <th>Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                if (data.movimientos && data.movimientos.length > 0) {
                    data.movimientos.forEach(mov => {
                        html += `
                            <tr>
                                <td>${mov.categoria_nombre || 'Sin categoría'}</td>
                                <td>${mov.descripcion}</td>
                                <td>${mov.monto}</td>
                            </tr>
                        `;
                    });
                } else {
                    html += `<tr><td colspan="3" class="text-center">No hay partidas registradas</td></tr>`;
                }
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
                
                document.getElementById("detalle-contenido").innerHTML = html;
                var modal = new bootstrap.Modal(document.getElementById("modalDetalle"));
                modal.show();
            } else {
                alert("Error al cargar el detalle de la expensa.");
            }
        })
        .catch(error => {
            console.error("Error:", error);
            alert("Error al cargar el detalle de la expensa.");
        });
}

function eliminarExpensa(id, unidad) {
    document.getElementById("unidadExpensaEliminar").textContent = unidad;
    document.getElementById("expensa_id_eliminar").value = id;
    
    var modal = new bootstrap.Modal(document.getElementById("modalConfirmarEliminar"));
    modal.show();
}

function marcarPagada(id) {
    document.getElementById("expensa_id_pagada").value = id;
    
    var modal = new bootstrap.Modal(document.getElementById("modalMarcarPagada"));
    modal.show();
}

// Resetear modal cuando se cierre
document.getElementById("modalExpensa").addEventListener("hidden.bs.modal", function() {
    document.getElementById("formExpensa").reset();
    document.getElementById("modalTitulo").textContent = "Nueva Expensa";
    document.getElementById("accion").value = "crear";
    document.getElementById("expensa_id").value = "";
    document.getElementById("campo-estado").style.display = "none";
    document.getElementById("movimientos-container").innerHTML = "";
    contadorMovimientos = 0;
    calcularTotal();
});

// Inicializar con un movimiento vacío
document.addEventListener("DOMContentLoaded", function() {
    agregarMovimiento();
    
    // Establecer fecha de vencimiento por defecto (10 días desde hoy)
    const hoy = new Date();
    const vencimiento = new Date(hoy);
    vencimiento.setDate(hoy.getDate() + 10);
    document.getElementById("fecha_vencimiento").value = vencimiento.toISOString().split('T')[0];
});
</script>
';

require_once 'inc/footer.php';
?>