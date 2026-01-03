<?php
// Cargar variables de entorno
require_once __DIR__ . '/env.php';

// Configuración de la base de datos desde .env
define('DB_HOST', Env::get('DB_HOST', 'localhost'));
define('DB_NAME', Env::get('DB_NAME', 'gestion_cobros'));
define('DB_USER', Env::get('DB_USER', 'root'));
define('DB_PASS', Env::get('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');

// Configuración de la aplicación
define('APP_NAME', 'Sistema de Gestión de Cobros');
define('APP_VERSION', '1.0.0');

// Detectar entorno
$esLocal = (Env::get('APP_ENV', 'local') === 'local');

// URL desde .env
if (!defined('APP_URL')) {
    define('APP_URL', Env::get('APP_URL', 'http://localhost/gestion-de-cobros'));
}

// Continuar con configuración anterior
if ($esLocal) {
    // Modo desarrollo ya configurado desde .env
} else {
    define('APP_URL', 'https://gestion-de-ventaspos.kcrsf.com');
}

// Configuración de seguridad
// SESSION_LIFETIME ahora se calcula dinámicamente por rol
// Se mantiene valor por defecto pero se sobrescribe según el rol del usuario
define('SESSION_LIFETIME_DEFAULT', 3600 * 8); // 8 horas (fallback por defecto)
define('TOKEN_LENGTH', 64);
define('PASSWORD_MIN_LENGTH', 8); // Aumentado a 8 caracteres

// Protección anti-fuerza bruta
define('MAX_LOGIN_ATTEMPTS', 5); // Máximo de intentos de login
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos de bloqueo
define('MAX_REQUESTS_PER_MINUTE', 60); // Rate limiting

// Protección anti-DDoS básica
define('MAX_REQUESTS_PER_IP', 100); // Máximo de peticiones por IP por minuto
define('ENABLE_RATE_LIMITING', true); // Activar limitación de peticiones

// Configuración de archivos
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Configuración de errores
if ($esLocal) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    define('DEBUG_MODE', true);
} else {
    // En producción: mostrar errores TEMPORALMENTE para debug
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    define('DEBUG_MODE', true); // Cambiar a false cuando esté funcionando
}

// Configuración de sesiones seguras - solo si la sesión no está activa
if (session_status() === PHP_SESSION_NONE) {
    // Cargar configuración dinámica de sesiones por rol
    require_once __DIR__ . '/../includes/session_config.php';
    
    // Obtener duración de sesión según rol (si está disponible)
    $session_duration = SESSION_LIFETIME_DEFAULT; // Fallback
    
    // Si ya hay sesión iniciada, usar configuración específica del rol
    if (isset($_SESSION['rol']) && function_exists('getSessionDurationByRole')) {
        $session_duration = getSessionDurationByRole($_SESSION['rol']) * 3600;
    }
    
    // Configurar parámetros de sesión
    ini_set('session.gc_maxlifetime', $session_duration);
    ini_set('session.cookie_lifetime', $session_duration);
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 1000);
    
    // Configuraciones de seguridad
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 1); // HTTPS obligatorio para cookies seguras
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.sid_length', 48);
    ini_set('session.sid_bits_per_character', 6);
}

// HEADERS COMENTADOS - Se configuran en cada script individual para evitar conflictos
// header_remove('X-Powered-By');
// header('X-Content-Type-Options: nosniff');
// header('X-Frame-Options: SAMEORIGIN');
// header('X-XSS-Protection: 1; mode=block');
// header('Referrer-Policy: strict-origin-when-cross-origin');

// INICIALIZACIÓN DEL SISTEMA DE SEGURIDAD AVANZADO
require_once __DIR__ . '/../includes/security_manager.php';
