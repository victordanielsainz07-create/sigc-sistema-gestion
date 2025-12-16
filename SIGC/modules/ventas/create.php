<?php
require_once '../../config/db.php';
validarSesion();

$database = new Database();
$db = $database->getConnection();

// Obtener clientes y productos
$queryClientes = "SELECT id_cliente, nombre FROM clientes ORDER BY nombre";
$stmtClientes = $db->prepare($queryClientes);
$stmtClientes->execute();
$clientes = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);

$queryProductos = "SELECT id_producto, nombre, precio, stock FROM productos WHERE stock > 0 ORDER BY nombre";
$stmtProductos = $db->prepare($queryProductos);
$stmtProductos->execute();
$productos = $stmtProductos->fetchAll(PDO::FETCH_ASSOC);

// Generar número de factura
$numeroFactura = 'FV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $db->beginTransaction();
        
        $id_cliente = intval($_POST['id_cliente']);
        $fecha_venta = $_POST['fecha_venta'];
        $observaciones = sanitizar($_POST['observaciones']);
        $numero_factura = sanitizar($_POST['numero_factura']);
        
        // Insertar cabecera de venta
        $queryVenta = "INSERT INTO ventas (id_cliente, numero_factura, fecha_venta, total, estado, observaciones) 
                      VALUES (:id_cliente, :numero_factura, :fecha_venta, 0, 'pendiente', :observaciones)";
        
        $stmtVenta = $db->prepare($queryVenta);
        $stmtVenta->bindParam(":id_cliente", $id_cliente);
        $stmtVenta->bindParam(":numero_factura", $numero_factura);
        $stmtVenta->bindParam(":fecha_venta", $fecha_venta);
        $stmtVenta->bindParam(":observaciones", $observaciones);
        $stmtVenta->execute();
        
        $id_venta = $db->lastInsertId();
        $total = 0;
        
        // Procesar detalles
        $productosVenta = $_POST['productos'];
        $cantidades = $_POST['cantidades'];
        $precios = $_POST['precios'];
        
        for ($i = 0; $i < count($productosVenta); $i++) {
            if (!empty($productosVenta[$i]) && !empty($cantidades[$i])) {
                $id_producto = intval($productosVenta[$i]);
                $cantidad = intval($cantidades[$i]);
                $precio_unitario = floatval($precios[$i]);
                $subtotal = $cantidad * $precio_unitario;
                
                // Verificar stock
                $queryStock = "SELECT stock FROM productos WHERE id_producto = :id";
                $stmtStock = $db->prepare($queryStock);
                $stmtStock->bindParam(":id", $id_producto);
                $stmtStock->execute();
                $stock = $stmtStock->fetch(PDO::FETCH_ASSOC)['stock'];
                
                if ($stock < $cantidad) {
                    throw new Exception("Stock insuficiente para el producto: " . $productosVenta[$i]);
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
                
                $total += $subtotal;
            }
        }
        
        // Actualizar total de la venta
        $queryUpdateTotal = "UPDATE ventas SET total = :total WHERE id_venta = :id_venta";
        $stmtUpdateTotal = $db->prepare($queryUpdateTotal);
        $stmtUpdateTotal->bindParam(":total", $total);
        $stmtUpdateTotal->bindParam(":id_venta", $id_venta);
        $stmtUpdateTotal->execute();
        
        $db->commit();
        
        header("Location: detail.php?id=$id_venta&msg=Venta registrada exitosamente");
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
    <title>Nueva Venta</title>
    <link rel="stylesheet" href="/SIGC/assets/css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .producto-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .btn-remove-producto {
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
        }
        .total-section {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 1.5em;
            text-align: right;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>Nueva Venta</h2>
        
        <?php if (isset($mensaje)): ?>
            <div class="<?php echo $tipoMensaje; ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <form method="POST" id="formVenta">
            <div class="form-row">
                <div class="form-group">
                    <label>Cliente *</label>
                    <select name="id_cliente" required>
                        <option value="">Seleccionar cliente</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id_cliente']; ?>">
                                <?php echo htmlspecialchars($cliente['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Número de Factura</label>
                    <input type="text" name="numero_factura" value="<?php echo $numeroFactura; ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>Fecha de Venta</label>
                    <input type="date" name="fecha_venta" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Observaciones</label>
                <textarea name="observaciones" rows="2"></textarea>
            </div>
            
            <h3>Productos</h3>
            <div id="productos-container">
                <div class="producto-item">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Producto</label>
                            <select name="productos[]" class="select-producto" required>
                                <option value="">Seleccionar producto</option>
                                <?php foreach ($productos as $producto): ?>
                                    <option value="<?php echo $producto['id_producto']; ?>" 
                                            data-precio="<?php echo $producto['precio']; ?>"
                                            data-stock="<?php echo $producto['stock']; ?>">
                                        <?php echo htmlspecialchars($producto['nombre']); ?> 
                                        (Stock: <?php echo $producto['stock']; ?>) - 
                                        $<?php echo number_format($producto['precio'], 2); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Cantidad</label>
                            <input type="number" name="cantidades[]" min="1" value="1" required class="input-cantidad">
                        </div>
                        
                        <div class="form-group">
                            <label>Precio Unitario</label>
                            <input type="number" name="precios[]" step="0.01" min="0" required class="input-precio">
                        </div>
                        
                        <div class="form-group">
                            <label>Subtotal</label>
                            <input type="text" class="input-subtotal" readonly>
                        </div>
                        
                        <div class="form-group">
                            <button type="button" class="btn-remove-producto" onclick="removerProducto(this)">×</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <button type="button" onclick="agregarProducto()" class="btn-secondary">+ Agregar Producto</button>
            
            <div class="total-section">
                <h3>Total: $<span id="total-venta">0.00</span></h3>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-primary">Registrar Venta</button>
                <a href="read.php" class="btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
    
    <script>
    function agregarProducto() {
        const container = document.getElementById('productos-container');
        const newItem = container.firstElementChild.cloneNode(true);
        
        // Limpiar valores
        newItem.querySelector('.select-producto').value = '';
        newItem.querySelector('.input-cantidad').value = 1;
        newItem.querySelector('.input-precio').value = '';
        newItem.querySelector('.input-subtotal').value = '';
        
        container.appendChild(newItem);
        actualizarCalculos();
    }
    
    function removerProducto(button) {
        if (document.querySelectorAll('.producto-item').length > 1) {
            button.closest('.producto-item').remove();
            actualizarCalculos();
        }
    }
    
    function actualizarCalculos() {
        let total = 0;
        
        document.querySelectorAll('.producto-item').forEach(item => {
            const select = item.querySelector('.select-producto');
            const cantidad = item.querySelector('.input-cantidad');
            const precio = item.querySelector('.input-precio');
            const subtotal = item.querySelector('.input-subtotal');
            
            if (select.value && cantidad.value && precio.value) {
                const cantidadVal = parseFloat(cantidad.value) || 0;
                const precioVal = parseFloat(precio.value) || 0;
                const subtotalVal = cantidadVal * precioVal;
                
                subtotal.value = '$' + subtotalVal.toFixed(2);
                total += subtotalVal;
            }
        });
        
        document.getElementById('total-venta').textContent = total.toFixed(2);
    }
    
    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('productos-container').addEventListener('change', function(e) {
            if (e.target.classList.contains('select-producto')) {
                const precio = e.target.options[e.target.selectedIndex].getAttribute('data-precio');
                const stock = e.target.options[e.target.selectedIndex].getAttribute('data-stock');
                const item = e.target.closest('.producto-item');
                item.querySelector('.input-precio').value = precio;
                item.querySelector('.input-cantidad').max = stock;
                actualizarCalculos();
            } else if (e.target.classList.contains('input-cantidad') || 
                      e.target.classList.contains('input-precio')) {
                actualizarCalculos();
            }
        });
        
        document.getElementById('productos-container').addEventListener('input', function(e) {
            if (e.target.classList.contains('input-cantidad') || 
                e.target.classList.contains('input-precio')) {
                actualizarCalculos();
            }
        });
        
        actualizarCalculos();
    });
    </script>
</body>
</html>