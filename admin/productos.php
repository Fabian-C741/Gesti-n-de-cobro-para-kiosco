<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();
require_once '../includes/functions.php';

require_admin();

$db = Database::getInstance()->getConnection();
$error = '';
$success = '';
$reabrir_modal = false;

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'crear') {
            $codigo = sanitize_input($_POST['codigo'] ?? '');
            $codigo_barras = sanitize_input($_POST['codigo_barras'] ?? '');
            $nombre = sanitize_input($_POST['nombre']);
            $descripcion = sanitize_input($_POST['descripcion'] ?? '');
            $precio_compra = floatval($_POST['precio_compra'] ?? 0);
            $precio_venta = floatval($_POST['precio_venta']);
            $stock = intval($_POST['stock'] ?? 0);
            $stock_minimo = intval($_POST['stock_minimo'] ?? 0);
            $categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
            
            // Validar código de barras duplicado (solo si se proporciona uno)
            if (!empty($codigo_barras)) {
                $stmt_check = $db->prepare("SELECT id, nombre FROM productos WHERE codigo_barras = ? AND activo = 1");
                $stmt_check->execute([$codigo_barras]);
                $producto_existente = $stmt_check->fetch();
                
                if ($producto_existente) {
                    $error = "Ya existe un producto con este código de barras: \"{$producto_existente['nombre']}\"";
                }
            }
            
            if (empty($error)) {
            // Manejo de imagen
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
                    // Verificar si la columna punto_venta_id existe
                    $has_pv = column_exists($db, 'productos', 'punto_venta_id');
                    
                    if ($has_pv) {
                        $punto_venta_id = get_user_punto_venta_id();
                        $stmt = $db->prepare("
                            INSERT INTO productos (codigo, codigo_barras, nombre, descripcion, precio_compra, precio_venta, 
                                                 stock, stock_minimo, categoria_id, usuario_id, imagen, punto_venta_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $codigo, $codigo_barras, $nombre, $descripcion, $precio_compra, $precio_venta,
                            $stock, $stock_minimo, $categoria_id, $_SESSION['user_id'], $imagen_nombre, $punto_venta_id
                        ]);
                    } else {
                        $stmt = $db->prepare("
                            INSERT INTO productos (codigo, codigo_barras, nombre, descripcion, precio_compra, precio_venta, 
                                                 stock, stock_minimo, categoria_id, usuario_id, imagen)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $codigo, $codigo_barras, $nombre, $descripcion, $precio_compra, $precio_venta,
                            $stock, $stock_minimo, $categoria_id, $_SESSION['user_id'], $imagen_nombre
                        ]);
                    }
                    
                    log_activity($db, $_SESSION['user_id'], 'crear_producto', "Producto: $nombre");
                    $success = 'Producto creado exitosamente. ¡Listo para escanear otro!';
                    $reabrir_modal = true;
                } catch (PDOException $e) {
                    $error = 'Error al crear el producto';
                }
            }
            } // Cierre del if de validación código de barras
        } elseif ($action === 'editar') {
            $id = intval($_POST['id']);
            $codigo = sanitize_input($_POST['codigo'] ?? '');
            $codigo_barras = sanitize_input($_POST['codigo_barras'] ?? '');
            $nombre = sanitize_input($_POST['nombre']);
            $descripcion = sanitize_input($_POST['descripcion'] ?? '');
            $precio_compra = floatval($_POST['precio_compra'] ?? 0);
            $precio_venta = floatval($_POST['precio_venta']);
            $stock = intval($_POST['stock'] ?? 0);
            $stock_minimo = intval($_POST['stock_minimo'] ?? 0);
            $categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
            
            // Validar código de barras duplicado en edición (solo si se proporciona uno)
            if (!empty($codigo_barras)) {
                $stmt_check = $db->prepare("SELECT id, nombre FROM productos WHERE codigo_barras = ? AND activo = 1 AND id != ?");
                $stmt_check->execute([$codigo_barras, $id]);
                $producto_existente = $stmt_check->fetch();
                
                if ($producto_existente) {
                    $error = "Ya existe un producto con este código de barras: \"{$producto_existente['nombre']}\"";
                }
            }
            
            if (empty($error)) {
            // Manejo de nueva imagen
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
                    
                    if ($archivo['size'] > MAX_FILE_SIZE) {
                        $error = 'La imagen es demasiado grande. Máximo ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB';
                    } elseif (!validate_image_extension($archivo['name'])) {
                        $error = 'Formato de imagen no permitido';
                    } elseif (!getimagesize($archivo['tmp_name'])) {
                        $error = 'El archivo no es una imagen válida';
                    } else {
                        // Obtener imagen anterior para eliminarla
                        $stmt = $db->prepare("SELECT imagen FROM productos WHERE id = ?");
                        $stmt->execute([$id]);
                        $producto_actual = $stmt->fetch();
                        
                        $imagen_nombre = generate_unique_filename($archivo['name']);
                        $ruta_destino = UPLOAD_DIR . $imagen_nombre;
                        
                        if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                            // Eliminar imagen anterior
                            if (!empty($producto_actual['imagen'])) {
                                $ruta_anterior = UPLOAD_DIR . $producto_actual['imagen'];
                                if (file_exists($ruta_anterior)) {
                                    unlink($ruta_anterior);
                                }
                            }
                        } else {
                            $error = 'Error al subir la imagen';
                        }
                    }
                }
            }
            
            if (empty($error)) {
                try {
                    if (!empty($imagen_nombre)) {
                        // Actualizar con nueva imagen
                        $stmt = $db->prepare("
                            UPDATE productos 
                            SET codigo = ?, codigo_barras = ?, nombre = ?, descripcion = ?, precio_compra = ?, 
                                precio_venta = ?, stock = ?, stock_minimo = ?, categoria_id = ?, imagen = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $codigo, $codigo_barras, $nombre, $descripcion, $precio_compra, $precio_venta,
                            $stock, $stock_minimo, $categoria_id, $imagen_nombre, $id
                        ]);
                    } else {
                        // Actualizar sin cambiar imagen
                        $stmt = $db->prepare("
                            UPDATE productos 
                            SET codigo = ?, codigo_barras = ?, nombre = ?, descripcion = ?, precio_compra = ?, 
                                precio_venta = ?, stock = ?, stock_minimo = ?, categoria_id = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $codigo, $codigo_barras, $nombre, $descripcion, $precio_compra, $precio_venta,
                            $stock, $stock_minimo, $categoria_id, $id
                        ]);
                    }
                    
                    log_activity($db, $_SESSION['user_id'], 'editar_producto', "Producto ID: $id");
                    $success = 'Producto actualizado exitosamente';
                } catch (PDOException $e) {
                    $error = 'Error al actualizar el producto';
                }
            }
            } // Cierre del if de validación código de barras en edición
        } elseif ($action === 'eliminar') {
            $id = intval($_POST['id']);
            
            $stmt = $db->prepare("UPDATE productos SET activo = 0 WHERE id = ?");
            $stmt->execute([$id]);
            
            log_activity($db, $_SESSION['user_id'], 'eliminar_producto', "Producto ID: $id");
            $success = 'Producto eliminado exitosamente';
        } elseif ($action === 'activar') {
            $id = intval($_POST['id']);
            
            $stmt = $db->prepare("UPDATE productos SET activo = 1 WHERE id = ?");
            $stmt->execute([$id]);
            
            log_activity($db, $_SESSION['user_id'], 'activar_producto', "Producto ID: $id");
            $success = 'Producto activado exitosamente';
        }
    }
}

