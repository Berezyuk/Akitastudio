<?php
// api/models/Portfolio.php

require_once __DIR__ . '/../config/database.php';

class Portfolio {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    // Получить все элементы портфолио (для админки)
    public function getAll() {
        $query = "SELECT p.*, sc.name as category_name, s.name as service_name
                  FROM portfolio p
                  LEFT JOIN service_categories sc ON p.category_id = sc.category_id
                  LEFT JOIN services s ON p.service_id = s.service_id
                  ORDER BY p.sort_order, p.id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Получить элементы для публичной страницы (с фильтрацией)
    public function getActive($categoryId = null, $serviceId = null) {
        $sql = "SELECT p.*, sc.name as category_name, s.name as service_name
                FROM portfolio p
                LEFT JOIN service_categories sc ON p.category_id = sc.category_id
                LEFT JOIN services s ON p.service_id = s.service_id
                WHERE 1=1";
        $params = [];
        if ($categoryId) {
            $sql .= " AND p.category_id = :category_id";
            $params[':category_id'] = $categoryId;
        }
        if ($serviceId) {
            $sql .= " AND p.service_id = :service_id";
            $params[':service_id'] = $serviceId;
        }
        $sql .= " ORDER BY p.sort_order, p.id DESC";
        
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getById($id) {
        $query = "SELECT * FROM portfolio WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function countHomeItems($excludeId = null) {
        $sql = "SELECT COUNT(*) FROM portfolio WHERE show_on_home = TRUE";
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }
        $stmt = $this->conn->prepare($sql);
        if ($excludeId !== null) {
            $stmt->bindValue(':exclude_id', (int)$excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function create($data) {
        $showOnHome = !empty($data['show_on_home']) ? 'TRUE' : 'FALSE';
        $query = "INSERT INTO portfolio (video_url, category_id, service_id, sort_order, show_on_home)
                  VALUES (:video_url, :category_id, :service_id, :sort_order, {$showOnHome})";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':video_url', $data['video_url']);
        $stmt->bindParam(':category_id', $data['category_id']);
        $stmt->bindParam(':service_id', $data['service_id']);
        $stmt->bindParam(':sort_order', $data['sort_order']);

        if ($stmt->execute()) {
            return ['success' => true, 'id' => $this->conn->lastInsertId()];
        }
        return ['error' => 'Ошибка добавления видео'];
    }

    public function update($id, $data) {
        $showOnHome = !empty($data['show_on_home']) ? 'TRUE' : 'FALSE';
        $query = "UPDATE portfolio SET
                  video_url = :video_url,
                  category_id = :category_id,
                  service_id = :service_id,
                  sort_order = :sort_order,
                  show_on_home = {$showOnHome}
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':video_url', $data['video_url']);
        $stmt->bindParam(':category_id', $data['category_id']);
        $stmt->bindParam(':service_id', $data['service_id']);
        $stmt->bindParam(':sort_order', $data['sort_order']);

        return ['success' => $stmt->execute()];
    }
    
    public function delete($id) {
        $query = "DELETE FROM portfolio WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return ['success' => $stmt->execute()];
    }
}