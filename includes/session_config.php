<?php
/**
 * Funciones para manejo de configuración de sesiones por rol
 */

/**
 * Obtener duración de sesión para un rol específico
 */
function getSessionDurationByRole($rol, $db = null) {
    if (!$db) {
        $db = Database::getInstance()->getConnection();
    }
    
    try {
        $stmt = $db->prepare("SELECT duracion_horas FROM configuracion_sesiones WHERE rol = ? AND activo = 1");
        $stmt->execute([$rol]);
        $config = $stmt->fetch();
        
        if ($config) {
            return intval($config['duracion_horas']) * 3600; // Convertir a segundos
        }
        
        // Valores por defecto si no hay configuración
        $defaults = [
            'superadmin' => 48 * 3600, // 48 horas
            'admin' => 24 * 3600,      // 24 horas
            'vendedor' => 12 * 3600,   // 12 horas
            'cajero' => 8 * 3600       // 8 horas
        ];
        
        return $defaults[$rol] ?? (8 * 3600); // 8 horas por defecto
        
    } catch (Exception $e) {
        // Si hay error, devolver 8 horas por defecto
        return 8 * 3600;
    }
}

/**
 * Obtener todas las configuraciones de sesión
 */
function getAllSessionConfigurations($db = null) {
    if (!$db) {
        $db = Database::getInstance()->getConnection();
    }
    
    try {
        $stmt = $db->query("SELECT * FROM configuracion_sesiones WHERE activo = 1 ORDER BY FIELD(rol, 'superadmin', 'admin', 'vendedor', 'cajero')");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Verificar si la sesión ha expirado según el rol del usuario
 */
function isSessionExpiredByRole($user_rol, $login_time, $db = null) {
    $session_duration = getSessionDurationByRole($user_rol, $db);
    return (time() - $login_time) > $session_duration;
}

/**
 * Crear configuraciones por defecto si no existen
 */
function createDefaultSessionConfigurations($db = null) {
    if (!$db) {
        $db = Database::getInstance()->getConnection();
    }
    
    try {
        // Verificar si ya existen configuraciones
        $stmt = $db->query("SELECT COUNT(*) as count FROM configuracion_sesiones");
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $defaults = [
                ['superadmin', 48, 'Super administradores - Acceso extendido'],
                ['admin', 24, 'Administradores - Acceso diario completo'],
                ['vendedor', 12, 'Vendedores/Colaboradores - Turno extendido'],
                ['cajero', 8, 'Cajeros - Turno estándar']
            ];
            
            $stmt = $db->prepare("INSERT INTO configuracion_sesiones (rol, duracion_horas, descripcion) VALUES (?, ?, ?)");
            
            foreach ($defaults as $config) {
                $stmt->execute($config);
            }
        }
        
    } catch (Exception $e) {
        // Silenciosamente fallar si hay problemas
        error_log("Error creating default session configurations: " . $e->getMessage());
    }
}
?>