<?php
// config/database.php

class Database {
    private $host = 'localhost';
    private $port = '5433';
    private $dbname = 'AkitaStudio';
    private $user = 'postgres';
    private $password = '271073324'; 
    
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->dbname}";
            $this->conn = new PDO($dsn, $this->user, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
            exit;
        }
        
        return $this->conn;
    }
}