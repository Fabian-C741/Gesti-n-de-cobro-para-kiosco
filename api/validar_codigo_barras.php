<?php
// Suprimir warnings y limpiar output
error_reporting(0);
ob_start();

session_start();

// DEBUG: Ver qué hay en la sesión
$debug_session = [
    'tenant_id' => $_SESSION['tenant_id'] ?? 'NO EXISTE',
    'user_id' => $_SESSION['user_id'] ?? 'NO EXISTE',
    'tenant_bd' => $_SESSION['tenant_bd'] ?? 'NO EXISTE'
];

// Usar configuración de tenant si existe
if (isset($_SESSION['tenant_id'])) {
    @require_once '../config/tenant_config.php';
} else {
    @require_once '../config/config.php';
}
@require_once '../includes/Database.php';

// Limpiar cualquier output previo (warnings)
ob_end_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['existe' => false, 'error' => 'No autenticado', 'debug' => $debug_session]);
    exit;
}

$db = Database::getInstance()->getConnection();
$codigo = trim($_GET['codigo'] ?? '');
$excluir_id = intval($_GET['excluir_id'] ?? 0);

if (empty($codigo)) {
    echo json_encode(['existe' => false]);
    exit;
}

try {
    $stmt = $db->prepare("SELECT id, nombre FROM productos WHERE codigo_barras = ? LIMIT 1");
    $stmt->execute([$codigo]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // DEBUG: info de BD
    $db_name = $db->query("SELECT DATABASE()")->fetchColumn();
    
    if ($producto && ($excluir_id == 0 || $producto['id'] != $excluir_id)) {
        echo json_encode(['existe' => true, 'nombre' => $producto['nombre'], 'id' => $producto['id']]);
    } else {
        echo json_encode(['existe' => false, 'debug_db' => $db_name, 'debug_session' => $debug_session]);
    }
} catch (PDOException $e) {
    echo json_encode(['existe' => false, 'error' => $e->getMessage()]);
}
