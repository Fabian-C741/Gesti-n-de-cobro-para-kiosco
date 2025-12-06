-- Script para crear tablas faltantes en producción
-- Ejecutar este script en phpMyAdmin de Hostinger

-- Crear tabla detalle_venta si no existe
CREATE TABLE IF NOT EXISTS detalle_venta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    producto_id INT NOT NULL,
    producto_nombre VARCHAR(255) NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE RESTRICT,
    INDEX idx_venta (venta_id),
    INDEX idx_producto (producto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verificar que la tabla ventas tenga la columna usuario_id
ALTER TABLE ventas 
ADD COLUMN IF NOT EXISTS usuario_id INT NOT NULL AFTER id,
ADD INDEX IF NOT EXISTS idx_usuario (usuario_id);

-- Agregar foreign key si no existe
SET @exist := (SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'ventas' 
    AND CONSTRAINT_NAME = 'ventas_ibfk_1');

SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE ventas ADD CONSTRAINT ventas_ibfk_1 FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE',
    'SELECT "FK ya existe"');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar columna imagen a productos si no existe
ALTER TABLE productos
ADD COLUMN IF NOT EXISTS imagen VARCHAR(255) DEFAULT NULL AFTER precio;
