-- Migración: Verificar estructura de tabla usuarios
-- Fecha: 2025-12-04
-- Descripción: Asegurar que existe la columna para el rol del usuario

-- Opción 1: Si la columna se llama 'rol', renombrarla
-- Opción 2: Si no existe ninguna, crearla

-- Esta migración verifica primero antes de ejecutar
-- Nota: Ejecutar manualmente la opción correcta según tu caso

-- OPCIÓN 1: Renombrar 'rol' a 'user_rol' (si ya existe como 'rol')
-- ALTER TABLE usuarios CHANGE COLUMN rol user_rol ENUM('admin', 'vendedor', 'cajero') DEFAULT 'vendedor';

-- OPCIÓN 2: Agregar columna 'user_rol' si no existe
-- ALTER TABLE usuarios ADD COLUMN user_rol ENUM('admin', 'vendedor', 'cajero') DEFAULT 'vendedor' AFTER email;

-- Para verificar qué columna existe, ejecuta:
-- SHOW COLUMNS FROM usuarios LIKE '%rol%';
