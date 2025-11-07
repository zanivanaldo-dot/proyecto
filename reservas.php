<?php
// Incluir configuración segura de sesión primero
require_once 'inc/sesion_segura.php';
iniciarSesionSegura();

require_once 'inc/conexion.php';
require_once 'inc/funciones.php';

requireAuth();

$pdo = obtenerConexion();
$titulo_pagina = 'Gestión de Reservas';
$icono_titulo = 'fas fa-piggy-bank';

$pdo = obtenerConexion();
$titulo_pagina = 'Gestión de Reservas';
$icono_titulo = 'fas fa-piggy-bank';

// Configuración de paginación
$por_pagina = 10;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $por_pagina;

// Búsqueda y filtros
$filtro_estado = isset($_GET['estado']) ? sanitizar($_GET['estado']) : '';
$filtro_origen = isset($_GET['origen']) ? sanitizar($_GET['origen']) : '';
$filtro_moneda = isset($_GET['moneda']) ? sanitizar($_GET['moneda']) : '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    // Verificar token CSRF
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Token de seguridad inválido.';
        redirigir('reservas.php');
    }
    
    // Verificar permisos para acciones de modificación
    if (!esAdmin() && in_array($accion, ['crear', 'editar', 'eliminar', 'usar_reserva'])) {
        $_SESSION['error'] = 'No tiene permisos para realizar esta acción.';
        redirigir('reservas.php');
    }
    
    switch ($accion) {
        case 'crear':
            $descripcion = sanitizar($_POST['descripcion'] ?? '');
            $monto = (float)($_POST['monto'] ?? 0);
            $moneda = sanitizar($_POST['moneda'] ?? 'ARS');
            $fecha_creacion = sanitizar($_POST['fecha_creacion'] ?? '');
            $origen = sanitizar($_POST['origen'] ?? 'aporte_extra');
            
            // Validaciones
            $errores = [];
            
            if (empty($descripcion)) {
                $errores[] = 'La descripción es obligatoria.';
            }
            
            if ($monto <= 0) {
                $errores[] = 'El monto debe ser mayor a 0.';
            }
            
            if (empty($fecha_creacion)) {
                $errores[] = 'La fecha de creación es obligatoria.';
            }
            
            if (empty($errores)) {
                try {
                    $pdo->beginTransaction();
                    
                    $sql = "INSERT INTO reservas (descripcion, monto, moneda, fecha_creacion, origen, estado) 
                            VALUES (?, ?, ?, ?, ?, 'disponible')";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$descripcion, $monto, $moneda, $fecha_creacion, $origen]);
                    
                    $reserva_id = $pdo->lastInsertId();
                    registrarLog($pdo, "Creó reserva ID: $reserva_id - " . formato_moneda($monto, $moneda));
                    
                    $pdo->commit();
                    $_SESSION['success'] = 'Reserva creada exitosamente.';
                    redirigir('reservas.php');
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Error al crear reserva: " . $e->getMessage());
                    $_SESSION['error'] = 'Error al crear la reserva.';
                }
            } else {
                $_SESSION['error'] = implode('<br>', $errores);
            }
            break;
            
        case 'editar':
            $descripcion = sanitizar($_POST['descripcion'] ?? '');
            $monto = (float)($_POST['monto'] ?? 0);
            $moneda = sanitizar($_POST['moneda'] ?? 'ARS');
            $fecha_creacion = sanitizar($_POST['fecha_creacion'] ?? '');
            $origen = sanitizar($_POST['origen'] ?? 'aporte_extra');
            $estado = sanitizar($_POST['estado'] ?? 'disponible');
            
            // Validaciones
            $errores = [];
            
            if (empty($descripcion)) {
                $errores[] = 'La descripción es obligatoria.';
            }
            
            if ($monto <= 0) {
                $errores[] = 'El monto debe ser mayor a 0.';
            }
            
            if (empty($fecha_creacion)) {
                $errores[] = 'La fecha de creación es obligatoria.';
            }
            
            if (empty($errores)) {
                try {
                    $pdo->beginTransaction();
                    
                    $sql = "UPDATE reservas SET descripcion = ?, monto = ?, moneda = ?, 
                            fecha_creacion = ?, origen = ?, estado = ? 
                            WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$descripcion, $monto, $moneda, $fecha_creacion, $origen, $estado, $id]);
                    
                    registrarLog($pdo, "Editó reserva ID: $id - " . formato_moneda($monto, $moneda));
                    $pdo->commit();
                    $_SESSION['success'] = 'Reserva actualizada exitosamente.';
                    redirigir('reservas.php');
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Error al actualizar reserva: " . $e->getMessage());
                    $_SESSION['error'] = 'Error al actualizar la reserva.';
                }
            } else {
                $_SESSION['error'] = implode('<br>', $errores);
            }
            break;
            
        case 'eliminar':
            try {
                $pdo->beginTransaction();
                
                // Obtener datos para el log
                $sql = "SELECT descripcion, monto, moneda FROM reservas WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                $reserva = $stmt->fetch();
                
                $sql = "DELETE FROM reservas WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                
                registrarLog($pdo, "Eliminó reserva ID: $id - {$reserva['descripcion']}");
                $pdo->commit();
                $_SESSION['success'] = 'Reserva eliminada exitosamente.';
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Error al eliminar reserva: " . $e->getMessage());
                $_SESSION['error'] = 'Error al eliminar la reserva.';
            }
            break;
            
        case 'usar_reserva':
            $reparacion_id = (int)($_POST['reparacion_id'] ?? 0);
            $monto_usar = (float)($_POST['monto_usar'] ?? 0);
            $reserva_id_usar = (int)($_POST['reserva_id_usar'] ?? 0);
            
            // Validaciones
            $errores = [];
            
            if (empty($reparacion_id)) {
                $errores[] = 'La reparación es obligatoria.';
            }
            
            if ($monto_usar <= 0) {
                $errores[] = 'El monto a usar debe ser mayor a 0.';
            }
            
            if (empty($reserva_id_usar)) {
                $errores[] = 'La reserva a utilizar es obligatoria.';
            }
            
            // Verificar que la reserva tenga saldo suficiente
            $sql_saldo = "SELECT monto, moneda, estado FROM reservas WHERE id = ?";
            $stmt_saldo = $pdo->prepare($sql_saldo);
            $stmt_saldo->execute([$reserva_id_usar]);
            $reserva = $stmt_saldo->fetch();
            
            if (!$reserva || $reserva['estado'] !== 'disponible') {
                $errores[] = 'La reserva seleccionada no está disponible.';
            } elseif ($reserva['monto'] < $monto_usar) {
                $errores[] = 'La reserva no tiene saldo suficiente.';
            }
            
            if (empty($errores)) {
                try {
                    $pdo->beginTransaction();
                    
                    // Actualizar la reparación con la fuente de financiación
                    $sql_reparacion = "UPDATE reparaciones 
                                      SET fuente_financiacion = 'reserva', monto_gastado = ?
                                      WHERE id = ?";
                    $stmt_reparacion = $pdo->prepare($sql_reparacion);
                    $stmt_reparacion->execute([$monto_usar, $reparacion_id]);
                    
                    // Actualizar el estado de la reserva (si se usa todo el monto, marcar como usado)
                    if ($reserva['monto'] == $monto_usar) {
                        $sql_reserva = "UPDATE reservas SET estado = 'usado' WHERE id = ?";
                        $stmt_reserva = $pdo->prepare($sql_reserva);
                        $stmt_reserva->execute([$reserva_id_usar]);
                    } else {
                        // Si se usa solo parte, crear un nuevo registro con el saldo
                        $nuevo_monto = $reserva['monto'] - $monto_usar;
                        
                        // Actualizar la reserva original con el monto usado
                        $sql_update = "UPDATE reservas SET monto = ? WHERE id = ?";
                        $stmt_update = $pdo->prepare($sql_update);
                        $stmt_update->execute([$monto_usar, $reserva_id_usar]);
                        
                        // Crear nueva reserva con el saldo
                        $sql_nueva = "INSERT INTO reservas (descripcion, monto, moneda, fecha_creacion, origen, estado) 
                                     VALUES (?, ?, ?, CURDATE(), 'aporte_extra', 'disponible')";
                        $stmt_nueva = $pdo->prepare($sql_nueva);
                        $descripcion = "Saldo restante de reserva #{$reserva_id_usar}";
                        $stmt_nueva->execute([$descripcion, $nuevo_monto, $reserva['moneda']]);
                    }
                    
                    // Registrar pago por el uso de la reserva
                    $sql_pago = "INSERT INTO pagos (tipo_pago, inquilino_id, unidad_id, monto, moneda, 
                                   fecha_pago, metodo_pago, descripcion) 
                                SELECT 'reserva', 1, r.unidad_id, ?, ?, CURDATE(), 'transferencia', 
                                       CONCAT('Uso de reserva para reparación: ', r.descripcion)
                                FROM reparaciones r 
                                WHERE r.id = ?";
                    $stmt_pago = $pdo->prepare($sql_pago);
                    $stmt_pago->execute([$monto_usar, $reserva['moneda'], $reparacion_id]);
                    
                    registrarLog($pdo, "Usó reserva ID: $reserva_id_usar para reparación ID: $reparacion_id - " . formato_moneda($monto_usar, $reserva['moneda']));
                    $pdo->commit();
                    $_SESSION['success'] = 'Reserva utilizada exitosamente para la reparación.';
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Error al usar reserva: " . $e->getMessage());
                    $_SESSION['error'] = 'Error al utilizar la reserva.';
                }
            } else {
                $_SESSION['error'] = implode('<br>', $errores);
            }
            break;
    }
    
    redirigir('reservas.php');
}

