<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();
require_once '../includes/functions.php';

require_login();

// BLOQUEAR acceso a cajeros - Solo vendedores y admins pueden gestionar productos
if ($_SESSION['user_rol'] === 'cajero') {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$usuario_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Crear directorio de uploads si no existe
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'crear' || $action === 'editar') {
            $id = intval($_POST['id'] ?? 0);
            $codigo = sanitize_input($_POST['codigo'] ?? '');
            $codigo_barras = sanitize_input($_POST['codigo_barras'] ?? '');
            $nombre = sanitize_input($_POST['nombre'] ?? '');
            $descripcion = sanitize_input($_POST['descripcion'] ?? '');
            $precio_compra = floatval($_POST['precio_compra'] ?? 0);
            $precio_venta = floatval($_POST['precio_venta'] ?? 0);
            $stock = intval($_POST['stock'] ?? 0);
            $stock_minimo = intval($_POST['stock_minimo'] ?? 0);
            $categoria_id = intval($_POST['categoria_id'] ?? 0);
            $activo = isset($_POST['activo']) ? 1 : 0;
            
            if (empty($nombre) || $precio_venta <= 0) {
                $error = 'El nombre y precio de venta son obligatorios';
            } else {
                // Procesar imagen si se subió
                $imagen_nombre = '';
                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
                        $upload_errors = [
                            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por el servidor',
                            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido',
                            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
                            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal',
                            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
                            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida'
                        ];
                        $error = $upload_errors[$_FILES['imagen']['error']] ?? 'Error desconocido al subir la imagen';
                    } else {
                        $archivo = $_FILES['imagen'];
                        
                        // Validar tamaño
                        if ($archivo['size'] > MAX_FILE_SIZE) {
                            $error = 'La imagen es demasiado grande. Máximo ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB';
                        }
                        // Validar extensión
                        elseif (!validate_image_extension($archivo['name'])) {
                            $error = 'Formato de imagen no permitido. Use: ' . implode(', ', ALLOWED_EXTENSIONS);
                        }
                        // Validar que sea una imagen real
                        elseif (!getimagesize($archivo['tmp_name'])) {
                            $error = 'El archivo no es una imagen válida';
                        } else {
                            $imagen_nombre = generate_unique_filename($archivo['name']);
                            $ruta_destino = UPLOAD_DIR . $imagen_nombre;
                            
                            if (!move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                                $error = 'Error al subir la imagen. Verifica permisos de la carpeta uploads/';
                            }
                        }
                    }
                }
                
                if (empty($error)) {
                    try {
                        if ($action === 'crear') {
                            $stmt = $db->prepare("
                                INSERT INTO productos (codigo, codigo_barras, nombre, descripcion, precio_compra, precio_venta, 
                                                     stock, stock_minimo, categoria_id, usuario_id, imagen, activo)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $codigo, $codigo_barras, $nombre, $descripcion, $precio_compra, $precio_venta,
                                $stock, $stock_minimo, $categoria_id ?: null, $usuario_id, $imagen_nombre, $activo
                            ]);
                            
                            log_activity($db, $usuario_id, 'crear_producto', "Producto creado: $nombre");
                            $success = 'Producto creado exitosamente';
                        } else {
                            // Verificar que el producto pertenezca al usuario
                            $stmt = $db->prepare("SELECT imagen FROM productos WHERE id = ? AND usuario_id = ?");
                            $stmt->execute([$id, $usuario_id]);
                            $producto_actual = $stmt->fetch();
                            
                            if (!$producto_actual) {
                                $error = 'Producto no encontrado';
                            } else {
                                // Si hay nueva imagen, eliminar la anterior
                                if (!empty($imagen_nombre) && !empty($producto_actual['imagen'])) {
                                    $ruta_anterior = UPLOAD_DIR . $producto_actual['imagen'];
                                    if (file_exists($ruta_anterior)) {
                                        unlink($ruta_anterior);
                                    }
                                } elseif (empty($imagen_nombre)) {
                                    $imagen_nombre = $producto_actual['imagen'];
                                }
                                
                                $stmt = $db->prepare("
                                    UPDATE productos 
                                    SET codigo = ?, codigo_barras = ?, nombre = ?, descripcion = ?, precio_compra = ?, precio_venta = ?,
                                        stock = ?, stock_minimo = ?, categoria_id = ?, imagen = ?, activo = ?
                                    WHERE id = ? AND usuario_id = ?
                                ");
                                $stmt->execute([
                                    $codigo, $codigo_barras, $nombre, $descripcion, $precio_compra, $precio_venta,
                                    $stock, $stock_minimo, $categoria_id ?: null, $imagen_nombre, $activo, $id, $usuario_id
                                ]);
                                
                                log_activity($db, $usuario_id, 'editar_producto', "Producto editado: $nombre");
                                $success = 'Producto actualizado exitosamente';
                            }
                        }
                    } catch (PDOException $e) {
                        $error = DEBUG_MODE ? 'Error: ' . $e->getMessage() : 'Error al guardar el producto';
                        
                        // Eliminar imagen si hubo error
                        if (!empty($imagen_nombre) && file_exists(UPLOAD_DIR . $imagen_nombre)) {
                            unlink(UPLOAD_DIR . $imagen_nombre);
                        }
                    }
                }
            }
        } elseif ($action === 'eliminar') {
            $id = intval($_POST['id'] ?? 0);
            
            try {
                // Obtener imagen para eliminarla
                $stmt = $db->prepare("SELECT imagen FROM productos WHERE id = ? AND usuario_id = ?");
                $stmt->execute([$id, $usuario_id]);
                $producto = $stmt->fetch();
                
                if ($producto) {
                    $stmt = $db->prepare("DELETE FROM productos WHERE id = ? AND usuario_id = ?");
                    $stmt->execute([$id, $usuario_id]);
                    
                    // Eliminar imagen
                    if (!empty($producto['imagen'])) {
                        $ruta_imagen = UPLOAD_DIR . $producto['imagen'];
                        if (file_exists($ruta_imagen)) {
                            unlink($ruta_imagen);
                        }
                    }
                    
                    log_activity($db, $usuario_id, 'eliminar_producto', "Producto eliminado ID: $id");
                    $success = 'Producto eliminado exitosamente';
                }
            } catch (PDOException $e) {
                $error = 'No se puede eliminar el producto. Puede estar asociado a ventas';
            }
        }
    }
}

