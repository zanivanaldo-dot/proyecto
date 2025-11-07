<?php
// =============================================
// FUNCIONES REUTILIZABLES DEL SISTEMA
// =============================================

// Incluir dependencias
require_once __DIR__ . '/conexion.php';

/**
 * Verifica si el usuario actual es administrador
 * @return bool True si es admin, false en caso contrario
 */
function esAdmin() {
    if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_rol'])) {
        return false;
    }
    return $_SESSION['usuario_rol'] === 'admin';
}

/**
 * Requiere que el usuario sea administrador
 * Redirige al dashboard si no tiene permisos
 */
function requireAdmin() {
    if (!esAdmin()) {
        $_SESSION['error'] = 'No tiene permisos para acceder a esta sección.';
        header('Location: index.php');
        exit;
    }
}

/**
 * Requiere autenticación
 * Redirige al login si no está autenticado
 */
function requireAuth() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Obtiene contratos que vencen en los próximos días
 * @param PDO $pdo Conexión a la base de datos
 * @param int $dias Número de días para alerta (por defecto 30)
 * @return array Contratos próximos a vencer
 */
function alertasContratosVencidos($pdo, $dias = 30) {
    $sql = "SELECT c.id, c.fecha_vencimiento, 
                   CONCAT(i.nombre, ' ', i.apellido) as inquilino,
                   u.numero as unidad, u.tipo,
                   c.monto_alquiler, c.moneda,
                   DATEDIFF(c.fecha_vencimiento, CURDATE()) as dias_restantes
            FROM contratos c
            INNER JOIN inquilinos i ON c.inquilino_id = i.id
            INNER JOIN unidades u ON c.unidad_id = u.id
            WHERE c.estado = 'activo'
            AND c.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY c.fecha_vencimiento ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dias]);
    return $stmt->fetchAll();
}

/**
 * Calcula el saldo total de reservas por moneda
 * @param PDO $pdo Conexión a la base de datos
 * @param string $moneda Moneda a consultar (ARS/USD)
 * @return float Saldo total disponible
 */
function saldoReservas($pdo, $moneda = 'ARS') {
    $sql = "SELECT COALESCE(SUM(monto), 0) as total
            FROM reservas 
            WHERE moneda = ? AND estado = 'disponible'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$moneda]);
    $result = $stmt->fetch();
    return (float)$result['total'];
}

/**
 * Calcula ingresos y gastos netos para un período
 * @param PDO $pdo Conexión a la base de datos
 * @param int $ano Año del período
 * @param int $mes Mes del período
 * @param string $moneda Moneda a consultar
 * @return array Array con ingresos, gastos y neto
 */
function totalIngresosGastosPeriodo($pdo, $ano, $mes, $moneda = 'ARS') {
    // Ingresos por alquileres y expensas
    $sql_ingresos = "SELECT COALESCE(SUM(monto), 0) as total
                     FROM pagos 
                     WHERE moneda = ? 
                     AND YEAR(fecha_pago) = ? 
                     AND MONTH(fecha_pago) = ?
                     AND tipo_pago IN ('alquiler', 'expensa')";
    
    $stmt = $pdo->prepare($sql_ingresos);
    $stmt->execute([$moneda, $ano, $mes]);
    $ingresos = (float)$stmt->fetch()['total'];
    
    // Gastos por reparaciones (solo las finalizadas)
    $sql_gastos = "SELECT COALESCE(SUM(monto_gastado), 0) as total
                   FROM reparaciones 
                   WHERE fuente_financiacion != 'reserva'
                   AND estado = 'finalizada'
                   AND YEAR(fecha_ejecucion) = ? 
                   AND MONTH(fecha_ejecucion) = ?";
    
    $stmt = $pdo->prepare($sql_gastos);
    $stmt->execute([$ano, $mes]);
    $gastos = (float)$stmt->fetch()['total'];
    
    return [
        'ingresos' => $ingresos,
        'gastos' => $gastos,
        'neto' => $ingresos - $gastos
    ];
}

/**
 * Formatea un valor monetario según la moneda
 * @param float $valor Valor a formatear
 * @param string $moneda Tipo de moneda (ARS/USD)
 * @return string Valor formateado
 */
function formato_moneda($valor, $moneda = 'ARS') {
    $formato = number_format($valor, 2, ',', '.');
    
    switch ($moneda) {
        case 'USD':
            return 'US$ ' . $formato;
        case 'ARS':
        default:
            return '$ ' . $formato;
    }
}

/**
 * Sanitiza datos de entrada para prevenir XSS - MEJORADO
 * @param mixed $data Dato a sanitizar
 * @return mixed Dato sanitizado
 */
