<?php
// middleware/auth.php

function authenticate() {
    if(!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Требуется авторизация']);
        exit;
    }
    
    return [
        'user_id' => $_SESSION['user_id'],
        'client_id' => $_SESSION['client_id'],
        'role' => $_SESSION['role'],
        'name' => $_SESSION['name']
    ];
}

function requireRole($role) {
    $user = authenticate();
    
    if($user['role'] !== $role && $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Доступ запрещен']);
        exit;
    }
    
    return $user;
}