// Obtener reservas con filtros
$where_conditions = [];
$params = [];

if (!empty($filtro_estado)) {
    $where_conditions[] = "r.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_origen)) {
    $where_conditions[] = "r.origen = ?";
    $params[] = $filtro_origen;
}

if (!empty($filtro_moneda)) {
    $where_conditions[] = "r.moneda = ?";
    $params[] = $filtro_moneda;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Consulta para contar total
$sql_count = "SELECT COUNT(*) as total FROM reservas r $where_sql";
$stmt = $pdo->prepare($sql_count);
$stmt->execute($params);
$total_reservas = $stmt->fetch()['total'];
$total_paginas = ceil($total_reservas / $por_pagina);

// Consulta para obtener reservas
$sql = "SELECT r.* 
        FROM reservas r 
        $where_sql 
        ORDER BY r.fecha_creacion DESC, r.creado_en DESC
        LIMIT $offset, $por_pagina";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservas = $stmt->fetchAll();

// Obtener reparaciones pendientes para usar reservas
$reparaciones_pendientes = $pdo->query("
    SELECT r.id, r.descripcion, r.monto_estimado, r.unidad_id,
           u.numero as unidad_numero, u.tipo as unidad_tipo
    FROM reparaciones r
    LEFT JOIN unidades u ON r.unidad_id = u.id
    WHERE r.estado = 'pendiente' AND r.fuente_financiacion = 'otro'
    ORDER BY r.fecha_reporte DESC
")->fetchAll();

// Calcular saldos totales
$saldo_ars = saldoReservas($pdo, 'ARS');
$saldo_usd = saldoReservas($pdo, 'USD');

// Breadcrumb
$breadcrumb = generarBreadcrumb([
    ['text' => 'Reservas', 'url' => 'reservas.php']
]);

// Acciones del título (solo para admin)
$acciones_titulo = '';
if (esAdmin()) {
    $acciones_titulo = '
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalReserva">
            <i class="fas fa-plus me-1"></i>Nueva Reserva
        </button>
    ';
}

require_once 'inc/header.php';
?>

<div class="row">
    <div class="col-12">
        <!-- Saldo de Reservas -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h6 class="card-title">Saldo Disponible (ARS)</h6>
                        <h2><?= formato_moneda($saldo_ars, 'ARS') ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h6 class="card-title">Saldo Disponible (USD)</h6>
                        <h2><?= formato_moneda($saldo_usd, 'USD') ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <select class="form-select" name="estado">
                            <option value="">Todos los estados</option>
                            <option value="disponible" <?= $filtro_estado === 'disponible' ? 'selected' : '' ?>>Disponible</option>
                            <option value="usado" <?= $filtro_estado === 'usado' ? 'selected' : '' ?>>Usado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="origen">
                            <option value="">Todos los orígenes</option>
                            <option value="excedente_expensa" <?= $filtro_origen === 'excedente_expensa' ? 'selected' : '' ?>>Excedente Expensas</option>
                            <option value="aporte_extra" <?= $filtro_origen === 'aporte_extra' ? 'selected' : '' ?>>Aporte Extra</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="moneda">
                            <option value="">Todas las monedas</option>
                            <option value="ARS" <?= $filtro_moneda === 'ARS' ? 'selected' : '' ?>>ARS</option>
                            <option value="USD" <?= $filtro_moneda === 'USD' ? 'selected' : '' ?>>USD</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="fas fa-filter me-1"></i>Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de Reservas -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($reservas)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-piggy-bank fa-3x text-muted mb-3"></i>
                        <h5>No se encontraron reservas</h5>
                        <p class="text-muted"><?= empty($filtro_estado) && empty($filtro_origen) && empty($filtro_moneda) ? 'No hay reservas registradas.' : 'Intente con otros filtros.' ?></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Descripción</th>
                                    <th>Monto</th>
                                    <th>Origen</th>
                                    <th>Fecha Creación</th>
                                    <th>Estado</th>
                                    <?php if (esAdmin()): ?>
                                        <th width="120">Acciones</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservas as $reserva): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($reserva['descripcion']) ?>
                                        </td>
                                        <td>
                                            <strong><?= formato_moneda($reserva['monto'], $reserva['moneda']) ?></strong>
                                        </td>
                                        <td>
                                            <?= $reserva['origen'] === 'excedente_expensa' ? 'Excedente Expensas' : 'Aporte Extra' ?>
                                        </td>
                                        <td>
                                            <?= formato_fecha($reserva['fecha_creacion']) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $reserva['estado'] === 'disponible' ? 'success' : 'secondary' ?>">
                                                <?= $reserva['estado'] === 'disponible' ? 'Disponible' : 'Usado' ?>
                                            </span>
                                        </td>
                                        <?php if (esAdmin()): ?>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            onclick="editarReserva(<?= htmlspecialchars(json_encode($reserva)) ?>)"
                                                            title="Editar reserva">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($reserva['estado'] === 'disponible'): ?>
                                                        <button type="button" class="btn btn-outline-warning" 
                                                                onclick="usarReserva(<?= $reserva['id'] ?>, '<?= formato_moneda($reserva['monto'], $reserva['moneda']) ?>')"
                                                                title="Usar reserva">
                                                            <i class="fas fa-hand-holding-usd"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="eliminarReserva(<?= $reserva['id'] ?>, '<?= htmlspecialchars($reserva['descripcion']) ?>')"
                                                            title="Eliminar reserva">
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
                        <nav aria-label="Paginación de reservas">
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
                        Mostrando <?= count($reservas) ?> de <?= $total_reservas ?> reservas
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (esAdmin()): ?>
<!-- Modal para Crear/Editar Reserva -->
<div class="modal fade" id="modalReserva" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="formReserva" data-validar>
                <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                <input type="hidden" name="accion" id="accion" value="crear">
                <input type="hidden" name="id" id="reserva_id" value="">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">Nueva Reserva</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="descripcion" class="form-label">Descripción *</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3" required></textarea>
                            <div class="invalid-feedback">La descripción es obligatoria.</div>
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
                            <label for="fecha_creacion" class="form-label">Fecha de Creación *</label>
                            <input type="date" class="form-control" id="fecha_creacion" name="fecha_creacion" 
                                   value="<?= date('Y-m-d') ?>" required>
                            <div class="invalid-feedback">La fecha de creación es obligatoria.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="origen" class="form-label">Origen *</label>
                            <select class="form-select" id="origen" name="origen" required>
                                <option value="excedente_expensa">Excedente de Expensas</option>
                                <option value="aporte_extra">Aporte Extraordinario</option>
                            </select>
                        </div>
                        
                        <div class="col-12" id="campo-estado" style="display: none;">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="disponible">Disponible</option>
                                <option value="usado">Usado</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Reserva</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Usar Reserva -->
<div class="modal fade" id="modalUsarReserva" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="formUsarReserva">
                <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                <input type="hidden" name="accion" value="usar_reserva">
                <input type="hidden" name="reserva_id_usar" id="reserva_id_usar">
                
                <div class="modal-header">
                    <h5 class="modal-title">Usar Reserva</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Está a punto de utilizar la reserva: <strong id="reservaInfo"></strong>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="reparacion_id" class="form-label">Reparación a Financiar *</label>
                            <select class="form-select" id="reparacion_id" name="reparacion_id" required>
                                <option value="">Seleccionar reparación</option>
                                <?php foreach ($reparaciones_pendientes as $reparacion): ?>
                                    <option value="<?= $reparacion['id'] ?>">
                                        <?= htmlspecialchars($reparacion['descripcion']) ?> 
                                        (<?= htmlspecialchars($reparacion['unidad_numero'] ?? 'General') ?>)
                                        - <?= formato_moneda($reparacion['monto_estimado'] ?? 0, 'ARS') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($reparaciones_pendientes)): ?>
                                <div class="form-text text-warning">
                                    No hay reparaciones pendientes disponibles para financiar.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-12">
                            <label for="monto_usar" class="form-label">Monto a Utilizar *</label>
                            <input type="number" step="0.01" class="form-control" id="monto_usar" name="monto_usar" required>
                            <div class="form-text">Monto máximo disponible: <span id="monto_maximo"></span></div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning" id="btnUsarReserva" <?= empty($reparaciones_pendientes) ? 'disabled' : '' ?>>
                        <i class="fas fa-hand-holding-usd me-1"></i>Usar Reserva
                    </button>
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
                <p>¿Está seguro de que desea eliminar la reserva <strong id="descripcionReservaEliminar"></strong>?</p>
                <p class="text-danger"><small>Esta acción no se puede deshacer.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" id="formEliminarReserva">
                    <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" id="reserva_id_eliminar">
                    <button type="submit" class="btn btn-danger">Eliminar Reserva</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$scripts_extra = '
