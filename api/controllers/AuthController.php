<?php
// api/controllers/AuthController.php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/RateLimiter.php';

class AuthController {

    public static function login() {
        $db = (new Database())->getConnection();

        if (RateLimiter::tooManyAttempts($db)) {
            http_response_code(429);
            echo json_encode(['error' => 'Слишком много попыток входа. Попробуйте через 15 минут.']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['login']) || !isset($data['password'])) {
            echo json_encode(['error' => 'Логин и пароль обязательны']);
            return;
        }

        $user   = new User();
        $result = $user->login($data['login'], $data['password']);

        if (!($result['success'] ?? false)) {
            RateLimiter::hit($db);
        }

        echo json_encode($result);
    }

    public static function register() {
    $data = json_decode(file_get_contents('php://input'), true);

    // Валидация
    if(empty($data['login']) || empty($data['password']) || empty($data['first_name']) || empty($data['last_name']) || empty($data['phone'])) {
        echo json_encode(['error' => 'Заполните все обязательные поля']);
        return;
    }
    
    // Проверка длины пароля
    if(strlen($data['password']) < 6) {
        echo json_encode(['error' => 'Пароль должен содержать минимум 6 символов']);
        return;
    }
    
    $user = new User();
    $result = $user->register($data);
    echo json_encode($result);
    }



    public static function logout() {
        $user = new User();
        $result = $user->logout();
        echo json_encode($result);
    }
    
    public static function me() {
    $user = new User();
    $current = $user->getCurrentUser();
    if(!$current) {
        echo json_encode(['error' => 'Не авторизован']);
        return;
    }
    echo json_encode(['success' => true, 'user' => $current]);
    }
    
    public static function createTestAdmin() {
        $user = new User();
        $result = $user->createTestAdmin();
        echo json_encode($result);
    }
}