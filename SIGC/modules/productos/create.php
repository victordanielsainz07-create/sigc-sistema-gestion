<?php
require_once '../../config/db.php';
validarSesion(); // Si es que tienes una función para validar sesión

$database = new Database();
$db = $database->getConnection();

// Inicializar variables
$nombre = $descripcion = $precio = $stock = $id_proveedor = $categoria = '';
$error = '';

// Obtener lista de proveedores para el select
$queryProveedores = "SELECT id_proveedor, nombre FROM proveedores ORDER BY nombre";
$stmtProveedores = $db->prepare($queryProveedores);
$stmtProveedores->execute();
$proveedores = $stmtProveedores->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recibir y sanitizar los datos del formulario
    $nombre = sanitizar($_POST['nombre']);
    $descripcion = sanitizar($_POST['descripcion']);
    $precio = sanitizar($_POST['precio']);
    $stock = sanitizar($_POST['stock']);
    $id_proveedor = sanitizar($_POST['id_proveedor']);
    $categoria = sanitizar($_POST['categoria']);

    // Validaciones básicas
    if (empty($nombre) || empty($precio)) {
        $error = "Nombre y precio son campos obligatorios.";
    } else {
        // Insertar en la base de datos
        $query = "INSERT INTO productos (nombre, descripcion, precio, stock, id_proveedor, categoria) 
                  VALUES (:nombre, :descripcion, :precio, :stock, :id_proveedor, :categoria)";
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':precio', $precio);
        $stmt->bindParam(':stock', $stock);
        $stmt->bindParam(':id_proveedor', $id_proveedor);
        $stmt->bindParam(':categoria', $categoria);

        if ($stmt->execute()) {
            header("Location: read.php?msg=Producto creado exitosamente");
            exit();
        } else {
            $error = "Error al crear el producto.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Producto</title>
    <link rel="stylesheet" href="/SIGC/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>Crear Nuevo Producto</h2>
        
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
                        <option value="<?php echo $proveedor['id_proveedor']; ?>" <?php echo ($id_proveedor == $proveedor['id_proveedor']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($proveedor['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="categoria">Categoría</label>
                <input type="text" id="categoria" name="categoria" value="<?php echo $categoria; ?>">
            </div>
            
            <button type="submit" class="btn-primary">Guardar Producto</button>
            <a href="read.php" class="btn-secondary">Cancelar</a>
        </form>
    </div>
</body>
</html>
