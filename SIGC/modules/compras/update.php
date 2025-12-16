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

// Obtener venta actual
$queryVenta = "SELECT v.*, c.nombre as cliente_nombre 
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

// Verificar que sea editable
if ($venta['estado'] != 'pendiente') {
    header("Location: detail.php?id=$id_venta&msg=Solo se pueden editar ventas pendientes");
    exit();
}

// Obtener detalles actuales
$queryDetalles = "SELECT dv.*, p.nombre as producto_nombre, p.precio as precio_actual, p.stock
                  FROM detalle_venta dv 
                  JOIN productos p ON dv.id_producto = p.id_producto 
                  WHERE dv.id_venta = :id";
$stmtDetalles = $db->prepare($queryDetalles);
$stmtDetalles->bindParam(":id", $id_venta);
$stmtDetalles->execute();
$detalles = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);

// Obtener clientes y productos para el formulario
$queryClientes = "SELECT id_cliente, nombre FROM clientes ORDER BY nombre";
$stmtClientes = $db->prepare($queryClientes);
$stmtClientes->execute();
$clientes = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);

$queryProductos = "SELECT id_producto, nombre, precio, stock FROM productos WHERE activo = 1 ORDER BY nombre";
$stmtProductos = $db->prepare($queryProductos);
$stmtProductos->execute();
$productos = $stmtProductos->fetchAll(PDO::FETCH_ASSOC);

