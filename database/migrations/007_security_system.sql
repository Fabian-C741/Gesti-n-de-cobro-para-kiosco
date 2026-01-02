-- Migración: Sistema de seguridad avanzado
-- Archivo: database/migrations/007_security_system.sql

-- 1. Tabla de logs de seguridad
CREATE TABLE IF NOT EXISTS security_logs (
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
    INDEX idx_ip_address (ip_address),
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla mejorada de rate limiting
CREATE TABLE IF NOT EXISTS rate_limit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    endpoint VARCHAR(255) DEFAULT 'global',
    request_count INT DEFAULT 1,
    first_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    blocked_until TIMESTAMP NULL,
    UNIQUE KEY unique_ip_endpoint (ip_address, endpoint),
    INDEX idx_last_request (last_request),
    INDEX idx_blocked_until (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabla mejorada de intentos de login
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL COMMENT 'Usuario, email o IP',
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE,
    failure_reason VARCHAR(100),
    locked_until TIMESTAMP NULL,
    INDEX idx_identifier (identifier),
    INDEX idx_ip_address (ip_address),
    INDEX idx_attempt_time (attempt_time),
    INDEX idx_locked_until (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabla de IPs bloqueadas
CREATE TABLE IF NOT EXISTS ip_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    reason VARCHAR(255) NOT NULL,
    blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    blocked_until TIMESTAMP NULL,
    auto_block BOOLEAN DEFAULT TRUE,
    created_by INT NULL,
    INDEX idx_ip_address (ip_address),
    INDEX idx_blocked_until (blocked_until),
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabla de sesiones activas mejorada
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id),
    INDEX idx_expires_at (expires_at),
    INDEX idx_last_activity (last_activity),
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabla de configuración de seguridad
CREATE TABLE IF NOT EXISTS security_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL,
    FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Insertar configuraciones de seguridad por defecto
INSERT INTO security_settings (setting_key, setting_value, setting_type, description) VALUES
('max_login_attempts', '5', 'integer', 'Máximo número de intentos de login antes de bloqueo'),
('lockout_duration', '15', 'integer', 'Duración del bloqueo en minutos'),
('rate_limit_requests', '100', 'integer', 'Máximo de peticiones por IP por minuto'),
('session_timeout_admin', '24', 'integer', 'Timeout de sesión para admins en horas'),
('session_timeout_user', '8', 'integer', 'Timeout de sesión para usuarios en horas'),
('enable_2fa', 'false', 'boolean', 'Habilitar autenticación de dos factores'),
('password_min_length', '8', 'integer', 'Longitud mínima de contraseña'),
('password_require_special', 'true', 'boolean', 'Requerir caracteres especiales en contraseña'),
('enable_ip_whitelist', 'false', 'boolean', 'Habilitar lista blanca de IPs'),
('log_retention_days', '90', 'integer', 'Días para mantener logs de seguridad')
ON DUPLICATE KEY UPDATE
setting_value = VALUES(setting_value);

-- 8. Evento programado para limpiar datos antiguos
DELIMITER $$
CREATE EVENT IF NOT EXISTS cleanup_expired_sessions
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    
    -- Limpiar sesiones expiradas (ignora error si no existe)
    DELETE FROM user_sessions WHERE expires_at < NOW();
    
    -- Limpiar rate limits antiguos (ignora error si no existe)  
    DELETE FROM rate_limit WHERE last_request < DATE_SUB(NOW(), INTERVAL 1 HOUR);
    
    -- Limpiar intentos de login antiguos (ignora error si no existe)
    DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR) AND locked_until IS NULL;
    
    -- Limpiar logs de seguridad antiguos (ignora error si no existe)
    DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
END$$
DELIMITER ;