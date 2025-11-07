<?php
require_once 'inc/sesion_segura.php';
iniciarSesionSegura();

require_once 'config.php';
require_once 'conexion.php';
require_once 'funciones.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$pdo = obtenerConexion();
$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

switch ($action) {
    case 'obtener_expensa':
        $id = (int)($_GET['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
            exit;
        }
        
        try {
            // Obtener datos de la expensa
            $sql = "SELECT e.*, u.numero as unidad_numero, u.tipo as unidad_tipo
                    FROM expensas e
                    INNER JOIN unidades u ON e.unidad_id = u.id
                    WHERE e.id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $expensa = $stmt->fetch();
            
            if (!$expensa) {
                echo json_encode(['success' => false, 'error' => 'Expensa no encontrada']);
                exit;
            }
            
            // Obtener movimientos de la expensa
            $sql_mov = "SELECT me.*, cg.nombre as categoria_nombre
                       FROM movimientos_expensa me
                       LEFT JOIN categorias_gasto cg ON me.categoria_id = cg.id
                       WHERE me.expensa_id = ?";
            $stmt_mov = $pdo->prepare($sql_mov);
            $stmt_mov->execute([$id]);
            $movimientos = $stmt_mov->fetchAll();
            
            $expensa['movimientos'] = $movimientos;
            
            echo json_encode(['success' => true, 'data' => $expensa]);
            
        } catch (PDOException $e) {
            error_log("Error API obtener_expensa: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error del servidor']);
        }
        break;
        
    case 'obtener_detalle_expensa':
        $id = (int)($_GET['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
            exit;
        }
        
        try {
            // Obtener datos de la expensa con formato
            $sql = "SELECT e.*, u.numero as unidad_numero, u.tipo as unidad_tipo,
                           DATE_FORMAT(e.fecha_emision, '%d/%m/%Y') as fecha_emision,
                           DATE_FORMAT(e.fecha_vencimiento, '%d/%m/%Y') as fecha_vencimiento,
                           CONCAT('$ ', FORMAT(e.monto_total, 2, 'es_AR')) as monto_total
                    FROM expensas e
                    INNER JOIN unidades u ON e.unidad_id = u.id
                    WHERE e.id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $expensa = $stmt->fetch();
            
            if (!$expensa) {
                echo json_encode(['success' => false, 'error' => 'Expensa no encontrada']);
                exit;
            }
            
            // Obtener movimientos de la expensa con formato
            $sql_mov = "SELECT me.*, cg.nombre as categoria_nombre,
                               CONCAT('$ ', FORMAT(me.monto, 2, 'es_AR')) as monto
                       FROM movimientos_expensa me
                       LEFT JOIN categorias_gasto cg ON me.categoria_id = cg.id
                       WHERE me.expensa_id = ?";
            $stmt_mov = $pdo->prepare($sql_mov);
            $stmt_mov->execute([$id]);
            $movimientos = $stmt_mov->fetchAll();
            
            $expensa['movimientos'] = $movimientos;
            
            echo json_encode(['success' => true, 'data' => $expensa]);
            
        } catch (PDOException $e) {
            error_log("Error API obtener_detalle_expensa: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error del servidor']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        break;
}
?>