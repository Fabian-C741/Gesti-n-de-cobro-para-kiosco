<?php
/**
 * Verificador de conectividad de base de datos
 * Se usa para determinar si el sistema de seguridad debe activarse completamente
 */

class DatabaseConnectivity {
    
    /**
     * Verificar si la base de datos está disponible
     */
    public static function isAvailable() {
        try {
            // Intentar cargar clase Database
            if (!class_exists('Database')) {
                return false;
            }
            
            // Intentar obtener conexión
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Verificar que la conexión funciona
            if (!$conn || !is_object($conn)) {
                return false;
            }
            
            // Hacer query simple para verificar conectividad
            $stmt = $conn->query("SELECT 1");
            return $stmt !== false;
            
        } catch (Exception $e) {
            error_log("Database connectivity check failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener conexión segura (null si no está disponible)
     */
    public static function getSafeConnection() {
        if (self::isAvailable()) {
            try {
                return Database::getInstance()->getConnection();
            } catch (Exception $e) {
                return null;
            }
        }
        return null;
    }
    
    /**
     * Verificar si tabla específica existe
     */
    public static function tableExists($table_name) {
        $conn = self::getSafeConnection();
        if (!$conn) {
            return false;
        }
        
        try {
            $stmt = $conn->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table_name]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>