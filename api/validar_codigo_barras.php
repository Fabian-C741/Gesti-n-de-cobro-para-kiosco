<?php
error_reporting(0);
ob_start();

require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();

ob_end_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['existe' => false]));
}

$db = Database::getInstance()->getConnection();
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
    } else {
        die(json_encode(['existe' => false]));
    }
} catch (PDOException $e) {
    die(json_encode(['existe' => false]));
}
