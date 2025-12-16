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

// Obtener venta actual
$queryVenta = "SELECT v.*, c.nombre as cliente_nombre 
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

// Verificar que la venta no esté completada o cancelada
if ($venta['estado'] == 'completada' || $venta['estado'] == 'cancelada') {
    header("Location: detail.php?id=$id_venta&msg=No se puede editar una venta " . $venta['estado']);
    exit();
}

// Obtener detalles actuales
$queryDetalles = "SELECT dv.*, p.nombre as producto_nombre, p.stock as producto_stock 
                  FROM detalle_venta dv 
                  INNER JOIN productos p ON dv.id_producto = p.id_producto 
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

$queryProductos = "SELECT id_producto, nombre, precio, stock FROM productos WHERE stock > 0 ORDER BY nombre";
$stmtProductos = $db->prepare($queryProductos);
$stmtProductos->execute();
$productos = $stmtProductos->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $db->beginTransaction();
        
        // Eliminar detalles actuales y restaurar stock
        foreach ($detalles as $detalle) {
            $queryRestaurar = "UPDATE productos SET stock = stock + :cantidad WHERE id_producto = :id_producto";
            $stmtRestaurar = $db->prepare($queryRestaurar);
            $stmtRestaurar->bindParam(":cantidad", $detalle['cantidad']);
            $stmtRestaurar->bindParam(":id_producto", $detalle['id_producto']);
            $stmtRestaurar->execute();
        }
        
        $queryEliminarDetalles = "DELETE FROM detalle_venta WHERE id_venta = :id_venta";
        $stmtEliminar = $db->prepare($queryEliminarDetalles);
        $stmtEliminar->bindParam(":id_venta", $id_venta);
        $stmtEliminar->execute();
        
        // Actualizar cabecera
        $id_cliente = intval($_POST['id_cliente']);
        $fecha_venta = $_POST['fecha_venta'];
        $observaciones = sanitizar($_POST['observaciones']);
        
        $queryActualizarVenta = "UPDATE ventas SET 
                                id_cliente = :id_cliente, 
                                fecha_venta = :fecha_venta, 
                                observaciones = :observaciones 
                                WHERE id_venta = :id_venta";
        
        $stmtActualizarVenta = $db->prepare($queryActualizarVenta);
        $stmtActualizarVenta->bindParam(":id_cliente", $id_cliente);
        $stmtActualizarVenta->bindParam(":fecha_venta", $fecha_venta);
        $stmtActualizarVenta->bindParam(":observaciones", $observaciones);
        $stmtActualizarVenta->bindParam(":id_venta", $id_venta);
        $stmtActualizarVenta->execute();
        
        $total = 0;
        
        // Insertar nuevos detalles
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
                    throw new Exception("Stock insuficiente para el producto seleccionado");
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
        
        // Actualizar total
        $queryUpdateTotal = "UPDATE ventas SET total = :total WHERE id_venta = :id_venta";
        $stmtUpdateTotal = $db->prepare($queryUpdateTotal);
        $stmtUpdateTotal->bindParam(":total", $total);
        $stmtUpdateTotal->bindParam(":id_venta", $id_venta);
        $stmtUpdateTotal->execute();
        
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>Editar Venta #<?php echo $venta['numero_factura']; ?></h2>
        
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
                            <option value="<?php echo $cliente['id_cliente']; ?>"
                                <?php echo ($cliente['id_cliente'] == $venta['id_cliente']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cliente['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Número de Factura</label>
                    <input type="text" value="<?php echo $venta['numero_factura']; ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>Fecha de Venta</label>
                    <input type="date" name="fecha_venta" value="<?php echo $venta['fecha_venta']; ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Observaciones</label>
                <textarea name="observaciones" rows="2"><?php echo htmlspecialchars($venta['observaciones']); ?></textarea>
            </div>
            
            <h3>Productos</h3>
            <div id="productos-container">
                <?php foreach ($detalles as $index => $detalle): ?>
                <div class="producto-item">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Producto</label>
                            <select name="productos[]" class="select-producto" required>
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
                        </div>
                        
                        <div class="form-group">
                            <label>Cantidad</label>
                            <input type="number" name="cantidades[]" min="1" 
                                   value="<?php echo $detalle['cantidad']; ?>" 
                                   required class="input-cantidad">
                        </div>
                        
                        <div class="form-group">
                            <label>Precio Unitario</label>
                            <input type="number" name="precios[]" step="0.01" min="0" 
                                   value="<?php echo $detalle['precio_unitario']; ?>" 
                                   required class="input-precio">
                        </div>
                        
                        <div class="form-group">
                            <label>Subtotal</label>
                            <input type="text" class="input-subtotal" 
                                   value="$<?php echo number_format($detalle['cantidad'] * $detalle['precio_unitario'], 2); ?>" 
                                   readonly>
                        </div>
                        
                        <div class="form-group">
                            <button type="button" class="btn-remove-producto" onclick="removerProducto(this)">×</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <button type="button" onclick="agregarProducto()" class="btn-secondary">+ Agregar Producto</button>
            
            <div class="total-section">
                <h3>Total: $<span id="total-venta"><?php echo number_format($venta['total'], 2); ?></span></h3>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-primary">Actualizar Venta</button>
                <a href="detail.php?id=<?php echo $id_venta; ?>" class="btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
    
    <script>
    // JavaScript similar al create.php
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