function sanitizar($data) {
    if (is_array($data)) {
        return array_map('sanitizar', $data);
    }
    
    if ($data === null) {
        return null;
    }
    
    // Remover caracteres de control excepto tab, newline, carriage return
    $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
    
    // Convertir caracteres especiales a entidades HTML
    $data = htmlspecialchars(trim($data), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    return $data;
}

/**
 * Valida y sube un archivo al servidor CON MEJORAS DE SEGURIDAD
 * @param array $archivo Array $_FILES del archivo
 * @param string $destino Carpeta de destino
 * @return array Resultado de la operación
 */
function subirArchivo($archivo, $destino = 'comprobantes') {
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Error en la subida del archivo: Código ' . $archivo['error']];
    }
    
    // Verificar tamaño - SEGURIDAD
    if ($archivo['size'] > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'error' => 'El archivo excede el tamaño máximo permitido de ' . (UPLOAD_MAX_SIZE / 1024 / 1024) . 'MB'];
    }
    
    // Verificar tipo por extensión - SEGURIDAD
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_FILE_TYPES)) {
        return ['success' => false, 'error' => 'Tipo de archivo no permitido. Formatos aceptados: ' . implode(', ', ALLOWED_FILE_TYPES)];
    }
    
    // Verificar tipo MIME real - SEGURIDAD MEJORADA
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_real = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);
    
    if (!isset(ALLOWED_MIME_TYPES[$extension]) || ALLOWED_MIME_TYPES[$extension] !== $mime_real) {
        return ['success' => false, 'error' => 'El tipo MIME del archivo no coincide con su extensión.'];
    }
    
    // Verificación adicional para imágenes
    if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
        $imagen_info = getimagesize($archivo['tmp_name']);
        if (!$imagen_info) {
            return ['success' => false, 'error' => 'El archivo no es una imagen válida.'];
        }
        
        // Prevenir ataques de polyglot
        $mime_imagen = $imagen_info['mime'];
        if ($mime_imagen !== ALLOWED_MIME_TYPES[$extension]) {
            return ['success' => false, 'error' => 'La imagen no es del tipo esperado.'];
        }
    }
    
    // Generar nombre único seguro
    $nombre_archivo = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $ruta_completa = constant(strtoupper($destino) . '_PATH') . $nombre_archivo;
    
    // Validar que la ruta de destino esté dentro del directorio permitido
    $ruta_destino_real = realpath(dirname($ruta_completa));
    $ruta_base_real = realpath(constant(strtoupper($destino) . '_PATH'));
    
    if ($ruta_destino_real !== $ruta_base_real) {
        return ['success' => false, 'error' => 'Ruta de destino inválida.'];
    }
    
    // Mover archivo con verificación
    if (move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
        // Aplicar permisos seguros
        chmod($ruta_completa, 0640);
        
        return [
            'success' => true, 
            'nombre_archivo' => $nombre_archivo,
            'ruta_completa' => $ruta_completa,
            'mime_type' => $mime_real
        ];
    } else {
        return ['success' => false, 'error' => 'No se pudo guardar el archivo en el servidor.'];
    }
}

/**
 * Genera un token CSRF
 * @return string Token generado
 */
function generarTokenCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica un token CSRF
 * @param string $token Token a verificar
 * @return bool True si es válido
 */
function verificarTokenCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Redirige a una URL
 * @param string $url URL de destino
 */
function redirigir($url) {
    header("Location: $url");
    exit;
}

/**
 * Obtiene el nombre del mes en español
 * @param int $numero_mes Número del mes (1-12)
 * @return string Nombre del mes
 */
function nombreMes($numero_mes) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $meses[$numero_mes] ?? 'Mes inválido';
}

/**
 * Calcula la edad en años a partir de una fecha
 * @param string $fecha_nacimiento Fecha en formato YYYY-MM-DD
 * @return int Edad en años
 */
function calcularEdad($fecha_nacimiento) {
    $nacimiento = new DateTime($fecha_nacimiento);
    $hoy = new DateTime();
    $edad = $hoy->diff($nacimiento);
    return $edad->y;
}

/**
 * Genera un string aleatorio para contraseñas temporales
 * @param int $longitud Longitud del string (por defecto 8)
 * @return string String aleatorio
 */
function generarPasswordTemporal($longitud = 8) {
    $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $password = '';
    for ($i = 0; $i < $longitud; $i++) {
        $password .= $caracteres[rand(0, strlen($caracteres) - 1)];
    }
    return $password;
}

/**
 * Genera breadcrumb dinámico
 * @param array $items Array de items [['text' => '', 'url' => '']]
 * @return string HTML del breadcrumb
 */
