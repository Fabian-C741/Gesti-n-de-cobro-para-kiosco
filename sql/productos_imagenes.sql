-- Agregar campo de imagen a productos
ALTER TABLE productos 
ADD COLUMN IF NOT EXISTS imagen VARCHAR(255) DEFAULT NULL AFTER descripcion,
ADD COLUMN IF NOT EXISTS imagen_thumbnail VARCHAR(255) DEFAULT NULL AFTER imagen;

-- Crear directorio de uploads (hacer manual): uploads/productos/
