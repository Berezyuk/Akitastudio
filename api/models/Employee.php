<?php
// api/models/Employee.php

require_once __DIR__ . '/../config/database.php';

class Employee {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function getAll() {
        $query = "SELECT employee_id, position, first_name, bio FROM employees ORDER BY employee_id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function create($data) {
        $query = "INSERT INTO employees (position, first_name, bio) VALUES (:position, :first_name, :bio)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':position', $data['position']);
        $stmt->bindParam(':first_name', $data['first_name']);
        $stmt->bindParam(':bio', $data['bio']);
        if($stmt->execute()) {
            return ['success' => true, 'employee_id' => $this->conn->lastInsertId()];
        }
        return ['error' => 'Ошибка добавления сотрудника'];
    }
    
    public function delete($id) {
        $query = "DELETE FROM employees WHERE employee_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return ['success' => $stmt->execute()];
    }
}