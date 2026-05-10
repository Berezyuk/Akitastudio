<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

class ProfileController {
    
    // Получить заказы текущего клиента
    public static function getOrders() {
    $user = authenticate();
    
    if(!$user['client_id']) {
        echo json_encode(['error' => 'Клиент не найден']);
        return;
    }
    
    $db = (new Database())->getConnection();
    
    $query = "SELECT 
                o.order_id, 
                o.order_date, 
                o.desired_date, 
                o.desired_time, 
                o.total_price, 
                o.status_id, 
                os.name as status_name,
                cb.name as car_brand,
                cm.name as car_model,
                STRING_AGG(DISTINCT s.name, ', ') as service_names
              FROM orders o
              LEFT JOIN order_services osrv ON o.order_id = osrv.order_id
              LEFT JOIN services s ON osrv.service_id = s.service_id
              LEFT JOIN order_statuses os ON o.status_id = os.status_id
              LEFT JOIN car_brands cb ON o.brand_id = cb.brand_id
              LEFT JOIN car_models cm ON o.model_id = cm.model_id
              WHERE o.client_id = :client_id
              GROUP BY o.order_id, os.name, cb.name, cm.name
              ORDER BY o.order_date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':client_id', $user['client_id']);
    $stmt->execute();
    
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'orders' => $orders]);
    }
    
    // Отменить заказ (если до желаемой даты больше 1 часа)
    public static function cancelOrder($orderId) {
        $user = authenticate();
        
        $db = (new Database())->getConnection();
        
        // Проверяем, что заказ принадлежит клиенту
        $check = $db->prepare("SELECT desired_date, desired_time, status_id FROM orders WHERE order_id = :order_id AND client_id = :client_id");
        $check->bindParam(':order_id', $orderId);
        $check->bindParam(':client_id', $user['client_id']);
        $check->execute();
        
        if($check->rowCount() == 0) {
            echo json_encode(['error' => 'Заказ не найден']);
            return;
        }
        
        $order = $check->fetch();
        
        // Нельзя отменить уже выполненный или отменённый заказ
        if($order['status_id'] == 4 || $order['status_id'] == 5) {
            echo json_encode(['error' => 'Этот заказ нельзя отменить']);
            return;
        }
        
        // Если нет желаемой даты, пропускаем проверку времени
        if($order['desired_date'] && $order['desired_time']) {
            $desiredDateTime = new DateTime($order['desired_date'] . ' ' . $order['desired_time']);
            $now = new DateTime();
            $diff = $now->diff($desiredDateTime);
            $hoursLeft = ($diff->days * 24) + $diff->h + ($diff->i / 60);
            
            if($hoursLeft < 1) {
                echo json_encode(['error' => 'Отмена недоступна: до записи осталось меньше часа']);
                return;
            }
        }
        
        // Отмена заказа (статус 5 = Отменён)
        $update = $db->prepare("UPDATE orders SET status_id = 5 WHERE order_id = :order_id");
        $update->bindParam(':order_id', $orderId);
        $update->execute();
        
        echo json_encode(['success' => true]);
    }
    
    // Перенести заказ
    public static function rescheduleOrder($orderId) {
        $user = authenticate();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if(empty($data['desired_date']) || empty($data['desired_time'])) {
            echo json_encode(['error' => 'Укажите новую дату и время']);
            return;
        }
        
        $db = (new Database())->getConnection();
        
        // Проверяем, что заказ принадлежит клиенту
        $check = $db->prepare("SELECT status_id FROM orders WHERE order_id = :order_id AND client_id = :client_id");
        $check->bindParam(':order_id', $orderId);
        $check->bindParam(':client_id', $user['client_id']);
        $check->execute();
        
        if($check->rowCount() == 0) {
            echo json_encode(['error' => 'Заказ не найден']);
            return;
        }
        
        $order = $check->fetch();
        
        // Нельзя переносить выполненный или отменённый заказ
        if($order['status_id'] == 4 || $order['status_id'] == 5) {
            echo json_encode(['error' => 'Этот заказ нельзя перенести']);
            return;
        }
        
        // Обновляем дату и время (статус сбрасываем на "Ожидание")
        $update = $db->prepare("UPDATE orders SET desired_date = :desired_date, desired_time = :desired_time, status_id = 1 WHERE order_id = :order_id");
        $update->bindParam(':desired_date', $data['desired_date']);
        $update->bindParam(':desired_time', $data['desired_time']);
        $update->bindParam(':order_id', $orderId);
        $update->execute();
        
        echo json_encode(['success' => true]);
    }
    
    // Получить автомобили клиента (из истории заказов)
    // Получить автомобили клиента (из истории заказов)
