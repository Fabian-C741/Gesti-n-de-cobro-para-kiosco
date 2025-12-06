<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();
require_once '../includes/functions.php';

require_admin();

$db = Database::getInstance()->getConnection();

// Filtros
$tipo = $_GET['tipo'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-7 days'));
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

// Logs de actividad
$query = "
    SELECT l.*, u.nombre as usuario_nombre, u.email as usuario_email
    FROM logs_actividad l
    LEFT JOIN usuarios u ON l.usuario_id = u.id
    WHERE DATE(l.fecha) BETWEEN ? AND ?
";

$params = [$fecha_desde, $fecha_hasta];

if (!empty($tipo)) {
    $query .= " AND l.accion = ?";
    $params[] = $tipo;
}

$query .= " ORDER BY l.fecha DESC LIMIT 200";

$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Tipos de acciones para filtro
$tipos_accion = [
    'login' => 'Login',
    'logout' => 'Logout',
    'crear_producto' => 'Crear Producto',
    'editar_producto' => 'Editar Producto',
    'eliminar_producto' => 'Eliminar Producto',
    'crear_venta' => 'Crear Venta',
    'crear_usuario' => 'Crear Usuario',
    'editar_usuario' => 'Editar Usuario',
    'cambio_password' => 'Cambio de Contraseña'
];

$page_title = 'Logs de Actividad';
include 'includes/header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4"><i class="bi bi-clock-history"></i> Logs de Actividad</h2>

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
                    <label class="form-label">Tipo de Acción</label>
                    <select name="tipo" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($tipos_accion as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $tipo == $key ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
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

    <!-- Tabla de logs -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Acción</th>
                            <th>Detalles</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No hay registros</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($log['fecha'])); ?></td>
                                    <td>
                                        <?php if ($log['usuario_nombre']): ?>
                                            <?php echo htmlspecialchars($log['usuario_nombre']); ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($log['usuario_email']); ?></small>
                                        <?php else: ?>
                                            <em>Sistema</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo $tipos_accion[$log['accion']] ?? htmlspecialchars($log['accion']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['detalles']); ?></td>
                                    <td><small><?php echo htmlspecialchars($log['ip_address']); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <small class="text-muted">Mostrando últimos 200 registros</small>
        </div>
    </div>

    <!-- Logs de seguridad -->
    <?php
    $stmt_sec = $db->prepare("
        SELECT * FROM security_logs 
        WHERE DATE(fecha) BETWEEN ? AND ?
        ORDER BY fecha DESC 
        LIMIT 50
    ");
    $stmt_sec->execute([$fecha_desde, $fecha_hasta]);
    $security_logs = $stmt_sec->fetchAll();
    ?>

    <?php if (!empty($security_logs)): ?>
        <div class="card mt-4">
            <div class="card-header bg-warning">
                <h5><i class="bi bi-shield-exclamation"></i> Eventos de Seguridad</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>IP</th>
                                <th>Detalles</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($security_logs as $slog): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($slog['fecha'])); ?></td>
                                    <td>
                                        <span class="badge bg-danger">
                                            <?php echo htmlspecialchars($slog['tipo_evento']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($slog['ip_address']); ?></td>
                                    <td><?php echo htmlspecialchars($slog['detalles']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
