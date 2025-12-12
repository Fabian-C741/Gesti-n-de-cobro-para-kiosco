<?php
session_start();
require_once 'config_superadmin.php';
verificarSuperAdmin();

$error = '';
$tenants_disponibles = [];

// Obtener todos los tenants activos
$stmt = $conn_master->query("SELECT id, nombre, dominio, plan, estado FROM tenants WHERE estado != 'cancelado' ORDER BY nombre");
$tenants_disponibles = $stmt->fetchAll();

// MODO DIOS: Acceso directo sin contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tenant_id'])) {
    $tenant_id = intval($_POST['tenant_id']);
    
    // Obtener datos del tenant
    $stmt = $conn_master->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmt->execute([$tenant_id]);
    $tenant = $stmt->fetch();
    
    if (!$tenant) {
        $error = 'Cliente no encontrado';
    } else {
        try {
            // Conectar a la base de datos del tenant usando credenciales del .env
            $tenant_dsn = "mysql:host=localhost;dbname={$tenant['bd_nombre']};charset=utf8mb4";
            $tenant_conn = new PDO($tenant_dsn, DB_USER_MASTER, DB_PASS_MASTER);
            $tenant_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Buscar el usuario admin del tenant
            $stmt_admin = $tenant_conn->prepare("SELECT * FROM usuarios WHERE user_rol = 'admin' LIMIT 1");
            $stmt_admin->execute();
            $admin_user = $stmt_admin->fetch();
            
            if (!$admin_user) {
                $error = 'No se encontró usuario administrador en este cliente';
            } else {
                // CREAR SESIÓN DEL TENANT (modo soporte)
                $_SESSION['tenant_dominio'] = $tenant['dominio'];
                $_SESSION['tenant_bd_nombre'] = $tenant['bd_nombre'];
                $_SESSION['tenant_bd_usuario'] = $tenant['bd_usuario'];
                $_SESSION['tenant_bd_password'] = $tenant['bd_password'];
                
                // Crear sesión de usuario admin
                $_SESSION['user_id'] = $admin_user['id'];
                $_SESSION['user_nombre'] = $admin_user['nombre'];
                $_SESSION['user_email'] = $admin_user['email'];
                $_SESSION['user_rol'] = $admin_user['rol'];
                
                // Marcar que es acceso de super admin
                $_SESSION['super_admin_access'] = true;
                $_SESSION['super_admin_original_id'] = $_SESSION['super_admin_id'];
                $_SESSION['super_admin_original_nombre'] = $_SESSION['super_admin_nombre'];
                
                // Registrar log
                registrarLog($tenant_id, 'acceso_superadmin', "Super Admin {$_SESSION['super_admin_nombre']} accedió como administrador del tenant");
                
                // Redirigir al dashboard del admin
                header('Location: ../admin/dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Error al conectar con la base de datos del cliente: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso a Clientes - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        body {
            background: #f8f9fa;
        }
        .navbar {
            background: var(--primary-gradient);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .tenant-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .tenant-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
            transform: translateY(-3px);
        }
        .modo-dios-badge {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="bi bi-shield-lock-fill me-2"></i>Super Admin Panel
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tenants.php">
                            <i class="bi bi-building me-1"></i>Clientes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="puntos_venta.php">
                            <i class="bi bi-shop me-1"></i>Puntos de Venta
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pagos.php">
                            <i class="bi bi-cash-coin me-1"></i>Pagos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logs.php">
                            <i class="bi bi-journal-text me-1"></i>Logs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="configuracion.php">
                            <i class="bi bi-gear me-1"></i>Configuración
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="acceso_clientes.php">
                            <i class="bi bi-door-open me-1"></i>Acceso Clientes
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center text-white">
                    <span class="modo-dios-badge me-3">
                        <i class="bi bi-lightning-charge-fill me-1"></i>MODO DIOS
                    </span>
                    <span class="me-3">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($_SESSION['super_admin_nombre']) ?>
                    </span>
                    <a href="logout.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>Salir
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="fw-bold">
                    <i class="bi bi-door-open me-2"></i>Acceso Directo a Clientes
                </h2>
                <p class="text-muted">Modo Dios: Accede a cualquier cliente sin contraseña para dar soporte o configurar el sistema</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <div class="card stat-card">
                    <div class="card-header bg-gradient" style="background: var(--primary-gradient); color: white;">
                        <h5 class="mb-0">
                            <i class="bi bi-building me-2"></i>Selecciona un cliente para acceder
                            <span class="badge bg-light text-dark ms-2"><?= count($tenants_disponibles) ?> disponibles</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-shield-exclamation me-2"></i>
                            <strong>Atención:</strong> Al acceder a un cliente, entrarás como su usuario administrador. 
                            Todos los cambios que hagas quedarán registrados. Para volver al panel de super admin, 
                            usa el botón "Volver a Super Admin" que aparecerá en el dashboard.
                        </div>

                        <?php if (empty($tenants_disponibles)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-inbox" style="font-size: 4rem;"></i>
                                <p class="mt-3">No hay clientes disponibles</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($tenants_disponibles as $tenant): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                                            <div class="tenant-card" onclick="this.closest('form').submit()">
                                                <div class="d-flex align-items-start mb-2">
                                                    <div class="flex-grow-1">
                                                        <h5 class="mb-1 fw-bold text-primary">
                                                            <i class="bi bi-building me-2"></i>
                                                            <?= htmlspecialchars($tenant['nombre']) ?>
                                                        </h5>
                                                        <p class="mb-2 text-muted">
                                                            <i class="bi bi-globe me-1"></i>
                                                            <code><?= htmlspecialchars($tenant['dominio']) ?></code>
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <?php
                                                        $plan_colors = [
                                                            'basico' => 'secondary',
                                                            'estandar' => 'primary',
                                                            'premium' => 'warning'
                                                        ];
                                                        $color = $plan_colors[$tenant['plan']] ?? 'secondary';
                                                        ?>
                                                        <span class="badge bg-<?= $color ?>">
                                                            <?= ucfirst($tenant['plan']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <?php
                                                    $estado_colors = [
                                                        'activo' => 'success',
                                                        'suspendido' => 'warning',
                                                        'vencido' => 'danger'
                                                    ];
                                                    $color_estado = $estado_colors[$tenant['estado']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?= $color_estado ?>">
                                                        <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>
                                                        <?= ucfirst($tenant['estado']) ?>
                                                    </span>
                                                    
                                                    <button type="submit" class="btn btn-sm btn-primary" onclick="event.stopPropagation()">
                                                        <i class="bi bi-box-arrow-in-right me-1"></i>Acceder
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
