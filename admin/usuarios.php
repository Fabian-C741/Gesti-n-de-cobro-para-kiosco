<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();
require_once '../includes/functions.php';

require_admin();

$db = Database::getInstance()->getConnection();
$error = '';
$success = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'crear') {
            $nombre = sanitize_input($_POST['nombre'] ?? '');
            $email = sanitize_input($_POST['email'] ?? '');
            $username = sanitize_input($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $rol = trim($_POST['rol'] ?? 'vendedor');
            // Asignar automáticamente el punto de venta del admin que crea el usuario
            $punto_venta_id = $_SESSION['punto_venta_id'] ?? null;
            
            if (empty($nombre) || empty($email) || empty($username) || empty($password)) {
                $error = 'Todos los campos son obligatorios';
            } elseif (!validate_email($email)) {
                $error = 'Email inválido';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                $error = 'El username solo puede contener letras, números y guión bajo';
            } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
                $error = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
            } elseif (!in_array($rol, ['admin', 'vendedor', 'cajero'])) {
                $error = 'Rol inválido';
            } else {
                // Verificar si el email ya existe
                $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->fetch()) {
                    $error = 'El email ya está registrado';
                } else {
                    // Verificar si el username ya existe
                    $stmt = $db->prepare("SELECT id FROM usuarios WHERE username = ?");
                    $stmt->execute([$username]);
                    
                    if ($stmt->fetch()) {
                        $error = 'El username ya está en uso';
                    } else {
                        try {
                            $stmt = $db->prepare("
                                INSERT INTO usuarios (nombre, email, username, password, user_rol, punto_venta_id, token_usado) 
                                VALUES (?, ?, ?, ?, ?, ?, 1)
                            ");
                            $stmt->execute([$nombre, $email, $username, hash_password($password), $rol, $punto_venta_id]);
                            
                            log_activity($db, $_SESSION['user_id'], 'crear_usuario', "Usuario creado: $email (Rol: $rol)");
                            $success = 'Usuario creado exitosamente';
                        } catch (PDOException $e) {
                            $error = 'Error al crear el usuario';
                        }
                    }
                }
            }
        } elseif ($action === 'editar') {
            $id = intval($_POST['id'] ?? 0);
            $nombre = sanitize_input($_POST['nombre'] ?? '');
            $email = sanitize_input($_POST['email'] ?? '');
            $username = sanitize_input($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $rol = trim($_POST['rol'] ?? 'vendedor');
            // Mantener el punto de venta del admin (no se puede cambiar desde aquí)
            $punto_venta_id = $_SESSION['punto_venta_id'] ?? null;
            $activo = isset($_POST['activo']) ? 1 : 0;
            
            if (empty($nombre) || empty($email) || empty($username)) {
                $error = 'Nombre, email y username son obligatorios';
            } elseif (!validate_email($email)) {
                $error = 'Email inválido';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                $error = 'El username solo puede contener letras, números y guión bajo';
            } elseif (!in_array($rol, ['admin', 'vendedor', 'cajero'])) {
                $error = 'Rol inválido';
            } else {
                try {
                    if (!empty($password)) {
                        if (strlen($password) < PASSWORD_MIN_LENGTH) {
                            $error = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
                        } else {
                            $stmt = $db->prepare("
                                UPDATE usuarios 
                                SET nombre = ?, email = ?, username = ?, password = ?, user_rol = ?, punto_venta_id = ?, activo = ? 
                                WHERE id = ?
                            ");
                            $stmt->execute([$nombre, $email, $username, hash_password($password), $rol, $punto_venta_id, $activo, $id]);
                        }
                    } else {
                        $stmt = $db->prepare("
                            UPDATE usuarios 
                            SET nombre = ?, email = ?, username = ?, user_rol = ?, punto_venta_id = ?, activo = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$nombre, $email, $username, $rol, $punto_venta_id, $activo, $id]);
                    }
                    
                    log_activity($db, $_SESSION['user_id'], 'editar_usuario', "Usuario editado: $email (Rol: $rol)");
                    $success = 'Usuario actualizado exitosamente';
                } catch (PDOException $e) {
                    $error = 'Error al actualizar el usuario';
                }
            }
        } elseif ($action === 'eliminar') {
            $id = intval($_POST['id'] ?? 0);
            
            try {
                $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
                
                log_activity($db, $_SESSION['user_id'], 'eliminar_usuario', "Usuario eliminado ID: $id");
                $success = 'Usuario eliminado exitosamente';
            } catch (PDOException $e) {
                $error = 'Error al eliminar el usuario';
            }
        } elseif ($action === 'restablecer_password') {
            $id = intval($_POST['id'] ?? 0);
            $nueva_password = $_POST['nueva_password'] ?? '';
            
            if (strlen($nueva_password) < PASSWORD_MIN_LENGTH) {
                $error = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
            } else {
                try {
                    $stmt = $db->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                    $stmt->execute([hash_password($nueva_password), $id]);
                    
                    // Obtener info del usuario para el log
                    $stmt = $db->prepare("SELECT username FROM usuarios WHERE id = ?");
                    $stmt->execute([$id]);
                    $usuario = $stmt->fetch();
                    
                    log_activity($db, $_SESSION['user_id'], 'restablecer_password', "Contraseña restablecida para: " . $usuario['username']);
                    $success = 'Contraseña restablecida exitosamente';
                } catch (PDOException $e) {
                    $error = 'Error al restablecer la contraseña';
                }
            }
        }
    }
}

