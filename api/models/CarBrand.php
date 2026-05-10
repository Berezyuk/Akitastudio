<?php
require_once __DIR__ . '/../config/database.php';

class CarBrand {
    private $conn;
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function getAll() {
        $query = "SELECT brand_id, name FROM car_brands ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function findOrCreateByName($name) {
        $name = trim($name);
        if (empty($name)) {
            return ['error' => 'Название марки не может быть пустым'];
        }
        // Поиск существующей
        $query = "SELECT brand_id FROM car_brands WHERE name = :name";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        if ($row = $stmt->fetch()) {
            return ['success' => true, 'brand_id' => $row['brand_id']];
        }
        // Создание новой
        $query = "INSERT INTO car_brands (name) VALUES (:name) RETURNING brand_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        $brandId = $stmt->fetchColumn();
        return ['success' => true, 'brand_id' => $brandId];
    }
}