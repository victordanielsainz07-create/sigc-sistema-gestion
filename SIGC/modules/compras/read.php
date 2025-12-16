<?php
require_once '../../config/db.php';
validarSesion();

$database = new Database();
$db = $database->getConnection();

// Filtros
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$id_cliente = isset($_GET['id_cliente']) ? intval($_GET['id_cliente']) : 0;

// Construir consulta
$where = "WHERE v.fecha_venta BETWEEN :fecha_inicio AND :fecha_fin";
$params = [
    ':fecha_inicio' => $fecha_inicio,
    ':fecha_fin' => $fecha_fin
];

if (!empty($estado)) {
    $where .= " AND v.estado = :estado";
    $params[':estado'] = $estado;
}

if ($id_cliente > 0) {
    $where .= " AND v.id_cliente = :id_cliente";
    $params[':id_cliente'] = $id_cliente;
}

// Obtener clientes para filtro
$queryClientes = "SELECT id_cliente, nombre FROM clientes ORDER BY nombre";
$stmtClientes = $db->prepare($queryClientes);
$stmtClientes->execute();
$clientes = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);

// Consulta de ventas
$query = "SELECT v.*, c.nombre as cliente_nombre 
          FROM ventas v 
          JOIN clientes c ON v.id_cliente = c.id_cliente 
          $where 
          ORDER BY v.fecha_venta DESC, v.id_venta DESC";
          
$stmt = $db->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$queryEstadisticas = "SELECT 
    COUNT(*) as total_ventas,
    SUM(total) as monto_total,
    SUM(CASE WHEN estado = 'completada' THEN total ELSE 0 END) as completadas,
    SUM(CASE WHEN estado = 'pendiente' THEN total ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'cancelada' THEN total ELSE 0 END) as canceladas
    FROM ventas 
    WHERE fecha_venta BETWEEN :fecha_inicio AND :fecha_fin";
    
$stmtEstadisticas = $db->prepare($queryEstadisticas);
$stmtEstadisticas->bindParam(':fecha_inicio', $fecha_inicio);
$stmtEstadisticas->bindParam(':fecha_fin', $fecha_fin);
$stmtEstadisticas->execute();
$estadisticas = $stmtEstadisticas->fetch(PDO::FETCH_ASSOC);
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
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> Ventas</h2>
                <a href="create.php" class="btn-primary">
                    <i class="fas fa-plus"></i> Nueva Venta
                </a>
            </div>
            
            <!-- Filtros -->
            <div class="filtros">
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
                            <select name="id_cliente">
                                <option value="0">Todos los clientes</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?php echo $cliente['id_cliente']; ?>"
                                        <?php echo ($id_cliente == $cliente['id_cliente']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cliente['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-filter"></i> Filtrar
                            </button>
                            <a href="read.php" class="btn-secondary">
                                <i class="fas fa-redo"></i> Limpiar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Total Ventas</h4>
                    <p class="stat-number"><?php echo $estadisticas['total_ventas']; ?></p>
                </div>
                <div class="stat-card">
                    <h4>Monto Total</h4>
                    <p class="stat-number">$<?php echo number_format($estadisticas['monto_total'], 2); ?></p>
                </div>
                <div class="stat-card">
                    <h4>Completadas</h4>
                    <p class="stat-number" style="color: #28a745;">$<?php echo number_format($estadisticas['completadas'], 2); ?></p>
                </div>
                <div class="stat-card">
                    <h4>Canceladas</h4>
                    <p class="stat-number" style="color: #dc3545;">$<?php echo number_format($estadisticas['canceladas'], 2); ?></p>
                </div>
            </div>
            
            <!-- Tabla de ventas -->
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Factura</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Subtotal</th>
                            <th>IGV</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Pago</th>
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
                            <td>$<?php echo number_format($venta['subtotal'], 2); ?></td>
                            <td>$<?php echo number_format($venta['igv'], 2); ?></td>
                            <td><strong>$<?php echo number_format($venta['total'], 2); ?></strong></td>
                            <td>
                                <?php 
                                $badgeClass = [
                                    'pendiente' => 'badge-warning',
                                    'completada' => 'badge-success',
                                    'cancelada' => 'badge-danger'
                                ][$venta['estado']] ?? 'badge-secondary';
                                ?>
                                <span class="badge <?php echo $badgeClass; ?>">
                                    <?php echo ucfirst($venta['estado']); ?>
                                </span>
                            </td>
                            <td><?php echo ucfirst($venta['tipo_pago']); ?></td>
                            <td class="actions">
                                <a href="detail.php?id=<?php echo $venta['id_venta']; ?>" 
                                   class="btn-info" title="Ver detalle">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="invoice.php?id=<?php echo $venta['id_venta']; ?>" 
                                   target="_blank" class="btn-secondary" title="Imprimir factura">
                                    <i class="fas fa-print"></i>
                                </a>
                                <?php if ($venta['estado'] == 'pendiente'): ?>
                                    <a href="update.php?id=<?php echo $venta['id_venta']; ?>" 
                                       class="btn-edit" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete.php?id=<?php echo $venta['id_venta']; ?>" 
                                       class="btn-delete" 
                                       onclick="return confirm('¿Cancelar esta venta?')"
                                       title="Cancelar">
                                        <i class="fas fa-ban"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (empty($ventas)): ?>
                    <div class="no-data">
                        <i class="fas fa-exclamation-circle"></i>
                        No se encontraron ventas en el período seleccionado
                    </div>
                <?php endif; ?>
                 </div>
        </div>
    </div>
</body>
</html>