<?php
require_once '../includes/error_handler.php';
require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();
require_once '../includes/functions.php';
require_once '../includes/session_validator.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$venta_id = (int)($_GET['id'] ?? 0);

if (!$venta_id) {
    echo json_encode(['success' => false, 'message' => 'ID de venta no especificado']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Admin puede ver todas las ventas, otros solo las suyas
    if ($_SESSION['user_rol'] === 'admin') {
        $stmt = $db->prepare("
            SELECT v.*, u.nombre as vendedor_nombre
            FROM ventas v
            LEFT JOIN usuarios u ON v.usuario_id = u.id
            WHERE v.id = ?
        ");
        $stmt->execute([$venta_id]);
    } else {
        $stmt = $db->prepare("
            SELECT v.*, u.nombre as vendedor_nombre
            FROM ventas v
            LEFT JOIN usuarios u ON v.usuario_id = u.id
            WHERE v.id = ? AND v.usuario_id = ?
        ");
        $stmt->execute([$venta_id, $_SESSION['user_id']]);
    }
    
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$venta) {
        echo json_encode(['success' => false, 'message' => 'Venta no encontrada']);
        exit;
    }
    
    // Obtener items de la venta
    $stmt = $db->prepare("
        SELECT dv.*, p.nombre as producto_nombre, p.codigo_barras
        FROM venta_detalle dv
        JOIN productos p ON dv.producto_id = p.id
        WHERE dv.venta_id = ?
        ORDER BY dv.id
    ");
    $stmt->execute([$venta_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'venta' => $venta,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al obtener detalle: ' . $e->getMessage()]);
}
