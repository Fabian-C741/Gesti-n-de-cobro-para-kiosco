<?php
session_start();
require_once 'config_superadmin.php';
verificarSuperAdmin();

// Obtener estadísticas generales (excluir cancelados)
$stmt = $conn_master->query("
    SELECT 
        COUNT(*) as total_tenants,
        SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) as activos,
        SUM(CASE WHEN estado = 'suspendido' THEN 1 ELSE 0 END) as suspendidos,
        SUM(CASE WHEN estado = 'vencido' THEN 1 ELSE 0 END) as vencidos
    FROM tenants
    WHERE estado != 'cancelado'
");
$stats = $stmt->fetch();

// Obtener ingresos del mes
$stmt = $conn_master->query("
    SELECT 
        SUM(monto) as total_mes,
        COUNT(*) as pagos_mes
    FROM tenant_pagos
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
    AND estado = 'aprobado'
");
$ingresos = $stmt->fetch();

// Obtener tenants recientes (excluir cancelados)
$stmt = $conn_master->query("
    SELECT id, nombre, dominio, plan, estado, fecha_inicio, fecha_expiracion
    FROM tenants
    WHERE estado != 'cancelado'
    ORDER BY created_at DESC
    LIMIT 10
");
$tenants_recientes = $stmt->fetchAll();

// Obtener pagos pendientes
$stmt = $conn_master->query("
    SELECT p.id, p.monto, p.created_at, t.nombre as tenant_nombre
    FROM tenant_pagos p
    JOIN tenants t ON p.tenant_id = t.id
    WHERE p.estado = 'pendiente'
    ORDER BY p.created_at DESC
    LIMIT 5
");
$pagos_pendientes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Super Admin</title>
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
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }
        .badge-plan {
            padding: 5px 12px;
            font-weight: 600;
            border-radius: 20px;
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
                        <a class="nav-link active" href="dashboard.php">
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
                        <a class="nav-link" href="configuracion_sesiones.php">
                            <i class="bi bi-clock-history me-1"></i>Sesiones
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="auditoria_seguridad.php">
                            <i class="bi bi-shield-check me-1"></i>Seguridad
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
            <div class="col-12">
                <h2 class="fw-bold">
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard General
                </h2>
                <p class="text-muted">Vista general del sistema SaaS</p>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1">Total Clientes</p>
                                <h3 class="mb-0 fw-bold"><?= $stats['total_tenants'] ?></h3>
                            </div>
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-building"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1">Activos</p>
                                <h3 class="mb-0 fw-bold text-success"><?= $stats['activos'] ?></h3>
                            </div>
                            <div class="stat-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1">Ingresos Mes</p>
                                <h3 class="mb-0 fw-bold text-info">$<?= number_format($ingresos['total_mes'] ?? 0, 2) ?></h3>
                            </div>
                            <div class="stat-icon bg-info bg-opacity-10 text-info">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1">Pagos Pendientes</p>
                                <h3 class="mb-0 fw-bold text-warning"><?= count($pagos_pendientes) ?></h3>
                            </div>
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Clientes Recientes -->
            <div class="col-md-8">
                <div class="card stat-card">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="bi bi-building me-2"></i>Clientes Recientes
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Cliente</th>
                                        <th>Dominio</th>
                                        <th>Plan</th>
                                        <th>Estado</th>
                                        <th>Vencimiento</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tenants_recientes as $tenant): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($tenant['nombre']) ?></td>
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
                                            <span class="badge badge-plan bg-<?= $color ?>"><?= ucfirst($tenant['plan']) ?></span>
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
                                            <span class="badge bg-<?= $color ?>"><?= ucfirst($tenant['estado']) ?></span>
                                        </td>
                                        <td><?= $tenant['fecha_expiracion'] ? date('d/m/Y', strtotime($tenant['fecha_expiracion'])) : '-' ?></td>
                                        <td>
                                            <a href="ver_tenant.php?id=<?= $tenant['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="tenants.php" class="btn btn-outline-primary">
                                Ver Todos los Clientes <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagos Pendientes -->
            <div class="col-md-4">
                <div class="card stat-card">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="bi bi-clock-history me-2"></i>Pagos Pendientes
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pagos_pendientes)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-check-circle" style="font-size: 3rem;"></i>
                                <p class="mt-2">No hay pagos pendientes</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($pagos_pendientes as $pago): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($pago['tenant_nombre']) ?></h6>
                                            <small class="text-muted">
                                                <?= date('d/m/Y H:i', strtotime($pago['created_at'])) ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-warning">$<?= number_format($pago['monto'], 2) ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="pagos.php?estado=pendiente" class="btn btn-sm btn-outline-warning w-100">
                                    Ver Todos
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Acceso Rápido -->
                <div class="card stat-card mt-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="bi bi-lightning-fill me-2"></i>Acceso Rápido
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="crear_tenant.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Nuevo Cliente
                            </a>
                            <a href="tenants.php" class="btn btn-outline-primary">
                                <i class="bi bi-building me-2"></i>Gestionar Clientes
                            </a>
                            <a href="pagos.php" class="btn btn-outline-info">
                                <i class="bi bi-cash-coin me-2"></i>Registrar Pago
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
