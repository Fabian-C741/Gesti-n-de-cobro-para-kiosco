<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Cajero'; ?> - Sistema de Gestión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        .cajero-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        .producto-item {
            cursor: pointer;
            transition: all 0.2s;
        }
        .producto-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .carrito-floating {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        .total-display {
            font-size: 2rem;
            font-weight: bold;
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="cajero-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <h4 class="mb-0">
                        <i class="bi bi-cash-register me-2"></i>
                        Punto de Venta - Cajero
                    </h4>
                </div>
                <div class="col-md-4 text-center">
                    <h5 class="mb-0"><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Cajero'); ?></h5>
                    <small><?php echo htmlspecialchars($_SESSION['punto_venta'] ?? 'PV-001'); ?></small>
                </div>
                <div class="col-md-4 text-end">
                    <a href="index.php" class="btn btn-light btn-sm me-2">
                        <i class="bi bi-cart me-1"></i> Punto de Venta
                    </a>
                    <a href="consultar_precios.php" class="btn btn-light btn-sm me-2">
                        <i class="bi bi-search me-1"></i> Consultar Precios
                    </a>
                    <a href="mis_ventas.php" class="btn btn-light btn-sm me-2">
                        <i class="bi bi-receipt me-1"></i> Mis Ventas
                    </a>
                    <a href="../logout.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i> Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </div>
