<?php
require_once '../../config/db.php';
validarSesion();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Cliente</title>
    <link rel="stylesheet" href="/SIGC/assets/css/style.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>Nuevo Cliente</h2>
        
        <?php
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $database = new Database();
            $db = $database->getConnection();
            
            $nombre = sanitizar($_POST['nombre']);
            $direccion = sanitizar($_POST['direccion']);
            $telefono = sanitizar($_POST['telefono']);
            $email = sanitizar($_POST['email']);
            
            $query = "INSERT INTO clientes (nombre, direccion, telefono, email) 
                     VALUES (:nombre, :direccion, :telefono, :email)";
            $stmt = $db->prepare($query);
            
            $stmt->bindParam(":nombre", $nombre);
            $stmt->bindParam(":direccion", $direccion);
            $stmt->bindParam(":telefono", $telefono);
            $stmt->bindParam(":email", $email);
            
            if ($stmt->execute()) {
                echo "<div class='success'>Cliente creado exitosamente</div>";
            } else {
                echo "<div class='error'>Error al crear cliente</div>";
            }
        }
        ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Nombre:</label>
                <input type="text" name="nombre" required>
            </div>
            
            <div class="form-group">
                <label>Dirección:</label>
                <textarea name="direccion" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label>Teléfono:</label>
                <input type="text" name="telefono">
            </div>
            
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email">
            </div>
            
            <button type="submit">Guardar</button>
            <a href="read.php" class="btn-cancel">Cancelar</a>
        </form>
    </div>
</body>
</html>