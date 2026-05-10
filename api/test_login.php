<?php
// test_login.php

// Подключаем базу
require_once __DIR__ . '/config/database.php';

$database = new Database();
$conn = $database->getConnection();

// Берем пользователя из базы
$login = 'admin';
$password = 'admin123'; // Пароль, который вводишь

$query = "SELECT user_id, login, password_hash, role FROM users WHERE login = :login";
$stmt = $conn->prepare($query);
$stmt->bindParam(':login', $login);
$stmt->execute();

if($stmt->rowCount() == 0) {
    echo "❌ Пользователь с логином '$login' не найден!\n";
    exit;
}

$user = $stmt->fetch();

echo "=== ДАННЫЕ ИЗ БАЗЫ ===\n";
echo "Логин: " . $user['login'] . "\n";
echo "Хеш из БД: " . $user['password_hash'] . "\n";
echo "========================\n\n";

// Проверяем пароль
echo "=== ПРОВЕРКА ПАРОЛЯ ===\n";
echo "Введенный пароль: $password\n";

if(password_verify($password, $user['password_hash'])) {
    echo "✅ ПАРОЛЬ ВЕРНЫЙ!\n";
    echo "Пользователь может войти.\n";
} else {
    echo "❌ ПАРОЛЬ НЕВЕРНЫЙ!\n";
    echo "\n";
    echo "=== ДИАГНОСТИКА ===\n";
    echo "Возможно, хеш в БД сгенерирован другим алгоритмом.\n";
    echo "Давай создадим новый правильный хеш.\n";
    
    // Генерируем правильный хеш
    $newHash = password_hash($password, PASSWORD_BCRYPT);
    echo "\n";
    echo "Правильный хеш для пароля '$password':\n";
    echo $newHash . "\n";
    echo "\n";
    echo "Выполни этот SQL, чтобы обновить пароль:\n";
    echo "UPDATE users SET password_hash = '" . $newHash . "' WHERE login = 'admin';\n";
}