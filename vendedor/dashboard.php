<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();
require_once '../includes/functions.php';
require_once '../includes/tenant_check.php';

require_login();

// Verificar si el tenant está activo (sistema SaaS)
verificarTenantActivo();

// Asegurar que no sea admin
if (is_admin()) {
    redirect('../admin/dashboard.php');
}

$db = Database::getInstance()->getConnection();
$usuario_id = $_SESSION['user_id'];
$pv_id = get_user_punto_venta_id();
$has_pv_column = column_exists($db, 'productos', 'punto_venta_id');

// Obtener estadísticas del vendedor
$stats_ventas = get_sales_stats($db, $usuario_id);

// Total productos (filtrado por punto de venta si aplica)
if ($pv_id && $has_pv_column) {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM productos WHERE activo = 1 AND (punto_venta_id = ? OR punto_venta_id IS NULL)");
    $stmt->execute([$pv_id]);
} else {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM productos WHERE usuario_id = ? AND activo = 1");
    $stmt->execute([$usuario_id]);
}
$total_productos = $stmt->fetch()['total'];

// Productos con stock bajo (filtrado por punto de venta si aplica)
if ($pv_id && $has_pv_column) {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM productos WHERE stock <= stock_minimo AND activo = 1 AND (punto_venta_id = ? OR punto_venta_id IS NULL)");
    $stmt->execute([$pv_id]);
} else {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM productos WHERE usuario_id = ? AND stock <= stock_minimo AND activo = 1");
    $stmt->execute([$usuario_id]);
}
$stock_bajo = $stmt->fetch()['total'];

// Ventas del día
$hoy = date('Y-m-d');
$stmt = $db->prepare("
    SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as monto 
    FROM ventas 
    WHERE usuario_id = ? AND DATE(fecha_venta) = ? AND estado = 'completada'
");
$stmt->execute([$usuario_id, $hoy]);
$ventas_hoy = $stmt->fetch();

// Productos más vendidos (top 5)
$stmt = $db->prepare("
    SELECT p.nombre, p.imagen, SUM(vd.cantidad) as total_vendido
    FROM venta_detalle vd
    JOIN productos p ON vd.producto_id = p.id
    JOIN ventas v ON vd.venta_id = v.id
    WHERE p.usuario_id = ? AND v.estado = 'completada' AND v.fecha_venta >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY p.id
    ORDER BY total_vendido DESC
    LIMIT 5
");
$stmt->execute([$usuario_id]);
$top_productos = $stmt->fetchAll();

// Últimas ventas
$stmt = $db->prepare("
    SELECT * FROM ventas 
    WHERE usuario_id = ? 
    ORDER BY fecha_venta DESC 
    LIMIT 10
");
$stmt->execute([$usuario_id]);
$ultimas_ventas = $stmt->fetchAll();

// Productos con stock bajo
$stmt = $db->prepare("
    SELECT * FROM productos 
    WHERE usuario_id = ? AND stock <= stock_minimo AND activo = 1
    ORDER BY stock ASC
    LIMIT 10
");
$stmt->execute([$usuario_id]);
$productos_stock_bajo = $stmt->fetchAll();

$page_title = 'Dashboard';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-speedometer2 me-2"></i>Mi Dashboard</h2>
        <a href="nueva_venta.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Nueva Venta
        </a>
    </div>

    <!-- Estadísticas principales -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Ventas de Hoy</h6>
                            <h3 class="mb-0"><?php echo format_price($ventas_hoy['monto']); ?></h3>
                            <small><?php echo $ventas_hoy['total']; ?> ventas</small>
                        </div>
                        <div class="stat-icon bg-white bg-opacity-25">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card stat-card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Total Ventas</h6>
                            <h3 class="mb-0"><?php echo format_price($stats_ventas['total_monto']); ?></h3>
                            <small><?php echo number_format($stats_ventas['total_ventas']); ?> ventas</small>
                        </div>
                        <div class="stat-icon bg-white bg-opacity-25">
                            <i class="bi bi-graph-up"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card stat-card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Mis Productos</h6>
                            <h3 class="mb-0"><?php echo $total_productos; ?></h3>
                            <small>Activos</small>
                        </div>
                        <div class="stat-icon bg-white bg-opacity-25">
                            <i class="bi bi-box-seam"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card stat-card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Stock Bajo</h6>
                            <h3 class="mb-0"><?php echo $stock_bajo; ?></h3>
                            <small>Productos</small>
                        </div>
                        <div class="stat-icon bg-white bg-opacity-25">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Productos con stock bajo -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Stock Bajo</h5>
                    <a href="productos.php" class="btn btn-sm btn-outline-primary">Ver Todos</a>
                </div>
                <div class="card-body">
                    <?php if (empty($productos_stock_bajo)): ?>
                        <p class="text-muted">No hay productos con stock bajo</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($productos_stock_bajo as $producto): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex align-items-center">
                                        <?php if ($producto['imagen']): ?>
                                            <img src="../uploads/<?php echo htmlspecialchars($producto['imagen']); ?>" 
                                                 class="product-img me-3" alt="Producto">
                                        <?php else: ?>
                                            <div class="product-img me-3 bg-light d-flex align-items-center justify-content-center">
                                                <i class="bi bi-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($producto['nombre']); ?></h6>
                                            <small class="text-muted">Mínimo: <?php echo $producto['stock_minimo']; ?></small>
                                        </div>
                                        <div class="text-end">
                                            <strong class="text-danger">Stock: <?php echo $producto['stock']; ?></strong>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top productos -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Top 5 Productos Vendidos</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($top_productos)): ?>
                        <p class="text-muted">No hay datos disponibles</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($top_productos as $producto): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex align-items-center">
                                        <?php if ($producto['imagen']): ?>
                                            <img src="../uploads/<?php echo htmlspecialchars($producto['imagen']); ?>" 
                                                 class="product-img me-3" alt="Producto">
                                        <?php else: ?>
                                            <div class="product-img me-3 bg-light d-flex align-items-center justify-content-center">
                                                <i class="bi bi-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($producto['nombre']); ?></h6>
                                            <small class="text-success">Vendido: <?php echo $producto['total_vendido']; ?> unidades</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Últimas ventas -->
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Últimas Ventas</h5>
                    <a href="ventas.php" class="btn btn-sm btn-primary">Ver Todas</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>N° Venta</th>
                                    <th>Cliente</th>
                                    <th>Total</th>
                                    <th>Método</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ultimas_ventas)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No hay ventas registradas</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($ultimas_ventas as $venta): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($venta['numero_venta']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($venta['cliente_nombre'] ?? '-'); ?></td>
                                            <td><strong class="text-success"><?php echo format_price($venta['total']); ?></strong></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo ucfirst($venta['metodo_pago']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo format_date($venta['fecha_venta']); ?></td>
                                            <td>
                                                <?php
                                                $badges = [
                                                    'completada' => 'success',
                                                    'pendiente' => 'warning',
                                                    'cancelada' => 'danger'
                                                ];
                                                $badge_class = $badges[$venta['estado']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $badge_class; ?>">
                                                    <?php echo ucfirst($venta['estado']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
