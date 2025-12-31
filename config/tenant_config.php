<?php
/**
 * Configuración para sistema Multi-Tenant
 * Este archivo conecta a la base de datos específica del tenant desde la sesión
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar que exista sesión de tenant
if (!isset($_SESSION['tenant_id']) || !isset($_SESSION['tenant_bd'])) {
    // Si no hay sesión de tenant, redirigir al login multi-tenant
    header('Location: /tenant_login.php');
    exit;
}

// Definir constantes del tenant desde la sesión
define('TENANT_ID', $_SESSION['tenant_id']);
define('TENANT_DOMINIO', $_SESSION['tenant_dominio']);
define('TENANT_NOMBRE', $_SESSION['tenant_nombre']);

// Configuración de base de datos del tenant
define('DB_HOST', $_SESSION['tenant_bd_host']);
define('DB_NAME', $_SESSION['tenant_bd']);
define('DB_USER', $_SESSION['tenant_bd_user']);
define('DB_PASS', $_SESSION['tenant_bd_pass']);
define('DB_CHARSET', 'utf8mb4');

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: /tenant_login.php?error=no_autenticado');
    exit;
}

// Verificar expiración de sesión (24 horas)
$session_lifetime = 86400; // 24 horas
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $session_lifetime) {
    session_destroy();
    header('Location: /tenant_login.php?error=sesion_expirada');
    exit;
}

// Funciones auxiliares para multi-tenant
function is_tenant_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['tenant_id']);
}

function get_tenant_connection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión a base de datos del tenant: " . $e->getMessage());
    }
}

function tenant_redirect($url) {
    header("Location: $url");
    exit;
}

function is_tenant_admin() {
    return isset($_SESSION['user_rol']) && $_SESSION['user_rol'] === 'admin';
}

function tenant_logout() {
    session_destroy();
    header('Location: /tenant_login.php?logout=1');
    exit;
}
?>
