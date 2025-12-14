<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['existe' => false, 'error' => 'No autenticado']);
    exit;
}

$db = Database::getInstance()->getConnection();

$codigo_barras = trim($_GET['codigo'] ?? '');
$excluir_id = intval($_GET['excluir_id'] ?? 0);

if (empty($codigo_barras)) {
    echo json_encode(['existe' => false]);
    exit;
}

try {
    // Buscar en TODOS los productos si existe el código
    if ($excluir_id > 0) {
        $stmt = $db->prepare("SELECT id, nombre FROM productos WHERE codigo_barras = ? AND id != ? LIMIT 1");
        $stmt->execute([$codigo_barras, $excluir_id]);
    } else {
        $stmt = $db->prepare("SELECT id, nombre FROM productos WHERE codigo_barras = ? LIMIT 1");
        $stmt->execute([$codigo_barras]);
    }
    
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($producto) {
        echo json_encode([
            'existe' => true,
            'nombre' => $producto['nombre'],
            'id' => $producto['id']
        ]);
    } else {
        echo json_encode(['existe' => false]);
    }
} catch (PDOException $e) {
    echo json_encode(['existe' => false, 'error' => 'Error de base de datos']);
}
