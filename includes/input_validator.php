<?php
/**
 * Sistema de Validación y Sanitización Avanzado
 * Protección contra XSS, SQL Injection y otros ataques
 */

class InputValidator {
    
    /**
     * Sanitizar string para output HTML (anti-XSS)
     */
    public static function sanitizeOutput($input, $allow_html = false) {
        if ($allow_html) {
            // Solo permitir tags seguros
            $allowed_tags = '<p><br><strong><em><u><ul><ol><li><h1><h2><h3><h4><h5><h6>';
            return strip_tags($input, $allowed_tags);
        }
        
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Validar y sanitizar email
     */
    public static function validateEmail($email) {
        $email = trim($email);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Protección adicional contra bypass
        if (strlen($email) > 254 || preg_match('/[<>"]/', $email)) {
            return false;
        }
        
        return strtolower($email);
    }
    
    /**
     * Validar y sanitizar número entero
     */
    public static function validateInteger($input, $min = null, $max = null) {
        if (!is_numeric($input)) {
            return false;
        }
        
        $int = filter_var($input, FILTER_VALIDATE_INT);
        
        if ($int === false) {
            return false;
        }
        
        if ($min !== null && $int < $min) {
            return false;
        }
        
        if ($max !== null && $int > $max) {
            return false;
        }
        
        return $int;
    }
    
    /**
     * Validar y sanitizar número decimal
     */
    public static function validateFloat($input, $min = null, $max = null) {
        if (!is_numeric($input)) {
            return false;
        }
        
        $float = filter_var($input, FILTER_VALIDATE_FLOAT);
        
        if ($float === false) {
            return false;
        }
        
        if ($min !== null && $float < $min) {
            return false;
        }
        
        if ($max !== null && $float > $max) {
            return false;
        }
        
        return $float;
    }
    
    /**
     * Validar contraseña segura
     */
    public static function validatePassword($password, $min_length = 8) {
        if (strlen($password) < $min_length) {
            return ['valid' => false, 'message' => "La contraseña debe tener al menos {$min_length} caracteres"];
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            return ['valid' => false, 'message' => 'La contraseña debe contener al menos una mayúscula'];
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            return ['valid' => false, 'message' => 'La contraseña debe contener al menos una minúscula'];
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'message' => 'La contraseña debe contener al menos un número'];
        }
        
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            return ['valid' => false, 'message' => 'La contraseña debe contener al menos un carácter especial'];
        }
        
        return ['valid' => true, 'message' => 'Contraseña válida'];
    }
    
    /**
     * Detectar patrones de ataque
     */
    public static function detectMaliciousPatterns($input) {
        $patterns = [
            // SQL Injection
            '/(\bunion\b.*\bselect\b)|(\bselect\b.*\bfrom\b)|(\binsert\b.*\binto\b)|(\bdelete\b.*\bfrom\b)|(\bdrop\b.*\btable\b)/i',
            // XSS
            '/<script[^>]*>.*?<\/script>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            // Path Traversal
            '/\.\.\/|\.\.\\\/i',
            // Command Injection
            '/(\||;|&|`|\$\(|\${)/i',
            // LDAP Injection
            '/(\(\||\*\)|\|\))/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Sanitizar string general
     */
    public static function sanitizeString($input, $max_length = 255) {
        // Remover null bytes
        $input = str_replace(chr(0), '', $input);
        
        // Trimear espacios
        $input = trim($input);
        
        // Verificar longitud máxima
        if (strlen($input) > $max_length) {
            $input = substr($input, 0, $max_length);
        }
        
        // Detectar patrones maliciosos
        if (self::detectMaliciousPatterns($input)) {
            throw new InvalidArgumentException('Entrada contiene patrones potencialmente maliciosos');
        }
        
        return $input;
    }
    
    /**
     * Validar archivo subido
     */
    public static function validateUploadedFile($file, $allowed_types = ['jpg', 'jpeg', 'png', 'gif'], $max_size = 5242880) {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['valid' => false, 'message' => 'Parámetros de archivo inválidos'];
        }
        
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return ['valid' => false, 'message' => 'No se subió ningún archivo'];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['valid' => false, 'message' => 'El archivo excede el tamaño máximo permitido'];
            default:
                return ['valid' => false, 'message' => 'Error desconocido al subir archivo'];
        }
        
        if ($file['size'] > $max_size) {
            return ['valid' => false, 'message' => 'El archivo es demasiado grande'];
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed_types)) {
            return ['valid' => false, 'message' => 'Tipo de archivo no permitido'];
        }
        
        // Verificar tipo MIME real
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file['tmp_name']);
        
        $allowed_mimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif'
        ];
        
        if (!isset($allowed_mimes[$extension]) || $mime_type !== $allowed_mimes[$extension]) {
            return ['valid' => false, 'message' => 'El contenido del archivo no coincide con su extensión'];
        }
        
        return ['valid' => true, 'message' => 'Archivo válido'];
    }
}

/**
 * Funciones helper globales
 */
function clean_output($input, $allow_html = false) {
    return InputValidator::sanitizeOutput($input, $allow_html);
}

function validate_email($email) {
    return InputValidator::validateEmail($email);
}

function validate_int($input, $min = null, $max = null) {
    return InputValidator::validateInteger($input, $min, $max);
}

function validate_float($input, $min = null, $max = null) {
    return InputValidator::validateFloat($input, $min, $max);
}

function clean_string($input, $max_length = 255) {
    return InputValidator::sanitizeString($input, $max_length);
}

function is_malicious($input) {
    return InputValidator::detectMaliciousPatterns($input);
}
?>