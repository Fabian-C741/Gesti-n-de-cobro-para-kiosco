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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .error-container {
            max-width: 500px;
            width: 90%;
        }
        .error-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        .error-icon {
            font-size: 80px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        p {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        .btn-home {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 14px 35px;
            color: white;
            border-radius: 25px;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
            font-weight: 500;
            transition: all 0.3s;
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
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-card">
            <div class="error-icon">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <h1>Algo salió mal</h1>
            <p>El sistema encontró un problema temporal. No te preocupes, es algo que podemos solucionar fácilmente.</p>
            
            <div class="solution-box">
                <strong><i class="bi bi-lightbulb-fill me-2"></i>¿Qué puedes hacer?</strong><br>
                <ul style="margin-top: 10px; text-align: left; padding-left: 25px;">
                    <li>Haz clic en el botón de abajo para volver al inicio</li>
                    <li>Inicia sesión nuevamente</li>
                    <li>Si el problema persiste, contacta al administrador</li>
                </ul>
            </div>
            
            <a href="/login.php" class="btn-home">
                <i class="bi bi-house-door-fill me-2"></i>Volver al Inicio
            </a>
        </div>
    </div>
</body>
</html>
