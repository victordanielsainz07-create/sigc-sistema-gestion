<?php
require_once '../../config/db.php';
validarSesion();

$database = new Database();
$db = $database->getConnection();

// Filtros
$filtro = isset($_GET['filtro']) ? sanitizar($_GET['filtro']) : '';

$where = "";
$params = [];

if (!empty($filtro)) {
    $where = "WHERE nombre LIKE :filtro OR ruc LIKE :filtro OR email LIKE :filtro";
    $params[':filtro'] = "%$filtro%";
}

// Consulta con filtros
$query = "SELECT * FROM proveedores $where ORDER BY nombre";
$stmt = $db->prepare($query);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Proveedores</title>
    <link rel="stylesheet" href="/SIGC/assets/css/style.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container">
        <h2>Proveedores</h2>

        <div class="actions">
            <a href="create.php" class="btn-primary">Nuevo Proveedor</a>
        </div>

        <!-- Formulario de filtro -->
        <form method="GET" class="form-filtro">
            <input type="text" name="filtro" placeholder="Buscar..." value="<?php echo htmlspecialchars($filtro); ?>">
            <button type="submit">Buscar</button>
        </form>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>RUC</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($proveedores as $proveedor): ?>
                <tr>
                    <td><?php echo $proveedor['id_proveedor']; ?></td>
                    <td><?php echo htmlspecialchars($proveedor['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($proveedor['ruc']); ?></td>
                    <td><?php echo htmlspecialchars($proveedor['telefono']); ?></td>
                    <td><?php echo htmlspecialchars($proveedor['email']); ?></td>
                    <td class="actions">
                        <a href="update.php?id=<?php echo $proveedor['id_proveedor']; ?>" class="btn-edit">Editar</a>
                        <a href="delete.php?id=<?php echo $proveedor['id_proveedor']; ?>" class="btn-delete" onclick="return confirm('¿Eliminar?')">Eliminar</a>
                        <a href="detail.php?id=<?php echo $proveedor['id_proveedor']; ?>" class="btn-info">Detalle</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>