// Obtener productos
$search = $_GET['search'] ?? '';
$categoria_filter = $_GET['categoria'] ?? '';
$pv_id = get_user_punto_venta_id();
$has_pv_column = column_exists($db, 'productos', 'punto_venta_id');

$query = "
    SELECT p.*, c.nombre as categoria_nombre 
    FROM productos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE 1=1
";

$params = [];

// Filtrar por punto de venta SOLO si la columna existe y el usuario tiene uno asignado
if ($pv_id && $has_pv_column) {
    $query .= " AND (p.punto_venta_id = ? OR p.punto_venta_id IS NULL)";
    $params[] = $pv_id;
}

if (!empty($search)) {
    $query .= " AND (p.nombre LIKE ? OR p.codigo LIKE ? OR p.descripcion LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($categoria_filter)) {
    $query .= " AND p.categoria_id = ?";
    $params[] = $categoria_filter;
}

$query .= " ORDER BY p.nombre ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$productos = $stmt->fetchAll();

// Obtener categorías para el filtro
$has_pv_cat = column_exists($db, 'categorias', 'punto_venta_id');
if ($pv_id && $has_pv_cat) {
    $stmt_cat = $db->prepare("SELECT * FROM categorias WHERE activo = 1 AND (punto_venta_id = ? OR punto_venta_id IS NULL) ORDER BY nombre");
    $stmt_cat->execute([$pv_id]);
} else {
    $stmt_cat = $db->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre");
}
$categorias = $stmt_cat->fetchAll();

