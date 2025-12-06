<?php
require_once '../includes/error_handler.php';
require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();
require_once '../includes/functions.php';
require_once '../includes/session_validator.php';

require_login();
require_admin();

$page_title = 'Reportes por Empleado';

$db = Database::getInstance()->getConnection();

// Filtros
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01'); // Primer día del mes
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$usuario_id = (int)($_GET['usuario_id'] ?? 0);
$punto_venta = $_GET['punto_venta'] ?? '';

// Obtener lista de usuarios (vendedores y cajeros)
$stmt = $db->query("SELECT id, nombre, user_rol, punto_venta FROM usuarios WHERE user_rol IN ('vendedor', 'cajero') AND activo = 1 ORDER BY nombre");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener puntos de venta
$stmt = $db->query("SELECT DISTINCT codigo, nombre FROM puntos_venta WHERE activo = 1 ORDER BY nombre");
$puntos_venta = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construir query con filtros
$where_conditions = ["DATE(v.fecha_venta) BETWEEN ? AND ?"];
$params = [$fecha_desde, $fecha_hasta];

if ($usuario_id > 0) {
    $where_conditions[] = "v.usuario_id = ?";
    $params[] = $usuario_id;
}

if (!empty($punto_venta)) {
    $where_conditions[] = "u.punto_venta = ?";
    $params[] = $punto_venta;
}

$where_clause = implode(' AND ', $where_conditions);

