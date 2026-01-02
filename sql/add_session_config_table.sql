-- Crear tabla para configuración de sesiones por rol
CREATE TABLE IF NOT EXISTS configuracion_sesiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rol ENUM('admin', 'vendedor', 'cajero', 'superadmin') NOT NULL,
    duracion_horas INT NOT NULL DEFAULT 8,
    descripcion VARCHAR(255),
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rol (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar configuraciones por defecto
INSERT INTO configuracion_sesiones (rol, duracion_horas, descripcion) VALUES
('superadmin', 48, 'Super administradores - Acceso extendido'),
('admin', 24, 'Administradores - Acceso diario completo'),
('vendedor', 12, 'Vendedores/Colaboradores - Turno extendido'),
('cajero', 8, 'Cajeros - Turno estándar')
ON DUPLICATE KEY UPDATE
    duracion_horas = VALUES(duracion_horas),
    descripcion = VALUES(descripcion);