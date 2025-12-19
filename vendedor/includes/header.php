<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <!-- Librería Html5-QRCode para escanear códigos de barras con cámara -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <i class="bi bi-shop"></i>
            <?php echo APP_NAME; ?>
        </div>
        <div class="sidebar-menu">
            <nav class="nav flex-column mt-3">
                <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <?php if ($_SESSION['user_rol'] !== 'cajero'): ?>
                <a href="productos.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'productos.php' ? 'active' : ''; ?>">
                    <i class="bi bi-box-seam"></i> Mis Productos
                </a>
                <a href="categorias.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'categorias.php' ? 'active' : ''; ?>">
                    <i class="bi bi-tags"></i> Categorías
                </a>
                <?php endif; ?>
                <a href="nueva_venta.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'nueva_venta.php' ? 'active' : ''; ?>">
                    <i class="bi bi-plus-circle"></i> Nueva Venta
                </a>
                <a href="ventas.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'ventas.php' ? 'active' : ''; ?>">
                    <i class="bi bi-receipt"></i> Mis Ventas
                </a>
                <?php if ($_SESSION['user_rol'] !== 'cajero'): ?>
                <a href="reportes.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reportes.php' ? 'active' : ''; ?>">
                    <i class="bi bi-bar-chart-line"></i> Reportes
                </a>
                <?php endif; ?>
                <a href="perfil.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'perfil.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person-circle"></i> Mi Perfil
                </a>
            </nav>
        </div>
        <div class="sidebar-footer">
            <hr class="border-white-50 my-2">
            <div class="text-white-50 small mb-2">
                <i class="bi bi-person-circle me-1"></i>
                <?php echo htmlspecialchars($_SESSION['user_nombre']); ?>
            </div>
            <a href="logout.php" class="btn btn-outline-light btn-sm w-100">
                <i class="bi bi-box-arrow-right me-1"></i> Cerrar Sesión
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <nav class="navbar navbar-top mb-4">
            <div class="container-fluid">
                <button class="btn btn-link text-dark d-lg-none" onclick="toggleSidebar()">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <div class="ms-auto d-flex align-items-center">
                    <span class="me-3">
                        <i class="bi bi-person-circle me-1"></i>
                        <strong><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></strong>
                        <?php 
                        $rol = $_SESSION['user_rol'] ?? 'vendedor';
                        $badgeColor = $rol === 'admin' ? 'danger' : ($rol === 'cajero' ? 'info' : 'success');
                        $rolTexto = $rol === 'admin' ? 'Administrador' : ($rol === 'cajero' ? 'Cajero' : 'Colaborador');
                        ?>
                        <span class="badge bg-<?php echo $badgeColor; ?> ms-2"><?php echo $rolTexto; ?></span>
                    </span>
                </div>
            </div>
        </nav>
