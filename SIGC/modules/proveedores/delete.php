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

// Verificar si el proveedor tiene productos asociados
$query = "SELECT COUNT(*) as total FROM productos WHERE id_proveedor = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$tieneProductos = $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;

// Verificar si el proveedor tiene compras asociadas
$query = "SELECT COUNT(*) as total FROM compras WHERE id_proveedor = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$tieneCompras = $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['confirmar'])) {
        // Si tiene productos o compras, no eliminar, solo marcar como inactivo (si tuviéramos un campo)
        // Pero como no tenemos campo activo, no eliminaremos si tiene dependencias.
        if ($tieneProductos || $tieneCompras) {
            $error = "No se puede eliminar el proveedor porque tiene productos o compras asociadas.";
        } else {
            $query = "DELETE FROM proveedores WHERE id_proveedor = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            if ($stmt->execute()) {
                header("Location: read.php?msg=Proveedor eliminado");
                exit();
            } else {
                $error = "Error al eliminar el proveedor.";
            }
        }
    }
}

// Obtener información del proveedor
$query = "SELECT * FROM proveedores WHERE id_proveedor = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Eliminar Proveedor</title>
    <link rel="stylesheet" href="/SIGC/assets/css/style.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container">
        <h2>Eliminar Proveedor</h2>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="info">
            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($proveedor['nombre']); ?></p>
            <p><strong>RUC:</strong> <?php echo htmlspecialchars($proveedor['ruc']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($proveedor['email']); ?></p>
        </div>

        <?php if ($tieneProductos || $tieneCompras): ?>
            <div class="warning">
                <p>Este proveedor tiene <?php echo $tieneProductos ? 'productos' : ''; ?> <?php echo ($tieneProductos && $tieneCompras) ? 'y' : ''; ?> <?php echo $tieneCompras ? 'compras' : ''; ?> asociados. No se puede eliminar.</p>
            </div>
            <a href="read.php" class="btn-secondary">Volver</a>
        <?php else: ?>
            <form method="POST">
                <p>¿Está seguro de eliminar este proveedor?</p>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="confirmar" required> Confirmar eliminación
                    </label>
                </div>
                <button type="submit" class="btn-delete">Eliminar</button>
                <a href="read.php" class="btn-secondary">Cancelar</a>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>