function generarBreadcrumb($items) {
    $breadcrumb = [];
    
    // Item de inicio siempre presente
    $breadcrumb[] = ['text' => 'Inicio', 'url' => 'index.php'];
    
    // Agregar items proporcionados
    foreach ($items as $item) {
        $breadcrumb[] = $item;
    }
    
    // Marcar último item como activo
    if (!empty($breadcrumb)) {
        $breadcrumb[count($breadcrumb) - 1]['active'] = true;
        unset($breadcrumb[count($breadcrumb) - 1]['url']); // Remover URL del último item
    }
    
    return $breadcrumb;
}

/**
 * Obtiene estadísticas rápidas para el dashboard
 * @param PDO $pdo Conexión a la base de datos
 * @return array Estadísticas
 */
function obtenerEstadisticasDashboard($pdo) {
    $stats = [];
    
    // Total de unidades
    $sql = "SELECT COUNT(*) as total FROM unidades WHERE activo = 1";
    $stats['total_unidades'] = $pdo->query($sql)->fetch()['total'];
    
    // Unidades ocupadas
    $sql = "SELECT COUNT(DISTINCT unidad_id) as total 
            FROM contratos 
            WHERE estado = 'activo' AND fecha_vencimiento >= CURDATE()";
    $stats['unidades_ocupadas'] = $pdo->query($sql)->fetch()['total'];
    
    // Contratos por vencer (30 días)
    $sql = "SELECT COUNT(*) as total 
            FROM contratos 
            WHERE estado = 'activo' 
            AND fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    $stats['contratos_por_vencer'] = $pdo->query($sql)->fetch()['total'];
    
    // Expensas pendientes
    $sql = "SELECT COUNT(*) as total 
            FROM expensas 
            WHERE estado = 'pendiente' AND fecha_vencimiento < CURDATE()";
    $stats['expensas_vencidas'] = $pdo->query($sql)->fetch()['total'];
    
    // Reparaciones pendientes
    $sql = "SELECT COUNT(*) as total 
            FROM reparaciones 
            WHERE estado = 'pendiente'";
    $stats['reparaciones_pendientes'] = $pdo->query($sql)->fetch()['total'];
    
    // Ingresos del mes actual
    $sql = "SELECT COALESCE(SUM(monto), 0) as total 
            FROM pagos 
            WHERE tipo_pago IN ('alquiler', 'expensa')
            AND YEAR(fecha_pago) = YEAR(CURDATE()) 
            AND MONTH(fecha_pago) = MONTH(CURDATE())";
    $stats['ingresos_mes_actual'] = $pdo->query($sql)->fetch()['total'];
    
    return $stats;
}

/**
 * Registra una acción en el log del sistema
 * @param PDO $pdo Conexión a la base de datos
 * @param string $accion Descripción de la acción
 * @param int $usuario_id ID del usuario (opcional)
 * @param string $tabla Tabla afectada (opcional)
 * @param int $registro_id ID del registro afectado (opcional)
 */
function registrarLog($pdo, $accion, $usuario_id = null, $tabla = null, $registro_id = null) {
    if ($usuario_id === null) {
        $usuario_id = $_SESSION['usuario_id'] ?? null;
    }
    
    try {
        $sql = "INSERT INTO logs_sistema (usuario_id, accion, tabla, registro_id, ip, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $usuario_id,
            $accion,
            $tabla,
            $registro_id,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido'
        ]);
    } catch (PDOException $e) {
        error_log("Error al registrar log: " . $e->getMessage());
    }
}

/**
 * Genera HTML para paginación
 * @param int $pagina_actual Página actual
 * @param int $total_paginas Total de páginas
 * @param array $params Parámetros adicionales para la URL
 * @return string HTML de la paginación
 */
function generarPaginacion($pagina_actual, $total_paginas, $params = []) {
    if ($total_paginas <= 1) return '';
    
    $html = '<nav aria-label="Paginación"><ul class="pagination justify-content-center">';
    
    // Botón anterior
    $disabled_prev = $pagina_actual <= 1 ? 'disabled' : '';
    $prev_url = $pagina_actual > 1 ? '?' . http_build_query(array_merge($params, ['pagina' => $pagina_actual - 1])) : '#';
    $html .= '<li class="page-item ' . $disabled_prev . '">';
    $html .= '<a class="page-link" href="' . $prev_url . '">Anterior</a></li>';
    
    // Páginas
    $inicio = max(1, $pagina_actual - 2);
    $fin = min($total_paginas, $pagina_actual + 2);
    
    for ($i = $inicio; $i <= $fin; $i++) {
        $active = $i == $pagina_actual ? 'active' : '';
        $url = '?' . http_build_query(array_merge($params, ['pagina' => $i]));
        $html .= '<li class="page-item ' . $active . '">';
        $html .= '<a class="page-link" href="' . $url . '">' . $i . '</a></li>';
    }
    
    // Botón siguiente
    $disabled_next = $pagina_actual >= $total_paginas ? 'disabled' : '';
    $next_url = $pagina_actual < $total_paginas ? '?' . http_build_query(array_merge($params, ['pagina' => $pagina_actual + 1])) : '#';
    $html .= '<li class="page-item ' . $disabled_next . '">';
    $html .= '<a class="page-link" href="' . $next_url . '">Siguiente</a></li>';
    
    $html .= '</ul></nav>';
    return $html;
}