// Obtener puntos de venta
$stmt = $db->query("SELECT id, codigo, nombre FROM puntos_venta WHERE activo = 1 ORDER BY nombre");
$puntos_venta = $stmt->fetchAll();

// Obtener lista de usuarios
$stmt = $db->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM productos WHERE usuario_id = u.id) as total_productos,
           (SELECT COUNT(*) FROM ventas WHERE usuario_id = u.id) as total_ventas
    FROM usuarios u
    ORDER BY u.user_rol, u.fecha_creacion DESC
");
$usuarios = $stmt->fetchAll();

$page_title = 'Gestión de Usuarios';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-people me-2"></i>Gestión de Usuarios</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
            <i class="bi bi-person-plus me-1"></i> Nuevo Usuario
        </button>
    </div>

    <?php if ($error): ?>
        <?php echo show_alert($error, 'error'); ?>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <?php echo show_alert($success, 'success'); ?>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="tablaUsuarios">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Sucursal</th>
                            <th>Punto Venta</th>
                            <th>Productos</th>
                            <th>Ventas</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td><?php echo $usuario['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($usuario['nombre']); ?></strong></td>
                                <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                <td>
                                    <?php 
                                    $rol = $usuario['user_rol'] ?? 'vendedor';
                                    $badgeColor = $rol === 'admin' ? 'danger' : ($rol === 'cajero' ? 'info' : 'primary');
                                    $rolTexto = $rol === 'admin' ? 'Administrador' : ($rol === 'cajero' ? 'Cajero' : 'Vendedor');
                                    ?>
                                    <span class="badge bg-<?php echo $badgeColor; ?>"><?php echo $rolTexto; ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($usuario['sucursal'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($usuario['punto_venta']): ?>
                                        <code><?php echo htmlspecialchars($usuario['punto_venta']); ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">Sin asignar</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-info"><?php echo $usuario['total_productos']; ?></span></td>
                                <td><span class="badge bg-success"><?php echo $usuario['total_ventas']; ?></span></td>
                                <td>
                                    <?php if ($usuario['activo']): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editarUsuario(<?php echo htmlspecialchars(json_encode($usuario)); ?>)" title="Editar usuario">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-info" onclick="restablecerPassword(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['nombre']); ?>')" title="Restablecer contraseña">
                                        <i class="bi bi-key"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="eliminarUsuario(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['nombre']); ?>')" title="Eliminar usuario">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Crear Usuario -->
<div class="modal fade" id="modalCrear" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="crear">
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre Completo *</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre de Usuario (Username) *</label>
                        <input type="text" name="username" class="form-control" required pattern="[a-zA-Z0-9_]+" title="Solo letras, números y guión bajo">
                        <small class="text-muted">Solo letras, números y guión bajo (_)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Contraseña *</label>
                        <input type="password" name="password" class="form-control" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
                        <small class="text-muted">Mínimo <?php echo PASSWORD_MIN_LENGTH; ?> caracteres</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Rol *</label>
                        <select name="rol" id="crear_rol" class="form-select" required>
                            <option value="">-- Seleccionar Rol --</option>
                            <option value="admin">Administrador</option>
                            <option value="vendedor">Vendedor</option>
                            <option value="cajero">Cajero</option>
                        </select>
                        <small class="text-muted">
                            <strong>Admin:</strong> Acceso total al sistema<br>
                            <strong>Vendedor:</strong> Gestión completa de productos y ventas<br>
                            <strong>Cajero:</strong> Solo realizar ventas
                        </small>
                    </div>
                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Usuario -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="editar">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre Completo *</label>
                        <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre de Usuario (Username) *</label>
                        <input type="text" name="username" id="edit_username" class="form-control" required pattern="[a-zA-Z0-9_]+" title="Solo letras, números y guión bajo">
                        <small class="text-muted">Solo letras, números y guión bajo (_)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nueva Contraseña</label>
                        <input type="password" name="password" class="form-control" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                        <small class="text-muted">Dejar en blanco para mantener la actual</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Rol *</label>
                        <select name="rol" id="edit_rol" class="form-select" required>
                            <option value="vendedor">Vendedor</option>
                            <option value="cajero">Cajero</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="activo" id="edit_activo" class="form-check-input">
                            <label class="form-check-label" for="edit_activo">Usuario Activo</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Eliminar -->
<div class="modal fade" id="modalEliminar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Eliminar Usuario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="eliminar">
                    <input type="hidden" name="id" id="delete_id">
                    
                    <p>¿Está seguro de eliminar al usuario <strong id="delete_nombre"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Esta acción eliminará todos los productos y ventas asociados.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Restablecer Contraseña -->
<div class="modal fade" id="modalRestablecer" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bi bi-key me-2"></i>Restablecer Contraseña</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="restablecer_password">
                    <input type="hidden" name="id" id="reset_id">
                    
                    <p>Restablecer contraseña para: <strong id="reset_nombre"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">Nueva Contraseña</label>
                        <input type="password" name="nueva_password" class="form-control" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                        <small class="text-muted">Mínimo <?php echo PASSWORD_MIN_LENGTH; ?> caracteres</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-1"></i>
                        El usuario deberá usar esta nueva contraseña para iniciar sesión.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-info">Restablecer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editarUsuario(usuario) {
    document.getElementById('edit_id').value = usuario.id;
    document.getElementById('edit_nombre').value = usuario.nombre;
    document.getElementById('edit_email').value = usuario.email;
    document.getElementById('edit_username').value = usuario.username || '';
    document.getElementById('edit_rol').value = usuario.rol || 'vendedor';
    document.getElementById('edit_activo').checked = usuario.activo == 1;
    
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}

function eliminarUsuario(id, nombre) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_nombre').textContent = nombre;
    
    new bootstrap.Modal(document.getElementById('modalEliminar')).show();
}

function restablecerPassword(id, nombre) {
    document.getElementById('reset_id').value = id;
    document.getElementById('reset_nombre').textContent = nombre;
    
    new bootstrap.Modal(document.getElementById('modalRestablecer')).show();
}
</script>

<?php include 'includes/footer.php'; ?>
