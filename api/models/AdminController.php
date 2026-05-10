<?php
// controllers/AdminController.php

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../models/Order.php';

class AdminController {
    
    public static function getOrders() {
        $admin = requireRole('admin');
        
        // Пока заглушка, потом допишем
        echo json_encode(['success' => true, 'orders' => []]);
    }
    
    public static function getStats() {
        $admin = requireRole('admin');
        
        echo json_encode(['success' => true, 'stats' => [
            'today_orders' => 0,
            'week_orders' => 0,
            'revenue_today' => 0,
            'active_orders' => 0
        ]]);
    }
    
    public static function getClients() {
        $admin = requireRole('admin');
        
        echo json_encode(['success' => true, 'clients' => []]);
    }

    // Добавить в существующий AdminController.php

public static function getServices() {
    $admin = requireRole('admin');
    
    $database = new Database();
    $conn = $database->getConnection();
    
    $query = "SELECT * FROM services ORDER BY sort_order, created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'services' => $stmt->fetchAll()]);
}

public static function addService() {
    $admin = requireRole('admin');
    $data = json_decode(file_get_contents('php://input'), true);
    
    $database = new Database();
    $conn = $database->getConnection();
    
    $query = "INSERT INTO services (name, category, description, price_from, price_to, duration_hours, is_active) 
              VALUES (:name, :category, :description, :price_from, :price_to, :duration_hours, :is_active)";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':name', $data['name']);
    $stmt->bindParam(':category', $data['category']);
    $stmt->bindParam(':description', $data['description']);
    $stmt->bindParam(':price_from', $data['price_from']);
    $stmt->bindParam(':price_to', $data['price_to']);
    $stmt->bindParam(':duration_hours', $data['duration_hours']);
    $stmt->bindParam(':is_active', $data['is_active']);
    
    if($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка при добавлении']);
    }
}

public static function updateService($id) {
    $admin = requireRole('admin');
    $data = json_decode(file_get_contents('php://input'), true);
    
    $database = new Database();
    $conn = $database->getConnection();
    
    $query = "UPDATE services SET 
              name = :name, 
              category = :category, 
              description = :description, 
              price_from = :price_from, 
              price_to = :price_to, 
              duration_hours = :duration_hours, 
              is_active = :is_active,
              updated_at = NOW()
              WHERE service_id = :id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':name', $data['name']);
    $stmt->bindParam(':category', $data['category']);
    $stmt->bindParam(':description', $data['description']);
    $stmt->bindParam(':price_from', $data['price_from']);
    $stmt->bindParam(':price_to', $data['price_to']);
    $stmt->bindParam(':duration_hours', $data['duration_hours']);
    $stmt->bindParam(':is_active', $data['is_active']);
    
    if($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка при обновлении']);
    }
}

public static function deleteService($id) {
    $admin = requireRole('admin');
    
    $database = new Database();
    $conn = $database->getConnection();
    
    $query = "DELETE FROM services WHERE service_id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    
    if($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка при удалении']);
    }
}

public static function toggleServiceActive($id) {
    $admin = requireRole('admin');
    $data = json_decode(file_get_contents('php://input'), true);
    
    $database = new Database();
    $conn = $database->getConnection();
    
    $query = "UPDATE services SET is_active = :is_active, updated_at = NOW() WHERE service_id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':is_active', $data['is_active']);
    
    if($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка при изменении статуса']);
    }
}

public static function getPortfolio() {
    self::checkAdmin();
    $portfolio = new Portfolio();
    $items = $portfolio->getAll();
    echo json_encode(['success' => true, 'portfolio' => $items]);
}

public static function addPortfolio() {
    self::checkAdmin();
    $data = json_decode(file_get_contents('php://input'), true);
    $portfolio = new Portfolio();
    $result = $portfolio->create($data);
    echo json_encode($result);
}

public static function updatePortfolio($id) {
    self::checkAdmin();
    $data = json_decode(file_get_contents('php://input'), true);
    $portfolio = new Portfolio();
    $result = $portfolio->update($id, $data);
    echo json_encode($result);
}

public static function deletePortfolio($id) {
    self::checkAdmin();
    $portfolio = new Portfolio();
    $result = $portfolio->delete($id);
    echo json_encode($result);
}



}