/**
 * Obtiene roles de usuario para selects
 * @return array Array de roles
 */
function obtenerRolesUsuario() {
    return [
        'usuario' => 'Usuario',
        'admin' => 'Administrador'
    ];
}

/**
 * Obtiene estados de contrato para selects
 * @return array Array de estados
 */
function obtenerEstadosContrato() {
    return [
        'activo' => 'Activo',
        'finalizado' => 'Finalizado', 
        'renovado' => 'Renovado'
    ];
}

/**
 * Verifica si una unidad tiene contrato activo
 * @param PDO $pdo Conexión a la base de datos
 * @param int $unidad_id ID de la unidad
 * @return bool True si tiene contrato activo
 */
function unidadTieneContratoActivo($pdo, $unidad_id) {
    $sql = "SELECT COUNT(*) as total 
            FROM contratos 
            WHERE unidad_id = ? AND estado = 'activo' AND fecha_vencimiento >= CURDATE()";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$unidad_id]);
    $result = $stmt->fetch();
    return $result['total'] > 0;
}

/**
 * Obtiene el contrato activo de una unidad
 * @param PDO $pdo Conexión a la base de datos
 * @param int $unidad_id ID de la unidad
 * @return array|false Datos del contrato o false si no existe
 */
function obtenerContratoActivoUnidad($pdo, $unidad_id) {
    $sql = "SELECT c.*, i.nombre, i.apellido, i.dni, i.email, i.telefono
            FROM contratos c
            INNER JOIN inquilinos i ON c.inquilino_id = i.id
            WHERE c.unidad_id = ? AND c.estado = 'activo' AND c.fecha_vencimiento >= CURDATE()
            ORDER BY c.fecha_vencimiento DESC 
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$unidad_id]);
    return $stmt->fetch();
}

/**
 * Calcula días restantes para el vencimiento de un contrato
 * @param string $fecha_vencimiento Fecha de vencimiento en formato YYYY-MM-DD
 * @return int Días restantes (negativo si ya venció)
 */
function diasRestantesContrato($fecha_vencimiento) {
    $hoy = new DateTime();
    $vencimiento = new DateTime($fecha_vencimiento);
    $diferencia = $hoy->diff($vencimiento);
    return $diferencia->invert ? -$diferencia->days : $diferencia->days;
}

/**
 * Obtiene tipos de unidades para selects
 * @return array Array de tipos de unidades
 */
function obtenerTiposUnidad() {
    return [
        'departamento' => 'Departamento',
        'oficina' => 'Oficina',
        'local' => 'Local Comercial'
    ];
}

/**
 * Obtiene estados de expensas para selects
 * @return array Array de estados
 */
function obtenerEstadosExpensa() {
    return [
        'pendiente' => 'Pendiente',
        'pagada' => 'Pagada',
        'vencida' => 'Vencida'
    ];
}

/**
 * Obtiene tipos de pago para selects
 * @return array Array de tipos de pago
 */
function obtenerTiposPago() {
    return [
        'alquiler' => 'Alquiler',
        'expensa' => 'Expensa',
        'reserva' => 'Reserva',
        'reparacion' => 'Reparación'
    ];
}

/**
 * Obtiene fuentes de financiación para selects
 * @return array Array de fuentes
 */
function obtenerFuentesFinanciacion() {
    return [
        'reserva' => 'Fondo de Reserva',
        'fondo_edificio' => 'Fondo del Edificio',
        'otro' => 'Otro'
    ];
}

/**
 * Obtiene estados de reparación para selects
 * @return array Array de estados
 */
function obtenerEstadosReparacion() {
    return [
        'pendiente' => 'Pendiente',
        'en_proceso' => 'En Proceso',
        'finalizada' => 'Finalizada'
    ];
}

/**
 * Obtiene orígenes de reserva para selects
 * @return array Array de orígenes
 */
function obtenerOrigenesReserva() {
    return [
        'excedente_expensa' => 'Excedente de Expensas',
        'aporte_extra' => 'Aporte Extraordinario'
    ];
}

/**
 * Valida un DNI argentino
 * @param string $dni DNI a validar
 * @return bool True si es válido
 */
