<?php
session_start();
require_once 'config_superadmin.php';
verificarSuperAdmin();

$tenant_id = $_GET['tenant_id'] ?? 0;

// Si viene de un cliente específico
if ($tenant_id) {
    $stmt = $conn_master->prepare("SELECT id, nombre FROM tenants WHERE id = ?");
    $stmt->execute([$tenant_id]);
    $tenant_seleccionado = $stmt->fetch();
}

// Obtener todos los tenants para el selector
$stmt = $conn_master->query("SELECT id, nombre FROM tenants WHERE estado != 'cancelado' ORDER BY nombre");
$tenants = $stmt->fetchAll();

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $tenant_id = intval($_POST['tenant_id']);
        $monto = floatval($_POST['monto']);
        $metodo = $_POST['metodo'];
        $estado = $_POST['estado'];
        $referencia = trim($_POST['referencia']);
        $notas = trim($_POST['notas']);
        
        // Insertar pago
        $stmt = $conn_master->prepare("
            INSERT INTO tenant_pagos (tenant_id, monto, metodo_pago, estado, referencia_pago, notas)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$tenant_id, $monto, $metodo, $estado, $referencia, $notas]);
        
        // Si el pago es aprobado, extender fecha de expiración
        if ($estado === 'aprobado') {
            $stmt = $conn_master->prepare("
                UPDATE tenants 
                SET fecha_expiracion = DATE_ADD(COALESCE(fecha_expiracion, NOW()), INTERVAL 30 DAY),
                    estado = 'activo'
                WHERE id = ?
            ");
            $stmt->execute([$tenant_id]);
        }
        
        registrarLog($tenant_id, 'pago_registrado', "Pago de $$monto - Método: $metodo - Estado: $estado");
        
        $mensaje = 'Pago registrado correctamente';
        
    } catch (Exception $e) {
        $error = "Error al registrar pago: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Pago - Super Admin</title>
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
                        <a class="nav-link active" href="pagos.php">
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
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card stat-card">
                    <div class="card-header bg-white py-3">
                        <h4 class="mb-0 fw-bold">
                            <i class="bi bi-cash-coin me-2"></i>Registrar Pago
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
                            <div class="mb-3">
                                <label class="form-label">Cliente *</label>
                                <select class="form-select" name="tenant_id" required>
                                    <option value="">Seleccionar cliente...</option>
                                    <?php foreach ($tenants as $t): ?>
                                        <option value="<?= $t['id'] ?>" <?= ($tenant_id == $t['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($t['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Monto *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="monto" step="0.01" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Método de Pago *</label>
                                <select class="form-select" name="metodo" required>
                                    <option value="transferencia">Transferencia Bancaria</option>
                                    <option value="mercadopago">Mercado Pago</option>
                                    <option value="efectivo">Efectivo</option>
                                    <option value="tarjeta">Tarjeta</option>
                                    <option value="otro">Otro</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Estado *</label>
                                <select class="form-select" name="estado" required>
                                    <option value="aprobado">Aprobado</option>
                                    <option value="pendiente">Pendiente</option>
                                    <option value="rechazado">Rechazado</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Referencia/Comprobante</label>
                                <input type="text" class="form-control" name="referencia" placeholder="Número de operación, ID de pago...">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Notas</label>
                                <textarea class="form-control" name="notas" rows="3"></textarea>
                            </div>

                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Nota:</strong> Si el estado es "Aprobado", se extenderá automáticamente 
                                la fecha de vencimiento del cliente por 30 días.
                            </div>

                            <div class="d-flex gap-2 justify-content-end">
                                <a href="pagos.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle me-1"></i>Cancelar
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-circle me-1"></i>Registrar Pago
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
