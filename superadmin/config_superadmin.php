<?php
/**
 * Configuración específica para el panel de Super Admin
 */

// Cargar variables de entorno
require_once __DIR__ . '/../config/env.php';

// Configuración de base de datos MAESTRA (donde están los tenants)
define('DB_HOST_MASTER', Env::get('DB_HOST_MASTER', 'localhost'));
define('DB_USER_MASTER', Env::get('DB_USER_MASTER', 'root'));
define('DB_PASS_MASTER', Env::get('DB_PASS_MASTER', ''));
define('DB_NAME_MASTER', Env::get('DB_NAME_MASTER', 'gestion_cobros')); // BD maestra con tabla tenants

// Conectar a la base de datos maestra
try {
    $conn_master = new PDO(
        "mysql:host=" . DB_HOST_MASTER . ";dbname=" . DB_NAME_MASTER . ";charset=utf8mb4",
        DB_USER_MASTER,
        DB_PASS_MASTER,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    die("Error de conexión a BD maestra: " . $e->getMessage());
}

// Función para verificar autenticación de super admin
function verificarSuperAdmin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['super_admin_id'])) {
        header('Location: /superadmin/login.php');
        exit;
    }
}

// Función para registrar actividad
function registrarLog($tenant_id, $accion, $descripcion = '', $conn = null) {
    global $conn_master;
    $db = $conn ?? $conn_master;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $usuario = $_SESSION['super_admin_nombre'] ?? 'Sistema';
    
    $stmt = $db->prepare("
        INSERT INTO tenant_logs (tenant_id, accion, descripcion, usuario, ip_address)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$tenant_id, $accion, $descripcion, $usuario, $ip]);
}

// Función para conectar a la base de datos de un tenant específico
function conectarTenant($tenant_id) {
    global $conn_master;
    
    $stmt = $conn_master->prepare("
        SELECT bd_nombre, bd_host, bd_usuario, bd_password 
        FROM tenants 
        WHERE id = ? AND activo = 1
    ");
    $stmt->execute([$tenant_id]);
    $tenant = $stmt->fetch();
    
    if (!$tenant) {
        throw new Exception("Tenant no encontrado o inactivo");
    }
    
    try {
        $conn = new PDO(
            "mysql:host={$tenant['bd_host']};dbname={$tenant['bd_nombre']};charset=utf8mb4",
            $tenant['bd_usuario'],
            $tenant['bd_password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $conn;
    } catch(PDOException $e) {
        throw new Exception("Error conectando a BD del tenant: " . $e->getMessage());
    }
}

// Planes disponibles con sus características
$planes = [
    'basico' => [
        'nombre' => 'Plan Básico',
        'precio' => 20,
        'limite_usuarios' => 2,
        'limite_productos' => 1000,
        'limite_puntos_venta' => 1,
        'soporte' => 'Email (48hs)'
    ],
    'estandar' => [
        'nombre' => 'Plan Estándar',
        'precio' => 40,
        'limite_usuarios' => 5,
        'limite_productos' => 5000,
        'limite_puntos_venta' => 3,
        'soporte' => 'Email + Chat (24hs)'
    ],
    'premium' => [
        'nombre' => 'Plan Premium',
        'precio' => 80,
        'limite_usuarios' => 999,
        'limite_productos' => 999999,
        'limite_puntos_venta' => 10,
        'soporte' => 'Prioritario (4hs) + WhatsApp'
    ]
];
