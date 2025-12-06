<?php
require_once '../includes/error_handler.php';
require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();
require_once '../includes/functions.php';
require_once '../includes/session_validator.php';

require_login();

// Obtener ID de la venta
$venta_id = (int)($_GET['id'] ?? 0);

if (!$venta_id) {
    die('ID de venta no especificado');
}

$db = Database::getInstance()->getConnection();

// Obtener datos de la venta
$stmt = $db->prepare("
    SELECT v.*, u.nombre as vendedor_nombre, u.punto_venta, u.sucursal
    FROM ventas v
    LEFT JOIN usuarios u ON v.usuario_id = u.id
    WHERE v.id = ? AND v.usuario_id = ?
");
$stmt->execute([$venta_id, $_SESSION['user_id']]);
$venta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$venta) {
    die('Venta no encontrada');
}

// Obtener items de la venta
$stmt = $db->prepare("
    SELECT dv.*, p.nombre as producto_nombre, p.codigo_barras
    FROM venta_detalle dv
    JOIN productos p ON dv.producto_id = p.id
    WHERE dv.venta_id = ?
    ORDER BY dv.id
");
$stmt->execute([$venta_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener configuración de la empresa
$stmt = $db->prepare("SELECT clave, valor FROM configuracion_sistema WHERE clave IN (
    'nombre_empresa', 'direccion_empresa', 'telefono_empresa', 'email_empresa', 
    'cuit_empresa', 'mensaje_ticket', 'formato_ticket'
)");
$stmt->execute();
$config_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$config = [];
foreach ($config_rows as $row) {
    $config[$row['clave']] = $row['valor'];
}

$ancho = ($config['formato_ticket'] ?? '80mm') === '58mm' ? '58mm' : '80mm';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo $venta['id']; ?></title>
    <style>
        @page {
            size: <?php echo $ancho; ?> auto;
            margin: 0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: <?php echo $ancho === '58mm' ? '10px' : '12px'; ?>;
            line-height: 1.3;
            padding: 5mm;
            width: <?php echo $ancho; ?>;
            margin: 0 auto;
        }
        
        .ticket {
            width: 100%;
        }
        
        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
        }
        
        .empresa-nombre {
            font-weight: bold;
            font-size: <?php echo $ancho === '58mm' ? '12px' : '14px'; ?>;
            margin-bottom: 3px;
        }
        
        .empresa-info {
            font-size: <?php echo $ancho === '58mm' ? '9px' : '10px'; ?>;
            line-height: 1.4;
        }
        
        .info-venta {
            margin: 10px 0;
            font-size: <?php echo $ancho === '58mm' ? '9px' : '10px'; ?>;
        }
        
        .info-venta .row {
            display: flex;
            justify-content: space-between;
            margin: 2px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        
        th {
            text-align: left;
            border-bottom: 1px solid #000;
            padding: 3px 0;
            font-weight: bold;
        }
        
        td {
            padding: 3px 0;
            vertical-align: top;
        }
        
        .col-cant {
            width: 15%;
            text-align: center;
        }
        
        .col-desc {
            width: 45%;
        }
        
        .col-precio {
            width: 20%;
            text-align: right;
        }
        
        .col-total {
            width: 20%;
            text-align: right;
        }
        
        .totales {
            margin-top: 10px;
            border-top: 1px solid #000;
            padding-top: 5px;
        }
        
        .totales .row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
        }
        
        .total-final {
            font-weight: bold;
            font-size: <?php echo $ancho === '58mm' ? '12px' : '14px'; ?>;
            border-top: 1px dashed #000;
            margin-top: 5px;
            padding-top: 5px;
        }
        
        .footer {
            text-align: center;
            margin-top: 15px;
            border-top: 1px dashed #000;
            padding-top: 8px;
            font-size: <?php echo $ancho === '58mm' ? '9px' : '10px'; ?>;
        }
        
        .barcode {
            text-align: center;
            margin: 10px 0;
            font-family: 'Libre Barcode 39', cursive;
            font-size: 32px;
            letter-spacing: 2px;
        }
        
        @media print {
            body {
                width: <?php echo $ancho; ?>;
            }
            
            .no-print {
                display: none;
            }
        }
        
        .btn-print {
            position: fixed;
            top: 10px;
            right: 10px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .btn-print:hover {
            background: #0056b3;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39&display=swap" rel="stylesheet">
</head>
<body>
    <button class="btn-print no-print" onclick="window.print()">🖨️ Imprimir</button>
    
    <div class="ticket">
        <!-- HEADER -->
        <div class="header">
            <div class="empresa-nombre"><?php echo strtoupper(htmlspecialchars($config['nombre_empresa'] ?? 'MI EMPRESA')); ?></div>
            <div class="empresa-info">
                <?php if (!empty($config['direccion_empresa'])): ?>
                    <?php echo htmlspecialchars($config['direccion_empresa']); ?><br>
                <?php endif; ?>
                <?php if (!empty($config['telefono_empresa'])): ?>
                    Tel: <?php echo htmlspecialchars($config['telefono_empresa']); ?><br>
                <?php endif; ?>
                <?php if (!empty($config['email_empresa'])): ?>
                    <?php echo htmlspecialchars($config['email_empresa']); ?><br>
                <?php endif; ?>
                <?php if (!empty($config['cuit_empresa'])): ?>
                    CUIT: <?php echo htmlspecialchars($config['cuit_empresa']); ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- INFO VENTA -->
        <div class="info-venta">
            <div class="row">
                <span>Ticket Nº:</span>
                <strong>#<?php echo str_pad($venta['id'], 6, '0', STR_PAD_LEFT); ?></strong>
            </div>
            <div class="row">
                <span>Fecha:</span>
                <span><?php echo date('d/m/Y H:i', strtotime($venta['fecha_venta'])); ?></span>
            </div>
            <div class="row">
                <span>Vendedor:</span>
                <span><?php echo htmlspecialchars($venta['vendedor_nombre']); ?></span>
            </div>
            <?php if (!empty($venta['punto_venta'])): ?>
            <div class="row">
                <span>Punto Venta:</span>
                <span><?php echo htmlspecialchars($venta['punto_venta']); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($venta['sucursal']) && $venta['sucursal'] !== 'Principal'): ?>
            <div class="row">
                <span>Sucursal:</span>
                <span><?php echo htmlspecialchars($venta['sucursal']); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- ITEMS -->
        <table>
            <thead>
                <tr>
                    <th class="col-cant">Cant</th>
                    <th class="col-desc">Descripción</th>
                    <th class="col-precio">Precio</th>
                    <th class="col-total">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td class="col-cant"><?php echo $item['cantidad']; ?></td>
                    <td class="col-desc">
                        <?php echo htmlspecialchars($item['producto_nombre']); ?>
                        <?php if (!empty($item['codigo_barras'])): ?>
                        <br><small><?php echo htmlspecialchars($item['codigo_barras']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="col-precio">$<?php echo number_format($item['precio_unitario'], 2); ?></td>
                    <td class="col-total">$<?php echo number_format($item['subtotal'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- TOTALES -->
        <div class="totales">
            <div class="row">
                <span>Subtotal:</span>
                <span>$<?php echo number_format($venta['total'], 2); ?></span>
            </div>
            
            <?php if ($venta['metodo_pago'] === 'efectivo' && isset($venta['monto_pagado']) && $venta['monto_pagado'] > $venta['total']): ?>
            <div class="row">
                <span>Pagó con:</span>
                <span>$<?php echo number_format($venta['monto_pagado'], 2); ?></span>
            </div>
            <div class="row">
                <span>Cambio:</span>
                <span>$<?php echo number_format($venta['monto_pagado'] - $venta['total'], 2); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="row total-final">
                <span>TOTAL:</span>
                <span>$<?php echo number_format($venta['total'], 2); ?></span>
            </div>
            
            <div class="row" style="margin-top: 8px;">
                <span>Método de Pago:</span>
                <strong><?php echo strtoupper(htmlspecialchars($venta['metodo_pago'])); ?></strong>
            </div>
        </div>
        
        <!-- BARCODE -->
        <div class="barcode">*<?php echo str_pad($venta['id'], 6, '0', STR_PAD_LEFT); ?>*</div>
        
        <!-- FOOTER -->
        <div class="footer">
            <?php echo htmlspecialchars($config['mensaje_ticket'] ?? 'Gracias por su compra'); ?>
            <br>
            <small>Ticket no válido como factura</small>
        </div>
    </div>
    
    <script>
        // Auto-imprimir al cargar y cerrar después
        window.onload = function() {
            window.print();
            // Cerrar la ventana después de imprimir (o cancelar)
            window.onafterprint = function() {
                window.close();
            };
        };
    </script>
</body>
</html>
