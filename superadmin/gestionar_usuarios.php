<?php
session_start();
require_once 'config_superadmin.php';
verificarSuperAdmin();

$tenant_id = $_GET['tenant_id'] ?? 0;

// Obtener datos del tenant
$stmt = $conn_master->prepare("SELECT * FROM tenants WHERE id = ?");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch();

if (!$tenant) {
    header('Location: tenants.php');
    exit;
}

$error = '';
$exito = '';

// Conectar a la BD del tenant
try {
    $tenant_conn = new PDO(
        "mysql:host=localhost;dbname={$tenant['bd_nombre']};charset=utf8mb4",
        $tenant['bd_usuario'],
        $tenant['bd_password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Error de conexión al tenant: " . $e->getMessage());
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'crear_usuario') {
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $rol = $_POST['rol'] ?? 'vendedor';
        
        if (empty($nombre) || empty($email) || empty($password)) {
            $error = 'Complete todos los campos obligatorios';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email inválido';
        } elseif (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres';
        } else {
            // Verificar si el email ya existe
            $stmt = $tenant_conn->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Este email ya está registrado';
            } else {
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $tenant_conn->prepare("
                    INSERT INTO usuarios (nombre, email, username, password, user_rol, activo, created_at)
                    VALUES (?, ?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$nombre, $email, $username ?: $email, $password_hash, $rol]);
                
                registrarLog($tenant_id, 'usuario_creado', "Super Admin creó usuario: $nombre ($rol) en tenant {$tenant['nombre']}");
                $exito = "Usuario $nombre creado exitosamente";
            }
        }
    } elseif ($action === 'editar_usuario') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $activo = intval($_POST['activo'] ?? 1);
        
        if (empty($nombre) || empty($email)) {
            $error = 'Complete todos los campos';
        } else {
            $stmt = $tenant_conn->prepare("UPDATE usuarios SET nombre = ?, email = ?, activo = ? WHERE id = ?");
            $stmt->execute([$nombre, $email, $activo, $user_id]);
            
            registrarLog($tenant_id, 'usuario_editado', "Super Admin editó usuario ID:$user_id en tenant {$tenant['nombre']}");
            $exito = 'Usuario actualizado correctamente';
        }
    } elseif ($action === 'cambiar_password') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $nueva_password = $_POST['nueva_password'] ?? '';
        
        if (strlen($nueva_password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres';
        } else {
            $password_hash = password_hash($nueva_password, PASSWORD_BCRYPT);
            $stmt = $tenant_conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $stmt->execute([$password_hash, $user_id]);
            
            registrarLog($tenant_id, 'password_cambiada', "Super Admin cambió password usuario ID:$user_id en tenant {$tenant['nombre']}");
            $exito = 'Contraseña actualizada exitosamente';
        }
    } elseif ($action === 'eliminar_usuario') {
        $user_id = intval($_POST['user_id'] ?? 0);
        
        // Verificar que no sea el único admin
        $stmt = $tenant_conn->query("SELECT COUNT(*) FROM usuarios WHERE user_rol = 'admin' AND activo = 1");
        $total_admins = $stmt->fetchColumn();
        
        $stmt = $tenant_conn->prepare("SELECT user_rol FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $usuario = $stmt->fetch();
        
        if ($usuario['user_rol'] === 'admin' && $total_admins <= 1) {
            $error = 'No puedes eliminar el único administrador';
        } else {
            $stmt = $tenant_conn->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?");
            $stmt->execute([$user_id]);
            
            registrarLog($tenant_id, 'usuario_desactivado', "Super Admin desactivó usuario ID:$user_id en tenant {$tenant['nombre']}");
            $exito = 'Usuario desactivado correctamente';
        }
    }
}

