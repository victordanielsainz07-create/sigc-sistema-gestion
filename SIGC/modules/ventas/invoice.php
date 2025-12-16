<?php
require_once '../../config/db.php';
validarSesion();

$database = new Database();
$db = $database->getConnection();

$id_venta = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_venta == 0) {
    header("Location: read.php");
    exit();
}

// Obtener información de la venta
$queryVenta = "SELECT v.*, c.nombre as cliente_nombre, c.direccion as cliente_direccion, 
                      c.telefono as cliente_telefono, c.email as cliente_email
               FROM ventas v 
               INNER JOIN clientes c ON v.id_cliente = c.id_cliente 
               WHERE v.id_venta = :id";
$stmtVenta = $db->prepare($queryVenta);
$stmtVenta->bindParam(":id", $id_venta);
$stmtVenta->execute();
$venta = $stmtVenta->fetch(PDO::FETCH_ASSOC);

if (!$venta) {
    header("Location: read.php");
    exit();
}

// Obtener detalles de la venta
$queryDetalles = "SELECT dv.*, p.nombre as producto_nombre
                  FROM detalle_venta dv 
                  INNER JOIN productos p ON dv.id_producto = p.id_producto 
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
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            background-color: white;
        }
        
        .invoice-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #333;
        }
        
        .company-info h1 {
            margin: 0;
            color: #333;
        }
        
        .invoice-info h2 {
            margin: 0;
            color: #333;
        }
        
        .customer-info {
            margin-bottom: 30px;
        }
        
        .table-info {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .table-info th, .table-info td {
            padding: 8px;
            text-align: left;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .data-table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #333;
            padding: 10px;
        }
        
        .data-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .data-table tfoot td {
            font-weight: bold;
            background-color: #f8f9fa;
            border-top: 2px solid #333;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .observations {
            margin-top: 30px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .invoice-container {
                border: none;
                padding: 0;
            }
            
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Encabezado -->
        <div class="invoice-header">
            <div class="company-info">
                <h1>SIGC</h1>
                <p>Sistema de Gestión Comercial</p>
                <p>Av. Principal #123</p>
                <p>Tel: (123) 456-7890</p>
                <p>info@sigc.com</p>
                <p>RUC: 12345678901</p>
            </div>
            
            <div class="invoice-info">
                <h2>FACTURA</h2>
                <p><strong>N°:</strong> <?php echo $venta['numero_factura']; ?></p>
                <p><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($venta['fecha_venta'])); ?></p>
                <p><strong>Estado:</strong> <?php echo ucfirst($venta['estado']); ?></p>
            </div>
        </div>
        
        <!-- Información del cliente -->
        <div class="customer-info">
            <h3>Cliente:</h3>
            <table class="table-info">
                <tr>
                    <th>Nombre:</th>
                    <td><?php echo htmlspecialchars($venta['cliente_nombre']); ?></td>
                </tr>
                <tr>
                    <th>Dirección:</th>
                    <td><?php echo htmlspecialchars($venta['cliente_direccion']); ?></td>
                </tr>
                <tr>
                    <th>Teléfono:</th>
                    <td><?php echo $venta['cliente_telefono']; ?></td>
                </tr>
                <tr>
                    <th>Email:</th>
                    <td><?php echo $venta['cliente_email']; ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Detalles -->
        <h3>Detalles de la Venta:</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Producto</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-right">Precio Unitario</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $index => $detalle): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($detalle['producto_nombre']); ?></td>
                    <td class="text-right"><?php echo $detalle['cantidad']; ?></td>
                    <td class="text-right">$<?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                    <td class="text-right">$<?php echo number_format($detalle['subtotal'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right"><strong>TOTAL:</strong></td>
                    <td class="text-right"><strong>$<?php echo number_format($venta['total'], 2); ?></strong></td>
                </tr>
            </tfoot>
        </table>
        
        <!-- Observaciones -->
        <?php if (!empty($venta['observaciones'])): ?>
        <div class="observations">
            <h4>Observaciones:</h4>
            <p><?php echo nl2br(htmlspecialchars($venta['observaciones'])); ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="footer">
            <p>¡Gracias por su compra!</p>
            <p>Para consultas o soporte, contáctenos: info@sigc.com | (123) 456-7890</p>
            <p>Factura generada electrónicamente el <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
    </div>
    
    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" class="btn-primary">Imprimir Factura</button>
        <button onclick="window.close()" class="btn-secondary">Cerrar</button>
    </div>
    
    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>