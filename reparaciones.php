<?php
session_start();
require_once 'inc/config.php';
require_once 'inc/conexion.php';
require_once 'inc/funciones.php';

requireAuth();

$pdo = obtenerConexion();
$titulo_pagina = 'Gestión de Reparaciones';
$icono_titulo = 'fas fa-tools';

// Configuración de paginación
$por_pagina = 10;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $por_pagina;

// Búsqueda y filtros
$busqueda = isset($_GET['busqueda']) ? sanitizar($_GET['busqueda']) : '';
$filtro_estado = isset($_GET['estado']) ? sanitizar($_GET['estado']) : '';
$filtro_fuente = isset($_GET['fuente']) ? sanitizar($_GET['fuente']) : '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    // Verificar token CSRF
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Token de seguridad inválido.';
        redirigir('reparaciones.php');
    }
    
    switch ($accion) {
        case 'crear':
            $unidad_id = !empty($_POST['unidad_id']) ? (int)$_POST['unidad_id'] : null;
            $descripcion = sanitizar($_POST['descripcion'] ?? '');
            $monto_estimado = !empty($_POST['monto_estimado']) ? (float)$_POST['monto_estimado'] : null;
            $fecha_reporte = sanitizar($_POST['fecha_reporte'] ?? '');
            $responsable = sanitizar($_POST['responsable'] ?? '');
            $fuente_financiacion = sanitizar($_POST['fuente_financiacion'] ?? 'otro');
            
            // Validaciones
            $errores = [];
            
            if (empty($descripcion)) {
                $errores[] = 'La descripción es obligatoria.';
            }
            
            if (empty($fecha_reporte)) {
                $errores[] = 'La fecha de reporte es obligatoria.';
            }
            
            if (empty($responsable)) {
                $errores[] = 'El responsable es obligatorio.';
            }
            
            if (empty($errores)) {
                try {
                    $pdo->beginTransaction();
                    
                    $sql = "INSERT INTO reparaciones (unidad_id, descripcion, monto_estimado, fecha_reporte, 
                            responsable, fuente_financiacion, estado) 
                            VALUES (?, ?, ?, ?, ?, ?, 'pendiente')";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$unidad_id, $descripcion, $monto_estimado, $fecha_reporte, 
                                    $responsable, $fuente_financiacion]);
                    
                    $reparacion_id = $pdo->lastInsertId();
                    registrarLog($pdo, "Creó reparación ID: $reparacion_id - $descripcion");
                    
                    $pdo->commit();
                    $_SESSION['success'] = 'Reparación creada exitosamente.';
                    redirigir('reparaciones.php');
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Error al crear reparación: " . $e->getMessage());
                    $_SESSION['error'] = 'Error al crear la reparación.';
                }
            } else {
                $_SESSION['error'] = implode('<br>', $errores);
            }
            break;
            
        case 'editar':
            $unidad_id = !empty($_POST['unidad_id']) ? (int)$_POST['unidad_id'] : null;
            $descripcion = sanitizar($_POST['descripcion'] ?? '');
            $monto_estimado = !empty($_POST['monto_estimado']) ? (float)$_POST['monto_estimado'] : null;
            $monto_gastado = !empty($_POST['monto_gastado']) ? (float)$_POST['monto_gastado'] : null;
            $fecha_reporte = sanitizar($_POST['fecha_reporte'] ?? '');
            $fecha_ejecucion = !empty($_POST['fecha_ejecucion']) ? sanitizar($_POST['fecha_ejecucion']) : null;
            $responsable = sanitizar($_POST['responsable'] ?? '');
            $fuente_financiacion = sanitizar($_POST['fuente_financiacion'] ?? 'otro');
            $estado = sanitizar($_POST['estado'] ?? 'pendiente');
            
            // Validaciones
            $errores = [];
            
            if (empty($descripcion)) {
                $errores[] = 'La descripción es obligatoria.';
            }
            
            if (empty($fecha_reporte)) {
                $errores[] = 'La fecha de reporte es obligatoria.';
            }
            
            if (empty($responsable)) {
                $errores[] = 'El responsable es obligatorio.';
            }
            
            if (empty($errores)) {
                try {
                    $pdo->beginTransaction();
                    
                    $sql = "UPDATE reparaciones SET unidad_id = ?, descripcion = ?, monto_estimado = ?, 
                            monto_gastado = ?, fecha_reporte = ?, fecha_ejecucion = ?, 
                            responsable = ?, fuente_financiacion = ?, estado = ? 
                            WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$unidad_id, $descripcion, $monto_estimado, $monto_gastado, 
                                    $fecha_reporte, $fecha_ejecucion, $responsable, 
                                    $fuente_financiacion, $estado, $id]);
                    
                    // Procesar archivo de comprobante si se subió
                    if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
                        $resultado = subirArchivo($_FILES['comprobante'], 'comprobantes');
                        if ($resultado['success']) {
                            $sql_update = "UPDATE reparaciones SET comprobante_path = ? WHERE id = ?";
                            $stmt_update = $pdo->prepare($sql_update);
                            $stmt_update->execute([$resultado['nombre_archivo'], $id]);
                        }
                    }
                    
                    registrarLog($pdo, "Editó reparación ID: $id - $descripcion");
                    $pdo->commit();
                    $_SESSION['success'] = 'Reparación actualizada exitosamente.';
                    redirigir('reparaciones.php');
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Error al actualizar reparación: " . $e->getMessage());
                    $_SESSION['error'] = 'Error al actualizar la reparación.';
                }
            } else {
                $_SESSION['error'] = implode('<br>', $errores);
            }
            break;
            
        case 'eliminar':
            try {
                $pdo->beginTransaction();
                
                // Obtener datos para el log
                $sql = "SELECT descripcion FROM reparaciones WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                $reparacion = $stmt->fetch();
                
                $sql = "DELETE FROM reparaciones WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                
                registrarLog($pdo, "Eliminó reparación ID: $id - {$reparacion['descripcion']}");
                $pdo->commit();
                $_SESSION['success'] = 'Reparación eliminada exitosamente.';
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Error al eliminar reparación: " . $e->getMessage());
                $_SESSION['error'] = 'Error al eliminar la reparación.';
            }
            break;
    }
    
    redirigir('reparaciones.php');
}