// Procesar actualización
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $db->beginTransaction();
        
        // Restaurar stock de productos anteriores
        foreach ($detalles as $detalle) {
            $queryRestaurar = "UPDATE productos SET stock = stock + :cantidad WHERE id_producto = :id_producto";
            $stmtRestaurar = $db->prepare($queryRestaurar);
            $stmtRestaurar->bindParam(":cantidad", $detalle['cantidad']);
            $stmtRestaurar->bindParam(":id_producto", $detalle['id_producto']);
            $stmtRestaurar->execute();
        }
        
        // Eliminar detalles anteriores
        $queryEliminarDetalles = "DELETE FROM detalle_venta WHERE id_venta = :id_venta";
        $stmtEliminar = $db->prepare($queryEliminarDetalles);
        $stmtEliminar->bindParam(":id_venta", $id_venta);
        $stmtEliminar->execute();
        
        // Actualizar cabecera
        $id_cliente = intval($_POST['id_cliente']);
        $fecha_venta = $_POST['fecha_venta'];
        $tipo_pago = sanitizar($_POST['tipo_pago']);
        $observaciones = sanitizar($_POST['observaciones']);
        $subtotal = floatval($_POST['subtotal']);
        $igv = floatval($_POST['igv']);
        $total = floatval($_POST['total']);
        
        $queryActualizar = "UPDATE ventas SET 
                           id_cliente = :id_cliente,
                           fecha_venta = :fecha_venta,
                           tipo_pago = :tipo_pago,
                           observaciones = :observaciones,
                           subtotal = :subtotal,
                           igv = :igv,
                           total = :total
                           WHERE id_venta = :id_venta";
        
        $stmtActualizar = $db->prepare($queryActualizar);
        $stmtActualizar->bindParam(":id_cliente", $id_cliente);
        $stmtActualizar->bindParam(":fecha_venta", $fecha_venta);
        $stmtActualizar->bindParam(":tipo_pago", $tipo_pago);
        $stmtActualizar->bindParam(":observaciones", $observaciones);
        $stmtActualizar->bindParam(":subtotal", $subtotal);
        $stmtActualizar->bindParam(":igv", $igv);
        $stmtActualizar->bindParam(":total", $total);
        $stmtActualizar->bindParam(":id_venta", $id_venta);
        $stmtActualizar->execute();
        
        // Insertar nuevos detalles
        $productosVenta = $_POST['producto_id'];
        $cantidades = $_POST['cantidad'];
        $precios = $_POST['precio'];
        
        for ($i = 0; $i < count($productosVenta); $i++) {
            if (!empty($productosVenta[$i]) && !empty($cantidades[$i])) {
                $id_producto = intval($productosVenta[$i]);
                $cantidad = intval($cantidades[$i]);
                $precio_unitario = floatval($precios[$i]);
                
                // Verificar stock disponible
                $queryStock = "SELECT stock FROM productos WHERE id_producto = :id_producto";
                $stmtStock = $db->prepare($queryStock);
                $stmtStock->bindParam(":id_producto", $id_producto);
                $stmtStock->execute();
                $productoStock = $stmtStock->fetch(PDO::FETCH_ASSOC);
                
                if ($productoStock['stock'] < $cantidad) {
                    throw new Exception("Stock insuficiente para el producto ID: $id_producto");
                }
                
                // Insertar detalle
                $queryDetalle = "INSERT INTO detalle_venta (id_venta, id_producto, cantidad, precio_unitario) 
                                VALUES (:id_venta, :id_producto, :cantidad, :precio_unitario)";
                
                $stmtDetalle = $db->prepare($queryDetalle);
                $stmtDetalle->bindParam(":id_venta", $id_venta);
                $stmtDetalle->bindParam(":id_producto", $id_producto);
                $stmtDetalle->bindParam(":cantidad", $cantidad);
                $stmtDetalle->bindParam(":precio_unitario", $precio_unitario);
                $stmtDetalle->execute();
                
                // Actualizar stock
                $queryUpdateStock = "UPDATE productos SET stock = stock - :cantidad WHERE id_producto = :id_producto";
                $stmtUpdate = $db->prepare($queryUpdateStock);
                $stmtUpdate->bindParam(":cantidad", $cantidad);
                $stmtUpdate->bindParam(":id_producto", $id_producto);
                $stmtUpdate->execute();
            }
        }
        
        $db->commit();
        
        header("Location: detail.php?id=$id_venta&msg=Venta actualizada exitosamente");
        exit();
        
    } catch(Exception $e) {
        $db->rollBack();
        $mensaje = "Error: " . $e->getMessage();
        $tipoMensaje = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Venta</title>
    <link rel="stylesheet" href="/SIGC/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="card">
            <h2><i class="fas fa-edit"></i> Editar Venta #<?php echo $venta['numero_factura']; ?></h2>
            
            <?php if (isset($mensaje)): ?>
                <div class="<?php echo $tipoMensaje; ?>"><?php echo $mensaje; ?></div>
            <?php endif; ?>
            
            <form method="POST" id="formVenta">
                <div class="form-row">
                    <div class="form-group">
                        <label>Cliente *</label>
                        <select name="id_cliente" required>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['id_cliente']; ?>"
                                    <?php echo ($cliente['id_cliente'] == $venta['id_cliente']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cliente['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>N° Factura</label>
                        <input type="text" value="<?php echo $venta['numero_factura']; ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Fecha *</label>
                        <input type="date" name="fecha_venta" value="<?php echo $venta['fecha_venta']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Tipo de Pago *</label>
                        <select name="tipo_pago" required>
                            <option value="efectivo" <?php echo ($venta['tipo_pago'] == 'efectivo') ? 'selected' : ''; ?>>Efectivo</option>
                            <option value="tarjeta" <?php echo ($venta['tipo_pago'] == 'tarjeta') ? 'selected' : ''; ?>>Tarjeta</option>
                            <option value="transferencia" <?php echo ($venta['tipo_pago'] == 'transferencia') ? 'selected' : ''; ?>>Transferencia</option>
                            <option value="credito" <?php echo ($venta['tipo_pago'] == 'credito') ? 'selected' : ''; ?>>Crédito</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Observaciones</label>
                    <textarea name="observaciones" rows="2"><?php echo htmlspecialchars($venta['observaciones']); ?></textarea>
                </div>
                
                <h3>Productos</h3>
                <div id="productos-container">
                    <?php foreach ($detalles as $index => $detalle): ?>
                    <div class="producto-row">
                        <select name="producto_id[]" class="select-producto" required 
                                onchange="actualizarPrecio(this)" data-index="<?php echo $index; ?>">
                            <option value="">Seleccionar producto</option>
                            <?php foreach ($productos as $producto): ?>
                                <option value="<?php echo $producto['id_producto']; ?>"
                                        data-precio="<?php echo $producto['precio']; ?>"
                                        data-stock="<?php echo $producto['stock']; ?>"
                                        <?php echo ($producto['id_producto'] == $detalle['id_producto']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($producto['nombre']); ?> 
                                    (Stock: <?php echo $producto['stock']; ?>) - 
                                    $<?php echo number_format($producto['precio'], 2); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <input type="number" name="cantidad[]" min="1" 
                               value="<?php echo $detalle['cantidad']; ?>" required 
                               class="cantidad" onchange="actualizarSubtotal(this)" 
                               oninput="validarStock(this)">
                        
                        <input type="number" name="precio[]" step="0.01" min="0" 
                               value="<?php echo $detalle['precio_unitario']; ?>" required 
                               class="precio" readonly>
                        
                        <input type="text" class="subtotal" readonly 
                               value="$<?php echo number_format($detalle['cantidad'] * $detalle['precio_unitario'], 2); ?>">
                        
                        <button type="button" class="btn-remove" onclick="removerProducto(this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" class="btn-secondary" onclick="agregarProducto()">
                    <i class="fas fa-plus"></i> Agregar Producto
                </button>
                
                <div class="totales">
                    <div>
                        <span>Subtotal:</span>
                        <span>$<span id="subtotal"><?php echo number_format($venta['subtotal'], 2); ?></span></span>
                        <input type="hidden" name="subtotal" id="input-subtotal" value="<?php echo $venta['subtotal']; ?>">
                    </div>
                    <div>
                        <span>IGV (18%):</span>
                        <span>$<span id="igv"><?php echo number_format($venta['igv'], 2); ?></span></span>
                        <input type="hidden" name="igv" id="input-igv" value="<?php echo $venta['igv']; ?>">
                    </div>
                    <div class="total-final">
                        <span>TOTAL:</span>
                        <span>$<span id="total"><?php echo number_format($venta['total'], 2); ?></span></span>
                        <input type="hidden" name="total" id="input-total" value="<?php echo $venta['total']; ?>">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Actualizar Venta
                    </button>
                    <a href="detail.php?id=<?php echo $id_venta; ?>" class="btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // JavaScript similar al create.php pero pre-cargado con datos
        let contadorProductos = <?php echo count($detalles); ?>;
        
        function agregarProducto() {
            const container = document.getElementById('productos-container');
            const newRow = container.children[0].cloneNode(true);
            
            // Limpiar valores
            newRow.querySelector('.select-producto').value = '';
            newRow.querySelector('.cantidad').value = 1;
            newRow.querySelector('.precio').value = '';
            newRow.querySelector('.subtotal').value = '';
            
            // Actualizar índice
            const index = contadorProductos;
            newRow.querySelector('.select-producto').setAttribute('data-index', index);
            
            // Actualizar eventos
            newRow.querySelector('.select-producto').onchange = function() { actualizarPrecio(this); };
            newRow.querySelector('.cantidad').onchange = function() { actualizarSubtotal(this); };
            newRow.querySelector('.cantidad').oninput = function() { validarStock(this); };
            
            container.appendChild(newRow);
            contadorProductos++;
        }
        
        // Las demás funciones son similares al create.php
        // ... (copiar funciones del create.php)
        
        // Inicializar cálculos
        document.addEventListener('DOMContentLoaded', function() {
            calcularTotales();
        });
    </script>
</body>
</html>
