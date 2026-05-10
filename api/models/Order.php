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
    
    /**
     * Получить все заказы для админки (с деталями)
     */
    public function getAllWithDetails() {
    $query = "SELECT 
                o.order_id, 
                o.order_date, 
                o.total_price, 
                o.prepayment, 
                o.notes,
                o.desired_date, 
                o.desired_time, 
                o.client_notes, 
                o.admin_notes,
                c.first_name, 
                c.last_name, 
                c.phone_number,
                cb.name as brand_name, 
                cm.name as model_name,
                (SELECT STRING_AGG(s.name, ', ') 
                 FROM order_services osv 
                 JOIN services s ON osv.service_id = s.service_id 
                 WHERE osv.order_id = o.order_id) as service_names,
                os.name as status_name
              FROM orders o
              LEFT JOIN clients c ON o.client_id = c.client_id
              LEFT JOIN car_brands cb ON o.brand_id = cb.brand_id
              LEFT JOIN car_models cm ON o.model_id = cm.model_id
              LEFT JOIN order_statuses os ON o.status_id = os.status_id
              ORDER BY o.order_date DESC, o.order_id DESC";
    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Получить заказ по ID
     */
    public function getById($id) {
        $query = "SELECT o.*, c.first_name, c.last_name, c.phone_number, c.email,
                         cb.name as brand_name, cm.name as model_name,
                         os.name as status_name
                  FROM orders o
                  LEFT JOIN clients c ON o.client_id = c.client_id
                  LEFT JOIN car_brands cb ON o.brand_id = cb.brand_id
                  LEFT JOIN car_models cm ON o.model_id = cm.model_id
                  LEFT JOIN order_statuses os ON o.status_id = os.status_id
                  WHERE o.order_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function createFromRequest($data) {
    // Если передан client_id, используем его
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

    // 2. Марка и модель
    $carBrandModel = new CarBrand();
    $brandResult = $carBrandModel->findOrCreateByName($data['car_brand'] ?? '');
    if (!$brandResult['success']) return ['error' => 'Ошибка с маркой'];
    $brandId = $brandResult['brand_id'];

    $carModelModel = new CarModel();
    $modelResult = $carModelModel->findOrCreateByName($brandId, $data['car_model'] ?? '');
    if (!$modelResult['success']) return ['error' => 'Ошибка с моделью'];
    $modelId = $modelResult['model_id'];

    // 3. Услуги
    $serviceIds = $data['service_ids'] ?? [];
    if (empty($serviceIds)) return ['error' => 'Выберите хотя бы одну услугу'];

    // 4. Расчёт общей суммы
    $serviceModel = new Service();
    $totalPrice = 0;
    $servicesData = [];
    foreach ($serviceIds as $serviceId) {
        $service = $serviceModel->getById($serviceId);
        if ($service) {
            $totalPrice += $service['base_price'];
            $servicesData[] = ['id' => $serviceId, 'price' => $service['base_price']];
        }
    }

    // 5. Создаём ОДИН заказ
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
    foreach ($servicesData as $srv) {
        $queryService = "INSERT INTO order_services (order_id, service_id, price_at_moment) 
                         VALUES (:order_id, :service_id, :price)";
        $stmtService = $this->conn->prepare($queryService);
        $stmtService->bindParam(':order_id', $orderId);
        $stmtService->bindParam(':service_id', $srv['id']);
        $stmtService->bindParam(':price', $srv['price']);
        $stmtService->execute();
    }

    return ['success' => true, 'order_id' => $orderId];
    }
    
    /**
     * Обновить статус заказа
     */
    public function updateStatus($id, $statusId) {
        $query = "UPDATE orders SET status_id = :status_id WHERE order_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':status_id', $statusId);
        return ['success' => $stmt->execute()];
    }
    
    /**
     * Обновить заказ (админский метод)
     */
    public function update($id, $data) {
        $query = "UPDATE orders SET status_id = :status_id, total_price = :total_price, prepayment = :prepayment, notes = :notes, admin_notes = :admin_notes WHERE order_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':status_id', $data['status_id']);
        $stmt->bindParam(':total_price', $data['total_price']);
        $stmt->bindParam(':prepayment', $data['prepayment']);
        $stmt->bindParam(':notes', $data['notes']);
        $stmt->bindParam(':admin_notes', $data['admin_notes']);
        return ['success' => $stmt->execute()];
    }
    
    /**
     * Удалить заказ (админский метод)
     */
    public function delete($id) {
        $query = "DELETE FROM orders WHERE order_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return ['success' => $stmt->execute()];
    }

    // api/models/Order.php – добавить метод getFilteredOrders

    public function getFilteredOrders($filters) {
    $sql = "SELECT o.order_id, o.order_date, o.total_price, o.prepayment, o.notes,
                   o.desired_date, o.desired_time, o.client_notes, o.admin_notes,
                   c.first_name, c.last_name, c.phone_number,
                   cb.name as brand_name, cm.name as model_name,
                   s.name as service_name, os.name as status_name
            FROM orders o
            LEFT JOIN clients c ON o.client_id = c.client_id
            LEFT JOIN car_brands cb ON o.brand_id = cb.brand_id
            LEFT JOIN car_models cm ON o.model_id = cm.model_id
            LEFT JOIN order_services osv ON o.order_id = osv.order_id
            LEFT JOIN services s ON osv.service_id = s.service_id
            LEFT JOIN order_statuses os ON o.status_id = os.status_id
            WHERE 1=1";
    $params = [];

    if (!empty($filters['search'])) {
        $search = '%' . $filters['search'] . '%';
        $sql .= " AND (c.first_name ILIKE :search OR c.last_name ILIKE :search OR c.phone_number ILIKE :search OR cb.name ILIKE :search OR cm.name ILIKE :search)";
        $params[':search'] = $search;
    }
    if (!empty($filters['status_id'])) {
        $sql .= " AND o.status_id = :status_id";
        $params[':status_id'] = $filters['status_id'];
    }
    if (!empty($filters['date_from'])) {
        $sql .= " AND o.order_date >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $sql .= " AND o.order_date <= :date_to";
        $params[':date_to'] = $filters['date_to'];
    }

    $sql .= " ORDER BY o.order_date DESC, o.order_id DESC";
    $stmt = $this->conn->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}