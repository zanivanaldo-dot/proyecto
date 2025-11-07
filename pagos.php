<?php
session_start();
require_once 'inc/config.php';
require_once 'inc/conexion.php';
require_once 'inc/funciones.php';

requireAuth();

$pdo = obtenerConexion();
$titulo_pagina = 'Gestión de Pagos';
$icono_titulo = 'fas fa-money-bill-wave';

// Configuración de paginación
$por_pagina = 15;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $por_pagina;

// Búsqueda y filtros
$busqueda = isset($_GET['busqueda']) ? sanitizar($_GET['busqueda']) : '';
$filtro_tipo = isset($_GET['tipo']) ? sanitizar($_GET['tipo']) : '';
$filtro_metodo = isset($_GET['metodo']) ? sanitizar($_GET['metodo']) : '';
$filtro_mes = isset($_GET['mes']) ? sanitizar($_GET['mes']) : '';
$filtro_ano = isset($_GET['ano']) ? (int)$_GET['ano'] : date('Y');
$filtro_moneda = isset($_GET['moneda']) ? sanitizar($_GET['moneda']) : '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    // Verificar token CSRF
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Token de seguridad inválido.';
        redirigir('pagos.php');
    }
    
    // Verificar permisos para acciones de modificación
    if (!esAdmin() && in_array($accion, ['crear', 'editar', 'eliminar'])) {
        $_SESSION['error'] = 'No tiene permisos para realizar esta acción.';
        redirigir('pagos.php');
    }
    
    switch ($accion) {
        case 'crear':
            $tipo_pago = sanitizar($_POST['tipo_pago'] ?? '');
            $contrato_id = !empty($_POST['contrato_id']) ? (int)$_POST['contrato_id'] : null;
            $inquilino_id = (int)($_POST['inquilino_id'] ?? 0);
            $unidad_id = (int)($_POST['unidad_id'] ?? 0);
            $monto = (float)($_POST['monto'] ?? 0);
            $moneda = sanitizar($_POST['moneda'] ?? 'ARS');
            $fecha_pago = sanitizar($_POST['fecha_pago'] ?? '');
            $metodo_pago = sanitizar($_POST['metodo_pago'] ?? '');
            $referencia = sanitizar($_POST['referencia'] ?? '');
            $descripcion = sanitizar($_POST['descripcion'] ?? '');
            
            // Validaciones
            $errores = [];
            
            if (empty($tipo_pago) || empty($inquilino_id) || empty($unidad_id)) {
                $errores[] = 'Tipo de pago, inquilino y unidad son obligatorios.';
            }
            
            if ($monto <= 0) {
                $errores[] = 'El monto debe ser mayor a 0.';
            }
            
            if (empty($fecha_pago)) {
                $errores[] = 'La fecha de pago es obligatoria.';
            }
            
            if (empty($metodo_pago)) {
                $errores[] = 'El método de pago es obligatorio.';
            }
            
            // Validaciones específicas por tipo de pago
            if ($tipo_pago === 'alquiler' && empty($contrato_id)) {
                $errores[] = 'Para pagos de alquiler, el contrato es obligatorio.';
            }
            
            if (empty($errores)) {
                try {
                    $pdo->beginTransaction();
                    
                    $sql = "INSERT INTO pagos (tipo_pago, contrato_id, inquilino_id, unidad_id, monto, moneda, 
                            fecha_pago, metodo_pago, referencia, descripcion) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$tipo_pago, $contrato_id, $inquilino_id, $unidad_id, $monto, $moneda,
                                    $fecha_pago, $metodo_pago, $referencia, $descripcion]);
                    
                    $pago_id = $pdo->lastInsertId();
                    
                    // Si es pago de expensa, actualizar estado de la expensa
                    if ($tipo_pago === 'expensa') {
                        // Buscar expensas pendientes de la unidad para el mes actual
                        $mes_actual = date('n');
                        $ano_actual = date('Y');
                        
                        $sql_expensa = "SELECT id, monto_total FROM expensas 
                                       WHERE unidad_id = ? AND periodo_mes = ? AND periodo_ano = ? 
                                       AND estado IN ('pendiente', 'vencida') 
                                       ORDER BY fecha_emision DESC LIMIT 1";
                        $stmt_expensa = $pdo->prepare($sql_expensa);
                        $stmt_expensa->execute([$unidad_id, $mes_actual, $ano_actual]);
                        $expensa = $stmt_expensa->fetch();
                        
                        if ($expensa) {
                            // Verificar si el pago cubre la expensa
                            if ($monto >= $expensa['monto_total']) {
                                $sql_update_expensa = "UPDATE expensas SET estado = 'pagada' WHERE id = ?";
                                $stmt_update = $pdo->prepare($sql_update_expensa);
                                $stmt_update->execute([$expensa['id']]);
                            }
                        }
                    }
                    
                    // Procesar archivo de comprobante si se subió
                    if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
                        $resultado = subirArchivo($_FILES['comprobante'], 'comprobantes');
                        if ($resultado['success']) {
                            $sql_update = "UPDATE pagos SET comprobante_path = ? WHERE id = ?";
                            $stmt_update = $pdo->prepare($sql_update);
                            $stmt_update->execute([$resultado['nombre_archivo'], $pago_id]);
                        }
                    }
                    
                    registrarLog($pdo, "Registró pago ID: $pago_id - $tipo_pago - " . formato_moneda($monto, $moneda));
                    $pdo->commit();
                    $_SESSION['success'] = 'Pago registrado exitosamente.';
                    redirigir('pagos.php');
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Error al registrar pago: " . $e->getMessage());
                    $_SESSION['error'] = 'Error al registrar el pago.';
                }
            } else {
                $_SESSION['error'] = implode('<br>', $errores);
            }
            break;
            
        case 'editar':
            $tipo_pago = sanitizar($_POST['tipo_pago'] ?? '');
            $contrato_id = !empty($_POST['contrato_id']) ? (int)$_POST['contrato_id'] : null;
            $inquilino_id = (int)($_POST['inquilino_id'] ?? 0);
            $unidad_id = (int)($_POST['unidad_id'] ?? 0);
            $monto = (float)($_POST['monto'] ?? 0);
            $moneda = sanitizar($_POST['moneda'] ?? 'ARS');
            $fecha_pago = sanitizar($_POST['fecha_pago'] ?? '');
            $metodo_pago = sanitizar($_POST['metodo_pago'] ?? '');
            $referencia = sanitizar($_POST['referencia'] ?? '');
            $descripcion = sanitizar($_POST['descripcion'] ?? '');
            
            // Validaciones
            $errores = [];
            
            if (empty($tipo_pago) || empty($inquilino_id) || empty($unidad_id)) {
                $errores[] = 'Tipo de pago, inquilino y unidad son obligatorios.';
            }
            
            if ($monto <= 0) {
                $errores[] = 'El monto debe ser mayor a 0.';
            }
            
            if (empty($fecha_pago)) {
                $errores[] = 'La fecha de pago es obligatoria.';
            }
            
            if (empty($metodo_pago)) {
                $errores[] = 'El método de pago es obligatorio.';
            }
            
            if (empty($errores)) {
                try {
                    $pdo->beginTransaction();
                    
                    $sql = "UPDATE pagos SET tipo_pago = ?, contrato_id = ?, inquilino_id = ?, unidad_id = ?, 
                            monto = ?, moneda = ?, fecha_pago = ?, metodo_pago = ?, referencia = ?, descripcion = ? 
                            WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$tipo_pago, $contrato_id, $inquilino_id, $unidad_id, $monto, $moneda,
                                    $fecha_pago, $metodo_pago, $referencia, $descripcion, $id]);
                    
                    // Procesar archivo de comprobante si se subió
                    if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
                        $resultado = subirArchivo($_FILES['comprobante'], 'comprobantes');
                        if ($resultado['success']) {
                            $sql_update = "UPDATE pagos SET comprobante_path = ? WHERE id = ?";
                            $stmt_update = $pdo->prepare($sql_update);
                            $stmt_update->execute([$resultado['nombre_archivo'], $id]);
                        }
                    }
                    
                    registrarLog($pdo, "Editó pago ID: $id - $tipo_pago - " . formato_moneda($monto, $moneda));
                    $pdo->commit();
                    $_SESSION['success'] = 'Pago actualizado exitosamente.';
                    redirigir('pagos.php');
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Error al actualizar pago: " . $e->getMessage());
                    $_SESSION['error'] = 'Error al actualizar el pago.';
                }
            } else {
                $_SESSION['error'] = implode('<br>', $errores);
            }
            break;
            
        case 'eliminar':
            try {
                $pdo->beginTransaction();
                
                // Obtener datos para el log
                $sql = "SELECT tipo_pago, monto, moneda FROM pagos WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                $pago = $stmt->fetch();
                
                $sql = "DELETE FROM pagos WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                
                registrarLog($pdo, "Eliminó pago ID: $id - {$pago['tipo_pago']} - " . formato_moneda($pago['monto'], $pago['moneda']));
                $pdo->commit();
                $_SESSION['success'] = 'Pago eliminado exitosamente.';
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Error al eliminar pago: " . $e->getMessage());
                $_SESSION['error'] = 'Error al eliminar el pago.';
            }
            break;
    }
    
    redirigir('pagos.php');
}

