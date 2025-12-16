<?php
require_once '../../config/db.php';
validarSesion();

$database = new Database();
$db = $database->getConnection();

// Obtener el ID del producto a eliminar
$id = isset($_GET['id']) ? $_GET['id'] : die('ERROR: ID no especificado.');

// Verificar si el producto existe
$query = "SELECT id_producto FROM productos WHERE id_producto = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $id);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    die('Producto no encontrado.');
}

// Si se confirma la eliminación
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Eliminar el producto
    $query = "DELETE FROM productos WHERE id_producto = ?";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $id);
    
    if ($stmt->execute()) {
        header("Location: read.php?msg=Producto eliminado exitosamente");
        exit();
    } else {
        die('Error al eliminar el producto.');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Eliminar Producto</title>
    <link rel="stylesheet" href="/SIGC/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>Eliminar Producto</h2>
        <p>¿Está seguro de que desea eliminar este producto?</p>
        
        <form method="POST" action="">
            <button type="submit" class="btn-delete">Sí, eliminar</button>
            <a href="read.php" class="btn-secondary">Cancelar</a>
        </form>
    </div>
</body>
</html>