<?php
session_start();
require_once 'config_superadmin.php';
verificarSuperAdmin();

// Filtros
$filtro_tenant = $_GET['tenant_id'] ?? '';
$filtro_accion = $_GET['accion'] ?? '';
$limit = $_GET['limit'] ?? 50;

// Construir query
$where = ["1=1"];
$params = [];

if ($filtro_tenant) {
    $where[] = "l.tenant_id = ?";
    $params[] = $filtro_tenant;
}
if ($filtro_accion) {
    $where[] = "l.accion LIKE ?";
    $params[] = "%$filtro_accion%";
}

$sql = "
    SELECT l.*, t.nombre as tenant_nombre, t.dominio
    FROM tenant_logs l
    JOIN tenants t ON l.tenant_id = t.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY l.created_at DESC
    LIMIT ?
";
$stmt = $conn_master->prepare($sql);
$params[] = (int)$limit;
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Obtener lista de tenants para filtro
$tenants_list = $conn_master->query("SELECT id, nombre FROM tenants ORDER BY nombre")->fetchAll();

// Obtener tipos de acciones únicas
$acciones = $conn_master->query("
    SELECT DISTINCT accion FROM tenant_logs ORDER BY accion
")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Actividad - Super Admin</title>
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
        .log-item {
            padding: 12px;
            border-left: 3px solid #e0e0e0;
            margin-bottom: 8px;
            border-radius: 5px;
            background: white;
            transition: all 0.2s;
        }
        .log-item:hover {
            border-left-color: #667eea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .log-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
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
                        <a class="nav-link" href="pagos.php">
                            <i class="bi bi-cash-coin me-1"></i>Pagos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="logs.php">
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
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="fw-bold">
                    <i class="bi bi-journal-text me-2"></i>Logs de Actividad
                </h2>
                <p class="text-muted">Registro de todas las acciones realizadas en el sistema</p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card stat-card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
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
                        <label class="form-label">Acción</label>
                        <select class="form-select" name="accion">
                            <option value="">Todas las acciones</option>
                            <?php foreach ($acciones as $accion): ?>
                                <option value="<?= htmlspecialchars($accion) ?>" <?= $filtro_accion === $accion ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($accion) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Registros</label>
                        <select class="form-select" name="limit">
                            <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                            <option value="200" <?= $limit == 200 ? 'selected' : '' ?>>200</option>
                            <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-search me-1"></i>Filtrar
                            </button>
                            <a href="logs.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de Logs -->
        <div class="card stat-card">
            <div class="card-body">
                <?php if (empty($logs)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size: 4rem;"></i>
                        <p class="mt-3">No se encontraron logs de actividad</p>
                    </div>
                <?php else: ?>
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <p class="text-muted mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Mostrando <strong><?= count($logs) ?></strong> registros
                        </p>
                        <button class="btn btn-sm btn-outline-primary" onclick="exportarLogs()">
                            <i class="bi bi-download me-1"></i>Exportar CSV
                        </button>
                    </div>

                    <div class="logs-container">
                        <?php
                        $fecha_actual = '';
                        foreach ($logs as $log):
                            $fecha_log = date('Y-m-d', strtotime($log['created_at']));
                            if ($fecha_log !== $fecha_actual):
                                $fecha_actual = $fecha_log;
                        ?>
                                <div class="mt-4 mb-3">
                                    <h6 class="text-muted">
                                        <i class="bi bi-calendar3 me-2"></i>
                                        <?php
                                        $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                                        $dia_semana = $dias[date('w', strtotime($fecha_log))];
                                        echo "$dia_semana, " . date('d/m/Y', strtotime($fecha_log));
                                        ?>
                                    </h6>
                                    <hr>
                                </div>
                        <?php
                            endif;
                            
                            // Determinar icono y color según acción
                            $iconos = [
                                'tenant_creado' => ['icon' => 'plus-circle-fill', 'color' => 'success'],
                                'estado_cambiado' => ['icon' => 'arrow-repeat', 'color' => 'warning'],
                                'pago_registrado' => ['icon' => 'cash-coin', 'color' => 'success'],
                                'tenant_editado' => ['icon' => 'pencil-fill', 'color' => 'primary'],
                                'plan_cambiado' => ['icon' => 'star-fill', 'color' => 'info'],
                                'tenant_eliminado' => ['icon' => 'trash-fill', 'color' => 'danger'],
                            ];
                            
                            $accion_info = $iconos[$log['accion']] ?? ['icon' => 'circle-fill', 'color' => 'secondary'];
                        ?>
                            <div class="log-item">
                                <div class="d-flex">
                                    <div class="log-icon bg-<?= $accion_info['color'] ?> bg-opacity-10 text-<?= $accion_info['color'] ?> me-3">
                                        <i class="bi bi-<?= $accion_info['icon'] ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?= htmlspecialchars($log['accion']) ?></strong>
                                                <span class="mx-2">•</span>
                                                <a href="ver_tenant.php?id=<?= $log['tenant_id'] ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($log['tenant_nombre']) ?>
                                                </a>
                                                <?php if ($log['descripcion']): ?>
                                                    <div class="text-muted small mt-1">
                                                        <?= htmlspecialchars($log['descripcion']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-end text-muted small">
                                                <div><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
                                                <?php if ($log['usuario']): ?>
                                                    <div>
                                                        <i class="bi bi-person-fill me-1"></i>
                                                        <?= htmlspecialchars($log['usuario']) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($log['ip_address']): ?>
                                                    <div class="text-muted">
                                                        <i class="bi bi-geo-alt-fill me-1"></i>
                                                        <?= htmlspecialchars($log['ip_address']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportarLogs() {
            // Crear CSV
            let csv = 'Fecha,Hora,Cliente,Acción,Descripción,Usuario,IP\n';
            
            <?php foreach ($logs as $log): ?>
                csv += '<?= date('d/m/Y', strtotime($log['created_at'])) ?>,' +
                       '<?= date('H:i:s', strtotime($log['created_at'])) ?>,' +
                       '"<?= addslashes($log['tenant_nombre']) ?>",' +
                       '"<?= addslashes($log['accion']) ?>",' +
                       '"<?= addslashes($log['descripcion'] ?? '') ?>",' +
                       '"<?= addslashes($log['usuario'] ?? '') ?>",' +
                       '"<?= addslashes($log['ip_address'] ?? '') ?>"\n';
            <?php endforeach; ?>
            
            // Descargar
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'logs_actividad_<?= date('Y-m-d_His') ?>.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
