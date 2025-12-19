<?php
require_once '../config/config.php';
require_once '../includes/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/functions.php';
require_once '../includes/session_validator.php';
require_once '../includes/error_handler.php';

require_login();
limpiar_sesion_corrupta();

$page_title = 'Mi Perfil';
$db = Database::getInstance()->getConnection();
$mensaje = '';
$tipo_mensaje = '';

// Verificar si la columna punto_venta_id existe en usuarios
$has_pv_column = column_exists($db, 'usuarios', 'punto_venta_id');

// Obtener datos del usuario
if ($has_pv_column) {
    $query = "SELECT u.*, pv.nombre as punto_venta_nombre, s.nombre as sucursal_nombre,
              (SELECT COUNT(*) FROM ventas WHERE usuario_id = u.id) as total_ventas,
              (SELECT COALESCE(SUM(total), 0) FROM ventas WHERE usuario_id = u.id) as ventas_total
              FROM usuarios u
              LEFT JOIN puntos_venta pv ON u.punto_venta_id = pv.id
              LEFT JOIN sucursales s ON pv.sucursal_id = s.id
              WHERE u.id = ?";
} else {
    $query = "SELECT u.*, NULL as punto_venta_nombre, NULL as sucursal_nombre,
              (SELECT COUNT(*) FROM ventas WHERE usuario_id = u.id) as total_ventas,
              (SELECT COALESCE(SUM(total), 0) FROM ventas WHERE usuario_id = u.id) as ventas_total
              FROM usuarios u
              WHERE u.id = ?";
}
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_password'])) {
    $password_actual = $_POST['password_actual'];
    $password_nueva = $_POST['password_nueva'];
    $password_confirmar = $_POST['password_confirmar'];
    
    if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
        $mensaje = 'Todos los campos de contraseña son obligatorios';
        $tipo_mensaje = 'danger';
    } elseif ($password_nueva !== $password_confirmar) {
        $mensaje = 'Las contraseñas nuevas no coinciden';
        $tipo_mensaje = 'danger';
    } elseif (strlen($password_nueva) < 6) {
        $mensaje = 'La contraseña debe tener al menos 6 caracteres';
        $tipo_mensaje = 'danger';
    } else {
        // Verificar contraseña actual
        if (password_verify($password_actual, $usuario['password'])) {
            $password_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
            $query = "UPDATE usuarios SET password = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$password_hash, $_SESSION['user_id']])) {
                $mensaje = 'Contraseña actualizada exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al actualizar la contraseña';
                $tipo_mensaje = 'danger';
            }
        } else {
            $mensaje = 'La contraseña actual es incorrecta';
            $tipo_mensaje = 'danger';
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($mensaje); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Información del perfil -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <div class="bg-primary text-white rounded-circle mx-auto d-flex align-items-center justify-content-center" 
                             style="width: 100px; height: 100px; font-size: 3rem;">
                            <i class="bi bi-person-circle"></i>
                        </div>
                    </div>
                    <h5><?php echo htmlspecialchars($usuario['nombre']); ?></h5>
                    <p class="text-muted mb-1"><?php echo htmlspecialchars($usuario['email']); ?></p>
                    <span class="badge bg-primary"><?php echo ucfirst($usuario['user_rol']); ?></span>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-building me-2"></i>Asignación</h6>
                </div>
                <div class="card-body">
                    <?php if ($usuario['punto_venta_nombre']): ?>
                    <p class="mb-1"><strong>Punto de Venta:</strong></p>
                    <p class="text-muted"><?php echo htmlspecialchars($usuario['punto_venta_nombre']); ?></p>
                    
                    <?php if ($usuario['sucursal_nombre']): ?>
                    <p class="mb-1"><strong>Sucursal:</strong></p>
                    <p class="text-muted"><?php echo htmlspecialchars($usuario['sucursal_nombre']); ?></p>
                    <?php endif; ?>
                    <?php else: ?>
                    <p class="text-muted">Sin asignación</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Estadísticas</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-12 mb-3">
                            <h3 class="text-primary mb-0"><?php echo number_format($usuario['total_ventas']); ?></h3>
                            <small class="text-muted">Total de Ventas</small>
                        </div>
                        <div class="col-12">
                            <h3 class="text-success mb-0">$<?php echo number_format($usuario['ventas_total'], 2); ?></h3>
                            <small class="text-muted">Monto Total</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formularios de edición -->
        <div class="col-md-8">
            <!-- Información de usuario (solo lectura) -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Información del Usuario</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Nombre Completo</label>
                            <p class="form-control-plaintext fw-bold"><?php echo htmlspecialchars($usuario['nombre']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Email</label>
                            <p class="form-control-plaintext fw-bold"><?php echo htmlspecialchars($usuario['email']); ?></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Usuario</label>
                            <p class="form-control-plaintext fw-bold"><?php echo htmlspecialchars($usuario['username'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Rol</label>
                            <p class="form-control-plaintext">
                                <span class="badge bg-<?php echo $usuario['user_rol'] === 'admin' ? 'danger' : ($usuario['user_rol'] === 'cajero' ? 'info' : 'success'); ?>">
                                    <?php echo $usuario['user_rol'] === 'admin' ? 'Administrador' : ($usuario['user_rol'] === 'cajero' ? 'Cajero' : 'Colaborador'); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle me-2"></i>
                        <small>Para modificar tus datos personales, contacta al administrador del sistema.</small>
                    </div>
                </div>
            </div>

            <!-- Cambiar contraseña -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Cambiar Contraseña</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Contraseña Actual</label>
                            <input type="password" name="password_actual" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nueva Contraseña</label>
                            <input type="password" name="password_nueva" class="form-control" 
                                   minlength="6" required>
                            <small class="text-muted">Mínimo 6 caracteres</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Confirmar Nueva Contraseña</label>
                            <input type="password" name="password_confirmar" class="form-control" 
                                   minlength="6" required>
                        </div>

                        <button type="submit" name="cambiar_password" class="btn btn-warning">
                            <i class="bi bi-key me-2"></i>Cambiar Contraseña
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