// Obtener pagos con filtros
$where_conditions = [];
$params = [];

if (!empty($busqueda)) {
    $where_conditions[] = "(i.nombre LIKE ? OR i.apellido LIKE ? OR u.numero LIKE ? OR p.referencia LIKE ?)";
    $like_param = "%$busqueda%";
    $params[] = $like_param;
    $params[] = $like_param;
    $params[] = $like_param;
    $params[] = $like_param;
}

if (!empty($filtro_tipo)) {
    $where_conditions[] = "p.tipo_pago = ?";
    $params[] = $filtro_tipo;
}

if (!empty($filtro_metodo)) {
    $where_conditions[] = "p.metodo_pago = ?";
    $params[] = $filtro_metodo;
}

if (!empty($filtro_mes)) {
    $where_conditions[] = "MONTH(p.fecha_pago) = ?";
    $params[] = $filtro_mes;
}

if (!empty($filtro_moneda)) {
    $where_conditions[] = "p.moneda = ?";
    $params[] = $filtro_moneda;
}

$where_conditions[] = "YEAR(p.fecha_pago) = ?";
$params[] = $filtro_ano;

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Consulta para contar total
$sql_count = "SELECT COUNT(*) as total 
              FROM pagos p
              INNER JOIN inquilinos i ON p.inquilino_id = i.id
              INNER JOIN unidades u ON p.unidad_id = u.id
              $where_sql";
