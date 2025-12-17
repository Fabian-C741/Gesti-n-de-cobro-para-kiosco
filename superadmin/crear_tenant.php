<?php
session_start();
require_once 'config_superadmin.php';
verificarSuperAdmin();

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar datos
        $nombre = trim($_POST['nombre'] ?? '');
        $razon_social = trim($_POST['razon_social'] ?? '');
        $cuit = trim($_POST['cuit'] ?? '');
        $dominio = strtolower(trim($_POST['dominio'] ?? ''));
        $plan = $_POST['plan'] ?? 'basico';
        $email_contacto = trim($_POST['email_contacto'] ?? '');
        $telefono_contacto = trim($_POST['telefono_contacto'] ?? '');
        $admin_nombre = trim($_POST['admin_nombre'] ?? '');
        $admin_email = trim($_POST['admin_email'] ?? '');
        $admin_telefono = trim($_POST['admin_telefono'] ?? '');
        $admin_password = $_POST['admin_password'] ?? '';
        $bd_existente = trim($_POST['bd_existente'] ?? '');
        $bd_usuario = trim($_POST['bd_usuario'] ?? '');
        $bd_password = $_POST['bd_password'] ?? '';
        
        if (empty($nombre) || empty($dominio) || empty($admin_nombre) || empty($admin_email) || empty($admin_password) || empty($bd_existente) || empty($bd_usuario)) {
            throw new Exception('Por favor completa todos los campos obligatorios (incluye credenciales de BD)');
        }
        
        // Validar que el dominio no exista
        $stmt = $conn_master->prepare("SELECT id FROM tenants WHERE dominio = ?");
        $stmt->execute([$dominio]);
        if ($stmt->fetch()) {
            throw new Exception('El dominio ya está en uso');
        }
        
        // Usar BD existente (creada manualmente en Hostinger)
        $bd_nombre = $bd_existente;
        
        // Obtener límites según el plan
        global $planes;
        $plan_info = $planes[$plan];
        
        // Iniciar transacción
        $conn_master->beginTransaction();
        
        // 1. Crear registro del tenant en BD maestra
        $stmt = $conn_master->prepare("
            INSERT INTO tenants (
                nombre, razon_social, cuit, dominio,
                bd_nombre, bd_host, bd_usuario, bd_password,
                plan, precio_mensual, fecha_inicio, fecha_expiracion,
                limite_usuarios, limite_productos, limite_puntos_venta,
                email_contacto, telefono_contacto,
                admin_nombre, admin_email, admin_telefono,
                estado, activo
            ) VALUES (
                ?, ?, ?, ?,
                ?, 'localhost', ?, ?,
                ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 MONTH),
                ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                'activo', 1
            )
        ");
        
        // Usar las credenciales ingresadas para esta BD
        $stmt->execute([
            $nombre, $razon_social, $cuit, $dominio,
            $bd_nombre, $bd_usuario, $bd_password,
            $plan, $plan_info['precio'],
            $plan_info['limite_usuarios'], $plan_info['limite_productos'], $plan_info['limite_puntos_venta'],
            $email_contacto, $telefono_contacto,
            $admin_nombre, $admin_email, $admin_telefono
        ]);
        
        $tenant_id = $conn_master->lastInsertId();
        
        // 2. La BD ya debe existir en Hostinger (creada manualmente)
        // Solo verificamos que podamos conectarnos
        
        // 3. Leer el schema SQL base
        $schema_file = __DIR__ . '/../database.sql';
        if (!file_exists($schema_file)) {
            throw new Exception('Archivo de schema no encontrado');
        }
        
        $schema_sql = file_get_contents($schema_file);
        
        // 4. Conectar a la BD del tenant usando las credenciales ingresadas
        $conn_tenant = new PDO(
            "mysql:host=localhost;dbname=$bd_nombre;charset=utf8mb4",
            $bd_usuario,
            $bd_password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Dividir y ejecutar cada sentencia SQL
        $statements = array_filter(
            array_map('trim', explode(';', $schema_sql)),
            function($stmt) { return !empty($stmt) && substr($stmt, 0, 2) !== '--'; }
        );
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $conn_tenant->exec($statement);
            }
        }
        
        // 5. Crear usuario administrador en la BD del tenant
        $admin_password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
        
        $stmt = $conn_tenant->prepare("
            INSERT INTO usuarios (nombre, email, password, rol, activo)
            VALUES (?, ?, ?, 'admin', 1)
        ");
        $stmt->execute([$admin_nombre, $admin_email, $admin_password_hash]);
        
        // 6. Crear sucursal y punto de venta por defecto
        $stmt = $conn_tenant->prepare("
            INSERT INTO sucursales (nombre, direccion, activo)
            VALUES ('Sucursal Principal', 'A configurar', 1)
        ");
        $stmt->execute();
        $sucursal_id = $conn_tenant->lastInsertId();
        
        $stmt = $conn_tenant->prepare("
            INSERT INTO puntos_venta (codigo, nombre, sucursal_id, activo)
            VALUES ('PV001', 'Punto de Venta 1', ?, 1)
        ");
        $stmt->execute([$sucursal_id]);
        $punto_venta_id = $conn_tenant->lastInsertId();
        
        // Actualizar el usuario admin con el punto de venta
        $stmt = $conn_tenant->prepare("
            UPDATE usuarios SET punto_venta_id = ? WHERE id = 1
        ");
        $stmt->execute([$punto_venta_id]);
        
        // 7. Insertar categorías base
        $categorias_base = [
            'Bebidas', 'Snacks', 'Golosinas', 'Cigarrillos', 
            'Almacén', 'Limpieza', 'Perfumería', 'Varios'
        ];
        
        $stmt = $conn_tenant->prepare("INSERT INTO categorias (nombre, activo) VALUES (?, 1)");
        foreach ($categorias_base as $cat) {
            $stmt->execute([$cat]);
        }
        
        // 8. Registrar log de creación
        registrarLog($tenant_id, 'tenant_creado', "Cliente creado: $nombre (Plan: $plan)", $conn_master);
        
        // Confirmar transacción
        $conn_master->commit();
        
        $mensaje = "
            <div class='alert alert-success'>
                <h5><i class='bi bi-check-circle-fill me-2'></i>Cliente Creado Exitosamente</h5>
                <hr>
                <p><strong>Detalles de acceso:</strong></p>
                <ul>
                    <li><strong>URL:</strong> <code>http://{$dominio}</code></li>
                    <li><strong>Usuario:</strong> <code>{$admin_username}</code></li>
                    <li><strong>Contraseña:</strong> <code>{$admin_password}</code></li>
                    <li><strong>Base de Datos:</strong> <code>{$bd_nombre}</code></li>
                </ul>
                <p class='mb-0'><small class='text-muted'>Guarda esta información. El cliente ya puede acceder a su sistema.</small></p>
                <div class='mt-3'>
                    <a href='ver_tenant.php?id={$tenant_id}' class='btn btn-primary'>Ver Detalles del Cliente</a>
                    <a href='tenants.php' class='btn btn-outline-primary'>Volver a Lista</a>
                    <a href='crear_tenant.php' class='btn btn-outline-secondary'>Crear Otro</a>
                </div>
            </div>
        ";
        
    } catch (Exception $e) {
        if ($conn_master->inTransaction()) {
            $conn_master->rollBack();
        }
        
        // Intentar eliminar la BD si se creó
        if (isset($bd_nombre)) {
            try {
                $conn_master->exec("DROP DATABASE IF EXISTS `$bd_nombre`");
            } catch (Exception $e2) {}
        }
        
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cliente - Super Admin</title>
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
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .plan-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }
        .plan-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        }
        .plan-card input[type="radio"] {
            width: 20px;
            height: 20px;
        }
        .section-divider {
            border-top: 2px solid #e0e0e0;
            margin: 2rem 0;
            padding-top: 2rem;
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

    <div class="container mt-4 mb-5">
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="tenants.php">Clientes</a></li>
                        <li class="breadcrumb-item active">Crear Nuevo</li>
                    </ol>
                </nav>
                <h2 class="fw-bold">
                    <i class="bi bi-plus-circle me-2"></i>Crear Nuevo Cliente
                </h2>
                <p class="text-muted">Provisiona un nuevo cliente con su base de datos y usuario administrador</p>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <?= $mensaje ?>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="formCrearTenant">
                <div class="card stat-card mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-building me-2"></i>Datos del Cliente</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombre Comercial <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nombre" required placeholder="Ej: Kiosco La Esquina">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Razón Social</label>
                                <input type="text" class="form-control" name="razon_social" placeholder="Ej: Juan Pérez">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">CUIT/CUIL</label>
                                <input type="text" class="form-control" name="cuit" placeholder="20-12345678-9">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Email de Contacto</label>
                                <input type="email" class="form-control" name="email_contacto" placeholder="contacto@cliente.com">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Teléfono</label>
                                <input type="text" class="form-control" name="telefono_contacto" placeholder="+54 11 1234-5678">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card stat-card mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-globe me-2"></i>Configuración de Dominio y Base de Datos</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Paso previo:</strong> Antes de crear el tenant, debes crear la base de datos en Hostinger → Bases de datos → MySQL
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Código de acceso <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="dominio" required 
                                       placeholder="kiosco-juan" pattern="[a-z0-9-]+" 
                                       title="Solo letras minúsculas, números y guiones">
                                <small class="text-muted">El cliente usará este código para iniciar sesión</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nombre de BD <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="bd_existente" required 
                                       placeholder="u464516792_cliente1">
                                <small class="text-muted">Nombre exacto de la BD creada en Hostinger</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Usuario de BD <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="bd_usuario" required 
                                       placeholder="u464516792_cliente1">
                                <small class="text-muted">Usuario de la base de datos</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contraseña de BD <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="bd_password" 
                                       placeholder="••••••••">
                                <small class="text-muted">Contraseña de la base de datos</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card stat-card mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-star me-2"></i>Seleccionar Plan</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <?php foreach ($planes as $key => $plan): ?>
                            <div class="col-md-4">
                                <label class="plan-card <?= $key === 'basico' ? 'selected' : '' ?>" data-plan="<?= $key ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="fw-bold mb-0"><?= $plan['nombre'] ?></h5>
                                            <div class="text-primary fw-bold fs-3">$<?= $plan['precio'] ?><small class="fs-6 text-muted">/mes</small></div>
                                        </div>
                                        <input type="radio" name="plan" value="<?= $key ?>" <?= $key === 'basico' ? 'checked' : '' ?>>
                                    </div>
                                    <ul class="list-unstyled">
                                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i><?= $plan['limite_usuarios'] ?> Usuarios</li>
                                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i><?= number_format($plan['limite_productos']) ?> Productos</li>
                                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i><?= $plan['limite_puntos_venta'] ?> Punto(s) de Venta</li>
                                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i><?= $plan['soporte'] ?></li>
                                    </ul>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="card stat-card mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2"></i>Usuario Administrador</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Se creará automáticamente un usuario administrador para el cliente
                        </p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="admin_nombre" required placeholder="Juan Pérez">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="admin_email" required placeholder="admin@cliente.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Teléfono</label>
                                <input type="text" class="form-control" name="admin_telefono" placeholder="+54 11 1234-5678">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="admin_password" required 
                                       value="<?= bin2hex(random_bytes(4)) ?>" placeholder="Mínimo 8 caracteres">
                                <small class="text-muted">Se genera automáticamente. Puedes cambiarla si lo deseas.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        <i class="bi bi-check-circle me-2"></i>Crear Cliente
                    </button>
                    <a href="tenants.php" class="btn btn-outline-secondary btn-lg px-5">
                        <i class="bi bi-x-circle me-2"></i>Cancelar
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Selección visual de planes
        document.querySelectorAll('.plan-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
            });
        });
        
        // Generar dominio automáticamente desde el nombre
        document.querySelector('input[name="nombre"]').addEventListener('input', function(e) {
            const dominioInput = document.querySelector('input[name="dominio"]');
            if (!dominioInput.value || dominioInput.dataset.auto !== 'false') {
                const slug = e.target.value
                    .toLowerCase()
                    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');
                dominioInput.value = slug;
            }
        });
        
        document.querySelector('input[name="dominio"]').addEventListener('input', function() {
            this.dataset.auto = 'false';
        });
    </script>
</body>
</html>
