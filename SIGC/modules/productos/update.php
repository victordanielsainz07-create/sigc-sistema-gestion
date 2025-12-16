<?php
require_once '../../config/db.php';
validarSesion();

$database = new Database();
$db = $database->getConnection();

// Obtener el ID del producto a editar
$id = isset($_GET['id']) ? $_GET['id'] : die('ERROR: ID no especificado.');

// Obtener los datos actuales del producto
$query = "SELECT * FROM productos WHERE id_producto = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $id);
$stmt->execute();
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    die('Producto no encontrado.');
}

// Obtener lista de proveedores
$queryProveedores = "SELECT id_proveedor, nombre FROM proveedores ORDER BY nombre";
$stmtProveedores = $db->prepare($queryProveedores);
$stmtProveedores->execute();
$proveedores = $stmtProveedores->fetchAll(PDO::FETCH_ASSOC);

// Inicializar variables
$nombre = $producto['nombre'];
$descripcion = $producto['descripcion'];
$precio = $producto['precio'];
$stock = $producto['stock'];
$id_proveedor = $producto['id_proveedor'];
$categoria = $producto['categoria'];
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recibir y sanitizar los datos del formulario
    $nombre = sanitizar($_POST['nombre']);
    $descripcion = sanitizar($_POST['descripcion']);
    $precio = sanitizar($_POST['precio']);
    $stock = sanitizar($_POST['stock']);
    $id_proveedor = sanitizar($_POST['id_proveedor']);
    $categoria = sanitizar($_POST['categoria']);

    if (empty($nombre) || empty($precio)) {
        $error = "Nombre y precio son campos obligatorios.";
    } else {
        // Actualizar en la base de datos
        $query = "UPDATE productos 
                  SET nombre = :nombre, descripcion = :descripcion, precio = :precio, 
                      stock = :stock, id_proveedor = :id_proveedor, categoria = :categoria 
                  WHERE id_producto = :id";
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':precio', $precio);
        $stmt->bindParam(':stock', $stock);
        $stmt->bindParam(':id_proveedor', $id_proveedor);
        $stmt->bindParam(':categoria', $categoria);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            header("Location: read.php?msg=Producto actualizado exitosamente");
            exit();
        } else {
            $error = "Error al actualizar el producto.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto</title>
    <link rel="stylesheet" href="/SIGC/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>Editar Producto</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="nombre">Nombre *</label>
                <input type="text" id="nombre" name="nombre" value="<?php echo $nombre; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion" rows="3"><?php echo $descripcion; ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="precio">Precio *</label>
                <input type="number" id="precio" name="precio" step="0.01" min="0" value="<?php echo $precio; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="stock">Stock</label>
                <input type="number" id="stock" name="stock" min="0" value="<?php echo $stock; ?>">
            </div>
            
            <div class="form-group">
                <label for="id_proveedor">Proveedor</label>
                <select id="id_proveedor" name="id_proveedor">
                    <option value="">-- Seleccione un proveedor --</option>
                    <?php foreach ($proveedores as $proveedor): ?>
                        <option value="<?php echo $proveedor['id_proveedor']; ?>" 
                            <?php echo ($id_proveedor == $proveedor['id_proveedor']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($proveedor['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="categoria">Categoría</label>
                <input type="text" id="categoria" name="categoria" value="<?php echo $categoria; ?>">
            </div>
            
            <button type="submit" class="btn-primary">Actualizar Producto</button>
            <a href="read.php" class="btn-secondary">Cancelar</a>
        </form>
    </div>
</body>
</html>