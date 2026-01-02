<?php
/**
 * Sistema Avanzado de Logs de Seguridad
 * Monitorea y registra eventos críticos
 */

class SecurityLogger {
    private $db;
    private $log_file;
    
    public function __construct($log_to_db = true, $log_to_file = true) {
        if ($log_to_db && class_exists('Database')) {
            try {
                $this->db = Database::getInstance()->getConnection();
                $this->ensureSecurityTable();
            } catch (Exception $e) {
                // Si no hay BD disponible, solo log a archivo
                $this->db = null;
                error_log("SecurityLogger: BD no disponible - " . $e->getMessage());
            }
        }
        
        if ($log_to_file) {
            $this->log_file = __DIR__ . '/../logs/security.log';
            $this->ensureLogDirectory();
        }
    }
    
    /**
     * Crear tabla de logs si no existe
     */
    private function ensureSecurityTable() {
        if (!$this->db) return;
        
        $sql = "CREATE TABLE IF NOT EXISTS security_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            event_type VARCHAR(50) NOT NULL,
            severity ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'MEDIUM',
            ip_address VARCHAR(45),
            user_agent TEXT,
            user_id INT NULL,
            message TEXT NOT NULL,
            additional_data JSON,
            INDEX idx_timestamp (timestamp),
            INDEX idx_event_type (event_type),
            INDEX idx_severity (severity),
            INDEX idx_ip_address (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
    }
    
    /**
     * Asegurar que existe directorio de logs
     */
    private function ensureLogDirectory() {
        $log_dir = dirname($this->log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        // Crear .htaccess para proteger logs
        $htaccess_file = $log_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Deny from all\n");
        }
    }
    
    /**
     * Log evento de seguridad
     */
    public function logEvent($event_type, $message, $severity = 'MEDIUM', $additional_data = null, $user_id = null) {
        $ip_address = $this->getClientIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $timestamp = date('Y-m-d H:i:s');
        
        // Log a base de datos
        if ($this->db) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO security_logs 
                    (event_type, severity, ip_address, user_agent, user_id, message, additional_data)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $event_type,
                    $severity,
                    $ip_address,
                    $user_agent,
                    $user_id,
                    $message,
                    $additional_data ? json_encode($additional_data) : null
                ]);
            } catch (Exception $e) {
                // Si falla DB, al menos log a archivo
                error_log("SecurityLogger DB Error: " . $e->getMessage());
            }
        }
        
        // Log a archivo
        if ($this->log_file) {
            $log_entry = sprintf(
                "[%s] [%s] %s - %s - IP: %s - User: %s - Data: %s\n",
                $timestamp,
                $severity,
                $event_type,
                $message,
                $ip_address,
                $user_id ?? 'N/A',
                $additional_data ? json_encode($additional_data) : 'N/A'
            );
            
            file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
        }
        
        // Alertas críticas por email (opcional)
        if ($severity === 'CRITICAL') {
            $this->sendCriticalAlert($event_type, $message, $ip_address);
        }
    }
    
    /**
     * Eventos específicos de seguridad
     */
    public function logLoginAttempt($username, $success, $user_id = null) {
        $this->logEvent(
            $success ? 'LOGIN_SUCCESS' : 'LOGIN_FAILED',
            "Intento de login para usuario: {$username}",
            $success ? 'LOW' : 'MEDIUM',
            ['username' => $username],
            $user_id
        );
    }
    
    public function logBruteForceAttempt($username, $attempt_count) {
        $this->logEvent(
            'BRUTE_FORCE_DETECTED',
            "Ataque de fuerza bruta detectado para usuario: {$username} (Intento #{$attempt_count})",
            'HIGH',
            ['username' => $username, 'attempt_count' => $attempt_count]
        );
    }
    
    public function logSQLInjectionAttempt($query, $user_id = null) {
        $this->logEvent(
            'SQL_INJECTION_ATTEMPT',
            "Intento de inyección SQL detectado",
            'CRITICAL',
            ['query' => $query],
            $user_id
        );
    }
    
    public function logXSSAttempt($payload, $user_id = null) {
        $this->logEvent(
            'XSS_ATTEMPT',
            "Intento de XSS detectado",
            'HIGH',
            ['payload' => $payload],
            $user_id
        );
    }
    
    public function logCSRFAttempt($user_id = null) {
        $this->logEvent(
            'CSRF_ATTEMPT',
            "Intento de ataque CSRF detectado",
            'HIGH',
            ['referer' => $_SERVER['HTTP_REFERER'] ?? 'N/A'],
            $user_id
        );
    }
    
    public function logRateLimitExceeded($limit_type, $current_count) {
        $this->logEvent(
            'RATE_LIMIT_EXCEEDED',
            "Límite de peticiones excedido: {$limit_type}",
            'MEDIUM',
            ['limit_type' => $limit_type, 'current_count' => $current_count]
        );
    }
    
    public function logUnauthorizedAccess($resource, $user_id = null) {
        $this->logEvent(
            'UNAUTHORIZED_ACCESS',
            "Acceso no autorizado a recurso: {$resource}",
            'HIGH',
            ['resource' => $resource, 'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A'],
            $user_id
        );
    }
    
    public function logPasswordChange($user_id) {
        $this->logEvent(
            'PASSWORD_CHANGED',
            "Contraseña cambiada",
            'LOW',
            null,
            $user_id
        );
    }
    
    public function logPrivilegeEscalation($from_role, $to_role, $user_id, $admin_id) {
        $this->logEvent(
            'PRIVILEGE_ESCALATION',
            "Cambio de privilegios de {$from_role} a {$to_role}",
            'MEDIUM',
            ['from_role' => $from_role, 'to_role' => $to_role, 'admin_id' => $admin_id],
            $user_id
        );
    }
    
    /**
     * Obtener IP del cliente de forma segura
     */
    private function getClientIP() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Enviar alerta crítica (implementar según necesidades)
     */
    private function sendCriticalAlert($event_type, $message, $ip_address) {
        // TODO: Implementar notificación por email/Slack/webhook
        error_log("CRITICAL SECURITY ALERT: {$event_type} - {$message} - IP: {$ip_address}");
    }
    
    /**
     * Limpiar logs antiguos
     */
    public function cleanupOldLogs($days = 90) {
        if ($this->db) {
            try {
                $stmt = $this->db->prepare("DELETE FROM security_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)");
                $stmt->execute([$days]);
            } catch (Exception $e) {
                error_log("Error cleaning up security logs: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Obtener estadísticas de seguridad
     */
    public function getSecurityStats($days = 7) {
        if (!$this->db) return [];
        
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    event_type,
                    severity,
                    COUNT(*) as count,
                    DATE(timestamp) as date
                FROM security_logs 
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY event_type, severity, DATE(timestamp)
                ORDER BY timestamp DESC
            ");
            
            $stmt->execute([$days]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}

// Instancia global solo si Database está disponible
if (class_exists('Database')) {
    $security_logger = new SecurityLogger();
}

/**
 * Funciones helper globales
 */
function log_security_event($event_type, $message, $severity = 'MEDIUM', $additional_data = null, $user_id = null) {
    global $security_logger;
    if (isset($security_logger)) {
        $security_logger->logEvent($event_type, $message, $severity, $additional_data, $user_id);
    } else {
        error_log("SECURITY_EVENT [{$severity}] {$event_type}: {$message}");
    }
}

function log_login_attempt($username, $success, $user_id = null) {
    global $security_logger;
    if (isset($security_logger)) {
        $security_logger->logLoginAttempt($username, $success, $user_id);
    } else {
        error_log("LOGIN_ATTEMPT: {$username} - " . ($success ? 'SUCCESS' : 'FAILED'));
    }
}

function log_brute_force($username, $attempt_count) {
    global $security_logger;
    if (isset($security_logger)) {
        $security_logger->logBruteForceAttempt($username, $attempt_count);
    } else {
        error_log("BRUTE_FORCE: {$username} - Attempt #{$attempt_count}");
    }
}

function log_sql_injection($query, $user_id = null) {
    global $security_logger;
    if (isset($security_logger)) {
        $security_logger->logSQLInjectionAttempt($query, $user_id);
    } else {
        error_log("SQL_INJECTION_ATTEMPT: " . substr($query, 0, 100));
    }
}

function log_xss_attempt($payload, $user_id = null) {
    global $security_logger;
    if (isset($security_logger)) {
        $security_logger->logXSSAttempt($payload, $user_id);
    } else {
        error_log("XSS_ATTEMPT: " . substr($payload, 0, 100));
    }
}

function log_csrf_attempt($user_id = null) {
    global $security_logger;
    if (isset($security_logger)) {
        $security_logger->logCSRFAttempt($user_id);
    } else {
        error_log("CSRF_ATTEMPT from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
}
?>