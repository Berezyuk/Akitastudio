<?php
// api/models/Order.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Client.php';
require_once __DIR__ . '/Service.php';
require_once __DIR__ . '/CarBrand.php';
require_once __DIR__ . '/CarModel.php';

class Order {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function createFromRequest($data) {
    // ── Валидация только на чтение — до любых записей, можно свободно return ──
    // is_array обязателен: скаляр проходит empty(), а foreach по нему — warning,
    // а не ошибка (заказ уходил бы на 0 ₽ без услуг, мимо проверок ниже).
    $serviceIds = $data['service_ids'] ?? [];
    if (!is_array($serviceIds) || empty($serviceIds)) {
        return ['error' => 'Выберите хотя бы одну услугу'];
    }

    $carBrand = trim($data['car_brand'] ?? '');
    $carModel = trim($data['car_model'] ?? '');
    if ($carBrand === '') return ['error' => 'Ошибка с маркой'];
    if ($carModel === '') return ['error' => 'Ошибка с моделью'];

    // Резолв услуг + сумма. Раньше цикл молча пропускал ненайденное — заказ на
    // 0 ₽ без услуг или заниженная сумма. Любой нерезолвнутый id — отказ.
    $serviceModel = new Service();
    $totalPrice = 0;
    $servicesData = [];
    foreach ($serviceIds as $serviceId) {
        $service = $serviceModel->getById($serviceId);
        if (!$service) {
            return ['error' => 'Услуга не найдена. Обновите страницу и выберите заново.'];
        }
        // getById (в отличие от getActive/getByCategory) не фильтрует is_active,
        // поэтому отключённую услугу можно было забронировать по старой цене.
        if (!$service['is_active']) {
            return ['error' => 'Услуга «' . $service['name'] . '» больше не оказывается.'];
        }
        // base_price NULL — это «по запросу»: цену согласует администратор.
        // Пишем 0, иначе NULL уходил в price_at_moment NOT NULL и ронял весь заказ.
        $price = $service['base_price'] ?? 0;
        $totalPrice += $price;
        $servicesData[] = ['id' => $serviceId, 'price' => $price];
    }

    // Существование клиента (если пришёл id) — тоже чтение.
    $clientId = null;
    if (!empty($data['client_id'])) {
        $stmt = $this->conn->prepare("SELECT client_id FROM clients WHERE client_id = :id");
        $stmt->bindValue(':id', $data['client_id']);
        $stmt->execute();
        if (!$stmt->fetch()) return ['error' => 'Клиент не найден'];
        $clientId = $data['client_id'];
    }

    // Идемпотентность: повтор отправки (double-submit / ретрай / resubmit) с тем
    // же ключом не создаёт второй заказ. Ключ — UUID от клиента; кривой игнорим,
    // иначе UUID-колонка бросит 22P02 → 500.
    $idemKey = $data['idempotency_key'] ?? null;
    if ($idemKey !== null && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $idemKey)) {
        $idemKey = null;
    }

