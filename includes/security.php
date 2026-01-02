<?php
/**
 * Funciones de seguridad avanzadas
 * Protección anti-DDoS, rate limiting, anti-brute force
 */

/**
 * Rate Limiting - Limitar peticiones por IP
 */
function check_rate_limit($db, $ip_address, $max_requests = MAX_REQUESTS_PER_IP) {
    if (!ENABLE_RATE_LIMITING) {
        return true;
    }
    
    // Si no hay conexión a BD, permitir acceso pero logear
    if (!$db || !is_object($db)) {
        error_log("Rate limiting deshabilitado: Sin conexión a base de datos");
        return true;
    }
    
    try {
        // Crear tabla si no existe
        $db->exec("
            CREATE TABLE IF NOT EXISTS rate_limit (
                ip_address VARCHAR(45) NOT NULL,
                request_count INT DEFAULT 1,
                first_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (ip_address),
                INDEX idx_last_request (last_request)
            ) ENGINE=InnoDB
        ");
        
        // Limpiar registros antiguos (más de 1 minuto)
        $db->exec("DELETE FROM rate_limit WHERE last_request < DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
        
        // Obtener contador actual
        $stmt = $db->prepare("SELECT request_count, first_request FROM rate_limit WHERE ip_address = ?");
        $stmt->execute([$ip_address]);
        $record = $stmt->fetch();
        
        if ($record) {
            // Verificar si superó el límite
            if ($record['request_count'] >= $max_requests) {
                // Registrar intento de ataque
                log_security_event($db, 'rate_limit_exceeded', $ip_address, "Superó límite de {$max_requests} peticiones/minuto");
                return false;
            }
            
            // Incrementar contador
            $stmt = $db->prepare("UPDATE rate_limit SET request_count = request_count + 1 WHERE ip_address = ?");
            $stmt->execute([$ip_address]);
        } else {
            // Primera petición
            $stmt = $db->prepare("INSERT INTO rate_limit (ip_address) VALUES (?)");
            $stmt->execute([$ip_address]);
        }
        
        return true;
    } catch (PDOException $e) {
        // Si hay error, permitir la petición (fail-open)
        return true;
    }
}

/**
 * Anti-Brute Force - Limitar intentos de login
 */
function check_login_attempts($db, $identifier, $max_attempts = MAX_LOGIN_ATTEMPTS) {
    // Si no hay conexión a BD, permitir acceso
    if (!$db || !is_object($db)) {
        error_log("Login attempts check deshabilitado: Sin conexión a base de datos");
        return true;
    }
    
    try {
        // Crear tabla si no existe
        $db->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                identifier VARCHAR(100) NOT NULL,
                attempts INT DEFAULT 1,
                ip_address VARCHAR(45),
                first_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                locked_until TIMESTAMP NULL,
                PRIMARY KEY (identifier),
                INDEX idx_locked (locked_until)
            ) ENGINE=InnoDB
        ");
        
        // Limpiar registros antiguos (más de 1 día)
        $db->exec("DELETE FROM login_attempts WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 1 DAY)");
        
        // Obtener intentos actuales
        $stmt = $db->prepare("SELECT attempts, locked_until FROM login_attempts WHERE identifier = ?");
        $stmt->execute([$identifier]);
        $record = $stmt->fetch();
        
        if ($record) {
            // Verificar si está bloqueado
            if ($record['locked_until'] && strtotime($record['locked_until']) > time()) {
                $remaining = strtotime($record['locked_until']) - time();
                $minutes = ceil($remaining / 60);
                return [
                    'allowed' => false,
                    'message' => "Demasiados intentos fallidos. Cuenta bloqueada por {$minutes} minuto(s)"
                ];
            }
            
            // Verificar si superó el límite
            if ($record['attempts'] >= $max_attempts) {
                // Bloquear cuenta
                $locked_until = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME);
                $stmt = $db->prepare("
                    UPDATE login_attempts 
                    SET locked_until = ?, attempts = attempts + 1 
                    WHERE identifier = ?
                ");
                $stmt->execute([$locked_until, $identifier]);
                
                log_security_event($db, 'account_locked', $_SERVER['REMOTE_ADDR'] ?? 'unknown', "Cuenta bloqueada: {$identifier}");
                
                return [
                    'allowed' => false,
                    'message' => "Demasiados intentos fallidos. Cuenta bloqueada por " . (LOGIN_LOCKOUT_TIME / 60) . " minutos"
                ];
            }
        }
        
        return ['allowed' => true];
    } catch (PDOException $e) {
        // Si hay error, permitir el intento (fail-open)
        return ['allowed' => true];
    }
}

/**
 * Registrar intento de login fallido
 */
function record_failed_login($db, $identifier) {
    // Si no hay conexión a BD, no registrar pero continuar
    if (!$db || !is_object($db)) {
        error_log("Failed login recording deshabilitado: Sin conexión a base de datos");
        return;
    }
    
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $stmt = $db->prepare("
            INSERT INTO login_attempts (identifier, ip_address, attempts) 
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE 
                attempts = attempts + 1,
                ip_address = ?,
                last_attempt = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$identifier, $ip, $ip]);
        
        // Obtener intentos actuales
        $stmt = $db->prepare("SELECT attempts FROM login_attempts WHERE identifier = ?");
        $stmt->execute([$identifier]);
        $record = $stmt->fetch();
        
        if ($record && $record['attempts'] >= MAX_LOGIN_ATTEMPTS - 2) {
            log_security_event($db, 'multiple_failed_logins', $ip, "Email: {$identifier}, Intentos: {$record['attempts']}");
        }
    } catch (PDOException $e) {
        // Ignorar errores
    }
}

