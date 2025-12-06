-- Renombrar columna 'rol' a 'user_rol' en tabla usuarios para consistencia

-- Primero verificar si la columna 'user_rol' ya existe, si no, renombrar 'rol'
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'usuarios' 
    AND COLUMN_NAME = 'user_rol');

SET @sql_rename = IF(@col_exists = 0, 
    'ALTER TABLE usuarios CHANGE COLUMN rol user_rol ENUM(''admin'', ''vendedor'', ''cajero'') NOT NULL DEFAULT ''vendedor''',
    'SELECT ''La columna user_rol ya existe'' AS mensaje');

PREPARE stmt FROM @sql_rename;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
