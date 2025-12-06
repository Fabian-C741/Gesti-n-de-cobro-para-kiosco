-- Agregar columna codigo_barras a la tabla productos

ALTER TABLE productos ADD COLUMN IF NOT EXISTS codigo_barras VARCHAR(100) NULL AFTER codigo;

-- Crear índice para búsquedas rápidas
CREATE INDEX IF NOT EXISTS idx_codigo_barras ON productos(codigo_barras);
