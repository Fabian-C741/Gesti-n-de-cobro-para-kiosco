<?php
require_once '../includes/error_handler.php';
require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();
require_once '../includes/functions.php';
require_once '../includes/session_validator.php';
require_once '../includes/tenant_check.php';

require_login();

// Verificar si el tenant está activo (sistema SaaS)
verificarTenantActivo();

// Verificar que sea cajero
if ($_SESSION['user_rol'] !== 'cajero') {
    header('Location: ../index.php');
    exit;
}

$page_title = 'Punto de Venta';
$db = Database::getInstance()->getConnection();
$pv_id = get_user_punto_venta_id();
$has_pv_column = column_exists($db, 'productos', 'punto_venta_id');

// Obtener productos solo si hay búsqueda (filtrado por punto de venta)
$buscar = trim($_GET['buscar'] ?? '');
$productos = [];
$error_busqueda = '';

if (!empty($buscar) && strlen($buscar) >= 3) {
    try {
        if ($pv_id && $has_pv_column) {
            $query = "SELECT p.*, c.nombre as categoria_nombre 
                      FROM productos p
                      LEFT JOIN categorias c ON p.categoria_id = c.id
                      WHERE p.activo = 1
                      AND (p.punto_venta_id = ? OR p.punto_venta_id IS NULL)
                      AND (p.nombre LIKE ? OR p.codigo LIKE ? OR p.codigo_barras LIKE ?)
                      ORDER BY p.nombre ASC 
                      LIMIT 50";
            
            $stmt = $db->prepare($query);
            $buscar_param = "%$buscar%";
            $stmt->execute([$pv_id, $buscar_param, $buscar_param, $buscar_param]);
        } else {
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
        }
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error_busqueda = 'Error al buscar productos: ' . $e->getMessage();
        error_log("Error en búsqueda cajero: " . $e->getMessage());
    }
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Panel de productos -->
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="position-relative">
                        <input type="text" id="buscarProducto" class="form-control form-control-lg" 
                               placeholder="🔍 Escribe 3 letras o escanea código de barras (búsqueda automática)..." 
                               value="<?php echo htmlspecialchars($buscar); ?>"
                               autofocus>
                        <small class="text-muted d-block mt-1">
                            <i class="bi bi-info-circle"></i> La búsqueda se activa automáticamente al escribir 3 o más caracteres
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="row g-3" id="productosGrid">
                <?php if (empty($buscar)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="bi bi-search me-2" style="font-size: 3rem;"></i>
                        <h5>Busca un producto para comenzar</h5>
                        <p class="mb-0">Escribe al menos 3 caracteres del nombre o código</p>
                        <p class="mb-0"><small>✨ También puedes escanear el código de barras directamente</small></p>
                    </div>
                </div>
                <?php elseif (empty($productos)): ?>
                <div class="col-12">
                    <div class="alert alert-warning" id="alertaNoEncontrado">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        No se encontraron productos con "<strong><?php echo htmlspecialchars($buscar); ?></strong>". Escaneando siguiente...
                    </div>
                </div>
                <script>
                    (function() {
                        var input = document.getElementById('buscarProducto');
                        if (input) {
                            input.value = '';
                            input.focus();
                        }
                        history.replaceState({}, '', 'index.php');
                        setTimeout(function() {
                            var alerta = document.getElementById('alertaNoEncontrado');
                            if (alerta) alerta.style.display = 'none';
                        }, 2000);
                    })();
                </script>
                <?php else: ?>
                <?php foreach ($productos as $producto): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card producto-item h-100" data-producto='<?php echo htmlspecialchars(json_encode($producto), ENT_QUOTES); ?>' style="cursor: pointer;">
                        <?php if ($producto['imagen']): ?>
                        <img src="../uploads/<?php echo htmlspecialchars($producto['imagen']); ?>" 
                             class="card-img-top" style="height: 120px; object-fit: cover;" alt="Producto">
                        <?php else: ?>
                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                             style="height: 120px;">
                            <i class="bi bi-image text-muted" style="font-size: 2rem;"></i>
                        </div>
                        <?php endif; ?>
                        
                        <div class="card-body p-2">
                            <h6 class="card-title mb-1"><?php echo htmlspecialchars($producto['nombre']); ?></h6>
                            <?php if ($producto['categoria_nombre']): ?>
                            <small class="badge bg-secondary mb-1"><?php echo htmlspecialchars($producto['categoria_nombre']); ?></small>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between align-items-center">
                                <strong class="text-primary">$<?php echo number_format($producto['precio_venta'], 2); ?></strong>
                                <small class="text-muted">Stock: <?php echo $producto['stock']; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Carrito de compra -->
        <div class="col-lg-5">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-cart3 me-2"></i>
                        Carrito de Venta
                    </h5>
                </div>
                <div class="card-body" style="max-height: 50vh; overflow-y: auto;">
                    <div id="carritoItems">
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-cart-x fs-1"></i>
                            <p>Carrito vacío</p>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mb-0">TOTAL:</h4>
                        <h3 class="mb-0 total-display" id="totalDisplay">$0.00</h3>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Método de Pago</label>
                        <select id="metodoPago" class="form-select">
                            <option value="efectivo">Efectivo</option>
                            <option value="tarjeta">Tarjeta</option>
                            <option value="transferencia">Transferencia</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="montoEfectivo" style="display: none;">
                        <label class="form-label">Monto Recibido</label>
                        <input type="number" id="montoPagado" class="form-control" step="0.01" min="0" 
                               oninput="calcularCambio()">
                        <small class="text-muted">Cambio: <strong id="cambioDisplay">$0.00</strong></small>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button class="btn btn-success btn-lg" onclick="procesarVenta()" id="btnProcesar" disabled>
                            <i class="bi bi-check-circle me-2"></i>
                            Procesar Venta
                        </button>
                        <button class="btn btn-outline-danger" onclick="limpiarCarrito()">
                            <i class="bi bi-trash me-2"></i>
                            Limpiar Carrito
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Cargar carrito desde localStorage o inicializar vacío
let carrito = JSON.parse(localStorage.getItem('carrito_cajero')) || [];
let timeoutBusqueda = null;

// Actualizar carrito en localStorage
function guardarCarrito() {
    localStorage.setItem('carrito_cajero', JSON.stringify(carrito));
}

// Cargar carrito al iniciar
document.addEventListener('DOMContentLoaded', function() {
    actualizarCarrito();
    
    // Auto-agregar producto si solo hay uno (para escaneo de código de barras)
    const productosCards = document.querySelectorAll('.producto-item[data-producto]');
    if (productosCards.length === 1) {
        try {
            const producto = JSON.parse(productosCards[0].dataset.producto);
            agregarAlCarrito(producto);
        } catch (error) {
            console.error('Error al auto-agregar producto:', error);
        }
    }
    
    // Event listener para clicks en productos usando delegation (cuando hay múltiples resultados)
    const productosGrid = document.getElementById('productosGrid');
    if (productosGrid) {
        productosGrid.addEventListener('click', function(e) {
            const productoCard = e.target.closest('.producto-item');
            if (productoCard && productoCard.dataset.producto) {
                try {
                    const producto = JSON.parse(productoCard.dataset.producto);
                    agregarAlCarrito(producto);
                } catch (error) {
                    console.error('Error al agregar producto:', error);
                }
            }
        });
    }
});

// Buscar productos con debounce (espera 500ms después de escribir)
document.getElementById('buscarProducto').addEventListener('input', function(e) {
    const valor = e.target.value.trim();
    
    // Cancelar búsqueda anterior si existe
    if (timeoutBusqueda) {
        clearTimeout(timeoutBusqueda);
    }
    
    // Si tiene 3 o más caracteres, buscar automáticamente
    if (valor.length >= 3) {
        timeoutBusqueda = setTimeout(() => {
            buscarProductos();
        }, 500); // Espera 500ms después de que el usuario deja de escribir
    }
});

// También permitir buscar con Enter (para escáner de códigos)
document.getElementById('buscarProducto').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        if (timeoutBusqueda) {
            clearTimeout(timeoutBusqueda);
        }
        buscarProductos();
    }
});

