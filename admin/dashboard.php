<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();
require_once '../includes/functions.php';
require_once '../includes/tenant_check.php';

require_admin();

// Verificar si el tenant está activo (sistema SaaS)
verificarTenantActivo();

$db = Database::getInstance()->getConnection();

// Obtener estadísticas generales
$stats_ventas = get_sales_stats($db);

// Total usuarios
$stmt = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE user_rol = 'vendedor'");
$total_usuarios = $stmt->fetch()['total'];

// Total productos
$stmt = $db->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1");
$total_productos = $stmt->fetch()['total'];

// Productos con stock bajo
$stmt = $db->query("SELECT COUNT(*) as total FROM productos WHERE stock <= stock_minimo AND activo = 1");
$stock_bajo = $stmt->fetch()['total'];

// Ventas del día
$hoy = date('Y-m-d');
$stmt = $db->prepare("
    SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as monto 
    FROM ventas 
    WHERE DATE(fecha_venta) = ? AND estado = 'completada'
");
$stmt->execute([$hoy]);
$ventas_hoy = $stmt->fetch();

// Top 5 productos más vendidos
$stmt = $db->query("
    SELECT p.nombre, p.imagen, SUM(vd.cantidad) as total_vendido, SUM(vd.subtotal) as total_monto
    FROM venta_detalle vd
    JOIN productos p ON vd.producto_id = p.id
    JOIN ventas v ON vd.venta_id = v.id
    WHERE v.estado = 'completada' AND v.fecha_venta >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY p.id
    ORDER BY total_vendido DESC
    LIMIT 5
");
$top_productos = $stmt->fetchAll();

// Últimas ventas
$stmt = $db->query("
    SELECT v.*, u.nombre as vendedor
    FROM ventas v
    JOIN usuarios u ON v.usuario_id = u.id
    ORDER BY v.fecha_venta DESC
    LIMIT 10
");
$ultimas_ventas = $stmt->fetchAll();

// Ventas por mes (últimos 6 meses)
$stmt = $db->query("
    SELECT 
        DATE_FORMAT(fecha_venta, '%Y-%m') as mes,
        COUNT(*) as total_ventas,
        SUM(total) as total_monto
    FROM ventas
    WHERE estado = 'completada' AND fecha_venta >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(fecha_venta, '%Y-%m')
    ORDER BY mes ASC
");
$ventas_mes = $stmt->fetchAll();

$page_title = 'Dashboard';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-speedometer2 me-2"></i>Dashboard Administrativo</h2>
        <div class="text-muted">
            <i class="bi bi-calendar3 me-1"></i>
            <?php echo format_date(date('Y-m-d H:i:s')); ?>
        </div>
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
                            <h6 class="text-white-50 mb-1">Productos</h6>
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
        <!-- Gráfico de ventas -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Ventas de los Últimos 6 Meses</h5>
                </div>
                <div class="card-body">
                    <canvas id="ventasChart" height="80"></canvas>
                </div>
            </div>
        </div>

        <!-- Top productos -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Top 5 Productos</h5>
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
                                            <small class="text-muted">
                                                Vendido: <?php echo $producto['total_vendido']; ?> unidades
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <strong class="text-success">
                                                <?php echo format_price($producto['total_monto']); ?>
                                            </strong>
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
                                    <th>Vendedor</th>
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
                                        <td colspan="7" class="text-center text-muted">No hay ventas registradas</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($ultimas_ventas as $venta): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($venta['numero_venta']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($venta['vendedor']); ?></td>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Gráfico de ventas
const ctx = document.getElementById('ventasChart');
if (ctx) {
    const data = {
        labels: [<?php echo implode(',', array_map(function($v) { return "'" . $v['mes'] . "'"; }, $ventas_mes)); ?>],
        datasets: [{
            label: 'Ventas (<?php echo get_config($db, 'moneda', 'ARS'); ?>)',
            data: [<?php echo implode(',', array_map(function($v) { return $v['total_monto']; }, $ventas_mes)); ?>],
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            borderColor: 'rgb(13, 110, 253)',
            borderWidth: 2,
            tension: 0.4,
            fill: true
        }]
    };

    new Chart(ctx, {
        type: 'line',
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>
