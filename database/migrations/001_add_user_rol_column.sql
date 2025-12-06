-- Migración: Renombrar columna rol a user_rol en tabla usuarios
-- Fecha: 2025-12-04
-- Descripción: Estandarizar el nombre de la columna de rol

-- Intentar renombrar la columna (si ya existe como user_rol, no hará nada)
ALTER TABLE usuarios 
CHANGE COLUMN rol user_rol ENUM('admin', 'vendedor', 'cajero') DEFAULT 'vendedor';
