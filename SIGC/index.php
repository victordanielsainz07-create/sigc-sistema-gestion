<?php
session_start();

// Cargar configuración de base de datos
require_once __DIR__ . '/config/db.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión Comercial</title>
    <link rel="stylesheet" href="/SIGC/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="header">
        <nav class="navbar">
            <div class="logo">SIGC</div>
            <ul class="nav-links">
                <li><a href="/SIGC/modules/clientes/read.php">Clientes</a></li>
                <li><a href="/SIGC/modules/proveedores/read.php">Proveedores</a></li>
                <li><a href="/SIGC/modules/productos/read.php">Productos</a></li>
                <li><a href="/SIGC/modules/ventas/read.php">Ventas</a></li>
                <li><a href="/SIGC/modules/compras/read.php">Compras</a></li>
            </ul>
        </nav>
    </header>
    
    <div class="container">
        <h1>Sistema de Gestión Comercial</h1>
        
        <div class="dashboard-grid">
            <?php
            try {
                $database = new Database();
                $db = $database->getConnection();
            ?>
            
            <div class="card">
                <h3><i class="fas fa-users"></i> Clientes</h3>
                <p class="number">
                    <?php
                    $stmt = $db->query("SELECT COUNT(*) FROM clientes");
                    echo $stmt->fetchColumn();
                    ?>
                </p>
                <a href="modules/clientes/read.php">Ver todos</a>
            </div>
            
            <div class="card">
                <h3><i class="fas fa-boxes"></i> Productos</h3>
                <p class="number">
                    <?php
                    $stmt = $db->query("SELECT COUNT(*) FROM productos");
                    echo $stmt->fetchColumn();
                    ?>
                </p>
                <a href="modules/productos/read.php">Ver todos</a>
            </div>
            
            <div class="card">
                <h3><i class="fas fa-shopping-cart"></i> Ventas Hoy</h3>
                <p class="number">
                    <?php
                    $stmt = $db->query("SELECT COUNT(*) FROM ventas WHERE DATE(fecha_venta) = CURDATE()");
                    echo $stmt->fetchColumn();
                    ?>
                </p>
                <a href="modules/ventas/read.php">Ver ventas</a>
            </div>
            
            <div class="card">
                <h3><i class="fas fa-chart-line"></i> Stock Bajo</h3>
                <p class="number" style="color: #f44336;">
                    <?php
                    $stmt = $db->query("SELECT COUNT(*) FROM productos WHERE stock < 10");
                    echo $stmt->fetchColumn();
                    ?>
                </p>
                <a href="modules/productos/read.php?filter=low_stock">Ver productos</a>
            </div>
            
            <?php
            } catch (Exception $e) {
                echo '<div class="error-message">';
                echo '<p>Error al cargar el dashboard. Por favor, verifica la configuración de la base de datos.</p>';
                if (getenv('ENV') !== 'production') {
                    echo '<p><small>' . htmlspecialchars($e->getMessage()) . '</small></p>';
                }
                echo '</div>';
            }
            ?>
        </div>
        
        <div class="quick-actions">
            <h2>Acciones Rápidas</h2>
            <div class="action-buttons">
                <a href="modules/ventas/create.php" class="btn-primary">
                    <i class="fas fa-plus"></i> Nueva Venta
                </a>
                <a href="modules/clientes/create.php" class="btn-primary">
                    <i class="fas fa-user-plus"></i> Nuevo Cliente
                </a>
                <a href="modules/productos/create.php" class="btn-primary">
                    <i class="fas fa-box"></i> Nuevo Producto
                </a>
            </div>
        </div>
    </div>
</body>
</html>