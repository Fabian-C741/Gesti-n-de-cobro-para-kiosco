<?php
session_start();
require_once 'config_superadmin.php';
verificarSuperAdmin();

$id = $_GET['id'] ?? 0;

// Obtener datos del tenant
$stmt = $conn_master->prepare("SELECT * FROM tenants WHERE id = ?");
$stmt->execute([$id]);
$tenant = $stmt->fetch();

if (!$tenant) {
    header('Location: tenants.php');
    exit;
}

// Obtener estadísticas del tenant
try {
    $conn_tenant = conectarTenant($id);
    
    // Contar usuarios
    $stmt = $conn_tenant->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 1");
    $stats_usuarios = $stmt->fetch();
    
    // Contar productos
    $stmt = $conn_tenant->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1");
    $stats_productos = $stmt->fetch();
    
    // Contar puntos de venta
    $stmt = $conn_tenant->query("SELECT COUNT(*) as total FROM puntos_venta WHERE activo = 1");
    $stats_puntos = $stmt->fetch();
    
    // Total de ventas
    $stmt = $conn_tenant->query("SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as monto FROM ventas");
    $stats_ventas = $stmt->fetch();
    
    $stats_ok = true;
} catch (Exception $e) {
    $stats_ok = false;
    $error_stats = $e->getMessage();
}

// Obtener logs recientes
$stmt = $conn_master->prepare("
    SELECT * FROM tenant_logs 
    WHERE tenant_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$id]);
$logs = $stmt->fetchAll();

// Obtener pagos
$stmt = $conn_master->prepare("
    SELECT * FROM tenant_pagos 
    WHERE tenant_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$id]);
