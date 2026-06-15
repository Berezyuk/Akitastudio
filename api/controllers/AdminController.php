<?php
// api/controllers/AdminController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../helpers/MinioHelper.php';
require_once __DIR__ . '/../models/Service.php';
require_once __DIR__ . '/../models/Portfolio.php';
require_once __DIR__ . '/../models/Client.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/CarBrand.php';
require_once __DIR__ . '/../models/CarModel.php';
require_once __DIR__ . '/../models/ServiceCategory.php';
require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../models/OrderStatus.php';
require_once __DIR__ . '/../models/SiteSettings.php';

class AdminController {
    
    private static function checkAdmin() {
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
    }
    
    // ========== УПРАВЛЕНИЕ УСЛУГАМИ ==========
    public static function getServices() {
        self::checkAdmin();
        $service = new Service();
        $services = $service->getAll();
        echo json_encode(['success' => true, 'services' => $services]);
    }
    
    public static function addService() {
        self::checkAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $service = new Service();
        $result = $service->create($data);
        echo json_encode($result);
    }
    
    public static function updateService($id) {
        self::checkAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $service = new Service();
        $result = $service->update($id, $data);
        echo json_encode($result);
    }
    
    public static function deleteService($id) {
        self::checkAdmin();
        $service = new Service();
        $result = $service->delete($id);
        echo json_encode($result);
    }
    
    // ========== УПРАВЛЕНИЕ ПОРТФОЛИО ==========
    public static function getPortfolio() {
        self::checkAdmin();
        $portfolio = new Portfolio();
        $items = $portfolio->getAll();
        echo json_encode(['success' => true, 'portfolio' => $items]);
    }
    
    public static function addPortfolio() {
        self::checkAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $portfolio = new Portfolio();
        if (!empty($data['show_on_home']) && $portfolio->countHomeItems() >= 5) {
            echo json_encode(['success' => false, 'home_limit_exceeded' => true]);
            return;
        }
        $result = $portfolio->create($data);
        echo json_encode($result);
    }

    public static function updatePortfolio($id) {
        self::checkAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $portfolio = new Portfolio();
        if (!empty($data['show_on_home']) && $portfolio->countHomeItems($id) >= 5) {
            echo json_encode(['success' => false, 'home_limit_exceeded' => true]);
            return;
        }
        $result = $portfolio->update($id, $data);
        echo json_encode($result);
    }


    public static function deletePortfolio($id) {
        self::checkAdmin();
        $portfolio = new Portfolio();
        $result = $portfolio->delete($id);
        echo json_encode($result);
    }
    
    // ========== УПРАВЛЕНИЕ КЛИЕНТАМИ ==========
    public static function getClients() {
        self::checkAdmin();
        $client = new Client();
        $clients = $client->getAll();
        echo json_encode(['success' => true, 'clients' => $clients]);
    }
    
    public static function addClient() {
        self::checkAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $client = new Client();
        $result = $client->create($data);
        echo json_encode($result);
    }
    