function validarDNI($dni) {
    return preg_match('/^\d{7,8}$/', $dni);
}

/**
 * Validar email con verificación de dominio
 * @param string $email Email a validar
 * @return bool True si es válido
 */
function validarEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Verificar formato estricto
    if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        return false;
    }
    
    return true;
}

/**
 * Validar y limpiar número telefónico
 * @param string $telefono Teléfono a validar
 * @return string|false Teléfono limpio o false si es inválido
 */
function validarYLimpiarTelefono($telefono) {
    // Remover todo excepto números y +
    $telefono_limpio = preg_replace('/[^\d+]/', '', $telefono);
    
    // Validar longitud mínima
    if (strlen($telefono_limpio) < 8) {
        return false;
    }
    
    // Validar formato internacional o local
    if (strpos($telefono_limpio, '+') === 0) {
        // Número internacional
        if (strlen($telefono_limpio) < 10) {
            return false;
        }
    } else {
        // Número local
        if (strlen($telefono_limpio) < 8 || strlen($telefono_limpio) > 12) {
            return false;
        }
    }
    
    return $telefono_limpio;
}

/**
 * Formatea una fecha para mostrar
 * @param string $fecha Fecha en formato YYYY-MM-DD
 * @param bool $incluir_hora Si incluye la hora
 * @return string Fecha formateada
 */
function formato_fecha($fecha, $incluir_hora = false) {
    if (empty($fecha)) return '';
    
    $timestamp = strtotime($fecha);
    if ($incluir_hora) {
        return date('d/m/Y H:i', $timestamp);
    } else {
        return date('d/m/Y', $timestamp);
    }
}

/**
 * Convierte una fecha del formato español al formato de base de datos
 * @param string $fecha_espanol Fecha en formato DD/MM/YYYY
 * @return string Fecha en formato YYYY-MM-DD
 */
function fecha_a_mysql($fecha_espanol) {
    if (empty($fecha_espanol)) return '';
    
    $partes = explode('/', $fecha_espanol);
    if (count($partes) === 3) {
        return $partes[2] . '-' . $partes[1] . '-' . $partes[0];
    }
    return $fecha_espanol;
}

/**
 * Convierte una fecha del formato de base de datos al formato español
 * @param string $fecha_mysql Fecha en formato YYYY-MM-DD
 * @return string Fecha en formato DD/MM/YYYY
 */
function fecha_a_espanol($fecha_mysql) {
    if (empty($fecha_mysql)) return '';
    
    $partes = explode('-', $fecha_mysql);
    if (count($partes) === 3) {
        return $partes[2] . '/' . $partes[1] . '/' . $partes[0];
    }
    return $fecha_mysql;
}

/**
 * Calcula el próximo período (mes y año)
 * @param int $mes Mes actual
 * @param int $ano Año actual
 * @return array Array con próximo mes y año
 */
function proximoPeriodo($mes, $ano) {
    if ($mes == 12) {
        return ['mes' => 1, 'ano' => $ano + 1];
    } else {
        return ['mes' => $mes + 1, 'ano' => $ano];
    }
}

/**
 * Obtiene los últimos N meses para selects
 * @param int $cantidad Cantidad de meses a obtener
 * @return array Array de meses [YYYY-MM => Nombre]
 */
function obtenerUltimosMeses($cantidad = 12) {
    $meses = [];
    $fecha = new DateTime();
    
    for ($i = 0; $i < $cantidad; $i++) {
        $mes = $fecha->format('n');
        $ano = $fecha->format('Y');
        $clave = $ano . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT);
        $meses[$clave] = nombreMes($mes) . ' ' . $ano;
        $fecha->modify('-1 month');
    }
    
    return $meses;
}

/**
 * Obtiene el saldo pendiente de expensas de una unidad
 * @param PDO $pdo Conexión a la base de datos
 * @param int $unidad_id ID de la unidad
 * @return float Saldo pendiente
 */
function saldoPendienteExpensas($pdo, $unidad_id) {
    $sql = "SELECT COALESCE(SUM(monto_total), 0) as total
            FROM expensas 
            WHERE unidad_id = ? AND estado IN ('pendiente', 'vencida')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$unidad_id]);
    $result = $stmt->fetch();
    return (float)$result['total'];
}

/**
 * Obtiene el saldo pendiente de alquiler de un contrato
 * @param PDO $pdo Conexión a la base de datos
 * @param int $contrato_id ID del contrato
 * @param int $mes Mes a consultar
 * @param int $ano Año a consultar
 * @return float Saldo pendiente
 */
