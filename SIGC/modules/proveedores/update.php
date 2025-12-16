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

// Obtener proveedor actual
$query = "SELECT * FROM proveedores WHERE id_proveedor = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$proveedor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proveedor) {
    header("Location: read.php?msg=Proveedor no encontrado");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = sanitizar($_POST['nombre']);
    $direccion = sanitizar($_POST['direccion']);
    $telefono = sanitizar($_POST['telefono']);
    $email = sanitizar($_POST['email']);
    $ruc = sanitizar($_POST['ruc']);

    // Verificar si el RUC ya existe en otro proveedor
    $query = "SELECT id_proveedor FROM proveedores WHERE ruc = :ruc AND id_proveedor != :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':ruc', $ruc);
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $error = "El RUC ya está registrado en otro proveedor.";
    } else {
        $query = "UPDATE proveedores SET nombre = :nombre, direccion = :direccion, telefono = :telefono, email = :email, ruc = :ruc WHERE id_proveedor = :id";
        $stmt = $db->prepare($query);

        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':direccion', $direccion);
        $stmt->bindParam(':telefono', $telefono);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':ruc', $ruc);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            $success = "Proveedor actualizado exitosamente.";
            // Actualizar los datos mostrados
            $proveedor = array_merge($proveedor, $_POST);
        } else {
            $error = "Error al actualizar el proveedor.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Proveedor</title>
    <link rel="stylesheet" href="/SIGC/assets/css/style.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container">
        <h2>Editar Proveedor</h2>

        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Nombre *</label>
                <input type="text" name="nombre" value="<?php echo htmlspecialchars($proveedor['nombre']); ?>" required>
            </div>

            <div class="form-group">
                <label>RUC *</label>
                <input type="text" name="ruc" value="<?php echo htmlspecialchars($proveedor['ruc']); ?>" required pattern="[0-9]{11}">
            </div>

            <div class="form-group">
                <label>Dirección</label>
                <textarea name="direccion" rows="3"><?php echo htmlspecialchars($proveedor['direccion']); ?></textarea>
            </div>

            <div class="form-group">
                <label>Teléfono</label>
                <input type="text" name="telefono" value="<?php echo htmlspecialchars($proveedor['telefono']); ?>">
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($proveedor['email']); ?>">
            </div>

            <button type="submit" class="btn-primary">Actualizar</button>
            <a href="read.php" class="btn-secondary">Cancelar</a>
        </form>
    </div>
</body>
</html>