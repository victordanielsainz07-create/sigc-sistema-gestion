<?php
require_once '../../config/db.php';
validarSesion();

$database = new Database();
$db = $database->getConnection();

$id_venta = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_venta == 0) {
    header("Location: read.php?msg=ID no válido");
    exit();
}

// Obtener venta
$queryVenta = "SELECT * FROM ventas WHERE id_venta = :id";
$stmtVenta = $db->prepare($queryVenta);
$stmtVenta->bindParam(":id", $id_venta);
$stmtVenta->execute();
$venta = $stmtVenta->fetch(PDO::FETCH_ASSOC);

if (!$venta) {
    header("Location: read.php?msg=Venta no encontrada");
    exit();
}

// Verificar que no esté ya cancelada
if ($venta['estado'] == 'cancelada') {
    header("Location: detail.php?id=$id_venta&msg=La venta ya está cancelada");
    exit();
}

// Procesar cancelación
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $db->beginTransaction();
        
        // 1. Restaurar stock de productos
        $queryDetalles = "SELECT * FROM detalle_venta WHERE id_venta = :id_venta";
        $stmtDetalles = $db->prepare($queryDetalles);
        $stmtDetalles->bindParam(":id_venta", $id_venta);
        $stmtDetalles->execute();
        $detalles = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($detalles as $detalle) {
            $queryRestaurar = "UPDATE productos SET stock = stock + :cantidad WHERE id_producto = :id_producto";
            $stmtRestaurar = $db->prepare($queryRestaurar);
            $stmtRestaurar->bindParam(":cantidad", $detalle['cantidad']);
            $stmtRestaurar->bindParam(":id_producto", $detalle['id_producto']);
            $stmtRestaurar->execute();
        }
        
        // 2. Marcar venta como cancelada
        $queryCancelar = "UPDATE ventas SET estado = 'cancelada' WHERE id_venta = :id_venta";
        $stmtCancelar = $db->prepare($queryCancelar);
        $stmtCancelar->bindParam(":id_venta", $id_venta);
        $stmtCancelar->execute();
        
        $db->commit();
        
        header("Location: read.php?msg=Venta cancelada exitosamente");
        exit();
        
    } catch(Exception $e) {
        $db->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cancelar Venta</title>
    <link rel="stylesheet" href="/SIGC/assets/css/style.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="card">
            <h2><i class="fas fa-ban"></i> Cancelar Venta</h2>
            
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="info-venta">
                <h3>Información de la Venta</h3>
                <table class="table-info">
                    <tr>
                        <th>Número de Factura:</th>
                        <td><?php echo $venta['numero_factura']; ?></td>
                    </tr>
                    <tr>
                        <th>Fecha:</th>
                        <td><?php echo date('d/m/Y', strtotime($venta['fecha_venta'])); ?></td>
                    </tr>
                    <tr>
                        <th>Total:</th>
                        <td>$<?php echo number_format($venta['total'], 2); ?></td>
                    </tr>
                    <tr>
                        <th>Estado:</th>
                        <td>
                            <span class="badge badge-<?php echo $venta['estado']; ?>">
                                <?php echo ucfirst($venta['estado']); ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="advertencia">
                <h4><i class="fas fa-exclamation-triangle"></i> ¡Atención!</h4>
                <p>Al cancelar esta venta:</p>
                <ul>
                    <li>Se restaurará el stock de todos los productos vendidos</li>
                    <li>La venta se marcará como <strong>cancelada</strong></li>
                    <li>Se mantendrá un registro de la operación</li>
                    <li>Esta acción no se puede deshacer</li>
                </ul>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="motivo">Motivo de cancelación:</label>
                    <textarea name="motivo" id="motivo" rows="3" 
                              placeholder="Especifique el motivo de la cancelación..." required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="confirmar">
                        <input type="checkbox" name="confirmar" id="confirmar" required>
                        Confirmo que deseo cancelar esta venta
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-delete">
                        <i class="fas fa-ban"></i> Confirmar Cancelación
                    </button>
                    <a href="detail.php?id=<?php echo $id_venta; ?>" class="btn-secondary">
                        <i class="fas fa-times"></i> Volver
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>