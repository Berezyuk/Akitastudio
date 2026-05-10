<?php
// api/controllers/AdminController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../models/Service.php';
require_once __DIR__ . '/../models/Portfolio.php';
require_once __DIR__ . '/../models/Client.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/CarBrand.php';
require_once __DIR__ . '/../models/CarModel.php';
require_once __DIR__ . '/../models/ServiceCategory.php';
require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../models/OrderStatus.php';

class AdminController {
    
    // Проверка роли admin (сессия)
    private static function checkAdmin() {
        if(!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Не авторизован']);
            exit;
        }
        // Здесь можно дополнительно проверить роль, но у нас только админ
        return true;
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
        $result = $portfolio->create($data);
        echo json_encode($result);
    }
    
    public static function updatePortfolio($id) {
    self::checkAdmin();
    $data = json_decode(file_get_contents('php://input'), true);
    $portfolio = new Portfolio();
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
    
    

    public static function updateOrderStatus($id) {
    self::checkAdmin();
    $data = json_decode(file_get_contents('php://input'), true);
    $statusId = $data['status_id'];
    $order = new Order();
    $result = $order->updateStatus($id, $statusId);
    echo json_encode($result);
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
        $result = $cat->create($data['name'], $data['sort_order']);
        echo json_encode($result);
    }
    
    public static function updateServiceCategory($id) {
        self::checkAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $cat = new ServiceCategory();
        $result = $cat->update($id, $data['name'], $data['sort_order']);
        echo json_encode($result);
    }
    
    public static function deleteServiceCategory($id) {
        self::checkAdmin();
        $cat = new ServiceCategory();
        $result = $cat->delete($id);
        echo json_encode($result);
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
    $order = new Order();
    $orders = $order->getAllWithDetails(); // вызываем правильный метод
    echo json_encode(['success' => true, 'orders' => $orders]);
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
    $db = new Database();
    $conn = $db->getConnection();

    $sql = "SELECT client_id, first_name, last_name, patronymic, phone_number, email 
            FROM clients WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (first_name ILIKE :search OR last_name ILIKE :search OR phone_number ILIKE :search OR email ILIKE :search)";
        $params[':search'] = "%$search%";
    }

    $sql .= " ORDER BY client_id DESC";
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'clients' => $clients]);
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

    // Количество новых заказов за сегодня (не просмотренных – но пока просто за сегодня)
    $stmt = $conn->query("SELECT COUNT(*) as count FROM orders WHERE order_date = CURRENT_DATE");
    $newOrdersToday = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Количество заказов в статусе "ожидание" (status_id = 1)
    $stmt = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status_id = 1");
    $pendingOrders = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Данные для графика (последние 7 дней)
    $chartData = ['labels' => [], 'values' => []];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $label = date('d.m', strtotime($date));
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE order_date = :date");
        $stmt->bindParam(':date', $date);
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $chartData['labels'][] = $label;
        $chartData['values'][] = $count;
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
    
    echo json_encode(['success' => true]);
    }
    
    // Загрузить фото для заказа
public static function uploadOrderPhoto($orderId) {
    $admin = requireRole('admin');
    
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'Ошибка загрузки файла']);
        return;
    }
    
    $file = $_FILES['photo'];
    $caption = $_POST['caption'] ?? '';
    
    // Проверка типа файла
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['error' => 'Можно загружать только JPG, PNG или WEBP']);
        return;
    }
    
    // Проверка размера (макс 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['error' => 'Файл слишком большой. Максимум 5MB']);
        return;
    }
    
    // Создаём уникальное имя файла
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = uniqid() . '_' . time() . '.' . $extension;
    $uploadPath = __DIR__ . '/../uploads/order_photos/' . $fileName;
    
    // Перемещаем файл
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        $db = (new Database())->getConnection();
        
        $photoUrl = '/api/uploads/order_photos/' . $fileName;
        
        $query = "INSERT INTO order_photos (order_id, photo_url, caption, uploaded_by, sort_order) 
                  VALUES (:order_id, :photo_url, :caption, 'admin', 
                  (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM order_photos WHERE order_id = :order_id))";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':order_id', $orderId);
        $stmt->bindParam(':photo_url', $photoUrl);
        $stmt->bindParam(':caption', $caption);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'photo_url' => $photoUrl]);
    } else {
        echo json_encode(['error' => 'Не удалось сохранить файл']);
    }
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

// Удалить фото
   public static function deleteOrderPhoto($photoId) {
    $admin = requireRole('admin');
    
    $db = (new Database())->getConnection();
    
    // Получаем путь к файлу
    $query = "SELECT photo_url FROM order_photos WHERE id = :photo_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':photo_id', $photoId);
    $stmt->execute();
    $photo = $stmt->fetch();
    
    if ($photo) {
        // Удаляем физический файл
        $filePath = __DIR__ . '/../' . str_replace('/api/', '', $photo['photo_url']);
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Удаляем запись из БД
        $delete = $db->prepare("DELETE FROM order_photos WHERE id = :photo_id");
        $delete->bindParam(':photo_id', $photoId);
        $delete->execute();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Фото не найдено']);
    }
    }

}