    public static function updateClient($id) {
    self::checkAdmin();
    $data = json_decode(file_get_contents('php://input'), true);
    $db = new Database();
    $conn = $db->getConnection();

    $query = "UPDATE clients SET first_name = :first_name, last_name = :last_name, phone_number = :phone, email = :email WHERE client_id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':first_name', $data['first_name']);
    $stmt->bindParam(':last_name', $data['last_name']);
    $stmt->bindParam(':phone', $data['phone_number']);
    $stmt->bindParam(':email', $data['email']);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка обновления']);
    }
    }
    
    public static function deleteClient($id) {
        self::checkAdmin();
        $client = new Client();
        $result = $client->delete($id);
        echo json_encode($result);
    }
    
    

    public static function deleteOrder($id) {
        self::checkAdmin();
        $db = (new Database())->getConnection();
        $stmt = $db->prepare('DELETE FROM orders WHERE order_id = :id');
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true]);
    }

    // Обновление редактируемых полей заказа (дата, время, сумма, заметки)
    public static function updateOrder($id) {
        self::checkAdmin();
        $data = json_decode(file_get_contents('php://input'), true);

        $db     = (new Database())->getConnection();
        $fields = [];
        $params = [':id' => $id];

        $allowed = ['desired_date', 'desired_time', 'total_price', 'admin_notes', 'status_id'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field] === '' ? null : $data[$field];
            }
        }

        if (empty($fields)) {
            echo json_encode(['error' => 'Нет полей для обновления']);
            return;
        }

        $stmt = $db->prepare('UPDATE orders SET ' . implode(', ', $fields) . ' WHERE order_id = :id');
        $stmt->execute($params);
        echo json_encode(['success' => true]);
    }

    public static function updateOrderStatus($id) {
        self::checkAdmin();
        $data     = json_decode(file_get_contents('php://input'), true);
        $statusId = $data['status_id'];
        $order    = new Order();
        echo json_encode($order->updateStatus($id, $statusId));
    }
   

    public static function getOrder($id) {
    self::checkAdmin();
    $order = new Order();
    $details = $order->getById($id);
    echo json_encode(['success' => true, 'order' => $details]);
    }
    
    // ========== УПРАВЛЕНИЕ СПРАВОЧНИКАМИ ==========
    // Марки автомобилей
    public static function getCarBrands() {
        self::checkAdmin();
        $brand = new CarBrand();
        $brands = $brand->getAll();
        echo json_encode(['success' => true, 'brands' => $brands]);
    }
    
    public static function addCarBrand() {
        self::checkAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $brand = new CarBrand();
        $result = $brand->create($data['name']);
        echo json_encode($result);
    }
    
    public static function updateCarBrand($id) {
        self::checkAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $brand = new CarBrand();
        $result = $brand->update($id, $data['name']);
        echo json_encode($result);
    }
    
    public static function deleteCarBrand($id) {
        self::checkAdmin();
        $brand = new CarBrand();
        $result = $brand->delete($id);
        echo json_encode($result);
    }
    
    // Модели автомобилей
    public static function getCarModels() {
        self::checkAdmin();
        $model = new CarModel();
        $models = $model->getAll();
        echo json_encode(['success' => true, 'models' => $models]);
    }
    
    public static function addCarModel() {
        self::checkAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $model = new CarModel();
        $result = $model->create($data);
        echo json_encode($result);
    }
    
    public static function updateCarModel($id) {
        self::checkAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $model = new CarModel();
        $result = $model->update($id, $data);
        echo json_encode($result);
    }
    
    public static function deleteCarModel($id) {
        self::checkAdmin();
        $model = new CarModel();
        $result = $model->delete($id);
        echo json_encode($result);
    }
    
    // Категории услуг
    //public static function getServiceCategories() {
     //   self::checkAdmin();
     //   $cat = new ServiceCategory();
     //   $cats = $cat->getAll();
      //  echo json_encode(['success' => true, 'categories' => $cats]);
   // }

    public static function getServiceCategories() {
    self::checkAdmin();
    $cat = new ServiceCategory();
    $categories = $cat->getAll();
    echo json_encode(['success' => true, 'categories' => $categories]);
   }

    public static function getServicesByCategory($categoryId) {
    self::checkAdmin();
    $service = new Service();
    $services = $service->getByCategory($categoryId);
    echo json_encode(['success' => true, 'services' => $services]);
    }
    
    public static function addServiceCategory() {
        self::checkAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $cat = new ServiceCategory();
        if (!empty($data['show_on_home']) && $cat->countHomeItems() >= 5) {
            echo json_encode(['success' => false, 'home_limit_exceeded' => true]);
            return;
        }
        $result = $cat->create(
            $data['name'],
            $data['sort_order'],
            $data['icon'] ?? '',
            !empty($data['show_on_home'])
        );
        echo json_encode($result);
    }

    public static function updateServiceCategory($id) {
        self::checkAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $cat = new ServiceCategory();
        if (!empty($data['show_on_home']) && $cat->countHomeItems($id) >= 5) {
            echo json_encode(['success' => false, 'home_limit_exceeded' => true]);
            return;
        }
        $result = $cat->update(
            $id,
            $data['name'],
            $data['sort_order'],
            $data['icon'] ?? '',
            !empty($data['show_on_home'])
        );
        echo json_encode($result);
    }

    public static function deleteServiceCategory($id) {
        self::checkAdmin();
        $cat = new ServiceCategory();
        // Удаляем медиа из MinIO если есть
        $existing = $cat->getMediaUrl($id);
        if ($existing) {
            $parsed = MinioHelper::parseUrl($existing);
            if ($parsed) {
                try { MinioHelper::delete($parsed['bucket'], $parsed['key']); } catch (\Exception $e) {}
            }
        }
        $result = $cat->delete($id);
        echo json_encode($result);
    }

    // POST /api/admin/service-categories/:id/media  (multipart, поле 'media')
    public static function uploadCategoryMedia($id) {
        self::checkAdmin();

        if (!isset($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['error' => 'Ошибка загрузки файла']);
            return;
        }

        $file = $_FILES['media'];
        $allowedTypes = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif',
            'video/mp4', 'video/webm', 'video/ogg',
        ];
        if (!in_array($file['type'], $allowedTypes)) {
            echo json_encode(['error' => 'Разрешены: JPG, PNG, WEBP, GIF, MP4, WEBM, OGG']);
            return;
        }
        if ($file['size'] > 100 * 1024 * 1024) {
            echo json_encode(['error' => 'Максимум 100 МБ']);
            return;
        }

        $cat = new ServiceCategory();

        // Удаляем старое медиа
        $existing = $cat->getMediaUrl($id);
        if ($existing) {
            $parsed = MinioHelper::parseUrl($existing);
            if ($parsed) {
                try { MinioHelper::delete($parsed['bucket'], $parsed['key']); } catch (\Exception $e) {}
            }
        }

        $key = MinioHelper::generateKey('category-media', $file['name']);
        try {
            $url = MinioHelper::upload('portfolio', $key, $file['tmp_name'], $file['type']);
        } catch (\Exception $e) {
            error_log('MinIO category media upload error: ' . $e->getMessage());
            echo json_encode(['error' => 'Не удалось загрузить файл']);
            return;
        }

        $cat->updateMedia($id, $url);
        echo json_encode(['success' => true, 'url' => $url]);
    }

    // DELETE /api/admin/service-categories/:id/media
    public static function deleteCategoryMedia($id) {
        self::checkAdmin();
        $cat = new ServiceCategory();
        $existing = $cat->getMediaUrl($id);
        if ($existing) {
            $parsed = MinioHelper::parseUrl($existing);
            if ($parsed) {
                try { MinioHelper::delete($parsed['bucket'], $parsed['key']); } catch (\Exception $e) {}
            }
        }
        $cat->updateMedia($id, null);
        echo json_encode(['success' => true]);
    }
    
    // Сотрудники (мастера)
    public static function getEmployees() {
        self::checkAdmin();
        $emp = new Employee();
        $employees = $emp->getAll();
        echo json_encode(['success' => true, 'employees' => $employees]);
    }
    
    public static function addEmployee() {
        self::checkAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $emp = new Employee();
        $result = $emp->create($data);
        echo json_encode($result);
    }
    
    public static function deleteEmployee($id) {
        self::checkAdmin();
        $emp = new Employee();
        $result = $emp->delete($id);
        echo json_encode($result);
    }
    
    // Статусы заказов (для выпадающих списков)
    public static function getOrderStatuses() {
        self::checkAdmin();
        $status = new OrderStatus();
        $statuses = $status->getAll();
        echo json_encode(['success' => true, 'statuses' => $statuses]);
    }

    // В AdminController.php добавить или заменить:

    public static function getOrders() {
        self::checkAdmin();

        $page  = max(1, (int)($_GET['page']  ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $db = (new Database())->getConnection();

        // Общее кол-во для пагинатора
        $total = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();

        $stmt = $db->prepare(
            "SELECT o.order_id, o.order_date, o.total_price, o.prepayment, o.notes,
                    o.desired_date, o.desired_time, o.client_notes, o.admin_notes,
                    o.status_id,
                    c.first_name, c.last_name, c.phone_number,
                    cb.name AS brand_name, cm.name AS model_name,
                    (SELECT STRING_AGG(s.name, ', ')
                     FROM order_services osv JOIN services s ON osv.service_id = s.service_id
                     WHERE osv.order_id = o.order_id) AS service_names,
                    os.name AS status_name
             FROM orders o
             LEFT JOIN clients c ON o.client_id = c.client_id
             LEFT JOIN car_brands cb ON o.brand_id = cb.brand_id
             LEFT JOIN car_models cm ON o.model_id = cm.model_id
             LEFT JOIN order_statuses os ON o.status_id = os.status_id
             ORDER BY o.order_date DESC, o.order_id DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit',  $limit,  \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'orders'  => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total'   => $total,
            'page'    => $page,
            'limit'   => $limit,
        ]);
    }

    public static function exportOrders() {
    self::checkAdmin();
    $filters = $_GET;
    $order = new Order();
    $orders = $order->getFilteredOrders($filters);

    $filename = 'orders_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['ID', 'Клиент', 'Телефон', 'Услуга', 'Автомобиль', 'Статус', 'Дата заказа', 'Желаемая дата', 'Сумма']);

    foreach ($orders as $order) {
        fputcsv($output, [
            $order['order_id'],
            ($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''),
            $order['phone_number'] ?? '',
            $order['service_name'] ?? '',
            ($order['brand_name'] ?? '') . ' ' . ($order['model_name'] ?? ''),
            $order['status_name'] ?? '',
            $order['order_date'] ?? '',
            $order['desired_date'] ?? '',
            $order['total_price'] ?? ''
        ]);
    }
    fclose($output);
    exit;
    }

    // api/controllers/AdminController.php

// Получить список клиентов с поиском
    public static function getClientsList() {
        self::checkAdmin();

        $search = $_GET['search'] ?? '';
        $page   = max(1, (int)($_GET['page']  ?? 1));
        $limit  = min(100, max(1, (int)($_GET['limit'] ?? 30)));
        $offset = ($page - 1) * $limit;

        $conn   = (new Database())->getConnection();
        $where  = '1=1';
        $params = [];

        if (!empty($search)) {
            $where .= " AND (first_name ILIKE :s OR last_name ILIKE :s OR phone_number ILIKE :s OR email ILIKE :s)";
            $params[':s'] = "%{$search}%";
        }

        $total = (int)$conn->prepare("SELECT COUNT(*) FROM clients WHERE {$where}")
                           ->execute($params) ? $conn->query("SELECT COUNT(*) FROM clients WHERE {$where}")->fetchColumn() : 0;

        // Более надёжный способ получить total
        $stmtCount = $conn->prepare("SELECT COUNT(*) FROM clients WHERE {$where}");
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $stmtData = $conn->prepare(
            "SELECT client_id, first_name, last_name, patronymic, phone_number, email
             FROM clients WHERE {$where}
             ORDER BY client_id DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $stmtData->bindValue($k, $v);
        }
        $stmtData->bindValue(':limit',  $limit,  \PDO::PARAM_INT);
        $stmtData->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmtData->execute();

        echo json_encode([
            'success' => true,
            'clients' => $stmtData->fetchAll(PDO::FETCH_ASSOC),
            'total'   => $total,
            'page'    => $page,
            'limit'   => $limit,
        ]);
    }

// Получить детали клиента и его заказы
    public static function getClientDetails($id) {
    self::checkAdmin();
    $db = new Database();
    $conn = $db->getConnection();

    // Информация о клиенте
    $stmt = $conn->prepare("SELECT client_id, first_name, last_name, patronymic, phone_number, email FROM clients WHERE client_id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$client) {
        echo json_encode(['error' => 'Клиент не найден']);
        return;
    }

    // История заказов клиента
    $ordersSql = "SELECT o.order_id, o.order_date, o.total_price, o.status_id, os.name as status_name,
                         (SELECT STRING_AGG(s.name, ', ') 
                          FROM order_services osv 
                          JOIN services s ON osv.service_id = s.service_id 
                          WHERE osv.order_id = o.order_id) as services
                  FROM orders o
                  LEFT JOIN order_statuses os ON o.status_id = os.status_id
                  WHERE o.client_id = :client_id
                  ORDER BY o.order_date DESC";
    $stmtOrders = $conn->prepare($ordersSql);
    $stmtOrders->bindParam(':client_id', $id);
    $stmtOrders->execute();
    $orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'client' => $client, 'orders' => $orders]);
    }

    public static function exportClientsCSV() {
    self::checkAdmin();
    $search = $_GET['search'] ?? '';
    $db = new Database();
    $conn = $db->getConnection();

    $sql = "SELECT client_id, first_name, last_name, phone_number, email FROM clients WHERE 1=1";
    if (!empty($search)) {
        $sql .= " AND (first_name ILIKE :search OR last_name ILIKE :search OR phone_number ILIKE :search OR email ILIKE :search)";
    }
    $sql .= " ORDER BY client_id DESC";
    $stmt = $conn->prepare($sql);
    if (!empty($search)) {
        $stmt->bindValue(':search', "%$search%");
    }
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'clients_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['ID', 'Имя', 'Фамилия', 'Телефон', 'Email']);

    foreach ($clients as $client) {
        fputcsv($output, [
            $client['client_id'],
            $client['first_name'],
            $client['last_name'],
            $client['phone_number'],
            $client['email']
        ]);
    }
    fclose($output);
    exit;
    }

    public static function getDashboardStats() {
    self::checkAdmin();
    $db = new Database();
    $conn = $db->getConnection();

    // Заказы сегодня
    $stmt = $conn->query("SELECT COUNT(*) as count FROM orders WHERE order_date = CURRENT_DATE");
    $todayOrders = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Активные заказы (статусы: 1-ожидание, 2-подтверждён, 3-в работе)
    $stmt = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status_id IN (1,2,3)");
    $activeOrders = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Выручка за текущий месяц
    $stmt = $conn->query("SELECT COALESCE(SUM(total_price), 0) as total FROM orders WHERE EXTRACT(YEAR FROM order_date) = EXTRACT(YEAR FROM CURRENT_DATE) AND EXTRACT(MONTH FROM order_date) = EXTRACT(MONTH FROM CURRENT_DATE)");
    $monthRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Всего клиентов
    $stmt = $conn->query("SELECT COUNT(*) as count FROM clients");
    $totalClients = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Одним запросом: ожидание + статистика для дублированных счётчиков
    $stmt = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status_id = 1");
    $pendingOrders = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $newOrdersToday = $todayOrders; // дублировался тот же запрос

    // Данные для графика — заказы и выручка за последние 7 дней
    $chartData = ['labels' => [], 'values' => [], 'revenue' => []];
    $fromDate = date('Y-m-d', strtotime('-6 days'));
    $stmt = $conn->prepare(
        "SELECT order_date::date::text AS day, COUNT(*) AS cnt, COALESCE(SUM(total_price), 0) AS revenue
         FROM orders WHERE order_date::date >= :from_date
         GROUP BY order_date::date ORDER BY order_date::date ASC"
    );
    $stmt->execute([':from_date' => $fromDate]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $byDate = array_column($rows, null, 'day');
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $chartData['labels'][] = date('d.m', strtotime($date));
        $chartData['values'][] = (int)($byDate[$date]['cnt'] ?? 0);
        $chartData['revenue'][] = (float)($byDate[$date]['revenue'] ?? 0);
    }

    // Популярные услуги (топ 5)
    $stmt = $conn->query("SELECT s.service_id, s.name, COUNT(osv.service_id) as count 
                          FROM order_services osv
                          JOIN services s ON osv.service_id = s.service_id
                          GROUP BY s.service_id, s.name
                          ORDER BY count DESC LIMIT 5");
    $popularServices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Последние 5 заказов с деталями
    $stmt = $conn->query("SELECT o.order_id, o.order_date, o.total_price, 
                                 c.first_name, c.last_name, 
                                 (SELECT STRING_AGG(s.name, ', ') FROM order_services osv JOIN services s ON osv.service_id = s.service_id WHERE osv.order_id = o.order_id) as services,
                                 os.name as status_name
                          FROM orders o
                          LEFT JOIN clients c ON o.client_id = c.client_id
                          LEFT JOIN order_statuses os ON o.status_id = os.status_id
                          ORDER BY o.order_id DESC LIMIT 5");
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'stats' => [
            'today_orders' => $todayOrders,
            'active_orders' => $activeOrders,
            'month_revenue' => $monthRevenue,
            'total_clients' => $totalClients,
            'new_orders_today' => $newOrdersToday,
            'pending_orders' => $pendingOrders
        ],
        
        'recent_orders' => $recentOrders,
        'popular_services' => $popularServices,
        'chart_data' => $chartData
    ]);
    }
    
    public static function changePassword() {
    self::checkAdmin();
    $data = json_decode(file_get_contents('php://input'), true);
    
    $oldPassword = $data['old_password'] ?? '';
    $newPassword = $data['new_password'] ?? '';
    $confirmPassword = $data['confirm_password'] ?? '';
    
    if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
        echo json_encode(['error' => 'Заполните все поля']);
        return;
    }
    
    if ($newPassword !== $confirmPassword) {
        echo json_encode(['error' => 'Новый пароль и подтверждение не совпадают']);
        return;
    }
    
    if (strlen($newPassword) < 6) {
        echo json_encode(['error' => 'Новый пароль должен быть не менее 6 символов']);
        return;
    }
    
    $db = new Database();
    $conn = $db->getConnection();
    
    // Получаем текущий хеш пароля
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = :id");
    $stmt->bindParam(':id', $userId);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !password_verify($oldPassword, $user['password_hash'])) {
        echo json_encode(['error' => 'Неверный текущий пароль']);
        return;
    }
    
    // Хешируем новый пароль
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
    
    $stmt = $conn->prepare("UPDATE users SET password_hash = :hash WHERE user_id = :id");
    $stmt->bindParam(':hash', $newHash);
    $stmt->bindParam(':id', $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Пароль успешно изменён']);
    } else {
        echo json_encode(['error' => 'Ошибка при смене пароля']);
    }
    }


    // Получить услуги с прогрессом для конкретного заказа