/**
 * Limpiar intentos de login después de login exitoso
 */
function clear_login_attempts($db, $identifier) {
    // Si no hay conexión a BD, no limpiar pero continuar
    if (!$db || !is_object($db)) {
        return;
    }
    
    try {
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE identifier = ?");
        $stmt->execute([$identifier]);
    } catch (PDOException $e) {
        // Ignorar errores
    }
}

/**
 * Registrar evento de seguridad
 */
function log_security_event($db, $event_type, $ip_address, $description) {
    // Si no hay conexión a BD, logear a archivo de error
    if (!$db || !is_object($db)) {
        error_log("SECURITY_EVENT: {$event_type} - {$description} - IP: {$ip_address}");
        return;
    }
    
    try {
        // Crear tabla si no existe
        $db->exec("
            CREATE TABLE IF NOT EXISTS security_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(50) NOT NULL,
                ip_address VARCHAR(45),
                description TEXT,
                user_agent VARCHAR(255),
                fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_event (event_type),
                INDEX idx_ip (ip_address),
                INDEX idx_fecha (fecha)
            ) ENGINE=InnoDB
        ");
        
        $stmt = $db->prepare("
            INSERT INTO security_logs (event_type, ip_address, description, user_agent) 
            VALUES (?, ?, ?, ?)
        ");
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $stmt->execute([$event_type, $ip_address, $description, $user_agent]);
    } catch (PDOException $e) {
        // Ignorar errores en logs
    }
}

/**
 * Verificar IP en lista negra
 */
function check_ip_blacklist($db, $ip_address) {
    // Si no hay conexión a BD, permitir acceso
    if (!$db || !is_object($db)) {
        return false;
    }
    
    try {
        // Crear tabla si no existe
        $db->exec("
            CREATE TABLE IF NOT EXISTS ip_blacklist (
                ip_address VARCHAR(45) PRIMARY KEY,
                reason VARCHAR(255),
                blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                blocked_until TIMESTAMP NULL,
                INDEX idx_blocked_until (blocked_until)
            ) ENGINE=InnoDB
        ");
        
        $stmt = $db->prepare("
            SELECT reason, blocked_until 
            FROM ip_blacklist 
            WHERE ip_address = ? 
            AND (blocked_until IS NULL OR blocked_until > NOW())
        ");
        $stmt->execute([$ip_address]);
        $record = $stmt->fetch();
        
        if ($record) {
            log_security_event($db, 'blocked_ip_attempt', $ip_address, $record['reason']);
            return false;
        }
        
        return true;
    } catch (PDOException $e) {
        // Si hay error, permitir acceso (fail-open)
        return true;
    }
}

/**
 * Agregar IP a lista negra
 */
function add_to_blacklist($db, $ip_address, $reason, $duration_hours = 24) {
    // Si no hay conexión a BD, logear a archivo
    if (!$db || !is_object($db)) {
        error_log("BLACKLIST_ADD: {$ip_address} - {$reason} (BD no disponible)");
        return false;
    }
    
    try {
        $blocked_until = date('Y-m-d H:i:s', time() + ($duration_hours * 3600));
        
        $stmt = $db->prepare("
            INSERT INTO ip_blacklist (ip_address, reason, blocked_until) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                reason = ?, 
                blocked_until = ?,
                blocked_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$ip_address, $reason, $blocked_until, $reason, $blocked_until]);
        
        log_security_event($db, 'ip_blacklisted', $ip_address, $reason);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Validar y sanitizar IP
 */
function get_client_ip() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Verificar headers de proxy (con precaución)
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    
    // Validar formato de IP
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Detectar patrones de ataque en input
 */
function detect_attack_patterns($input) {
    $patterns = [
        '/(\<script)|(\<\/script\>)/i', // XSS básico
        '/(union.*select|select.*from|insert.*into|delete.*from|drop.*table)/i', // SQL Injection
        '/\.\.\/|\.\.\\\/i', // Path traversal
        '/<iframe|<object|<embed/i', // Embedding malicioso
        '/javascript:/i', // JavaScript URI
        '/on\w+\s*=/i', // Event handlers inline
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Validar origen de petición (CSRF adicional)
 */
function validate_request_origin() {
    if (!isset($_SERVER['HTTP_REFERER']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        return false;
    }
    
    if (isset($_SERVER['HTTP_REFERER'])) {
        $referer = parse_url($_SERVER['HTTP_REFERER']);
        $host = $_SERVER['HTTP_HOST'];
        
        if (isset($referer['host']) && $referer['host'] !== $host) {
            return false;
        }
    }
    
    return true;
}

/**
 * Generar hash seguro para verificación de integridad
 */
function generate_integrity_hash($data, $secret_key = '') {
    if (empty($secret_key)) {
        $secret_key = TOKEN_LENGTH;
    }
    return hash_hmac('sha256', $data, $secret_key);
}

/**
 * Protección contra ataques de temporización
 */
function secure_compare($a, $b) {
    return hash_equals($a, $b);
}

/**
 * Limpiar logs antiguos automáticamente
 */
function cleanup_old_logs($db, $days = 30) {
    // Si no hay conexión a BD, no limpiar
    if (!$db || !is_object($db)) {
        return false;
    }
    
    try {
        // Limpiar logs de actividad antiguos
        $stmt = $db->prepare("DELETE FROM logs_actividad WHERE fecha < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$days]);
        
        // Limpiar logs de seguridad antiguos
        $stmt = $db->prepare("DELETE FROM security_logs WHERE fecha < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$days]);
        
        // Limpiar sesiones expiradas
        clean_expired_sessions($db);
        
        return true;
    } catch (PDOException $e) {
        return false;
    }
}
