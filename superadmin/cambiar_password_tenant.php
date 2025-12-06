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
        $nueva_password = $_POST['nueva_password'] ?? '';
        $confirmar_password = $_POST['confirmar_password'] ?? '';
        
        if (empty($nueva_password) || empty($confirmar_password)) {
            $error = 'Complete todos los campos';
        } elseif (strlen($nueva_password) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres';
        } elseif ($nueva_password !== $confirmar_password) {
            $error = 'Las contraseñas no coinciden';
        } else {
            // Conectar a la BD del tenant
            $conn_tenant = conectarTenant($id);
            
            // Actualizar contraseña del admin (primer usuario con rol admin)
            $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
            
            $stmt = $conn_tenant->prepare("
                UPDATE usuarios 
                SET password = ? 
                WHERE rol = 'admin' 
                ORDER BY id ASC 
                LIMIT 1
            ");
            $stmt->execute([$password_hash]);
            
            registrarLog($id, 'password_cambiado', "Contraseña del administrador actualizada desde panel Super Admin");
            
            $mensaje = 'Contraseña actualizada correctamente';
        }
    } catch (Exception $e) {
        $error = "Error al cambiar contraseña: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña - Super Admin</title>
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
        .password-strength {
            height: 5px;
            border-radius: 3px;
            transition: all 0.3s;
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
                        <li class="breadcrumb-item"><a href="ver_tenant.php?id=<?= $id ?>"><?= htmlspecialchars($tenant['nombre']) ?></a></li>
                        <li class="breadcrumb-item active">Cambiar Contraseña</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card stat-card">
                    <div class="card-header bg-white py-3">
                        <h4 class="mb-0 fw-bold">
                            <i class="bi bi-key me-2"></i>Cambiar Contraseña del Administrador
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Atención:</strong> Esto cambiará la contraseña del usuario administrador principal del cliente.
                        </div>

                        <div class="mb-3">
                            <strong>Cliente:</strong> <?= htmlspecialchars($tenant['nombre']) ?><br>
                            <strong>Dominio:</strong> <code><?= htmlspecialchars($tenant['dominio']) ?></code><br>
                            <strong>Admin Email:</strong> <?= htmlspecialchars($tenant['admin_email']) ?>
                        </div>

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

                        <form method="POST" action="" id="formPassword">
                            <div class="mb-3">
                                <label class="form-label">Nueva Contraseña *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" class="form-control" name="nueva_password" id="nueva_password" 
                                           minlength="8" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength mt-2" id="strengthBar"></div>
                                <small class="text-muted">Mínimo 8 caracteres</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Confirmar Contraseña *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" class="form-control" name="confirmar_password" id="confirmar_password" 
                                           minlength="8" required>
                                </div>
                                <div id="matchMessage" class="mt-1"></div>
                            </div>

                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <a href="ver_tenant.php?id=<?= $id ?>" class="btn btn-secondary">
                                    <i class="bi bi-x-circle me-1"></i>Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-1"></i>Cambiar Contraseña
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
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('nueva_password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });

        // Password strength indicator
        document.getElementById('nueva_password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            const colors = ['#dc3545', '#fd7e14', '#ffc107', '#28a745', '#28a745'];
            const widths = ['20%', '40%', '60%', '80%', '100%'];
            
            strengthBar.style.width = widths[strength - 1] || '0%';
            strengthBar.style.backgroundColor = colors[strength - 1] || '#e9ecef';
        });

        // Password match validator
        document.getElementById('confirmar_password').addEventListener('input', function() {
            const nueva = document.getElementById('nueva_password').value;
            const confirmar = this.value;
            const matchMessage = document.getElementById('matchMessage');
            
            if (confirmar === '') {
                matchMessage.textContent = '';
            } else if (nueva === confirmar) {
                matchMessage.innerHTML = '<small class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Las contraseñas coinciden</small>';
            } else {
                matchMessage.innerHTML = '<small class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Las contraseñas no coinciden</small>';
            }
        });
    </script>
</body>
</html>
