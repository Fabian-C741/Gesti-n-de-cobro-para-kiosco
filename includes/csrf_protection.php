<?php
/**
 * Sistema de Protección CSRF (Cross-Site Request Forgery)
 * Genera y valida tokens únicos para cada formulario
 */

class CSRFProtection {
    
    /**
     * Generar token CSRF único
     */
    public static function generateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }
    
    /**
     * Validar token CSRF
     */
    public static function validateToken($token, $max_time = 3600) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Verificar que existe token en sesión
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        // Verificar tiempo de expiración (1 hora por defecto)
        if (time() - $_SESSION['csrf_token_time'] > $max_time) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }
        
        // Comparación segura contra timing attacks
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Generar campo hidden para formularios
     */
    public static function getTokenField() {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Verificar token desde POST
     */
    public static function verifyRequest() {
        $token = $_POST['csrf_token'] ?? '';
        
        if (!self::validateToken($token)) {
            http_response_code(403);
            die(json_encode([
                'success' => false,
                'message' => 'Token CSRF inválido o expirado. Recarga la página.',
                'error_code' => 'CSRF_INVALID'
            ]));
        }
        
        // Regenerar token después de uso exitoso
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
    }
}

/**
 * Función helper para generar token
 */
function csrf_token() {
    return CSRFProtection::generateToken();
}

/**
 * Función helper para campo hidden
 */
function csrf_field() {
    return CSRFProtection::getTokenField();
}

/**
 * Función helper para verificar request
 */
function verify_csrf() {
    CSRFProtection::verifyRequest();
}
?>