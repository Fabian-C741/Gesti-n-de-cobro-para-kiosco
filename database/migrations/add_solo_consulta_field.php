<?php
/**
 * Migración: Agregar campo solo_consulta a usuarios
 * Fecha: 2025-12-30
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "=== MIGRACIÓN: Campo solo_consulta ===\n";
    
    // Verificar si el campo ya existe
    $result = $db->query("SHOW COLUMNS FROM usuarios LIKE 'solo_consulta'");
    if ($result->rowCount() > 0) {
        echo "✓ El campo 'solo_consulta' ya existe\n";
    } else {
        echo "• Agregando campo 'solo_consulta'...\n";
        
        // Agregar el campo
        $db->exec("ALTER TABLE usuarios ADD COLUMN solo_consulta BOOLEAN DEFAULT FALSE AFTER user_rol");
        
        // Agregar comentario
        $db->exec("ALTER TABLE usuarios MODIFY COLUMN solo_consulta BOOLEAN DEFAULT FALSE COMMENT 'TRUE: Solo puede consultar precios, FALSE: Puede cobrar (default)'");
        
        echo "✓ Campo 'solo_consulta' agregado exitosamente\n";
    }
    
    echo "=== MIGRACIÓN COMPLETADA ===\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>