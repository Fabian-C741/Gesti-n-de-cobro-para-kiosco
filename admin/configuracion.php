<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();
require_once '../includes/functions.php';

require_admin();

$db = Database::getInstance()->getConnection();
$error = '';
$success = '';

// Obtener datos actuales del usuario
$stmt = $db->prepare("SELECT nombre, email, username FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$usuario_actual = $stmt->fetch();

// Obtener configuración del sistema
$config_sistema = ['nombre_app' => 'Sistema de Gestión de Cobros', 'version_app' => '1.0.0'];
try {
    $stmt = $db->prepare("SELECT clave, valor FROM configuracion_sistema WHERE clave IN ('nombre_app', 'version_app')");
    $stmt->execute();
    $config_rows = $stmt->fetchAll();
    foreach ($config_rows as $row) {
        $config_sistema[$row['clave']] = $row['valor'];
    }
} catch (Exception $e) {
    // Si la tabla no existe, usar valores por defecto
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'cambiar_nombre_app') {
        // Cambiar nombre de la aplicación
        $nuevo_nombre = sanitize_input($_POST['nombre_app'] ?? '');
        
        if (empty($nuevo_nombre)) {
            $error = 'El nombre de la aplicación no puede estar vacío';
        } else {
            try {
                $stmt = $db->prepare("UPDATE configuracion_sistema SET valor = ? WHERE clave = 'nombre_app'");
                if ($stmt->execute([$nuevo_nombre])) {
                    $config_sistema['nombre_app'] = $nuevo_nombre;
                    log_activity($db, $_SESSION['user_id'], 'configuracion', 'Cambió el nombre de la aplicación a: ' . $nuevo_nombre);
                    $success = 'Nombre de la aplicación actualizado correctamente';
                } else {
                    $error = 'Error al actualizar el nombre de la aplicación';
                }
            } catch (Exception $e) {
                $error = 'Error: La tabla de configuración no existe. Ejecuta el SQL de add_settings_table.sql';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'cambiar_email') {
        // Cambio de email y username
        $nuevo_email = sanitize_input($_POST['nuevo_email'] ?? '');
        $nuevo_username = sanitize_input($_POST['nuevo_username'] ?? '');
        $password_confirmacion = $_POST['password_confirmacion'] ?? '';
        
        if (empty($nuevo_email) || empty($nuevo_username)) {
            $error = 'Todos los campos son obligatorios';
        } elseif (!validate_email($nuevo_email)) {
            $error = 'Email inválido';
        } elseif (strlen($nuevo_username) < 3 || strlen($nuevo_username) > 50) {
            $error = 'El nombre de usuario debe tener entre 3 y 50 caracteres';
        } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $nuevo_username)) {
            $error = 'El nombre de usuario solo puede contener letras, números, guiones y guiones bajos';
        } else {
            // Verificar contraseña actual
            $stmt = $db->prepare("SELECT password FROM usuarios WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user_data = $stmt->fetch();
            
            if (!verify_password($password_confirmacion, $user_data['password'])) {
                $error = 'La contraseña es incorrecta';
            } else {
                // Verificar si el email ya existe
                $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                $stmt->execute([$nuevo_email, $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    $error = 'Este email ya está en uso';
                } else {
                    // Verificar si el username ya existe
                    $stmt = $db->prepare("SELECT id FROM usuarios WHERE username = ? AND id != ?");
                    $stmt->execute([$nuevo_username, $_SESSION['user_id']]);
                    if ($stmt->fetch()) {
                        $error = 'Este nombre de usuario ya está en uso';
                    } else {
                        // Actualizar email y username
                        $stmt = $db->prepare("UPDATE usuarios SET email = ?, username = ? WHERE id = ?");
                        $stmt->execute([$nuevo_email, $nuevo_username, $_SESSION['user_id']]);
                        
                        $_SESSION['user_email'] = $nuevo_email;
                        
                        log_activity($db, $_SESSION['user_id'], 'cambio_email', "Email cambiado a: $nuevo_email, Username: $nuevo_username");
                        $success = 'Email y nombre de usuario actualizados exitosamente';
                        
                        // Recargar datos
                        $stmt = $db->prepare("SELECT nombre, email, username FROM usuarios WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $usuario_actual = $stmt->fetch();
                    }
                }
            }
        }
    } else {
        // Cambio de contraseña
        $password_actual = $_POST['password_actual'] ?? '';
        $password_nueva = $_POST['password_nueva'] ?? '';
        $password_confirmar = $_POST['password_confirmar'] ?? '';
        
        if (empty($password_actual) || empty($password_nueva)) {
            $error = 'Todos los campos son obligatorios';
        } elseif ($password_nueva !== $password_confirmar) {
            $error = 'Las contraseñas nuevas no coinciden';
        } elseif (strlen($password_nueva) < PASSWORD_MIN_LENGTH) {
            $error = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
        } else {
            // Verificar contraseña actual
            $stmt = $db->prepare("SELECT password FROM usuarios WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $usuario = $stmt->fetch();
            
            if (!verify_password($password_actual, $usuario['password'])) {
                $error = 'La contraseña actual es incorrecta';
            } else {
                // Actualizar contraseña
                $nueva_hash = password_hash($password_nueva, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                $stmt->execute([$nueva_hash, $_SESSION['user_id']]);
                
                log_activity($db, $_SESSION['user_id'], 'cambio_password', 'Contraseña actualizada');
                $success = 'Contraseña actualizada exitosamente';
            }
        }
    }
}

$page_title = 'Configuración';
include 'includes/header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4"><i class="bi bi-gear"></i> Configuración del Sistema</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Nombre de la Aplicación -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-app-indicator"></i> Nombre de la Aplicación</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="cambiar_nombre_app">
                        
                        <div class="mb-3">
                            <label for="nombre_app" class="form-label">Nombre Actual</label>
                            <input type="text" class="form-control" id="nombre_app" name="nombre_app" 
                                   value="<?php echo htmlspecialchars($config_sistema['nombre_app'] ?? 'Sistema de Gestión de Cobros'); ?>" 
                                   required maxlength="100">
                            <div class="form-text">Este nombre aparecerá en el encabezado del sistema</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Guardar Cambios
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Cambio de email y username -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-envelope"></i> Cambiar Email y Usuario</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="cambiar_email">
                        
                        <div class="mb-3">
                            <label class="form-label">Email Actual</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($usuario_actual['email']); ?>" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nuevo Email</label>
                            <input type="email" class="form-control" name="nuevo_email" value="<?php echo htmlspecialchars($usuario_actual['email']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nombre de Usuario</label>
                            <input type="text" class="form-control" name="nuevo_username" value="<?php echo htmlspecialchars($usuario_actual['username'] ?? ''); ?>" required pattern="[a-zA-Z0-9_-]{3,50}">
                            <small class="text-muted">Solo letras, números, - y _ (3-50 caracteres)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Contraseña Actual (para confirmar)</label>
                            <input type="password" class="form-control" name="password_confirmacion" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Actualizar Datos
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-key"></i> Cambiar Contraseña</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Contraseña Actual</label>
                            <input type="password" class="form-control" name="password_actual" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nueva Contraseña</label>
                            <input type="password" class="form-control" name="password_nueva" required>
                            <small class="text-muted">Mínimo <?php echo PASSWORD_MIN_LENGTH; ?> caracteres</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirmar Nueva Contraseña</label>
                            <input type="password" class="form-control" name="password_confirmar" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Actualizar Contraseña
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Información del sistema -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-info-circle"></i> Información del Sistema</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Versión:</strong></td>
                            <td>1.0.0</td>
                        </tr>
                        <tr>
                            <td><strong>PHP:</strong></td>
                            <td><?php echo phpversion(); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Base de Datos:</strong></td>
                            <td><?php 
                                try {
                                    echo $db->getAttribute(PDO::ATTR_SERVER_VERSION);
                                } catch (Exception $e) {
                                    echo 'N/A';
                                }
                            ?></td>
                        </tr>
                        <tr>
                            <td><strong>Usuario:</strong></td>
                            <td><?php echo htmlspecialchars($_SESSION['user_nombre'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td><?php echo htmlspecialchars($usuario_actual['email']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Usuario:</strong></td>
                            <td><?php echo htmlspecialchars($usuario_actual['username'] ?? 'No configurado'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5><i class="bi bi-shield-check"></i> Seguridad</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li><i class="bi bi-check-circle text-success"></i> Protección CSRF activa</li>
                        <li><i class="bi bi-check-circle text-success"></i> Rate limiting activo</li>
                        <li><i class="bi bi-check-circle text-success"></i> Anti-brute force activo</li>
                        <li><i class="bi bi-check-circle text-success"></i> Sesiones seguras</li>
                        <li><i class="bi bi-check-circle text-success"></i> Logs de actividad</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
