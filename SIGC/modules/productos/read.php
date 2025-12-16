<?php
require_once '../../config/db.php';
validarSesion();

$database = new Database();
$db = $database->getConnection();

$query = "SELECT p.*, pr.nombre as proveedor_nombre 
          FROM productos p 
          LEFT JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor 
          ORDER BY p.fecha_creacion DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Manejar mensajes de éxito
$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lista de Productos</title>
    <link rel="stylesheet" href="/SIGC/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>Productos</h2>
        
        <?php if ($msg): ?>
            <div class="success"><?php echo $msg; ?></div>
        <?php endif; ?>
        
        <div class="actions">
            <a href="create.php" class="btn-primary">
                <i class="fas fa-plus"></i> Nuevo Producto
            </a>
        </div>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Precio</th>
                    <th>Stock</th>
                    <th>Proveedor</th>
                    <th>Categoría</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $producto): ?>
                <tr>
                    <td><?php echo $producto['id_producto']; ?></td>
                    <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($producto['descripcion']); ?></td>
                    <td>$<?php echo number_format($producto['precio'], 2); ?></td>
                    <td><?php echo $producto['stock']; ?></td>
                    <td><?php echo htmlspecialchars($producto['proveedor_nombre']); ?></td>
                    <td><?php echo htmlspecialchars($producto['categoria']); ?></td>
                    <td class="actions">
                        <a href="update.php?id=<?php echo $producto['id_producto']; ?>" 
                           class="btn-edit" title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="delete.php?id=<?php echo $producto['id_producto']; ?>" 
                           class="btn-delete" 
                           onclick="return confirm('¿Está seguro de eliminar este producto?');" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>