// Main JavaScript

// Confirmación antes de eliminar
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        if (!alert.classList.contains('alert-dismissible')) {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        }
    });
});

// Formatear precio en inputs
function formatPriceInput(input) {
    let value = input.value.replace(/[^0-9.]/g, '');
    if (value) {
        value = parseFloat(value).toFixed(2);
        input.value = value;
    }
}

// Validar stock
function validateStock(input) {
    let value = parseInt(input.value);
    if (isNaN(value) || value < 0) {
        input.value = 0;
    }
}

// Preview de imagen antes de subir
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById(previewId);
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Buscar en tabla
function searchTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    
    if (!input || !table) return;
    
    const filter = input.value.toUpperCase();
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        const cells = rows[i].getElementsByTagName('td');
        let found = false;
        
        for (let j = 0; j < cells.length; j++) {
            const cell = cells[j];
            if (cell) {
                const textValue = cell.textContent || cell.innerText;
                if (textValue.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
        }
        
        rows[i].style.display = found ? '' : 'none';
    }
}

// Calcular total de venta
function calcularTotal() {
    let subtotal = 0;
    const filas = document.querySelectorAll('.fila-producto');
    
    filas.forEach(fila => {
        const cantidad = parseFloat(fila.querySelector('.cantidad-input')?.value || 0);
        const precio = parseFloat(fila.querySelector('.precio-input')?.value || 0);
        const total = cantidad * precio;
        
        const totalCell = fila.querySelector('.total-cell');
        if (totalCell) {
            totalCell.textContent = '$' + total.toFixed(2);
        }
        
        subtotal += total;
    });
    
    const descuentoInput = document.getElementById('descuento');
    const descuento = parseFloat(descuentoInput?.value || 0);
    
    const total = subtotal - descuento;
    
    const subtotalEl = document.getElementById('subtotal');
    const totalEl = document.getElementById('total');
    
    if (subtotalEl) subtotalEl.textContent = '$' + subtotal.toFixed(2);
    if (totalEl) totalEl.textContent = '$' + total.toFixed(2);
}

// Confirmar acción
function confirmar(mensaje) {
    return confirm(mensaje);
}

// Imprimir
function imprimir() {
    window.print();
}
