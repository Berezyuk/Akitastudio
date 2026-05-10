<?php
// api/models/ServiceCategory.php

require_once __DIR__ . '/../config/database.php';

class ServiceCategory {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function getAll() {
        $query = "SELECT category_id, name, sort_order FROM service_categories ORDER BY sort_order";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function create($name, $sortOrder) {
        $query = "INSERT INTO service_categories (name, sort_order) VALUES (:name, :sort_order)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':sort_order', $sortOrder);
        if($stmt->execute()) {
            return ['success' => true, 'category_id' => $this->conn->lastInsertId()];
        }
        return ['error' => 'Ошибка создания категории'];
    }
    
    public function update($id, $name, $sortOrder) {
        $query = "UPDATE service_categories SET name = :name, sort_order = :sort_order WHERE category_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':sort_order', $sortOrder);
        return ['success' => $stmt->execute()];
    }
    
    public function delete($id) {
        $query = "DELETE FROM service_categories WHERE category_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return ['success' => $stmt->execute()];
    }
}