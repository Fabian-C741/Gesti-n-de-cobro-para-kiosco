<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();
require_once '../includes/functions.php';

require_admin();

$db = Database::getInstance()->getConnection();
$error = '';
$success = '';

// Obtener configuración actual
$stmt = $db->query("SELECT * FROM personalizacion ORDER BY categoria, clave");
$config_items = $stmt->fetchAll(PDO::FETCH_GROUP);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $db->beginTransaction();
        
        try {
            // Actualizar colores
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'config_') === 0) {
                    $config_key = substr($key, 7); // Remover "config_"
                    $stmt = $db->prepare("UPDATE personalizacion SET valor = ? WHERE clave = ?");
                    $stmt->execute([$value, $config_key]);
                }
            }
            
            // Subir imagen de fondo del login
            if (isset($_FILES['login_bg_image']) && $_FILES['login_bg_image']['error'] === UPLOAD_ERR_OK) {
                $upload_result = upload_image($_FILES['login_bg_image'], '../uploads/backgrounds/');
                if ($upload_result['success']) {
                    $stmt = $db->prepare("UPDATE personalizacion SET valor = ? WHERE clave = 'login_bg_image'");
                    $stmt->execute([$upload_result['filename']]);
                } else {
                    throw new Exception($upload_result['error']);
                }
            }
            
            // Subir logo
            if (isset($_FILES['logo_image']) && $_FILES['logo_image']['error'] === UPLOAD_ERR_OK) {
                $upload_result = upload_image($_FILES['logo_image'], '../uploads/logos/');
                if ($upload_result['success']) {
                    $stmt = $db->prepare("UPDATE personalizacion SET valor = ? WHERE clave = 'logo_image'");
                    $stmt->execute([$upload_result['filename']]);
                } else {
                    throw new Exception($upload_result['error']);
                }
            }
            
            $db->commit();
            
            // Generar CSS personalizado
            generate_custom_css($db);
            
            log_activity($db, $_SESSION['user_id'], 'personalizar_sistema', 'Configuración de personalización actualizada');
            $success = 'Personalización actualizada exitosamente';
            
            // Recargar configuración
            $stmt = $db->query("SELECT * FROM personalizacion ORDER BY categoria, clave");
            $config_items = $stmt->fetchAll(PDO::FETCH_GROUP);
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Resetear a valores por defecto
if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    $stmt = $db->query("
        UPDATE personalizacion SET 
        valor = CASE clave
            WHEN 'primary_color' THEN '#0d6efd'
            WHEN 'secondary_color' THEN '#6c757d'
            WHEN 'success_color' THEN '#198754'
            WHEN 'danger_color' THEN '#dc3545'
            WHEN 'sidebar_bg_start' THEN '#0d6efd'
            WHEN 'sidebar_bg_end' THEN '#0a58ca'
            WHEN 'btn_border_radius' THEN '8'
            WHEN 'card_border_radius' THEN '12'
            ELSE valor
        END
    ");
    
    generate_custom_css($db);
    log_activity($db, $_SESSION['user_id'], 'personalizar_sistema', 'Resetear personalización a valores por defecto');
    redirect('personalizacion.php?success=reset');
}

if (isset($_GET['success']) && $_GET['success'] === 'reset') {
    $success = 'Personalización restaurada a valores por defecto';
}

