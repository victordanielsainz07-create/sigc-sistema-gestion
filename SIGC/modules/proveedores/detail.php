<?php
require_once '../../config/db.php';
validarSesion();

$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id == 0) {
    header("Location: read.php?msg=ID no válido");
    exit();
}

$query = "SELECT * FROM proveedores WHERE id_proveedor = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$proveedor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proveedor) {
    header("Location: read.php?msg=Proveedor no encontrado");
    exit();
}

// Obtener productos de este proveedor
$queryProductos = "SELECT * FROM productos WHERE id_proveedor = :id";
$stmtProductos = $db->prepare($queryProductos);
$stmtProductos->bindParam(':id', $id);
$stmtProductos->execute();
$productos = $stmtProductos->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle Proveedor</title>
    <link rel="stylesheet" href="/SIGC/assets/css/style.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container">
        <h2>Detalle del Proveedor</h2>

        <div class="card">
            <h3>Información General</h3>
            <table class="table-info">
                <tr>
                    <th>ID:</th>
                    <td><?php echo $proveedor['id_proveedor']; ?></td>
                </tr>
                <tr>
                    <th>Nombre:</th>
                    <td><?php echo htmlspecialchars($proveedor['nombre']); ?></td>
                </tr>
                <tr>
                    <th>RUC:</th>
                    <td><?php echo htmlspecialchars($proveedor['ruc']); ?></td>
                </tr>
                <tr>
                    <th>Dirección:</th>
                    <td><?php echo htmlspecialchars($proveedor['direccion']); ?></td>
                </tr>
                <tr>
                    <th>Teléfono:</th>
                    <td><?php echo htmlspecialchars($proveedor['telefono']); ?></td>
                </tr>
                <tr>
                    <th>Email:</th>
                    <td><?php echo htmlspecialchars($proveedor['email']); ?></td>
                </tr>
                <tr>
                    <th>Fecha de Registro:</th>
                    <td><?php echo date('d/m/Y', strtotime($proveedor['fecha_registro'])); ?></td>
                </tr>
            </table>
        </div>

        <div class="card">
            <h3>Productos de este Proveedor</h3>
            <?php if (count($productos) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Producto</th>
                            <th>Precio</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $producto): ?>
                        <tr>
                            <td><?php echo $producto['id_producto']; ?></td>
                            <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                            <td>$<?php echo number_format($producto['precio'], 2); ?></td>
                            <td><?php echo $producto['stock']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No hay productos registrados para este proveedor.</p>
            <?php endif; ?>
        </div>

        <a href="read.php" class="btn-secondary">Volver a la lista</a>
    </div>
</body>
</html>