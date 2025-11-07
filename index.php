<?php
session_start();
require_once 'inc/config.php';
require_once 'inc/conexion.php';
require_once 'inc/funciones.php';

requireAuth();

$pdo = obtenerConexion();
$titulo_pagina = 'Dashboard';
$icono_titulo = 'fas fa-tachometer-alt';

// Obtener estadísticas
$stats = obtenerEstadisticasDashboard($pdo);
$alertas_contratos = alertasContratosVencidos($pdo, 30);
$saldo_reservas_ars = saldoReservas($pdo, 'ARS');
$saldo_reservas_usd = saldoReservas($pdo, 'USD');

// Breadcrumb
$breadcrumb = generarBreadcrumb([
    ['text' => 'Dashboard', 'url' => 'index.php']
]);

require_once 'inc/header.php';
?>

<div class="row">
    <!-- Estadísticas Rápidas -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card success h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs fw-bold text-success text-uppercase mb-1">
                            Unidades Ocupadas
                        </div>
                        <div class="h5 mb-0 fw-bold text-gray-800">
                            <?= $stats['unidades_ocupadas'] ?> / <?= $stats['total_unidades'] ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-home fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card primary h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                            Ingresos del Mes
                        </div>
                        <div class="h5 mb-0 fw-bold text-gray-800">
                            <?= formato_moneda($stats['ingresos_mes_actual'], 'ARS') ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card warning h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs fw-bold text-warning text-uppercase mb-1">
                            Contratos por Vencer
                        </div>
                        <div class="h5 mb-0 fw-bold text-gray-800">
                            <?= $stats['contratos_por_vencer'] ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar-exclamation fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card danger h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs fw-bold text-danger text-uppercase mb-1">
                            Expensas Vencidas
                        </div>
                        <div class="h5 mb-0 fw-bold text-gray-800">
                            <?= $stats['expensas_vencidas'] ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Alertas para Admin -->
    <?php if (esAdmin() && !empty($alertas_contratos)): ?>
    <div class="col-lg-6 mb-4">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Contratos por Vencer (30 días)
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($alertas_contratos as $alerta): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><?= htmlspecialchars($alerta['inquilino']) ?></h6>
                                <small class="text-muted">
                                    <?= htmlspecialchars($alerta['unidad']) ?> - 
                                    <?= formato_moneda($alerta['monto_alquiler'], $alerta['moneda']) ?>
                                </small>
                            </div>
                            <span class="badge bg-<?= $alerta['dias_restantes'] <= 7 ? 'danger' : 'warning' ?> rounded-pill">
                                <?= $alerta['dias_restantes'] ?> días
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Saldo de Reservas -->
    <?php if (esAdmin()): ?>
    <div class="col-lg-6 mb-4">
        <div class="card border-success">
            <div class="card-header bg-success text-white">
                <i class="fas fa-piggy-bank me-2"></i>
                Fondo de Reservas
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <h3 class="text-success"><?= formato_moneda($saldo_reservas_ars, 'ARS') ?></h3>
                        <small class="text-muted">Pesos Argentinos</small>
                    </div>
                    <div class="col-6">
                        <h3 class="text-success"><?= formato_moneda($saldo_reservas_usd, 'USD') ?></h3>
                        <small class="text-muted">Dólares Estadounidenses</small>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <a href="reservas.php" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-arrow-right me-1"></i>Gestionar Reservas
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row">
    <!-- Acciones Rápidas -->
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-bolt me-2"></i>Acciones Rápidas
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3 col-6">
                        <a href="contratos.php" class="btn btn-outline-primary w-100 h-100 py-3">
                            <i class="fas fa-file-contract fa-2x mb-2"></i><br>
                            Contratos
                        </a>
                    </div>
                    <div class="col-md-3 col-6">
                        <a href="expensas.php" class="btn btn-outline-success w-100 h-100 py-3">
                            <i class="fas fa-receipt fa-2x mb-2"></i><br>
                            Expensas
                        </a>
                    </div>
                    <div class="col-md-3 col-6">
                        <a href="pagos.php" class="btn btn-outline-info w-100 h-100 py-3">
                            <i class="fas fa-money-bill-wave fa-2x mb-2"></i><br>
                            Pagos
                        </a>
                    </div>
                    <div class="col-md-3 col-6">
                        <a href="reportes.php" class="btn btn-outline-warning w-100 h-100 py-3">
                            <i class="fas fa-chart-bar fa-2x mb-2"></i><br>
                            Reportes
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Scripts para gráficos (se agregarán en PARTE 10)
$scripts_extra = '
<script>
// Inicializar tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll(\'[data-bs-toggle="tooltip"]\'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
</script>
';

require_once 'inc/footer.php';
?>