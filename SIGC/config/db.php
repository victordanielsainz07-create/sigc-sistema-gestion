<?php
class Database {
     private $host = "localhost";
    private $db_name = "sigc_db";
    private $username = "root";
    private $password = "vicdan";      
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }
        return $this->conn;
    }
}


function validarSesion() {
    return true;
}
function validarSesion() {
    session_start();
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: /SIGC/index.php");
        exit();
    }
}


function sanitizar($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>