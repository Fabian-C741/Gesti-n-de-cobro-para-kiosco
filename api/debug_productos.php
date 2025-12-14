<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'No autenticado']));
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';

$db = Database::getInstance()->getConnection();

$stmt = $db->query("SELECT id, nombre, codigo_barras FROM productos LIMIT 20");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'db' => DB_NAME,
    'total' => count($productos),
    'productos' => $productos
], JSON_PRETTY_PRINT);
