-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 06-11-2025 a las 18:23:19
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `gestion_alquileres`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias_gasto`
--

CREATE TABLE `categorias_gasto` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categorias_gasto`
--

INSERT INTO `categorias_gasto` (`id`, `nombre`, `descripcion`) VALUES
(1, 'Sueldos', 'Personal de limpieza y mantenimiento'),
(2, 'Limpieza', 'Productos y servicios de limpieza'),
(3, 'Mantenimiento', 'Reparaciones menores y mantenimiento preventivo'),
(4, 'Servicios', 'Luz, agua, gas, internet común'),
(5, 'Seguros', 'Seguro del edificio'),
(6, 'Administración', 'Honorarios administración'),
(7, 'Reserva', 'Fondo de reserva para futuras reparaciones');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contratos`
--

CREATE TABLE `contratos` (
  `id` int(11) NOT NULL,
  `inquilino_id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `monto_alquiler` decimal(12,2) NOT NULL,
  `moneda` enum('ARS','USD') NOT NULL DEFAULT 'ARS',
  `dia_vencimiento` int(11) NOT NULL CHECK (`dia_vencimiento` between 1 and 31),
  `deposito_garantia` decimal(12,2) DEFAULT 0.00,
  `estado` enum('activo','finalizado','renovado') NOT NULL DEFAULT 'activo',
  `documento_path` varchar(500) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `contratos`
--

INSERT INTO `contratos` (`id`, `inquilino_id`, `unidad_id`, `fecha_inicio`, `fecha_vencimiento`, `monto_alquiler`, `moneda`, `dia_vencimiento`, `deposito_garantia`, `estado`, `documento_path`, `creado_en`) VALUES
(1, 1, 1, '2023-01-01', '2024-12-31', 150000.00, 'ARS', 5, 150000.00, 'activo', NULL, '2025-11-06 13:55:24'),
(2, 2, 2, '2023-03-15', '2024-03-14', 120000.00, 'ARS', 10, 120000.00, 'activo', NULL, '2025-11-06 13:55:24'),
(3, 3, 4, '2023-06-01', '2024-05-31', 800.00, 'USD', 15, 800.00, 'activo', NULL, '2025-11-06 13:55:24'),
(4, 4, 6, '2022-09-01', '2023-08-31', 2000.00, 'USD', 20, 2000.00, 'finalizado', NULL, '2025-11-06 13:55:24');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `edificios`
--

