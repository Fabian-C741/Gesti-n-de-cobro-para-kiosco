<?php
session_start();
require_once 'config_superadmin.php';
verificarSuperAdmin();

$error = '';
$exito = '';

// Obtener lista de tenants para el filtro
$stmt = $conn_master->prepare("SELECT id, nombre FROM tenants WHERE activo = 1 ORDER BY nombre");
$stmt->execute();
$tenants = $stmt->fetchAll();

// Filtro por tenant
$tenant_id = isset($_GET['tenant_id']) ? intval($_GET['tenant_id']) : 0;

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $selected_tenant_id = intval($_POST['tenant_id'] ?? 0);
    
    if ($action === 'crear') {
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        
        if (empty($codigo) || empty($nombre) || empty($selected_tenant_id)) {
            $error = 'Código, nombre y cliente son obligatorios';
        } else {
            try {
                // Conectar a la base de datos del tenant
                $conn_tenant = conectarTenant($selected_tenant_id);
                
                // Verificar si el código ya existe
                $stmt = $conn_tenant->prepare("SELECT id FROM puntos_venta WHERE codigo = ?");
                $stmt->execute([$codigo]);
                if ($stmt->fetch()) {
                    $error = 'El código ya existe';
                } else {
                    $stmt = $conn_tenant->prepare("
                        INSERT INTO puntos_venta (codigo, nombre, direccion, telefono, activo)
                        VALUES (?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$codigo, $nombre, $direccion, $telefono]);
                    
                    registrarLog($_SESSION['super_admin_id'], 'crear_punto_venta', "Punto de venta $nombre creado para tenant $selected_tenant_id");
                    $exito = 'Punto de venta creado exitosamente';
                    $tenant_id = $selected_tenant_id;
                }
            } catch (PDOException $e) {
                $error = 'Error al crear punto de venta: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'editar') {
        $id = intval($_POST['id'] ?? 0);
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        
        if (empty($codigo) || empty($nombre) || empty($selected_tenant_id)) {
            $error = 'Código, nombre y cliente son obligatorios';
        } else {
            try {
                $conn_tenant = conectarTenant($selected_tenant_id);
                
                $stmt = $conn_tenant->prepare("
                    UPDATE puntos_venta 
                    SET codigo = ?, nombre = ?, direccion = ?, telefono = ?
                    WHERE id = ?
                ");
                $stmt->execute([$codigo, $nombre, $direccion, $telefono, $id]);
                
                registrarLog($_SESSION['super_admin_id'], 'editar_punto_venta', "Punto de venta ID $id editado");
                $exito = 'Punto de venta actualizado exitosamente';
                $tenant_id = $selected_tenant_id;
            } catch (PDOException $e) {
                $error = 'Error al actualizar: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'toggle_estado') {
        $id = intval($_POST['id'] ?? 0);
        $estado = intval($_POST['estado'] ?? 0);
        
        try {
            $conn_tenant = conectarTenant($selected_tenant_id);
            
            $stmt = $conn_tenant->prepare("UPDATE puntos_venta SET activo = ? WHERE id = ?");
            $stmt->execute([$estado, $id]);
            
            registrarLog($_SESSION['super_admin_id'], 'toggle_punto_venta', "Punto de venta ID $id " . ($estado ? 'activado' : 'desactivado'));
            $exito = 'Estado actualizado exitosamente';
            $tenant_id = $selected_tenant_id;
        } catch (PDOException $e) {
            $error = 'Error al cambiar estado: ' . $e->getMessage();
        }
    }
}

// Obtener puntos de venta del tenant seleccionado
$puntos_venta = [];
if ($tenant_id > 0) {
    try {
        $conn_tenant = conectarTenant($tenant_id);
        
        $stmt = $conn_tenant->query("
            SELECT pv.*,
                   (SELECT COUNT(*) FROM usuarios WHERE punto_venta = pv.codigo) as total_usuarios
            FROM puntos_venta pv
            ORDER BY pv.activo DESC, pv.nombre ASC
        ");
        $puntos_venta = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = 'Error al obtener puntos de venta: ' . $e->getMessage();
    }
}

$page_title = 'Gestión de Puntos de Venta';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Super Admin</title>
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
                        <a class="nav-link active" href="puntos_venta.php">
                            <i class="bi bi-shop me-1"></i>Puntos de Venta
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pagos.php">
                            <i class="bi bi-cash-coin me-1"></i>Pagos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logs.php">
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
                        <?= htmlspecialchars($_SESSION['super_admin_nombre'] ?? 'Super Admin') ?>
                    </span>
                    <a href="logout.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>Salir
                    </a>
                </div>
            </div>
        </div>
    </nav>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="bi bi-shop me-2"></i>Puntos de Venta</h1>
            <p class="text-muted">Gestiona los puntos de venta de cada cliente</p>
        </div>
        <?php if ($tenant_id > 0): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
            <i class="bi bi-plus-circle me-1"></i>Nuevo Punto de Venta
        </button>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($exito): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i><?php echo $exito; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Filtro por Tenant -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Seleccionar Cliente</label>
                    <select name="tenant_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">-- Seleccionar Cliente --</option>
                        <?php foreach ($tenants as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $tenant_id == $t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if ($tenant_id > 0): ?>
    <!-- Tabla de Puntos de Venta -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Puntos de Venta - <?= htmlspecialchars($tenants[array_search($tenant_id, array_column($tenants, 'id'))]['nombre'] ?? '') ?></h5>
        </div>
        <div class="card-body">
            <?php if (count($puntos_venta) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Dirección</th>
                            <th>Teléfono</th>
                            <th>Usuarios</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($puntos_venta as $pv): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($pv['codigo']) ?></code></td>
                            <td><strong><?= htmlspecialchars($pv['nombre']) ?></strong></td>
                            <td><?= htmlspecialchars($pv['direccion'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($pv['telefono'] ?? '-') ?></td>
                            <td><span class="badge bg-info"><?= $pv['total_usuarios'] ?></span></td>
                            <td>
                                <?php if ($pv['activo']): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="editarPuntoVenta(<?= htmlspecialchars(json_encode($pv)) ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-<?= $pv['activo'] ? 'warning' : 'success' ?>" 
                                        onclick="toggleEstado(<?= $pv['id'] ?>, <?= $pv['activo'] ? 0 : 1 ?>)">
                                    <i class="bi bi-<?= $pv['activo'] ? 'pause' : 'play' ?>-circle"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-shop text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-3">No hay puntos de venta registrados</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-building text-muted" style="font-size: 3rem;"></i>
            <p class="text-muted mt-3">Selecciona un cliente para ver sus puntos de venta</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Crear -->
<div class="modal fade" id="modalCrear" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nuevo Punto de Venta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="crear">
                    <input type="hidden" name="tenant_id" value="<?= $tenant_id ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Código *</label>
                        <input type="text" name="codigo" class="form-control" required placeholder="PV-001">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" class="form-control" required placeholder="Caja Principal">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="direccion" class="form-control" placeholder="Av. Principal 123">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control" placeholder="+54 11 1234-5678">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Punto de Venta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="editar">
                    <input type="hidden" name="tenant_id" value="<?= $tenant_id ?>">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Código *</label>
                        <input type="text" name="codigo" id="edit_codigo" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="direccion" id="edit_direccion" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" id="edit_telefono" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form oculto para toggle estado -->
<form id="formToggle" method="POST" style="display: none;">
    <input type="hidden" name="action" value="toggle_estado">
    <input type="hidden" name="tenant_id" value="<?= $tenant_id ?>">
    <input type="hidden" name="id" id="toggle_id">
    <input type="hidden" name="estado" id="toggle_estado">
</form>

<script>
function editarPuntoVenta(pv) {
    document.getElementById('edit_id').value = pv.id;
    document.getElementById('edit_codigo').value = pv.codigo;
    document.getElementById('edit_nombre').value = pv.nombre;
    document.getElementById('edit_direccion').value = pv.direccion || '';
    document.getElementById('edit_telefono').value = pv.telefono || '';
    
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}

function toggleEstado(id, estado) {
    const accion = estado ? 'activar' : 'desactivar';
    if (confirm(`¿Está seguro de ${accion} este punto de venta?`)) {
        document.getElementById('toggle_id').value = id;
        document.getElementById('toggle_estado').value = estado;
        document.getElementById('formToggle').submit();
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
