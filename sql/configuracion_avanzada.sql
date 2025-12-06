-- Configuración avanzada del sitio
ALTER TABLE configuracion_sistema 
ADD COLUMN IF NOT EXISTS descripcion TEXT AFTER valor;

-- Insertar configuraciones de personalización visual
INSERT INTO configuracion_sistema (clave, valor, descripcion) VALUES
('logo_sistema', '', 'Ruta de la imagen del logo del sistema'),
('imagen_login', '', 'Ruta de la imagen de fondo del login'),
('favicon', '', 'Ruta del favicon del sistema'),
('color_primario', '#0d6efd', 'Color primario del sistema (hex)'),
('color_secundario', '#6c757d', 'Color secundario del sistema (hex)'),
('nombre_empresa', 'Mi Empresa', 'Nombre de la empresa'),
('direccion_empresa', '', 'Dirección de la empresa'),
('telefono_empresa', '', 'Teléfono de la empresa'),
('email_empresa', '', 'Email de la empresa'),
('cuit_empresa', '', 'CUIT/RUT/RFC de la empresa'),
('mensaje_ticket', 'Gracias por su compra', 'Mensaje al pie del ticket de venta'),
('formato_ticket', '80mm', 'Formato del ticket: 80mm o 58mm'),
('impresora_habilitada', '1', 'Habilitar impresión automática de tickets')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- Agregar campo de sucursal/punto de venta a usuarios
ALTER TABLE usuarios 
ADD COLUMN IF NOT EXISTS sucursal VARCHAR(100) DEFAULT 'Principal' AFTER rol,
ADD COLUMN IF NOT EXISTS punto_venta VARCHAR(50) DEFAULT 'PV-001' AFTER sucursal;

-- Crear tabla de puntos de venta
CREATE TABLE IF NOT EXISTS puntos_venta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    sucursal VARCHAR(100) DEFAULT 'Principal',
    activo TINYINT(1) DEFAULT 1,
    direccion TEXT,
    telefono VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar punto de venta por defecto
INSERT INTO puntos_venta (codigo, nombre, sucursal) VALUES
('PV-001', 'Caja Principal', 'Principal')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);
