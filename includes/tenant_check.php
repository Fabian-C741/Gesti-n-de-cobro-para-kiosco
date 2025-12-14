<?php
/**
 * Verificación de estado del tenant para sistema SaaS
 * Incluir este archivo en las páginas principales para bloquear acceso si el tenant está suspendido
 */

function verificarEstadoTenant() {
    // Requerir env.php si no está cargado
    if (!class_exists('Env')) {
        require_once __DIR__ . '/../config/env.php';
    }
    
    try {
        $DB_HOST_MASTER = Env::get('DB_HOST_MASTER', '');
        $DB_NAME_MASTER = Env::get('DB_NAME_MASTER', '');
        $DB_USER_MASTER = Env::get('DB_USER_MASTER', '');
        $DB_PASS_MASTER = Env::get('DB_PASS_MASTER', '');
        
        // Solo verificar si hay configuración de BD maestra
        if (empty($DB_NAME_MASTER) || empty($DB_USER_MASTER)) {
            return ['activo' => true]; // Sistema standalone, sin multi-tenant
        }
        
        $conn_master = new PDO(
            "mysql:host={$DB_HOST_MASTER};dbname={$DB_NAME_MASTER};charset=utf8mb4",
            $DB_USER_MASTER,
            $DB_PASS_MASTER,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Buscar este sistema en la tabla de tenants por nombre de BD
        $bd_actual = defined('DB_NAME') ? DB_NAME : Env::get('DB_NAME', '');
        $stmt = $conn_master->prepare("SELECT estado, nombre FROM tenants WHERE bd_nombre = ? LIMIT 1");
        $stmt->execute([$bd_actual]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tenant) {
            return ['activo' => true]; // No encontrado en tenants, sistema standalone
        }
        
        if ($tenant['estado'] === 'activo') {
            return ['activo' => true, 'nombre' => $tenant['nombre']];
        }
        
        // Tenant no activo
        return [
            'activo' => false,
            'estado' => $tenant['estado'],
            'nombre' => $tenant['nombre']
        ];
        
    } catch (PDOException $e) {
        // Si falla, permitir acceso (puede ser sistema standalone)
        return ['activo' => true];
    }
}

function mostrarPaginaSuspension($estado, $nombre = '') {
    $mensajes = [
        'suspendido' => 'Este sistema está <strong>SUSPENDIDO</strong>. Contacte al administrador para más información.',
        'vencido' => 'La suscripción de este sistema ha <strong>VENCIDO</strong>. Contacte al administrador para renovar.',
        'cancelado' => 'Este sistema ha sido <strong>CANCELADO</strong>. Contacte al administrador.'
    ];
    
    $mensaje = $mensajes[$estado] ?? 'Este sistema no está disponible actualmente.';
    
    // Destruir sesión
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sistema No Disponible</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .error-card {
                background: white;
                border-radius: 20px;
                padding: 40px;
                text-align: center;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                max-width: 500px;
            }
            .error-icon {
                font-size: 80px;
                color: #dc3545;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="error-icon">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <h2 class="mb-3">Acceso No Disponible</h2>
            <p class="text-muted mb-4"><?php echo $mensaje; ?></p>
            <hr>
            <p class="small text-muted mb-0">
                <i class="bi bi-envelope me-1"></i> Si cree que esto es un error, contacte al soporte técnico.
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Función rápida para verificar y bloquear si es necesario
 * Usar al inicio de cada página protegida
 */
function verificarTenantActivo() {
    $resultado = verificarEstadoTenant();
    
    if (!$resultado['activo']) {
        mostrarPaginaSuspension($resultado['estado'], $resultado['nombre'] ?? '');
    }
}
