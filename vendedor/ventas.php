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

$page_title = 'Mis Ventas';

$db = Database::getInstance()->getConnection();

// Obtener ventas del usuario
$stmt = $db->prepare("
    SELECT v.*, 
           COUNT(dv.id) as total_items,
           SUM(dv.cantidad) as total_productos
    FROM ventas v
    LEFT JOIN venta_detalle dv ON v.id = dv.venta_id
    WHERE v.usuario_id = ?
    GROUP BY v.id
    ORDER BY v.fecha_venta DESC
    LIMIT 100
");
$stmt->execute([$_SESSION['user_id']]);
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales
$total_ventas = count($ventas);
$total_efectivo = 0;
$total_tarjeta = 0;
$total_transferencia = 0;

foreach ($ventas as $venta) {
    switch ($venta['metodo_pago']) {
        case 'efectivo':
            $total_efectivo += $venta['total'];
            break;
        case 'tarjeta':
            $total_tarjeta += $venta['total'];
            break;
        case 'transferencia':
            $total_transferencia += $venta['total'];
            break;
    }
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4"><i class="bi bi-receipt me-2"></i>Mis Ventas</h2>
    
    <!-- Tarjetas de resumen -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Ventas</h6>
                    <h3 class="mb-0"><?php echo $total_ventas; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Efectivo</h6>
                    <h3 class="mb-0">$<?php echo number_format($total_efectivo, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Tarjeta</h6>
                    <h3 class="mb-0">$<?php echo number_format($total_tarjeta, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title">Transferencia</h6>
                    <h3 class="mb-0">$<?php echo number_format($total_transferencia, 2); ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tabla de ventas -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Ticket</th>
                            <th>Fecha</th>
                            <th>Items</th>
                            <th>Productos</th>
                            <th>Total</th>
                            <th>Método Pago</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ventas)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                No hay ventas registradas
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($ventas as $venta): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad($venta['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha_venta'])); ?></td>
                            <td><span class="badge bg-secondary"><?php echo $venta['total_items']; ?></span></td>
                            <td><span class="badge bg-info"><?php echo $venta['total_productos']; ?></span></td>
                            <td><strong>$<?php echo number_format($venta['total'], 2); ?></strong></td>
                            <td>
                                <?php
                                $badge_color = 'secondary';
                                $icon = 'cash';
                                switch ($venta['metodo_pago']) {
                                    case 'efectivo':
                                        $badge_color = 'success';
                                        $icon = 'cash';
                                        break;
                                    case 'tarjeta':
                                        $badge_color = 'info';
                                        $icon = 'credit-card';
                                        break;
                                    case 'transferencia':
                                        $badge_color = 'warning';
                                        $icon = 'arrow-left-right';
                                        break;
                                }
                                ?>
                                <span class="badge bg-<?php echo $badge_color; ?>">
                                    <i class="bi bi-<?php echo $icon; ?> me-1"></i><?php echo ucfirst($venta['metodo_pago']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="imprimir_ticket.php?id=<?php echo $venta['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary" 
                                   onclick="abrirTicket(event, this.href)"
                                   title="Imprimir ticket">
                                    <i class="bi bi-printer"></i>
                                </a>
                                <button class="btn btn-sm btn-outline-info" 
                                        onclick="verDetalle(<?php echo $venta['id']; ?>)"
                                        title="Ver detalle">
                                    <i class="bi bi-eye"></i>
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
                <h5 class="modal-title"><i class="bi bi-list-ul me-2"></i>Detalle de Venta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleContent">
                <div class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
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
                        <small class="text-muted">${data.venta.fecha_venta}</small>
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

function abrirTicket(event, url) {
    if (event) event.preventDefault();
    const width = 350;
    const height = 600;
    const left = (screen.width / 2) - (width / 2);
    const top = (screen.height / 2) - (height / 2);
    window.open(url, 'Ticket', `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`);
}
</script>

<?php include 'includes/footer.php'; ?>