// Obtener productos del usuario
$filtro_categoria = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
$buscar = isset($_GET['buscar']) ? sanitize_input($_GET['buscar']) : '';

$sql = "SELECT p.*, c.nombre as categoria_nombre 
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE p.usuario_id = ?";
$params = [$usuario_id];

if ($filtro_categoria > 0) {
    $sql .= " AND p.categoria_id = ?";
    $params[] = $filtro_categoria;
}

if (!empty($buscar)) {
    $sql .= " AND (p.nombre LIKE ? OR p.codigo LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

$sql .= " ORDER BY p.fecha_creacion DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

// Obtener categorías activas (propias o creadas por cualquier admin)
$stmt = $db->prepare("
    SELECT DISTINCT c.* FROM categorias c
    LEFT JOIN usuarios u ON c.usuario_id = u.id
    WHERE c.activo = 1 AND (c.usuario_id = ? OR u.user_rol = 'admin')
    ORDER BY c.nombre
");
$stmt->execute([$usuario_id]);
$categorias = $stmt->fetchAll();

$page_title = 'Mis Productos';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-box-seam me-2"></i>Mis Productos</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
            <i class="bi bi-plus-circle me-1"></i> Nuevo Producto
        </button>
    </div>

    <?php if ($error): ?>
        <?php echo show_alert($error, 'error'); ?>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <?php echo show_alert($success, 'success'); ?>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="buscar" class="form-control" placeholder="Buscar por nombre o código..." 
                           value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                <div class="col-md-3">
                    <select name="categoria" class="form-select">
                        <option value="0">Todas las categorías</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" 
                                    <?php echo $filtro_categoria == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-1"></i> Filtrar
                    </button>
                </div>
                <?php if ($filtro_categoria > 0 || !empty($buscar)): ?>
                    <div class="col-md-2">
                        <a href="productos.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-1"></i> Limpiar
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Lista de productos -->
    <div class="row g-3">
        <?php if (empty($productos)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    No hay productos registrados. ¡Crea tu primer producto!
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($productos as $producto): ?>
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="card h-100">
                        <?php if ($producto['imagen']): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($producto['imagen']); ?>" 
                                 class="card-img-top" style="height: 200px; object-fit: cover;" alt="Producto">
                        <?php else: ?>
                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                 style="height: 200px;">
                                <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($producto['nombre']); ?></h5>
                            <?php if ($producto['codigo']): ?>
                                <p class="text-muted small mb-1">Código: <?php echo htmlspecialchars($producto['codigo']); ?></p>
                            <?php endif; ?>
                            <?php if ($producto['categoria_nombre']): ?>
                                <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($producto['categoria_nombre']); ?></span>
                            <?php endif; ?>
                            
                            <div class="mb-2">
                                <strong class="text-primary"><?php echo format_price($producto['precio_venta']); ?></strong>
                            </div>
                            
                            <div class="mb-2">
                                Stock: 
                                <?php
                                $stock_class = 'stock-high';
                                if ($producto['stock'] <= $producto['stock_minimo']) {
                                    $stock_class = 'stock-low';
                                } elseif ($producto['stock'] <= $producto['stock_minimo'] * 1.5) {
                                    $stock_class = 'stock-medium';
                                }
                                ?>
                                <span class="<?php echo $stock_class; ?>"><?php echo $producto['stock']; ?></span>
                                <small class="text-muted">(Min: <?php echo $producto['stock_minimo']; ?>)</small>
                            </div>
                            
                            <?php if (!$producto['activo']): ?>
                                <span class="badge bg-danger mb-2">Inactivo</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-footer bg-white border-top-0">
                            <div class="btn-group w-100">
                                <button class="btn btn-sm btn-warning" onclick='editarProducto(<?php echo json_encode($producto); ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="eliminarProducto(<?php echo $producto['id']; ?>, '<?php echo htmlspecialchars($producto['nombre']); ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Crear Producto -->
<div class="modal fade" id="modalCrear" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="crear">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Código de Barras</label>
                            <div class="input-group">
                                <input type="text" name="codigo_barras" id="codigo_barras_crear" class="form-control" placeholder="Escanea o escribe">
                                <button type="button" class="btn btn-outline-secondary" onclick="buscarPorCodigoBarras('crear')">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                            <small class="text-muted">Escanea con el lector o escribe manualmente</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Código/SKU</label>
                            <input type="text" name="codigo" id="codigo_crear" class="form-control">
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" id="nombre_crear" class="form-control" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="2"></textarea>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Categoría</label>
                            <select name="categoria_id" class="form-select">
                                <option value="0">Sin categoría</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Imagen</label>
                            <input type="file" name="imagen" class="form-control" accept="image/*" 
                                   onchange="previewImage(this, 'preview_crear')">
                            <small class="text-muted">Máximo <?php echo MAX_FILE_SIZE / 1024 / 1024; ?>MB</small>
                            <img id="preview_crear" class="image-preview mt-2" style="display: none;">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Precio Compra</label>
                            <input type="number" name="precio_compra" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Precio Venta *</label>
                            <input type="number" name="precio_venta" class="form-control" step="0.01" min="0" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Stock Inicial *</label>
                            <input type="number" name="stock" class="form-control" min="0" value="0" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Stock Mínimo</label>
                            <input type="number" name="stock_minimo" class="form-control" min="0" value="5">
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input type="checkbox" name="activo" class="form-check-input" id="crear_activo" checked>
                                <label class="form-check-label" for="crear_activo">Producto Activo</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Producto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Producto -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="editar">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Código de Barras</label>
                            <input type="text" name="codigo_barras" id="edit_codigo_barras" class="form-control">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Código/SKU</label>
                            <input type="text" name="codigo" id="edit_codigo" class="form-control">
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" id="edit_descripcion" class="form-control" rows="2"></textarea>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Categoría</label>
                            <select name="categoria_id" id="edit_categoria_id" class="form-select">
                                <option value="0">Sin categoría</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Nueva Imagen</label>
                            <input type="file" name="imagen" class="form-control" accept="image/*"
                                   onchange="previewImage(this, 'preview_editar')">
                            <small class="text-muted">Dejar vacío para mantener la actual</small>
                            <img id="preview_editar" class="image-preview mt-2" style="display: none;">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Precio Compra</label>
                            <input type="number" name="precio_compra" id="edit_precio_compra" class="form-control" step="0.01" min="0">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Precio Venta *</label>
                            <input type="number" name="precio_venta" id="edit_precio_venta" class="form-control" step="0.01" min="0" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Stock Actual *</label>
                            <input type="number" name="stock" id="edit_stock" class="form-control" min="0" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Stock Mínimo</label>
                            <input type="number" name="stock_minimo" id="edit_stock_minimo" class="form-control" min="0">
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input type="checkbox" name="activo" id="edit_activo" class="form-check-input">
                                <label class="form-check-label" for="edit_activo">Producto Activo</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Eliminar -->
<div class="modal fade" id="modalEliminar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Eliminar Producto</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="eliminar">
                    <input type="hidden" name="id" id="delete_id">
                    
                    <p>¿Está seguro de eliminar el producto <strong id="delete_nombre"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Esta acción no se puede deshacer.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editarProducto(producto) {
    document.getElementById('edit_id').value = producto.id;
    document.getElementById('edit_codigo_barras').value = producto.codigo_barras || '';
    document.getElementById('edit_codigo').value = producto.codigo || '';
    document.getElementById('edit_nombre').value = producto.nombre;
    document.getElementById('edit_descripcion').value = producto.descripcion || '';
    document.getElementById('edit_categoria_id').value = producto.categoria_id || 0;
    document.getElementById('edit_precio_compra').value = producto.precio_compra;
    document.getElementById('edit_precio_venta').value = producto.precio_venta;
    document.getElementById('edit_stock').value = producto.stock;
    document.getElementById('edit_stock_minimo').value = producto.stock_minimo;
    document.getElementById('edit_activo').checked = producto.activo == 1;
    
    // Mostrar imagen actual si existe
    const preview = document.getElementById('preview_editar');
    if (producto.imagen) {
        preview.src = '../uploads/' + producto.imagen;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
    
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}

function eliminarProducto(id, nombre) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_nombre').textContent = nombre;
    
    new bootstrap.Modal(document.getElementById('modalEliminar')).show();
}

function buscarPorCodigoBarras(modo) {
    const codigoBarras = document.getElementById(`codigo_barras_${modo}`).value.trim();
    
    if (!codigoBarras) {
        alert('Por favor ingresa un código de barras');
        return;
    }
    
    // Buscar si ya existe el producto
    fetch(`../api/buscar_producto.php?codigo_barras=${encodeURIComponent(codigoBarras)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.producto) {
                // Producto encontrado - preguntar si quiere editar
                if (confirm(`El producto "${data.producto.nombre}" ya existe. ¿Deseas editarlo?`)) {
                    editarProducto(data.producto);
                }
            } else {
                // Producto no existe - mantener código de barras y permitir crear
                alert('Producto no encontrado. Puedes registrarlo con este código de barras.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Producto no encontrado. Puedes registrarlo con este código de barras.');
        });
}
</script>

<?php include 'includes/footer.php'; ?>
