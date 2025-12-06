<?php
session_start();
require_once 'config_superadmin.php';
verificarSuperAdmin();

$error = '';
$exito = '';

// Obtener datos del super admin actual
$stmt = $conn_master->prepare("SELECT * FROM super_admins WHERE id = ?");
$stmt->execute([$_SESSION['super_admin_id']]);
$super_admin = $stmt->fetch();

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'cambiar_password') {
        $password_actual = $_POST['password_actual'] ?? '';
        $password_nueva = $_POST['password_nueva'] ?? '';
        $password_confirmar = $_POST['password_confirmar'] ?? '';
        
        if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
            $error = 'Todos los campos son obligatorios';
        } elseif ($password_nueva !== $password_confirmar) {
            $error = 'Las contraseñas nuevas no coinciden';
        } elseif (strlen($password_nueva) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres';
        } elseif (!password_verify($password_actual, $super_admin['password'])) {
            $error = 'La contraseña actual es incorrecta';
        } else {
            $nueva_hash = password_hash($password_nueva, PASSWORD_BCRYPT);
            $stmt = $conn_master->prepare("UPDATE super_admins SET password = ? WHERE id = ?");
            $stmt->execute([$nueva_hash, $_SESSION['super_admin_id']]);
            
            registrarLog($_SESSION['super_admin_id'], 'cambio_password_superadmin', 'Contraseña actualizada');
            $exito = 'Contraseña actualizada exitosamente';
        }
    } elseif ($action === 'cambiar_datos') {
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($nombre) || empty($email)) {
            $error = 'Nombre y email son obligatorios';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email inválido';
        } else {
            // Verificar si el email ya existe en otro super admin
            $stmt = $conn_master->prepare("SELECT id FROM super_admins WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['super_admin_id']]);
            if ($stmt->fetch()) {
                $error = 'Este email ya está en uso por otro super administrador';
            } else {
                $stmt = $conn_master->prepare("UPDATE super_admins SET nombre = ?, email = ? WHERE id = ?");
                $stmt->execute([$nombre, $email, $_SESSION['super_admin_id']]);
                
                $_SESSION['super_admin_nombre'] = $nombre;
                $_SESSION['super_admin_email'] = $email;
                
                registrarLog($_SESSION['super_admin_id'], 'cambio_datos_superadmin', "Datos actualizados: $nombre, $email");
                $exito = 'Datos actualizados exitosamente';
                
                // Recargar datos
                $stmt = $conn_master->prepare("SELECT * FROM super_admins WHERE id = ?");
                $stmt->execute([$_SESSION['super_admin_id']]);
                $super_admin = $stmt->fetch();
            }
        }
    } elseif ($action === 'limpiar_logs_antiguos') {
        // SUPER PODER 1: Limpiar logs antiguos
        $dias = intval($_POST['dias'] ?? 90);
        if ($dias < 30) {
            $error = 'Debe mantener al menos 30 días de logs';
        } else {
            $stmt = $conn_master->prepare("DELETE FROM tenant_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$dias]);
            $eliminados = $stmt->rowCount();
            
            registrarLog($_SESSION['super_admin_id'], 'limpieza_logs', "Eliminados $eliminados logs anteriores a $dias días");
            $exito = "Se eliminaron $eliminados registros de logs antiguos";
        }
    } elseif ($action === 'recalcular_estados') {
        // SUPER PODER 2: Recalcular estados de todos los tenants
        $stmt = $conn_master->query("
            UPDATE tenants 
            SET estado = CASE 
                WHEN fecha_expiracion < CURDATE() THEN 'vencido'
                WHEN estado = 'suspendido' THEN 'suspendido'
                ELSE 'activo'
            END
            WHERE estado != 'cancelado'
        ");
        $actualizados = $stmt->rowCount();
        
        registrarLog($_SESSION['super_admin_id'], 'recalculo_estados', "Recalculados $actualizados estados de tenants");
        $exito = "Se actualizaron $actualizados estados de clientes";
    } elseif ($action === 'backup_database') {
        // SUPER PODER 3: Crear backup de configuración
        $backupDir = __DIR__ . '/backups_sistema';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $fecha = date('Y-m-d_H-i-s');
        $archivo = $backupDir . "/config_tenants_{$fecha}.json";
        
        $stmt = $conn_master->query("SELECT * FROM tenants");
        $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        file_put_contents($archivo, json_encode($tenants, JSON_PRETTY_PRINT));
        
        registrarLog($_SESSION['super_admin_id'], 'backup_sistema', "Backup creado: " . basename($archivo));
        $exito = "Backup creado: " . basename($archivo);
    }
}

// Obtener estadísticas del sistema
$stmt = $conn_master->query("SELECT COUNT(*) as total FROM tenants");
$total_tenants = $stmt->fetch()['total'];

$stmt = $conn_master->query("SELECT COUNT(*) as total FROM tenant_logs");
$total_logs = $stmt->fetch()['total'];

$stmt = $conn_master->query("SELECT COUNT(*) as total FROM tenant_pagos");
$total_pagos = $stmt->fetch()['total'];

$stmt = $conn_master->query("SELECT COUNT(*) as total FROM super_admins");
$total_admins = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - Super Admin</title>
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
        .super-poder-card {
            border-left: 4px solid #667eea;
            transition: all 0.3s;
        }
        .super-poder-card:hover {
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
            transform: translateX(5px);
        }
    </style>
</head>
<body>
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
                        <a class="nav-link" href="logs.php">
                            <i class="bi bi-journal-text me-1"></i>Logs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="configuracion.php">
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
                    <i class="bi bi-gear me-2"></i>Configuración del Sistema
                </h2>
                <p class="text-muted">Panel de control y configuración del Super Administrador</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($exito): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($exito) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Configuración Personal -->
            <div class="col-md-6">
                <div class="card stat-card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-person-gear me-2"></i>Mi Cuenta</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="cambiar_datos">
                            
                            <div class="mb-3">
                                <label class="form-label">Nombre</label>
                                <input type="text" class="form-control" name="nombre" value="<?= htmlspecialchars($super_admin['nombre']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($super_admin['email']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Usuario</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($super_admin['usuario']) ?>" disabled>
                                <small class="text-muted">El usuario no se puede cambiar</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Actualizar Datos
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card stat-card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-key me-2"></i>Cambiar Contraseña</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="cambiar_password">
                            
                            <div class="mb-3">
                                <label class="form-label">Contraseña Actual</label>
                                <input type="password" class="form-control" name="password_actual" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Nueva Contraseña</label>
                                <input type="password" class="form-control" name="password_nueva" required minlength="8">
                                <small class="text-muted">Mínimo 8 caracteres</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Confirmar Nueva Contraseña</label>
                                <input type="password" class="form-control" name="password_confirmar" required>
                            </div>
                            
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-shield-lock me-1"></i>Cambiar Contraseña
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Super Poderes -->
            <div class="col-md-6">
                <div class="card stat-card mb-4 super-poder-card">
                    <div class="card-header bg-gradient" style="background: var(--primary-gradient); color: white;">
                        <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i>Super Poderes</h5>
                    </div>
                    <div class="card-body">
                        <!-- Super Poder 1: Limpiar Logs -->
                        <div class="border-bottom pb-3 mb-3">
                            <h6 class="fw-bold text-primary">
                                <i class="bi bi-trash3 me-2"></i>Limpiar Logs Antiguos
                            </h6>
                            <p class="text-muted small mb-2">Elimina registros de logs anteriores a X días para liberar espacio</p>
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="action" value="limpiar_logs_antiguos">
                                <input type="number" class="form-control form-control-sm" name="dias" value="90" min="30" max="365" style="width: 100px;">
                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar logs antiguos?')">
                                    <i class="bi bi-trash me-1"></i>Limpiar
                                </button>
                            </form>
                            <small class="text-muted">Logs actuales: <?= number_format($total_logs) ?></small>
                        </div>

                        <!-- Super Poder 2: Recalcular Estados -->
                        <div class="border-bottom pb-3 mb-3">
                            <h6 class="fw-bold text-info">
                                <i class="bi bi-arrow-repeat me-2"></i>Recalcular Estados de Clientes
                            </h6>
                            <p class="text-muted small mb-2">Actualiza automáticamente el estado de todos los clientes según su fecha de expiración</p>
                            <form method="POST">
                                <input type="hidden" name="action" value="recalcular_estados">
                                <button type="submit" class="btn btn-sm btn-outline-info" onclick="return confirm('¿Recalcular estados de todos los clientes?')">
                                    <i class="bi bi-arrow-repeat me-1"></i>Recalcular
                                </button>
                            </form>
                        </div>

                        <!-- Super Poder 3: Backup -->
                        <div class="pb-3 mb-3">
                            <h6 class="fw-bold text-success">
                                <i class="bi bi-download me-2"></i>Crear Backup de Configuración
                            </h6>
                            <p class="text-muted small mb-2">Genera un archivo JSON con todos los datos de clientes</p>
                            <form method="POST">
                                <input type="hidden" name="action" value="backup_database">
                                <button type="submit" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-download me-1"></i>Crear Backup
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Información del Sistema -->
                <div class="card stat-card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Información del Sistema</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td><i class="bi bi-building text-primary me-2"></i><strong>Total Clientes:</strong></td>
                                <td class="text-end"><span class="badge bg-primary"><?= number_format($total_tenants) ?></span></td>
                            </tr>
                            <tr>
                                <td><i class="bi bi-journal-text text-info me-2"></i><strong>Logs Registrados:</strong></td>
                                <td class="text-end"><span class="badge bg-info"><?= number_format($total_logs) ?></span></td>
                            </tr>
                            <tr>
                                <td><i class="bi bi-cash-coin text-success me-2"></i><strong>Pagos Procesados:</strong></td>
                                <td class="text-end"><span class="badge bg-success"><?= number_format($total_pagos) ?></span></td>
                            </tr>
                            <tr>
                                <td><i class="bi bi-person-badge text-warning me-2"></i><strong>Super Admins:</strong></td>
                                <td class="text-end"><span class="badge bg-warning"><?= number_format($total_admins) ?></span></td>
                            </tr>
                            <tr>
                                <td><strong>PHP Version:</strong></td>
                                <td class="text-end"><?= phpversion() ?></td>
                            </tr>
                            <tr>
                                <td><strong>Base de Datos:</strong></td>
                                <td class="text-end"><?= $conn_master->getAttribute(PDO::ATTR_SERVER_VERSION) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Servidor:</strong></td>
                                <td class="text-end"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
