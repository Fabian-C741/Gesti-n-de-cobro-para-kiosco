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
        $plan = $_POST['plan'];
        $precio_mensual = floatval($_POST['precio_mensual']);
        
        // Actualizar límites según el plan
        global $planes;
        $nuevo_plan = $planes[$plan];
        
        $stmt = $conn_master->prepare("
            UPDATE tenants SET
                plan = ?,
                precio_mensual = ?,
                limite_usuarios = ?,
                limite_productos = ?,
                limite_puntos_venta = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $plan,
            $precio_mensual,
            $nuevo_plan['limite_usuarios'],
            $nuevo_plan['limite_productos'],
            $nuevo_plan['limite_puntos_venta'],
            $id
        ]);
        
        registrarLog($id, 'plan_cambiado', "Plan cambiado de {$tenant['plan']} a $plan");
        
        header('Location: ver_tenant.php?id=' . $id);
        exit;
        
    } catch (Exception $e) {
        $error = "Error al cambiar plan: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Plan - Super Admin</title>
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
        .plan-card {
            transition: all 0.3s;
            cursor: pointer;
            border: 3px solid transparent;
        }
        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .plan-card.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .plan-card input[type="radio"] {
            display: none;
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
                <h2 class="fw-bold">
                    <i class="bi bi-arrow-up-circle me-2"></i>Cambiar Plan
                </h2>
                <p class="text-muted">Cliente: <strong><?= htmlspecialchars($tenant['nombre']) ?></strong></p>
                <p class="text-muted">Plan actual: <span class="badge bg-primary"><?= ucfirst($tenant['plan']) ?></span></p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="formPlan">
            <div class="row g-4 mb-4">
                <?php
                global $planes;
                $colores = ['basico' => 'secondary', 'estandar' => 'primary', 'premium' => 'warning'];
                foreach ($planes as $key => $plan):
                ?>
                <div class="col-md-4">
                    <label class="plan-card card stat-card h-100 <?= $tenant['plan'] === $key ? 'selected' : '' ?>">
                        <input type="radio" name="plan" value="<?= $key ?>" <?= $tenant['plan'] === $key ? 'checked' : '' ?>>
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <span class="badge bg-<?= $colores[$key] ?> fs-6 px-3 py-2"><?= $plan['nombre'] ?></span>
                            </div>
                            <h2 class="fw-bold mb-0">$<?= $plan['precio'] ?></h2>
                            <p class="text-muted">por mes</p>
                            
                            <hr>
                            
                            <ul class="list-unstyled text-start">
                                <li class="mb-2">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <strong><?= $plan['limite_usuarios'] ?></strong> usuarios
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <strong><?= number_format($plan['limite_productos']) ?></strong> productos
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <strong><?= $plan['limite_puntos_venta'] ?></strong> puntos de venta
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-headset text-info me-2"></i>
                                    <?= $plan['soporte'] ?>
                                </li>
                            </ul>
                            
                            <?php if ($tenant['plan'] === $key): ?>
                                <span class="badge bg-success w-100 py-2">Plan Actual</span>
                            <?php endif; ?>
                        </div>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="card stat-card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <label class="form-label">Precio Mensual Personalizado</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="precio_mensual" id="precioInput" value="<?= $tenant['precio_mensual'] ?>" step="0.01" required>
                            </div>
                            <small class="text-muted">Puedes ajustar el precio según un acuerdo especial</small>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="ver_tenant.php?id=<?= $id ?>" class="btn btn-secondary me-2">
                                <i class="bi bi-x-circle me-1"></i>Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-circle me-1"></i>Confirmar Cambio de Plan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const precios = {
            'basico': 20,
            'estandar': 40,
            'premium': 80
        };
        
        // Manejar selección de plan
        document.querySelectorAll('.plan-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                
                // Actualizar precio
                document.getElementById('precioInput').value = precios[radio.value];
            });
        });
    </script>
</body>
</html>
