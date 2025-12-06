<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();
require_once '../includes/functions.php';

require_admin();

$db = Database::getInstance()->getConnection();

// Filtros
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$vendedor_id = $_GET['vendedor'] ?? '';

// Obtener ventas
$query = "
    SELECT v.*, u.nombre as vendedor_nombre,
           (SELECT SUM(vd.cantidad * vd.precio_unitario) 
            FROM venta_detalle vd 
            WHERE vd.venta_id = v.id) as total
    FROM ventas v
    INNER JOIN usuarios u ON v.usuario_id = u.id
    WHERE DATE(v.fecha_venta) BETWEEN ? AND ?
";

$params = [$fecha_desde, $fecha_hasta];

if (!empty($vendedor_id)) {
    $query .= " AND v.usuario_id = ?";
    $params[] = $vendedor_id;
}

$query .= " ORDER BY v.fecha_venta DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$ventas = $stmt->fetchAll();

// Obtener vendedores para filtro
$stmt_vendedores = $db->query("SELECT id, nombre FROM usuarios WHERE user_rol = 'vendedor' ORDER BY nombre");
$vendedores = $stmt_vendedores->fetchAll();

$page_title = 'Ventas';
include 'includes/header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4"><i class="bi bi-cart-check"></i> Registro de Ventas</h2>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Fecha Desde</label>
                    <input type="date" name="fecha_desde" class="form-control" value="<?php echo $fecha_desde; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control" value="<?php echo $fecha_hasta; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Vendedor</label>
                    <select name="vendedor" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($vendedores as $v): ?>
                            <option value="<?php echo $v['id']; ?>" <?php echo $vendedor_id == $v['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($v['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de ventas -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Vendedor</th>
                            <th>Total</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ventas)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No hay ventas registradas</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ventas as $venta): ?>
                                <tr>
                                    <td>#<?php echo $venta['id']; ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha_venta'])); ?></td>
                                    <td><?php echo htmlspecialchars($venta['vendedor_nombre']); ?></td>
                                    <td>$<?php echo number_format($venta['total'], 2); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="verDetalle(<?php echo $venta['id']; ?>)">
                                            <i class="bi bi-eye"></i> Ver
                                        </button>
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

<!-- Modal Detalle -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de Venta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleContent">
                <div class="text-center">
                    <div class="spinner-border" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function verDetalle(ventaId) {
    const modal = new bootstrap.Modal(document.getElementById('modalDetalle'));
    const content = document.getElementById('detalleContent');
    
    content.innerHTML = '<div class="text-center py-4"><div class="spinner-border" role="status"></div></div>';
    modal.show();
    
    fetch(`../api/venta_detalle.php?id=${ventaId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = `
                    <div class="mb-3">
                        <strong>Ticket #${String(data.venta.id).padStart(6, '0')}</strong><br>
                        <small class="text-muted">${data.venta.fecha_venta}</small><br>
                        <small class="text-muted">Vendedor: ${data.venta.vendedor_nombre || 'N/A'}</small>
                    </div>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Cant.</th>
                                <th class="text-end">Precio</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                data.items.forEach(item => {
                    html += `
                        <tr>
                            <td>${item.producto_nombre}</td>
                            <td class="text-center">${item.cantidad}</td>
                            <td class="text-end">$${parseFloat(item.precio_unitario).toFixed(2)}</td>
                            <td class="text-end">$${parseFloat(item.subtotal).toFixed(2)}</td>
                        </tr>
                    `;
                });
                
                html += `
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">TOTAL:</th>
                                <th class="text-end">$${parseFloat(data.venta.total).toFixed(2)}</th>
                            </tr>
                        </tfoot>
                    </table>
                    <div class="mt-3">
                        <strong>Método de pago:</strong> ${data.venta.metodo_pago.toUpperCase()}
                    </div>
                `;
                
                content.innerHTML = html;
            } else {
                content.innerHTML = '<div class="alert alert-danger">Error al cargar el detalle</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Error de conexión</div>';
        });
}
</script>

<?php include 'includes/footer.php'; ?>
