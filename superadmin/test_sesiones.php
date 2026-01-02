<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de Configuración de Sesiones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .info-card {
            border-left: 4px solid #0d6efd;
            padding: 1rem;
            background-color: #f8f9fa;
            margin-bottom: 1rem;
        }
        .role-info {
            padding: 0.5rem 1rem;
            margin: 0.5rem 0;
            border-radius: 0.375rem;
            border: 1px solid #dee2e6;
        }
        .admin { background-color: #ffd6cc; border-color: #fd7e14; }
        .vendedor { background-color: #cff4fc; border-color: #0dcaf0; }
        .cajero { background-color: #d1ecf1; border-color: #17a2b8; }
        .superadmin { background-color: #f8d7da; border-color: #dc3545; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <h1 class="mb-4">🧪 Prueba de Sistema de Sesiones por Rol</h1>
        
        <?php
        require_once '../config/config.php';
        require_once '../includes/Database.php';
        require_once '../includes/session_config.php';
        
        echo "<div class='info-card'>";
        echo "<h3>Información del Sistema</h3>";
        echo "<p><strong>Servidor:</strong> " . $_SERVER['SERVER_NAME'] . "</p>";
        echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
        echo "<p><strong>Tiempo actual:</strong> " . date('Y-m-d H:i:s') . "</p>";
        echo "</div>";
        
        // Verificar si existe la tabla de configuración de sesiones
        try {
            $db = new Database();
            $conn = $db->getConnection();
            
            $stmt = $conn->query("SHOW TABLES LIKE 'configuracion_sesiones'");
            $tabla_existe = $stmt->rowCount() > 0;
            
            echo "<div class='info-card'>";
            echo "<h3>Estado de la Base de Datos</h3>";
            echo "<p><strong>Tabla configuracion_sesiones:</strong> " . ($tabla_existe ? "✅ Existe" : "❌ No existe") . "</p>";
            echo "</div>";
            
            if ($tabla_existe) {
                // Obtener todas las configuraciones
                $stmt = $conn->query("SELECT * FROM configuracion_sesiones ORDER BY rol");
                $configuraciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<div class='info-card'>";
                echo "<h3>Configuraciones de Sesión por Rol</h3>";
                
                if (empty($configuraciones)) {
                    echo "<div class='alert alert-warning'>No hay configuraciones definidas. Ejecute el archivo SQL de configuraciones por defecto.</div>";
                } else {
                    echo "<div class='row'>";
                    foreach ($configuraciones as $config) {
                        $clase_rol = $config['rol'];
                        echo "<div class='col-md-6 col-lg-4'>";
                        echo "<div class='role-info {$clase_rol}'>";
                        echo "<h5>" . ucfirst($config['rol']) . "</h5>";
                        echo "<p><strong>Duración:</strong> {$config['duracion_horas']} horas</p>";
                        echo "<p><small>{$config['descripcion']}</small></p>";
                        echo "</div>";
                        echo "</div>";
                    }
                    echo "</div>";
                }
                echo "</div>";
                
                // Probar funciones
                echo "<div class='info-card'>";
                echo "<h3>Pruebas de Funciones</h3>";
                
                $roles_prueba = ['admin', 'vendedor', 'cajero', 'superadmin'];
                foreach ($roles_prueba as $rol) {
                    $duracion = getSessionDurationByRole($rol);
                    echo "<p><strong>{$rol}:</strong> {$duracion} horas (" . ($duracion * 3600) . " segundos)</p>";
                }
                echo "</div>";
            } else {
                echo "<div class='alert alert-danger'>";
                echo "<h4>⚠️ Configuración Incompleta</h4>";
                echo "<p>La tabla de configuración de sesiones no existe. Ejecute los siguientes comandos SQL:</p>";
                echo "<ol>";
                echo "<li>Ejecutar: <code>sql/add_session_config_table.sql</code></li>";
                echo "<li>Ejecutar: <code>sql/insert_default_session_config.sql</code></li>";
                echo "</ol>";
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>";
            echo "<h4>Error de Conexión</h4>";
            echo "<p>" . $e->getMessage() . "</p>";
            echo "</div>";
        }
        ?>
        
        <div class="info-card">
            <h3>Archivos del Sistema</h3>
            <ul>
                <li><strong>Base de datos:</strong> sql/add_session_config_table.sql</li>
                <li><strong>Datos iniciales:</strong> sql/insert_default_session_config.sql</li>
                <li><strong>Panel admin:</strong> superadmin/configuracion_sesiones.php</li>
                <li><strong>Funciones:</strong> includes/session_config.php</li>
                <li><strong>API renovación:</strong> api/renovar_sesion.php</li>
            </ul>
        </div>
        
        <div class="mt-4">
            <a href="dashboard.php" class="btn btn-primary">← Volver al Dashboard</a>
            <a href="configuracion_sesiones.php" class="btn btn-success">Configurar Sesiones</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>