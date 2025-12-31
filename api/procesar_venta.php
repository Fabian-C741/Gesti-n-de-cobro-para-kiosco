<?php
require_once '../config/config.php';
require_once '../includes/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/functions.php';

header('Content-Type: application/json');

// Verificar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar si es cajero con restricción de solo consulta
if ($_SESSION['user_rol'] === 'cajero') {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT solo_consulta FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $usuario = $stmt->fetch();
        
        if ($usuario && $usuario['solo_consulta']) {
            echo json_encode([
                'success' => false, 
                'message' => 'Acceso denegado: Tu cuenta está configurada solo para consulta de precios'
            ]);
            exit;
        }
    } catch (Exception $e) {
        // Si hay error, permitir por seguridad (fail-open)
    }
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['items']) || empty($input['items'])) {
        echo json_encode(['success' => false, 'message' => 'Carrito vacío']);
        exit;
    }
    
    $items = $input['items'];
    $metodo_pago = $input['metodo_pago'] ?? 'efectivo';
    $monto_pagado = floatval($input['monto_pagado'] ?? 0);
    
    // Calcular totales
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['precio_venta'] * $item['cantidad'];
    }
    
    $descuento = 0;
    $total = $subtotal - $descuento;
    
    // Iniciar transacción
    $db->beginTransaction();
    
    // Generar número de venta único
    $numero_venta = 'V-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Obtener punto_venta_id del usuario
    $punto_venta_id = $_SESSION['punto_venta_id'] ?? null;
    
    // Verificar si la columna punto_venta_id existe
    $has_pv_column = column_exists($db, 'ventas', 'punto_venta_id');
    
    // Insertar venta
    if ($has_pv_column && $punto_venta_id) {
        $stmt = $db->prepare("
            INSERT INTO ventas (numero_venta, usuario_id, subtotal, descuento, total, metodo_pago, estado, punto_venta_id)
            VALUES (?, ?, ?, ?, ?, ?, 'completada', ?)
        ");
        $stmt->execute([
            $numero_venta,
            $_SESSION['user_id'],
            $subtotal,
            $descuento,
            $total,
            $metodo_pago,
            $punto_venta_id
        ]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO ventas (numero_venta, usuario_id, subtotal, descuento, total, metodo_pago, estado)
            VALUES (?, ?, ?, ?, ?, ?, 'completada')
        ");
        $stmt->execute([
            $numero_venta,
            $_SESSION['user_id'],
            $subtotal,
            $descuento,
            $total,
            $metodo_pago
        ]);
    }
    
    $venta_id = $db->lastInsertId();
    
    // Insertar detalle de venta y actualizar stock
    $stmt_detalle = $db->prepare("
        INSERT INTO venta_detalle (venta_id, producto_id, cantidad, precio_unitario, subtotal)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt_stock = $db->prepare("
        UPDATE productos 
        SET stock = stock - ? 
        WHERE id = ? AND stock >= ?
    ");
    
    foreach ($items as $item) {
        // Insertar detalle
        $stmt_detalle->execute([
            $venta_id,
            $item['id'],
            $item['cantidad'],
            $item['precio_venta'],
            $item['precio_venta'] * $item['cantidad']
        ]);
        
        // Actualizar stock
        $stmt_stock->execute([
            $item['cantidad'],
            $item['id'],
            $item['cantidad']
        ]);
        
        // Verificar que se actualizó el stock
        if ($stmt_stock->rowCount() === 0) {
            throw new Exception('Stock insuficiente para: ' . $item['nombre']);
        }
    }
    
    // Confirmar transacción
    $db->commit();
    
    // Registrar actividad
    log_activity($db, $_SESSION['user_id'], 'venta', "Venta procesada: $numero_venta - Total: $$total");
    
    echo json_encode([
        'success' => true,
        'venta_id' => $venta_id,
        'numero_venta' => $numero_venta,
        'total' => $total,
        'cambio' => $monto_pagado - $total
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
