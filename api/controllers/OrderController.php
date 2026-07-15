<?php
// api/controllers/OrderController.php

require_once __DIR__ . '/../models/Order.php';

class OrderController {

   public static function createOrder() {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    // client_id берём ТОЛЬКО из сессии. Раньше сессия его лишь переопределяла,
    // а без сессии значение приходило из тела запроса — аноним мог подбросить
    // заказ в кабинет любого клиента, зная его id (IDOR).
    unset($data['client_id']);
    if (!empty($_SESSION['client_id'])) {
        $data['client_id'] = $_SESSION['client_id'];
    }

    // Проверка услуг
    if (empty($data['service_ids']) && empty($data['service_id'])) {
        echo json_encode(['error' => 'Выберите хотя бы одну услугу']);
        return;
    }

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