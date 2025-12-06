<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Cerrar sesión
if (isset($_SESSION['user_id'])) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Marcar sesión como inactiva
        if (isset($_SESSION['token_sesion'])) {
            $stmt = $db->prepare("UPDATE sesiones SET activa = 0 WHERE token_sesion = ?");
            $stmt->execute([$_SESSION['token_sesion']]);
        }
        
        log_activity($db, $_SESSION['user_id'], 'logout', 'Cierre de sesión');
    } catch (PDOException $e) {
        // Ignorar errores
    }
}

// Destruir sesión
session_unset();
session_destroy();

// Limpiar cookie de sesión
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

redirect('../login.php?success=logout');
