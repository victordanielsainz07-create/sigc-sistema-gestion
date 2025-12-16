<?php
require_once '../../config/db.php';
validarSesion();

$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['id'])) {
    die("ID inválido");
}

$id = intval($_GET['id']);

$query = "SELECT * FROM clientes WHERE id_cliente = :id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(":id", $id);
$stmt->execute();
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    die("Cliente no encontrado");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $nombre = sanitizar($_POST['nombre']);
    $direccion = sanitizar($_POST['direccion']);
    $telefono = sanitizar($_POST['telefono']);
    $email = sanitizar($_POST['email']);

    $queryUpdate = "UPDATE clientes 
                    SET nombre = :nombre,
                        direccion = :direccion,
                        telefono = :telefono,
                        email = :email
                    WHERE id_cliente = :id";

    $stmtUpdate = $db->prepare($queryUpdate);
    $stmtUpdate->bindParam(":nombre", $nombre);
    $stmtUpdate->bindParam(":direccion", $direccion);
    $stmtUpdate->bindParam(":telefono", $telefono);
    $stmtUpdate->bindParam(":email", $email);
    $stmtUpdate->bindParam(":id", $id);

    if ($stmtUpdate->execute()) {
        $mensaje = "<div class='success'>Cliente actualizado exitosamente</div>";
    } else {
        $mensaje = "<div class='error'>Error al actualizar el cliente</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Cliente</title>
    <link rel="stylesheet" href="/SIGC/assets/css/style.css">
</head>
<body>

<?php include '../../includes/header.php'; ?>

<div class="container">
    <h2>Editar Cliente</h2>

    <?php if (isset($mensaje)) echo $mensaje; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label>Nombre:</label>
            <input type="text" name="nombre" value="<?php echo htmlspecialchars($cliente['nombre']); ?>" required>
        </div>

        <div class="form-group">
            <label>Dirección:</label>
            <textarea name="direccion" rows="3"><?php echo htmlspecialchars($cliente['direccion']); ?></textarea>
        </div>

        <div class="form-group">
            <label>Teléfono:</label>
            <input type="text" name="telefono" value="<?php echo htmlspecialchars($cliente['telefono']); ?>">
        </div>

        <div class="form-group">
            <label>Email:</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($cliente['email']); ?>">
        </div>

        <button type="submit">Actualizar</button>
        <a href="read.php" class="btn-cancel">Cancelar</a>
    </form>
</div>

</body>
</html>