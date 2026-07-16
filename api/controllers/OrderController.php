<?php
// api/controllers/OrderController.php

require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/RateLimiter.php';

class OrderController {

   public static function createOrder() {
    // Троттл: публичный эндпоинт создаёт заказы (и клиентов-сирот для гостей).
    // Без лимита аноним флудит таблицу orders.
    $db = (new Database())->getConnection();
    if (RateLimiter::tooManyAttempts($db, 'order', 20, 900)) {
        http_response_code(429);
        echo json_encode(['error' => 'Слишком много заявок. Попробуйте позже.']);
        return;
    }
    RateLimiter::hit($db, 'order');

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    // client_id берём ТОЛЬКО из сессии. Раньше сессия его лишь переопределяла,
    // а без сессии значение приходило из тела запроса — аноним мог подбросить
    // заказ в кабинет любого клиента, зная его id (IDOR).
    unset($data['client_id']);
    if (!empty($_SESSION['client_id'])) {
        $data['client_id'] = $_SESSION['client_id'];
    }

    // Услуги не проверяем: это делает Order::createFromRequest первым же шагом.
    // Здешняя копия пропускала одиночный service_id (модель читает только
    // service_ids), и такой запрос доходил до создания клиента-сироты.

    // Если нет client_id, то нужны имя и телефон
    if (empty($data['client_id']) && (empty($data['client_name']) || empty($data['client_phone']))) {
        echo json_encode(['error' => 'Заполните имя и телефон клиента']);
        return;
    }

    $order = new Order();
    $result = $order->createFromRequest($data);
    echo json_encode($result);
    }
}