function saldoPendienteAlquiler($pdo, $contrato_id, $mes, $ano) {
    $sql = "SELECT COALESCE(SUM(monto), 0) as pagado
            FROM pagos 
            WHERE contrato_id = ? 
            AND tipo_pago = 'alquiler'
            AND YEAR(fecha_pago) = ? 
            AND MONTH(fecha_pago) = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$contrato_id, $ano, $mes]);
    $pagado = (float)$stmt->fetch()['pagado'];
    
    // Obtener monto del alquiler
    $sql_contrato = "SELECT monto_alquiler FROM contratos WHERE id = ?";
    $stmt = $pdo->prepare($sql_contrato);
    $stmt->execute([$contrato_id]);
    $contrato = $stmt->fetch();
    
    $monto_alquiler = (float)($contrato['monto_alquiler'] ?? 0);
    
    return max(0, $monto_alquiler - $pagado);
}

/**
 * Envía un email (función básica)
 * @param string $destinatario Email del destinatario
 * @param string $asunto Asunto del email
 * @param string $mensaje Cuerpo del mensaje
 * @return bool True si se envió correctamente
 */
function enviarEmail($destinatario, $asunto, $mensaje) {
    // Esta es una implementación básica. En producción, usar una librería como PHPMailer
    $headers = "From: " . APP_NAME . " <no-reply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($destinatario, $asunto, $mensaje, $headers);
}

/**
 * Genera un comprobante de pago en texto
 * @param array $pago Datos del pago
 * @param array $contrato Datos del contrato (opcional)
 * @return string Texto del comprobante
 */
function generarComprobantePago($pago, $contrato = null) {
    $comprobante = "COMPROBANTE DE PAGO\n";
    $comprobante .= "===================\n\n";
    $comprobante .= "Fecha: " . formato_fecha($pago['fecha_pago'] ?? '') . "\n";
    $comprobante .= "Monto: " . formato_moneda($pago['monto'] ?? 0, $pago['moneda'] ?? 'ARS') . "\n";
    $comprobante .= "Tipo: " . ucfirst($pago['tipo_pago'] ?? '') . "\n";
    $comprobante .= "Método: " . ($pago['metodo_pago'] ?? '') . "\n\n";
    
    if ($contrato) {
        $comprobante .= "Contrato: " . ($contrato['id'] ?? '') . "\n";
        $comprobante .= "Inquilino: " . ($contrato['inquilino_nombre'] ?? '') . " " . ($contrato['inquilino_apellido'] ?? '') . "\n";
        $comprobante .= "Unidad: " . ($contrato['unidad_numero'] ?? '') . "\n\n";
    }
    
    $comprobante .= "Sistema de Gestión de Alquileres\n";
    $comprobante .= APP_NAME . "\n";
    $comprobante .= "Fecha de emisión: " . date('d/m/Y H:i:s');
    
    return $comprobante;
}

/**
 * Obtiene el año actual
 * @return int Año actual
 */
function anoActual() {
    return (int)date('Y');
}

/**
 * Obtiene el mes actual
 * @return int Mes actual
 */
function mesActual() {
    return (int)date('n');
}

/**
 * Verifica si una fecha es válida
 * @param string $fecha Fecha a validar
 * @param string $formato Formato esperado (por defecto YYYY-MM-DD)
 * @return bool True si es válida
 */
function fechaValida($fecha, $formato = 'Y-m-d') {
    $d = DateTime::createFromFormat($formato, $fecha);
    return $d && $d->format($formato) === $fecha;
}

/**
 * Obtiene la diferencia en meses entre dos fechas
 * @param string $fecha_inicio Fecha de inicio
 * @param string $fecha_fin Fecha de fin
 * @return int Diferencia en meses
 */
function diferenciaMeses($fecha_inicio, $fecha_fin) {
    $inicio = new DateTime($fecha_inicio);
    $fin = new DateTime($fecha_fin);
    $diferencia = $inicio->diff($fin);
    return ($diferencia->y * 12) + $diferencia->m;
}

/**
 * Limpia y formatea un número de teléfono
 * @param string $telefono Número de teléfono
 * @return string Teléfono formateado
 */
function formatearTelefono($telefono) {
    // Remover todo excepto números
    $telefono = preg_replace('/[^\d]/', '', $telefono);
    
    // Formatear según la longitud
    if (strlen($telefono) === 10) {
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $telefono);
    } elseif (strlen($telefono) === 8) {
        return preg_replace('/(\d{4})(\d{4})/', '$1-$2', $telefono);
    }
    
    return $telefono;
}

/**
 * Obtiene el historial de uso de reservas para una reparación
 * @param PDO $pdo Conexión a la base de datos
 * @param int $reparacion_id ID de la reparación
 * @return array Historial de uso de reservas
 */
