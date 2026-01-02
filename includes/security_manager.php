<?php
/**
 * Configuración Principal de Seguridad
 * Carga automática de todos los sistemas de seguridad
 */

// Autoload de todos los componentes de seguridad
require_once __DIR__ . '/database_connectivity.php';
require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/security.php';  // Para funciones como check_rate_limit
require_once __DIR__ . '/input_validator.php';
require_once __DIR__ . '/csrf_protection.php';

// Security Logger se carga después de verificar Database

class SecurityManager {
    private static $instance = null;
    private $db;
    private $settings = [];
    
    public function __construct() {
        $this->loadDatabase();
        $this->loadSettings();
        $this->initializeSecurity();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Cargar conexión a base de datos
     */
    private function loadDatabase() {
        try {
            // Usar verificador de conectividad
            if (DatabaseConnectivity::isAvailable()) {
                require_once __DIR__ . '/security_logger.php';
                $this->db = DatabaseConnectivity::getSafeConnection();
            } else {
                $this->db = null;
                error_log("SecurityManager: Base de datos no disponible - funcionando en modo degradado");
            }
        } catch (Exception $e) {
            error_log("SecurityManager: No se pudo conectar a la base de datos - " . $e->getMessage());
            $this->db = null;
        }
    }
    
    /**
     * Cargar configuraciones de seguridad desde BD
     */
    private function loadSettings() {
        if (!$this->db) {
            // Valores por defecto si no hay BD
            $this->settings = [
                'max_login_attempts' => 5,
                'lockout_duration' => 15,
                'rate_limit_requests' => 100,
                'session_timeout_admin' => 24,
                'session_timeout_user' => 8,
                'enable_2fa' => false,
                'password_min_length' => 8,
                'password_require_special' => true,
                'enable_ip_whitelist' => false,
                'log_retention_days' => 90
            ];
            return;
        }
        
        try {
            $stmt = $this->db->query("SELECT setting_key, setting_value, setting_type FROM security_settings");
            while ($row = $stmt->fetch()) {
                $value = $row['setting_value'];
                
                // Convertir tipos
                switch ($row['setting_type']) {
                    case 'integer':
                        $value = (int)$value;
                        break;
                    case 'boolean':
                        $value = $value === 'true' || $value === '1';
                        break;
                    case 'json':
                        $value = json_decode($value, true);
                        break;
                    // 'string' no necesita conversión
                }
                
                $this->settings[$row['setting_key']] = $value;
            }
        } catch (Exception $e) {
            error_log("SecurityManager: Error cargando configuraciones - " . $e->getMessage());
        }
    }
    
    /**
     * Inicializar sistemas de seguridad
     */
    private function initializeSecurity() {
        // Aplicar headers de seguridad solo si no estamos en migración
        if (!headers_sent() && !defined('MIGRATION_MODE')) {
            SecurityHeaders::setAllSecurityHeaders();
        }
        
        // Inicializar logger de seguridad solo si está disponible
        if (class_exists('SecurityLogger')) {
            global $security_logger;
            if (!isset($security_logger)) {
                $security_logger = new SecurityLogger();
            }
        }
        
        // Verificar rate limiting solo si no estamos en migración
        if (!defined('MIGRATION_MODE')) {
            $this->checkRateLimit();
            
            // Verificar IP bloqueada
            $this->checkBlacklist();
            
            // Log de inicio de sesión
            $this->logPageAccess();
        }
    }
    
    /**
     * Obtener configuración
     */
    public function getSetting($key, $default = null) {
        return $this->settings[$key] ?? $default;
    }
    
    /**
     * Establecer configuración
     */
    public function setSetting($key, $value, $type = 'string', $description = '') {
        if (!$this->db) return false;
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO security_settings (setting_key, setting_value, setting_type, description, updated_by)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                setting_type = VALUES(setting_type),
                description = VALUES(description),
                updated_by = VALUES(updated_by)
            ");
            
            $user_id = $_SESSION['user_id'] ?? null;
            $stmt->execute([$key, $value, $type, $description, $user_id]);
            
            // Actualizar cache local
            $this->settings[$key] = $value;
            
            return true;
        } catch (Exception $e) {
            error_log("SecurityManager: Error guardando configuración - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar rate limiting
     */
    private function checkRateLimit() {
        $ip = $this->getClientIP();
        $limit = $this->getSetting('rate_limit_requests', 100);
        
        // Pasar null si no hay BD, la función check_rate_limit manejará el caso
        if (function_exists('check_rate_limit')) {
            if (check_rate_limit($this->db, $ip, $limit) === false) {
                http_response_code(429);
                header('Retry-After: 60');
                log_security_event('RATE_LIMIT_EXCEEDED', "IP bloqueada por exceder límite: {$limit} req/min", 'HIGH');
                die(json_encode(['error' => 'Rate limit exceeded', 'retry_after' => 60]));
            }
        }
    }
    
    /**
     * Verificar IP en lista negra
     */
    private function checkBlacklist() {
        if (!$this->db) return;
        
        $ip = $this->getClientIP();
        
        try {
            if (function_exists('check_ip_blacklist') && check_ip_blacklist($this->db, $ip)) {
                http_response_code(403);
                log_security_event('BLACKLISTED_IP_ACCESS', "IP bloqueada intentó acceder", 'HIGH');
                die(json_encode(['error' => 'IP blocked']));
            }
        } catch (Exception $e) {
            // Continuar si hay error verificando blacklist
            error_log("Error verificando blacklist: " . $e->getMessage());
        }
    }
    
    /**
     * Log de acceso a página
     */
    private function logPageAccess() {
        $sensitive_pages = ['/admin/', '/superadmin/', '/login.php', '/configuracion'];
        $current_page = $_SERVER['REQUEST_URI'] ?? '';
        
        foreach ($sensitive_pages as $page) {
            if (strpos($current_page, $page) !== false) {
                log_security_event(
                    'SENSITIVE_PAGE_ACCESS',
                    "Acceso a página sensible: {$current_page}",
                    'LOW',
                    ['page' => $current_page, 'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET'],
                    $_SESSION['user_id'] ?? null
                );
                break;
            }
        }
    }
    
    /**
     * Obtener IP del cliente
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
     * Validar petición completa
     */
    public function validateRequest($require_csrf = false, $allowed_methods = ['GET', 'POST']) {
        // Verificar método HTTP
        SecurityMiddleware::validateHTTPMethod($allowed_methods);
        
        // Verificar origen
        SecurityMiddleware::validateRequestOrigin();
        
        // Verificar CSRF si es requerido
        if ($require_csrf && in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'DELETE'])) {
            verify_csrf();
        }
        
        // Verificar entrada por patrones maliciosos
        $this->validateInputs();
    }
    
    /**
     * Validar todas las entradas
     */
    private function validateInputs() {
        $inputs = array_merge($_GET, $_POST, $_COOKIE);
        
        foreach ($inputs as $key => $value) {
            if (is_string($value) && InputValidator::detectMaliciousPatterns($value)) {
                log_security_event(
                    'MALICIOUS_INPUT_DETECTED',
                    "Entrada maliciosa detectada en {$key}",
                    'CRITICAL',
                    ['key' => $key, 'value' => substr($value, 0, 200)],
                    $_SESSION['user_id'] ?? null
                );
                
                http_response_code(400);
                die(json_encode(['error' => 'Invalid input detected']));
            }
        }
    }
    
    /**
     * Cleanup automático
     */
    public function cleanup() {
        global $security_logger;
        $retention_days = $this->getSetting('log_retention_days', 90);
        $security_logger->cleanupOldLogs($retention_days);
    }
}

// Inicialización automática
if (!defined('DISABLE_SECURITY_AUTO_INIT')) {
    $security_manager = SecurityManager::getInstance();
}

/**
 * Funciones helper globales
 */
function get_security_setting($key, $default = null) {
    global $security_manager;
    return $security_manager->getSetting($key, $default);
}

function set_security_setting($key, $value, $type = 'string', $description = '') {
    global $security_manager;
    return $security_manager->setSetting($key, $value, $type, $description);
}

function validate_request($require_csrf = false, $allowed_methods = ['GET', 'POST']) {
    global $security_manager;
    $security_manager->validateRequest($require_csrf, $allowed_methods);
}
?>