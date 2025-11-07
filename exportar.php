<?php
require_once 'inc/sesion_segura.php';
iniciarSesionSegura();

require_once 'inc/conexion.php';
require_once 'inc/funciones.php';

requireAuth();
requireAdmin();

$pdo = obtenerConexion();
$titulo_pagina = 'Exportar Datos';
$icono_titulo = 'fas fa-file-export';

// Procesar exportación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exportar'])) {
    $tipo = $_POST['tipo'] ?? '';
    $formato = $_POST['formato'] ?? 'csv';
    $fecha_desde = $_POST['fecha_desde'] ?? '';
    $fecha_hasta = $_POST['fecha_hasta'] ?? '';
    
    // Validar token CSRF
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Token de seguridad inválido.';
        redirigir('exportar.php');
    }
    
    try {
        switch ($tipo) {
            case 'pagos':
                exportarPagos($pdo, $formato, $fecha_desde, $fecha_hasta);
                break;
                
            case 'expensas':
                exportarExpensas($pdo, $formato, $fecha_desde, $fecha_hasta);
                break;
                
            case 'contratos':
                exportarContratos($pdo, $formato);
                break;
                
            case 'reservas':
                exportarReservas($pdo, $formato);
                break;
                
            case 'reparaciones':
                exportarReparaciones($pdo, $formato, $fecha_desde, $fecha_hasta);
                break;
                
            default:
                $_SESSION['error'] = 'Tipo de exportación no válido.';
                redirigir('exportar.php');
        }
    } catch (Exception $e) {
        error_log("Error en exportación: " . $e->getMessage());
        $_SESSION['error'] = 'Error al generar el archivo de exportación.';
        redirigir('exportar.php');
    }
}

// Breadcrumb
$breadcrumb = generarBreadcrumb([
    ['text' => 'Exportar Datos', 'url' => 'exportar.php']
]);

