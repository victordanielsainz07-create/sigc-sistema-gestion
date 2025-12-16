<?php
require_once '../../config/db.php';
validarSesion();

$database = new Database();
$db = $database->getConnection();

// Obtener proveedores y productos
$queryProveedores = "SELECT id_proveedor, nombre FROM proveedores ORDER BY nombre";
$stmtProveedores = $db->prepare($queryProveedores);
$stmtProveedores->execute();
$proveedores = $stmtProveedores->fetchAll(PDO::FETCH_ASSOC);

$queryProductos = "SELECT id_producto, nombre, precio FROM productos ORDER BY nombre";
$stmtProductos = $db->prepare($queryProductos);
$stmtProductos->execute();
$productos = $stmtProductos->fetchAll(PDO::FETCH_ASSOC);

// Generar número de factura
$numeroFactura = 'FC-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $db->beginTransaction();
        
        $id_proveedor = intval($_POST['id_proveedor']);
        $fecha_compra = $_POST['fecha_compra'];
        $observaciones = sanitizar($_POST['observaciones']);
        $numero_factura = sanitizar($_POST['numero_factura']);
        
        // Insertar cabecera de compra
        $queryCompra = "INSERT INTO compras (id_proveedor, numero_factura, fecha_compra, total, estado, observaciones) 
                       VALUES (:id_proveedor, :numero_factura, :fecha_compra, 0, 'pendiente', :observaciones)";
        
        $stmtCompra = $db->prepare($queryCompra);
        $stmtCompra->bindParam(":id_proveedor", $id_proveedor);
        $stmtCompra->bindParam(":numero_factura", $numero_factura);
        $stmtCompra->bindParam(":fecha_compra", $fecha_compra);
        $stmtCompra->bindParam(":observaciones", $observaciones);
        $stmtCompra->execute();
        
        $id_compra = $db->lastInsertId();
        $total = 0;
        
        // Procesar detalles
        $productosCompra = $_POST['productos'];
        $cantidades = $_POST['cantidades'];
        $precios = $_POST['precios'];
        
        for ($i = 0; $i < count($productosCompra); $i++) {
            if (!empty($productosCompra[$i]) && !empty($cantidades[$i])) {
                $id_producto = intval($productosCompra[$i]);
                $cantidad = intval($cantidades[$i]);
                $precio_unitario = floatval($precios[$i]);
                $subtotal = $cantidad * $precio_unitario;
                
                // Insertar detalle
                $queryDetalle = "INSERT INTO detalle_compra (id_compra, id_producto, cantidad, precio_unitario) 
                                VALUES (:id_compra, :id_producto, :cantidad, :precio_unitario)";
                
                $stmtDetalle = $db->prepare($queryDetalle);
                $stmtDetalle->bindParam(":id_compra", $id_compra);
                $stmtDetalle->bindParam(":id_producto", $id_producto);
                $stmtDetalle->bindParam(":cantidad", $cantidad);
                $stmtDetalle->bindParam(":precio_unitario", $precio_unitario);
                $stmtDetalle->execute();
                
                $total += $subtotal;
            }
        }
        
        // Actualizar total de la compra
        $queryUpdateTotal = "UPDATE compras SET total = :total WHERE id_compra = :id_compra";
        $stmtUpdateTotal = $db->prepare($queryUpdateTotal);
        $stmtUpdateTotal->bindParam(":total", $total);
        $stmtUpdateTotal->bindParam(":id_compra", $id_compra);
        $stmtUpdateTotal->execute();
        
        $db->commit();
        
        header("Location: detail.php?id=$id_compra&msg=Compra registrada exitosamente");
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
    <title>Nueva Compra</title>
    <link rel="stylesheet" href="/SIGC/assets/css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>Nueva Compra</h2>
        
        <?php if (isset($mensaje)): ?>
            <div class="<?php echo $tipoMensaje; ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <form method="POST" id="formCompra">
            <div class="form-row">
                <div class="form-group">
                    <label>Proveedor *</label>
                    <select name="id_proveedor" required>
                        <option value="">Seleccionar proveedor</option>
                        <?php foreach ($proveedores as $proveedor): ?>
                            <option value="<?php echo $proveedor['id_proveedor']; ?>">
                                <?php echo htmlspecialchars($proveedor['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Número de Factura</label>
                    <input type="text" name="numero_factura" value="<?php echo $numeroFactura; ?>">
                </div>
                
                <div class="form-group">
                    <label>Fecha de Compra</label>
                    <input type="date" name="fecha_compra" value="<?php echo date('Y-m-d'); ?>" required>
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
                                            data-precio="<?php echo $producto['precio']; ?>">
                                        <?php echo htmlspecialchars($producto['nombre']); ?> - 
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
                <h3>Total: $<span id="total-compra">0.00</span></h3>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-primary">Registrar Compra</button>
                <a href="read.php" class="btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
    
    <script>
    // JavaScript similar al de ventas
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
        
        document.getElementById('total-compra').textContent = total.toFixed(2);
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('productos-container').addEventListener('change', function(e) {
            if (e.target.classList.contains('select-producto')) {
                const precio = e.target.options[e.target.selectedIndex].getAttribute('data-precio');
                e.target.closest('.producto-item').querySelector('.input-precio').value = precio;
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