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

// Единый guard для админских эндпоинтов. Возвращает данные пользователя,
// чтобы вызыватели могли взять name/user_id без повторного чтения сессии.
//
// Раньше рядом жил requireRole($role) с условием `role !== $role &&
// role !== 'admin'` — админ проходил ЛЮБУЮ проверку роли. Пока звался только
// с 'admin' это ничего не давало, но был латентный капкан: requireRole('client')
// для ресурса, куда админу нельзя, молча пропустил бы админа. Удалён —
// весь админский guard теперь один, без обхода.
function requireAdmin() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Не авторизован']);
        exit;
    }
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Доступ запрещён']);
        exit;
    }
    return authenticate();
}