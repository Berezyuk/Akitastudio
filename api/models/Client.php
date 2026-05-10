<?php
// api/models/Client.php

require_once __DIR__ . '/../config/database.php';

class Client {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function getAll() {
        $query = "SELECT client_id, first_name, last_name, phone_number, email FROM clients ORDER BY client_id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function findOrCreate($data) {
        // Поиск по телефону (обязательное поле)
        $query = "SELECT client_id FROM clients WHERE phone_number = :phone";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':phone', $data['phone_number']);
        $stmt->execute();
        if ($row = $stmt->fetch()) {
            return ['success' => true, 'client_id' => $row['client_id']];
        }
        
        // Создание нового клиента
        $query = "INSERT INTO clients (first_name, last_name, phone_number, email) 
                  VALUES (:first_name, :last_name, :phone, :email) RETURNING client_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':first_name', $data['first_name']);
        $stmt->bindParam(':last_name', $data['last_name']);
        $stmt->bindParam(':phone', $data['phone_number']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->execute();
        $clientId = $stmt->fetchColumn();
        return ['success' => true, 'client_id' => $clientId];
    }
    
    // остальные методы (getById, update, delete) при необходимости
}