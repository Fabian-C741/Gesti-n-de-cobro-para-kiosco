<?php
session_start();

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

// DEBUG: Ver qué hay en la sesión
$debug_session = [
    'tenant_bd' => $_SESSION['tenant_bd'] ?? 'NO EXISTE',
    'tenant_bd_host' => $_SESSION['tenant_bd_host'] ?? 'NO EXISTE',
    'tenant_bd_user' => $_SESSION['tenant_bd_user'] ?? 'NO EXISTE',
    'has_pass' => isset($_SESSION['tenant_bd_pass']) ? 'SI' : 'NO'
];

// Si no hay credenciales del tenant, usar las del config
if (!isset($_SESSION['tenant_bd'])) {
    require_once __DIR__ . '/../config/config.php';
    $host = DB_HOST;
    $name = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;
} else {
    $host = $_SESSION['tenant_bd_host'];
    $name = $_SESSION['tenant_bd'];
    $user = $_SESSION['tenant_bd_user'];
    $pass = $_SESSION['tenant_bd_pass'];
}

try {
    $db = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión', 'debug' => $debug_session, 'error' => $e->getMessage()]);
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
