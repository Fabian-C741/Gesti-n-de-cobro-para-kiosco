<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
        }
        .btn-login:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <?php
    session_start();
    
    // Si ya está logueado, redirigir al dashboard
    if (isset($_SESSION['super_admin_id'])) {
        header('Location: dashboard.php');
        exit;
    }
    
    $error = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once 'config_superadmin.php';
        
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Por favor completa todos los campos';
        } else {
            $stmt = $conn_master->prepare("
                SELECT id, nombre, email, password, rol 
                FROM super_admins 
                WHERE username = ? AND activo = 1
            ");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password'])) {
                // Login exitoso
                $_SESSION['super_admin_id'] = $admin['id'];
                $_SESSION['super_admin_nombre'] = $admin['nombre'];
                $_SESSION['super_admin_email'] = $admin['email'];
                $_SESSION['super_admin_rol'] = $admin['rol'];
                
                // Actualizar último acceso
                $stmt = $conn_master->prepare("UPDATE super_admins SET ultimo_acceso = NOW() WHERE id = ?");
                $stmt->execute([$admin['id']]);
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Usuario o contraseña incorrectos';
            }
        }
    }
    ?>

    <div class="login-card">
        <div class="login-header">
            <i class="bi bi-shield-lock-fill" style="font-size: 3rem;"></i>
            <h3 class="mt-3 mb-0">Panel Super Admin</h3>
            <p class="mb-0 opacity-75">Sistema de Gestión SaaS</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Usuario</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                        <input type="text" class="form-control" id="username" name="username" required autofocus>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-login w-100">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión
                </button>
            </form>
            
            <div class="mt-4 text-center text-muted small">
                <p class="mb-0">Acceso restringido a administradores del sistema</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
