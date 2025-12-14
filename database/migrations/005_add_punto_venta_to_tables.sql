-- Migración: Agregar punto_venta_id a tablas principales
-- Fecha: 2024-12-14
-- Descripción: Permite separar datos por punto de venta (opcional, compatible hacia atrás)

-- Agregar punto_venta_id a productos (si no existe)
ALTER TABLE productos 
ADD COLUMN IF NOT EXISTS punto_venta_id INT NULL DEFAULT NULL,
ADD INDEX IF NOT EXISTS idx_punto_venta (punto_venta_id);

-- Agregar punto_venta_id a categorias (si no existe)
ALTER TABLE categorias 
ADD COLUMN IF NOT EXISTS punto_venta_id INT NULL DEFAULT NULL,
ADD INDEX IF NOT EXISTS idx_punto_venta (punto_venta_id);

-- Agregar punto_venta_id a ventas (si no existe)
ALTER TABLE ventas 
ADD COLUMN IF NOT EXISTS punto_venta_id INT NULL DEFAULT NULL,
ADD INDEX IF NOT EXISTS idx_punto_venta (punto_venta_id);

-- Nota: Los registros existentes quedan con punto_venta_id = NULL
-- lo que significa que son visibles para TODOS los usuarios (compatibilidad hacia atrás)
