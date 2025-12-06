-- Agregar campo username a la tabla usuarios
-- Ejecutar este script en la base de datos de Hostinger

ALTER TABLE usuarios ADD COLUMN username VARCHAR(50) UNIQUE AFTER email;

-- Actualizar usuarios existentes con username basado en email
UPDATE usuarios SET username = SUBSTRING_INDEX(email, '@', 1) WHERE username IS NULL;

-- Agregar índice para búsquedas rápidas
CREATE INDEX idx_username ON usuarios(username);
