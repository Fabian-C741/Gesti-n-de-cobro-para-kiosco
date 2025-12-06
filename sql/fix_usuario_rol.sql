-- Script para actualizar manualmente el rol de usuarios
-- Ejecutar en phpMyAdmin para cambiar el rol del usuario Cajero2

-- Ver el valor actual
SELECT id, nombre, email, user_rol, punto_venta FROM usuarios WHERE nombre LIKE '%Cajero%';

-- Actualizar el usuario Cajero2 a rol cajero
UPDATE usuarios SET user_rol = 'cajero' WHERE nombre = 'Cajero2';

-- Verificar el cambio
SELECT id, nombre, email, user_rol, punto_venta FROM usuarios WHERE nombre LIKE '%Cajero%';
