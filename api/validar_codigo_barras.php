<?php
// Actualizado: 14/12/2025 15:40
session_start();

// Usar configuración de tenant si existe
if (isset($_SESSION['tenant_id'])) {
    require_once '../config/tenant_config.php';
} else {
    require_once '../config/config.php';
}
require_once '../includes/Database.php';

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
    // DEBUG: Listar códigos existentes
    $codes_stmt = $db->query("SELECT id, codigo_barras, nombre FROM productos WHERE codigo_barras IS NOT NULL AND codigo_barras != '' LIMIT 20");
    $codigos = $codes_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar producto
    $stmt = $db->prepare("SELECT id, nombre FROM productos WHERE codigo_barras = ? LIMIT 1");
    $stmt->execute([$codigo_barras]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($producto && ($excluir_id == 0 || $producto['id'] != $excluir_id)) {
        echo json_encode([
            'existe' => true,
            'nombre' => $producto['nombre'],
            'id' => $producto['id']
        ]);
    } else {
        echo json_encode([
            'existe' => false,
            'buscado' => $codigo_barras,
            'codigos_en_bd' => $codigos
        ]);
    }
} catch (PDOException $e) {
    echo json_encode(['existe' => false, 'error' => $e->getMessage()]);
}
