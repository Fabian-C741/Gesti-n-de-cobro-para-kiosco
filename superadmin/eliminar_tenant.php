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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmar = $_POST['confirmar'] ?? '';
    
    if ($confirmar !== 'ELIMINAR') {
        $error = 'Debe escribir ELIMINAR para confirmar';
    } else {
        try {
            $conn_master->beginTransaction();
            
            // 1. CREAR BACKUP EN TXT antes de eliminar
            $backupDir = __DIR__ . '/backups_clientes';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            $fecha_backup = date('Y-m-d_H-i-s');
            $archivo_backup = $backupDir . "/tenant_{$id}_{$tenant['dominio']}_{$fecha_backup}.txt";
            
            // Obtener pagos del cliente (si la tabla existe)
            $pagos = [];
            try {
                $stmt_pagos = $conn_master->prepare("SELECT * FROM tenant_pagos WHERE tenant_id = ? ORDER BY id DESC");
                $stmt_pagos->execute([$id]);
                $pagos = $stmt_pagos->fetchAll();
            } catch (PDOException $e) {
                // La tabla no existe o hay error, continuar sin pagos
                $pagos = [];
            }
            
            // Crear contenido del backup
            $backup_content = "=================================================\n";
            $backup_content .= "BACKUP DE CLIENTE - SISTEMA GESTION DE COBROS\n";
            $backup_content .= "=================================================\n";
            $backup_content .= "Fecha de backup: " . date('d/m/Y H:i:s') . "\n";
            $backup_content .= "Eliminado por: " . $_SESSION['super_admin_nombre'] . "\n\n";
            
            $backup_content .= "--- DATOS DEL CLIENTE ---\n";
            $backup_content .= "ID: {$tenant['id']}\n";
            $backup_content .= "Nombre: {$tenant['nombre']}\n";
            $backup_content .= "Razón Social: {$tenant['razon_social']}\n";
            $backup_content .= "Dominio: {$tenant['dominio']}\n";
            $backup_content .= "Email: {$tenant['email_contacto']}\n";
            $backup_content .= "Teléfono: {$tenant['telefono_contacto']}\n";
            $backup_content .= "Plan: {$tenant['plan']}\n";
            $backup_content .= "Precio Mensual: \${$tenant['precio_mensual']}\n";
            $backup_content .= "Estado: {$tenant['estado']}\n";
            $backup_content .= "Fecha Inicio: {$tenant['fecha_inicio']}\n";
            $backup_content .= "Fecha Expiración: {$tenant['fecha_expiracion']}\n";
            $backup_content .= "Fecha Creación: {$tenant['created_at']}\n\n";
            
            $backup_content .= "--- CONFIGURACIÓN DE BASE DE DATOS ---\n";
            $backup_content .= "Nombre BD: {$tenant['bd_nombre']}\n";
            $backup_content .= "Usuario BD: {$tenant['bd_usuario']}\n";
            $backup_content .= "Contraseña BD: {$tenant['bd_password']}\n\n";
            
            if (!empty($pagos)) {
                $backup_content .= "--- HISTORIAL DE PAGOS ---\n";
                foreach ($pagos as $pago) {
                    $backup_content .= "\nPago #{$pago['id']}:\n";
                    $backup_content .= "  Fecha: {$pago['fecha_pago']}\n";
                    $backup_content .= "  Monto: \${$pago['monto']}\n";
                    $backup_content .= "  Método: {$pago['metodo_pago']}\n";
                    $backup_content .= "  Referencia: {$pago['referencia']}\n";
                    $backup_content .= "  Días extendidos: {$pago['dias_extendidos']}\n";
                }
            } else {
                $backup_content .= "--- SIN HISTORIAL DE PAGOS ---\n";
            }
            
            $backup_content .= "\n=================================================\n";
            $backup_content .= "FIN DEL BACKUP\n";
            $backup_content .= "=================================================\n";
            
            // Guardar archivo de backup
            file_put_contents($archivo_backup, $backup_content);
            
            // 2. Registrar en logs antes de eliminar
            registrarLog($id, 'tenant_eliminado', "Cliente {$tenant['nombre']} (dominio: {$tenant['dominio']}) eliminado - Backup: " . basename($archivo_backup));
            
            // 3. Eliminar logs del tenant
            $stmt = $conn_master->prepare("DELETE FROM tenant_logs WHERE tenant_id = ?");
            $stmt->execute([$id]);
            
            // 4. Eliminar pagos del tenant
            $stmt = $conn_master->prepare("DELETE FROM tenant_pagos WHERE tenant_id = ?");
            $stmt->execute([$id]);
            
            // 5. ELIMINAR FÍSICAMENTE EL TENANT de la base de datos
            $stmt = $conn_master->prepare("DELETE FROM tenants WHERE id = ?");
            $stmt->execute([$id]);
            
            // NOTA: La base de datos del tenant NO se elimina por seguridad
            // Si deseas eliminarla, hazlo manualmente desde phpMyAdmin
            
            $conn_master->commit();
            
            header('Location: tenants.php?mensaje=Cliente eliminado correctamente. Backup guardado: ' . basename($archivo_backup));
            exit;
            
        } catch (Exception $e) {
            $conn_master->rollBack();
            $error = "Error al eliminar cliente: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Cliente - Super Admin</title>
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
        .danger-zone {
            border: 2px solid #dc3545;
            border-radius: 10px;
            padding: 20px;
            background: #fff5f5;
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
                        <li class="breadcrumb-item active">Eliminar</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card stat-card">
                    <div class="card-header bg-danger text-white py-3">
                        <h4 class="mb-0 fw-bold">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>ZONA PELIGROSA: Eliminar Cliente
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
                            </div>
                        <?php endif; ?>

                        <div class="danger-zone">
                            <h5 class="text-danger mb-3">
                                <i class="bi bi-shield-exclamation me-2"></i>¿Estás seguro de eliminar este cliente?
                            </h5>
                            
                            <div class="mb-3">
                                <strong>Cliente:</strong> <?= htmlspecialchars($tenant['nombre']) ?><br>
                                <strong>Dominio:</strong> <code><?= htmlspecialchars($tenant['dominio']) ?></code><br>
                                <strong>Plan:</strong> <?= ucfirst($tenant['plan']) ?><br>
                                <strong>Email:</strong> <?= htmlspecialchars($tenant['email_contacto']) ?>
                            </div>

                            <div class="alert alert-danger">
                                <h6 class="fw-bold">⚠️ ELIMINACIÓN FÍSICA CON BACKUP</h6>
                                <p class="mb-2">Al eliminar este cliente:</p>
                                <ul class="mb-0">
                                    <li>Se <strong>ELIMINARÁ PERMANENTEMENTE</strong> de la base de datos</li>
                                    <li>Se creará un <strong>BACKUP en TXT</strong> con todos sus datos</li>
                                    <li>El cliente <strong>NO PODRÁ ACCEDER</strong> más a su sistema</li>
                                    <li>Se eliminarán los <strong>logs</strong> y <strong>pagos</strong> registrados</li>
                                    <li>Podrás <strong>RESTAURARLO</strong> más adelante desde el backup si lo solicita nuevamente</li>
                                </ul>
                            </div>

                            <div class="alert alert-success">
                                <i class="bi bi-file-text me-2"></i>
                                <strong>Backup automático:</strong> Se guardará un archivo TXT en <code>/superadmin/backups_clientes/</code> 
                                con toda la información del cliente, incluyendo datos de acceso y historial de pagos.
                            </div>
                        </div>

                        <form method="POST" action="" id="formEliminar" class="mt-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    Para confirmar, escribe la palabra <code>ELIMINAR</code> en el campo:
                                </label>
                                <input type="text" class="form-control" name="confirmar" id="confirmar" 
                                       placeholder="Escribe ELIMINAR" required>
                            </div>

                            <div class="d-flex gap-2 justify-content-end">
                                <a href="ver_tenant.php?id=<?= $id ?>" class="btn btn-secondary btn-lg">
                                    <i class="bi bi-x-circle me-1"></i>Cancelar
                                </a>
                                <button type="submit" class="btn btn-danger btn-lg" id="btnEliminar" disabled>
                                    <i class="bi bi-trash me-1"></i>Eliminar Permanentemente
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
        // Habilitar botón solo si escribe "ELIMINAR"
        document.getElementById('confirmar').addEventListener('input', function() {
            const btn = document.getElementById('btnEliminar');
            if (this.value === 'ELIMINAR') {
                btn.disabled = false;
            } else {
                btn.disabled = true;
            }
        });

        // Confirmación adicional antes de enviar
        document.getElementById('formEliminar').addEventListener('submit', function(e) {
            if (!confirm('¿ESTÁS COMPLETAMENTE SEGURO? Esta acción NO se puede deshacer.')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