// Estadísticas por empleado
$stmt = $db->prepare("
    SELECT 
        u.id,
        u.nombre,
        u.user_rol,
        u.punto_venta,
        u.sucursal,
        COUNT(v.id) as total_ventas,
        COALESCE(SUM(v.total), 0) as total_vendido,
        COALESCE(AVG(v.total), 0) as ticket_promedio,
        COALESCE(SUM(CASE WHEN v.metodo_pago = 'efectivo' THEN v.total ELSE 0 END), 0) as total_efectivo,
        COALESCE(SUM(CASE WHEN v.metodo_pago = 'tarjeta' THEN v.total ELSE 0 END), 0) as total_tarjeta,
        COALESCE(SUM(CASE WHEN v.metodo_pago = 'transferencia' THEN v.total ELSE 0 END), 0) as total_transferencia,
        MIN(v.fecha_venta) as primera_venta,
        MAX(v.fecha_venta) as ultima_venta
    FROM usuarios u
    LEFT JOIN ventas v ON u.id = v.usuario_id AND $where_clause
    WHERE u.user_rol IN ('vendedor', 'cajero') AND u.activo = 1
    GROUP BY u.id, u.nombre, u.user_rol, u.punto_venta, u.sucursal
    ORDER BY total_vendido DESC
");
$stmt->execute($params);
$estadisticas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totales generales
$total_general_ventas = array_sum(array_column($estadisticas, 'total_ventas'));
$total_general_vendido = array_sum(array_column($estadisticas, 'total_vendido'));

// Top productos por empleado (si hay filtro de empleado)
$top_productos = [];
if ($usuario_id > 0) {
    $stmt = $db->prepare("
        SELECT 
            p.nombre,
            p.codigo,
            SUM(dv.cantidad) as cantidad_vendida,
            COALESCE(SUM(dv.subtotal), 0) as total_vendido
        FROM venta_detalle dv
        JOIN productos p ON dv.producto_id = p.id
        JOIN ventas v ON dv.venta_id = v.id
        WHERE v.usuario_id = ? AND DATE(v.fecha_venta) BETWEEN ? AND ?
        GROUP BY p.id, p.nombre, p.codigo
        ORDER BY cantidad_vendida DESC
        LIMIT 10
    ");
    $stmt->execute([$usuario_id, $fecha_desde, $fecha_hasta]);
    $top_productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-graph-up me-2"></i>Reportes por Empleado</h2>
        <a href="reportes.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver a Reportes
        </a>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Desde</label>
                    <input type="date" name="fecha_desde" class="form-control" value="<?php echo $fecha_desde; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control" value="<?php echo $fecha_hasta; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Empleado</label>
                    <select name="usuario_id" class="form-select">
                        <option value="0">Todos</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $usuario_id == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['nombre']); ?> (<?php echo ucfirst($u['rol']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Punto de Venta</label>
                    <select name="punto_venta" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($puntos_venta as $pv): ?>
                            <option value="<?php echo htmlspecialchars($pv['codigo']); ?>" 
                                    <?php echo $punto_venta == $pv['codigo'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pv['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel me-1"></i> Filtrar
                    </button>
                    <a href="reportes_empleados.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle me-1"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Resumen General -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Ventas</h6>
                    <h3 class="mb-0"><?php echo number_format($total_general_ventas); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Vendido</h6>
                    <h3 class="mb-0">$<?php echo number_format($total_general_vendido, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Ticket Promedio</h6>
                    <h3 class="mb-0">$<?php echo $total_general_ventas > 0 ? number_format($total_general_vendido / $total_general_ventas, 2) : '0.00'; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title">Empleados Activos</h6>
                    <h3 class="mb-0"><?php echo count(array_filter($estadisticas, fn($e) => $e['total_ventas'] > 0)); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Ranking de Empleados -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Ranking de Empleados</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Empleado</th>
                            <th>Rol</th>
                            <th>Punto Venta</th>
                            <th>Ventas</th>
                            <th>Total Vendido</th>
                            <th>Ticket Prom.</th>
                            <th>Efectivo</th>
                            <th>Tarjeta</th>
                            <th>Transfer.</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($estadisticas)): ?>
                        <tr>
                            <td colspan="11" class="text-center text-muted py-4">
                                No hay datos para el período seleccionado
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php 
                        $posicion = 0;
                        foreach ($estadisticas as $empleado): 
                            $posicion++;
                            $badge_color = $posicion == 1 ? 'warning' : ($posicion == 2 ? 'secondary' : ($posicion == 3 ? 'danger' : 'light'));
                        ?>
                        <tr>
                            <td>
                                <?php if ($posicion <= 3): ?>
                                    <span class="badge bg-<?php echo $badge_color; ?>">
                                        <?php echo $posicion; ?>°
                                    </span>
                                <?php else: ?>
                                    <?php echo $posicion; ?>°
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($empleado['nombre']); ?></strong></td>
                            <td>
                                <span class="badge bg-<?php echo $empleado['user_rol'] === 'vendedor' ? 'primary' : 'info'; ?>">
                                    <?php echo ucfirst($empleado['user_rol']); ?>
                                </span>
                            </td>
                            <td><code><?php echo htmlspecialchars($empleado['punto_venta'] ?? 'N/A'); ?></code></td>
                            <td><span class="badge bg-secondary"><?php echo $empleado['total_ventas']; ?></span></td>
                            <td><strong class="text-success">$<?php echo number_format($empleado['total_vendido'], 2); ?></strong></td>
                            <td>$<?php echo number_format($empleado['ticket_promedio'], 2); ?></td>
                            <td><small>$<?php echo number_format($empleado['total_efectivo'], 2); ?></small></td>
                            <td><small>$<?php echo number_format($empleado['total_tarjeta'], 2); ?></small></td>
                            <td><small>$<?php echo number_format($empleado['total_transferencia'], 2); ?></small></td>
                            <td>
                                <a href="?fecha_desde=<?php echo $fecha_desde; ?>&fecha_hasta=<?php echo $fecha_hasta; ?>&usuario_id=<?php echo $empleado['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary"
                                   title="Ver detalle">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Detalle del empleado seleccionado -->
    <?php if ($usuario_id > 0 && !empty($top_productos)): ?>
        <?php 
        $empleado_seleccionado = array_filter($estadisticas, fn($e) => $e['id'] == $usuario_id);
        $empleado_seleccionado = reset($empleado_seleccionado);
        ?>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-person-badge me-2"></i>
                            Detalle: <?php echo htmlspecialchars($empleado_seleccionado['nombre']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-6">Rol:</dt>
                            <dd class="col-sm-6">
                                <span class="badge bg-<?php echo $empleado_seleccionado['rol'] === 'vendedor' ? 'primary' : 'info'; ?>">
                                    <?php echo ucfirst($empleado_seleccionado['rol']); ?>
                                </span>
                            </dd>

                            <dt class="col-sm-6">Punto de Venta:</dt>
                            <dd class="col-sm-6"><code><?php echo htmlspecialchars($empleado_seleccionado['punto_venta']); ?></code></dd>

                            <dt class="col-sm-6">Sucursal:</dt>
                            <dd class="col-sm-6"><?php echo htmlspecialchars($empleado_seleccionado['sucursal']); ?></dd>

                            <dt class="col-sm-6">Primera Venta:</dt>
                            <dd class="col-sm-6">
                                <?php echo $empleado_seleccionado['primera_venta'] ? date('d/m/Y H:i', strtotime($empleado_seleccionado['primera_venta'])) : 'N/A'; ?>
                            </dd>

                            <dt class="col-sm-6">Última Venta:</dt>
                            <dd class="col-sm-6">
                                <?php echo $empleado_seleccionado['ultima_venta'] ? date('d/m/Y H:i', strtotime($empleado_seleccionado['ultima_venta'])) : 'N/A'; ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-star me-2"></i>Top 10 Productos Vendidos</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th class="text-center">Cant.</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_productos as $producto): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($producto['nombre']); ?>
                                            <?php if ($producto['codigo']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($producto['codigo']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info"><?php echo $producto['cantidad_vendida']; ?></span>
                                        </td>
                                        <td class="text-end">
                                            <strong>$<?php echo number_format($producto['total_vendido'], 2); ?></strong>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php include 'includes/footer.php'; ?>
