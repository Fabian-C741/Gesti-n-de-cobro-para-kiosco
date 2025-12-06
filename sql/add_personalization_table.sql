-- Tabla de personalización del sistema
CREATE TABLE IF NOT EXISTS personalizacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    tipo ENUM('color', 'imagen', 'texto') DEFAULT 'texto',
    categoria VARCHAR(50),
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar valores por defecto
INSERT INTO personalizacion (clave, valor, tipo, categoria) VALUES
('primary_color', '#0d6efd', 'color', 'colores'),
('secondary_color', '#6c757d', 'color', 'colores'),
('success_color', '#198754', 'color', 'colores'),
('danger_color', '#dc3545', 'color', 'colores'),
('sidebar_bg_start', '#0d6efd', 'color', 'colores'),
('sidebar_bg_end', '#0a58ca', 'color', 'colores'),
('login_bg_image', '', 'imagen', 'imagenes'),
('logo_image', '', 'imagen', 'imagenes'),
('app_name', 'Sistema POS', 'texto', 'general'),
('btn_border_radius', '8', 'texto', 'estilos'),
('card_border_radius', '12', 'texto', 'estilos')
ON DUPLICATE KEY UPDATE valor=valor;
