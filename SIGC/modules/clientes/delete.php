<?php
require_once '../../config/db.php';
validarSesion();

if (!isset($_GET['id'])) {
    die("ID inválido");
}

$id = intval($_GET['id']);

$database = new Database();
$db = $database->getConnection();

$query = "DELETE FROM clientes WHERE id_cliente = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(":id", $id);

if ($stmt->execute()) {
    header("Location: read.php?msg=deleted");
    exit();
} else {
    echo "<div class='error'>Error al eliminar cliente</div>";
}