$pagos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Cliente - Super Admin</title>
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
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
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
                        <a class="nav-link" href="pagos.php">
                            <i class="bi bi-cash-coin me-1"></i>Pagos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logs.php">
                            <i class="bi bi-journal-text me-1"></i>Logs
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
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="tenants.php">Clientes</a></li>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($tenant['nombre']) ?></li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="fw-bold mb-0">
                    <i class="bi bi-building me-2"></i><?= htmlspecialchars($tenant['nombre']) ?>
                </h2>
                <?php if ($tenant['razon_social']): ?>
                    <p class="text-muted mb-0"><?= htmlspecialchars($tenant['razon_social']) ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-4 text-end">
                <a href="editar_tenant.php?id=<?= $id ?>" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i>Editar
                </a>
                <?php if ($tenant['estado'] === 'activo'): ?>
                    <button onclick="suspenderTenant()" class="btn btn-warning">
                        <i class="bi bi-pause-circle me-1"></i>Suspender
                    </button>
                <?php else: ?>
                    <button onclick="activarTenant()" class="btn btn-success">
                        <i class="bi bi-play-circle me-1"></i>Activar
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info Principal -->
        <div class="row g-4 mb-4">
            <div class="col-md-8">
                <div class="card stat-card">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold">Información General</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="text-muted small">Dominio</label>
                                <div class="fw-semibold">
                                    <code><?= htmlspecialchars($tenant['dominio']) ?></code>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="text-muted small">Plan</label>
                                <div class="fw-semibold text-capitalize"><?= $tenant['plan'] ?></div>
                            </div>
                            <div class="col-md-3">
                                <label class="text-muted small">Estado</label>
                                <div>
                                    <?php
                                    $estado_colors = [
                                        'activo' => 'success',
                                        'suspendido' => 'warning',
                                        'vencido' => 'danger',
                                        'cancelado' => 'secondary'
                                    ];
                                    $color = $estado_colors[$tenant['estado']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $color ?>"><?= ucfirst($tenant['estado']) ?></span>
                                </div>
                            </div>
                            <?php if ($tenant['cuit']): ?>
                            <div class="col-md-6">
                                <label class="text-muted small">CUIT</label>
                                <div class="fw-semibold"><?= htmlspecialchars($tenant['cuit']) ?></div>
                            </div>
                            <?php endif; ?>
                            <div class="col-md-6">
                                <label class="text-muted small">Email de Contacto</label>
                                <div class="fw-semibold"><?= htmlspecialchars($tenant['email_contacto']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small">Teléfono</label>
                                <div class="fw-semibold"><?= htmlspecialchars($tenant['telefono_contacto'] ?: '-') ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small">Fecha de Inicio</label>
                                <div class="fw-semibold"><?= date('d/m/Y', strtotime($tenant['fecha_inicio'])) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small">Fecha de Vencimiento</label>
                                <div class="fw-semibold">
                                    <?= $tenant['fecha_expiracion'] ? date('d/m/Y', strtotime($tenant['fecha_expiracion'])) : '-' ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small">Base de Datos</label>
                                <div class="fw-semibold"><code><?= htmlspecialchars($tenant['bd_nombre']) ?></code></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas de Uso -->
                <?php if ($stats_ok): ?>
                <div class="card stat-card mt-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold">Estadísticas de Uso</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-3">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                                        <i class="bi bi-people-fill"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small">Usuarios</div>
                                        <div class="fw-bold fs-4">
                                            <?= $stats_usuarios['total'] ?> / <?= $tenant['limite_usuarios'] ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                                        <i class="bi bi-box-seam"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small">Productos</div>
                                        <div class="fw-bold fs-4">
                                            <?= number_format($stats_productos['total']) ?> / <?= number_format($tenant['limite_productos']) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                                        <i class="bi bi-shop"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small">Puntos de Venta</div>
                                        <div class="fw-bold fs-4">
                                            <?= $stats_puntos['total'] ?> / <?= $tenant['limite_puntos_venta'] ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                                        <i class="bi bi-cart-check"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small">Total Ventas</div>
                                        <div class="fw-bold fs-4"><?= number_format($stats_ventas['total']) ?></div>
                                        <div class="small text-muted">$<?= number_format($stats_ventas['monto'], 2) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning mt-4">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    No se pudieron cargar las estadísticas del cliente
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-md-4">
                <!-- Administrador -->
                <div class="card stat-card mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold">Administrador</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <i class="bi bi-person-fill me-2"></i>
                            <strong><?= htmlspecialchars($tenant['admin_nombre']) ?></strong>
                        </div>
                        <div class="mb-2">
                            <i class="bi bi-envelope me-2"></i>
                            <?= htmlspecialchars($tenant['admin_email']) ?>
                        </div>
                        <?php if ($tenant['admin_telefono']): ?>
                        <div>
                            <i class="bi bi-telephone me-2"></i>
                            <?= htmlspecialchars($tenant['admin_telefono']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Acciones Rápidas -->
                <div class="card stat-card">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold">Acciones Rápidas</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($tenant['estado'] === 'cancelado'): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Cliente Cancelado</strong><br>
                                Este cliente ha sido eliminado. Solo puedes restaurarlo desde el panel de Restauración.
                            </div>
                            <div class="d-grid">
                                <a href="restaurar_tenant.php" class="btn btn-success">
                                    <i class="bi bi-arrow-counterclockwise me-2"></i>Ir a Restauración
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="d-grid gap-2">
                                <a href="gestionar_usuarios.php?tenant_id=<?= $id ?>" class="btn btn-outline-primary">
                                    <i class="bi bi-people me-2"></i>Gestionar Usuarios
                                </a>
                                <a href="registrar_pago.php?tenant_id=<?= $id ?>" class="btn btn-success">
                                    <i class="bi bi-cash-coin me-2"></i>Registrar Pago
                                </a>
                                <a href="editar_tenant.php?id=<?= $id ?>" class="btn btn-primary">
                                    <i class="bi bi-pencil me-2"></i>Editar Datos
                                </a>
                                <a href="cambiar_plan.php?id=<?= $id ?>" class="btn btn-info text-white">
                                    <i class="bi bi-arrow-up-circle me-2"></i>Cambiar Plan
                                </a>
                                <a href="cambiar_password_tenant.php?id=<?= $id ?>" class="btn btn-warning text-white">
                                    <i class="bi bi-key me-2"></i>Cambiar Contraseña
                                </a>
                                <hr>
                                <a href="eliminar_tenant.php?id=<?= $id ?>" class="btn btn-danger">
                                    <i class="bi bi-trash me-2"></i>Eliminar Cliente
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logs Recientes -->
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card stat-card">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold">Actividad Reciente</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($logs)): ?>
                            <p class="text-muted text-center py-3">No hay actividad registrada</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($logs as $log): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong><?= htmlspecialchars($log['accion']) ?></strong>
                                            <?php if ($log['descripcion']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($log['descripcion']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted"><?= date('d/m H:i', strtotime($log['created_at'])) ?></small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card stat-card">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold">Historial de Pagos</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pagos)): ?>
                            <p class="text-muted text-center py-3">No hay pagos registrados</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Monto</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pagos as $pago): ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($pago['created_at'])) ?></td>
                                            <td>$<?= number_format($pago['monto'], 2) ?></td>
                                            <td>
                                                <?php
                                                $estado_colors = [
                                                    'pendiente' => 'warning',
                                                    'aprobado' => 'success',
                                                    'rechazado' => 'danger'
                                                ];
                                                $color = $estado_colors[$pago['estado']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?= $color ?>"><?= ucfirst($pago['estado']) ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function suspenderTenant() {
            if (confirm('¿Estás seguro de suspender este cliente?\n\nSe bloqueará el acceso a su sistema hasta que lo reactives.')) {
                window.location.href = 'cambiar_estado_tenant.php?id=<?= $id ?>&estado=suspendido';
            }
        }
        
        function activarTenant() {
            if (confirm('¿Activar este cliente?\n\nPodrá acceder nuevamente a su sistema.')) {
                window.location.href = 'cambiar_estado_tenant.php?id=<?= $id ?>&estado=activo';
            }
        }
    </script>
</body>
</html>