CREATE TABLE `edificios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `direccion` text NOT NULL,
  `administrador_contacto` varchar(200) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `edificios`
--

INSERT INTO `edificios` (`id`, `nombre`, `direccion`, `administrador_contacto`, `creado_en`) VALUES
(1, 'Edificio Central', 'Av. Principal 1234, Ciudad Capital', 'Sr. Roberto López - Tel: 11-1234-5678', '2025-11-06 13:55:24');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `expensas`
--

CREATE TABLE `expensas` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `periodo_ano` int(11) NOT NULL CHECK (`periodo_ano` between 2020 and 2030),
  `periodo_mes` int(11) NOT NULL CHECK (`periodo_mes` between 1 and 12),
  `monto_total` decimal(12,2) NOT NULL,
  `detalle` text DEFAULT NULL,
  `fecha_emision` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `estado` enum('pendiente','pagada','vencida') NOT NULL DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `expensas`
--

INSERT INTO `expensas` (`id`, `unidad_id`, `periodo_ano`, `periodo_mes`, `monto_total`, `detalle`, `fecha_emision`, `fecha_vencimiento`, `estado`) VALUES
(1, 1, 2024, 1, 25000.00, NULL, '2024-01-01', '2024-01-10', 'pagada'),
(2, 1, 2024, 2, 25500.00, NULL, '2024-02-01', '2024-02-10', 'pagada'),
(3, 1, 2024, 3, 26000.00, NULL, '2024-03-01', '2024-03-10', 'pendiente'),
(4, 2, 2024, 1, 23000.00, NULL, '2024-01-01', '2024-01-10', 'pagada'),
(5, 2, 2024, 2, 23500.00, NULL, '2024-02-01', '2024-02-10', 'pagada'),
(6, 2, 2024, 3, 24000.00, NULL, '2024-03-01', '2024-03-10', 'vencida'),
(7, 3, 2024, 1, 24500.00, 'Expensas comunes enero', '2024-01-01', '2024-01-10', 'pagada'),
(8, 3, 2024, 2, 24800.00, 'Expensas comunes febrero', '2024-02-01', '2024-02-10', 'pagada'),
(9, 3, 2024, 3, 25200.00, 'Expensas comunes marzo', '2024-03-01', '2024-03-10', 'pendiente'),
(10, 4, 2024, 1, 18500.00, 'Expensas oficina 101', '2024-01-01', '2024-01-10', 'pagada'),
(11, 4, 2024, 2, 18800.00, 'Expensas oficina 101', '2024-02-01', '2024-02-10', 'pagada'),
(12, 4, 2024, 3, 19200.00, 'Expensas oficina 101', '2024-03-01', '2024-03-10', 'pendiente'),
(13, 5, 2024, 1, 22000.00, 'Expensas oficina 102', '2024-01-01', '2024-01-10', 'pagada'),
(14, 5, 2024, 2, 22400.00, 'Expensas oficina 102', '2024-02-01', '2024-02-10', 'pagada'),
(15, 5, 2024, 3, 22800.00, 'Expensas oficina 102', '2024-03-01', '2024-03-10', 'vencida'),
(16, 6, 2024, 1, 35000.00, 'Expensas local comercial', '2024-01-01', '2024-01-10', 'pagada'),
(17, 6, 2024, 2, 35500.00, 'Expensas local comercial', '2024-02-01', '2024-02-10', 'pagada'),
(18, 6, 2024, 3, 36000.00, 'Expensas local comercial', '2024-03-01', '2024-03-10', 'vencida');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inquilinos`
--

CREATE TABLE `inquilinos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `dni` varchar(20) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `inquilinos`
--

INSERT INTO `inquilinos` (`id`, `nombre`, `apellido`, `dni`, `email`, `telefono`, `direccion`, `creado_en`) VALUES
(1, 'Carlos', 'Rodríguez', '30123456', 'carlos.rodriguez@email.com', '11-2345-6789', 'Calle Falsa 123', '2025-11-06 13:55:24'),
(2, 'Ana', 'Martínez', '28987654', 'ana.martinez@email.com', '11-3456-7890', 'Av. Siempre Viva 456', '2025-11-06 13:55:24'),
(3, 'Luis', 'García', '33234567', 'luis.garcia@email.com', '11-4567-8901', 'Calle Real 789', '2025-11-06 13:55:24'),
(4, 'Sofía', 'López', '35456789', 'sofia.lopez@email.com', '11-5678-9012', 'Boulevard Central 321', '2025-11-06 13:55:24');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logs_sistema`
--

CREATE TABLE `logs_sistema` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` text NOT NULL,
  `tabla` varchar(100) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `ip` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `logs_sistema`
--

INSERT INTO `logs_sistema` (`id`, `usuario_id`, `accion`, `tabla`, `registro_id`, `ip`, `user_agent`, `creado_en`) VALUES
(1, 1, 'Editó contrato ID: 1 para unidad 1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-06 16:49:35'),
(2, 1, 'Eliminó usuario ID: 2 (Juan Pérez)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-06 17:09:06'),
(3, 1, 'Eliminó usuario ID: 3 (María Gómez)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-06 17:09:08'),
(4, 1, 'Editó usuario ID: 1 (Admin Sistema)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-06 17:09:26');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_expensa`
--

