<?php
require_once __DIR__ . '/../config/database.php';

class CarModel {
    private $conn;
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function getByBrand($brandId) {
        $query = "SELECT model_id, name FROM car_models WHERE brand_id = :brand_id ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':brand_id', $brandId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByBrandName($brandName) {
        $query = "SELECT cm.model_id, cm.name
                  FROM car_models cm
                  JOIN car_brands cb ON cm.brand_id = cb.brand_id
                  WHERE cb.name ILIKE :brand
                  ORDER BY cm.name
                  LIMIT 50";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':brand', $brandName);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function findOrCreateByName($brandId, $modelName) {
        $modelName = trim($modelName);
        if (empty($modelName)) {
            return ['error' => 'Название модели не может быть пустым'];
        }
        // Поиск существующей модели у данной марки
        $query = "SELECT model_id FROM car_models WHERE brand_id = :brand_id AND name = :name";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':brand_id', $brandId);
        $stmt->bindParam(':name', $modelName);
        $stmt->execute();
        if ($row = $stmt->fetch()) {
            return ['success' => true, 'model_id' => $row['model_id']];
        }
        // Создание новой модели
        $query = "INSERT INTO car_models (brand_id, name) VALUES (:brand_id, :name) RETURNING model_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':brand_id', $brandId);
        $stmt->bindParam(':name', $modelName);
        $stmt->execute();
        $modelId = $stmt->fetchColumn();
        return ['success' => true, 'model_id' => $modelId];
    }
}