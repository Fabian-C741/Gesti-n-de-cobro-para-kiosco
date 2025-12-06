<?php
/**
 * Script de verificación de columna ROL en usuarios
 * Ejecutar para ver qué columna existe actualmente
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "========================================\n";
    echo "   VERIFICACIÓN DE COLUMNA ROL\n";
    echo "========================================\n\n";
    
    // Obtener información de columnas
    $stmt = $db->query("SHOW COLUMNS FROM usuarios LIKE '%rol%'");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($columns)) {
        echo "✗ NO se encontró ninguna columna de rol\n";
        echo "  Solución: Crear columna user_rol\n";
        echo "\n  SQL:\n";
        echo "  ALTER TABLE usuarios ADD COLUMN user_rol ENUM('admin', 'vendedor', 'cajero') DEFAULT 'vendedor' AFTER email;\n";
    } else {
        echo "✓ Columnas encontradas:\n\n";
        foreach ($columns as $col) {
            echo "  Nombre: {$col['Field']}\n";
            echo "  Tipo: {$col['Type']}\n";
            echo "  Nulo: {$col['Null']}\n";
            echo "  Default: {$col['Default']}\n";
            echo "  ----\n";
        }
        
        $hasUserRol = false;
        $hasRol = false;
        
        foreach ($columns as $col) {
            if ($col['Field'] === 'user_rol') $hasUserRol = true;
            if ($col['Field'] === 'rol') $hasRol = true;
        }
        
        if ($hasUserRol) {
            echo "\n✓ La columna 'user_rol' existe correctamente\n";
        } elseif ($hasRol) {
            echo "\n⚠ La columna se llama 'rol' en lugar de 'user_rol'\n";
            echo "  Solución: Renombrar la columna\n";
            echo "\n  SQL:\n";
            echo "  ALTER TABLE usuarios CHANGE COLUMN rol user_rol ENUM('admin', 'vendedor', 'cajero') DEFAULT 'vendedor';\n";
        }
    }
    
    echo "\n========================================\n";
    
} catch (Exception $e) {
    echo "✗ Error: {$e->getMessage()}\n";
}
