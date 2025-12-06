<?php
session_start();
require_once 'config_superadmin.php';
verificarSuperAdmin();

// Filtros
$filtro_estado = $_GET['estado'] ?? '';
$filtro_tenant = $_GET['tenant_id'] ?? '';

// Construir query
$where = ["1=1"];
$params = [];

if ($filtro_estado) {
    $where[] = "p.estado = ?";
    $params[] = $filtro_estado;
}
if ($filtro_tenant) {
    $where[] = "p.tenant_id = ?";
    $params[] = $filtro_tenant;
}

$sql = "
    SELECT p.*, t.nombre as tenant_nombre, t.dominio,
           sa.nombre as procesado_por_nombre
    FROM tenant_pagos p
    JOIN tenants t ON p.tenant_id = t.id
    LEFT JOIN super_admins sa ON p.procesado_por = sa.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY p.created_at DESC
";
$stmt = $conn_master->prepare($sql);
$stmt->execute($params);
$pagos = $stmt->fetchAll();

// Obtener lista de tenants para filtro
$tenants_list = $conn_master->query("SELECT id, nombre FROM tenants ORDER BY nombre")->fetchAll();

// Procesar registro de nuevo pago
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_pago'])) {
    try {
        $tenant_id = $_POST['tenant_id'];
        $monto = $_POST['monto'];
        $metodo_pago = $_POST['metodo_pago'];
        $periodo_desde = $_POST['periodo_desde'];
        $periodo_hasta = $_POST['periodo_hasta'];
        $notas = $_POST['notas'] ?? '';
        
        $stmt = $conn_master->prepare("
            INSERT INTO tenant_pagos (
                tenant_id, monto, metodo_pago, estado,
                periodo_desde, periodo_hasta, notas, procesado_por
            ) VALUES (?, ?, ?, 'aprobado', ?, ?, ?, ?)
        ");
        $stmt->execute([
            $tenant_id, $monto, $metodo_pago,
            $periodo_desde, $periodo_hasta, $notas,
            $_SESSION['super_admin_id']
        ]);
        
        // Extender fecha de vencimiento del tenant
        $stmt = $conn_master->prepare("
            UPDATE tenants 
            SET fecha_expiracion = ?, estado = 'activo'
            WHERE id = ?
        ");
        $stmt->execute([$periodo_hasta, $tenant_id]);
        
        registrarLog($tenant_id, 'pago_registrado', "Pago de $$monto registrado");
        
        $_SESSION['mensaje'] = 'Pago registrado exitosamente';
        header('Location: pagos.php');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pagos - Super Admin</title>
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
                        <a class="nav-link active" href="pagos.php">
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
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i><?= $_SESSION['mensaje'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['mensaje']); ?>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-6">
                <h2 class="fw-bold">
                    <i class="bi bi-cash-coin me-2"></i>Gestión de Pagos
                </h2>
                <p class="text-muted">Administra los pagos y suscripciones de clientes</p>
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#modalRegistrarPago">
                    <i class="bi bi-plus-circle me-2"></i>Registrar Pago
                </button>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card stat-card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Cliente</label>
                        <select class="form-select" name="tenant_id">
                            <option value="">Todos los clientes</option>
                            <?php foreach ($tenants_list as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= $filtro_tenant == $t['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="estado">
                            <option value="">Todos</option>
                            <option value="pendiente" <?= $filtro_estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="aprobado" <?= $filtro_estado === 'aprobado' ? 'selected' : '' ?>>Aprobado</option>
                            <option value="rechazado" <?= $filtro_estado === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i>Filtrar
                            </button>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <a href="pagos.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i>Limpiar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de Pagos -->
        <div class="card stat-card">
            <div class="card-body">
                <?php if (empty($pagos)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size: 4rem;"></i>
                        <p class="mt-3">No se encontraron pagos</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Monto</th>
                                    <th>Método</th>
                                    <th>Período</th>
                                    <th>Estado</th>
                                    <th>Procesado Por</th>
                                    <th>Notas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pagos as $pago): ?>
                                <tr>
                                    <td><strong>#<?= $pago['id'] ?></strong></td>
                                    <td><?= date('d/m/Y H:i', strtotime($pago['created_at'])) ?></td>
                                    <td>
                                        <a href="ver_tenant.php?id=<?= $pago['tenant_id'] ?>">
                                            <?= htmlspecialchars($pago['tenant_nombre']) ?>
                                        </a>
                                        <br><small class="text-muted"><?= htmlspecialchars($pago['dominio']) ?></small>
                                    </td>
                                    <td class="fw-bold text-success">$<?= number_format($pago['monto'], 2) ?></td>
                                    <td>
                                        <?php
                                        $metodos = [
                                            'efectivo' => '<i class="bi bi-cash"></i> Efectivo',
                                            'transferencia' => '<i class="bi bi-bank"></i> Transferencia',
                                            'mercadopago' => '<i class="bi bi-credit-card"></i> MercadoPago',
                                            'stripe' => '<i class="bi bi-credit-card"></i> Stripe'
                                        ];
                                        echo $metodos[$pago['metodo_pago']] ?? ucfirst($pago['metodo_pago']);
                                        ?>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($pago['periodo_desde'])) ?>
                                        <i class="bi bi-arrow-right"></i>
                                        <?= date('d/m/Y', strtotime($pago['periodo_hasta'])) ?>
                                    </td>
                                    <td>
                                        <?php
                                        $estado_colors = [
                                            'pendiente' => 'warning',
                                            'aprobado' => 'success',
                                            'rechazado' => 'danger'
                                        ];
                                        $color = $estado_colors[$pago['estado']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $color ?>">
                                            <?= ucfirst($pago['estado']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($pago['procesado_por_nombre'] ?? 'Sistema') ?></small>
                                    </td>
                                    <td>
                                        <?php if ($pago['notas']): ?>
                                            <small><?= htmlspecialchars($pago['notas']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        <p class="text-muted mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Total de registros: <strong><?= count($pagos) ?></strong>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Registrar Pago -->
    <div class="modal fade" id="modalRegistrarPago" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-plus-circle me-2"></i>Registrar Nuevo Pago
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Cliente <span class="text-danger">*</span></label>
                            <select class="form-select" name="tenant_id" required id="selectTenant">
                                <option value="">Seleccionar cliente...</option>
                                <?php
                                $stmt = $conn_master->query("SELECT id, nombre, plan, precio_mensual FROM tenants WHERE estado != 'cancelado' ORDER BY nombre");
                                while ($t = $stmt->fetch()):
                                ?>
                                    <option value="<?= $t['id'] ?>" data-precio="<?= $t['precio_mensual'] ?>">
                                        <?= htmlspecialchars($t['nombre']) ?> (<?= ucfirst($t['plan']) ?> - $<?= $t['precio_mensual'] ?>/mes)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Monto <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="monto" required step="0.01" id="inputMonto">
                            </div>
                            <small class="text-muted">Se completará automáticamente según el plan</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Método de Pago <span class="text-danger">*</span></label>
                            <select class="form-select" name="metodo_pago" required>
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia" selected>Transferencia Bancaria</option>
                                <option value="mercadopago">MercadoPago</option>
                                <option value="stripe">Stripe</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Período Desde <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="periodo_desde" required 
                                       value="<?= date('Y-m-d') ?>" id="periodoDesde">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Período Hasta <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="periodo_hasta" required 
                                       value="<?= date('Y-m-d', strtotime('+1 month')) ?>" id="periodoHasta">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notas</label>
                            <textarea class="form-control" name="notas" rows="2" placeholder="Observaciones adicionales..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="registrar_pago" class="btn btn-success">
                            <i class="bi bi-check-circle me-1"></i>Registrar Pago
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-completar monto según cliente seleccionado
        document.getElementById('selectTenant').addEventListener('change', function() {
            const precio = this.options[this.selectedIndex].dataset.precio;
            if (precio) {
                document.getElementById('inputMonto').value = precio;
            }
            
            // Actualizar período hasta (1 mes desde período desde)
            const desde = document.getElementById('periodoDesde').value;
            if (desde) {
                const fecha = new Date(desde);
                fecha.setMonth(fecha.getMonth() + 1);
                document.getElementById('periodoHasta').value = fecha.toISOString().split('T')[0];
            }
        });
        
        // Actualizar período hasta cuando cambia período desde
        document.getElementById('periodoDesde').addEventListener('change', function() {
            const fecha = new Date(this.value);
            fecha.setMonth(fecha.getMonth() + 1);
            document.getElementById('periodoHasta').value = fecha.toISOString().split('T')[0];
        });
    </script>
</body>
</html>
