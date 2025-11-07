<?php
// Incluir configuración segura de sesión primero
require_once __DIR__ . '/sesion_segura.php';
iniciarSesionSegura();

// Configuración de la página
$titulo_pagina = $titulo_pagina ?? 'Sistema de Gestión';
$icono_titulo = $icono_titulo ?? 'fas fa-cog';
$breadcrumb = $breadcrumb ?? [];
$acciones_titulo = $acciones_titulo ?? '';

// ... el resto del código de header.php permanece igual ...

// Incluir Chart.js solo en páginas que lo necesiten
$paginas_con_graficos = ['index.php', 'reportes.php', 'dashboard.php'];
$pagina_actual = basename($_SERVER['PHP_SELF']);
if (in_array($pagina_actual, $paginas_con_graficos)) {
    $scripts_extra_header = '
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    ';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo_pagina) ?> - <?= APP_NAME ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <!-- Estilos personalizados -->
    <link href="../assets/css/estilo.css" rel="stylesheet">
    
    <?= $scripts_extra_header ?? '' ?>
    
    <style>
        .sidebar {
            min-height: calc(100vh - 56px);
            background: #2c3e50;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 0.75rem 1rem;
            border-left: 3px solid transparent;
        }
        .sidebar .nav-link:hover {
            background: #34495e;
            border-left-color: #3498db;
        }
        .sidebar .nav-link.active {
            background: #34495e;
            border-left-color: #3498db;
            color: #3498db;
        }
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .breadcrumb {
            background: transparent;
            padding: 0.75rem 0;
        }
        .main-content {
            background: #f8f9fa;
            min-height: calc(100vh - 56px);
        }
    </style>
</head>
<body>
    <!-- Barra de Navegación Superior -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-building me-2"></i>
                <?= APP_NAME ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i>
                            <?= htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text">
                                <small><?= htmlspecialchars($_SESSION['usuario_email'] ?? '') ?></small>
                            </span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="perfil.php">
                                <i class="fas fa-user-edit me-2"></i>Mi Perfil
                            </a></li>
                            <?php if (esAdmin()): ?>
                            <li><a class="dropdown-item" href="usuarios.php">
                                <i class="fas fa-users me-2"></i>Gestionar Usuarios
                            </a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?= $pagina_actual === 'index.php' ? 'active' : '' ?>" href="index.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        
                        <!-- Gestión de Propiedades -->
                        <li class="nav-item mt-3">
                            <small class="text-muted px-3">PROPIEDADES</small>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $pagina_actual === 'edificios.php' ? 'active' : '' ?>" href="edificios.php">
                                <i class="fas fa-building me-2"></i>
                                Edificios
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $pagina_actual === 'unidades.php' ? 'active' : '' ?>" href="unidades.php">
                                <i class="fas fa-home me-2"></i>
                                Unidades
                            </a>
                        </li>
                        
                        <!-- Gestión de Personas -->
                        <li class="nav-item mt-3">
                            <small class="text-muted px-3">PERSONAS</small>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $pagina_actual === 'inquilinos.php' ? 'active' : '' ?>" href="inquilinos.php">
                                <i class="fas fa-users me-2"></i>
                                Inquilinos
                            </a>
                        </li>
                        
                        <!-- Contratos y Pagos -->
                        <li class="nav-item mt-3">
                            <small class="text-muted px-3">CONTRATOS Y PAGOS</small>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $pagina_actual === 'contratos.php' ? 'active' : '' ?>" href="contratos.php">
                                <i class="fas fa-file-contract me-2"></i>
                                Contratos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $pagina_actual === 'expensas.php' ? 'active' : '' ?>" href="expensas.php">
                                <i class="fas fa-receipt me-2"></i>
                                Expensas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $pagina_actual === 'pagos.php' ? 'active' : '' ?>" href="pagos.php">
                                <i class="fas fa-money-bill-wave me-2"></i>
                                Pagos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $pagina_actual === 'conciliacion.php' ? 'active' : '' ?>" href="conciliacion.php">
                                <i class="fas fa-balance-scale me-2"></i>
                                Conciliación
                            </a>
                        </li>
                        
                        <!-- Mantenimiento -->
                        <li class="nav-item mt-3">
                            <small class="text-muted px-3">MANTENIMIENTO</small>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $pagina_actual === 'reparaciones.php' ? 'active' : '' ?>" href="reparaciones.php">
                                <i class="fas fa-tools me-2"></i>
                                Reparaciones
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $pagina_actual === 'reservas.php' ? 'active' : '' ?>" href="reservas.php">
                                <i class="fas fa-piggy-bank me-2"></i>
                                Reservas
                            </a>
                        </li>
                        
                        <!-- Reportes y Administración -->
                        <li class="nav-item mt-3">
                            <small class="text-muted px-3">REPORTES Y ADMIN</small>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $pagina_actual === 'reportes.php' ? 'active' : '' ?>" href="reportes.php">
                                <i class="fas fa-chart-bar me-2"></i>
                                Reportes
                            </a>
                        </li>
                        <?php if (esAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $pagina_actual === 'usuarios.php' ? 'active' : '' ?>" href="usuarios.php">
                                <i class="fas fa-user-cog me-2"></i>
                                Usuarios
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $pagina_actual === 'configuracion.php' ? 'active' : '' ?>" href="configuracion.php">
                                <i class="fas fa-cogs me-2"></i>
                                Configuración
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                    
                    <!-- Información del Sistema -->
                    <div class="mt-4 px-3">
                        <div class="card bg-dark border-0">
                            <div class="card-body p-3">
                                <small class="text-light">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <strong>v<?= APP_VERSION ?></strong><br>
                                    <span class="text-muted"><?= APP_NAME ?></span>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contenido Principal -->
            <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
                <div class="container-fluid py-4">
                    <!-- Header de la Página -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-1">
                                <i class="<?= $icono_titulo ?> me-2"></i>
                                <?= htmlspecialchars($titulo_pagina) ?>
                            </h1>
                            <!-- Breadcrumb -->
                            <?php if (!empty($breadcrumb)): ?>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <?php foreach ($breadcrumb as $item): ?>
                                        <?php if (isset($item['active']) && $item['active']): ?>
                                            <li class="breadcrumb-item active"><?= htmlspecialchars($item['text']) ?></li>
                                        <?php else: ?>
                                            <li class="breadcrumb-item">
                                                <a href="<?= htmlspecialchars($item['url']) ?>">
                                                    <?= htmlspecialchars($item['text']) ?>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ol>
                            </nav>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Acciones del Título -->
                        <?php if (!empty($acciones_titulo)): ?>
                            <div class="d-flex gap-2">
                                <?= $acciones_titulo ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Mensajes de Alerta -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= $_SESSION['success'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= $_SESSION['error'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['warning'])): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?= $_SESSION['warning'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['warning']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['info'])): ?>
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <i class="fas fa-info-circle me-2"></i>
                            <?= $_SESSION['info'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['info']); ?>
                    <?php endif; ?>