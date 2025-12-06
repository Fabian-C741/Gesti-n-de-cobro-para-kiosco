<?php
require_once '../config/config.php';
require_once '../includes/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$venta_id = intval($_GET['id'] ?? 0);

if (!$venta_id) {
    echo json_encode(['error' => 'ID no especificado']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener datos de la venta
    $stmt = $db->prepare("
        SELECT v.*, u.nombre as vendedor_nombre
        FROM ventas v
        LEFT JOIN usuarios u ON v.usuario_id = u.id
        WHERE v.id = ?
    ");
    $stmt->execute([$venta_id]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$venta) {
        echo json_encode(['error' => 'Venta no encontrada']);
        exit;
    }
    
    // Obtener items de la venta
    $stmt = $db->prepare("
        SELECT vd.*, p.nombre as producto_nombre
        FROM venta_detalle vd
        LEFT JOIN productos p ON vd.producto_id = p.id
        WHERE vd.venta_id = ?
        ORDER BY vd.id
    ");
    $stmt->execute([$venta_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener configuración
    $stmt = $db->query("SELECT clave, valor FROM configuracion_sistema WHERE clave IN ('nombre_empresa', 'direccion_empresa', 'telefono_empresa', 'mensaje_ticket')");
    $config_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $config = [];
    foreach ($config_rows as $row) {
        $config[$row['clave']] = $row['valor'];
    }
    
    // Preparar respuesta
    $response = [
        'empresa' => $config['nombre_empresa'] ?? 'KIOSCO',
        'direccion' => $config['direccion_empresa'] ?? '',
        'telefono' => $config['telefono_empresa'] ?? '',
        'mensaje' => $config['mensaje_ticket'] ?? '',
        'vendedor' => $venta['vendedor_nombre'] ?? 'Cajero',
        'items' => array_map(function($item) {
            return [
                'nombre' => $item['producto_nombre'] ?? 'Producto',
                'cantidad' => $item['cantidad'],
                'precio_unitario' => $item['precio_unitario'],
                'subtotal' => $item['subtotal']
            ];
        }, $items)
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