function obtenerHistorialUsoReservas($pdo, $reparacion_id) {
    $sql = "SELECT r.descripcion, r.monto, r.moneda, r.fecha_creacion, r.origen
            FROM reservas r
            INNER JOIN pagos p ON p.descripcion LIKE CONCAT('%reserva #', r.id, '%')
            WHERE p.tipo_pago = 'reserva' 
            AND p.descripcion LIKE CONCAT('%reparación: ', 
                (SELECT descripcion FROM reparaciones WHERE id = ?), '%')
            ORDER BY r.fecha_creacion DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$reparacion_id]);
    return $stmt->fetchAll();
}

/**
 * Calcula el total de reservas utilizadas en un período
 * @param PDO $pdo Conexión a la base de datos
 * @param int $ano Año del período
 * @param int $mes Mes del período
 * @param string $moneda Moneda a consultar
 * @return float Total de reservas utilizadas
 */
function totalReservasUtilizadasPeriodo($pdo, $ano, $mes, $moneda = 'ARS') {
    $sql = "SELECT COALESCE(SUM(p.monto), 0) as total
            FROM pagos p
            WHERE p.tipo_pago = 'reserva'
            AND p.moneda = ?
            AND YEAR(p.fecha_pago) = ? 
            AND MONTH(p.fecha_pago) = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$moneda, $ano, $mes]);
    $result = $stmt->fetch();
    return (float)$result['total'];
}

/**
 * Obtiene el resumen de reservas por origen
 * @param PDO $pdo Conexión a la base de datos
 * @return array Resumen por origen
 */
function resumenReservasPorOrigen($pdo) {
    $sql = "SELECT origen, 
                   COUNT(*) as cantidad,
                   SUM(CASE WHEN estado = 'disponible' THEN monto ELSE 0 END) as saldo_disponible,
                   SUM(CASE WHEN estado = 'usado' THEN monto ELSE 0 END) as total_usado,
                   SUM(monto) as total_general
            FROM reservas 
            GROUP BY origen";
    
    return $pdo->query($sql)->fetchAll();
}

/**
 * Verifica si una reparación puede ser financiada con reservas
 * @param PDO $pdo Conexión a la base de datos
 * @param int $reparacion_id ID de la reparación
 * @return bool True si puede ser financiada
 */
function reparacionPuedeFinanciarseConReserva($pdo, $reparacion_id) {
    $sql = "SELECT estado, fuente_financiacion 
            FROM reparaciones 
            WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$reparacion_id]);
    $reparacion = $stmt->fetch();
    
    return $reparacion && $reparacion['estado'] === 'pendiente' && $reparacion['fuente_financiacion'] === 'otro';
}

/**
 * Obtiene las reservas disponibles para una reparación
 * @param PDO $pdo Conexión a la base de datos
 * @param float $monto_requerido Monto requerido para la reparación
 * @param string $moneda Moneda requerida
 * @return array Reservas disponibles
 */
function obtenerReservasDisponibles($pdo, $monto_requerido = null, $moneda = 'ARS') {
    $sql = "SELECT id, descripcion, monto, moneda, fecha_creacion
            FROM reservas 
            WHERE estado = 'disponible' 
            AND moneda = ?";
    
    $params = [$moneda];
    
    if ($monto_requerido !== null) {
        $sql .= " AND monto >= ?";
        $params[] = $monto_requerido;
    }
    
    $sql .= " ORDER BY fecha_creacion DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Generar hash seguro para contraseñas
 * @param string $password Contraseña en texto plano
 * @return string Hash seguro
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
}

/**
 * Verificar contraseña contra hash
 * @param string $password Contraseña en texto plano
 * @param string $hash Hash almacenado
 * @return bool True si coinciden
 */
function verificarPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Validar fuerza de contraseña
 * @param string $password Contraseña a validar
 * @return array Resultado de validación
 */
function validarFuerzaPassword($password) {
    $errores = [];
    
    if (strlen($password) < 8) {
        $errores[] = 'La contraseña debe tener al menos 8 caracteres';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errores[] = 'La contraseña debe contener al menos una letra mayúscula';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errores[] = 'La contraseña debe contener al menos una letra minúscula';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errores[] = 'La contraseña debe contener al menos un número';
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errores[] = 'La contraseña debe contener al menos un carácter especial';
    }
    
    return [
        'valida' => empty($errores),
        'errores' => $errores
    ];
}

/**
 * Registrar evento de seguridad
 * @param PDO $pdo Conexión a la base de datos
 * @param string $evento Descripción del evento
 * @param string $tipo Tipo de evento (login_fallido, acceso_denegado, etc.)
 * @param string $ip Dirección IP (opcional)
 */
