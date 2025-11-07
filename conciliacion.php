<?php
session_start();
require_once 'inc/config.php';
require_once 'inc/conexion.php';
require_once 'inc/funciones.php';

requireAuth();

$pdo = obtenerConexion();
$titulo_pagina = 'Conciliación de Pagos';
$icono_titulo = 'fas fa-balance-scale';

// Filtros por defecto
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('n');
$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : date('Y');
$unidad_id = isset($_GET['unidad_id']) ? (int)$_GET['unidad_id'] : '';

// Obtener datos para conciliación
$conciliacion = [];

try {
    // Obtener todas las unidades
    $unidades = $pdo->query("SELECT id, numero, tipo FROM unidades WHERE activo = 1 ORDER BY numero")->fetchAll();
    
    // Obtener contratos activos
    $contratos_activos = $pdo->query("SELECT c.id, c.unidad_id, c.monto_alquiler, c.moneda, 
                                             i.nombre, i.apellido, u.numero
                                      FROM contratos c
                                      INNER JOIN inquilinos i ON c.inquilino_id = i.id
                                      INNER JOIN unidades u ON c.unidad_id = u.id
                                      WHERE c.estado = 'activo' AND c.fecha_vencimiento >= CURDATE()")
                          ->fetchAll();
    
    // Obtener expensas del período
    $sql_expensas = "SELECT e.unidad_id, e.monto_total, e.estado
                     FROM expensas e
                     WHERE e.periodo_ano = ? AND e.periodo_mes = ?";
    $stmt_expensas = $pdo->prepare($sql_expensas);
    $stmt_expensas->execute([$ano, $mes]);
    $expensas = $stmt_expensas->fetchAll();
    
    // Obtener pagos del período
    $sql_pagos = "SELECT p.unidad_id, p.tipo_pago, p.monto, p.moneda
                  FROM pagos p
                  WHERE YEAR(p.fecha_pago) = ? AND MONTH(p.fecha_pago) = ?";
    $stmt_pagos = $pdo->prepare($sql_pagos);
    $stmt_pagos->execute([$ano, $mes]);
    $pagos = $stmt_pagos->fetchAll();
    
    // Procesar conciliación por unidad
    foreach ($unidades as $unidad) {
        if ($unidad_id && $unidad['id'] != $unidad_id) {
            continue;
        }
        
        $contrato = array_filter($contratos_activos, function($c) use ($unidad) {
            return $c['unidad_id'] == $unidad['id'];
        });
        $contrato = !empty($contrato) ? reset($contrato) : null;
        
        $expensa_unidad = array_filter($expensas, function($e) use ($unidad) {
            return $e['unidad_id'] == $unidad['id'];
        });
        $expensa_unidad = !empty($expensa_unidad) ? reset($expensa_unidad) : null;
        
        $pagos_unidad = array_filter($pagos, function($p) use ($unidad) {
            return $p['unidad_id'] == $unidad['id'];
        });
        
        // Calcular totales de pagos
        $total_alquiler = 0;
        $total_expensas = 0;
        $total_otros = 0;
        
        foreach ($pagos_unidad as $pago) {
            if ($pago['tipo_pago'] === 'alquiler') {
                $total_alquiler += $pago['monto'];
            } elseif ($pago['tipo_pago'] === 'expensa') {
                $total_expensas += $pago['monto'];
            } else {
                $total_otros += $pago['monto'];
            }
        }
        
        // Calcular saldos
        $saldo_alquiler = $contrato ? max(0, $contrato['monto_alquiler'] - $total_alquiler) : 0;
        $saldo_expensas = $expensa_unidad ? max(0, $expensa_unidad['monto_total'] - $total_expensas) : 0;
        
        $conciliacion[] = [
            'unidad' => $unidad,
            'contrato' => $contrato,
            'expensa' => $expensa_unidad,
            'pagos_alquiler' => $total_alquiler,
            'pagos_expensas' => $total_expensas,
            'pagos_otros' => $total_otros,
            'saldo_alquiler' => $saldo_alquiler,
            'saldo_expensas' => $saldo_expensas,
            'estado' => $saldo_alquiler == 0 && $saldo_expensas == 0 ? 'al_dia' : ($saldo_alquiler > 0 || $saldo_expensas > 0 ? 'pendiente' : 'adelantado')
        ];
    }
    
} catch (PDOException $e) {
    error_log("Error en conciliación: " . $e->getMessage());
    $_SESSION['error'] = 'Error al generar la conciliación.';
}

// Breadcrumb
$breadcrumb = generarBreadcrumb([
    ['text' => 'Conciliación', 'url' => 'conciliacion.php']
]);

require_once 'inc/header.php';
?>

<div class="row">
    <div class="col-12">
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <select class="form-select" name="mes">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= $mes == $i ? 'selected' : '' ?>>
                                    <?= nombreMes($i) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="ano">
                            <?php for ($i = date('Y') - 1; $i <= date('Y') + 1; $i++): ?>
                                <option value="<?= $i ?>" <?= $ano == $i ? 'selected' : '' ?>>
                                    <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" name="unidad_id">
                            <option value="">Todas las unidades</option>
                            <?php foreach ($unidades as $unidad): ?>
                                <option value="<?= $unidad['id'] ?>" <?= $unidad_id == $unidad['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($unidad['numero'] . ' - ' . $unidad['tipo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-1"></i>Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Resumen General -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h6 class="card-title">Unidades al Día</h6>
                        <h4><?= count(array_filter($conciliacion, fn($c) => $c['estado'] === 'al_dia')) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                        <h6 class="card-title">Con Pendientes</h6>
                        <h4><?= count(array_filter($conciliacion, fn($c) => $c['estado'] === 'pendiente')) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h6 class="card-title">Adelantados</h6>
                        <h4><?= count(array_filter($conciliacion, fn($c) => $c['estado'] === 'adelantado')) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h6 class="card-title">Total Unidades</h6>
                        <h4><?= count($conciliacion) ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Conciliación -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    Conciliación - <?= nombreMes($mes) ?> <?= $ano ?>
                    <?php if ($unidad_id): ?>
                        - Unidad <?= htmlspecialchars(array_column($unidades, 'numero', 'id')[$unidad_id] ?? '') ?>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($conciliacion)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-balance-scale fa-3x text-muted mb-3"></i>
                        <h5>No hay datos para mostrar</h5>
                        <p class="text-muted">No se encontraron unidades con los filtros seleccionados.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Unidad</th>
                                    <th>Inquilino</th>
                                    <th>Alquiler</th>
                                    <th>Expensas</th>
                                    <th>Pagado Alquiler</th>
                                    <th>Pagado Expensas</th>
                                    <th>Saldo Alquiler</th>
                                    <th>Saldo Expensas</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($conciliacion as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($item['unidad']['numero']) ?></strong>
                                            <div class="text-muted small"><?= htmlspecialchars($item['unidad']['tipo']) ?></div>
                                        </td>
                                        <td>
                                            <?php if ($item['contrato']): ?>
                                                <?= htmlspecialchars($item['contrato']['nombre'] . ' ' . $item['contrato']['apellido']) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Sin contrato</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($item['contrato']): ?>
                                                <strong><?= formato_moneda($item['contrato']['monto_alquiler'], $item['contrato']['moneda']) ?></strong>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($item['expensa']): ?>
                                                <strong><?= formato_moneda($item['expensa']['monto_total'], 'ARS') ?></strong>
                                                <div class="text-muted small">
                                                    <?= ucfirst($item['expensa']['estado']) ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($item['pagos_alquiler'] > 0): ?>
                                                <span class="text-success"><?= formato_moneda($item['pagos_alquiler'], $item['contrato']['moneda'] ?? 'ARS') ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($item['pagos_expensas'] > 0): ?>
                                                <span class="text-success"><?= formato_moneda($item['pagos_expensas'], 'ARS') ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($item['saldo_alquiler'] > 0): ?>
                                                <span class="text-danger"><strong><?= formato_moneda($item['saldo_alquiler'], $item['contrato']['moneda'] ?? 'ARS') ?></strong></span>
                                            <?php else: ?>
                                                <span class="text-success">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($item['saldo_expensas'] > 0): ?>
                                                <span class="text-danger"><strong><?= formato_moneda($item['saldo_expensas'], 'ARS') ?></strong></span>
                                            <?php else: ?>
                                                <span class="text-success">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $item['estado'] === 'al_dia' ? 'success' : 
                                                ($item['estado'] === 'pendiente' ? 'warning' : 'info')
                                            ?>">
                                                <?= $item['estado'] === 'al_dia' ? 'Al Día' : 
                                                   ($item['estado'] === 'pendiente' ? 'Pendiente' : 'Adelantado') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Resumen de Totales -->
        <?php if (!empty($conciliacion)): ?>
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Resumen de Deudas</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $total_deuda_alquiler = array_sum(array_column($conciliacion, 'saldo_alquiler'));
                        $total_deuda_expensas = array_sum(array_column($conciliacion, 'saldo_expensas'));
                        ?>
                        <div class="row text-center">
                            <div class="col-6">
                                <h4 class="text-danger"><?= formato_moneda($total_deuda_alquiler, 'ARS') ?></h4>
                                <small class="text-muted">Deuda Total Alquiler</small>
                            </div>
                            <div class="col-6">
                                <h4 class="text-danger"><?= formato_moneda($total_deuda_expensas, 'ARS') ?></h4>
                                <small class="text-muted">Deuda Total Expensas</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Resumen de Cobranzas</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $total_cobrado_alquiler = array_sum(array_column($conciliacion, 'pagos_alquiler'));
                        $total_cobrado_expensas = array_sum(array_column($conciliacion, 'pagos_expensas'));
                        ?>
                        <div class="row text-center">
                            <div class="col-6">
                                <h4 class="text-success"><?= formato_moneda($total_cobrado_alquiler, 'ARS') ?></h4>
                                <small class="text-muted">Cobrado Alquiler</small>
                            </div>
                            <div class="col-6">
                                <h4 class="text-success"><?= formato_moneda($total_cobrado_expensas, 'ARS') ?></h4>
                                <small class="text-muted">Cobrado Expensas</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once 'inc/footer.php';
?>