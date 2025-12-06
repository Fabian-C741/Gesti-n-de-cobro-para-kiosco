<?php
require_once '../includes/error_handler.php';
require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();
require_once '../includes/functions.php';
require_once '../includes/session_validator.php';

require_login();

// Verificar que sea cajero
if ($_SESSION['user_rol'] !== 'cajero') {
    header('Location: ../index.php');
    exit;
}

$page_title = 'Mis Ventas';
$db = Database::getInstance()->getConnection();

// Obtener ventas del cajero
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

// Calcular totales del día
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_ventas,
        COALESCE(SUM(total), 0) as total_vendido
    FROM ventas
    WHERE usuario_id = ? AND DATE(fecha_venta) = CURDATE()
");
$stmt->execute([$_SESSION['user_id']]);
$stats_hoy = $stmt->fetch(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4"><i class="bi bi-receipt-cutoff me-2"></i>Mis Ventas</h2>
    
    <!-- Estadísticas del día -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Ventas Hoy</h6>
                    <h2 class="mb-0"><?php echo $stats_hoy['total_ventas']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Vendido Hoy</h6>
                    <h2 class="mb-0">$<?php echo number_format($stats_hoy['total_vendido'], 2); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Histórico</h6>
                    <h2 class="mb-0"><?php echo count($ventas); ?></h2>
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
                            <th>Total</th>
                            <th>Método Pago</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ventas)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                No hay ventas registradas
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($ventas as $venta): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad($venta['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha_venta'])); ?></td>
                            <td><span class="badge bg-info"><?php echo $venta['total_items']; ?></span></td>
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
                                <a href="../vendedor/imprimir_ticket.php?id=<?php echo $venta['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary" 
                                   onclick="abrirTicket(event, this.href)"
                                   title="Reimprimir ticket">
                                    <i class="bi bi-printer"></i>
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
</div>

<script>
function abrirTicket(event, url) {
    event.preventDefault();
    const width = 350;
    const height = 600;
    const left = (screen.width / 2) - (width / 2);
    const top = (screen.height / 2) - (height / 2);
    window.open(url, 'Ticket', `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`);
}
</script>

<?php include 'includes/footer.php'; ?>
