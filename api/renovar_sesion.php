<?php
/**
 * Endpoint para renovar sesión automáticamente
 * Evita que expire por inactividad
 */

require_once 'config/config.php';

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

// Regenerar ID de sesión para mayor seguridad (opcional)
if (rand(1, 100) <= 10) { // 10% de probabilidad
    session_regenerate_id(true);
}

echo json_encode([
    'success' => true,
    'message' => 'Sesión renovada',
    'user_id' => $_SESSION['user_id'],
    'timestamp' => time(),
    'formatted_time' => date('Y-m-d H:i:s')
]);
?>