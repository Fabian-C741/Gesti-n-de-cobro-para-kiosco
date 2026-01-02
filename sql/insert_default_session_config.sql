-- Insertar configuraciones de sesión predeterminadas por rol
-- Ejecutar después de crear la tabla configuracion_sesiones

INSERT INTO configuracion_sesiones (rol, duracion_horas, descripcion) VALUES
('admin', 24, 'Administradores - 24 horas de sesión'),
('vendedor', 12, 'Vendedores - 12 horas de sesión'), 
('cajero', 8, 'Cajeros - 8 horas de sesión'),
('superadmin', 48, 'Super Admin - 48 horas de sesión')
ON DUPLICATE KEY UPDATE
duracion_horas = VALUES(duracion_horas),
descripcion = VALUES(descripcion);