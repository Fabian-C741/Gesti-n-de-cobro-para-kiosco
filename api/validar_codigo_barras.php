<?php
error_reporting(0);
ob_start();

require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();

ob_end_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['existe' => false, 'error' => 'No hay sesion']));
}

$db = Database::getInstance()->getConnection();
$codigo = trim($_GET['codigo'] ?? '');
$excluir_id = intval($_GET['excluir_id'] ?? 0);

if (empty($codigo)) {
    die(json_encode(['existe' => false, 'error' => 'Codigo vacio']));
}

try {
    // DEBUG: Ver todos los códigos en la BD
    $all_stmt = $db->query("SELECT id, codigo_barras, nombre FROM productos WHERE codigo_barras IS NOT NULL AND codigo_barras != '' LIMIT 10");
    $todos = $all_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar el código
    $stmt = $db->prepare("SELECT id, nombre, codigo_barras FROM productos WHERE codigo_barras = ? LIMIT 1");
    $stmt->execute([$codigo]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // También buscar con LIKE por si hay espacios
    $stmt2 = $db->prepare("SELECT id, nombre, codigo_barras FROM productos WHERE codigo_barras LIKE ? LIMIT 1");
    $stmt2->execute(['%' . $codigo . '%']);
    $producto_like = $stmt2->fetch(PDO::FETCH_ASSOC);
    
    if ($producto && ($excluir_id == 0 || $producto['id'] != $excluir_id)) {
        die(json_encode(['existe' => true, 'nombre' => $producto['nombre'], 'id' => $producto['id']]));
    } else {
        die(json_encode([
            'existe' => false,
            'debug' => [
                'codigo_buscado' => $codigo,
                'codigo_len' => strlen($codigo),
                'productos_en_bd' => $todos,
                'encontrado_like' => $producto_like
            ]
        ]));
    }
} catch (PDOException $e) {
    die(json_encode(['existe' => false, 'error' => $e->getMessage()]));
}
