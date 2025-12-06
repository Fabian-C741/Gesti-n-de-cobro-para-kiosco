<?php
/**
 * Validador y sanitizador de sesiones
 * Previene errores por sesiones corruptas o incompletas
 */

function validar_sesion_segura() {
    // Si no hay sesión activa, no hacer nada
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return true;
    }
    
    // Verificar que las variables críticas de sesión existan
    $variables_requeridas = ['user_id', 'user_nombre', 'user_rol'];
    $sesion_valida = true;
    
    foreach ($variables_requeridas as $variable) {
        if (!isset($_SESSION[$variable]) || empty($_SESSION[$variable])) {
            $sesion_valida = false;
            break;
        }
    }
    
    // Si la sesión está corrupta, limpiarla
    if (!$sesion_valida && isset($_SESSION['user_id'])) {
        session_unset();
        session_destroy();
        return false;
    }
    
    return $sesion_valida;
}

function limpiar_sesion_corrupta() {
    if (!validar_sesion_segura()) {
        // Redirigir al login con mensaje informativo
        header('Location: /login.php?error=sesion_invalida');
        exit;
    }
}
