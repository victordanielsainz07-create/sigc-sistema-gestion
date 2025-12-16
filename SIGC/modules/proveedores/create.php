<?php
require_once '../../config/db.php';
validarSesion();

$database = new Database();
$db = $database->getConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = sanitizar($_POST['nombre']);
    $direccion = sanitizar($_POST['direccion']);
    $telefono = sanitizar($_POST['telefono']);
    $email = sanitizar($_POST['email']);
    $ruc = sanitizar($_POST['ruc']);

    // Verificar si el RUC ya existe
    $query = "SELECT id_proveedor FROM proveedores WHERE ruc = :ruc";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':ruc', $ruc);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $error = "El RUC ya está registrado.";
    } else {
        $query = "INSERT INTO proveedores (nombre, direccion, telefono, email, ruc) 
                  VALUES (:nombre, :direccion, :telefono, :email, :ruc)";
        $stmt = $db->prepare($query);

        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':direccion', $direccion);
        $stmt->bindParam(':telefono', $telefono);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':ruc', $ruc);

        if ($stmt->execute()) {
            $success = "Proveedor creado exitosamente.";
        } else {
            $error = "Error al crear el proveedor.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Proveedor</title>
    <link rel="stylesheet" href="/SIGC/assets/css/style.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container">
        <h2>Nuevo Proveedor</h2>

        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Nombre *</label>
                <input type="text" name="nombre" required>
            </div>

            <div class="form-group">
                <label>RUC *</label>
                <input type="text" name="ruc" required pattern="[0-9]{11}" title="11 dígitos">
            </div>

            <div class="form-group">
                <label>Dirección</label>
                <textarea name="direccion" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label>Teléfono</label>
                <input type="text" name="telefono">
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email">
            </div>

            <button type="submit" class="btn-primary">Guardar</button>
            <a href="read.php" class="btn-secondary">Cancelar</a>
        </form>
    </div>
</body>
</html>