<?php
require_once '../../config/db.php';
validarSesion();

$database = new Database();
$db = $database->getConnection();

// Filtros
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$cliente = isset($_GET['cliente']) ? $_GET['cliente'] : '';

// Construir consulta con filtros
$where = "WHERE v.fecha_venta BETWEEN :fecha_inicio AND :fecha_fin";
$params = [
    ':fecha_inicio' => $fecha_inicio,
    ':fecha_fin' => $fecha_fin
];

if (!empty($estado)) {
    $where .= " AND v.estado = :estado";
    $params[':estado'] = $estado;
}

if (!empty($cliente)) {
    $where .= " AND c.nombre LIKE :cliente";
    $params[':cliente'] = "%$cliente%";
}

// Consulta principal
$query = "SELECT v.*, c.nombre as cliente_nombre 
          FROM ventas v 
          INNER JOIN clientes c ON v.id_cliente = c.id_cliente 
          $where 
          ORDER BY v.fecha_venta DESC";
          
$stmt = $db->prepare($query);
foreach ($params as $key => &$val) {
    $stmt->bindParam($key, $val);
}
$stmt->execute();
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totales
$queryTotal = "SELECT 
               COUNT(*) as total_ventas,
               SUM(total) as monto_total,
               AVG(total) as promedio_venta
               FROM ventas v $where";
$stmtTotal = $db->prepare($queryTotal);
foreach ($params as $key => &$val) {
    $stmtTotal->bindParam($key, $val);
}
$stmtTotal->execute();
$totales = $stmtTotal->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lista de Ventas</title>
    <link rel="stylesheet" href="/SIGC/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>Ventas</h2>
        
        <!-- Filtros -->
        <div class="card">
            <h3>Filtros</h3>
            <form method="GET" class="form-filtros">
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>">
                    </div>
                    <div class="form-group">
                        <label>Fecha Fin</label>
                        <input type="date" name="fecha_fin" value="<?php echo $fecha_fin; ?>">
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="estado">
                            <option value="">Todos</option>
                            <option value="pendiente" <?php echo ($estado == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="completada" <?php echo ($estado == 'completada') ? 'selected' : ''; ?>>Completada</option>
                            <option value="cancelada" <?php echo ($estado == 'cancelada') ? 'selected' : ''; ?>>Cancelada</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Cliente</label>
                        <input type="text" name="cliente" value="<?php echo htmlspecialchars($cliente); ?>" placeholder="Nombre del cliente">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Filtrar</button>
                    <a href="read.php" class="btn-secondary">Limpiar</a>
                </div>
            </form>
        </div>
        
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <h4>Total Ventas</h4>
                <p class="stat-number"><?php echo $totales['total_ventas']; ?></p>
            </div>
            <div class="stat-card">
                <h4>Monto Total</h4>
                <p class="stat-number">$<?php echo number_format($totales['monto_total'], 2); ?></p>
            </div>
            <div class="stat-card">
                <h4>Promedio por Venta</h4>
                <p class="stat-number">$<?php echo number_format($totales['promedio_venta'], 2); ?></p>
            </div>
        </div>
        
        <!-- Lista de ventas -->
        <div class="card">
            <div class="card-header">
                <h3>Lista de Ventas</h3>
                <a href="create.php" class="btn-primary">Nueva Venta</a>
            </div>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Factura</th>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventas as $venta): ?>
                    <tr>
                        <td><?php echo $venta['id_venta']; ?></td>
                        <td><?php echo $venta['numero_factura']; ?></td>
                        <td><?php echo htmlspecialchars($venta['cliente_nombre']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($venta['fecha_venta'])); ?></td>
                        <td>$<?php echo number_format($venta['total'], 2); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $venta['estado']; ?>">
                                <?php echo ucfirst($venta['estado']); ?>
                            </span>
                        </td>
                        <td class="actions">
                            <a href="detail.php?id=<?php echo $venta['id_venta']; ?>" class="btn-info" title="Ver">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="update.php?id=<?php echo $venta['id_venta']; ?>" class="btn-edit" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="delete.php?id=<?php echo $venta['id_venta']; ?>" class="btn-delete" 
                               onclick="return confirm('¿Anular venta?')" title="Anular">
                                <i class="fas fa-ban"></i>
                            </a>
                            <a href="invoice.php?id=<?php echo $venta['id_venta']; ?>" target="_blank" class="btn-secondary" title="Imprimir">
                                <i class="fas fa-print"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <style>
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-pendiente {
            background-color: #ffc107;
            color: #000;
        }
        .badge-completada {
            background-color: #28a745;
            color: #fff;
        }
        .badge-cancelada {
            background-color: #dc3545;
            color: #fff;
        }
    </style>
</body>
</html>