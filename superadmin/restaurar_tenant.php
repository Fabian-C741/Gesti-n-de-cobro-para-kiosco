<?php
session_start();
require_once 'config_superadmin.php';
verificarSuperAdmin();

$backupDir = __DIR__ . '/backups_clientes';
$error = '';
$exito = '';

// Listar archivos de backup disponibles
$backups = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'txt') {
            $backups[] = $file;
        }
    }
    rsort($backups); // Más recientes primero
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $archivo_backup = $_POST['archivo_backup'] ?? '';
    
    if (!$archivo_backup || !file_exists($backupDir . '/' . $archivo_backup)) {
        $error = 'Archivo de backup no válido';
    } else {
        try {
            // Leer contenido del backup
            $contenido = file_get_contents($backupDir . '/' . $archivo_backup);
            
            // Parsear datos del backup (formato simple)
            $datos = [];
            preg_match('/Nombre: (.+)/', $contenido, $match);
            $datos['nombre'] = trim($match[1] ?? '');
            
            preg_match('/Razón Social: (.+)/', $contenido, $match);
            $datos['razon_social'] = trim($match[1] ?? '');
            
            preg_match('/Dominio: (.+)/', $contenido, $match);
            $datos['dominio'] = trim($match[1] ?? '');
            
            preg_match('/Email: (.+)/', $contenido, $match);
            $datos['email_contacto'] = trim($match[1] ?? '');
            
            preg_match('/Teléfono: (.+)/', $contenido, $match);
            $datos['telefono_contacto'] = trim($match[1] ?? '');
            
            preg_match('/Plan: (.+)/', $contenido, $match);
            $datos['plan'] = trim($match[1] ?? '');
            
            preg_match('/Precio Mensual: \$(.+)/', $contenido, $match);
            $datos['precio_mensual'] = trim($match[1] ?? '0');
            
            preg_match('/Nombre BD: (.+)/', $contenido, $match);
            $datos['bd_nombre'] = trim($match[1] ?? '');
            
            preg_match('/Usuario BD: (.+)/', $contenido, $match);
            $datos['bd_usuario'] = trim($match[1] ?? '');
            
            preg_match('/Contraseña BD: (.+)/', $contenido, $match);
            $datos['bd_password'] = trim($match[1] ?? '');
            
            // Verificar si el dominio ya existe
            $stmt = $conn_master->prepare("SELECT id FROM tenants WHERE dominio = ?");
            $stmt->execute([$datos['dominio']]);
            if ($stmt->fetch()) {
                throw new Exception("El dominio '{$datos['dominio']}' ya existe en el sistema");
            }
            
            // Insertar tenant restaurado
            $stmt = $conn_master->prepare("
                INSERT INTO tenants (
                    nombre, razon_social, dominio, email_contacto, telefono_contacto,
                    plan, precio_mensual, estado, fecha_inicio, fecha_expiracion,
                    bd_nombre, bd_usuario, bd_password, activo, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'activo', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?, ?, ?, 1, NOW())
            ");
            
            $stmt->execute([
                $datos['nombre'],
                $datos['razon_social'],
                $datos['dominio'],
                $datos['email_contacto'],
                $datos['telefono_contacto'],
                $datos['plan'],
                $datos['precio_mensual'],
                $datos['bd_nombre'],
                $datos['bd_usuario'],
                $datos['bd_password']
            ]);
            
            $nuevo_id = $conn_master->lastInsertId();
            
            // Registrar en logs
            registrarLog($nuevo_id, 'tenant_restaurado', "Cliente restaurado desde backup: {$archivo_backup}");
            
            $exito = "Cliente '{$datos['nombre']}' restaurado correctamente con ID #{$nuevo_id}. Se le dio 30 días de servicio activo.";
            
        } catch (Exception $e) {
            $error = "Error al restaurar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurar Cliente - Super Admin</title>
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
        .backup-item {
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .backup-item:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .backup-item input[type="radio"] {
            cursor: pointer;
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
                        <a class="nav-link active" href="tenants.php">
                            <i class="bi bi-building me-1"></i>Clientes
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

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="tenants.php">Clientes</a></li>
                        <li class="breadcrumb-item active">Restaurar Cliente</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row">
            <div class="col-md-10 mx-auto">
                <div class="card stat-card">
                    <div class="card-header bg-success text-white py-3">
                        <h4 class="mb-0 fw-bold">
                            <i class="bi bi-arrow-counterclockwise me-2"></i>Restaurar Cliente desde Backup
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($exito): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($exito) ?>
                                <div class="mt-2">
                                    <a href="tenants.php" class="btn btn-sm btn-success">Ver Clientes</a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($backups)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                No hay backups disponibles. Los backups se crean automáticamente al eliminar un cliente.
                            </div>
                            <a href="tenants.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Volver a Clientes
                            </a>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Importante:</strong> Al restaurar un cliente, se creará con estado <strong>"Activo"</strong> 
                                y se le darán <strong>30 días</strong> de servicio. Asegúrate de que la base de datos del cliente 
                                aún exista en el servidor.
                            </div>

                            <form method="POST" action="">
                                <div class="mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-file-earmark-text me-1"></i>
                                        Selecciona un backup para restaurar (<?= count($backups) ?> disponibles):
                                    </label>
                                    
                                    <?php foreach ($backups as $backup): ?>
                                        <?php
                                        // Extraer información del nombre del archivo
                                        // Formato: tenant_{id}_{dominio}_{fecha}.txt
                                        preg_match('/tenant_(\d+)_(.+?)_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.txt/', $backup, $matches);
                                        $tenant_id = $matches[1] ?? '?';
                                        $dominio = $matches[2] ?? '?';
                                        $fecha = isset($matches[3]) ? str_replace('_', ' ', $matches[3]) : '?';
                                        ?>
                                        <label class="backup-item">
                                            <div class="d-flex align-items-start">
                                                <input type="radio" name="archivo_backup" value="<?= htmlspecialchars($backup) ?>" 
                                                       class="form-check-input mt-1 me-3" required>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold text-primary">
                                                        <i class="bi bi-building me-1"></i><?= htmlspecialchars($dominio) ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <i class="bi bi-clock me-1"></i>Eliminado: <?= $fecha ?>
                                                        <span class="mx-2">|</span>
                                                        <i class="bi bi-tag me-1"></i>ID original: #<?= $tenant_id ?>
                                                        <span class="mx-2">|</span>
                                                        <i class="bi bi-file-earmark me-1"></i><?= htmlspecialchars($backup) ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <div class="d-flex gap-2 justify-content-end">
                                    <a href="tenants.php" class="btn btn-secondary">
                                        <i class="bi bi-x-circle me-1"></i>Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar Cliente
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Hacer clic en el div también selecciona el radio
        document.querySelectorAll('.backup-item').forEach(item => {
            item.addEventListener('click', function(e) {
                if (e.target.tagName !== 'INPUT') {
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                }
            });
        });
    </script>
</body>
</html>
