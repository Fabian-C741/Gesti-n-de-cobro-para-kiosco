<?php
require_once '../includes/error_handler.php';
require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();
require_once '../includes/functions.php';
require_once '../includes/session_validator.php';

require_login();

// Verificar que sea cajero
if ($_SESSION['user_rol'] !== 'cajero') {
    header('Location: ../index.php');
    exit;
}

$page_title = 'Consultar Precios';
$db = Database::getInstance()->getConnection();

// Búsqueda de productos
$buscar = $_GET['buscar'] ?? '';
$productos = [];

if (!empty($buscar)) {
    $query = "SELECT p.*, c.nombre as categoria_nombre 
              FROM productos p
              LEFT JOIN categorias c ON p.categoria_id = c.id
              WHERE p.activo = 1
              AND (p.nombre LIKE ? OR p.codigo LIKE ? OR p.codigo_barras LIKE ?)
              ORDER BY p.nombre ASC 
              LIMIT 50";
    
    $stmt = $db->prepare($query);
    $buscar_param = "%$buscar%";
    $stmt->execute([$buscar_param, $buscar_param, $buscar_param]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-search me-2"></i><?php echo $page_title; ?></h2>
    </div>

    <!-- Buscador -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-10">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="buscar" class="form-control" 
                               placeholder="Buscar por nombre, código o código de barras..." 
                               value="<?php echo htmlspecialchars($buscar); ?>"
                               autofocus>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-search me-2"></i>Buscar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Resultados -->
    <?php if (empty($buscar)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-upc-scan text-muted mb-3" style="font-size: 5rem;"></i>
            <h4 class="text-muted">Busca un producto para consultar su precio</h4>
            <p class="text-muted">Puedes buscar por nombre, código o escanear el código de barras</p>
        </div>
    </div>
    <?php elseif (empty($productos)): ?>
    <div class="alert alert-warning" id="alertaNoEncontrado">
        <i class="bi bi-exclamation-triangle me-2"></i>
        No se encontraron productos con "<strong><?php echo htmlspecialchars($buscar); ?></strong>"
    </div>
    <script>
        (function() {
            var input = document.querySelector('input[name="buscar"]');
            if (input) {
                input.value = '';
                input.focus();
            }
            history.replaceState({}, '', 'consultar_precios.php');
            setTimeout(function() {
                var alerta = document.getElementById('alertaNoEncontrado');
                if (alerta) alerta.style.display = 'none';
            }, 2000);
        })();
    </script>
    <?php else: ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-list-check me-2"></i>
                Resultados de búsqueda (<?php echo count($productos); ?>)
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Imagen</th>
                            <th>Producto</th>
                            <th>Código</th>
                            <th>Código Barras</th>
                            <th>Categoría</th>
                            <th class="text-end">Precio</th>
                            <th class="text-center">Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $producto): ?>
                        <tr>
                            <td>
                                <?php if ($producto['imagen']): ?>
                                <img src="../uploads/<?php echo htmlspecialchars($producto['imagen']); ?>" 
                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;" 
                                     alt="Producto">
                                <?php else: ?>
                                <div class="bg-light d-flex align-items-center justify-content-center" 
                                     style="width: 50px; height: 50px; border-radius: 5px;">
                                    <i class="bi bi-image text-muted"></i>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                <?php if ($producto['descripcion']): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($producto['descripcion'], 0, 60)); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo htmlspecialchars($producto['codigo']); ?></code></td>
                            <td>
                                <?php if ($producto['codigo_barras']): ?>
                                <code><?php echo htmlspecialchars($producto['codigo_barras']); ?></code>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($producto['categoria_nombre']): ?>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($producto['categoria_nombre']); ?></span>
                                <?php else: ?>
                                <span class="text-muted">Sin categoría</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <h5 class="mb-0 text-success">$<?php echo number_format($producto['precio_venta'], 2); ?></h5>
                            </td>
                            <td class="text-center">
                                <?php if ($producto['stock'] > 0): ?>
                                <span class="badge bg-success"><?php echo $producto['stock']; ?></span>
                                <?php else: ?>
                                <span class="badge bg-danger">Agotado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Limpiar búsqueda después de mostrar resultado
<?php if (!empty($productos) && count($productos) > 0): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Limpiar el campo de búsqueda después de mostrar los resultados
    const searchInput = document.querySelector('input[name="buscar"]');
    if (searchInput) {
        searchInput.value = '';
        searchInput.focus();
    }
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
