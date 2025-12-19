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
            $codigo_barras = trim($_POST['codigo_barras'] ?? '');
            $nombre = sanitize_input($_POST['nombre']);
            $descripcion = sanitize_input($_POST['descripcion'] ?? '');
            $precio_compra = floatval($_POST['precio_compra'] ?? 0);
            $precio_venta = floatval($_POST['precio_venta']);
            $stock = intval($_POST['stock'] ?? 0);
            $stock_minimo = intval($_POST['stock_minimo'] ?? 0);
            $categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
            
            // Validar código de barras duplicado SIEMPRE antes de crear
            if (!empty($codigo_barras)) {
                $stmt_check = $db->prepare("SELECT id, nombre FROM productos WHERE codigo_barras = ? LIMIT 1");
                $stmt_check->execute([$codigo_barras]);
                $producto_existente = $stmt_check->fetch(PDO::FETCH_ASSOC);
                
                if ($producto_existente) {
                    $error = "El código de barras ya existe. Producto: \"{$producto_existente['nombre']}\"";
                    $reabrir_modal = true;
                }
            }
            
            // SOLO continuar si NO hay error
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
            }
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
                $stmt_check = $db->prepare("SELECT id, nombre FROM productos WHERE codigo_barras = ? AND id != ?");
                $stmt_check->execute([$codigo_barras, $id]);
                $producto_existente = $stmt_check->fetch();
                
                if ($producto_existente) {
                    $error = "El código de barras ya existe. Producto: \"{$producto_existente['nombre']}\"";
                    $reabrir_modal = true;
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
                            <div class="input-group">
                                <input type="text" class="form-control" name="codigo_barras" id="productoCodigoBarras" placeholder="Escanea o escribe">
                                <button type="button" class="btn btn-outline-primary" onclick="abrirEscanerProducto()" title="Escanear con cámara">
                                    <i class="bi bi-camera"></i>
                                </button>
                            </div>
                            <small class="text-muted">Usa la <strong>📷 cámara</strong> o escribe manualmente</small>
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
    document.getElementById('productoNombre').disabled = false;
}

// Validar código usando buscar_producto.php que SÍ FUNCIONA
function validarCodigo() {
    const input = document.getElementById('productoCodigoBarras');
    const nombreInput = document.getElementById('productoNombre');
    const codigo = input.value.trim();
    const productoId = document.getElementById('productoId').value || 0;
    
    if (!codigo) {
        nombreInput.focus();
        return;
    }
    
    const xhr = new XMLHttpRequest();
    // Usar buscar_producto.php que ya funciona
    xhr.open('GET', '../api/buscar_producto.php?codigo_barras=' + encodeURIComponent(codigo), true);
    xhr.timeout = 5000;
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const data = JSON.parse(xhr.responseText);
                // Si encontró producto Y no es el mismo que estamos editando
                if (data.success && data.producto) {
                    if (productoId == 0 || data.producto.id != productoId) {
                        alert('¡DUPLICADO! El producto "' + data.producto.nombre + '" ya tiene ese código de barras.');
                        input.value = '';
                        input.focus();
                        return;
                    }
                }
                // No existe o es el mismo - continuar
                nombreInput.focus();
            } catch(e) {
                nombreInput.focus();
            }
        } else {
            nombreInput.focus();
        }
    };
    
    xhr.onerror = function() { nombreInput.focus(); };
    xhr.ontimeout = function() { nombreInput.focus(); };
    xhr.send();
}

document.getElementById('modalProducto').addEventListener('hidden.bs.modal', function () {
    limpiarFormulario();
});

document.getElementById('modalProducto').addEventListener('shown.bs.modal', function () {
    const codigoInput = document.getElementById('productoCodigoBarras');
    codigoInput.focus();
    
    codigoInput.onkeydown = function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            e.stopPropagation();
            validarCodigo();
            return false;
        }
    };
});

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($reabrir_modal): ?>
    limpiarFormulario();
    const modal = new bootstrap.Modal(document.getElementById('modalProducto'));
    modal.show();
    <?php if (!empty($error)): ?>
    alert('<?php echo addslashes($error); ?>');
    <?php endif; ?>
    setTimeout(function() {
        document.getElementById('productoCodigoBarras').focus();
    }, 500);
    <?php endif; ?>
});

