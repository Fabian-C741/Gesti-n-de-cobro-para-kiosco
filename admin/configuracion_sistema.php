<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();
require_once '../includes/functions.php';

require_admin();

$db = Database::getInstance()->getConnection();
$success = '';
$error = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Configuraciones de texto
        $configs = [
            'nombre_empresa', 'direccion_empresa', 'telefono_empresa', 
            'email_empresa', 'cuit_empresa', 'mensaje_ticket', 
            'formato_ticket', 'color_primario', 'color_secundario'
        ];
        
        foreach ($configs as $config) {
            if (isset($_POST[$config])) {
                $stmt = $db->prepare("
                    INSERT INTO configuracion_sistema (clave, valor) 
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE valor = ?
                ");
                $valor = sanitize_input($_POST[$config]);
                $stmt->execute([$config, $valor, $valor]);
            }
        }
        
        // Subir logo
        if (isset($_FILES['logo_sistema']) && $_FILES['logo_sistema']['error'] === 0) {
            $upload_dir = '../uploads/config/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $extension = strtolower(pathinfo($_FILES['logo_sistema']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            
            if (in_array($extension, $allowed)) {
                $filename = 'logo_' . time() . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['logo_sistema']['tmp_name'], $filepath)) {
                    $stmt = $db->prepare("
                        INSERT INTO configuracion_sistema (clave, valor) 
                        VALUES ('logo_sistema', ?)
                        ON DUPLICATE KEY UPDATE valor = ?
                    ");
                    $relative_path = 'uploads/config/' . $filename;
                    $stmt->execute([$relative_path, $relative_path]);
                }
            }
        }
        
        // Subir imagen de fondo login
        if (isset($_FILES['imagen_login']) && $_FILES['imagen_login']['error'] === 0) {
            $upload_dir = '../uploads/config/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $extension = strtolower(pathinfo($_FILES['imagen_login']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($extension, $allowed)) {
                $filename = 'login_bg_' . time() . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['imagen_login']['tmp_name'], $filepath)) {
                    $stmt = $db->prepare("
                        INSERT INTO configuracion_sistema (clave, valor) 
                        VALUES ('imagen_login', ?)
                        ON DUPLICATE KEY UPDATE valor = ?
                    ");
                    $relative_path = 'uploads/config/' . $filename;
                    $stmt->execute([$relative_path, $relative_path]);
                }
            }
        }
        
        // Subir favicon
        if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === 0) {
            $upload_dir = '../uploads/config/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $extension = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));
            $allowed = ['ico', 'png', 'jpg', 'jpeg', 'svg'];
            
            if (in_array($extension, $allowed)) {
                $filename = 'favicon.' . $extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['favicon']['tmp_name'], $filepath)) {
                    $stmt = $db->prepare("
                        INSERT INTO configuracion_sistema (clave, valor) 
                        VALUES ('favicon', ?)
                        ON DUPLICATE KEY UPDATE valor = ?
                    ");
                    $relative_path = 'uploads/config/' . $filename;
                    $stmt->execute([$relative_path, $relative_path]);
                }
            }
        }
        
        $success = 'Configuración guardada exitosamente';
    } catch (Exception $e) {
        $error = 'Error al guardar: ' . $e->getMessage();
    }
}

// Obtener configuración actual
$stmt = $db->query("SELECT clave, valor FROM configuracion_sistema");
$config = [];
while ($row = $stmt->fetch()) {
    $config[$row['clave']] = $row['valor'];
}