$page_title = 'Productos';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-box-seam"></i> Gestión de Productos</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProducto">
            <i class="bi bi-plus-circle"></i> Nuevo Producto
        </button>
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

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" placeholder="Buscar por nombre, código o descripción..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4">
                    <select name="categoria" class="form-select">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $categoria_filter == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de productos -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Imagen</th>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Precio Compra</th>
                            <th>Precio Venta</th>
                            <th>Stock</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productos)): ?>
                            <tr>
                                <td colspan="9" class="text-center">No hay productos registrados</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($productos as $producto): ?>
                                <tr>
                                    <td>
                                        <?php if ($producto['imagen']): ?>
                                            <img src="../uploads/<?php echo htmlspecialchars($producto['imagen']); ?>" alt="Producto" style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-secondary text-white d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                <i class="bi bi-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($producto['codigo']); ?></td>
                                    <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría'); ?></td>
                                    <td>$<?php echo number_format($producto['precio_compra'], 2); ?></td>
                                    <td>$<?php echo number_format($producto['precio_venta'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php echo $producto['stock'] <= $producto['stock_minimo'] ? 'bg-danger' : 'bg-success'; ?>">
                                            <?php echo $producto['stock']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $producto['activo'] ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $producto['activo'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="editarProducto(<?php echo htmlspecialchars(json_encode($producto)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($producto['activo']): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar este producto?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="eliminar">
                                                <input type="hidden" name="id" value="<?php echo $producto['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="activar">
                                                <input type="hidden" name="id" value="<?php echo $producto['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Producto -->
<div class="modal fade" id="modalProducto" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" id="formProducto">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalProductoTitle">Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="crear" id="productoAction">
                    <input type="hidden" name="id" id="productoId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Código de Barras</label>
                            <input type="text" class="form-control" name="codigo_barras" id="productoCodigoBarras" placeholder="Escanea o escribe">
                            <small class="text-muted">Escanea con el lector o escribe manualmente</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Código/SKU</label>
                            <input type="text" class="form-control" name="codigo" id="productoCodigo">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Nombre *</label>
                            <input type="text" class="form-control" name="nombre" id="productoNombre" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" id="productoDescripcion" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio de Compra</label>
                            <input type="number" step="0.01" class="form-control" name="precio_compra" id="productoPrecioCompra" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio de Venta *</label>
                            <input type="number" step="0.01" class="form-control" name="precio_venta" id="productoPrecioVenta" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Stock</label>
                            <input type="number" class="form-control" name="stock" id="productoStock" value="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Stock Mínimo</label>
                            <input type="number" class="form-control" name="stock_minimo" id="productoStockMinimo" value="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Categoría</label>
                            <select class="form-select" name="categoria_id" id="productoCategoria">
                                <option value="">Sin categoría</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Imagen del Producto</label>
                        <input type="file" class="form-control" name="imagen" accept="image/*">
                        <small class="text-muted">Formatos: JPG, PNG, GIF, WebP. Máximo 5MB</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editarProducto(producto) {
    document.getElementById('modalProductoTitle').textContent = 'Editar Producto';
    document.getElementById('productoAction').value = 'editar';
    document.getElementById('productoId').value = producto.id;
    document.getElementById('productoCodigoBarras').value = producto.codigo_barras || '';
    document.getElementById('productoCodigo').value = producto.codigo || '';
    document.getElementById('productoNombre').value = producto.nombre;
    document.getElementById('productoDescripcion').value = producto.descripcion || '';
    document.getElementById('productoPrecioCompra').value = producto.precio_compra;
    document.getElementById('productoPrecioVenta').value = producto.precio_venta;
    document.getElementById('productoStock').value = producto.stock;
    document.getElementById('productoStockMinimo').value = producto.stock_minimo;
    document.getElementById('productoCategoria').value = producto.categoria_id || '';
    
    new bootstrap.Modal(document.getElementById('modalProducto')).show();
}

function limpiarFormulario() {
    document.getElementById('formProducto').reset();
    document.getElementById('modalProductoTitle').textContent = 'Nuevo Producto';
    document.getElementById('productoAction').value = 'crear';
    document.getElementById('productoId').value = '';
    document.getElementById('productoCodigoBarras').value = '';
}

function buscarPorCodigoBarras() {
    const codigoBarras = document.getElementById('productoCodigoBarras').value.trim();
    
    if (!codigoBarras) {
        return;
    }
    
    // Solo buscar para editar si el usuario lo solicita explícitamente
    fetch(`../api/buscar_producto.php?codigo_barras=${encodeURIComponent(codigoBarras)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.producto) {
                if (confirm(`El producto "${data.producto.nombre}" ya existe. ¿Deseas editarlo?`)) {
                    editarProducto(data.producto);
                }
            } else {
                // No mostrar alerta - simplemente continuar
                document.getElementById('productoNombre').focus();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('productoNombre').focus();
        });
}

document.getElementById('modalProducto').addEventListener('hidden.bs.modal', function () {
    limpiarFormulario();
});

// Reabrir modal para escaneo continuo
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($reabrir_modal && !empty($success)): ?>
    // Limpiar formulario y reabrir modal para escaneo continuo
    limpiarFormulario();
    const modal = new bootstrap.Modal(document.getElementById('modalProducto'));
    modal.show();
    setTimeout(function() {
        document.getElementById('productoCodigoBarras').focus();
    }, 500);
    <?php endif; ?>
    
    // Al escanear (Enter), pasar al siguiente campo sin buscar
    const codigoBarrasInput = document.getElementById('productoCodigoBarras');
    if (codigoBarrasInput) {
        codigoBarrasInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('productoNombre').focus();
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
