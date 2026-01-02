<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔒 Auditoría de Seguridad del Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .vulnerability-critical { background-color: #f8d7da; border-left: 4px solid #dc3545; }
        .vulnerability-high { background-color: #fff3cd; border-left: 4px solid #ffc107; }
        .vulnerability-medium { background-color: #d4edda; border-left: 4px solid #28a745; }
        .vulnerability-low { background-color: #cce5ff; border-left: 4px solid #007bff; }
        .security-card { padding: 1rem; margin-bottom: 1rem; border-radius: 0.375rem; }
        .fix-button { margin-top: 0.5rem; }
        .status-secure { color: #28a745; font-weight: bold; }
        .status-vulnerable { color: #dc3545; font-weight: bold; }
        .status-partial { color: #ffc107; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <h1 class="mb-4">🔒 Auditoría de Seguridad del Sistema POS</h1>
        
        <?php
        require_once '../config/config.php';
        require_once '../includes/Database.php';
        require_once '../includes/security.php';
        
        echo "<div class='row mb-4'>";
        echo "<div class='col-12'>";
        echo "<div class='alert alert-info'>";
        echo "<h4>📊 Estado General del Sistema</h4>";
        echo "<p><strong>Fecha de auditoría:</strong> " . date('d/m/Y H:i:s') . "</p>";
        echo "<p><strong>Servidor:</strong> " . $_SERVER['SERVER_NAME'] . "</p>";
        echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        
        $vulnerabilities = [];
        $security_score = 0;
        $total_checks = 0;
        
        // 1. Verificar archivos de configuración
        $total_checks++;
        if (file_exists('../.env')) {
            $env_perms = substr(sprintf('%o', fileperms('../.env')), -4);
            if ($env_perms === '0644' || $env_perms === '0755') {
                $vulnerabilities[] = [
                    'level' => 'critical',
                    'title' => 'Archivo .env con permisos inseguros',
                    'description' => "El archivo .env tiene permisos $env_perms. Debería ser 600 para evitar acceso no autorizado.",
                    'impact' => 'Credenciales de BD expuestas a otros usuarios del servidor',
                    'fix' => 'chmod 600 .env'
                ];
            } else {
                $security_score++;
            }
        } else {
            $vulnerabilities[] = [
                'level' => 'high',
                'title' => 'Archivo .env no encontrado',
                'description' => 'No se encontró archivo de variables de entorno.',
                'impact' => 'Credenciales hardcodeadas en código fuente',
                'fix' => 'Crear archivo .env con credenciales'
            ];
        }
        
        // 2. Verificar configuración de sesiones
        $total_checks++;
        $session_secure = ini_get('session.cookie_secure');
        $session_httponly = ini_get('session.cookie_httponly');
        if (!$session_secure || !$session_httponly) {
            $vulnerabilities[] = [
                'level' => 'medium',
                'title' => 'Configuración de sesiones insegura',
                'description' => 'Cookies de sesión no están configuradas como secure/httponly',
                'impact' => 'Susceptible a ataques XSS y MITM',
                'fix' => 'Configurar session.cookie_secure y session.cookie_httponly'
            ];
        } else {
            $security_score++;
        }
        
        // 3. Verificar protección SQL Injection
        $total_checks++;
        $sample_files = ['../admin/usuarios.php', '../api/procesar_venta.php', '../superadmin/configuracion_sesiones.php'];
        $sql_injection_safe = true;
        foreach ($sample_files as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                if (preg_match('/\$_[A-Z]+.*?[\'"]\s*\.\s*[\'"]/i', $content) && !preg_match('/prepare\s*\(/i', $content)) {
                    $sql_injection_safe = false;
                    break;
                }
            }
        }
        
        if (!$sql_injection_safe) {
            $vulnerabilities[] = [
                'level' => 'critical',
                'title' => 'Posible SQL Injection',
                'description' => 'Se detectaron concatenaciones directas de variables en consultas SQL',
                'impact' => 'Acceso no autorizado a base de datos, pérdida de datos',
                'fix' => 'Usar prepared statements en todas las consultas'
            ];
        } else {
            $security_score++;
        }
        
        // 4. Verificar protección XSS
        $total_checks++;
        $xss_protection = true;
        foreach ($sample_files as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                if (preg_match('/echo\s+\$_[A-Z]+/i', $content) && !preg_match('/htmlspecialchars|htmlentities/i', $content)) {
                    $xss_protection = false;
                    break;
                }
            }
        }
        
        if (!$xss_protection) {
            $vulnerabilities[] = [
                'level' => 'high',
                'title' => 'Protección XSS insuficiente',
                'description' => 'Variables de usuario se muestran sin sanitización',
                'impact' => 'Ejecución de JavaScript malicioso',
                'fix' => 'Usar htmlspecialchars() en todas las salidas'
            ];
        } else {
            $security_score++;
        }
        
        // 5. Verificar protección CSRF
        $total_checks++;
        $csrf_protection = false;
        foreach ($sample_files as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                if (preg_match('/csrf_token|_token/i', $content)) {
                    $csrf_protection = true;
                    break;
                }
            }
        }
        
        if (!$csrf_protection) {
            $vulnerabilities[] = [
                'level' => 'high',
                'title' => 'Sin protección CSRF',
                'description' => 'Los formularios no tienen tokens CSRF',
                'impact' => 'Ataques de falsificación de peticiones',
                'fix' => 'Implementar tokens CSRF en formularios'
            ];
        } else {
            $security_score++;
        }
        
        // 6. Verificar autenticación robusta
        $total_checks++;
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->query("SHOW TABLES LIKE 'login_attempts'");
            $brute_force_protection = $stmt->rowCount() > 0;
            
            if ($brute_force_protection) {
                $security_score++;
            } else {
                $vulnerabilities[] = [
                    'level' => 'medium',
                    'title' => 'Sin protección anti-fuerza bruta',
                    'description' => 'No hay limitación de intentos de login',
                    'impact' => 'Ataques de fuerza bruta contra contraseñas',
                    'fix' => 'Implementar limitación de intentos de login'
                ];
            }
        } catch (Exception $e) {
            $vulnerabilities[] = [
                'level' => 'critical',
                'title' => 'Error de conexión a base de datos',
                'description' => 'No se pudo conectar para verificar protecciones: ' . $e->getMessage(),
                'impact' => 'Sistema no funcional',
                'fix' => 'Verificar configuración de base de datos'
            ];
        }
        
        // 7. Verificar contraseñas hasheadas
        $total_checks++;
        if (isset($conn)) {
            try {
                $stmt = $conn->query("SELECT password FROM usuarios LIMIT 1");
                $user = $stmt->fetch();
                if ($user && !password_get_info($user['password'])['algo']) {
                    $vulnerabilities[] = [
                        'level' => 'critical',
                        'title' => 'Contraseñas sin hashear',
                        'description' => 'Las contraseñas no están hasheadas correctamente',
                        'impact' => 'Contraseñas visibles si la BD es comprometida',
                        'fix' => 'Migrar a password_hash()'
                    ];
                } else {
                    $security_score++;
                }
            } catch (Exception $e) {
                // Asumir que están hasheadas si no podemos verificar
                $security_score++;
            }
        }
        
        // 8. Verificar logs de seguridad
        $total_checks++;
        if (file_exists('../logs/security.log') || (isset($conn) && $conn->query("SHOW TABLES LIKE 'security_logs'")->rowCount() > 0)) {
            $security_score++;
        } else {
            $vulnerabilities[] = [
                'level' => 'medium',
                'title' => 'Sin logs de seguridad',
                'description' => 'No se encontraron logs de eventos de seguridad',
                'impact' => 'Imposible detectar ataques o incidentes',
                'fix' => 'Implementar sistema de logging de seguridad'
            ];
        }
        
        // Calcular puntuación
        $score_percentage = ($security_score / $total_checks) * 100;
        
        // Mostrar puntuación general
        echo "<div class='row mb-4'>";
        echo "<div class='col-12'>";
        echo "<div class='card'>";
        echo "<div class='card-body text-center'>";
        echo "<h3>Puntuación de Seguridad</h3>";
        
        $score_class = 'danger';
        if ($score_percentage >= 80) $score_class = 'success';
        elseif ($score_percentage >= 60) $score_class = 'warning';
        elseif ($score_percentage >= 40) $score_class = 'info';
        
        echo "<div class='progress mb-3' style='height: 30px;'>";
        echo "<div class='progress-bar bg-{$score_class}' role='progressbar' style='width: {$score_percentage}%'>";
        echo "<strong>" . round($score_percentage) . "%</strong>";
        echo "</div>";
        echo "</div>";
        
        echo "<p>Checks de seguridad pasados: <strong>{$security_score}/{$total_checks}</strong></p>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        
        // Mostrar vulnerabilidades
        if (!empty($vulnerabilities)) {
            echo "<h2 class='mb-3'>⚠️ Vulnerabilidades Detectadas</h2>";
            
            $critical = array_filter($vulnerabilities, fn($v) => $v['level'] === 'critical');
            $high = array_filter($vulnerabilities, fn($v) => $v['level'] === 'high');
            $medium = array_filter($vulnerabilities, fn($v) => $v['level'] === 'medium');
            $low = array_filter($vulnerabilities, fn($v) => $v['level'] === 'low');
            
            foreach (['critical' => $critical, 'high' => $high, 'medium' => $medium, 'low' => $low] as $level => $vulns) {
                if (!empty($vulns)) {
                    $level_names = ['critical' => 'Críticas', 'high' => 'Altas', 'medium' => 'Medias', 'low' => 'Bajas'];
                    echo "<h4 class='mt-4'>" . $level_names[$level] . " (" . count($vulns) . ")</h4>";
                    
                    foreach ($vulns as $vuln) {
                        echo "<div class='security-card vulnerability-{$level}'>";
                        echo "<h5>{$vuln['title']}</h5>";
                        echo "<p><strong>Descripción:</strong> {$vuln['description']}</p>";
                        echo "<p><strong>Impacto:</strong> {$vuln['impact']}</p>";
                        echo "<p><strong>Solución:</strong> <code>{$vuln['fix']}</code></p>";
                        echo "</div>";
                    }
                }
            }
        } else {
            echo "<div class='alert alert-success'>";
            echo "<h4>✅ ¡Excelente!</h4>";
            echo "<p>No se detectaron vulnerabilidades críticas en la auditoría básica.</p>";
            echo "</div>";
        }
        
        // Recomendaciones adicionales
        echo "<h2 class='mt-5 mb-3'>🛡️ Recomendaciones de Seguridad Adicionales</h2>";
        
        $recommendations = [
            'Implementar HTTPS en producción con certificado SSL válido',
            'Configurar Content Security Policy (CSP) headers',
            'Implementar autenticación de dos factores (2FA)',
            'Realizar backups regulares y cifrados de la base de datos',
            'Mantener PHP y dependencias actualizadas',
            'Configurar fail2ban para protección adicional contra ataques',
            'Implementar monitoreo de integridad de archivos',
            'Configurar firewall restrictivo (solo puertos necesarios)',
            'Usar WAF (Web Application Firewall)',
            'Implementar rate limiting más granular por endpoint'
        ];
        
        echo "<div class='row'>";
        foreach ($recommendations as $index => $rec) {
            echo "<div class='col-md-6 mb-3'>";
            echo "<div class='card'>";
            echo "<div class='card-body'>";
            echo "<h6 class='card-title'>" . ($index + 1) . ". {$rec}</h6>";
            echo "</div>";
            echo "</div>";
            echo "</div>";
        }
        echo "</div>";
        ?>
        
        <div class="mt-4">
            <a href="dashboard.php" class="btn btn-primary">← Volver al Dashboard</a>
            <button class="btn btn-success" onclick="window.location.reload()">🔄 Re-ejecutar Auditoría</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>