$page_title = 'Configuración del Sistema';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-sliders me-2"></i>Configuración del Sistema</h2>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        
                        <!-- Tabs de configuración -->
                        <ul class="nav nav-tabs mb-4" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#empresa">
                                    <i class="bi bi-building me-1"></i>Datos de Empresa
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#visual">
                                    <i class="bi bi-palette me-1"></i>Personalización Visual
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#tickets">
                                    <i class="bi bi-printer me-1"></i>Configuración de Tickets
                                </a>
                            </li>
                        </ul>

                        <div class="tab-content">
                            
                            <!-- Datos de Empresa -->
                            <div class="tab-pane fade show active" id="empresa">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nombre de la Empresa *</label>
                                        <input type="text" name="nombre_empresa" class="form-control" 
                                               value="<?php echo htmlspecialchars($config['nombre_empresa'] ?? 'Mi Empresa'); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">CUIT/RUT/RFC</label>
                                        <input type="text" name="cuit_empresa" class="form-control" 
                                               value="<?php echo htmlspecialchars($config['cuit_empresa'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Teléfono</label>
                                        <input type="text" name="telefono_empresa" class="form-control" 
                                               value="<?php echo htmlspecialchars($config['telefono_empresa'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email_empresa" class="form-control" 
                                               value="<?php echo htmlspecialchars($config['email_empresa'] ?? ''); ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Dirección</label>
                                        <textarea name="direccion_empresa" class="form-control" rows="2"><?php echo htmlspecialchars($config['direccion_empresa'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Personalización Visual -->
                            <div class="tab-pane fade" id="visual">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Logo del Sistema</label>
                                        <?php if (!empty($config['logo_sistema'])): ?>
                                            <div class="mb-2">
                                                <img src="../<?php echo htmlspecialchars($config['logo_sistema']); ?>" 
                                                     alt="Logo" style="max-height: 80px;" class="img-thumbnail">
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" name="logo_sistema" class="form-control" accept="image/*">
                                        <small class="text-muted">Formatos: JPG, PNG, SVG. Tamaño recomendado: 200x80px</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Imagen de Fondo del Login</label>
                                        <?php if (!empty($config['imagen_login'])): ?>
                                            <div class="mb-2">
                                                <img src="../<?php echo htmlspecialchars($config['imagen_login']); ?>" 
                                                     alt="Fondo Login" style="max-height: 80px;" class="img-thumbnail">
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" name="imagen_login" class="form-control" accept="image/*">
                                        <small class="text-muted">Formatos: JPG, PNG. Tamaño recomendado: 1920x1080px</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Favicon</label>
                                        <?php if (!empty($config['favicon'])): ?>
                                            <div class="mb-2">
                                                <img src="../<?php echo htmlspecialchars($config['favicon']); ?>" 
                                                     alt="Favicon" style="max-height: 32px;" class="img-thumbnail">
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" name="favicon" class="form-control" accept=".ico,.png,.jpg,.jpeg,.svg">
                                        <small class="text-muted">Formatos: ICO, PNG, SVG. Tamaño recomendado: 32x32px o 64x64px</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Color Primario</label>
                                        <input type="color" name="color_primario" class="form-control form-control-color" 
                                               value="<?php echo htmlspecialchars($config['color_primario'] ?? '#0d6efd'); ?>">
                                        <small class="text-muted">Color principal de la interfaz</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Color Secundario</label>
                                        <input type="color" name="color_secundario" class="form-control form-control-color" 
                                               value="<?php echo htmlspecialchars($config['color_secundario'] ?? '#6c757d'); ?>">
                                        <small class="text-muted">Color secundario de la interfaz</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Configuración de Tickets -->
                            <div class="tab-pane fade" id="tickets">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Formato de Ticket</label>
                                        <select name="formato_ticket" class="form-select">
                                            <option value="80mm" <?php echo ($config['formato_ticket'] ?? '80mm') === '80mm' ? 'selected' : ''; ?>>
                                                80mm (Estándar)
                                            </option>
                                            <option value="58mm" <?php echo ($config['formato_ticket'] ?? '') === '58mm' ? 'selected' : ''; ?>>
                                                58mm (Compacto)
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Mensaje al Pie del Ticket</label>
                                        <textarea name="mensaje_ticket" class="form-control" rows="2"><?php echo htmlspecialchars($config['mensaje_ticket'] ?? 'Gracias por su compra'); ?></textarea>
                                        <small class="text-muted">Mensaje que aparecerá al final de cada ticket</small>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <hr class="my-4">
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-save me-2"></i>Guardar Configuración
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
