<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();
require_once '../includes/functions.php';

require_login();

// BLOQUEAR acceso a cajeros - Solo vendedores y admins pueden gestionar categorías
if ($_SESSION['user_rol'] === 'cajero') {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$usuario_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'crear') {
            $nombre = sanitize_input($_POST['nombre']);
            $descripcion = sanitize_input($_POST['descripcion'] ?? '');
            
            if (empty($nombre)) {
                $error = 'El nombre de la categoría es obligatorio';
            } else {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO categorias (nombre, descripcion, usuario_id)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$nombre, $descripcion, $usuario_id]);
                    
                    log_activity($db, $usuario_id, 'crear_categoria', "Categoría: $nombre");
                    $success = 'Categoría creada exitosamente';
                } catch (PDOException $e) {
                    $error = 'Error al crear la categoría';
                }
            }
        } elseif ($action === 'editar') {
            $id = intval($_POST['id']);
            $nombre = sanitize_input($_POST['nombre']);
            $descripcion = sanitize_input($_POST['descripcion'] ?? '');
            
            if (empty($nombre)) {
                $error = 'El nombre de la categoría es obligatorio';
            } else {
                try {
                    // Verificar que la categoría pertenece al usuario o el usuario es admin
                    $stmt = $db->prepare("SELECT usuario_id FROM categorias WHERE id = ?");
                    $stmt->execute([$id]);
                    $categoria = $stmt->fetch();
                    
                    if (!$categoria) {
                        $error = 'Categoría no encontrada';
                    } elseif ($categoria['usuario_id'] != $usuario_id && $_SESSION['user_rol'] !== 'admin') {
                        $error = 'No tienes permisos para editar esta categoría';
                    } else {
                        $stmt = $db->prepare("
                            UPDATE categorias 
                            SET nombre = ?, descripcion = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$nombre, $descripcion, $id]);
                        
                        log_activity($db, $usuario_id, 'editar_categoria', "Categoría ID: $id");
                        $success = 'Categoría actualizada exitosamente';
                    }
                } catch (PDOException $e) {
                    $error = 'Error al actualizar la categoría';
                }
            }
        } elseif ($action === 'eliminar') {
            $id = intval($_POST['id']);
            
            try {
                // Verificar que la categoría pertenece al usuario o el usuario es admin
                $stmt = $db->prepare("SELECT usuario_id FROM categorias WHERE id = ?");
                $stmt->execute([$id]);
                $categoria = $stmt->fetch();
                
                if (!$categoria) {
                    $error = 'Categoría no encontrada';
                } elseif ($categoria['usuario_id'] != $usuario_id && $_SESSION['user_rol'] !== 'admin') {
                    $error = 'No tienes permisos para eliminar esta categoría';
                } else {
                    // Verificar si hay productos asociados
                    $stmt = $db->prepare("SELECT COUNT(*) as total FROM productos WHERE categoria_id = ? AND activo = 1");
                    $stmt->execute([$id]);
                    $count = $stmt->fetch()['total'];
                    
                    if ($count > 0) {
                        $error = "No se puede eliminar. Hay $count producto(s) asociado(s) a esta categoría";
                    } else {
                        $stmt = $db->prepare("UPDATE categorias SET activo = 0 WHERE id = ?");
                        $stmt->execute([$id]);
                        
                        log_activity($db, $usuario_id, 'eliminar_categoria', "Categoría ID: $id");
                        $success = 'Categoría eliminada exitosamente';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Error al eliminar la categoría';
            }
        } elseif ($action === 'activar') {
            $id = intval($_POST['id']);
            
            try {
                // Verificar que la categoría pertenece al usuario o el usuario es admin
                $stmt = $db->prepare("SELECT usuario_id FROM categorias WHERE id = ?");
                $stmt->execute([$id]);
                $categoria = $stmt->fetch();
                
                if (!$categoria) {
                    $error = 'Categoría no encontrada';
                } elseif ($categoria['usuario_id'] != $usuario_id && $_SESSION['user_rol'] !== 'admin') {
                    $error = 'No tienes permisos para activar esta categoría';
                } else {
                    $stmt = $db->prepare("UPDATE categorias SET activo = 1 WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    log_activity($db, $usuario_id, 'activar_categoria', "Categoría ID: $id");
                    $success = 'Categoría activada exitosamente';
                }
            } catch (PDOException $e) {
                $error = 'Error al activar la categoría';
            }
        }
    }
}

// Obtener categorías del usuario (o todas si es admin)
$search = $_GET['search'] ?? '';

if ($_SESSION['user_rol'] === 'admin') {
    // Admin ve todas las categorías
    $query = "
        SELECT c.*, 
               COUNT(DISTINCT p.id) as total_productos,
               u.nombre as creador_nombre
        FROM categorias c
        LEFT JOIN productos p ON c.id = p.categoria_id AND p.activo = 1
        LEFT JOIN usuarios u ON c.usuario_id = u.id
        WHERE 1=1
    ";
} else {
    // Vendedor ve sus propias categorías
    $query = "
        SELECT c.*, 
               COUNT(DISTINCT p.id) as total_productos,
               u.nombre as creador_nombre
        FROM categorias c
        LEFT JOIN productos p ON c.id = p.categoria_id AND p.activo = 1
        LEFT JOIN usuarios u ON c.usuario_id = u.id
        WHERE c.usuario_id = ?
    ";
}

$params = [];

if ($_SESSION['user_rol'] !== 'admin') {
    $params[] = $usuario_id;
}

if (!empty($search)) {
    $query .= " AND (c.nombre LIKE ? OR c.descripcion LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " GROUP BY c.id ORDER BY c.nombre ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$categorias = $stmt->fetchAll();

$page_title = 'Categorías';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-tags"></i> Mis Categorías</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCategoria">
            <i class="bi bi-plus-circle"></i> Nueva Categoría
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

    <!-- Buscador -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-10">
                    <input type="text" name="search" class="form-control" placeholder="Buscar por nombre o descripción..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de categorías -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Productos</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categorias)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    No tienes categorías registradas. ¡Crea tu primera categoría!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categorias as $categoria): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($categoria['nombre']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($categoria['descripcion'] ?: '-'); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo $categoria['total_productos']; ?> productos
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $categoria['activo'] ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $categoria['activo'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="editarCategoria(<?php echo htmlspecialchars(json_encode($categoria)); ?>)" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($categoria['activo']): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar esta categoría?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="eliminar">
                                                <input type="hidden" name="id" value="<?php echo $categoria['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Reactivar esta categoría?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="activar">
                                                <input type="hidden" name="id" value="<?php echo $categoria['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success" title="Activar">
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

<!-- Modal Categoría -->
<div class="modal fade" id="modalCategoria" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="formCategoria">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCategoriaTitle">Nueva Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="crear" id="categoriaAction">
                    <input type="hidden" name="id" id="categoriaId">
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nombre" id="categoriaNombre" required maxlength="100">
                        <div class="form-text">Nombre descriptivo para la categoría</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" id="categoriaDescripcion" rows="3" maxlength="255" placeholder="Descripción opcional de la categoría..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editarCategoria(categoria) {
    document.getElementById('modalCategoriaTitle').textContent = 'Editar Categoría';
    document.getElementById('categoriaAction').value = 'editar';
    document.getElementById('categoriaId').value = categoria.id;
    document.getElementById('categoriaNombre').value = categoria.nombre;
    document.getElementById('categoriaDescripcion').value = categoria.descripcion || '';
    
    new bootstrap.Modal(document.getElementById('modalCategoria')).show();
}

// Resetear modal al cerrarlo
document.getElementById('modalCategoria').addEventListener('hidden.bs.modal', function () {
    document.getElementById('formCategoria').reset();
    document.getElementById('modalCategoriaTitle').textContent = 'Nueva Categoría';
    document.getElementById('categoriaAction').value = 'crear';
    document.getElementById('categoriaId').value = '';
});
</script>

<?php include 'includes/footer.php'; ?>
