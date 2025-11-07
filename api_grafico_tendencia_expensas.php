[file name]: api_grafico_tendencia_expensas.php
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
$unidad_id = isset($_GET['unidad_id']) ? (int)$_GET['unidad_id'] : null;

try {
    $datos = [
        'success' => true,
        'meses' => [],
        'montos' => []
    ];
    
    // Obtener datos de expensas por mes
    for ($mes = 1; $mes <= 12; $mes++) {
        $datos['meses'][] = nombreMes($mes);
        
        $sql = "SELECT COALESCE(SUM(e.monto_total), 0) as total
                FROM expensas e";
        
        $where = " WHERE e.periodo_ano = ? AND e.periodo_mes = ?";
        $params = [$ano, $mes];
        
        if ($unidad_id) {
            $where .= " AND e.unidad_id = ?";
            $params[] = $unidad_id;
        }
        
        $sql .= $where;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        $datos['montos'][] = (float)$result['total'];
    }
    
    echo json_encode($datos);
    
} catch (PDOException $e) {
    error_log("Error en API tendencia expensas: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener datos',
        'meses' => [],
        'montos' => []
    ]);
}
?>
[file content end]