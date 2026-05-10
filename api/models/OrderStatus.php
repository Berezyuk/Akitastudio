<?php
// api/models/OrderStatus.php

require_once __DIR__ . '/../config/database.php';

class OrderStatus {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function getAll() {
        $query = "SELECT status_id, name FROM order_statuses ORDER BY status_id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}