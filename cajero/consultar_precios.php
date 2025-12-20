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
                <div class="col-md-8">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="buscar" id="buscarProducto" class="form-control" 
                               placeholder="Buscar por nombre, código o código de barras..." 
                               value="<?php echo htmlspecialchars($buscar); ?>"
                               autofocus>
                        <button class="btn btn-primary" type="button" onclick="abrirEscaner()" title="Escanear con cámara">
                            <i class="bi bi-camera"></i>
                        </button>
                    </div>
                    <small class="text-muted d-block mt-1">
                        <i class="bi bi-info-circle"></i> También puedes usar <strong>📷 la cámara</strong> para escanear
                    </small>
                </div>
                <div class="col-md-4">
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

// ===== ESCÁNER DE CÓDIGO DE BARRAS CON CÁMARA =====
let html5QrCode = null;
let scannerActivo = false;

function abrirEscaner() {
    if (!document.getElementById('modalEscaner')) {
        const modalHtml = `
        <div class="modal fade" id="modalEscaner" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="bi bi-camera me-2"></i>Escanear Código de Barras</h5>
                        <button type="button" class="btn-close btn-close-white" onclick="detenerEscaner()"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div id="reader" style="width: 100%; min-height: 300px; background: #000;"></div>
                        <div id="scannerStatus" class="p-3 text-center"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="detenerEscaner()">Cancelar</button>
                    </div>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }
    
    document.getElementById('scannerStatus').innerHTML = '<div class="spinner-border text-primary" role="status"></div><p class="mt-2 mb-0">Cargando...</p>';
    const modal = new bootstrap.Modal(document.getElementById('modalEscaner'));
    modal.show();
    solicitarPermisoCamara();
}

function solicitarPermisoCamara() {
    const statusDiv = document.getElementById('scannerStatus');
    statusDiv.innerHTML = '<div class="spinner-border text-primary" role="status"></div><p class="mt-2 mb-0">Verificando permisos...</p>';
    
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        statusDiv.innerHTML = `
            <div class="alert alert-danger mb-0">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Tu navegador no soporta acceso a la cámara.<br>
                <small>Usa Chrome, Firefox o Safari actualizados.</small>
            </div>`;
        return;
    }
    
    if (navigator.permissions && navigator.permissions.query) {
        navigator.permissions.query({ name: 'camera' }).then(result => {
            console.log('Estado del permiso:', result.state);
            if (result.state === 'denied') {
                mostrarInstruccionesDesbloqueo();
            } else {
                intentarAccesoCamara();
            }
        }).catch(() => intentarAccesoCamara());
    } else {
        intentarAccesoCamara();
    }
}

function mostrarInstruccionesDesbloqueo() {
    const statusDiv = document.getElementById('scannerStatus');
    statusDiv.innerHTML = `
        <div class="alert alert-danger mb-3">
            <i class="bi bi-lock me-2"></i>
            <strong>Cámara bloqueada</strong>
        </div>
        <p class="mb-3">Ya denegaste el permiso de cámara. Para habilitarlo:</p>
        <div class="card bg-light mb-3">
            <div class="card-body text-start">
                <p class="mb-2"><strong>En Chrome (Android):</strong></p>
                <ol class="mb-0 small">
                    <li>Toca el ícono <strong>🔒</strong> o <strong>ⓘ</strong> en la barra de direcciones</li>
                    <li>Toca <strong>"Permisos"</strong> o <strong>"Configuración del sitio"</strong></li>
                    <li>Busca <strong>"Cámara"</strong></li>
                    <li>Cámbialo a <strong>"Permitir"</strong></li>
                    <li>Vuelve aquí y toca el botón de abajo</li>
                </ol>
            </div>
        </div>
        <button class="btn btn-primary btn-lg" onclick="location.reload()">
            <i class="bi bi-arrow-clockwise me-2"></i>Recargar Página
        </button>`;
}

function intentarAccesoCamara() {
    const statusDiv = document.getElementById('scannerStatus');
    statusDiv.innerHTML = '<div class="spinner-border text-primary" role="status"></div><p class="mt-2 mb-0">Solicitando acceso a cámara...</p>';
    
    navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } })
        .then(stream => {
            console.log('Permiso de cámara concedido');
            window.cameraStream = stream;
            cargarLibreriaYEscanear();
        })
        .catch(err => {
            console.error('Error de cámara:', err.name, err.message);
            
            if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                mostrarInstruccionesDesbloqueo();
            } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                statusDiv.innerHTML = `<div class="alert alert-danger mb-0"><i class="bi bi-camera-video-off me-2"></i><strong>No se encontró ninguna cámara</strong></div>`;
            } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
                statusDiv.innerHTML = `<div class="alert alert-warning mb-3"><i class="bi bi-exclamation-triangle me-2"></i><strong>Cámara ocupada</strong><br><small>Cierra otras apps que usen la cámara</small></div><button class="btn btn-primary" onclick="solicitarPermisoCamara()"><i class="bi bi-arrow-clockwise me-2"></i>Reintentar</button>`;
            } else {
                statusDiv.innerHTML = `<div class="alert alert-danger mb-3"><strong>Error:</strong> ${err.message || err.name}</div><button class="btn btn-primary" onclick="solicitarPermisoCamara()"><i class="bi bi-arrow-clockwise me-2"></i>Reintentar</button>`;
            }
        });
}

function cargarLibreriaYEscanear() {
    const statusDiv = document.getElementById('scannerStatus');
    statusDiv.innerHTML = '<div class="spinner-border text-primary" role="status"></div><p class="mt-2 mb-0">Iniciando escáner...</p>';
    
    if (typeof Html5Qrcode === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js';
        script.onload = () => setTimeout(iniciarEscaner, 300);
        script.onerror = () => { statusDiv.innerHTML = '<div class="alert alert-danger mb-0">No se pudo cargar la librería</div>'; };
        document.head.appendChild(script);
    } else {
        setTimeout(iniciarEscaner, 300);
    }
}

function iniciarEscaner() {
    if (scannerActivo) return;
    const statusDiv = document.getElementById('scannerStatus');
    
    if (window.cameraStream) {
        window.cameraStream.getTracks().forEach(track => track.stop());
        window.cameraStream = null;
    }
    
    if (typeof Html5Qrcode === 'undefined') {
        statusDiv.innerHTML = '<div class="alert alert-danger mb-0">Error: Librería no disponible</div>';
        return;
    }
    
    try {
        html5QrCode = new Html5Qrcode("reader");
        scannerActivo = true;
        
        html5QrCode.start({ facingMode: "environment" }, { fps: 10, qrbox: { width: 250, height: 100 } },
            (decodedText) => {
                document.getElementById('buscarProducto').value = decodedText;
                detenerEscaner();
                document.querySelector('form').submit();
                if ('vibrate' in navigator) navigator.vibrate(200);
            },
            () => {}
        ).then(() => {
            statusDiv.innerHTML = '<small class="text-success"><i class="bi bi-check-circle me-1"></i>Apunta al código de barras</small>';
        }).catch(err => {
            console.error('Error al iniciar escáner:', err);
            scannerActivo = false;
            statusDiv.innerHTML = `
                <div class="alert alert-warning mb-3">Error al iniciar cámara<br><small>${err}</small></div>
                <button class="btn btn-primary" onclick="solicitarPermisoCamara()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Reintentar
                </button>`;
        });
    } catch(e) {
        console.error('Excepción en escáner:', e);
        statusDiv.innerHTML = '<div class="alert alert-danger mb-0">Error: ' + e.message + '</div>';
        scannerActivo = false;
    }
}

function detenerEscaner() {
    if (html5QrCode && scannerActivo) {
        html5QrCode.stop().then(() => { html5QrCode.clear(); scannerActivo = false; }).catch(() => { scannerActivo = false; });
    }
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalEscaner'));
    if (modal) modal.hide();
}

document.addEventListener('hidden.bs.modal', function(e) {
    if (e.target.id === 'modalEscaner' && html5QrCode && scannerActivo) {
        html5QrCode.stop().catch(() => {});
        scannerActivo = false;
    }
});
</script>

<?php include 'includes/footer.php'; ?>