require_once 'inc/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-download me-2"></i>Exportar Datos del Sistema
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Exporte los datos del sistema en formatos CSV para su análisis externo o respaldo.
                </div>

                <form method="POST" id="formExportar">
                    <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="tipo" class="form-label">Tipo de Datos *</label>
                            <select class="form-select" id="tipo" name="tipo" required>
                                <option value="">Seleccionar tipo...</option>
                                <option value="pagos">Pagos</option>
                                <option value="expensas">Expensas</option>
                                <option value="contratos">Contratos</option>
                                <option value="reservas">Reservas</option>
                                <option value="reparaciones">Reparaciones</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="formato" class="form-label">Formato *</label>
                            <select class="form-select" id="formato" name="formato" required>
                                <option value="csv">CSV (Excel)</option>
                                <option value="json">JSON</option>
                            </select>
                        </div>
                        
                        <!-- Filtros de fecha (solo para algunos tipos) -->
                        <div class="col-md-6" id="filtro-fecha" style="display: none;">
                            <label for="fecha_desde" class="form-label">Fecha Desde</label>
                            <input type="date" class="form-control" id="fecha_desde" name="fecha_desde">
                        </div>
                        
                        <div class="col-md-6" id="filtro-fecha-hasta" style="display: none;">
                            <label for="fecha_hasta" class="form-label">Fecha Hasta</label>
                            <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta">
                        </div>
                        
                        <div class="col-12 mt-4">
                            <button type="submit" name="exportar" class="btn btn-success">
                                <i class="fas fa-file-export me-2"></i>Generar Exportación
                            </button>
                            <a href="reportes.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Volver a Reportes
                            </a>
                        </div>
                    </div>
                </form>
                
                <!-- Estadísticas de Exportación -->
                <div class="row mt-5" id="estadisticas" style="display: none;">
                    <div class="col-12">
                        <h6 class="border-bottom pb-2">Estadísticas del Conjunto de Datos</h6>
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Total Registros</h6>
                                        <h4 id="total-registros" class="text-primary">0</h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Período</h6>
                                        <h6 id="periodo" class="text-muted">-</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Tamaño Estimado</h6>
                                        <h6 id="tamaño" class="text-muted">-</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Última Exportación</h6>
                                        <h6 id="ultima-exportacion" class="text-muted">-</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Historial de Exportaciones -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history me-2"></i>Historial de Exportaciones
                </h5>
            </div>
            <div class="card-body">
                <?php
                $exportaciones = obtenerHistorialExportaciones($pdo);
                if (empty($exportaciones)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-file-export fa-3x text-muted mb-3"></i>
                        <h6>No hay exportaciones registradas</h6>
                        <p class="text-muted">Las exportaciones que realice aparecerán aquí.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Formato</th>
                                    <th>Registros</th>
                                    <th>Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($exportaciones as $export): ?>
                                    <tr>
                                        <td><?= formato_fecha($export['fecha_exportacion'], true) ?></td>
                                        <td>
                                            <span class="badge bg-info"><?= ucfirst($export['tipo_datos']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= strtoupper($export['formato']) ?></span>
                                        </td>
                                        <td><?= $export['total_registros'] ?></td>
                                        <td><?= htmlspecialchars($export['usuario_nombre']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Mostrar/ocultar filtros de fecha según el tipo
document.getElementById('tipo').addEventListener('change', function() {
    const tipo = this.value;
    const filtroFecha = document.getElementById('filtro-fecha');
    const filtroFechaHasta = document.getElementById('filtro-fecha-hasta');
    const estadisticas = document.getElementById('estadisticas');
    
    // Tipos que requieren filtro de fecha
    const tiposConFecha = ['pagos', 'expensas', 'reparaciones'];
    
    if (tiposConFecha.includes(tipo)) {
        filtroFecha.style.display = 'block';
        filtroFechaHasta.style.display = 'block';
        estadisticas.style.display = 'block';
        // Aquí podrías cargar estadísticas via AJAX
        cargarEstadisticas(tipo);
    } else {
        filtroFecha.style.display = 'none';
        filtroFechaHasta.style.display = 'none';
        estadisticas.style.display = 'none';
    }
});

function cargarEstadisticas(tipo) {
    // Simular carga de estadísticas
    document.getElementById('total-registros').textContent = 'Cargando...';
    document.getElementById('periodo').textContent = '-';
    document.getElementById('tamaño').textContent = '-';
    document.getElementById('ultima-exportacion').textContent = '-';
    
    // En una implementación real, harías una llamada AJAX aquí
    setTimeout(() => {
        document.getElementById('total-registros').textContent = '150';
        document.getElementById('periodo').textContent = 'Ene 2024 - Actual';
        document.getElementById('tamaño').textContent = '~45 KB';
        document.getElementById('ultima-exportacion').textContent = 'Hace 2 días';
    }, 500);
}

// Establecer fecha máxima como hoy
document.getElementById('fecha_hasta').max = new Date().toISOString().split('T')[0];
</script>

<?php
require_once 'inc/footer.php';

// =============================================================================
// FUNCIONES DE EXPORTACIÓN
// =============================================================================

function exportarPagos($pdo, $formato, $fecha_desde = '', $fecha_hasta = '') {
    $where = [];
    $params = [];
    
    if (!empty($fecha_desde)) {
        $where[] = "p.fecha_pago >= ?";
        $params[] = $fecha_desde;
    }
    
    if (!empty($fecha_hasta)) {
        $where[] = "p.fecha_pago <= ?";
        $params[] = $fecha_hasta;
    }
    
    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT p.*, 
                   CONCAT(i.nombre, ' ', i.apellido) as inquilino,
                   u.numero as unidad_numero,
                   p.creado_en
            FROM pagos p
            LEFT JOIN inquilinos i ON p.inquilino_id = i.id
            LEFT JOIN unidades u ON p.unidad_id = u.id
            $where_sql
            ORDER BY p.fecha_pago DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $datos = $stmt->fetchAll();
    
    $nombre_archivo = "pagos_" . date('Y-m-d_His');
    generarArchivoExportacion($datos, $nombre_archivo, $formato, 'pagos', $pdo);
}

function exportarExpensas($pdo, $formato, $fecha_desde = '', $fecha_hasta = '') {
    $where = [];
    $params = [];
    
    if (!empty($fecha_desde)) {
        $where[] = "e.fecha_emision >= ?";
        $params[] = $fecha_desde;
    }
    
    if (!empty($fecha_hasta)) {
        $where[] = "e.fecha_emision <= ?";
        $params[] = $fecha_hasta;
    }
    
    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT e.*, 
                   u.numero as unidad_numero,
                   u.tipo as unidad_tipo,
                   e.creado_en
            FROM expensas e
            INNER JOIN unidades u ON e.unidad_id = u.id
            $where_sql
            ORDER BY e.fecha_emision DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $datos = $stmt->fetchAll();
    
    $nombre_archivo = "expensas_" . date('Y-m-d_His');
    generarArchivoExportacion($datos, $nombre_archivo, $formato, 'expensas', $pdo);
}

function exportarContratos($pdo, $formato) {
    $sql = "SELECT c.*, 
                   CONCAT(i.nombre, ' ', i.apellido) as inquilino,
                   i.dni as inquilino_dni,
                   i.email as inquilino_email,
                   i.telefono as inquilino_telefono,
                   u.numero as unidad_numero,
                   u.tipo as unidad_tipo,
                   c.creado_en
            FROM contratos c
            INNER JOIN inquilinos i ON c.inquilino_id = i.id
            INNER JOIN unidades u ON c.unidad_id = u.id
            ORDER BY c.fecha_inicio DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $datos = $stmt->fetchAll();
    
    $nombre_archivo = "contratos_" . date('Y-m-d_His');
    generarArchivoExportacion($datos, $nombre_archivo, $formato, 'contratos', $pdo);
}

function exportarReservas($pdo, $formato) {
    $sql = "SELECT r.*, 
                   r.creado_en
            FROM reservas r
            ORDER BY r.fecha_creacion DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $datos = $stmt->fetchAll();
    
    $nombre_archivo = "reservas_" . date('Y-m-d_His');
    generarArchivoExportacion($datos, $nombre_archivo, $formato, 'reservas', $pdo);
}

function exportarReparaciones($pdo, $formato, $fecha_desde = '', $fecha_hasta = '') {
    $where = [];
    $params = [];
    
    if (!empty($fecha_desde)) {
        $where[] = "r.fecha_reporte >= ?";
        $params[] = $fecha_desde;
    }
    
    if (!empty($fecha_hasta)) {
        $where[] = "r.fecha_reporte <= ?";
        $params[] = $fecha_hasta;
    }
    
    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT r.*, 
                   u.numero as unidad_numero,
                   u.tipo as unidad_tipo,
                   r.creado_en
            FROM reparaciones r
            LEFT JOIN unidades u ON r.unidad_id = u.id
            $where_sql
            ORDER BY r.fecha_reporte DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $datos = $stmt->fetchAll();
    
    $nombre_archivo = "reparaciones_" . date('Y-m-d_His');
    generarArchivoExportacion($datos, $nombre_archivo, $formato, 'reparaciones', $pdo);
}

function generarArchivoExportacion($datos, $nombre_archivo, $formato, $tipo, $pdo) {
    // Registrar la exportación en el historial
    registrarExportacion($pdo, $tipo, $formato, count($datos));
    
    if ($formato === 'csv') {
        exportarCSV($datos, $nombre_archivo);
    } elseif ($formato === 'json') {
        exportarJSON($datos, $nombre_archivo);
    }
}

function exportarCSV($datos, $nombre_archivo) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Headers
    if (!empty($datos)) {
        fputcsv($output, array_keys($datos[0]));
    }
    
    // Datos
    foreach ($datos as $fila) {
        fputcsv($output, $fila);
    }
    
    fclose($output);
    exit;
}

function exportarJSON($datos, $nombre_archivo) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '.json"');
    
    echo json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function registrarExportacion($pdo, $tipo, $formato, $total_registros) {
    try {
        $sql = "INSERT INTO exportaciones (usuario_id, tipo_datos, formato, total_registros) 
                VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_SESSION['usuario_id'],
            $tipo,
            $formato,
            $total_registros
        ]);
    } catch (PDOException $e) {
        error_log("Error al registrar exportación: " . $e->getMessage());
    }
}

function obtenerHistorialExportaciones($pdo) {
    $sql = "SELECT e.*, u.nombre as usuario_nombre
            FROM exportaciones e
            INNER JOIN usuarios u ON e.usuario_id = u.id
            ORDER BY e.fecha_exportacion DESC
            LIMIT 10";
    
    return $pdo->query($sql)->fetchAll();
}
?>