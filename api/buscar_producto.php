<?php
session_start();

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

// Conectar a la BD del tenant (no a la principal)
$host = $_SESSION['tenant_bd_host'] ?? 'localhost';
$name = $_SESSION['tenant_bd'] ?? 'u464516792_produccion';
$user = $_SESSION['tenant_bd_user'] ?? 'root';
$pass = $_SESSION['tenant_bd_pass'] ?? '';

try {
    $db = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit;
}

// Obtener parámetro de búsqueda
$codigo_barras = trim($_GET['codigo_barras'] ?? '');

if (empty($codigo_barras)) {
    echo json_encode(['success' => false, 'message' => 'Código de barras requerido']);
    exit;
}

try {
    // Buscar producto por código de barras
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
            'message' => 'Producto no encontrado',
            'debug_db' => $name
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