function registrarEventoSeguridad($pdo, $evento, $tipo = 'general', $ip = null) {
    if ($ip === null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    try {
        $sql = "INSERT INTO logs_seguridad (usuario_id, evento, tipo, ip, user_agent) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_SESSION['usuario_id'] ?? null,
            $evento,
            $tipo,
            $ip,
            $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido'
        ]);
    } catch (PDOException $e) {
        error_log("Error al registrar evento de seguridad: " . $e->getMessage());
    }
}

/**
 * Limitar intentos de login
 * @param PDO $pdo Conexión a la base de datos
 * @param string $email Email del usuario
 * @return bool True si puede intentar login
 */
function puedeIntentarLogin($pdo, $email) {
    $limite_intentos = 5;
    $tiempo_bloqueo = 15 * 60; // 15 minutos
    
    $sql = "SELECT COUNT(*) as intentos 
            FROM logs_seguridad 
            WHERE evento LIKE 'Login fallido%' 
            AND ip = ? 
            AND creado_en > DATE_SUB(NOW(), INTERVAL ? SECOND)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SERVER['REMOTE_ADDR'], $tiempo_bloqueo]);
    $resultado = $stmt->fetch();
    
    return $resultado['intentos'] < $limite_intentos;
}

/**
 * Validar y limpiar datos de formulario
 * @param array $datos Datos del formulario
 * @param array $reglas Reglas de validación
 * @return array [datos_limpios, errores]
 */
function validarFormulario($datos, $reglas) {
    $limpios = [];
    $errores = [];
    
    foreach ($reglas as $campo => $regla) {
        $valor = $datos[$campo] ?? null;
        $reglas_campo = explode('|', $regla);
        
        foreach ($reglas_campo as $regla_individual) {
            $partes = explode(':', $regla_individual);
            $tipo_regla = $partes[0];
            $parametro = $partes[1] ?? null;
            
            switch ($tipo_regla) {
                case 'requerido':
                    if (empty($valor)) {
                        $errores[$campo] = "El campo $campo es requerido";
                    }
                    break;
                    
                case 'email':
                    if (!empty($valor) && !validarEmail($valor)) {
                        $errores[$campo] = "El campo $campo debe ser un email válido";
                    }
                    break;
                    
                case 'min':
                    if (!empty($valor) && strlen($valor) < $parametro) {
                        $errores[$campo] = "El campo $campo debe tener al menos $parametro caracteres";
                    }
                    break;
                    
                case 'max':
                    if (!empty($valor) && strlen($valor) > $parametro) {
                        $errores[$campo] = "El campo $campo no puede tener más de $parametro caracteres";
                    }
                    break;
                    
                case 'numero':
                    if (!empty($valor) && !is_numeric($valor)) {
                        $errores[$campo] = "El campo $campo debe ser un número";
                    }
                    break;
                    
                case 'dni':
                    if (!empty($valor) && !validarDNI($valor)) {
                        $errores[$campo] = "El campo $campo debe ser un DNI válido";
                    }
                    break;
            }
        }
        
        // Si no hay errores, limpiar el valor
        if (!isset($errores[$campo])) {
            $limpios[$campo] = sanitizar($valor);
        }
    }
    
    return [$limpios, $errores];
}

/**
 * Formatear tamaño de archivo para mostrar
 * @param int $bytes Tamaño en bytes
 * @return string Tamaño formateado
 */
function formato_tamaño_archivo($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Obtener información del sistema para logs
 * @return array Información del sistema
 */
function obtenerInfoSistema() {
    return [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido',
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'metodo' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

/**
 * Validar acceso a archivo
 * @param string $ruta_archivo Ruta del archivo
 * @param string $directorio_base Directorio base permitido
 * @return bool True si el acceso es válido
 */
function validarAccesoArchivo($ruta_archivo, $directorio_base) {
    $ruta_real = realpath($ruta_archivo);
    $base_real = realpath($directorio_base);
    
    if ($ruta_real === false || $base_real === false) {
        return false;
    }
    
    return strpos($ruta_real, $base_real) === 0;
}

/**
 * Comprimir archivo
 * @param string $origen Ruta del archivo origen
 * @param string $destino Ruta del archivo destino
 * @return bool True si se comprimió correctamente
 */
function comprimirArchivo($origen, $destino) {
    if (!function_exists('gzopen')) {
        return false;
    }
    
    $gz = gzopen($destino, 'w9');
    if (!$gz) {
        return false;
    }
    
    $archivo = fopen($origen, 'rb');
    if (!$archivo) {
        gzclose($gz);
        return false;
    }
    
    while (!feof($archivo)) {
        gzwrite($gz, fread($archivo, 1024 * 512));
    }
    
    fclose($archivo);
    gzclose($gz);
    
    return true;
}
?>