<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();
require_once '../includes/functions.php';

require_admin();

$db = Database::getInstance()->getConnection();

// Periodo seleccionado
$periodo = $_GET['periodo'] ?? 'mes';

switch($periodo) {
    case 'dia':
        $fecha_desde = date('Y-m-d');
        $fecha_hasta = date('Y-m-d');
        break;
    case 'semana':
        $fecha_desde = date('Y-m-d', strtotime('-7 days'));
        $fecha_hasta = date('Y-m-d');
        break;
    case 'año':
        $fecha_desde = date('Y-01-01');
        $fecha_hasta = date('Y-12-31');
        break;
    default: // mes
        $fecha_desde = date('Y-m-01');
        $fecha_hasta = date('Y-m-t');
}

// Ventas por día
$stmt = $db->prepare("
    SELECT DATE(v.fecha_venta) as fecha,
           SUM(vd.cantidad * vd.precio_unitario) as total
    FROM ventas v
    INNER JOIN venta_detalle vd ON v.id = vd.venta_id
    WHERE DATE(v.fecha_venta) BETWEEN ? AND ?
    GROUP BY DATE(v.fecha_venta)
    ORDER BY fecha ASC
");
$stmt->execute([$fecha_desde, $fecha_hasta]);
$ventas_por_dia = $stmt->fetchAll();

// Productos más vendidos
$stmt = $db->prepare("
    SELECT p.nombre, SUM(vd.cantidad) as cantidad_vendida,
           SUM(vd.cantidad * vd.precio_unitario) as total_vendido
    FROM venta_detalle vd
    INNER JOIN productos p ON vd.producto_id = p.id
    INNER JOIN ventas v ON vd.venta_id = v.id
    WHERE DATE(v.fecha_venta) BETWEEN ? AND ?
    GROUP BY p.id
    ORDER BY cantidad_vendida DESC
    LIMIT 10
");
$stmt->execute([$fecha_desde, $fecha_hasta]);
$productos_top = $stmt->fetchAll();

// Ventas por vendedor
$stmt = $db->prepare("
    SELECT u.nombre, COUNT(v.id) as total_ventas,
           SUM(vd.cantidad * vd.precio_unitario) as total_vendido
    FROM ventas v
    INNER JOIN usuarios u ON v.usuario_id = u.id
    INNER JOIN venta_detalle vd ON v.id = vd.venta_id
    WHERE DATE(v.fecha_venta) BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY total_vendido DESC
");
$stmt->execute([$fecha_desde, $fecha_hasta]);
$ventas_por_vendedor = $stmt->fetchAll();

$page_title = 'Reportes';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-graph-up"></i> Reportes y Estadísticas</h2>
        <div class="btn-group">
            <a href="?periodo=dia" class="btn btn-sm <?php echo $periodo == 'dia' ? 'btn-primary' : 'btn-outline-primary'; ?>">Hoy</a>
            <a href="?periodo=semana" class="btn btn-sm <?php echo $periodo == 'semana' ? 'btn-primary' : 'btn-outline-primary'; ?>">Semana</a>
            <a href="?periodo=mes" class="btn btn-sm <?php echo $periodo == 'mes' ? 'btn-primary' : 'btn-outline-primary'; ?>">Mes</a>
            <a href="?periodo=año" class="btn btn-sm <?php echo $periodo == 'año' ? 'btn-primary' : 'btn-outline-primary'; ?>">Año</a>
        </div>
    </div>

    <!-- Gráfico de ventas -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Ventas Diarias</h5>
        </div>
        <div class="card-body">
            <canvas id="chartVentas" height="80"></canvas>
        </div>
    </div>

    <div class="row">
        <!-- Productos más vendidos -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5>Top 10 Productos</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos_top as $prod): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($prod['nombre']); ?></td>
                                        <td><?php echo $prod['cantidad_vendida']; ?></td>
                                        <td>$<?php echo number_format($prod['total_vendido'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ventas por vendedor -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5>Rendimiento por Vendedor</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Vendedor</th>
                                    <th>Ventas</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ventas_por_vendedor as $vend): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($vend['nombre']); ?></td>
                                        <td><?php echo $vend['total_ventas']; ?></td>
                                        <td>$<?php echo number_format($vend['total_vendido'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
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
const ctx = document.getElementById('chartVentas');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($ventas_por_dia, 'fecha')); ?>,
        datasets: [{
            label: 'Ventas ($)',
            data: <?php echo json_encode(array_column($ventas_por_dia, 'total')); ?>,
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true
    }
});
</script>

<?php include 'includes/footer.php'; ?>
