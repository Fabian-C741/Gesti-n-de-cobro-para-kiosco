<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - Login STANDALONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .login-body {
            padding: 2rem;
        }
    </style>
</head>
<body>
    <?php
    // CONFIGURAR ERRORES AL TOPE
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    
    // Iniciar sesión
    session_start();
    
    // CREDENCIALES HARDCODEADAS PARA PRUEBA
    $DB_HOST = 'localhost';
    $DB_NAME = 'u464516792_produccion';
    $DB_USER = 'u464516792_gestion';
    $DB_PASS = 'GestionVentas987#';
    
    $error = '';
    $debug = [];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user_input = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $debug[] = "Usuario ingresado: " . htmlspecialchars($user_input);
        
        if (empty($user_input) || empty($password)) {
            $error = 'Por favor completa todos los campos';
        } else {
            try {
                $debug[] = "Intentando conectar a DB...";
                $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
                $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                $debug[] = "✓ Conexión exitosa";
                
                $debug[] = "Buscando usuario en super_admins...";
                $stmt = $pdo->prepare("SELECT id, usuario, clave FROM super_admins WHERE usuario = ? LIMIT 1");
                $stmt->execute([$user_input]);
                $admin = $stmt->fetch();
                
                if ($admin) {
                    $debug[] = "✓ Usuario encontrado: ID=" . $admin['id'];
                    $debug[] = "Hash en DB: " . substr($admin['clave'], 0, 30) . "...";
                    
                    if (password_verify($password, $admin['clave'])) {
                        $debug[] = "✓ Contraseña verificada correctamente";
                        $_SESSION['superadmin_id'] = $admin['id'];
                        $_SESSION['superadmin_user'] = $admin['usuario'];
                        $debug[] = "✓ Sesión iniciada";
                        $error = '<span class="text-success">LOGIN EXITOSO! Redirigiendo...</span>';
                        header('Refresh: 2; URL=dashboard.php');
                    } else {
                        $debug[] = "✗ Contraseña incorrecta";
                        $error = 'Contraseña incorrecta';
                    }
                } else {
                    $debug[] = "✗ Usuario no encontrado en la base de datos";
                    $error = 'Usuario no encontrado';
                }
            } catch (PDOException $e) {
                $debug[] = "✗ Error PDO: " . $e->getMessage();
                $error = 'Error de conexión: ' . $e->getMessage();
            } catch (Exception $e) {
                $debug[] = "✗ Error general: " . $e->getMessage();
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
    ?>

    <div class="login-card">
        <div class="login-header">
            <h3>LOGIN STANDALONE (DEBUG)</h3>
            <p class="mb-0 opacity-75">Sin includes, con errores visibles</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($debug)): ?>
                <div class="alert alert-info">
                    <strong>Debug Log:</strong><br>
                    <?php foreach($debug as $msg): ?>
                        <small><?= htmlspecialchars($msg) ?></small><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Usuario</label>
                    <input type="text" class="form-control" id="username" name="username" required autofocus>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">
                    Iniciar Sesión
                </button>
            </form>
        </div>
    </div>
</body>
</html>
