<?php
/**
 * Endpoint para renovar sesión automáticamente
 * Evita que expire por inactividad
 */

require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/session_config.php';

header('Content-Type: application/json');

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar que hay una sesión válida
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No hay sesión activa'
    ]);
    exit;
}

// Renovar el tiempo de login
$_SESSION['login_time'] = time();
$_SESSION['last_activity'] = time();

// Obtener información del usuario y duración de sesión por rol
$user_rol = $_SESSION['user_rol'] ?? 'cajero';
$db = Database::getInstance()->getConnection();
$session_duration = getSessionDurationByRole($user_rol, $db);

// Regenerar ID de sesión para mayor seguridad (opcional)
if (rand(1, 100) <= 10) { // 10% de probabilidad
    session_regenerate_id(true);
}

echo json_encode([
    'success' => true,
    'message' => 'Sesión renovada',
    'user_id' => $_SESSION['user_id'],
    'user_rol' => $user_rol,
    'session_duration_hours' => $session_duration / 3600,
    'timestamp' => time(),
    'formatted_time' => date('Y-m-d H:i:s')
]);
?>