$page_title = 'Personalización';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-palette"></i> Personalización del Sistema</h2>
        <a href="?reset=1" class="btn btn-outline-secondary" onclick="return confirm('¿Restaurar todos los valores por defecto?')">
            <i class="bi bi-arrow-counterclockwise"></i> Restaurar Valores
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        
        <div class="row">
            <!-- Colores -->
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-palette-fill"></i> Colores del Sistema</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $color_labels = [
                            'primary_color' => 'Color Primario',
                            'secondary_color' => 'Color Secundario',
                            'success_color' => 'Color Éxito',
                            'danger_color' => 'Color Peligro',
                            'sidebar_bg_start' => 'Sidebar - Color Inicio',
                            'sidebar_bg_end' => 'Sidebar - Color Fin'
                        ];
                        
                        $stmt = $db->query("SELECT * FROM personalizacion WHERE tipo = 'color'");
                        $colores = $stmt->fetchAll();
                        
                        foreach ($colores as $color):
                        ?>
                            <div class="mb-3 row align-items-center">
                                <label class="col-sm-5 col-form-label">
                                    <?php echo $color_labels[$color['clave']] ?? $color['clave']; ?>
                                </label>
                                <div class="col-sm-7">
                                    <div class="input-group">
                                        <input type="color" class="form-control form-control-color" 
                                               name="config_<?php echo $color['clave']; ?>" 
                                               value="<?php echo $color['valor']; ?>">
                                        <input type="text" class="form-control" 
                                               value="<?php echo $color['valor']; ?>" 
                                               readonly>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Estilos -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-sliders"></i> Estilos</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt = $db->query("SELECT * FROM personalizacion WHERE categoria = 'estilos'");
                        $estilos = $stmt->fetchAll();
                        
                        foreach ($estilos as $estilo):
                        ?>
                            <div class="mb-3">
                                <label class="form-label">
                                    <?php 
                                    $labels = [
                                        'btn_border_radius' => 'Radio de Bordes - Botones (px)',
                                        'card_border_radius' => 'Radio de Bordes - Tarjetas (px)'
                                    ];
                                    echo $labels[$estilo['clave']] ?? $estilo['clave'];
                                    ?>
                                </label>
                                <input type="number" class="form-control" 
                                       name="config_<?php echo $estilo['clave']; ?>" 
                                       value="<?php echo $estilo['valor']; ?>" 
                                       min="0" max="50">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Imágenes -->
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-image"></i> Imágenes</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt = $db->query("SELECT * FROM personalizacion WHERE clave = 'login_bg_image'");
                        $login_bg = $stmt->fetch();
                        ?>
                        
                        <div class="mb-4">
                            <label class="form-label"><strong>Fondo del Login</strong></label>
                            <input type="file" class="form-control" name="login_bg_image" accept="image/*">
                            <small class="text-muted">Recomendado: 1920x1080px</small>
                            
                            <?php if (!empty($login_bg['valor'])): ?>
                                <div class="mt-3">
                                    <img src="../uploads/backgrounds/<?php echo $login_bg['valor']; ?>" 
                                         class="img-thumbnail" style="max-width: 100%; max-height: 200px;">
                                    <div class="mt-2">
                                        <small class="text-muted">Imagen actual</small>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <hr>

                        <?php
                        $stmt = $db->query("SELECT * FROM personalizacion WHERE clave = 'logo_image'");
                        $logo = $stmt->fetch();
                        ?>
                        
                        <div class="mb-3">
                            <label class="form-label"><strong>Logo del Sistema</strong></label>
                            <input type="file" class="form-control" name="logo_image" accept="image/*">
                            <small class="text-muted">Recomendado: 200x200px (PNG transparente)</small>
                            
                            <?php if (!empty($logo['valor'])): ?>
                                <div class="mt-3">
                                    <img src="../uploads/logos/<?php echo $logo['valor']; ?>" 
                                         class="img-thumbnail" style="max-width: 200px;">
                                    <div class="mt-2">
                                        <small class="text-muted">Logo actual</small>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Vista Previa -->
                <div class="card">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="bi bi-eye"></i> Vista Previa</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex gap-2 mb-3">
                            <button type="button" class="btn btn-primary">Primario</button>
                            <button type="button" class="btn btn-secondary">Secundario</button>
                            <button type="button" class="btn btn-success">Éxito</button>
                            <button type="button" class="btn btn-danger">Peligro</button>
                        </div>
                        
                        <div class="card">
                            <div class="card-body">
                                <h6>Ejemplo de Tarjeta</h6>
                                <p class="mb-0">Con bordes personalizados</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <button type="submit" class="btn btn-primary btn-lg px-5">
                <i class="bi bi-check-circle"></i> Guardar Cambios
            </button>
        </div>
    </form>
</div>

<style>
/* Actualizar colores en tiempo real */
<?php
$stmt = $db->query("SELECT clave, valor FROM personalizacion WHERE tipo = 'color'");
$colores_db = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

:root {
    --primary-color: <?php echo $colores_db['primary_color'] ?? '#0d6efd'; ?>;
    --secondary-color: <?php echo $colores_db['secondary_color'] ?? '#6c757d'; ?>;
    --success-color: <?php echo $colores_db['success_color'] ?? '#198754'; ?>;
    --danger-color: <?php echo $colores_db['danger_color'] ?? '#dc3545'; ?>;
}

.sidebar {
    background: linear-gradient(180deg, 
        <?php echo $colores_db['sidebar_bg_start'] ?? '#0d6efd'; ?> 0%, 
        <?php echo $colores_db['sidebar_bg_end'] ?? '#0a58ca'; ?> 100%) !important;
}
</style>

<script>
// Actualizar preview en tiempo real
document.querySelectorAll('input[type="color"]').forEach(input => {
    input.addEventListener('input', function() {
        this.nextElementSibling.value = this.value;
        updatePreview();
    });
});

function updatePreview() {
    const primary = document.querySelector('[name="config_primary_color"]').value;
    const secondary = document.querySelector('[name="config_secondary_color"]').value;
    const success = document.querySelector('[name="config_success_color"]').value;
    const danger = document.querySelector('[name="config_danger_color"]').value;
    
    document.querySelector('.btn-primary').style.backgroundColor = primary;
    document.querySelector('.btn-secondary').style.backgroundColor = secondary;
    document.querySelector('.btn-success').style.backgroundColor = success;
    document.querySelector('.btn-danger').style.backgroundColor = danger;
}
</script>

<?php include 'includes/footer.php'; ?>
