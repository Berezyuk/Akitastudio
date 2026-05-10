<?php
// api/models/User.php

require_once __DIR__ . '/../config/database.php';

class User {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    // Проверка логина и пароля
    public function login($login, $password) {
        $query = "SELECT user_id, login, password_hash FROM users WHERE login = :login";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':login', $login);
        $stmt->execute();
        
        if($stmt->rowCount() == 0) {
            return ['error' => 'Неверный логин или пароль'];
        }
        
        $user = $stmt->fetch();

if(!password_verify($password, $user['password_hash'])) {
    return ['error' => 'Неверный логин или пароль'];
}

// НОВЫЙ ЗАПРОС — получаем полные данные пользователя
$query = "SELECT u.user_id, u.login, u.role, c.client_id, 
          CONCAT(c.first_name, ' ', c.last_name) as name
          FROM users u 
          LEFT JOIN clients c ON u.user_id = c.user_id 
          WHERE u.user_id = :user_id";
$stmt = $this->conn->prepare($query);
$stmt->bindParam(':user_id', $user['user_id']);
$stmt->execute();
$userData = $stmt->fetch();

$_SESSION['user_id'] = $userData['user_id'];
$_SESSION['role'] = $userData['role'];
$_SESSION['client_id'] = $userData['client_id'];
$_SESSION['name'] = $userData['name'];

return [
    'success' => true,
    'user' => [
        'user_id' => $userData['user_id'],
        'login' => $userData['login'],
        'role' => $userData['role'],
        'client_id' => $userData['client_id'],
        'name' => $userData['name']
    ]
];
    }
    
    public function logout() {
        session_destroy();
        return ['success' => true];
    }
    
    public function getCurrentUser() {
    if(!isset($_SESSION['user_id'])) {
        return null;
    }
    return [
        'user_id' => $_SESSION['user_id'],
        'role' => $_SESSION['role'] ?? 'client',
        'client_id' => $_SESSION['client_id'] ?? null,
        'name' => $_SESSION['name'] ?? null
    ];
    }

    // Добавь этот метод в класс User

public function register($data) {
    // Проверка на существование логина
    $check = $this->conn->prepare("SELECT user_id FROM users WHERE login = :login");
    $check->bindParam(':login', $data['login']);
    $check->execute();
    
    if($check->rowCount() > 0) {
        return ['error' => 'Пользователь с таким логином уже существует'];
    }
    
    // Проверка на существование телефона в clients
    $checkPhone = $this->conn->prepare("SELECT client_id FROM clients WHERE phone_number = :phone");
    $checkPhone->bindParam(':phone', $data['phone']);
    $checkPhone->execute();
    
    $hash = password_hash($data['password'], PASSWORD_BCRYPT);
    
    $this->conn->beginTransaction();
    
    try {
        // Создаём пользователя
        $userStmt = $this->conn->prepare("INSERT INTO users (login, password_hash, role) VALUES (:login, :hash, 'client')");
        $userStmt->bindParam(':login', $data['login']);
        $userStmt->bindParam(':hash', $hash);
        $userStmt->execute();
        $userId = $this->conn->lastInsertId();
        
        // Если телефон уже есть в clients — обновляем, иначе создаём
        if($checkPhone->rowCount() > 0) {
            $clientStmt = $this->conn->prepare("UPDATE clients SET user_id = :user_id, first_name = :first_name, last_name = :last_name, email = :email WHERE phone_number = :phone");
            $clientStmt->bindParam(':user_id', $userId);
            $clientStmt->bindParam(':first_name', $data['first_name']);
            $clientStmt->bindParam(':last_name', $data['last_name']);
            $clientStmt->bindParam(':email', $data['email']);
            $clientStmt->bindParam(':phone', $data['phone']);
            $clientStmt->execute();
        } else {
            $clientStmt = $this->conn->prepare("INSERT INTO clients (user_id, first_name, last_name, phone_number, email) VALUES (:user_id, :first_name, :last_name, :phone, :email)");
            $clientStmt->bindParam(':user_id', $userId);
            $clientStmt->bindParam(':first_name', $data['first_name']);
            $clientStmt->bindParam(':last_name', $data['last_name']);
            $clientStmt->bindParam(':phone', $data['phone']);
            $clientStmt->bindParam(':email', $data['email']);
            $clientStmt->execute();
        }
        
        $this->conn->commit();
        return ['success' => true];
        
    } catch(Exception $e) {
        $this->conn->rollBack();
        return ['error' => 'Ошибка регистрации: ' . $e->getMessage()];
    }
    }
    
    // Создание тестового админа (для первого запуска)
    public function createTestAdmin() {
        $login = 'admin';
        $password = 'admin123';
        $hash = password_hash($password, PASSWORD_BCRYPT);
        
        $query = "INSERT INTO users (login, password_hash) VALUES (:login, :hash) ON CONFLICT (login) DO NOTHING";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':login', $login);
        $stmt->bindParam(':hash', $hash);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Администратор создан'];
        }
        return ['info' => 'Администратор уже существует'];
    }
}