<script>
function editarReserva(reserva) {
    document.getElementById("modalTitulo").textContent = "Editar Reserva";
    document.getElementById("accion").value = "editar";
    document.getElementById("reserva_id").value = reserva.id;
    document.getElementById("descripcion").value = reserva.descripcion;
    document.getElementById("monto").value = reserva.monto;
    document.getElementById("moneda").value = reserva.moneda;
    document.getElementById("fecha_creacion").value = reserva.fecha_creacion;
    document.getElementById("origen").value = reserva.origen;
    document.getElementById("estado").value = reserva.estado;
    
    document.getElementById("campo-estado").style.display = "block";
    
    var modal = new bootstrap.Modal(document.getElementById("modalReserva"));
    modal.show();
}

function usarReserva(id, info) {
    document.getElementById("reserva_id_usar").value = id;
    document.getElementById("reservaInfo").textContent = info;
    document.getElementById("monto_maximo").textContent = info;
    
    // Establecer el monto máximo como valor por defecto
    const monto = parseFloat(info.replace(/[^\d.,]/g, "").replace(",", "."));
    document.getElementById("monto_usar").value = monto.toFixed(2);
    document.getElementById("monto_usar").setAttribute("max", monto);
    
    var modal = new bootstrap.Modal(document.getElementById("modalUsarReserva"));
    modal.show();
}

function eliminarReserva(id, descripcion) {
    document.getElementById("descripcionReservaEliminar").textContent = descripcion;
    document.getElementById("reserva_id_eliminar").value = id;
    
    var modal = new bootstrap.Modal(document.getElementById("modalConfirmarEliminar"));
    modal.show();
}

// Resetear modal cuando se cierre
document.getElementById("modalReserva").addEventListener("hidden.bs.modal", function() {
    document.getElementById("formReserva").reset();
    document.getElementById("modalTitulo").textContent = "Nueva Reserva";
    document.getElementById("accion").value = "crear";
    document.getElementById("reserva_id").value = "";
    document.getElementById("campo-estado").style.display = "none";
});

// Validar monto al usar reserva
document.getElementById("monto_usar").addEventListener("input", function() {
    const montoMaximo = parseFloat(this.getAttribute("max"));
    const montoIngresado = parseFloat(this.value) || 0;
    
    if (montoIngresado > montoMaximo) {
        this.setCustomValidity("El monto no puede superar el saldo disponible");
    } else {
        this.setCustomValidity("");
    }
});
</script>
';

require_once 'inc/footer.php';
?>