public static function getOrderServicesWithProgress($orderId) {
    $admin = requireRole('admin');
    
    $db = (new Database())->getConnection();
    
    // Получаем услуги заказа вместе с прогрессом
    $query = "SELECT osrv.service_id, s.name as service_name, 
              COALESCE(osp.progress_percent, 0) as progress_percent,
              COALESCE(osp.status, 'pending') as status
              FROM order_services osrv
              JOIN services s ON osrv.service_id = s.service_id
              LEFT JOIN order_services_progress osp ON osrv.order_id = osp.order_id AND osrv.service_id = osp.service_id
              WHERE osrv.order_id = :order_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'services' => $services]);
}

// Обновить прогресс услуги
public static function updateServiceProgress($orderId, $serviceId) {
    $admin = requireRole('admin');
    $data = json_decode(file_get_contents('php://input'), true);
    
    $progress = (int)$data['progress_percent'];
    if($progress < 0) $progress = 0;
    if($progress > 100) $progress = 100;
    
    $status = 'in_progress';
    if($progress == 0) $status = 'pending';
    if($progress == 100) $status = 'completed';
    
    $db = (new Database())->getConnection();
    
    $query = "INSERT INTO order_services_progress (order_id, service_id, progress_percent, status, updated_at)
              VALUES (:order_id, :service_id, :progress, :status, NOW())
              ON CONFLICT (order_id, service_id) 
              DO UPDATE SET progress_percent = :progress, status = :status, updated_at = NOW()";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $orderId);
    $stmt->bindParam(':service_id', $serviceId);
    $stmt->bindParam(':progress', $progress);
    $stmt->bindParam(':status', $status);
    $stmt->execute();

    // Авто-синхронизация статуса заказа по агрегированному прогрессу услуг
    $aggStmt = $db->prepare(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN osp.progress_percent = 100 THEN 1 ELSE 0 END) AS done,
                COALESCE(MAX(osp.progress_percent), 0) AS max_p
         FROM order_services osrv
         LEFT JOIN order_services_progress osp
               ON osrv.order_id = osp.order_id AND osrv.service_id = osp.service_id
         WHERE osrv.order_id = :order_id"
    );
    $aggStmt->execute([':order_id' => $orderId]);
    $agg = $aggStmt->fetch(PDO::FETCH_ASSOC);

    $newStatusId = 1; // Новый
    if ((int)$agg['max_p'] > 0)  $newStatusId = 2; // В работе
    if ((int)$agg['total'] > 0 && (int)$agg['done'] >= (int)$agg['total']) $newStatusId = 3; // Готово

    // Не перезаписываем «Выдан» (4) и «Отменён» (5) — только автоуправляемые статусы
    $db->prepare("UPDATE orders SET status_id = :sid WHERE order_id = :oid AND status_id NOT IN (4, 5)")
       ->execute([':sid' => $newStatusId, ':oid' => $orderId]);

    echo json_encode(['success' => true]);
    }
    
    // Загрузить фото для заказа → MinIO
    public static function uploadOrderPhoto($orderId) {
        requireRole('admin');

        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['error' => 'Ошибка загрузки файла']);
            return;
        }

        $file    = $_FILES['photo'];
        $caption = trim($_POST['caption'] ?? '');

        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            echo json_encode(['error' => 'Разрешены только JPG, PNG и WEBP']);
            return;
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            echo json_encode(['error' => 'Файл слишком большой. Максимум 10 МБ']);
            return;
        }

        $key = MinioHelper::generateKey("orders/{$orderId}", $file['name']);

        try {
            $photoUrl = MinioHelper::upload('order-photos', $key, $file['tmp_name'], $file['type']);
        } catch (Exception $e) {
            error_log('MinIO upload error: ' . $e->getMessage());
            echo json_encode(['error' => 'Не удалось загрузить файл в хранилище']);
            return;
        }

        $db = (new Database())->getConnection();

        $stmt = $db->prepare(
            "INSERT INTO order_photos (order_id, photo_url, caption, uploaded_by, sort_order)
             VALUES (:order_id, :photo_url, :caption, 'admin',
             (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM order_photos WHERE order_id = :order_id))"
        );
        $stmt->bindParam(':order_id',  $orderId);
        $stmt->bindParam(':photo_url', $photoUrl);
        $stmt->bindParam(':caption',   $caption);
        $stmt->execute();

        echo json_encode(['success' => true, 'photo_url' => $photoUrl]);
    }