function buscarProductos() {
    const buscar = document.getElementById('buscarProducto').value.trim();
    if (buscar.length >= 3) {
        window.location.href = `index.php?buscar=${encodeURIComponent(buscar)}`;
    } else if (buscar.length === 0) {
        // Si se borra todo el texto, recargar sin búsqueda
        window.location.href = 'index.php';
    }
}

// Método de pago
document.getElementById('metodoPago').addEventListener('change', function() {
    const montoEfectivo = document.getElementById('montoEfectivo');
    if (this.value === 'efectivo') {
        montoEfectivo.style.display = 'block';
    } else {
        montoEfectivo.style.display = 'none';
    }
});

// Agregar al carrito
function agregarAlCarrito(producto) {
    const index = carrito.findIndex(item => item.id === producto.id);
    
    if (index >= 0) {
        if (carrito[index].cantidad < producto.stock) {
            carrito[index].cantidad++;
        } else {
            alert('Stock insuficiente');
            return;
        }
    } else {
        carrito.push({
            ...producto,
            cantidad: 1
        });
    }
    
    guardarCarrito();
    actualizarCarrito();
    
    // Limpiar el campo de búsqueda sin recargar
    const searchInput = document.getElementById('buscarProducto');
    if (searchInput) {
        searchInput.value = '';
        searchInput.focus();
    }
    
    // Limpiar URL sin recargar la página
    const url = new URL(window.location);
    if (url.searchParams.has('buscar')) {
        url.searchParams.delete('buscar');
        window.history.replaceState({}, '', url);
        
        // Ocultar resultados de búsqueda y mostrar mensaje inicial
        document.getElementById('productosGrid').innerHTML = `
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="bi bi-search me-2" style="font-size: 3rem;"></i>
                    <h5>Busca un producto para continuar</h5>
                    <p class="mb-0">Escribe al menos 3 caracteres del nombre o código</p>
                    <p class="mb-0"><small>✨ También puedes escanear el código de barras directamente</small></p>
                </div>
            </div>
        `;
    }
}