// Obtener todos los usuarios del tenant
$stmt = $tenant_conn->query("SELECT * FROM usuarios ORDER BY user_rol, nombre");
$usuarios = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios del Cliente - Super Admin</title>
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
            <div class="d-flex align-items-center text-white ms-auto">
                <span class="me-3">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= htmlspecialchars($_SESSION['super_admin_nombre']) ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Salir
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="tenants.php">Clientes</a></li>
                        <li class="breadcrumb-item"><a href="ver_tenant.php?id=<?= $tenant_id ?>"><?= htmlspecialchars($tenant['nombre']) ?></a></li>
                        <li class="breadcrumb-item active">Usuarios</li>
                    </ol>
                </nav>
                <h2 class="fw-bold">
                    <i class="bi bi-people me-2"></i>Usuarios de <?= htmlspecialchars($tenant['nombre']) ?>
                </h2>
                <p class="text-muted">Gestiona los usuarios (Admin, Vendedores, Cajeros) de este cliente</p>
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
            <div class="col-12">
                <div class="card stat-card mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-people me-2"></i>Lista de Usuarios</h5>
                        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevo">
                            <i class="bi bi-plus-circle me-1"></i>Nuevo Usuario
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Email / Usuario</th>
                                        <th>Rol</th>
                                        <th>Estado</th>
                                        <th>Creado</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><strong>#<?= $usuario['id'] ?></strong></td>
                                        <td><?= htmlspecialchars($usuario['nombre']) ?></td>
                                        <td>
                                            <small>
                                                <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($usuario['email']) ?><br>
                                                <i class="bi bi-person me-1"></i><?= htmlspecialchars($usuario['username']) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php
                                            $badge_colors = [
                                                'admin' => 'danger',
                                                'vendedor' => 'primary',
                                                'cajero' => 'info'
                                            ];
                                            $color = $badge_colors[$usuario['rol']] ?? 'secondary';
                                            $rol_texto = ['admin' => 'Administrador', 'vendedor' => 'Vendedor', 'cajero' => 'Cajero'][$usuario['rol']] ?? $usuario['rol'];
                                            ?>
                                            <span class="badge bg-<?= $color ?>"><?= $rol_texto ?></span>
                                        </td>
                                        <td>
                                            <?php if ($usuario['activo']): ?>
                                                <span class="badge bg-success">Activo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($usuario['created_at'])) ?></td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-warning" onclick="cambiarPassword(<?= $usuario['id'] ?>, '<?= htmlspecialchars($usuario['nombre'], ENT_QUOTES) ?>')">
                                                    <i class="bi bi-key"></i>
                                                </button>
                                                <button class="btn btn-outline-primary" onclick="editarUsuario(<?= htmlspecialchars(json_encode($usuario)) ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php if ($usuario['activo']): ?>
                                                <button class="btn btn-outline-danger" onclick="desactivarUsuario(<?= $usuario['id'] ?>, '<?= htmlspecialchars($usuario['nombre'], ENT_QUOTES) ?>')">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nuevo Usuario -->
    <div class="modal fade" id="modalNuevo" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="crear_usuario">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Nuevo Usuario</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nombre Completo *</label>
                            <input type="text" class="form-control" name="nombre" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Usuario (opcional)</label>
                            <input type="text" class="form-control" name="username" placeholder="Si se deja vacío, usará el email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contraseña *</label>
                            <input type="password" class="form-control" name="password" required minlength="6">
                            <small class="text-muted">Mínimo 6 caracteres</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rol *</label>
                            <select class="form-select" name="rol" required>
                                <option value="admin">Administrador</option>
                                <option value="vendedor" selected>Vendedor</option>
                                <option value="cajero">Cajero</option>
                            </select>
                            <small class="text-muted">
                                <strong>Admin:</strong> Control total<br>
                                <strong>Vendedor:</strong> Puede agregar productos y vender<br>
                                <strong>Cajero:</strong> Solo puede vender
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Crear Usuario
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="formEditar">
                    <input type="hidden" name="action" value="editar_usuario">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Usuario</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="activo" id="edit_activo">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Cambiar Password -->
    <div class="modal fade" id="modalPassword" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="cambiar_password">
                    <input type="hidden" name="user_id" id="pass_user_id">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title"><i class="bi bi-key me-2"></i>Cambiar Contraseña</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Cambiar contraseña de: <strong id="pass_user_nombre"></strong></p>
                        <div class="mb-3">
                            <label class="form-label">Nueva Contraseña</label>
                            <input type="password" class="form-control" name="nueva_password" required minlength="6">
                            <small class="text-muted">Mínimo 6 caracteres</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Cambiar Contraseña</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editarUsuario(usuario) {
            document.getElementById('edit_user_id').value = usuario.id;
            document.getElementById('edit_nombre').value = usuario.nombre;
            document.getElementById('edit_email').value = usuario.email;
            document.getElementById('edit_activo').value = usuario.activo;
            new bootstrap.Modal(document.getElementById('modalEditar')).show();
        }

        function cambiarPassword(id, nombre) {
            document.getElementById('pass_user_id').value = id;
            document.getElementById('pass_user_nombre').textContent = nombre;
            new bootstrap.Modal(document.getElementById('modalPassword')).show();
        }

        function desactivarUsuario(id, nombre) {
            if (confirm(`¿Desactivar el usuario "${nombre}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="eliminar_usuario">
                    <input type="hidden" name="user_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
