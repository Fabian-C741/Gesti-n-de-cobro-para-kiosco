<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    <?php
    // Cargar favicon desde configuración
    $db_favicon = Database::getInstance()->getConnection();
    $stmt_favicon = $db_favicon->query("SELECT valor FROM configuracion_sistema WHERE clave = 'favicon'");
    $favicon = $stmt_favicon->fetchColumn();
    if ($favicon):
    ?>
    <link rel="icon" type="image/x-icon" href="../<?php echo htmlspecialchars($favicon); ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <?php if (file_exists('../assets/css/custom.css')): ?>
    <link rel="stylesheet" href="../assets/css/custom.css?v=<?php echo time(); ?>">
    <?php endif; ?>
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
                <a href="usuarios.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : ''; ?>">
                    <i class="bi bi-people"></i> Usuarios
                </a>
                <a href="tokens.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tokens.php' ? 'active' : ''; ?>">
                    <i class="bi bi-key"></i> Tokens de Acceso
                </a>
                <a href="productos.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'productos.php' ? 'active' : ''; ?>">
                    <i class="bi bi-box-seam"></i> Productos
                </a>
                <a href="categorias.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'categorias.php' ? 'active' : ''; ?>">
                    <i class="bi bi-tags"></i> Categorías
                </a>
                <a href="ventas.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'ventas.php' ? 'active' : ''; ?>">
                    <i class="bi bi-receipt"></i> Ventas
                </a>
                <a href="reportes.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reportes.php' ? 'active' : ''; ?>">
                    <i class="bi bi-graph-up"></i> Reportes
                </a>
                <a href="reportes_empleados.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reportes_empleados.php' ? 'active' : ''; ?>">
                    <i class="bi bi-people-fill"></i> Reportes Empleados
                </a>
                <a href="configuracion.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'configuracion.php' ? 'active' : ''; ?>">
                    <i class="bi bi-gear"></i> Configuración
                </a>
                <a href="configuracion_sistema.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'configuracion_sistema.php' ? 'active' : ''; ?>">
                    <i class="bi bi-sliders"></i> Config. del Sistema
                </a>
                <a href="personalizacion.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'personalizacion.php' ? 'active' : ''; ?>">
                    <i class="bi bi-palette"></i> Personalización
                </a>
                <a href="logs.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'active' : ''; ?>">
                    <i class="bi bi-list-ul"></i> Logs de Actividad
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
                        <span class="badge bg-primary ms-2">Admin</span>
                    </span>
                </div>
            </div>
        </nav>
