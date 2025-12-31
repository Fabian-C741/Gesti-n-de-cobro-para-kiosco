-- Agregar campo solo_consulta a la tabla usuarios
-- Este campo permite configurar cajeros que solo puedan consultar precios

ALTER TABLE usuarios 
ADD COLUMN solo_consulta BOOLEAN DEFAULT FALSE AFTER user_rol;

-- Agregar comentario para documentar el campo
ALTER TABLE usuarios 
MODIFY COLUMN solo_consulta BOOLEAN DEFAULT FALSE COMMENT 'TRUE: Solo puede consultar precios, FALSE: Puede cobrar (default)';