// Actualizar carrito
function actualizarCarrito() {
    const container = document.getElementById('carritoItems');
    const btnProcesar = document.getElementById('btnProcesar');
    
    if (carrito.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="bi bi-cart-x fs-1"></i>
                <p>Carrito vacío</p>
            </div>
        `;
        btnProcesar.disabled = true;
    } else {
        let html = '<div class="list-group list-group-flush">';
        
        carrito.forEach((item, index) => {
            const subtotal = item.precio_venta * item.cantidad;
            html += `
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="flex-grow-1">
                            <h6 class="mb-0">${item.nombre}</h6>
                            <small class="text-muted">$${parseFloat(item.precio_venta).toFixed(2)} c/u</small>
                        </div>
                        <button class="btn btn-sm btn-outline-danger" onclick="eliminarDelCarrito(${index})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary" onclick="cambiarCantidad(${index}, -1)">
                                <i class="bi bi-dash"></i>
                            </button>
                            <span class="btn btn-outline-secondary disabled">${item.cantidad}</span>
                            <button class="btn btn-outline-secondary" onclick="cambiarCantidad(${index}, 1)">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                        <strong>$${subtotal.toFixed(2)}</strong>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
        btnProcesar.disabled = false;
    }
    
    actualizarTotal();
}

// Cambiar cantidad
function cambiarCantidad(index, delta) {
    const item = carrito[index];
    const nuevaCantidad = item.cantidad + delta;
    
    if (nuevaCantidad <= 0) {
        eliminarDelCarrito(index);
    } else if (nuevaCantidad <= item.stock) {
        carrito[index].cantidad = nuevaCantidad;
        guardarCarrito();
        actualizarCarrito();
    } else {
        alert('Stock insuficiente');
    }
}

// Eliminar del carrito
function eliminarDelCarrito(index) {
    carrito.splice(index, 1);
    guardarCarrito();
    actualizarCarrito();
}

// Actualizar total
function actualizarTotal() {
    const total = carrito.reduce((sum, item) => sum + (item.precio_venta * item.cantidad), 0);
    document.getElementById('totalDisplay').textContent = '$' + total.toFixed(2);
    calcularCambio();
}

