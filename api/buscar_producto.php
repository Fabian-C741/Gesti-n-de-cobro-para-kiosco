<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

$db = Database::getInstance()->getConnection();
$usuario_id = $_SESSION['user_id'];
$pv_id = $_SESSION['punto_venta_id'] ?? null;

// Obtener parámetro de búsqueda
$codigo_barras = $_GET['codigo_barras'] ?? '';

if (empty($codigo_barras)) {
    echo json_encode(['success' => false, 'message' => 'Código de barras requerido']);
    exit;
}

try {
    // Verificar si la columna punto_venta_id existe
    $has_pv_column = column_exists($db, 'productos', 'punto_venta_id');
    
    // Buscar producto por código de barras (buscar TODOS para validar duplicados)
    $stmt = $db->prepare("
        SELECT p.*, c.nombre as categoria_nombre
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE p.codigo_barras = ?
        LIMIT 1
    ");
    $stmt->execute([$codigo_barras]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($producto) {
        echo json_encode([
            'success' => true,
            'producto' => $producto
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Producto no encontrado'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en la búsqueda'
    ]);
}
