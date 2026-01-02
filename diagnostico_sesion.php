<?php
/**
 * Script de diagnóstico de sesiones PHP
 * Verificar configuraciones reales del servidor
 */

echo "<h1>Diagnóstico de Configuración de Sesiones PHP</h1>";

echo "<h2>1. Información del Sistema</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Date/Time: " . date('Y-m-d H:i:s') . "<br>";

echo "<h2>2. Configuraciones de Sesión Actuales</h2>";
echo "session.gc_maxlifetime: " . ini_get('session.gc_maxlifetime') . " segundos (" . (ini_get('session.gc_maxlifetime') / 3600) . " horas)<br>";
echo "session.cookie_lifetime: " . ini_get('session.cookie_lifetime') . " segundos (" . (ini_get('session.cookie_lifetime') / 3600) . " horas)<br>";
echo "session.gc_probability: " . ini_get('session.gc_probability') . "<br>";
echo "session.gc_divisor: " . ini_get('session.gc_divisor') . "<br>";
echo "session.cookie_httponly: " . (ini_get('session.cookie_httponly') ? 'ON' : 'OFF') . "<br>";
echo "session.use_only_cookies: " . (ini_get('session.use_only_cookies') ? 'ON' : 'OFF') . "<br>";

echo "<h2>3. Configuraciones Relacionadas</h2>";
echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";

echo "<h2>4. Constantes de la Aplicación</h2>";
require_once 'config/config.php';
echo "SESSION_LIFETIME (definido): " . SESSION_LIFETIME . " segundos (" . (SESSION_LIFETIME / 3600) . " horas)<br>";

echo "<h2>5. Test de Sesión</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['test_start_time'])) {
    $_SESSION['test_start_time'] = time();
    echo "Nueva sesión iniciada a las: " . date('Y-m-d H:i:s') . "<br>";
} else {
    $duration = time() - $_SESSION['test_start_time'];
    echo "Sesión activa desde: " . date('Y-m-d H:i:s', $_SESSION['test_start_time']) . "<br>";
    echo "Duración actual: " . $duration . " segundos (" . round($duration / 3600, 2) . " horas)<br>";
}

echo "<h2>6. Información de la Sesión</h2>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Name: " . session_name() . "<br>";
echo "Session Save Path: " . session_save_path() . "<br>";

echo "<h2>7. Headers Enviados</h2>";
echo "Headers sent: " . (headers_sent() ? 'YES' : 'NO') . "<br>";

echo "<hr>";
echo "<p><strong>Actualiza esta página para ver si la sesión persiste</strong></p>";
echo "<p><a href='?refresh=" . time() . "'>Actualizar</a> | <a href='?destroy=1'>Destruir Sesión</a></p>";

if (isset($_GET['destroy'])) {
    session_destroy();
    echo "<p style='color: red;'><strong>Sesión destruida</strong></p>";
    echo "<p><a href='diagnostico_sesion.php'>Iniciar nueva sesión</a></p>";
}
?>