// Получить все фото по заказу (админ)
public static function getOrderPhotos($orderId) {
    $admin = requireRole('admin');
    
    $db = (new Database())->getConnection();
    
    $query = "SELECT id, photo_url, caption, uploaded_by, sort_order 
              FROM order_photos 
              WHERE order_id = :order_id 
              ORDER BY sort_order ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'photos' => $photos]);
}

    // Удалить фото из MinIO и БД
    public static function deleteOrderPhoto($photoId) {
        requireRole('admin');

        $db   = (new Database())->getConnection();
        $stmt = $db->prepare("SELECT photo_url FROM order_photos WHERE id = :id");
        $stmt->bindParam(':id', $photoId);
        $stmt->execute();
        $photo = $stmt->fetch();

        if (!$photo) {
            echo json_encode(['error' => 'Фото не найдено']);
            return;
        }

        // Удаляем объект из MinIO (если URL относится к MinIO)
        $info = MinioHelper::parseUrl($photo['photo_url']);
        if ($info) {
            try {
                MinioHelper::delete($info['bucket'], $info['key']);
            } catch (Exception $e) {
                error_log('MinIO delete error: ' . $e->getMessage());
            }
        }

        $db->prepare("DELETE FROM order_photos WHERE id = :id")
           ->execute([':id' => $photoId]);

        echo json_encode(['success' => true]);
    }

    // ── Загрузка медиафайла для портфолио ────────────────────────────────────
    // POST /api/admin/portfolio/upload  (multipart, поле 'media')
    // Возвращает { success: true, url: '...' } — URL вставляется в поле video_url формы.
    public static function uploadPortfolioMedia() {
        requireRole('admin');

        if (!isset($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['error' => 'Ошибка загрузки файла']);
            return;
        }

        $file = $_FILES['media'];

        $allowedTypes = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif',
            'video/mp4', 'video/webm', 'video/ogg',
        ];
        if (!in_array($file['type'], $allowedTypes)) {
            echo json_encode(['error' => 'Разрешены: JPG, PNG, WEBP, GIF, MP4, WEBM, OGG']);
            return;
        }

        if ($file['size'] > 100 * 1024 * 1024) {
            echo json_encode(['error' => 'Файл слишком большой. Максимум 100 МБ']);
            return;
        }

        $key = MinioHelper::generateKey('media', $file['name']);

        try {
            $url = MinioHelper::upload('portfolio', $key, $file['tmp_name'], $file['type']);
        } catch (Exception $e) {
            error_log('MinIO portfolio upload error: ' . $e->getMessage());
            echo json_encode(['error' => 'Не удалось загрузить файл в хранилище']);
            return;
        }

        echo json_encode(['success' => true, 'url' => $url]);
    }

    // ========== ОБЩИЕ НАСТРОЙКИ САЙТА ==========

    public static function getSettings() {
        self::checkAdmin();
        MinioHelper::ensurePublicRead('portfolio');
        MinioHelper::ensurePublicRead('order-photos');
        MinioHelper::ensurePublicRead('documents');
        $settings = new SiteSettings();
        echo json_encode(['success' => true, 'settings' => $settings->getAll()]);
    }

    public static function uploadAboutVideo() {
        self::checkAdmin();

        if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Ошибка загрузки файла']);
            return;
        }

        $file = $_FILES['video'];
        $allowed = ['video/mp4', 'video/webm', 'video/ogg'];
        if (!in_array($file['type'], $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Разрешены только видео: MP4, WEBM, OGG']);
            return;
        }
        if ($file['size'] > 200 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Файл слишком большой. Максимум 200 МБ']);
            return;
        }

        $key = MinioHelper::generateKey('site', $file['name']);

        try {
            $url = MinioHelper::upload('portfolio', $key, $file['tmp_name'], $file['type']);
        } catch (Exception $e) {
            error_log('MinIO about video upload error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Не удалось загрузить файл в хранилище']);
            return;
        }

        try {
            $settings = new SiteSettings();
            $settings->set('about_video_url', $url);
        } catch (Exception $e) {
            error_log('SiteSettings save error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Видео загружено, но не удалось сохранить настройку']);
            return;
        }

        echo json_encode(['success' => true, 'url' => $url]);
    }

    public static function uploadPrivacyPdf() {
        self::checkAdmin();

        if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Ошибка загрузки файла']);
            return;
        }

        $file = $_FILES['pdf'];
        $allowedMimes = ['application/pdf', 'application/x-pdf'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file['type'], $allowedMimes) && $ext !== 'pdf') {
            echo json_encode(['success' => false, 'error' => 'Разрешены только PDF-файлы']);
            return;
        }
        if ($file['size'] > 20 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Файл слишком большой. Максимум 20 МБ']);
            return;
        }

        $key = 'privacy-policy_' . time() . '.pdf';

        try {
            $url = MinioHelper::upload('documents', $key, $file['tmp_name'], 'application/pdf');
        } catch (Exception $e) {
            error_log('MinIO privacy PDF upload error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Не удалось загрузить файл в хранилище']);
            return;
        }

        $settings = new SiteSettings();
        $all = $settings->getAll();
        if (!empty($all['privacy_pdf_url'])) {
            $parsed = MinioHelper::parseUrl($all['privacy_pdf_url']);
            if ($parsed) {
                try { MinioHelper::delete($parsed['bucket'], $parsed['key']); } catch (Exception $e) {}
            }
        }

        $settings->set('privacy_pdf_url', $url);
        echo json_encode(['success' => true, 'url' => $url]);
    }

    public static function deletePrivacyPdf() {
        self::checkAdmin();

        $settings = new SiteSettings();
        $all = $settings->getAll();

        if (empty($all['privacy_pdf_url'])) {
            echo json_encode(['success' => false, 'error' => 'PDF не загружен']);
            return;
        }

        $parsed = MinioHelper::parseUrl($all['privacy_pdf_url']);
        if ($parsed) {
            try { MinioHelper::delete($parsed['bucket'], $parsed['key']); } catch (Exception $e) {}
        }

        $settings->set('privacy_pdf_url', '');
        echo json_encode(['success' => true]);
    }

}