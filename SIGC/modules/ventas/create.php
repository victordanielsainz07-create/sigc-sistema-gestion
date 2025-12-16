<?php
require_once '../../config/db.php';
validarSesion();

$database = new Database();
$db = $database->getConnection();

// Verificar si la columna observaciones existe
$checkColumn = $db->query("SHOW COLUMNS FROM ventas LIKE 'observaciones'")->fetch();
$tieneObservaciones = ($checkColumn !== false);

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
        
        // Construir query según si existe la columna observaciones
        if ($tieneObservaciones) {
            $queryVenta = "INSERT INTO ventas (id_cliente, numero_factura, fecha_venta, total, estado, observaciones) 
                          VALUES (:id_cliente, :numero_factura, :fecha_venta, 0, 'pendiente', :observaciones)";
        } else {
            $queryVenta = "INSERT INTO ventas (id_cliente, numero_factura, fecha_venta, total, estado) 
                          VALUES (:id_cliente, :numero_factura, :fecha_venta, 0, 'pendiente')";
        }
        
        $stmtVenta = $db->prepare($queryVenta);
        $stmtVenta->bindParam(":id_cliente", $id_cliente);
        $stmtVenta->bindParam(":numero_factura", $numero_factura);
        $stmtVenta->bindParam(":fecha_venta", $fecha_venta);
        
        if ($tieneObservaciones) {
            $stmtVenta->bindParam(":observaciones", $observaciones);
        }
        
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
                $queryStock = "SELECT stock, nombre FROM productos WHERE id_producto = :id";
                $stmtStock = $db->prepare($queryStock);
                $stmtStock->bindParam(":id", $id_producto);
                $stmtStock->execute();
                $producto = $stmtStock->fetch(PDO::FETCH_ASSOC);
                
                if (!$producto) {
                    throw new Exception("Producto no encontrado");
                }
                
                if ($producto['stock'] < $cantidad) {
                    throw new Exception("Stock insuficiente para el producto: " . $producto['nombre'] . 
                                      " (Disponible: " . $producto['stock'] . ")");
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
                
                // Actualizar stock del producto
                $queryUpdateStock = "UPDATE productos SET stock = stock - :cantidad WHERE id_producto = :id";
                $stmtUpdateStock = $db->prepare($queryUpdateStock);
                $stmtUpdateStock->bindParam(":cantidad", $cantidad);
                $stmtUpdateStock->bindParam(":id", $id_producto);
                $stmtUpdateStock->execute();
                
                $total += $subtotal;
            }
        }
        
        // Validar que se agregó al menos un producto
        if ($total == 0) {
            throw new Exception("Debe agregar al menos un producto a la venta");
        }
        
        // Actualizar total de la venta
        $queryUpdateTotal = "UPDATE ventas SET total = :total WHERE id_venta = :id_venta";
        $stmtUpdateTotal = $db->prepare($queryUpdateTotal);
        $stmtUpdateTotal->bindParam(":total", $total);
        $stmtUpdateTotal->bindParam(":id_venta", $id_venta);
        $stmtUpdateTotal->execute();
        
        $db->commit();
        
        header("Location: read.php?msg=Venta registrada exitosamente (ID: $id_venta)");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Venta - SIGC</title>
    <link rel="stylesheet" href="/SIGC/assets/css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .producto-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            background-color: #f9f9f9;
            position: relative;
        }
        .producto-item .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 10px;
            align-items: end;
        }
        .btn-remove-producto {
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            cursor: pointer;
            font-size: 20px;
            line-height: 1;
            transition: background-color 0.3s;
        }
        .btn-remove-producto:hover {
            background-color: #c82333;
        }
        .total-section {
            background-color: #e9ecef;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 1.5em;
            text-align: right;
        }
        .total-section h3 {
            margin: 0;
            color: #28a745;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .input-subtotal {
            background-color: #e9ecef;
            font-weight: bold;
        }
        @media (max-width: 768px) {
            .producto-item .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>Nueva Venta</h2>
        
        <?php if (isset($mensaje)): ?>
            <div class="<?php echo $tipoMensaje; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="formVenta" onsubmit="return validarFormulario()">
            <div class="form-row">
                <div class="form-group">
                    <label>Cliente *</label>
                    <select name="id_cliente" required>
                        <option value="">Seleccionar cliente</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id_cliente']; ?>"
                                    <?php echo (isset($_POST['id_cliente']) && $_POST['id_cliente'] == $cliente['id_cliente']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cliente['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Número de Factura</label>
                    <input type="text" name="numero_factura" 
                           value="<?php echo isset($_POST['numero_factura']) ? htmlspecialchars($_POST['numero_factura']) : $numeroFactura; ?>" 
                           readonly>
                </div>
                
                <div class="form-group">
                    <label>Fecha de Venta *</label>
                    <input type="date" name="fecha_venta" 
                           value="<?php echo isset($_POST['fecha_venta']) ? $_POST['fecha_venta'] : date('Y-m-d'); ?>" 
                           required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Observaciones</label>
                <textarea name="observaciones" rows="3" placeholder="Notas adicionales sobre la venta (opcional)"><?php echo isset($_POST['observaciones']) ? htmlspecialchars($_POST['observaciones']) : ''; ?></textarea>
                <?php if (!$tieneObservaciones): ?>
                    <small style="color: #666;">Nota: Las observaciones no se guardarán hasta que agregue la columna a la base de datos.</small>
                <?php endif; ?>
            </div>
            
            <h3>Productos</h3>
            <div id="productos-container">
                <div class="producto-item">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Producto *</label>
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
                            <label>Cantidad *</label>
                            <input type="number" name="cantidades[]" min="1" value="1" required class="input-cantidad">
                        </div>
                        
                        <div class="form-group">
                            <label>Precio Unit. *</label>
                            <input type="number" name="precios[]" step="0.01" min="0.01" required class="input-precio">
                        </div>
                        
                        <div class="form-group">
                            <label>Subtotal</label>
                            <input type="text" class="input-subtotal" readonly value="$0.00">
                        </div>
                        
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="button" class="btn-remove-producto" onclick="removerProducto(this)" title="Eliminar producto">×</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <button type="button" onclick="agregarProducto()" class="btn-secondary">
                + Agregar Producto
            </button>
            
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
    let productosData = <?php echo json_encode($productos); ?>;
    
    function agregarProducto() {
        const container = document.getElementById('productos-container');
        const newItem = container.firstElementChild.cloneNode(true);
        
        // Limpiar valores
        newItem.querySelector('.select-producto').value = '';
        newItem.querySelector('.input-cantidad').value = 1;
        newItem.querySelector('.input-precio').value = '';
        newItem.querySelector('.input-subtotal').value = '$0.00';
        
        container.appendChild(newItem);
        actualizarCalculos();
    }
    
    function removerProducto(button) {
        const items = document.querySelectorAll('.producto-item');
        if (items.length > 1) {
            button.closest('.producto-item').remove();
            actualizarCalculos();
        } else {
            alert('Debe mantener al menos un producto en la venta');
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
            } else {
                subtotal.value = '$0.00';
            }
        });
        
        document.getElementById('total-venta').textContent = total.toFixed(2);
    }
    
    function validarFormulario() {
        const items = document.querySelectorAll('.producto-item');
        let tieneProductos = false;
        
        items.forEach(item => {
            const select = item.querySelector('.select-producto');
            const cantidad = item.querySelector('.input-cantidad');
            const precio = item.querySelector('.input-precio');
            
            if (select.value && cantidad.value && precio.value) {
                tieneProductos = true;
                
                // Validar stock
                const stock = parseInt(select.options[select.selectedIndex].getAttribute('data-stock'));
                const cantidadVal = parseInt(cantidad.value);
                
                if (cantidadVal > stock) {
                    alert(`La cantidad solicitada (${cantidadVal}) excede el stock disponible (${stock}) para el producto seleccionado`);
                    cantidad.focus();
                    return false;
                }
            }
        });
        
        if (!tieneProductos) {
            alert('Debe agregar al menos un producto con cantidad y precio válidos');
            return false;
        }
        
        return true;
    }
    
    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('productos-container');
        
        // Usar delegación de eventos para manejar elementos dinámicos
        container.addEventListener('change', function(e) {
            if (e.target.classList.contains('select-producto')) {
                const selectedOption = e.target.options[e.target.selectedIndex];
                const precio = selectedOption.getAttribute('data-precio');
                const stock = selectedOption.getAttribute('data-stock');
                const item = e.target.closest('.producto-item');
                
                if (precio) {
                    item.querySelector('.input-precio').value = precio;
                    item.querySelector('.input-cantidad').max = stock;
                }
                
                actualizarCalculos();
            }
        });
        
        container.addEventListener('input', function(e) {
            if (e.target.classList.contains('input-cantidad') || 
                e.target.classList.contains('input-precio')) {
                actualizarCalculos();
            }
        });
        
        // Inicializar cálculos
        actualizarCalculos();
    });
    </script>
</body>
</html>