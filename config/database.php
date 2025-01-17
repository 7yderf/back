<?php
namespace config;

use PDO;
use PDOException;

class Database {
    private $host = 'db';   // Cambia esto por tu host si es necesario
    private $db_name = 'my_database';
    private $username = 'my_user';
    private $password = 'my_password';
    private $conn;

    public function connect() {
        $this->conn = null;

        try {
            $this->conn = new PDO('mysql:host=' . $this->host . ';dbname=' . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            echo 'Error en la conexión: ' . $e->getMessage();
        }

        return $this->conn;
    }
}
