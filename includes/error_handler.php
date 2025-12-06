<?php
/**
 * Manejador global de errores
 * Evita pantallas en blanco y muestra mensajes amigables al usuario
 */

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar errores técnicos al usuario
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php-errors.log');

// Función para mostrar página de error amigable
function mostrar_error_amigable($titulo, $mensaje, $solucion = null) {
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - Sistema POS</title>
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
            .error-container { max-width: 500px; width: 90%; }
            .error-card {
                background: white;
                border-radius: 20px;
                padding: 40px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                text-align: center;
            }
            .error-icon { font-size: 80px; color: #dc3545; margin-bottom: 20px; }
            h1 { color: #333; font-size: 24px; margin-bottom: 15px; }
            p { color: #666; margin-bottom: 20px; }
            .btn-home {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                padding: 12px 30px;
                color: white;
                border-radius: 25px;
                text-decoration: none;
                display: inline-block;
                margin-top: 10px;
            }
            .btn-home:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                color: white;
            }
            .solution-box {
                background: #f8f9fa;
                border-left: 4px solid #667eea;
                padding: 15px;
                margin-top: 20px;
                text-align: left;
                border-radius: 5px;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-card">
                <div class="error-icon">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <h1><?php echo htmlspecialchars($titulo); ?></h1>
                <p><?php echo htmlspecialchars($mensaje); ?></p>
                
                <?php if ($solucion): ?>
                <div class="solution-box">
                    <strong><i class="bi bi-lightbulb me-2"></i>Solución:</strong><br>
                    <?php echo htmlspecialchars($solucion); ?>
                </div>
                <?php endif; ?>
                
                <a href="/login.php" class="btn-home">
                    <i class="bi bi-house-door me-2"></i>Volver al inicio
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Manejador de errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        mostrar_error_amigable(
            'Algo salió mal',
            'El sistema encontró un problema inesperado.',
            'Intenta recargar la página o contacta al administrador si el problema persiste.'
        );
    }
});

// Manejador de excepciones no capturadas
set_exception_handler(function($exception) {
    error_log("Excepción no capturada: " . $exception->getMessage() . " en " . $exception->getFile() . ":" . $exception->getLine());
    
    mostrar_error_amigable(
        'Error del sistema',
        'Ocurrió un error al procesar tu solicitud.',
        'Por favor, intenta nuevamente en unos momentos.'
    );
});
