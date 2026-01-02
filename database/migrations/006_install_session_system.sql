-- Migración: Sistema de configuración de sesiones por rol
-- Archivo: database/migrations/006_install_session_system.sql

-- 1. Crear tabla de configuración de sesiones
CREATE TABLE IF NOT EXISTS configuracion_sesiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rol ENUM('admin', 'vendedor', 'cajero', 'superadmin') NOT NULL UNIQUE,
    duracion_horas INT NOT NULL DEFAULT 8,
    descripcion VARCHAR(255) DEFAULT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Insertar configuraciones predeterminadas por rol
INSERT INTO configuracion_sesiones (rol, duracion_horas, descripcion) VALUES
('admin', 24, 'Administradores - 24 horas de sesión'),
('vendedor', 12, 'Vendedores - 12 horas de sesión'), 
('cajero', 8, 'Cajeros - 8 horas de sesión'),
('superadmin', 48, 'Super Admin - 48 horas de sesión')
ON DUPLICATE KEY UPDATE
duracion_horas = VALUES(duracion_horas),
descripcion = VALUES(descripcion);