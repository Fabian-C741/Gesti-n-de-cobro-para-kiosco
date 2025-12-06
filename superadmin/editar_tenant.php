<?php
session_start();
require_once 'config_superadmin.php';
verificarSuperAdmin();

$id = $_GET['id'] ?? 0;

// Obtener datos del tenant
$stmt = $conn_master->prepare("SELECT * FROM tenants WHERE id = ?");
$stmt->execute([$id]);
$tenant = $stmt->fetch();

if (!$tenant) {
    header('Location: tenants.php');
    exit;
}

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombre = trim($_POST['nombre']);
        $razon_social = trim($_POST['razon_social']);
        $cuit = trim($_POST['cuit']);
        $dominio = trim($_POST['dominio']);
        $email_contacto = trim($_POST['email_contacto']);
        $telefono_contacto = trim($_POST['telefono_contacto']);
        $admin_nombre = trim($_POST['admin_nombre']);
        $admin_email = trim($_POST['admin_email']);
        $admin_telefono = trim($_POST['admin_telefono']);
        $plan = $_POST['plan'];
        $precio_mensual = floatval($_POST['precio_mensual']);
        $fecha_expiracion = $_POST['fecha_expiracion'];
        
        // Actualizar tenant
        $stmt = $conn_master->prepare("
            UPDATE tenants SET
                nombre = ?,
                razon_social = ?,
                cuit = ?,
                dominio = ?,
                email_contacto = ?,
                telefono_contacto = ?,
                admin_nombre = ?,
                admin_email = ?,
                admin_telefono = ?,
                plan = ?,
                precio_mensual = ?,
                fecha_expiracion = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $nombre,
            $razon_social,
            $cuit,
            $dominio,
            $email_contacto,
            $telefono_contacto,
            $admin_nombre,
            $admin_email,
            $admin_telefono,
            $plan,
            $precio_mensual,
            $fecha_expiracion,
            $id
        ]);
        
        registrarLog($id, 'tenant_editado', "Datos del tenant actualizados");
        
        $mensaje = 'Datos actualizados correctamente';
        
        // Recargar datos
        $stmt = $conn_master->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$id]);
        $tenant = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = "Error al actualizar: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente - Super Admin</title>
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
                        <li class="breadcrumb-item"><a href="ver_tenant.php?id=<?= $id ?>"><?= htmlspecialchars($tenant['nombre']) ?></a></li>
                        <li class="breadcrumb-item active">Editar</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card stat-card">
                    <div class="card-header bg-white py-3">
                        <h4 class="mb-0 fw-bold">
                            <i class="bi bi-pencil me-2"></i>Editar Cliente
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($mensaje): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="bi bi-check-circle me-2"></i><?= $mensaje ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <h5 class="fw-bold mb-3">Información del Negocio</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre del Negocio *</label>
                                    <input type="text" class="form-control" name="nombre" value="<?= htmlspecialchars($tenant['nombre']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Razón Social</label>
                                    <input type="text" class="form-control" name="razon_social" value="<?= htmlspecialchars($tenant['razon_social']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">CUIT</label>
                                    <input type="text" class="form-control" name="cuit" value="<?= htmlspecialchars($tenant['cuit']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Dominio *</label>
                                    <input type="text" class="form-control" name="dominio" value="<?= htmlspecialchars($tenant['dominio']) ?>" required>
                                    <small class="text-muted">Ej: miclientepos</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email de Contacto *</label>
                                    <input type="email" class="form-control" name="email_contacto" value="<?= htmlspecialchars($tenant['email_contacto']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" name="telefono_contacto" value="<?= htmlspecialchars($tenant['telefono_contacto']) ?>">
                                </div>
                            </div>

                            <h5 class="fw-bold mb-3 mt-4">Administrador del Sistema</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre Completo *</label>
                                    <input type="text" class="form-control" name="admin_nombre" value="<?= htmlspecialchars($tenant['admin_nombre']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="admin_email" value="<?= htmlspecialchars($tenant['admin_email']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" name="admin_telefono" value="<?= htmlspecialchars($tenant['admin_telefono']) ?>">
                                </div>
                            </div>

                            <h5 class="fw-bold mb-3 mt-4">Plan y Facturación</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label">Plan *</label>
                                    <select class="form-select" name="plan" required id="planSelect">
                                        <option value="basico" <?= $tenant['plan'] === 'basico' ? 'selected' : '' ?>>Básico</option>
                                        <option value="estandar" <?= $tenant['plan'] === 'estandar' ? 'selected' : '' ?>>Estándar</option>
                                        <option value="premium" <?= $tenant['plan'] === 'premium' ? 'selected' : '' ?>>Premium</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Precio Mensual *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" name="precio_mensual" value="<?= $tenant['precio_mensual'] ?>" step="0.01" required id="precioInput">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Fecha Vencimiento</label>
                                    <input type="date" class="form-control" name="fecha_expiracion" value="<?= $tenant['fecha_expiracion'] ?>">
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Nota:</strong> Los cambios en el plan no modifican automáticamente los límites. 
                                Los límites se establecieron durante la creación del cliente.
                            </div>

                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <a href="ver_tenant.php?id=<?= $id ?>" class="btn btn-secondary">
                                    <i class="bi bi-x-circle me-1"></i>Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-1"></i>Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-completar precio según plan
        document.getElementById('planSelect').addEventListener('change', function() {
            const precios = {
                'basico': 20,
                'estandar': 40,
                'premium': 80
            };
            document.getElementById('precioInput').value = precios[this.value];
        });
    </script>
</body>
</html>
