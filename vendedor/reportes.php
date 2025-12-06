<?php
require_once '../config/config.php';
require_once '../includes/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/functions.php';
require_once '../includes/session_validator.php';
require_once '../includes/error_handler.php';

require_login();
limpiar_sesion_corrupta();

$page_title = 'Reportes de Ventas';
$db = Database::getInstance()->getConnection();

// Filtros
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01'); // Primer día del mes
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d'); // Hoy
$usuario_id = $_SESSION['user_id'];

// Total de ventas en el período
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_ventas,
        COALESCE(SUM(total), 0) as monto_total,
        COALESCE(AVG(total), 0) as promedio_venta
    FROM ventas 
    WHERE usuario_id = ? 
    AND DATE(fecha_venta) BETWEEN ? AND ?
    AND estado = 'completada'
");
$stmt->execute([$usuario_id, $fecha_desde, $fecha_hasta]);
$resumen = $stmt->fetch(PDO::FETCH_ASSOC);

// Ventas por día
$stmt = $db->prepare("
    SELECT 
        DATE(fecha_venta) as fecha,
        COUNT(*) as cantidad,
        SUM(total) as total
    FROM ventas 
    WHERE usuario_id = ? 
    AND DATE(fecha_venta) BETWEEN ? AND ?
    AND estado = 'completada'
    GROUP BY DATE(fecha_venta)
    ORDER BY fecha DESC
");
$stmt->execute([$usuario_id, $fecha_desde, $fecha_hasta]);
$ventas_por_dia = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ventas por método de pago
$stmt = $db->prepare("
    SELECT 
        metodo_pago,
        COUNT(*) as cantidad,
        SUM(total) as total
    FROM ventas 
    WHERE usuario_id = ? 
    AND DATE(fecha_venta) BETWEEN ? AND ?
    AND estado = 'completada'
    GROUP BY metodo_pago
");
$stmt->execute([$usuario_id, $fecha_desde, $fecha_hasta]);
$ventas_por_metodo = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Productos más vendidos
$stmt = $db->prepare("
    SELECT 
        p.nombre,
        SUM(vd.cantidad) as cantidad_vendida,
        SUM(vd.subtotal) as total_vendido
    FROM venta_detalle vd
    JOIN productos p ON vd.producto_id = p.id
    JOIN ventas v ON vd.venta_id = v.id
    WHERE v.usuario_id = ? 
    AND DATE(v.fecha_venta) BETWEEN ? AND ?
    AND v.estado = 'completada'
    GROUP BY p.id
    ORDER BY cantidad_vendida DESC
    LIMIT 10
");
$stmt->execute([$usuario_id, $fecha_desde, $fecha_hasta]);
$productos_top = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-bar-chart-line me-2"></i><?php echo $page_title; ?></h2>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Desde</label>
                    <input type="date" name="fecha_desde" class="form-control" value="<?php echo $fecha_desde; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control" value="<?php echo $fecha_hasta; ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-2"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Resumen de ventas -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Total Ventas</h6>
                            <h2 class="mb-0"><?php echo number_format($resumen['total_ventas']); ?></h2>
                        </div>
                        <i class="bi bi-receipt" style="font-size: 3rem; opacity: 0.5;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Monto Total</h6>
                            <h2 class="mb-0">$<?php echo number_format($resumen['monto_total'], 2); ?></h2>
                        </div>
                        <i class="bi bi-currency-dollar" style="font-size: 3rem; opacity: 0.5;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Promedio por Venta</h6>
                            <h2 class="mb-0">$<?php echo number_format($resumen['promedio_venta'], 2); ?></h2>
                        </div>
                        <i class="bi bi-graph-up" style="font-size: 3rem; opacity: 0.5;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Ventas por día -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Ventas por Día</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th class="text-center">Cantidad</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ventas_por_dia as $dia): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($dia['fecha'])); ?></td>
                                    <td class="text-center"><span class="badge bg-primary"><?php echo $dia['cantidad']; ?></span></td>
                                    <td class="text-end fw-bold text-success">$<?php echo number_format($dia['total'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($ventas_por_dia)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No hay ventas en este período</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Métodos de pago y productos top -->
        <div class="col-md-4">
            <!-- Métodos de pago -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-credit-card me-2"></i>Por Método de Pago</h6>
                </div>
                <div class="card-body">
                    <?php foreach ($ventas_por_metodo as $metodo): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-capitalize"><?php echo $metodo['metodo_pago']; ?></span>
                            <strong>$<?php echo number_format($metodo['total'], 2); ?></strong>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <?php 
                            $porcentaje = ($metodo['total'] / $resumen['monto_total']) * 100;
                            ?>
                            <div class="progress-bar bg-primary" style="width: <?php echo $porcentaje; ?>%"></div>
                        </div>
                        <small class="text-muted"><?php echo $metodo['cantidad']; ?> ventas (<?php echo number_format($porcentaje, 1); ?>%)</small>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($ventas_por_metodo)): ?>
                    <p class="text-muted text-center mb-0">Sin datos</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Productos más vendidos -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-star me-2"></i>Top Productos</h6>
                </div>
                <div class="card-body">
                    <?php foreach ($productos_top as $index => $prod): ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between">
                            <small class="text-truncate me-2"><?php echo $index + 1; ?>. <?php echo htmlspecialchars($prod['nombre']); ?></small>
                            <small><strong><?php echo $prod['cantidad_vendida']; ?></strong></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($productos_top)): ?>
                    <p class="text-muted text-center mb-0">Sin datos</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