CREATE TABLE `movimientos_expensa` (
  `id` int(11) NOT NULL,
  `expensa_id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `descripcion` text NOT NULL,
  `monto` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `movimientos_expensa`
--

INSERT INTO `movimientos_expensa` (`id`, `expensa_id`, `categoria_id`, `descripcion`, `monto`) VALUES
(1, 1, 1, 'Sueldo encargado', 15000.00),
(2, 1, 2, 'Limpieza áreas comunes', 5000.00),
(3, 1, 4, 'Servicios comunes', 3000.00),
(4, 1, 7, 'Fondo reserva', 2000.00),
(5, 1, 1, 'Sueldo encargado', 15000.00),
(6, 1, 2, 'Limpieza áreas comunes', 5000.00),
(7, 1, 4, 'Servicios comunes', 3000.00),
(8, 1, 7, 'Fondo reserva', 1500.00),
(9, 2, 1, 'Sueldo encargado', 15200.00),
(10, 2, 2, 'Limpieza áreas comunes', 5100.00),
(11, 2, 4, 'Servicios comunes', 3200.00),
(12, 2, 7, 'Fondo reserva', 1300.00),
(13, 3, 1, 'Sueldo encargado', 15500.00),
(14, 3, 2, 'Limpieza áreas comunes', 5200.00),
(15, 3, 4, 'Servicios comunes', 3300.00),
(16, 3, 7, 'Fondo reserva', 1200.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

CREATE TABLE `pagos` (
  `id` int(11) NOT NULL,
  `tipo_pago` enum('alquiler','expensa','reserva','reparacion') NOT NULL,
  `contrato_id` int(11) DEFAULT NULL,
  `inquilino_id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `moneda` enum('ARS','USD') NOT NULL DEFAULT 'ARS',
  `fecha_pago` date NOT NULL,
  `metodo_pago` varchar(100) NOT NULL,
  `comprobante_path` varchar(500) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pagos`
--

INSERT INTO `pagos` (`id`, `tipo_pago`, `contrato_id`, `inquilino_id`, `unidad_id`, `monto`, `moneda`, `fecha_pago`, `metodo_pago`, `comprobante_path`, `creado_en`) VALUES
(1, 'alquiler', 1, 1, 1, 150000.00, 'ARS', '2024-01-05', 'transferencia', NULL, '2025-11-06 13:55:24'),
(2, 'expensa', NULL, 1, 1, 25000.00, 'ARS', '2024-01-08', 'efectivo', NULL, '2025-11-06 13:55:24'),
(3, 'alquiler', 2, 2, 2, 120000.00, 'ARS', '2024-01-10', 'transferencia', NULL, '2025-11-06 13:55:24'),
(4, 'expensa', NULL, 2, 2, 23000.00, 'ARS', '2024-01-09', 'efectivo', NULL, '2025-11-06 13:55:24'),
(5, 'alquiler', 3, 3, 4, 800.00, 'USD', '2024-01-15', 'transferencia', NULL, '2025-11-06 13:55:24'),
(6, 'alquiler', 1, 1, 1, 150000.00, 'ARS', '2024-02-05', 'transferencia', NULL, '2025-11-06 13:55:24'),
(7, 'expensa', NULL, 1, 1, 25500.00, 'ARS', '2024-02-07', 'efectivo', NULL, '2025-11-06 13:55:24'),
(8, 'alquiler', 2, 2, 2, 120000.00, 'ARS', '2024-02-10', 'transferencia', NULL, '2025-11-06 13:55:24'),
(9, 'expensa', NULL, 2, 2, 23500.00, 'ARS', '2024-02-08', 'efectivo', NULL, '2025-11-06 13:55:24'),
(10, 'alquiler', 3, 3, 4, 800.00, 'USD', '2024-02-15', 'transferencia', NULL, '2025-11-06 13:55:24');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reparaciones`
--

CREATE TABLE `reparaciones` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) DEFAULT NULL,
  `descripcion` text NOT NULL,
  `monto_estimado` decimal(12,2) DEFAULT NULL,
  `monto_gastado` decimal(12,2) DEFAULT NULL,
  `fecha_reporte` date NOT NULL,
  `fecha_ejecucion` date DEFAULT NULL,
  `responsable` varchar(150) NOT NULL,
  `fuente_financiacion` enum('reserva','fondo_edificio','otro') NOT NULL,
  `estado` enum('pendiente','en_proceso','finalizada') NOT NULL DEFAULT 'pendiente',
  `comprobante_path` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `reparaciones`