    // ── Всё, что ПИШЕТ — одной транзакцией: марка, модель, клиент, заказ, услуги.
    //    Раньше марка/модель/клиент создавались автокоммитом ДО транзакции заказа
    //    → откат INSERT заказа оставлял их сиротами. Теперь rollBack чистит всё. ──
    try {
        $this->conn->beginTransaction();

        $brandResult = (new CarBrand())->findOrCreateByName($carBrand);
        if (empty($brandResult['success'])) throw new RuntimeException('brand');
        $brandId = $brandResult['brand_id'];

        $modelResult = (new CarModel())->findOrCreateByName($brandId, $carModel);
        if (empty($modelResult['success'])) throw new RuntimeException('model');
        $modelId = $modelResult['model_id'];

        if ($clientId === null) {
            $client = (new Client())->findOrCreate([
                'first_name' => $data['client_name'],
                'last_name' => $data['client_lastname'] ?? '',
                'phone_number' => preg_replace('/[^0-9]/', '', $data['client_phone']),
                'email' => $data['client_email'] ?? null
            ]);
            if (empty($client['success'])) throw new RuntimeException('client');
            $clientId = $client['client_id'];
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO orders
               (idempotency_key, client_id, brand_id, model_id, status_id, order_date, desired_date, desired_time, client_notes, total_price)
             VALUES
               (:idem, :client_id, :brand_id, :model_id, 1, NOW(), :desired_date, :desired_time, :notes, :total_price)
             RETURNING order_id"
        );
        $stmt->bindValue(':idem', $idemKey);
        $stmt->bindValue(':client_id', $clientId);
        $stmt->bindValue(':brand_id', $brandId);
        $stmt->bindValue(':model_id', $modelId);
        $stmt->bindValue(':desired_date', $data['desired_date'] ?? null);
        $stmt->bindValue(':desired_time', $data['desired_time'] ?? null);
        $stmt->bindValue(':notes', $data['comment'] ?? null);
        $stmt->bindValue(':total_price', $totalPrice);
        $stmt->execute();
        $orderId = $stmt->fetchColumn();

        $stmtService = $this->conn->prepare(
            "INSERT INTO order_services (order_id, service_id, price_at_moment)
             VALUES (:order_id, :service_id, :price)"
        );
        foreach ($servicesData as $srv) {
            $stmtService->bindValue(':order_id', $orderId);
            $stmtService->bindValue(':service_id', $srv['id']);
            $stmtService->bindValue(':price', $srv['price']);
            $stmtService->execute();
        }

        $this->conn->commit();
        return ['success' => true, 'order_id' => $orderId];
    } catch (\Throwable $e) {
        if ($this->conn->inTransaction()) $this->conn->rollBack();

        // Повтор с тем же idempotency_key: заказ уже создан первой отправкой —
        // возвращаем его, а не плодим дубль.
        if ($idemKey !== null && $e instanceof \PDOException && $e->getCode() === '23505'
            && str_contains($e->getMessage(), 'idempotency')) {
            $stmt = $this->conn->prepare("SELECT order_id FROM orders WHERE idempotency_key = :k");
            $stmt->bindValue(':k', $idemKey);
            $stmt->execute();
            $existing = $stmt->fetchColumn();
            if ($existing) return ['success' => true, 'order_id' => $existing, 'duplicate' => true];
        }

        // Осмысленные ошибки марки/модели/клиента (пустое имя и т.п.).
        if (in_array($e->getMessage(), ['brand', 'model', 'client'], true)) {
            $map = ['brand' => 'Ошибка с маркой', 'model' => 'Ошибка с моделью', 'client' => 'Ошибка создания клиента'];
            return ['error' => $map[$e->getMessage()]];
        }

        error_log('Order create error: ' . $e->getMessage());
        return ['error' => 'Не удалось создать заказ. Попробуйте позже.'];
    }
    }
    
    /**
     * Обновить статус заказа
     */
    public function updateStatus($id, $statusId) {
        // Без валидации отсутствующий status_id уходил в UPDATE как NULL: заказ
        // терял статус и выпадал из счётчиков дашборда (status_id IN (1,2,3)),
        // а несуществующий id ронял FK в 500. EXISTS отдаёт оба случая ошибкой.
        $statusId = filter_var($statusId, FILTER_VALIDATE_INT);
        if ($statusId === false) {
            return ['error' => 'Некорректный статус'];
        }

        $stmt = $this->conn->prepare(
            "UPDATE orders SET status_id = :status_id
             WHERE order_id = :id
               AND EXISTS (SELECT 1 FROM order_statuses WHERE status_id = :status_id)"
        );
        $stmt->execute([':id' => $id, ':status_id' => $statusId]);

        // execute() = true и при нуле затронутых строк: без rowCount несуществующий
        // заказ отвечал success.
        if ($stmt->rowCount() === 0) {
            return ['error' => 'Заказ или статус не найден'];
        }
        return ['success' => true];
    }
    
}