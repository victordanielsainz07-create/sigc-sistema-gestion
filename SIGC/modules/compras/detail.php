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
    header("Location: read.php?msg=Venta no encontrada");
    exit();
}

// Obtener detalles
$queryDetalles = "SELECT dv.*, p.nombre as producto_nombre, p.descripcion as producto_descripcion
                  FROM detalle_venta dv 
                  JOIN productos p ON dv.id_producto = p.id_producto 
                  WHERE dv.id_venta = :id";
$stmtDetalles = $db->prepare($queryDetalles);
$stmtDetalles->bindParam(":id", $id_venta);
$stmtDetalles->execute();
$detalles = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);

$mensaje = isset($_GET['msg']) ? $_GET['msg'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de Venta</title>
    <link rel="stylesheet" href="/SIGC/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .invoice-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 20px;
        }
        .company-info {
            flex: 1;
        }
        .invoice-info {
            text-align: right;
        }
        .client-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        .table-details {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .table-details th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
        }
        .table-details td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        .table-details tfoot td {
            font-weight: bold;
            background: #f8f9fa;
        }
        .actions-invoice {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-pendiente { background: #ffc107; color: #000; }
        .badge-completada { background: #28a745; color: #fff; }
        .badge-cancelada { background: #dc3545; color: #fff; }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <?php if ($mensaje): ?>
            <div class="success"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <div class="invoice-container">
            <div class="invoice-header">
                <div class="company-info">
                    <h2>SIGC - Sistema de Gestión Comercial</h2>
                    <p>Av. Principal #123, Ciudad</p>
                    <p>Teléfono: (01) 234-5678</p>
                    <p>Email: info@sigc.com</p>
                    <p>RUC: 20123456789</p>
                </div>
                
                <div class="invoice-info">
                    <h1>FACTURA</h1>
                    <p><strong>N°:</strong> <?php echo $venta['numero_factura']; ?></p>
                    <p><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($venta['fecha_venta'])); ?></p>
                    <p><strong>Estado:</strong> 
                        <span class="badge badge-<?php echo $venta['estado']; ?>">
                            <?php echo ucfirst($venta['estado']); ?>
                        </span>
                    </p>
                </div>
            </div>
            
            <div class="client-info">
                <h3>Datos del Cliente</h3>
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 150px;"><strong>Nombre:</strong></td>
                        <td><?php echo htmlspecialchars($venta['cliente_nombre']); ?></td>
                        <td><strong>RUC/DNI:</strong></td>
                        <td><?php echo $venta['cliente_ruc'] ?: 'No especificado'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Dirección:</strong></td>
                        <td><?php echo htmlspecialchars($venta['cliente_direccion']); ?></td>
                        <td><strong>Teléfono:</strong></td>
                        <td><?php echo $venta['cliente_telefono']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Email:</strong></td>
                        <td><?php echo $venta['cliente_email']; ?></td>
                        <td><strong>Tipo Pago:</strong></td>
                        <td><?php echo ucfirst($venta['tipo_pago']); ?></td>
                    </tr>
                </table>
            </div>
            
            <h3>Detalle de la Venta</h3>
            <table class="table-details">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Descripción</th>
                        <th>Cantidad</th>
                        <th>Precio Unitario</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles as $detalle): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($detalle['producto_nombre']); ?></td>
                        <td><?php echo htmlspecialchars(substr($detalle['producto_descripcion'], 0, 50)) . '...'; ?></td>
                        <td><?php echo $detalle['cantidad']; ?></td>
                        <td>$<?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                        <td>$<?php echo number_format($detalle['subtotal'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align: right;"><strong>Subtotal:</strong></td>
                        <td><strong>$<?php echo number_format($venta['subtotal'], 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align: right;"><strong>IGV (18%):</strong></td>
                        <td><strong>$<?php echo number_format($venta['igv'], 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align: right;"><strong>TOTAL:</strong></td>
                        <td><strong>$<?php echo number_format($venta['total'], 2); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
            
            <?php if (!empty($venta['observaciones'])): ?>
            <div class="observations">
                <h4>Observaciones:</h4>
                <p><?php echo nl2br(htmlspecialchars($venta['observaciones'])); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="actions-invoice">
                <a href="invoice.php?id=<?php echo $id_venta; ?>" target="_blank" class="btn-primary">
                    <i class="fas fa-print"></i> Imprimir Factura
                </a>
                <a href="read.php" class="btn-secondary">
                    <i class="fas fa-list"></i> Volver a la lista
                </a>
                
                <?php if ($venta['estado'] == 'pendiente'): ?>
                    <a href="update.php?id=<?php echo $id_venta; ?>" class="btn-edit">
                        <i class="fas fa-edit"></i> Editar Venta
                    </a>
                    <a href="delete.php?id=<?php echo $id_venta; ?>" class="btn-delete">
                        <i class="fas fa-ban"></i> Cancelar Venta
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>