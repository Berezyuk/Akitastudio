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
        // UPSERT по телефону (UNIQUE-констрейнт на phone_number). Раньше был
        // SELECT-затем-INSERT: два параллельных гостевых заказа с одним новым
        // телефоном оба промахивались по SELECT и вставляли по строке → дубли
        // клиента, телефон переставал быть ключом. ON CONFLICT делает операцию
        // атомарной. DO UPDATE (no-op: пишем тот же phone) нужен только чтобы
        // RETURNING вернул client_id уже существующей строки, не меняя её данные.
        $query = "INSERT INTO clients (first_name, last_name, phone_number, email)
                  VALUES (:first_name, :last_name, :phone, :email)
                  ON CONFLICT (phone_number) DO UPDATE SET phone_number = EXCLUDED.phone_number
                  RETURNING client_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':first_name', $data['first_name']);
        $stmt->bindParam(':last_name', $data['last_name']);
        $stmt->bindParam(':phone', $data['phone_number']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->execute();
        return ['success' => true, 'client_id' => $stmt->fetchColumn()];
    }
    
    // остальные методы (getById, update, delete) при необходимости
}