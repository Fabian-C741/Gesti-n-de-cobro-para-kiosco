<?php
session_start();
require_once 'config_superadmin.php';
verificarSuperAdmin();

$id = $_GET['id'] ?? 0;
$nuevo_estado = $_GET['estado'] ?? '';

if (!$id || !in_array($nuevo_estado, ['activo', 'suspendido', 'vencido', 'cancelado'])) {
    header('Location: tenants.php');
    exit;
}

try {
    // Obtener datos del tenant
    $stmt = $conn_master->prepare("SELECT nombre FROM tenants WHERE id = ?");
    $stmt->execute([$id]);
    $tenant = $stmt->fetch();
    
    if (!$tenant) {
        throw new Exception('Cliente no encontrado');
    }
    
    // Actualizar estado
    $stmt = $conn_master->prepare("UPDATE tenants SET estado = ? WHERE id = ?");
    $stmt->execute([$nuevo_estado, $id]);
    
    // Registrar en log
    registrarLog($id, 'estado_cambiado', "Estado cambiado a: $nuevo_estado");
    
    $_SESSION['mensaje'] = "Estado del cliente actualizado a: " . ucfirst($nuevo_estado);
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

header('Location: tenants.php');
exit;