--

INSERT INTO `reparaciones` (`id`, `unidad_id`, `descripcion`, `monto_estimado`, `monto_gastado`, `fecha_reporte`, `fecha_ejecucion`, `responsable`, `fuente_financiacion`, `estado`, `comprobante_path`) VALUES
(1, NULL, 'Reparación ascensor', 150000.00, 145000.00, '2024-01-20', '2024-02-15', 'Empresa Ascensores S.A.', 'reserva', 'finalizada', NULL),
(2, 1, 'Cambio grifería cocina', 25000.00, 23000.00, '2024-02-10', '2024-02-12', 'Juan Plomero', 'fondo_edificio', 'finalizada', NULL),
(3, 2, 'Pintura interior', 30000.00, NULL, '2024-03-01', NULL, 'Pinturas y Decoraciones', 'reserva', 'pendiente', NULL),
(4, NULL, 'Reparación fachada', 80000.00, NULL, '2024-03-05', NULL, 'Constructora Norte', 'otro', 'en_proceso', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reservas`
--

CREATE TABLE `reservas` (
  `id` int(11) NOT NULL,
  `descripcion` text NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `moneda` enum('ARS','USD') NOT NULL DEFAULT 'ARS',
  `fecha_creacion` date NOT NULL,
  `origen` enum('excedente_expensa','aporte_extra') NOT NULL,
  `estado` enum('disponible','usado') NOT NULL DEFAULT 'disponible'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `reservas`
--

INSERT INTO `reservas` (`id`, `descripcion`, `monto`, `moneda`, `fecha_creacion`, `origen`, `estado`) VALUES
(1, 'Excedente expensas enero 2024', 50000.00, 'ARS', '2024-02-01', 'excedente_expensa', 'disponible'),
(2, 'Excedente expensas febrero 2024', 52000.00, 'ARS', '2024-03-01', 'excedente_expensa', 'disponible'),
(3, 'Aporte extraordinario', 100000.00, 'ARS', '2024-01-15', 'aporte_extra', 'usado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `unidades`
--

CREATE TABLE `unidades` (
  `id` int(11) NOT NULL,
  `edificio_id` int(11) NOT NULL,
  `tipo` enum('departamento','oficina','local') NOT NULL,
  `numero` varchar(50) NOT NULL,
  `superficie` decimal(10,2) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `unidades`
--

INSERT INTO `unidades` (`id`, `edificio_id`, `tipo`, `numero`, `superficie`, `descripcion`, `activo`) VALUES
(1, 1, 'departamento', 'A', 85.50, 'Departamento 3 ambientes con balcón', 1),
(2, 1, 'departamento', 'B', 75.00, 'Departamento 2 ambientes', 1),
(3, 1, 'departamento', 'C', 90.25, 'Departamento 3 ambientes con terraza', 1),
(4, 1, 'oficina', '101', 45.00, 'Oficina individual', 1),
(5, 1, 'oficina', '102', 60.00, 'Oficina para 2 personas', 1),
(6, 1, 'local', 'PB', 120.00, 'Local comercial a la calle', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` enum('admin','usuario') NOT NULL DEFAULT 'usuario',
  `activo` tinyint(1) DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_acceso` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `apellido`, `email`, `password_hash`, `rol`, `activo`, `creado_en`, `ultimo_acceso`) VALUES
(1, 'Admin', 'Sistema', 'admin@sistema.com', '$2y$10$RCOjJb/59DsjhzIUrY8S7.HWEWn7O7Ok8PXV/.N.jfFuJVaWG/pk6', 'admin', 1, '2025-11-06 13:55:24', '2025-11-06 13:48:49');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `categorias_gasto`
--
ALTER TABLE `categorias_gasto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_nombre` (`nombre`);

--
-- Indices de la tabla `contratos`
--
ALTER TABLE `contratos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inquilino` (`inquilino_id`),
  ADD KEY `idx_unidad` (`unidad_id`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_fecha_vencimiento` (`fecha_vencimiento`),
  ADD KEY `idx_fecha_inicio` (`fecha_inicio`);

--
-- Indices de la tabla `edificios`
--
ALTER TABLE `edificios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_nombre` (`nombre`);

--
-- Indices de la tabla `expensas`
--
ALTER TABLE `expensas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_expensa_unidad_periodo` (`unidad_id`,`periodo_ano`,`periodo_mes`),
  ADD KEY `idx_unidad` (`unidad_id`),
  ADD KEY `idx_periodo` (`periodo_ano`,`periodo_mes`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_fecha_vencimiento` (`fecha_vencimiento`);

--
-- Indices de la tabla `inquilinos`
--
ALTER TABLE `inquilinos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD KEY `idx_dni` (`dni`),
  ADD KEY `idx_nombre_apellido` (`nombre`,`apellido`);

--
-- Indices de la tabla `logs_sistema`
--
ALTER TABLE `logs_sistema`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_tabla` (`tabla`),
  ADD KEY `idx_creado_en` (`creado_en`);

--
-- Indices de la tabla `movimientos_expensa`
--
ALTER TABLE `movimientos_expensa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_expensa` (`expensa_id`),
  ADD KEY `idx_categoria` (`categoria_id`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tipo_pago` (`tipo_pago`),
  ADD KEY `idx_inquilino` (`inquilino_id`),
  ADD KEY `idx_unidad` (`unidad_id`),
  ADD KEY `idx_fecha_pago` (`fecha_pago`),
  ADD KEY `idx_contrato` (`contrato_id`);

--
-- Indices de la tabla `reparaciones`
--
ALTER TABLE `reparaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_unidad` (`unidad_id`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_fuente_financiacion` (`fuente_financiacion`),
  ADD KEY `idx_fecha_reporte` (`fecha_reporte`);

--
-- Indices de la tabla `reservas`
--
ALTER TABLE `reservas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_moneda` (`moneda`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_fecha_creacion` (`fecha_creacion`);

--
-- Indices de la tabla `unidades`
--
ALTER TABLE `unidades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_unidad_edificio` (`edificio_id`,`numero`),
  ADD KEY `idx_edificio` (`edificio_id`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_activo` (`activo`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_rol` (`rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `categorias_gasto`
--
ALTER TABLE `categorias_gasto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `contratos`
--
ALTER TABLE `contratos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `edificios`
--
ALTER TABLE `edificios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `expensas`
--
ALTER TABLE `expensas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `inquilinos`
--
ALTER TABLE `inquilinos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `logs_sistema`
--
ALTER TABLE `logs_sistema`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `movimientos_expensa`
--
ALTER TABLE `movimientos_expensa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `reparaciones`
--
ALTER TABLE `reparaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `reservas`
--
ALTER TABLE `reservas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `unidades`
--
ALTER TABLE `unidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `contratos`
--
ALTER TABLE `contratos`
  ADD CONSTRAINT `contratos_ibfk_1` FOREIGN KEY (`inquilino_id`) REFERENCES `inquilinos` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `contratos_ibfk_2` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `expensas`
--
ALTER TABLE `expensas`
  ADD CONSTRAINT `expensas_ibfk_1` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `logs_sistema`
--
ALTER TABLE `logs_sistema`
  ADD CONSTRAINT `logs_sistema_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `movimientos_expensa`
--
ALTER TABLE `movimientos_expensa`
  ADD CONSTRAINT `movimientos_expensa_ibfk_1` FOREIGN KEY (`expensa_id`) REFERENCES `expensas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `movimientos_expensa_ibfk_2` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_gasto` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`contrato_id`) REFERENCES `contratos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `pagos_ibfk_2` FOREIGN KEY (`inquilino_id`) REFERENCES `inquilinos` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `pagos_ibfk_3` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `reparaciones`
--
ALTER TABLE `reparaciones`
  ADD CONSTRAINT `reparaciones_ibfk_1` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `unidades`
--
ALTER TABLE `unidades`
  ADD CONSTRAINT `unidades_ibfk_1` FOREIGN KEY (`edificio_id`) REFERENCES `edificios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
