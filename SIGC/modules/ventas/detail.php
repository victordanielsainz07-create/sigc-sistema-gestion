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
$queryDetalles = "SELECT dv.*, p.nombre as producto_nombre, p.descripcion as producto_descripcion
                  FROM detalle_venta dv 
                  INNER JOIN productos p ON dv.id_producto = p.id_producto 
                  WHERE dv.id_venta = :id";
$stmtDetalles = $db->prepare($queryDetalles);
$stmtDetalles->bindParam(":id", $id_venta);
$stmtDetalles->execute();
$detalles = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);

// Mensajes
$mensaje = isset($_GET['msg']) ? $_GET['msg'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de Venta</title>
    <link rel="stylesheet" href="/SIGC/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <?php if ($mensaje): ?>
            <div class="success"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2>Factura de Venta</h2>
                <div class="card-actions">
                    <a href="invoice.php?id=<?php echo $id_venta; ?>" target="_blank" class="btn-primary">
                        <i class="fas fa-print"></i> Imprimir
                    </a>
                    <a href="read.php" class="btn-secondary">
                        <i class="fas fa-list"></i> Volver a la lista
                    </a>
                </div>
            </div>
            
            <!-- Encabezado de la factura -->
            <div class="invoice-header">
                <div class="company-info">
                    <h3>SIGC - Sistema de Gestión Comercial</h3>
                    <p>Dirección: Av. Principal #123</p>
                    <p>Teléfono: (123) 456-7890</p>
                    <p>Email: info@sigc.com</p>
                </div>
                
                <div class="invoice-info">
                    <h2>FACTURA #<?php echo $venta['numero_factura']; ?></h2>
                    <p><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($venta['fecha_venta'])); ?></p>
                    <p><strong>Estado:</strong> 
                        <span class="badge badge-<?php echo $venta['estado']; ?>">
                            <?php echo ucfirst($venta['estado']); ?>
                        </span>
                    </p>
                </div>
            </div>
            
            <!-- Información del cliente -->
            <div class="customer-info">
                <h3>Información del Cliente</h3>
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
            
            <!-- Detalles de la venta -->
            <div class="invoice-details">
                <h3>Detalles de la Venta</h3>
                <table class="data-table">
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
                            <td><?php echo htmlspecialchars(substr($detalle['producto_descripcion'], 0, 50)); ?>...</td>
                            <td><?php echo $detalle['cantidad']; ?></td>
                            <td>$<?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                            <td>$<?php echo number_format($detalle['subtotal'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-right"><strong>Total:</strong></td>
                            <td><strong>$<?php echo number_format($venta['total'], 2); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- Observaciones -->
            <?php if (!empty($venta['observaciones'])): ?>
            <div class="observations">
                <h3>Observaciones</h3>
                <p><?php echo nl2br(htmlspecialchars($venta['observaciones'])); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Botones de acción -->
            <div class="form-actions">
                <?php if ($venta['estado'] == 'pendiente'): ?>
                    <a href="update.php?id=<?php echo $id_venta; ?>" class="btn-primary">
                        <i class="fas fa-edit"></i> Editar Venta
                    </a>
                <?php endif; ?>
                
                <?php if ($venta['estado'] != 'cancelada'): ?>
                    <a href="delete.php?id=<?php echo $id_venta; ?>" class="btn-delete">
                        <i class="fas fa-ban"></i> Anular Venta
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <style>
        .invoice-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        
        .company-info, .invoice-info {
            flex: 1;
        }
        
        .invoice-info {
            text-align: right;
        }
        
        .customer-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        
        .table-info {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table-info th {
            text-align: left;
            width: 150px;
            padding: 5px 10px;
        }
        
        .table-info td {
            padding: 5px 10px;
        }
        
        .text-right {
            text-align: right;
        }
    </style>
</body>
</html>