-- Tabla para configuraciones generales del sistema
CREATE TABLE IF NOT EXISTS `configuracion_sistema` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `clave` VARCHAR(100) NOT NULL,
  `valor` TEXT,
  `descripcion` VARCHAR(255) DEFAULT NULL,
  `actualizado` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar configuraciones por defecto
INSERT INTO `configuracion_sistema` (`clave`, `valor`, `descripcion`) VALUES
('nombre_app', 'Sistema de Gestión de Cobros', 'Nombre de la aplicación que aparece en el sistema'),
('version_app', '1.0.0', 'Versión actual del sistema')
ON DUPLICATE KEY UPDATE `clave`=`clave`;
