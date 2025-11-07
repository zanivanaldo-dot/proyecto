<?php
session_start();
require_once '../inc/config.php';
require_once '../inc/conexion.php';
require_once '../inc/funciones.php';

requireAuth();

$expensa_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($expensa_id <= 0) {
    die('ID de expensa inválido');
}

try {
    $pdo = obtenerConexion();
    
    // Obtener datos de la expensa
    $sql = "SELECT e.*, u.numero as unidad_numero, u.tipo as unidad_tipo, u.descripcion as unidad_descripcion,
                   CONCAT(i.nombre, ' ', i.apellido) as inquilino_nombre, i.dni as inquilino_dni,
                   ed.nombre as edificio_nombre, ed.direccion as edificio_direccion
            FROM expensas e
            INNER JOIN unidades u ON e.unidad_id = u.id
            INNER JOIN edificios ed ON u.edificio_id = ed.id
            LEFT JOIN contratos c ON u.id = c.unidad_id AND c.estado = 'activo'
            LEFT JOIN inquilinos i ON c.inquilino_id = i.id
            WHERE e.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$expensa_id]);
    $expensa = $stmt->fetch();
    
    if (!$expensa) {
        die('Expensa no encontrada');
    }
    
    // Obtener movimientos de la expensa
    $sql_mov = "SELECT me.*, cg.nombre as categoria_nombre
               FROM movimientos_expensa me
               LEFT JOIN categorias_gasto cg ON me.categoria_id = cg.id
               WHERE me.expensa_id = ?
               ORDER BY me.id";
    $stmt_mov = $pdo->prepare($sql_mov);
    $stmt_mov->execute([$expensa_id]);
    $movimientos = $stmt_mov->fetchAll();
    
} catch (PDOException $e) {
    die('Error al obtener datos de la expensa: ' . $e->getMessage());
}

// Crear PDF simple (usando FPDF o DomPDF - aquí un ejemplo básico con HTML)
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Expensas - <?= htmlspecialchars($expensa['edificio_nombre']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { margin: 0; color: #333; }
        .header p { margin: 5px 0; color: #666; }
        .info-box { background: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .info-label { font-weight: bold; color: #333; }
        .table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .table th, .table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .table th { background: #f5f5f5; font-weight: bold; }
        .total-row { font-weight: bold; background: #e9ecef; }
        .footer { margin-top: 50px; text-align: center; color: #666; font-size: 12px; }
        .status-badge { padding: 5px 10px; border-radius: 3px; font-weight: bold; }
        .status-pagada { background: #d4edda; color: #155724; }
        .status-pendiente { background: #fff3cd; color: #856404; }
        .status-vencida { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="header">
        <h1>COMPROBANTE DE EXPENSAS</h1>
        <h2><?= htmlspecialchars($expensa['edificio_nombre']) ?></h2>
        <p><?= htmlspecialchars($expensa['edificio_direccion']) ?></p>
    </div>
    
    <div class="info-box">
        <div class="info-row">
            <span class="info-label">Unidad:</span>
            <span><?= htmlspecialchars($expensa['unidad_numero'] . ' - ' . $expensa['unidad_tipo']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Descripción:</span>
            <span><?= htmlspecialchars($expensa['unidad_descripcion']) ?></span>
        </div>
        <?php if ($expensa['inquilino_nombre']): ?>
        <div class="info-row">
            <span class="info-label">Inquilino:</span>
            <span><?= htmlspecialchars($expensa['inquilino_nombre']) ?> (DNI: <?= htmlspecialchars($expensa['inquilino_dni']) ?>)</span>
        </div>
        <?php endif; ?>
        <div class="info-row">
            <span class="info-label">Período:</span>
            <span><?= nombreMes($expensa['periodo_mes']) ?> <?= $expensa['periodo_ano'] ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Fecha de Emisión:</span>
            <span><?= date('d/m/Y', strtotime($expensa['fecha_emision'])) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Fecha de Vencimiento:</span>
            <span><?= date('d/m/Y', strtotime($expensa['fecha_vencimiento'])) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Estado:</span>
            <span class="status-badge status-<?= $expensa['estado'] ?>"><?= strtoupper($expensa['estado']) ?></span>
        </div>
    </div>
    
    <?php if (!empty($expensa['detalle'])): ?>
    <div style="margin: 20px 0;">
        <strong>Detalle General:</strong><br>
        <?= nl2br(htmlspecialchars($expensa['detalle'])) ?>
    </div>
    <?php endif; ?>
    
    <table class="table">
        <thead>
            <tr>
                <th width="60%">Descripción</th>
                <th width="20%">Categoría</th>
                <th width="20%">Monto</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($movimientos as $movimiento): ?>
            <tr>
                <td><?= htmlspecialchars($movimiento['descripcion']) ?></td>
                <td><?= htmlspecialchars($movimiento['categoria_nombre'] ?? 'General') ?></td>
                <td><?= formato_moneda($movimiento['monto'], 'ARS') ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="2" style="text-align: right;"><strong>TOTAL:</strong></td>
                <td><strong><?= formato_moneda($expensa['monto_total'], 'ARS') ?></strong></td>
            </tr>
        </tbody>
    </table>
    
    <div class="footer">
        <p>Comprobante generado el <?= date('d/m/Y H:i:s') ?> por <?= APP_NAME ?></p>
        <p>Este es un comprobante de expensas. Conserve este documento para sus registros.</p>
    </div>
    
    <script>
        // Auto-imprimir al cargar (opcional)
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>