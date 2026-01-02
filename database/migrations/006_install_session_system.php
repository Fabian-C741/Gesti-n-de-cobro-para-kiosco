<?php
// Migración: Instalar sistema de sesiones por rol
// Archivo: database/migrations/006_install_session_system.php

require_once __DIR__ . '/../../includes/Database.php';

function ejecutar_006_install_session_system() {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        echo "Instalando sistema de configuración de sesiones por rol...\n";
        
        // 1. Crear tabla de configuración de sesiones
        $sql_create_table = "
        CREATE TABLE IF NOT EXISTS configuracion_sesiones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rol ENUM('admin', 'vendedor', 'cajero', 'superadmin') NOT NULL UNIQUE,
            duracion_horas INT NOT NULL DEFAULT 8,
            descripcion VARCHAR(255) DEFAULT NULL,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $conn->exec($sql_create_table);
        echo "✓ Tabla configuracion_sesiones creada\n";
        
        // 2. Insertar configuraciones predeterminadas
        $sql_insert = "
        INSERT INTO configuracion_sesiones (rol, duracion_horas, descripcion) VALUES
        ('admin', 24, 'Administradores - 24 horas de sesión'),
        ('vendedor', 12, 'Vendedores - 12 horas de sesión'), 
        ('cajero', 8, 'Cajeros - 8 horas de sesión'),
        ('superadmin', 48, 'Super Admin - 48 horas de sesión')
        ON DUPLICATE KEY UPDATE
        duracion_horas = VALUES(duracion_horas),
        descripcion = VALUES(descripcion)";
        
        $conn->exec($sql_insert);
        echo "✓ Configuraciones por defecto insertadas\n";
        
        // 3. Verificar instalación
        $stmt = $conn->query("SELECT COUNT(*) as total FROM configuracion_sesiones");
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo "✅ Sistema de sesiones instalado exitosamente\n";
        echo "📊 Configuraciones creadas: $total\n";
        
        return true;
        
    } catch (Exception $e) {
        echo "❌ Error al instalar sistema de sesiones: " . $e->getMessage() . "\n";
        throw $e;
    }
}

// Solo ejecutar si es llamado directamente
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    ejecutar_006_install_session_system();
}
?>