@echo off
echo =========================================
echo   INSTALANDO SISTEMA DE SESIONES POR ROL
echo =========================================

php -r "require_once 'config/config.php'; require_once 'includes/Database.php'; try { $db = Database::getInstance(); $conn = $db->getConnection(); $sql_content = file_get_contents('sql/install_session_system.sql'); $statements = array_filter(array_map('trim', explode(';', $sql_content))); foreach ($statements as $statement) { if (!empty($statement) && !preg_match('/^--/', $statement)) { $conn->exec($statement); echo chr(8730) . ' Ejecutado: ' . substr($statement, 0, 50) . '...' . PHP_EOL; } } $stmt = $conn->query('SELECT COUNT(*) as total FROM configuracion_sesiones'); $total = $stmt->fetch(PDO::FETCH_ASSOC)['total']; echo PHP_EOL . '========================================' . PHP_EOL; echo chr(9989) . ' SISTEMA INSTALADO EXITOSAMENTE' . PHP_EOL; echo chr(128202) . ' Configuraciones creadas: ' . $total . PHP_EOL; echo '========================================' . PHP_EOL; } catch (Exception $e) { echo chr(10060) . ' Error: ' . $e->getMessage() . PHP_EOL; exit(1); }"

echo.
echo Presione una tecla para continuar...
pause >nul