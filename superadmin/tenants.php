<?php
session_start();
require_once 'config_superadmin.php';
verificarSuperAdmin();

// Filtros
$filtro_estado = $_GET['estado'] ?? '';
$filtro_plan = $_GET['plan'] ?? '';
$buscar = $_GET['buscar'] ?? '';

// Construir query
$where = [];
$params = [];

// Por defecto, NO mostrar clientes cancelados
if ($filtro_estado) {
    $where[] = "estado = ?";
    $params[] = $filtro_estado;
} else {
    // Si no hay filtro de estado, excluir cancelados
    $where[] = "estado != 'cancelado'";
}

if ($filtro_plan) {
    $where[] = "plan = ?";
    $params[] = $filtro_plan;
}
if ($buscar) {
    $where[] = "(nombre LIKE ? OR dominio LIKE ? OR email_contacto LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

$sql = "SELECT * FROM tenants WHERE " . implode(" AND ", $where) . " ORDER BY created_at DESC";
$stmt = $conn_master->prepare($sql);
$stmt->execute($params);
$tenants = $stmt->fetchAll();

// Mensaje de éxito
$mensaje = $_GET['mensaje'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes - Super Admin</title>
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
        .badge-plan {
            padding: 5px 12px;
            font-weight: 600;
            border-radius: 20px;
        }
        .table-actions .btn {
            padding: 4px 8px;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
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
                        <a class="nav-link active" href="tenants.php">
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
                        <a class="nav-link" href="acceso_clientes.php">
                            <i class="bi bi-door-open me-1"></i>Acceso Clientes
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center text-white">
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
            <div class="col-md-6">
                <h2 class="fw-bold">
                    <i class="bi bi-building me-2"></i>Gestión de Clientes
                </h2>
                <p class="text-muted">Administra todos los clientes del sistema SaaS</p>
            </div>
            <div class="col-md-6 text-end">
                <a href="restaurar_tenant.php" class="btn btn-success me-2">
                    <i class="bi bi-arrow-counterclockwise me-2"></i>Restaurar Cliente
                </a>
                <a href="crear_tenant.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-plus-circle me-2"></i>Nuevo Cliente
                </a>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card stat-card mb-4">
            <div class="card-body">
                <?php if ($mensaje): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($mensaje) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Buscar</label>
                        <input type="text" class="form-control" name="buscar" value="<?= htmlspecialchars($buscar) ?>" placeholder="Nombre, dominio, email...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="estado">
                            <option value="">Todos</option>
                            <option value="activo" <?= $filtro_estado === 'activo' ? 'selected' : '' ?>>Activo</option>
                            <option value="suspendido" <?= $filtro_estado === 'suspendido' ? 'selected' : '' ?>>Suspendido</option>
                            <option value="vencido" <?= $filtro_estado === 'vencido' ? 'selected' : '' ?>>Vencido</option>
                            <option value="cancelado" <?= $filtro_estado === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Plan</label>
                        <select class="form-select" name="plan">
                            <option value="">Todos</option>
                            <option value="basico" <?= $filtro_plan === 'basico' ? 'selected' : '' ?>>Básico</option>
                            <option value="estandar" <?= $filtro_plan === 'estandar' ? 'selected' : '' ?>>Estándar</option>
                            <option value="premium" <?= $filtro_plan === 'premium' ? 'selected' : '' ?>>Premium</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i>Filtrar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de Clientes -->
        <div class="card stat-card">
            <div class="card-body">
                <?php if (empty($tenants)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size: 4rem;"></i>
                        <p class="mt-3">No se encontraron clientes</p>
                        <a href="crear_tenant.php" class="btn btn-primary mt-2">
                            <i class="bi bi-plus-circle me-2"></i>Crear Primer Cliente
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Cliente</th>
                                    <th>Dominio</th>
                                    <th>Plan</th>
                                    <th>Estado</th>
                                    <th>Inicio</th>
                                    <th>Vencimiento</th>
                                    <th>Contacto</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tenants as $tenant): ?>
                                <tr>
                                    <td><strong>#<?= $tenant['id'] ?></strong></td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($tenant['nombre']) ?></strong>
                                            <?php if ($tenant['razon_social']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($tenant['razon_social']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <code><?= htmlspecialchars($tenant['dominio']) ?></code>
                                    </td>
                                    <td>
                                        <?php
                                        $plan_colors = [
                                            'basico' => 'secondary',
                                            'estandar' => 'primary',
                                            'premium' => 'warning'
                                        ];
                                        $color = $plan_colors[$tenant['plan']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-plan bg-<?= $color ?>">
                                            <?= ucfirst($tenant['plan']) ?>
                                        </span>
                                        <div class="small text-muted">$<?= number_format($tenant['precio_mensual'], 2) ?>/mes</div>
                                    </td>
                                    <td>
                                        <?php
                                        $estado_colors = [
                                            'activo' => 'success',
                                            'suspendido' => 'warning',
                                            'vencido' => 'danger',
                                            'cancelado' => 'secondary'
                                        ];
                                        $color = $estado_colors[$tenant['estado']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $color ?>">
                                            <?= ucfirst($tenant['estado']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($tenant['fecha_inicio'])) ?></td>
                                    <td>
                                        <?php if ($tenant['fecha_expiracion']): ?>
                                            <?= date('d/m/Y', strtotime($tenant['fecha_expiracion'])) ?>
                                            <?php
                                            $dias_restantes = (strtotime($tenant['fecha_expiracion']) - time()) / 86400;
                                            if ($dias_restantes < 7 && $dias_restantes > 0):
                                            ?>
                                                <br><small class="text-warning">
                                                    <i class="bi bi-exclamation-triangle-fill"></i> <?= floor($dias_restantes) ?> días
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($tenant['email_contacto']): ?>
                                            <small>
                                                <i class="bi bi-envelope"></i> <?= htmlspecialchars($tenant['email_contacto']) ?><br>
                                                <?php if ($tenant['telefono_contacto']): ?>
                                                    <i class="bi bi-telephone"></i> <?= htmlspecialchars($tenant['telefono_contacto']) ?>
                                                <?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="table-actions text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="ver_tenant.php?id=<?= $tenant['id'] ?>" class="btn btn-outline-info" title="Ver detalles">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="editar_tenant.php?id=<?= $tenant['id'] ?>" class="btn btn-outline-primary" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="cambiar_password_tenant.php?id=<?= $tenant['id'] ?>" class="btn btn-outline-warning" title="Cambiar contraseña">
                                                <i class="bi bi-key"></i>
                                            </a>
                                            <?php if ($tenant['estado'] === 'activo'): ?>
                                                <button onclick="suspenderTenant(<?= $tenant['id'] ?>)" class="btn btn-outline-warning" title="Suspender">
                                                    <i class="bi bi-pause-circle"></i>
                                                </button>
                                            <?php else: ?>
                                                <button onclick="activarTenant(<?= $tenant['id'] ?>)" class="btn btn-outline-success" title="Activar">
                                                    <i class="bi bi-play-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                            <a href="eliminar_tenant.php?id=<?= $tenant['id'] ?>" class="btn btn-outline-danger" title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        <p class="text-muted mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Total de clientes: <strong><?= count($tenants) ?></strong>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function suspenderTenant(id) {
            if (confirm('¿Estás seguro de suspender este cliente? Se bloqueará el acceso a su sistema.')) {
                window.location.href = `cambiar_estado_tenant.php?id=${id}&estado=suspendido`;
            }
        }
        
        function activarTenant(id) {
            if (confirm('¿Activar este cliente? Podrá acceder nuevamente a su sistema.')) {
                window.location.href = `cambiar_estado_tenant.php?id=${id}&estado=activo`;
            }
        }
    </script>
</body>
</html>
