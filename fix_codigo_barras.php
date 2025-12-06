<?php
require_once 'config/config.php';
require_once 'includes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar si la columna codigo_barras existe
    $stmt = $db->query("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'productos' 
        AND COLUMN_NAME = 'codigo_barras'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] == 0) {
        echo "Agregando columna codigo_barras...\n";
        $db->exec("ALTER TABLE productos ADD COLUMN codigo_barras VARCHAR(100) NULL AFTER codigo");
        $db->exec("CREATE INDEX idx_codigo_barras ON productos(codigo_barras)");
        echo "✓ Columna codigo_barras agregada exitosamente\n";
    } else {
        echo "✓ La columna codigo_barras ya existe\n";
    }
    
    // Verificar
    $stmt = $db->query("SHOW COLUMNS FROM productos LIKE 'codigo_barras'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($column) {
        echo "✓ Columna encontrada: " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
}