public static function getCars() {
    $user = authenticate();

    $db = (new Database())->getConnection();

    $query = "SELECT DISTINCT cb.name as brand_name, cm.name as model_name
              FROM orders o
              JOIN car_brands cb ON o.brand_id = cb.brand_id
              JOIN car_models cm ON o.model_id = cm.model_id
              WHERE o.client_id = :client_id
              ORDER BY o.order_date DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':client_id', $user['client_id']);
    $stmt->execute();

    $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'cars' => $cars]);
    }
    
    // Получить данные профиля
    public static function getProfile() {
        $user = authenticate();
        
        $db = (new Database())->getConnection();
        
        $query = "SELECT c.first_name, c.last_name, c.patronymic, c.phone_number, c.email 
                  FROM clients c
                  WHERE c.user_id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user['user_id']);
        $stmt->execute();
        
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'profile' => $profile]);
    }
    
    // Обновить профиль
        public static function updateProfile() {
        $user = authenticate();
        $data = json_decode(file_get_contents('php://input'), true);
        
        $db = (new Database())->getConnection();
        
        $query = "UPDATE clients SET 
                  first_name = :first_name,
                  last_name = :last_name,
                  patronymic = :patronymic,
                  email = :email
                  WHERE user_id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':first_name', $data['first_name']);
        $stmt->bindParam(':last_name', $data['last_name']);
        $stmt->bindParam(':patronymic', $data['patronymic']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':user_id', $user['user_id']);
        $stmt->execute();
        
        // Обновляем имя в сессии
        $_SESSION['name'] = $data['first_name'] . ' ' . $data['last_name'];
        
        echo json_encode(['success' => true]);
    }

    // Получить прогресс по заказам клиента
    public static function getOrdersProgress() {
    $user = authenticate();
    
    if(!$user['client_id']) {
        echo json_encode(['error' => 'Клиент не найден']);
        return;
    }
    
    $db = (new Database())->getConnection();
    
    $query = "SELECT osp.order_id, osp.service_id, osp.progress_percent, osp.status, s.name as service_name
              FROM order_services_progress osp
              JOIN services s ON osp.service_id = s.service_id
              JOIN orders o ON osp.order_id = o.order_id
              WHERE o.client_id = :client_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':client_id', $user['client_id']);
    $stmt->execute();
    
    $progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'progress' => $progress]);
    }

    // Получить фото по заказам клиента
    
    public static function getClientOrderPhotos($orderId) {
    $user = authenticate();
    
    if(!$user['client_id']) {
        echo json_encode(['error' => 'Клиент не найден']);
        return;
    }
    
    $db = (new Database())->getConnection();
    
    // Проверяем, что заказ принадлежит клиенту
    $check = $db->prepare("SELECT order_id FROM orders WHERE order_id = :order_id AND client_id = :client_id");
    $check->bindParam(':order_id', $orderId);
    $check->bindParam(':client_id', $user['client_id']);
    $check->execute();
    
    if($check->rowCount() == 0) {
        echo json_encode(['error' => 'Заказ не найден']);
        return;
    }
    
    $query = "SELECT id, photo_url, caption 
              FROM order_photos 
              WHERE order_id = :order_id 
              ORDER BY sort_order ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'photos' => $photos]);
    }
}