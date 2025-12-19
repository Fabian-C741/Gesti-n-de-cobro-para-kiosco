<?php
/**
 * Login Multi-Tenant
 * Permite a los clientes acceder a su instancia específica
 */

// Cargar variables de entorno
require_once __DIR__ . '/config/env.php';

// Conectar a la BD maestra desde variables de entorno
$DB_HOST = Env::get('DB_HOST_MASTER', 'localhost');
$DB_NAME = Env::get('DB_NAME_MASTER', 'gestion_cobros');
$DB_USER = Env::get('DB_USER_MASTER', 'root');
$DB_PASS = Env::get('DB_PASS_MASTER', '');

try {
    $conn_master = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

session_start();

$error = '';
$mensaje = '';

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    $mensaje = 'Sesión cerrada correctamente';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dominio = trim(strtolower($_POST['dominio'] ?? ''));
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($dominio) || empty($usuario) || empty($password)) {
        $error = 'Complete todos los campos';
    } else {
        try {
            // Buscar tenant por dominio
            $stmt = $conn_master->prepare("
                SELECT * FROM tenants 
                WHERE dominio = ?
            ");
            $stmt->execute([$dominio]);
            $tenant = $stmt->fetch();
            
            if (!$tenant) {
                $error = 'Dominio no encontrado';
            } elseif (!$tenant['activo']) {
                $error = 'Esta cuenta ha sido desactivada permanentemente. Contacte al administrador';
            } elseif ($tenant['estado'] === 'suspendido') {
                $error = 'Su cuenta está suspendida. Contacte al administrador para más información';
            } elseif ($tenant['estado'] === 'vencido') {
                $error = 'Su suscripción ha vencido. Contacte al administrador para renovar';
            } elseif ($tenant['estado'] === 'cancelado') {
                $error = 'Esta cuenta ha sido cancelada. Contacte al administrador';
            } elseif ($tenant['estado'] !== 'activo') {
                $error = 'Su cuenta no está activa. Contacte al administrador';
            } else {
                // Conectar a la BD del tenant
                try {
                    $conn_tenant = new PDO(
                        "mysql:host={$tenant['bd_host']};dbname={$tenant['bd_nombre']};charset=utf8mb4",
                        $tenant['bd_usuario'],
                        $tenant['bd_password'],
                        [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                        ]
                    );
                    
                    // Buscar usuario (puede ser por email o username, compatible con/sin punto_venta_id)
                    $stmt = $conn_tenant->prepare("
                        SELECT * 
                        FROM usuarios 
                        WHERE (email = ? OR username = ?) AND activo = 1
                    ");
                    $stmt->execute([$usuario, $usuario]);
                    $user = $stmt->fetch();
                    
                    if (!$user) {
                        $error = 'Usuario o contraseña incorrectos';
                    } elseif (!password_verify($password, $user['password'])) {
                        $error = 'Usuario o contraseña incorrectos';
                    } else {
                        // Login exitoso
                        $_SESSION['tenant_id'] = $tenant['id'];
                        $_SESSION['tenant_dominio'] = $tenant['dominio'];
                        $_SESSION['tenant_nombre'] = $tenant['nombre'];
                        $_SESSION['tenant_bd'] = $tenant['bd_nombre'];
                        $_SESSION['tenant_bd_host'] = $tenant['bd_host'];
                        $_SESSION['tenant_bd_user'] = $tenant['bd_usuario'];
                        $_SESSION['tenant_bd_pass'] = $tenant['bd_password'];
                        
                        // Compatible con columna 'rol' o 'user_rol'
                        $user_rol = $user['user_rol'] ?? $user['rol'] ?? 'vendedor';
                        
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_nombre'] = $user['nombre'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_rol'] = $user_rol;
                        $_SESSION['login_time'] = time();
                        $_SESSION['punto_venta_id'] = $user['punto_venta_id'] ?? null;
                        
                        // Redirigir según rol
                        if ($user_rol === 'admin') {
                            header('Location: admin/dashboard.php');
                        } elseif ($user_rol === 'cajero') {
                            header('Location: cajero/index.php');
                        } else {
                            header('Location: vendedor/dashboard.php');
                        }
                        exit;
                    }
                } catch (PDOException $e) {
                    $error = 'Error al conectar con su base de datos. Contacte al administrador';
                }
            }
        } catch (PDOException $e) {
            $error = 'Error al procesar la solicitud';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Multi-Tenant - Sistema POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .login-body {
            padding: 2rem;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
        }
        .btn-login:hover {
            opacity: 0.9;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-top: 20px;
            border-radius: 8px;
        }
        .admin-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="bi bi-shop" style="font-size: 3rem;"></i>
            <h3 class="mt-3 mb-0">Sistema POS Multi-Tenant</h3>
            <p class="mb-0 opacity-75">Acceso para Clientes</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($mensaje): ?>
                <div class="alert alert-success" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($mensaje) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="dominio" class="form-label">Dominio de su Negocio</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-building"></i></span>
                        <input type="text" class="form-control" id="dominio" name="dominio" 
                               placeholder="minegocio" required autofocus>
                    </div>
                    <small class="text-muted">Ej: si su negocio es "Mi Kiosco", ingrese: mikiosco</small>
                </div>
                
                <div class="mb-3">
                    <label for="usuario" class="form-label">Usuario o Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                        <input type="text" class="form-control" id="usuario" name="usuario" 
                               placeholder="usuario o email@ejemplo.com" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-login w-100">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Acceder a Mi Sistema
                </button>
            </form>
            
            <div class="info-box">
                <strong><i class="bi bi-info-circle-fill me-2"></i>¿No tienes cuenta?</strong><br>
                <small>Contacta al administrador para crear tu negocio en el sistema SaaS</small>
            </div>
            
            <div class="admin-link">
                <a href="https://wa.me/5491234567890?text=Hola%2C%20necesito%20ayuda%20con%20mi%20cuenta%20del%20sistema%20POS" 
                   target="_blank" 
                   class="btn btn-success w-100">
                    <i class="bi bi-whatsapp me-2"></i>Solicitar Soporte por WhatsApp
                </a>
                <p class="text-muted mt-2 mb-0 small">
                    ¿No tienes usuario? ¿Olvidaste tu contraseña? ¡Contáctanos!
                </p>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-formatear dominio (solo letras minúsculas y números)
        document.getElementById('dominio').addEventListener('input', function(e) {
            this.value = this.value.toLowerCase().replace(/[^a-z0-9]/g, '');
        });
    </script>
</body>
</html>
