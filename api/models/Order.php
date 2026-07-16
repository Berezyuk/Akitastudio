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
    // Услуги проверяем ПЕРВЫМ делом, до findOrCreate клиента: иначе отказ ниже
    // оставлял в БД клиента без единого заказа (аноним мог так засорять таблицу).
    // is_array обязателен: скаляр проходит empty(), а foreach по нему — warning,
    // а не ошибка. Тело цикла не выполнялось, и заказ уходил в БД на 0 ₽ без услуг,
    // мимо проверок is_active и цен ниже.
    $serviceIds = $data['service_ids'] ?? [];
    if (!is_array($serviceIds) || empty($serviceIds)) {
        return ['error' => 'Выберите хотя бы одну услугу'];
    }

    // Марка и модель — ДО создания клиента. Раньше findOrCreate клиента шёл
    // выше этих проверок: новый телефон + пустая марка/битый service_id →
    // клиент вставлен, заказ нет → сирота в БД. Всё, что может дать отказ
    // (авто, услуги), считаем первым; клиента создаём последним.
    $carBrandModel = new CarBrand();
    $brandResult = $carBrandModel->findOrCreateByName($data['car_brand'] ?? '');
    if (!$brandResult['success']) return ['error' => 'Ошибка с маркой'];
    $brandId = $brandResult['brand_id'];

    $carModelModel = new CarModel();
    $modelResult = $carModelModel->findOrCreateByName($brandId, $data['car_model'] ?? '');
    if (!$modelResult['success']) return ['error' => 'Ошибка с моделью'];
    $modelId = $modelResult['model_id'];

    // Расчёт общей суммы (тоже до клиента).
    // Раньше цикл молча пропускал всё, что не нашлось (`if ($service)` без else):
    // несуществующий id давал заказ на 0 ₽ без единой услуги, а частичный список
    // тихо занижал сумму. Любой нерезолвнутый id — теперь отказ.
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

    // Клиент — ПОСЛЕДНИМ, когда марка/модель/услуги уже проверены (нет сирот).
    if (!empty($data['client_id'])) {
        $clientId = $data['client_id'];
        // Проверка существования клиента
        $stmt = $this->conn->prepare("SELECT client_id FROM clients WHERE client_id = :id");
        $stmt->bindParam(':id', $clientId);
        $stmt->execute();
        if (!$stmt->fetch()) {
            return ['error' => 'Клиент не найден'];
        }
    } else {
        // Создание нового клиента
        $clientModel = new Client();
        $client = $clientModel->findOrCreate([
            'first_name' => $data['client_name'],
            'last_name' => $data['client_lastname'] ?? '',
            'phone_number' => preg_replace('/[^0-9]/', '', $data['client_phone']),
            'email' => $data['client_email'] ?? null
        ]);
        if (!$client['success']) return ['error' => 'Ошибка создания клиента'];
        $clientId = $client['client_id'];
    }

    // 5. Создаём ОДИН заказ
    // Заказ и его услуги — одной транзакцией: иначе сбой на вставке услуг
    // оставлял заказ без единой услуги.
    $this->conn->beginTransaction();
    try {
        $query = "INSERT INTO orders
                  (client_id, brand_id, model_id, status_id, order_date, desired_date, desired_time, client_notes, total_price)
                  VALUES
                  (:client_id, :brand_id, :model_id, 1, NOW(), :desired_date, :desired_time, :notes, :total_price)
                  RETURNING order_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':client_id', $clientId);
        $stmt->bindParam(':brand_id', $brandId);
        $stmt->bindParam(':model_id', $modelId);
        $stmt->bindParam(':desired_date', $data['desired_date']);
        $stmt->bindParam(':desired_time', $data['desired_time']);
        $stmt->bindParam(':notes', $data['comment']);
        $stmt->bindParam(':total_price', $totalPrice);
        $stmt->execute();
        $orderId = $stmt->fetchColumn();

        // 6. Добавляем услуги в order_services (несколько записей)
        $queryService = "INSERT INTO order_services (order_id, service_id, price_at_moment)
                         VALUES (:order_id, :service_id, :price)";
        $stmtService = $this->conn->prepare($queryService);
        foreach ($servicesData as $srv) {
            $stmtService->bindParam(':order_id', $orderId);
            $stmtService->bindParam(':service_id', $srv['id']);
            $stmtService->bindParam(':price', $srv['price']);
            $stmtService->execute();
        }

        $this->conn->commit();
    } catch (Exception $e) {
        $this->conn->rollBack();
        error_log('Order create error: ' . $e->getMessage());
        return ['error' => 'Не удалось создать заказ. Попробуйте позже.'];
    }

    return ['success' => true, 'order_id' => $orderId];
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