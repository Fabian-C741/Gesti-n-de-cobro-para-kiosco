-- Agregar campo fecha_cancelacion a la tabla tenants
-- Si ya existe, dará error pero no afectará la tabla

-- Opción 1: Agregar directamente (si da error, el campo ya existe)
ALTER TABLE tenants 
ADD COLUMN fecha_cancelacion DATETIME NULL;

-- Opción 2: Si necesitas que esté después de fecha_expiracion
-- ALTER TABLE tenants 
-- MODIFY COLUMN fecha_cancelacion DATETIME NULL AFTER fecha_expiracion;

-- Actualizar índices
ALTER TABLE tenants 
ADD INDEX idx_estado (estado);

ALTER TABLE tenants 
ADD INDEX idx_activo (activo);