// Obtener reparaciones con filtros
$where_conditions = [];
$params = [];

if (!empty($busqueda)) {
    $where_conditions[] = "(r.descripcion LIKE ? OR r.responsable LIKE ?)";
    $like_param = "%$busqueda%";
    $params[] = $like_param;
    $params[] = $like_param;
}

if (!empty($filtro_estado)) {
    $where_conditions[] = "r.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_fuente)) {
    $where_conditions[] = "r.fuente_financiacion = ?";
    $params[] = $filtro_fuente;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Consulta para contar total
$sql_count = "SELECT COUNT(*) as total 
              FROM reparaciones r
              $where_sql";
$stmt = $pdo->prepare($sql_count);
$stmt->execute($params);
$total_reparaciones = $stmt->fetch()['total'];
$total_paginas = ceil($total_reparaciones / $por_pagina);

// Consulta para obtener reparaciones
$sql = "SELECT r.*, u.numero as unidad_numero, u.tipo as unidad_tipo
        FROM reparaciones r
        LEFT JOIN unidades u ON r.unidad_id = u.id
        $where_sql 
        ORDER BY r.fecha_reporte DESC, r.creado_en DESC
        LIMIT $offset, $por_pagina";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reparaciones = $stmt->fetchAll();

// Obtener listas para selects
$unidades = $pdo->query("SELECT id, numero, tipo FROM unidades WHERE activo = 1 ORDER BY numero")->fetchAll();

// Breadcrumb
$breadcrumb = generarBreadcrumb([
    ['text' => 'Reparaciones', 'url' => 'reparaciones.php']
]);

// Acciones del título
$acciones_titulo = '
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalReparacion">
        <i class="fas fa-plus me-1"></i>Nueva Reparación
    </button>
';

require_once 'inc/header.php';
?>

<!-- El resto del código de reparaciones.php se mantiene similar al anterior, pero ahora incluye -->
<!-- la opción de seleccionar "reserva" como fuente de financiación -->

<?php
// Al final del archivo, agregar el script para manejar la fuente de financiación
$scripts_extra = '
<script>
// Cuando se selecciona "reserva" como fuente de financiación, mostrar información adicional
document.getElementById("fuente_financiacion").addEventListener("change", function() {
    const fuente = this.value;
    const infoReserva = document.getElementById("info-reserva");
    
    if (fuente === "reserva" && infoReserva) {
        infoReserva.style.display = "block";
    } else if (infoReserva) {
        infoReserva.style.display = "none";
    }
});
</script>
';

require_once 'inc/footer.php';
?>