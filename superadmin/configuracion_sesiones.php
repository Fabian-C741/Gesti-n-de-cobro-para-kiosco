<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/functions.php';

// Verificar que es superadmin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'superadmin') {
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$error = '';
$success = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'actualizar_configuraciones') {
        try {
            $db->beginTransaction();
            
            foreach ($_POST['configuraciones'] as $rol => $datos) {
                $duracion = intval($datos['duracion_horas']);
                $descripcion = sanitize_input($datos['descripcion']);
                
                if ($duracion < 1 || $duracion > 168) { // Máximo 1 semana
                    throw new Exception("Duración inválida para rol $rol. Debe estar entre 1 y 168 horas.");
                }
                
                $stmt = $db->prepare("
                    INSERT INTO configuracion_sesiones (rol, duracion_horas, descripcion) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    duracion_horas = VALUES(duracion_horas),
                    descripcion = VALUES(descripcion)
                ");
                $stmt->execute([$rol, $duracion, $descripcion]);
            }
            
            $db->commit();
            $success = 'Configuraciones de sesión actualizadas exitosamente';
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error al actualizar configuraciones: ' . $e->getMessage();
        }
    }
}

// Obtener configuraciones actuales
$stmt = $db->query("SELECT * FROM configuracion_sesiones ORDER BY FIELD(rol, 'superadmin', 'admin', 'vendedor', 'cajero')");
$configuraciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si no hay configuraciones, crear las por defecto
if (empty($configuraciones)) {
    $configuraciones_defecto = [
        ['rol' => 'superadmin', 'duracion_horas' => 48, 'descripcion' => 'Super administradores - Acceso extendido'],
        ['rol' => 'admin', 'duracion_horas' => 24, 'descripcion' => 'Administradores - Acceso diario completo'],
        ['rol' => 'vendedor', 'duracion_horas' => 12, 'descripcion' => 'Vendedores/Colaboradores - Turno extendido'],
        ['rol' => 'cajero', 'duracion_horas' => 8, 'descripcion' => 'Cajeros - Turno estándar']
    ];
    
    try {
        foreach ($configuraciones_defecto as $config) {
            $stmt = $db->prepare("
                INSERT IGNORE INTO configuracion_sesiones (rol, duracion_horas, descripcion) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$config['rol'], $config['duracion_horas'], $config['descripcion']]);
        }
        
        // Recargar configuraciones
        $stmt = $db->query("SELECT * FROM configuracion_sesiones ORDER BY FIELD(rol, 'superadmin', 'admin', 'vendedor', 'cajero')");
        $configuraciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = 'Error al crear configuraciones por defecto: ' . $e->getMessage();
    }
}

$page_title = 'Configuración de Sesiones';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-clock-history me-2"></i><?php echo $page_title; ?></h2>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Volver al Dashboard
                </a>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-gear me-2"></i>
                        Configurar Duración de Sesiones por Rol
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Importante:</strong> Los cambios se aplicarán a las nuevas sesiones. Los usuarios ya conectados necesitarán cerrar sesión y volver a iniciar para que apliquen los nuevos tiempos.
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="actualizar_configuraciones">
                        
                        <div class="row">
                            <?php foreach ($configuraciones as $config): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card border-<?php echo getRolColor($config['rol']); ?>">
                                    <div class="card-header bg-<?php echo getRolColor($config['rol']); ?> text-white">
                                        <h6 class="mb-0">
                                            <i class="bi bi-<?php echo getRolIcon($config['rol']); ?> me-2"></i>
                                            <?php echo ucfirst($config['rol']); ?>
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Duración en Horas</label>
                                            <div class="input-group">
                                                <input type="number" 
                                                       name="configuraciones[<?php echo $config['rol']; ?>][duracion_horas]" 
                                                       class="form-control" 
                                                       value="<?php echo $config['duracion_horas']; ?>" 
                                                       min="1" 
                                                       max="168" 
                                                       required>
                                                <span class="input-group-text">horas</span>
                                            </div>
                                            <small class="text-muted">Mínimo: 1 hora, Máximo: 168 horas (1 semana)</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Descripción</label>
                                            <input type="text" 
                                                   name="configuraciones[<?php echo $config['rol']; ?>][descripcion]" 
                                                   class="form-control" 
                                                   value="<?php echo htmlspecialchars($config['descripcion']); ?>" 
                                                   maxlength="255">
                                        </div>
                                        
                                        <div class="small text-muted">
                                            <strong>Actual:</strong> <?php echo $config['duracion_horas']; ?> 
                                            hora<?php echo $config['duracion_horas'] != 1 ? 's' : ''; ?>
                                            <br>
                                            <strong>En segundos:</strong> <?php echo $config['duracion_horas'] * 3600; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-check-circle me-2"></i>
                                Guardar Configuraciones
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
function getRolColor($rol) {
    $colors = [
        'superadmin' => 'danger',
        'admin' => 'primary',
        'vendedor' => 'success',
        'cajero' => 'info'
    ];
    return $colors[$rol] ?? 'secondary';
}

function getRolIcon($rol) {
    $icons = [
        'superadmin' => 'star-fill',
        'admin' => 'shield-fill-check',
        'vendedor' => 'person-fill',
        'cajero' => 'cash-coin'
    ];
    return $icons[$rol] ?? 'person';
}

include 'includes/footer.php';
?>