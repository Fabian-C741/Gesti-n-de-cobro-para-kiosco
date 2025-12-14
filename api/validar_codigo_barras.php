<?php
error_reporting(0);
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['existe' => false]));
}

// Conexión directa - sin singleton ni constantes cacheadas
$host = $_SESSION['tenant_bd_host'] ?? 'localhost';
$name = $_SESSION['tenant_bd'] ?? ($_ENV['DB_NAME'] ?? 'gestion_cobros');
$user = $_SESSION['tenant_bd_user'] ?? ($_ENV['DB_USER'] ?? 'root');
$pass = $_SESSION['tenant_bd_pass'] ?? ($_ENV['DB_PASS'] ?? '');

try {
    $db = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['existe' => false, 'error' => 'No pude conectar: ' . $e->getMessage()]));
}

$codigo = trim($_GET['codigo'] ?? '');
$excluir_id = intval($_GET['excluir_id'] ?? 0);

if (empty($codigo)) {
    die(json_encode(['existe' => false]));
}

try {
    $stmt = $db->prepare("SELECT id, nombre FROM productos WHERE codigo_barras = ? LIMIT 1");
    $stmt->execute([$codigo]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($producto && ($excluir_id == 0 || $producto['id'] != $excluir_id)) {
        die(json_encode(['existe' => true, 'nombre' => $producto['nombre'], 'id' => $producto['id']]));
    }
    
    die(json_encode(['existe' => false, 'db' => $name]));
} catch (PDOException $e) {
    die(json_encode(['existe' => false, 'error' => $e->getMessage()]));
}
