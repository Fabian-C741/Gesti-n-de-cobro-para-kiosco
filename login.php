<?php
require_once 'config/config.php';
require_once 'includes/Database.php';
session_start();
require_once 'includes/functions.php';
require_once 'includes/security.php';

// ============================================
// VERIFICAR ESTADO DEL TENANT (Sistema SaaS)
// ============================================
// Conectar a la BD maestra para verificar si este sistema está suspendido
$tenant_suspendido = false;
$mensaje_suspension = '';

try {
    $DB_HOST_MASTER = Env::get('DB_HOST_MASTER', '');
    $DB_NAME_MASTER = Env::get('DB_NAME_MASTER', '');
    $DB_USER_MASTER = Env::get('DB_USER_MASTER', '');
    $DB_PASS_MASTER = Env::get('DB_PASS_MASTER', '');
    
    // Solo verificar si hay configuración de BD maestra
    if (!empty($DB_NAME_MASTER) && !empty($DB_USER_MASTER)) {
        $conn_master = new PDO(
            "mysql:host={$DB_HOST_MASTER};dbname={$DB_NAME_MASTER};charset=utf8mb4",
            $DB_USER_MASTER,
            $DB_PASS_MASTER,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Buscar este sistema en la tabla de tenants por nombre de BD
        $bd_actual = DB_NAME;
        $stmt = $conn_master->prepare("SELECT estado, nombre FROM tenants WHERE bd_nombre = ? LIMIT 1");
        $stmt->execute([$bd_actual]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tenant) {
            if ($tenant['estado'] === 'suspendido') {
                $tenant_suspendido = true;
                $mensaje_suspension = 'Este sistema está <strong>SUSPENDIDO</strong>. Contacte al administrador para más información.';
            } elseif ($tenant['estado'] === 'vencido') {
                $tenant_suspendido = true;
                $mensaje_suspension = 'La suscripción de este sistema ha <strong>VENCIDO</strong>. Contacte al administrador para renovar.';
            } elseif ($tenant['estado'] === 'cancelado') {
                $tenant_suspendido = true;
                $mensaje_suspension = 'Este sistema ha sido <strong>CANCELADO</strong>. Contacte al administrador.';
            }
        }
    }
} catch (PDOException $e) {
    // Si falla la conexión a la BD maestra, continuar normalmente
    // (puede ser un sistema standalone sin multi-tenant)
}

// Si el tenant está suspendido, mostrar página de error
if ($tenant_suspendido) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sistema Suspendido</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .error-card {
                background: white;
                border-radius: 20px;
                padding: 40px;
                text-align: center;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                max-width: 500px;
            }
            .error-icon {
                font-size: 80px;
                color: #dc3545;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="error-icon">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <h2 class="mb-3">Acceso No Disponible</h2>
            <p class="text-muted mb-4"><?php echo $mensaje_suspension; ?></p>
            <hr>
            <p class="small text-muted mb-0">
                <i class="bi bi-envelope me-1"></i> Si cree que esto es un error, contacte al soporte técnico.
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
// ============================================

// Cargar personalización
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT clave, valor FROM personalizacion");
$personalizacion = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Cargar configuración del sistema (logo, imagen login, etc.)
$stmt = $db->query("SELECT clave, valor FROM configuracion_sistema");
$config_sistema = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Obtener IP del cliente
$client_ip = get_client_ip();

// Verificar lista negra de IPs
$db = Database::getInstance()->getConnection();
if (!check_ip_blacklist($db, $client_ip)) {
    die('Acceso denegado. Su IP ha sido bloqueada por actividad sospechosa.');
}

// Rate limiting básico
if (!check_rate_limit($db, $client_ip)) {
    log_security_event($db, 'rate_limit_exceeded', $client_ip, 'Demasiadas peticiones a login.php');
    http_response_code(429);
    die('Demasiadas peticiones. Por favor, intente más tarde.');
}

// Si ya está autenticado, redirigir según rol
if (is_logged_in()) {
    if (is_admin()) {
        redirect('admin/dashboard.php');
    } elseif ($_SESSION['user_rol'] === 'cajero') {
        redirect('cajero/index.php');
    } else {
        redirect('vendedor/dashboard.php');
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Token de seguridad inválido';
        log_security_event($db, 'csrf_token_invalid', $client_ip, 'Token CSRF inválido en login');
    } else {
        $login_input = sanitize_input($_POST['email'] ?? ''); // Puede ser email o username
        $password = $_POST['password'] ?? '';
        $token_acceso = sanitize_input($_POST['token_acceso'] ?? '');
        
        // Detectar patrones de ataque
        if (detect_attack_patterns($login_input . $password . $token_acceso)) {
            log_security_event($db, 'attack_pattern_detected', $client_ip, "Patrón de ataque en login");
            add_to_blacklist($db, $client_ip, 'Patrón de ataque detectado', 24);
            die('Acceso denegado.');
        }
        
        if (empty($login_input) || empty($password)) {
            $error = 'Por favor complete todos los campos';
        } else {
            try {
                
                // Si es primera vez con token
                if (!empty($token_acceso)) {
                    // Verificar si el token existe y no ha sido usado
                    $stmt = $db->prepare("SELECT id, usado FROM tokens_acceso WHERE token = ?");
                    $stmt->execute([$token_acceso]);
                    $token_data = $stmt->fetch();
                    
                    if (!$token_data) {
                        $error = 'Token de acceso inválido';
                    } elseif ($token_data['usado'] == 1) {
                        $error = 'Este token ya ha sido utilizado. Solicite un nuevo token al administrador';
                    } else {
                        // Validar formato de email
                        if (!filter_var($login_input, FILTER_VALIDATE_EMAIL)) {
                            $error = 'Por favor ingrese un email válido';
                        } else {
                            // Verificar si el email ya existe
                            $stmt = $db->prepare("SELECT id, nombre FROM usuarios WHERE email = ?");
                            $stmt->execute([$login_input]);
                            $email_existente = $stmt->fetch();
                            
                            if ($email_existente) {
                                $error = 'Este email ya está registrado. Si olvidó su contraseña, contacte al administrador';
                            } else {
                                // Verificar si el username ya existe
                                $username = sanitize_input($_POST['username'] ?? '');
                                
                                if (!empty($username)) {
                                    $stmt = $db->prepare("SELECT id FROM usuarios WHERE username = ?");
                                    $stmt->execute([$username]);
                                    if ($stmt->fetch()) {
                                        $error = 'Este nombre de usuario ya está en uso. Por favor elija otro';
                                    }
                                }
                                
                                if (empty($error)) {
                                    // Crear nuevo usuario
                                    $nombre = sanitize_input($_POST['nombre'] ?? '');
                                    
                                    if (empty($nombre)) {
                                        $error = 'Por favor ingrese su nombre';
                                    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
                                        $error = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
                                    } else {
                                        $db->beginTransaction();
                                        
                                        try {
                                            if (empty($username)) {
                                                $error = 'Por favor ingrese un nombre de usuario';
                                                $db->rollBack();
                                            } elseif (!preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $username)) {
                                                $error = 'El nombre de usuario solo puede contener letras, números, - y _ (3-50 caracteres)';
                                                $db->rollBack();
                                            } else {
                                                // Crear usuario
                                                $stmt = $db->prepare("
                                                    INSERT INTO usuarios (nombre, email, username, password, token_acceso, token_usado, user_rol) 
                                                    VALUES (?, ?, ?, ?, ?, 1, 'vendedor')
                                                ");
                                                $stmt->execute([$nombre, $login_input, $username, hash_password($password), $token_acceso]);
                                                $usuario_id = $db->lastInsertId();
                                                
                                                // Marcar token como usado
                                                $stmt = $db->prepare("
                                                    UPDATE tokens_acceso 
                                                    SET usado = 1, usuario_id = ?, fecha_uso = NOW() 
                                                    WHERE token = ?
                                                ");
                                                $stmt->execute([$usuario_id, $token_acceso]);
                                                
                                                $db->commit();
                                                
                                                log_activity($db, $usuario_id, 'registro', 'Usuario registrado con token');
                                                log_security_event($db, 'user_registered', $client_ip, "Nuevo usuario: $login_input");
                                                
                                                $success = 'Cuenta creada exitosamente. Ahora puede iniciar sesión';
                                            }
                                        } catch (PDOException $e) {
                                            $db->rollBack();
                                            $error = 'Error al crear la cuenta. Intente nuevamente';
                                            log_security_event($db, 'registration_error', $client_ip, "Error: " . $e->getMessage());
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else {
                    // Login normal
                    
                    // Verificar intentos de login
                    $check_attempts = check_login_attempts($db, $login_input);
                    if (!$check_attempts['allowed']) {
                        $error = $check_attempts['message'];
                        log_security_event($db, 'login_blocked', $client_ip, "Login: $login_input - " . $check_attempts['message']);
                    } else {
                        // Buscar por email o username
                        $stmt = $db->prepare("
                            SELECT id, nombre, email, username, password, user_rol, activo 
                            FROM usuarios 
                            WHERE (email = ? OR username = ?) AND activo = 1
                        ");
                        $stmt->execute([$login_input, $login_input]);
                        $usuario = $stmt->fetch();
                        
                        if (!$usuario) {
                            $error = 'Usuario/Email o contraseña incorrectos';
                            record_failed_login($db, $login_input);
                            log_security_event($db, 'login_failed', $client_ip, "Usuario/Email no encontrado: $login_input");
                        } elseif (!$usuario['activo']) {
                            $error = 'Su cuenta está desactivada. Contacte al administrador';
                            log_security_event($db, 'login_attempt_inactive', $client_ip, "Login: $login_input");
                        } elseif (!verify_password($password, $usuario['password'])) {
                            $error = 'Usuario/Email o contraseña incorrectos';
                            record_failed_login($db, $login_input);
                            log_security_event($db, 'login_failed', $client_ip, "Contraseña incorrecta para: $login_input");
                        } else {
                            // Login exitoso - Limpiar intentos fallidos
                            clear_login_attempts($db, $login_input);
                            
                            // Crear sesión
                            $token_sesion = generate_token(128);
                            $fecha_expiracion = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
                            
                            // Guardar sesión en BD
                            $stmt = $db->prepare("
                                INSERT INTO sesiones (usuario_id, token_sesion, ip_address, user_agent, fecha_expiracion) 
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $usuario['id'],
                                $token_sesion,
                                $client_ip,
                                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                                $fecha_expiracion
                            ]);
                            
                            // Establecer variables de sesión
                            $_SESSION['user_id'] = $usuario['id'];
                            $_SESSION['user_nombre'] = $usuario['nombre'];
                            $_SESSION['user_email'] = $usuario['email'];
                            $_SESSION['user_rol'] = $usuario['user_rol'];
                            $_SESSION['token_sesion'] = $token_sesion;
                            $_SESSION['login_ip'] = $client_ip;
                            $_SESSION['login_time'] = time();
                            
                            // Regenerar ID de sesión para prevenir session fixation
                            session_regenerate_id(true);
                            
                            log_activity($db, $usuario['id'], 'login', 'Inicio de sesión exitoso');
                            log_security_event($db, 'login_success', $client_ip, "Usuario: {$usuario['email']}");
                            
                            // Redirigir según rol
                            if ($usuario['user_rol'] === 'admin') {
                                redirect('admin/dashboard.php');
                            } elseif ($usuario['user_rol'] === 'cajero') {
                                redirect('cajero/index.php');
                            } else {
                                redirect('vendedor/dashboard.php');
                            }
                        }
                    }
                }
            } catch (PDOException $e) {
                $error = DEBUG_MODE ? 'Error: ' . $e->getMessage() : 'Error al procesar la solicitud';
            }
        }
    }
}

// Mensajes de URL
if (isset($_GET['error'])) {
    $errors_map = [
        'no_autenticado' => 'Debe iniciar sesión para acceder',
        'sesion_expirada' => 'Su sesión ha expirado',
        'acceso_denegado' => 'No tiene permisos para acceder a esa sección',
        'sesion_invalida' => 'Tu sesión ha expirado. Por favor, inicia sesión nuevamente'
    ];
    $error = $errors_map[$_GET['error']] ?? 'Error desconocido';
}

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'logout') {
        $success = 'Sesión cerrada correctamente';
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?php echo APP_NAME; ?></title>
    <?php if (!empty($config_sistema['favicon'])): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($config_sistema['favicon']); ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <?php if (file_exists('assets/css/custom.css')): ?>
    <link rel="stylesheet" href="assets/css/custom.css">
    <?php endif; ?>
    <style>
        <?php if (!empty($config_sistema['imagen_login'])): ?>
        body {
            background-image: url('<?php echo htmlspecialchars($config_sistema['imagen_login']); ?>');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: -1;
        }
        <?php endif; ?>
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <?php if (!empty($config_sistema['logo_sistema'])): ?>
                                <img src="<?php echo htmlspecialchars($config_sistema['logo_sistema']); ?>" 
                                     alt="Logo" 
                                     style="max-height: 80px; max-width: 200px; object-fit: contain;"
                                     class="mb-3">
                            <?php else: ?>
                                <i class="bi bi-shop text-primary" style="font-size: 3rem;"></i>
                            <?php endif; ?>
                            <h3 class="mt-3"><?php echo htmlspecialchars($config_sistema['nombre_empresa'] ?? APP_NAME); ?></h3>
                            <p class="text-muted">Sistema de Gestión</p>
                        </div>
                        
                        <?php if ($error): ?>
                            <?php echo show_alert($error, 'error'); ?>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <?php echo show_alert($success, 'success'); ?>
                        <?php endif; ?>
                        
                        <ul class="nav nav-tabs mb-4" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button">
                                    <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar Sesión
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button">
                                    <i class="bi bi-person-plus me-1"></i> Registrarse
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content">
                            <!-- Login Tab -->
                            <div class="tab-pane fade show active" id="login" role="tabpanel">
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Usuario o Email</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                                            <input type="text" name="email" class="form-control" placeholder="usuario o email@ejemplo.com" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Contraseña</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                            <input type="password" name="password" class="form-control" required>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar Sesión
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Register Tab -->
                            <div class="tab-pane fade" id="register" role="tabpanel">
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Necesita un <strong>token de acceso</strong> proporcionado por el administrador para registrarse.
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Token de Acceso</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                                            <input type="text" name="token_acceso" class="form-control" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Nombre Completo</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                                            <input type="text" name="nombre" class="form-control" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Nombre de Usuario</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-at"></i></span>
                                            <input type="text" name="username" class="form-control" pattern="[a-zA-Z0-9_-]{3,50}" placeholder="usuario123" required>
                                        </div>
                                        <small class="text-muted">Solo letras, números, - y _ (3-50 caracteres)</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                            <input type="email" name="email" class="form-control" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Contraseña (mínimo <?php echo PASSWORD_MIN_LENGTH; ?> caracteres)</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                            <input type="password" name="password" class="form-control" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="bi bi-person-plus me-1"></i> Crear Cuenta
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3 text-muted">
                    <small>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?></small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
