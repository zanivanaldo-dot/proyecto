[file name]: api_grafico_comparativo_ingresos.php
[file content begin]
<?php
session_start();
require_once '../inc/config.php';
require_once '../inc/conexion.php';
require_once '../inc/funciones.php';

requireAuth();

header('Content-Type: application/json');

$pdo = obtenerConexion();

// Obtener parámetros
$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : date('Y');
$moneda = isset($_GET['moneda']) ? sanitizar($_GET['moneda']) : 'ARS';
$unidad_id = isset($_GET['unidad_id']) ? (int)$_GET['unidad_id'] : null;

try {
    $datos = [
        'success' => true,
        'meses' => [],
        'alquileres' => [],
        'expensas' => [],
        'otros' => []
    ];
    
    for ($mes = 1; $mes <= 12; $mes++) {
        $datos['meses'][] = nombreMes($mes);
        
        // Ingresos por alquileres
        $sql_alquileres = "SELECT COALESCE(SUM(p.monto), 0) as total
                          FROM pagos p";
        
        $where_alquiler = " WHERE p.tipo_pago = 'alquiler' 
                           AND p.moneda = ?
                           AND YEAR(p.fecha_pago) = ? 
                           AND MONTH(p.fecha_pago) = ?";
        $params_alquiler = [$moneda, $ano, $mes];
        
        if ($unidad_id) {
            $where_alquiler .= " AND p.unidad_id = ?";
            $params_alquiler[] = $unidad_id;
        }
        
        $sql_alquileres .= $where_alquiler;
        
        $stmt = $pdo->prepare($sql_alquileres);
        $stmt->execute($params_alquiler);
        $alquileres = $stmt->fetch();
        $datos['alquileres'][] = (float)$alquileres['total'];
        
        // Ingresos por expensas
        $sql_expensas = "SELECT COALESCE(SUM(p.monto), 0) as total
                        FROM pagos p";
        
        $where_expensas = " WHERE p.tipo_pago = 'expensa' 
                           AND p.moneda = ?
                           AND YEAR(p.fecha_pago) = ? 
                           AND MONTH(p.fecha_pago) = ?";
        $params_expensas = [$moneda, $ano, $mes];
        
        if ($unidad_id) {
            $where_expensas .= " AND p.unidad_id = ?";
            $params_expensas[] = $unidad_id;
        }
        
        $sql_expensas .= $where_expensas;
        
        $stmt = $pdo->prepare($sql_expensas);
        $stmt->execute($params_expensas);
        $expensas = $stmt->fetch();
        $datos['expensas'][] = (float)$expensas['total'];
        
        // Otros ingresos
        $sql_otros = "SELECT COALESCE(SUM(p.monto), 0) as total
                     FROM pagos p";
        
        $where_otros = " WHERE p.tipo_pago NOT IN ('alquiler', 'expensa')
                        AND p.moneda = ?
                        AND YEAR(p.fecha_pago) = ? 
                        AND MONTH(p.fecha_pago) = ?";
        $params_otros = [$moneda, $ano, $mes];
        
        if ($unidad_id) {
            $where_otros .= " AND p.unidad_id = ?";
            $params_otros[] = $unidad_id;
        }
        
        $sql_otros .= $where_otros;
        
        $stmt = $pdo->prepare($sql_otros);
        $stmt->execute($params_otros);
        $otros = $stmt->fetch();
        $datos['otros'][] = (float)$otros['total'];
    }
    
    echo json_encode($datos);
    
} catch (PDOException $e) {
    error_log("Error en API comparativo ingresos: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener datos',
        'meses' => [],
        'alquileres' => [],
        'expensas' => [],
        'otros' => []
    ]);
}
?>
[file content end]