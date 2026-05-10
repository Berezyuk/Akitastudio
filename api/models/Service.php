<?php
// api/models/Service.php

require_once __DIR__ . '/../config/database.php';

class Service {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    // Получить все услуги (для админки, включая неактивные)
    public function getAll() {
        $query = "SELECT s.service_id, s.name, s.description, s.base_price, s.duration_minutes, s.is_active, s.icon_url, s.sort_order,
                         c.category_id, c.name as category_name, c.sort_order as category_sort
                  FROM services s
                  JOIN service_categories c ON s.category_id = c.category_id
                  ORDER BY c.sort_order, s.sort_order";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Получить активные услуги для публичной страницы
    public function getActive() {
        $query = "SELECT s.service_id, s.name, s.description, s.base_price, s.duration_minutes, s.icon_url,
                         c.category_id, c.name as category_name
                  FROM services s
                  JOIN service_categories c ON s.category_id = c.category_id
                  WHERE s.is_active = true
                  ORDER BY c.sort_order, s.sort_order";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getById($id) {
        $query = "SELECT * FROM services WHERE service_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function create($data) {
        $query = "INSERT INTO services (category_id, name, description, base_price, duration_minutes, is_active, icon_url, sort_order) 
                  VALUES (:category_id, :name, :description, :base_price, :duration_minutes, :is_active, :icon_url, :sort_order)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':category_id', $data['category_id']);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':base_price', $data['base_price']);
        $stmt->bindParam(':duration_minutes', $data['duration_minutes']);
        $stmt->bindParam(':is_active', $data['is_active']);
        $stmt->bindParam(':icon_url', $data['icon_url']);
        $stmt->bindParam(':sort_order', $data['sort_order']);
        if($stmt->execute()) {
            return ['success' => true, 'service_id' => $this->conn->lastInsertId()];
        }
        return ['error' => 'Ошибка добавления услуги'];
    }
    
    public function update($id, $data) {
        $query = "UPDATE services SET 
                  category_id = :category_id,
                  name = :name,
                  description = :description,
                  base_price = :base_price,
                  duration_minutes = :duration_minutes,
                  is_active = :is_active,
                  icon_url = :icon_url,
                  sort_order = :sort_order
                  WHERE service_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':category_id', $data['category_id']);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':base_price', $data['base_price']);
        $stmt->bindParam(':duration_minutes', $data['duration_minutes']);
        $stmt->bindParam(':is_active', $data['is_active']);
        $stmt->bindParam(':icon_url', $data['icon_url']);
        $stmt->bindParam(':sort_order', $data['sort_order']);
        return ['success' => $stmt->execute()];
    }
    
    public function delete($id) {
        $query = "DELETE FROM services WHERE service_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return ['success' => $stmt->execute()];
    }

    public function getByCategory($categoryId) {
    $query = "SELECT service_id, name FROM services WHERE category_id = :category_id AND is_active = true ORDER BY sort_order";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':category_id', $categoryId);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }  
}