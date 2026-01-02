#!/bin/bash
# Script para instalar el sistema de sesiones por rol en producción

echo "========================================="
echo "  INSTALANDO SISTEMA DE SESIONES POR ROL"
echo "========================================="

# Ejecutar el script SQL de instalación
php -r "
require_once 'config/config.php';
require_once 'includes/Database.php';

try {
    \$db = new Database();
    \$conn = \$db->getConnection();
    
    // Leer y ejecutar el archivo SQL
    \$sql_content = file_get_contents('sql/install_session_system.sql');
    
    // Dividir en sentencias individuales
    \$statements = array_filter(array_map('trim', explode(';', \$sql_content)));
    
    foreach (\$statements as \$statement) {
        if (!empty(\$statement) && !preg_match('/^--/', \$statement)) {
            \$conn->exec(\$statement);
            echo \"✓ Ejecutado: \" . substr(\$statement, 0, 50) . \"...\\n\";
        }
    }
    
    // Verificar instalación
    \$stmt = \$conn->query('SELECT COUNT(*) as total FROM configuracion_sesiones');
    \$total = \$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo \"\\n========================================\\n\";
    echo \"✅ SISTEMA INSTALADO EXITOSAMENTE\\n\";
    echo \"📊 Configuraciones creadas: \$total\\n\";
    echo \"========================================\\n\";
    
} catch (Exception \$e) {
    echo \"❌ Error: \" . \$e->getMessage() . \"\\n\";
    exit(1);
}
"