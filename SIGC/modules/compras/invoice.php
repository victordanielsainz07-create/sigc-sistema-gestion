<?php
require_once '../../config/db.php';
validarSesion();

$database = new Database();
$db = $database->getConnection();

$id_venta = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_venta == 0) {
    header("Location: read.php?msg=ID no válido");
    exit();
}

// Obtener venta
$queryVenta = "SELECT v.*, c.nombre as cliente_nombre, c.direccion as cliente_direccion, 
                      c.telefono as cliente_telefono, c.email as cliente_email,
                      c.ruc as cliente_ruc
               FROM ventas v 
               JOIN clientes c ON v.id_cliente = c.id_cliente 
               WHERE v.id_venta = :id";
$stmtVenta = $db->prepare($queryVenta);
$stmtVenta->bindParam(":id", $id_venta);
$stmtVenta->execute();
$venta = $stmtVenta->fetch(PDO::FETCH_ASSOC);

if (!$venta) {
    die("Venta no encontrada");
}

// Obtener detalles
$queryDetalles = "SELECT dv.*, p.nombre as producto_nombre
                  FROM detalle_venta dv 
                  JOIN productos p ON dv.id_producto = p.id_producto 
                  WHERE dv.id_venta = :id";
$stmtDetalles = $db->prepare($queryDetalles);
$stmtDetalles->bindParam(":id", $id_venta);
$stmtDetalles->execute();
$detalles = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura #<?php echo $venta['numero_factura']; ?></title>
    <style>
        @media print {
            @page {
                margin: 0;
                size: A4;
            }
            body {
                margin: 1.5cm;
                font-family: 'Arial', sans-serif;
            }
            .no-print {
                display: none !important;
            }
        }
        
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        
        .invoice {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            background: white;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        
        .company-info h1 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }
        
        .invoice-info h2 {
            margin: 0;
            font-size: 20px;
            color: #333;
        }
        
        .client-info {
            margin-bottom: 30px;
        }
        
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .details-table th {
            background-color: #f2f2f2;
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }
        
        .details-table td {
            border: 1px solid #ddd;
            padding: 10px;
        }
        
        .totals {
            margin-left: auto;
            width: 300px;
        }
        
        .totals table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .totals td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        
        .totals tr:last-child td {
            font-weight: bold;
            border-top: 2px solid #333;
            font-size: 18px;
        }
        
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        
        .print-button {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="invoice">
        <div class="header">
            <div class="company-info">
                <h1>SIGC - Sistema de Gestión Comercial</h1>
                <p>Av. Principal #123, Ciudad</p>
                <p>Teléfono: (01) 234-5678</p>
                <p>Email: info@sigc.com</p>
                <p>RUC: 20123456789</p>
            </div>
            
            <div class="invoice-info">
                <h2>FACTURA ELECTRÓNICA</h2>
                <p><strong>N°:</strong> <?php echo $venta['numero_factura']; ?></p>
                <p><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($venta['fecha_venta'])); ?></p>
                <p><strong>Estado:</strong> <?php echo ucfirst($venta['estado']); ?></p>
            </div>
        </div>
        
        <div class="client-info">
            <h3>DATOS DEL CLIENTE</h3>
            <table style="width: 100%;">
                <tr>
                    <td style="width: 150px;"><strong>Señor(es):</strong></td>
                    <td><?php echo htmlspecialchars($venta['cliente_nombre']); ?></td>
                </tr>
                <tr>
                    <td><strong>RUC/DNI:</strong></td>
                    <td><?php echo $venta['cliente_ruc'] ?: 'No especificado'; ?></td>
                </tr>
                <tr>
                    <td><strong>Dirección:</strong></td>
                    <td><?php echo htmlspecialchars($venta['cliente_direccion']); ?></td>
                </tr>
                <tr>
                    <td><strong>Teléfono:</strong></td>
                    <td><?php echo $venta['cliente_telefono']; ?></td>
                </tr>
            </table>
        </div>
        
        <table class="details-table">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Descripción</th>
                    <th style="width: 80px;">Cantidad</th>
                    <th style="width: 100px;">Precio Unit.</th>
                    <th style="width: 120px;">Valor Venta</th>
                </tr>
            </thead>
            <tbody>
                <?php $contador = 1; ?>
                <?php foreach ($detalles as $detalle): ?>
                <tr>
                    <td><?php echo $contador++; ?></td>
                    <td><?php echo htmlspecialchars($detalle['producto_nombre']); ?></td>
                    <td><?php echo $detalle['cantidad']; ?></td>
                    <td>$<?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                    <td>$<?php echo number_format($detalle['subtotal'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="totals">
            <table>
                <tr>
                    <td>Subtotal:</td>
                    <td style="text-align: right;">$<?php echo number_format($venta['subtotal'], 2); ?></td>
                </tr>
                <tr>
                    <td>IGV (18%):</td>
                    <td style="text-align: right;">$<?php echo number_format($venta['igv'], 2); ?></td>
                </tr>
                <tr>
                    <td><strong>TOTAL:</strong></td>
                    <td style="text-align: right;"><strong>$<?php echo number_format($venta['total'], 2); ?></strong></td>
                </tr>
            </table>
        </div>
        
        <?php if (!empty($venta['observaciones'])): ?>
        <div style="margin-top: 30px;">
            <h4>Observaciones:</h4>
            <p><?php echo nl2br(htmlspecialchars($venta['observaciones'])); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <p><strong>¡Gracias por su preferencia!</strong></p>
            <p>Para consultas o reclamos, contactarse a: info@sigc.com | (01) 234-5678</p>
            <p>Factura generada electrónicamente. No requiere firma ni sello.</p>
            <p>Fecha de impresión: <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
    </div>
    
    <div class="print-button no-print">
        <button onclick="window.print()" style="padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer;">
            <i class="fas fa-print"></i> Imprimir Factura
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
            <i class="fas fa-times"></i> Cerrar
        </button>
    </div>
    
    <script>
        // Imprimir automáticamente al cargar
        window.onload = function() {
            window.print();
        }
        
        // Redirigir al cerrar la ventana de impresión
        window.onafterprint = function() {
            // Opcional: cerrar la ventana automáticamente
            // window.close();
        }
    </script>
</body>
</html>