// Calcular cambio
function calcularCambio() {
    const total = carrito.reduce((sum, item) => sum + (item.precio_venta * item.cantidad), 0);
    const montoPagado = parseFloat(document.getElementById('montoPagado').value) || 0;
    const cambio = montoPagado - total;
    
    document.getElementById('cambioDisplay').textContent = '$' + Math.max(0, cambio).toFixed(2);
}

// Limpiar carrito
function limpiarCarrito() {
    if (carrito.length > 0 && confirm('¿Desea limpiar el carrito?')) {
        carrito = [];
        guardarCarrito();
        actualizarCarrito();
    }
}

// Procesar venta
function procesarVenta() {
    if (carrito.length === 0) {
        alert('El carrito está vacío');
        return;
    }
    
    const metodoPago = document.getElementById('metodoPago').value;
    const total = carrito.reduce((sum, item) => sum + (item.precio_venta * item.cantidad), 0);
    let montoPagado = total;
    
    if (metodoPago === 'efectivo') {
        montoPagado = parseFloat(document.getElementById('montoPagado').value) || 0;
        if (montoPagado < total) {
            alert('El monto recibido es insuficiente');
            return;
        }
    }
    
    const btnProcesar = document.getElementById('btnProcesar');
    btnProcesar.disabled = true;
    btnProcesar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
    
    // Enviar al servidor
    fetch('../api/procesar_venta.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            items: carrito,
            metodo_pago: metodoPago,
            monto_pagado: montoPagado
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Limpiar carrito
            carrito = [];
            guardarCarrito(); // Limpiar localStorage
            actualizarCarrito();
            document.getElementById('montoPagado').value = '';
            
            // Mostrar modal de éxito con opción de imprimir
            mostrarModalExito(data.venta_id, data.numero_venta, data.total, data.cambio);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error de conexión');
        console.error(error);
    })
    .finally(() => {
        btnProcesar.disabled = false;
        btnProcesar.innerHTML = '<i class="bi bi-check-circle me-2"></i>Procesar Venta';
    });
}