// ===== ESCÁNER DE CÓDIGO DE BARRAS CON CÁMARA PARA PRODUCTOS =====
let html5QrCode = null;
let scannerActivo = false;
let libreriaEscanerCargada = false;

function cargarLibreriaEscaner() {
    return new Promise((resolve, reject) => {
        if (typeof Html5Qrcode !== 'undefined') {
            resolve();
            return;
        }
        if (libreriaEscanerCargada) {
            setTimeout(() => {
                if (typeof Html5Qrcode !== 'undefined') resolve();
                else reject('No se pudo cargar');
            }, 1000);
            return;
        }
        libreriaEscanerCargada = true;
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js';
        script.onload = () => resolve();
        script.onerror = () => {
            // Intentar con unpkg como fallback
            const script2 = document.createElement('script');
            script2.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
            script2.onload = () => resolve();
            script2.onerror = () => reject('No se pudo cargar la librería');
            document.head.appendChild(script2);
        };
        document.head.appendChild(script);
    });
}

function abrirEscanerProducto() {
    if (!document.getElementById('modalEscanerProd')) {
        const modalHtml = `
        <div class="modal fade" id="modalEscanerProd" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="bi bi-camera me-2"></i>Escanear Código de Barras</h5>
                        <button type="button" class="btn-close btn-close-white" onclick="cerrarEscanerProd()"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div id="readerProd" style="width: 100%; min-height: 300px; background: #000;"></div>
                        <div id="scannerStatus" class="p-3 text-center">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-2 mb-0">Cargando escáner...</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="cerrarEscanerProd()">Cancelar</button>
                    </div>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }
    
    document.getElementById('scannerStatus').innerHTML = '<div class="spinner-border text-primary" role="status"></div><p class="mt-2 mb-0">Cargando escáner...</p>';
    
    const modal = new bootstrap.Modal(document.getElementById('modalEscanerProd'));
    modal.show();
    
    document.getElementById('modalEscanerProd').addEventListener('shown.bs.modal', function() {
        cargarLibreriaEscaner().then(() => {
            setTimeout(iniciarEscanerProd, 300);
        }).catch(err => {
            document.getElementById('scannerStatus').innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Error al cargar librería. Verifica tu conexión.</div>';
        });
    }, { once: true });
}

function iniciarEscanerProd() {
    if (scannerActivo) return;
    const statusDiv = document.getElementById('scannerStatus');
    statusDiv.innerHTML = '<div class="spinner-border text-primary" role="status"></div><p class="mt-2 mb-0">Solicitando acceso a cámara...</p>';
    
    try {
        html5QrCode = new Html5Qrcode("readerProd");
        scannerActivo = true;
        
        html5QrCode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 250, height: 100 }, aspectRatio: 1.0 },
            (decodedText) => {
                document.getElementById('productoCodigoBarras').value = decodedText;
                cerrarEscanerProd();
                validarCodigo();
                if ('vibrate' in navigator) navigator.vibrate(200);
            },
            () => {}
        ).then(() => {
            statusDiv.innerHTML = '<small class="text-success"><i class="bi bi-check-circle me-1"></i>Cámara activa - Apunta al código</small>';
        }).catch(err => {
            scannerActivo = false;
            if (err.name === 'NotAllowedError' || err.message.includes('Permission')) {
                statusDiv.innerHTML = '<div class="alert alert-warning mb-0"><i class="bi bi-camera-video me-2"></i>Toca "Permitir" cuando el navegador solicite acceso a la cámara</div>';
            } else {
                statusDiv.innerHTML = '<div class="alert alert-danger mb-0">Error: ' + (err.message || err) + '</div>';
            }
        });
    } catch(e) {
        statusDiv.innerHTML = '<div class="alert alert-danger mb-0">Error: ' + e.message + '</div>';
        scannerActivo = false;
    }
}

function cerrarEscanerProd() {
    if (html5QrCode && scannerActivo) {
        html5QrCode.stop().then(() => { html5QrCode.clear(); scannerActivo = false; }).catch(() => { scannerActivo = false; });
    }
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalEscanerProd'));
    if (modal) modal.hide();
}

document.addEventListener('hidden.bs.modal', function(e) {
    if (e.target.id === 'modalEscanerProd' && html5QrCode && scannerActivo) {
        html5QrCode.stop().catch(() => {});
        scannerActivo = false;
    }
});
</script>

<?php include 'includes/footer.php'; ?>