$stmt = $pdo->prepare($sql_count);
$stmt->execute($params);
$total_pagos = $stmt->fetch()['total'];
$total_paginas = ceil($total_pagos / $por_pagina);

// Consulta para obtener pagos
$sql = "SELECT p.*, i.nombre as inquilino_nombre, i.apellido as inquilino_apellido,
               u.numero as unidad_numero, u.tipo as unidad_tipo,
               c.id as contrato_id, c.monto_alquiler as contrato_monto,
               ed.nombre as edificio_nombre
        FROM pagos p
        INNER JOIN inquilinos i ON p.inquilino_id = i.id
        INNER JOIN unidades u ON p.unidad_id = u.id
        INNER JOIN edificios ed ON u.edificio_id = ed.id
        LEFT JOIN contratos c ON p.contrato_id = c.id
        $where_sql 
        ORDER BY p.fecha_pago DESC, p.creado_en DESC
        LIMIT $offset, $por_pagina";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pagos = $stmt->fetchAll();

// Obtener listas para selects
$inquilinos = $pdo->query("SELECT id, nombre, apellido FROM inquilinos ORDER BY nombre, apellido")->fetchAll();
$unidades = $pdo->query("SELECT id, numero, tipo FROM unidades WHERE activo = 1 ORDER BY numero")->fetchAll();
$contratos = $pdo->query("SELECT c.id, c.monto_alquiler, c.moneda, i.nombre, i.apellido, u.numero 
                         FROM contratos c
                         INNER JOIN inquilinos i ON c.inquilino_id = i.id
                         INNER JOIN unidades u ON c.unidad_id = u.id
                         WHERE c.estado = 'activo' 
                         ORDER BY i.nombre, i.apellido")->fetchAll();

// Métodos de pago comunes
$metodos_pago = [
    'efectivo' => 'Efectivo',
    'transferencia' => 'Transferencia Bancaria',
    'deposito' => 'Depósito',
    'cheque' => 'Cheque',
    'tarjeta_credito' => 'Tarjeta de Crédito',
    'tarjeta_debito' => 'Tarjeta de Débito',
    'mercadopago' => 'MercadoPago',
    'otro' => 'Otro'
];

// Calcular totales
$total_ars = 0;
$total_usd = 0;
foreach ($pagos as $pago) {
    if ($pago['moneda'] === 'ARS') {
        $total_ars += $pago['monto'];
    } else {
        $total_usd += $pago['monto'];
    }
}

// Breadcrumb
$breadcrumb = generarBreadcrumb([
    ['text' => 'Pagos', 'url' => 'pagos.php']
]);

// Acciones del título (solo para admin)
$acciones_titulo = '';
if (esAdmin()) {
    $acciones_titulo = '
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPago">
            <i class="fas fa-plus me-1"></i>Nuevo Pago
        </button>
    ';
}

require_once 'inc/header.php';
?>

<div class="row">
    <div class="col-12">
        <!-- Filtros y Búsqueda -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="busqueda" placeholder="Buscar por inquilino, unidad o referencia..." 
                               value="<?= htmlspecialchars($busqueda) ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="tipo">
                            <option value="">Todos los tipos</option>
                            <option value="alquiler" <?= $filtro_tipo === 'alquiler' ? 'selected' : '' ?>>Alquiler</option>
                            <option value="expensa" <?= $filtro_tipo === 'expensa' ? 'selected' : '' ?>>Expensa</option>
                            <option value="reserva" <?= $filtro_tipo === 'reserva' ? 'selected' : '' ?>>Reserva</option>
                            <option value="reparacion" <?= $filtro_tipo === 'reparacion' ? 'selected' : '' ?>>Reparación</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="metodo">
                            <option value="">Todos los métodos</option>
                            <?php foreach ($metodos_pago as $valor => $texto): ?>
                                <option value="<?= $valor ?>" <?= $filtro_metodo === $valor ? 'selected' : '' ?>>
                                    <?= $texto ?>
                                </option>
                            <?php endforeach; ?>
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
                    <div class="col-md-1">
                        <select class="form-select" name="ano">
                            <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++): ?>
                                <option value="<?= $i ?>" <?= $filtro_ano == $i ? 'selected' : '' ?>>
                                    <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <select class="form-select" name="moneda">
                            <option value="">Todas</option>
                            <option value="ARS" <?= $filtro_moneda === 'ARS' ? 'selected' : '' ?>>ARS</option>
                            <option value="USD" <?= $filtro_moneda === 'USD' ? 'selected' : '' ?>>USD</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="fas fa-search me-1"></i>Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Resumen de Pagos -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h6 class="card-title">Total Pagos (ARS)</h6>
                        <h4><?= formato_moneda($total_ars, 'ARS') ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h6 class="card-title">Total Pagos (USD)</h6>
                        <h4><?= formato_moneda($total_usd, 'USD') ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h6 class="card-title">Total Registros</h6>
                        <h4><?= $total_pagos ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                        <h6 class="card-title">Página Actual</h6>
                        <h4><?= $pagina ?> / <?= max(1, $total_paginas) ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Pagos -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($pagos)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                        <h5>No se encontraron pagos</h5>
                        <p class="text-muted"><?= empty($busqueda) && empty($filtro_tipo) && empty($filtro_mes) ? 'No hay pagos registrados.' : 'Intente con otros filtros.' ?></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Inquilino / Unidad</th>
                                    <th>Tipo</th>
                                    <th>Monto</th>
                                    <th>Método</th>
                                    <th>Referencia</th>
                                    <th>Comprobante</th>
                                    <?php if (esAdmin()): ?>
                                        <th width="100">Acciones</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pagos as $pago): ?>
                                    <tr>
                                        <td>
                                            <strong><?= date('d/m/Y', strtotime($pago['fecha_pago'])) ?></strong>
                                            <div class="text-muted small"><?= date('H:i', strtotime($pago['creado_en'])) ?></div>
                                        </td>
                                        <td>
                                            <div><strong><?= htmlspecialchars($pago['inquilino_nombre'] . ' ' . $pago['inquilino_apellido']) ?></strong></div>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($pago['unidad_numero']) ?> (<?= htmlspecialchars($pago['unidad_tipo']) ?>)
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $pago['tipo_pago'] === 'alquiler' ? 'primary' : 
                                                ($pago['tipo_pago'] === 'expensa' ? 'success' : 
                                                ($pago['tipo_pago'] === 'reserva' ? 'warning' : 'info')) 
                                            ?>">
                                                <?= ucfirst($pago['tipo_pago']) ?>
                                            </span>
                                            <?php if (!empty($pago['descripcion'])): ?>
                                                <div class="text-muted small"><?= htmlspecialchars($pago['descripcion']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= formato_moneda($pago['monto'], $pago['moneda']) ?></strong>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($metodos_pago[$pago['metodo_pago']] ?? $pago['metodo_pago']) ?>
                                        </td>
                                        <td>
                                            <?= !empty($pago['referencia']) ? htmlspecialchars($pago['referencia']) : '<span class="text-muted">N/A</span>' ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($pago['comprobante_path'])): ?>
                                                <a href="../uploads/comprobantes/<?= htmlspecialchars($pago['comprobante_path']) ?>" 
                                                   target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-file-pdf"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php if (esAdmin()): ?>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            onclick="editarPago(<?= htmlspecialchars(json_encode($pago)) ?>)"
                                                            title="Editar pago">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="eliminarPago(<?= $pago['id'] ?>, '<?= htmlspecialchars($pago['inquilino_nombre'] . ' ' . $pago['inquilino_apellido']) ?>')"
                                                            title="Eliminar pago">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <?php if ($total_paginas > 1): ?>
                        <nav aria-label="Paginación de pagos">
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
                        Mostrando <?= count($pagos) ?> de <?= $total_pagos ?> pagos
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (esAdmin()): ?>
<!-- Modal para Crear/Editar Pago -->
<div class="modal fade" id="modalPago" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formPago" data-validar enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                <input type="hidden" name="accion" id="accion" value="crear">
                <input type="hidden" name="id" id="pago_id" value="">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">Registrar Nuevo Pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="tipo_pago" class="form-label">Tipo de Pago *</label>
                            <select class="form-select" id="tipo_pago" name="tipo_pago" required onchange="actualizarCampos()">
                                <option value="">Seleccionar tipo</option>
                                <option value="alquiler">Alquiler</option>
                                <option value="expensa">Expensa</option>
                                <option value="reserva">Reserva</option>
                                <option value="reparacion">Reparación</option>
                            </select>
                            <div class="invalid-feedback">Seleccione el tipo de pago.</div>
                        </div>
                        
                        <div class="col-md-6" id="campo-contrato">
                            <label for="contrato_id" class="form-label">Contrato</label>
                            <select class="form-select" id="contrato_id" name="contrato_id">
                                <option value="">Seleccionar contrato</option>
                                <?php foreach ($contratos as $contrato): ?>
                                    <option value="<?= $contrato['id'] ?>" data-inquilino="<?= $contrato['id'] ?>" data-unidad="<?= $contrato['id'] ?>">
                                        <?= htmlspecialchars($contrato['nombre'] . ' ' . $contrato['apellido'] . ' - ' . $contrato['numero'] . ' - ' . formato_moneda($contrato['monto_alquiler'], $contrato['moneda'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
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
                            <label for="monto" class="form-label">Monto *</label>
                            <input type="number" step="0.01" class="form-control" id="monto" name="monto" required>
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
                            <label for="fecha_pago" class="form-label">Fecha de Pago *</label>
                            <input type="date" class="form-control" id="fecha_pago" name="fecha_pago" 
                                   value="<?= date('Y-m-d') ?>" required>
                            <div class="invalid-feedback">La fecha de pago es obligatoria.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="metodo_pago" class="form-label">Método de Pago *</label>
                            <select class="form-select" id="metodo_pago" name="metodo_pago" required>
                                <option value="">Seleccionar método</option>
                                <?php foreach ($metodos_pago as $valor => $texto): ?>
                                    <option value="<?= $valor ?>"><?= $texto ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Seleccione el método de pago.</div>
                        </div>
                        
                        <div class="col-12">
                            <label for="referencia" class="form-label">Referencia/Número de Operación</label>
                            <input type="text" class="form-control" id="referencia" name="referencia" 
                                   placeholder="Número de transferencia, cheque, etc.">
                        </div>
                        
                        <div class="col-12">
                            <label for="descripcion" class="form-label">Descripción/Concepto</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="2" 
                                      placeholder="Descripción adicional del pago..."></textarea>
                        </div>
                        
                        <div class="col-12">
                            <label for="comprobante" class="form-label">Comprobante (PDF, JPG, PNG)</label>
                            <input type="file" class="form-control" id="comprobante" name="comprobante" 
                                   accept=".pdf,.jpg,.jpeg,.png">
                            <div class="form-text">Tamaño máximo: 5MB. Formatos permitidos: PDF, JPG, PNG</div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Registrar Pago</button>
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
                <button type="button" class="btn-close" data-bs-djustify-content-between align-items-center" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Está seguro de que desea eliminar el pago de <strong id="nombreInquilinoEliminar"></strong>?</p>
                <p class="text-danger"><small>Esta acción no se puede deshacer.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" id="formEliminarPago">
                    <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" id="pago_id_eliminar">
                    <button type="submit" class="btn btn-danger">Eliminar Pago</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$scripts_extra = '
<script>
function actualizarCampos() {
    const tipoPago = document.getElementById("tipo_pago").value;
    const campoContrato = document.getElementById("campo-contrato");
    
    if (tipoPago === "alquiler") {
        campoContrato.style.display = "block";
        document.getElementById("contrato_id").setAttribute("required", "required");
    } else {
        campoContrato.style.display = "none";
        document.getElementById("contrato_id").removeAttribute("required");
        document.getElementById("contrato_id").value = "";
    }
}

function editarPago(pago) {
    document.getElementById("modalTitulo").textContent = "Editar Pago";
    document.getElementById("accion").value = "editar";
    document.getElementById("pago_id").value = pago.id;
    document.getElementById("tipo_pago").value = pago.tipo_pago;
    document.getElementById("contrato_id").value = pago.contrato_id || "";
    document.getElementById("inquilino_id").value = pago.inquilino_id;
    document.getElementById("unidad_id").value = pago.unidad_id;
    document.getElementById("monto").value = pago.monto;
    document.getElementById("moneda").value = pago.moneda;
    document.getElementById("fecha_pago").value = pago.fecha_pago;
    document.getElementById("metodo_pago").value = pago.metodo_pago;
    document.getElementById("referencia").value = pago.referencia || "";
    document.getElementById("descripcion").value = pago.descripcion || "";
    
    actualizarCampos();
    
    var modal = new bootstrap.Modal(document.getElementById("modalPago"));
    modal.show();
}

function eliminarPago(id, nombre) {
    document.getElementById("nombreInquilinoEliminar").textContent = nombre;
    document.getElementById("pago_id_eliminar").value = id;
    
    var modal = new bootstrap.Modal(document.getElementById("modalConfirmarEliminar"));
    modal.show();
}

// Resetear modal cuando se cierre
document.getElementById("modalPago").addEventListener("hidden.bs.modal", function() {
    document.getElementById("formPago").reset();
    document.getElementById("modalTitulo").textContent = "Registrar Nuevo Pago";
    document.getElementById("accion").value = "crear";
    document.getElementById("pago_id").value = "";
    actualizarCampos();
});

// Inicializar campos al cargar
document.addEventListener("DOMContentLoaded", function() {
    actualizarCampos();
    
    // Auto-completar inquilino y unidad cuando se selecciona contrato
    document.getElementById("contrato_id").addEventListener("change", function() {
        if (this.value) {
            const option = this.options[this.selectedIndex];
            // Aquí podrías implementar lógica para auto-completar inquilino y unidad
            // basado en el contrato seleccionado
        }
    });
});
</script>
';

require_once 'inc/footer.php';
?>