// Mostrar modal de éxito
function mostrarModalExito(ventaId, numeroVenta, total, cambio) {
    // Obtener datos del ticket via API
    fetch(`../api/obtener_ticket.php?id=${ventaId}`)
        .then(response => response.json())
        .then(ticket => {
            const modal = `
                <div class="modal fade" id="modalTicket" tabindex="-1" data-bs-backdrop="static">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>¡Venta Exitosa!</h5>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div id="ticket-preview" style="background: white; padding: 20px; border: 1px solid #ddd; max-height: 600px; overflow-y: auto;">
                                            ${generarTicketHTML(ticket, numeroVenta, total, cambio)}
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="alert alert-success">
                                            <h5><i class="bi bi-check-circle me-2"></i>Venta Completada</h5>
                                            <hr>
                                            <p class="mb-1"><strong>N° Venta:</strong><br>${numeroVenta}</p>
                                            <p class="mb-1"><strong>Total:</strong><br><span class="fs-4">$${total.toFixed(2)}</span></p>
                                            ${cambio > 0 ? `<p class="mb-0"><strong>Cambio:</strong><br><span class="fs-4 text-success">$${cambio.toFixed(2)}</span></p>` : ''}
                                        </div>
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-primary btn-lg" onclick="imprimirTicket()">
                                                <i class="bi bi-printer me-2"></i>Imprimir Ticket
                                            </button>
                                            <button class="btn btn-secondary" onclick="cerrarModalTicket()">
                                                <i class="bi bi-x-circle me-2"></i>Cerrar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modal);
            const modalElement = new bootstrap.Modal(document.getElementById('modalTicket'));
            modalElement.show();
        })
        .catch(error => {
            console.error('Error al obtener ticket:', error);
            alert('Venta procesada correctamente pero hubo un error al cargar el ticket');
        });
}

function generarTicketHTML(ticket, numeroVenta, total, cambio) {
    const fecha = new Date().toLocaleString('es-AR');
    let html = `
        <div style="font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.4;">
            <div style="text-align: center; border-bottom: 2px dashed #000; padding-bottom: 10px; margin-bottom: 10px;">
                <h3 style="margin: 0; font-size: 16px;">TICKET DE VENTA</h3>
                <p style="margin: 5px 0;">${ticket.empresa || 'KIOSCO'}</p>
                ${ticket.direccion ? `<p style="margin: 2px 0; font-size: 10px;">${ticket.direccion}</p>` : ''}
                ${ticket.telefono ? `<p style="margin: 2px 0; font-size: 10px;">Tel: ${ticket.telefono}</p>` : ''}
            </div>
            
            <div style="margin: 10px 0; font-size: 11px;">
                <p style="margin: 2px 0;"><strong>Fecha:</strong> ${fecha}</p>
                <p style="margin: 2px 0;"><strong>N° Venta:</strong> ${numeroVenta}</p>
                <p style="margin: 2px 0;"><strong>Vendedor:</strong> ${ticket.vendedor || 'Cajero'}</p>
            </div>
            
            <div style="border-top: 2px dashed #000; border-bottom: 2px dashed #000; padding: 10px 0; margin: 10px 0;">
                <table style="width: 100%; font-size: 11px;">
                    <thead>
                        <tr style="border-bottom: 1px solid #000;">
                            <th style="text-align: left; padding: 5px 0;">PRODUCTO</th>
                            <th style="text-align: center;">CANT</th>
                            <th style="text-align: right;">P.UNIT</th>
                            <th style="text-align: right;">SUBTOTAL</th>
                        </tr>
                    </thead>
                    <tbody>`;
    
    ticket.items.forEach(item => {
        html += `
                        <tr>
                            <td style="padding: 3px 0;">${item.nombre}</td>
                            <td style="text-align: center;">${item.cantidad}</td>
                            <td style="text-align: right;">$${parseFloat(item.precio_unitario).toFixed(2)}</td>
                            <td style="text-align: right;">$${parseFloat(item.subtotal).toFixed(2)}</td>
                        </tr>`;
    });
    
    html += `
                    </tbody>
                </table>
            </div>
            
            <div style="text-align: right; font-size: 12px; margin: 10px 0;">
                <p style="margin: 5px 0;"><strong>SUBTOTAL: $${total.toFixed(2)}</strong></p>
                <p style="margin: 5px 0; font-size: 16px;"><strong>TOTAL: $${total.toFixed(2)}</strong></p>
                ${cambio > 0 ? `
                <p style="margin: 5px 0;">Efectivo: $${(total + cambio).toFixed(2)}</p>
                <p style="margin: 5px 0;">Cambio: $${cambio.toFixed(2)}</p>
                ` : ''}
            </div>
            
            <div style="text-align: center; border-top: 2px dashed #000; padding-top: 10px; margin-top: 10px; font-size: 10px;">
                <p>¡GRACIAS POR SU COMPRA!</p>
                ${ticket.mensaje ? `<p style="margin-top: 5px;">${ticket.mensaje}</p>` : ''}
            </div>
        </div>
    `;
    
    return html;
}

function imprimirTicket() {
    const ticketContent = document.getElementById('ticket-preview').innerHTML;
    
    // Crear iframe oculto para imprimir
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    document.body.appendChild(iframe);
    
    const doc = iframe.contentWindow.document;
    doc.open();
    doc.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Ticket</title>
            <style>
                @media print {
                    @page { 
                        margin: 0; 
                        size: 80mm auto;
                    }
                    body { margin: 10mm; }
                }
                body {
                    font-family: 'Courier New', monospace;
                }
            </style>
        </head>
        <body>
            ${ticketContent}
        </body>
        </html>
    `);
    doc.close();
    
    // Esperar a que cargue e imprimir
    iframe.contentWindow.focus();
    setTimeout(() => {
        iframe.contentWindow.print();
        setTimeout(() => {
            document.body.removeChild(iframe);
        }, 100);
    }, 250);
}

function cerrarModalTicket() {
    const modalElement = document.getElementById('modalTicket');
    if (modalElement) {
        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) modal.hide();
        modalElement.remove();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
