<?php
// api/controllers/AdminOrdersController.php — заказы, статусы, прогресс, фото
require_once __DIR__ . '/../config/admin_deps.php';

class AdminOrdersController {

    private static function checkAdmin() {
        requireAdmin();
    }

    // Отдаём все заказы разом: пагинация переехала на клиент. Иначе фильтры,
    // сортировка и счётчики в админке видели только текущую страницу и врали
    // (поиск не находил заказ со второй страницы при «320 записей» в пагинаторе).
    // ponytail: весь список в один ответ; фильтровать на сервере, если заказов станет >5k
    public static function getOrders() {
        self::checkAdmin();

        $db = (new Database())->getConnection();

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
             ORDER BY o.order_date DESC, o.order_id DESC"
        );
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'orders'  => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    public static function updateOrderStatus($id) {
        self::checkAdmin();
        $data     = json_decode(file_get_contents('php://input'), true);
        $statusId = $data['status_id'] ?? null;   // валидацию делает Order::updateStatus
        $order    = new Order();
        echo json_encode($order->updateStatus($id, $statusId));
    }

    // Статусы заказов (для выпадающих списков)
    public static function getOrderStatuses() {
        self::checkAdmin();
        $status = new OrderStatus();
        $statuses = $status->getAll();
        echo json_encode(['success' => true, 'statuses' => $statuses]);
    }

    // Получить услуги с прогрессом для конкретного заказа
    public static function getOrderServicesWithProgress($orderId) {
        $admin = requireRole('admin');

        $db = (new Database())->getConnection();

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
        $realMime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($realMime, $allowedTypes)) {
            echo json_encode(['error' => 'Разрешены только JPG, PNG и WEBP']);
            return;
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            echo json_encode(['error' => 'Файл слишком большой. Максимум 10 МБ']);
            return;
        }

        $key = MinioHelper::generateKey("orders/{$orderId}", $file['name']);

        try {
            $photoUrl = MinioHelper::upload('order-photos', $key, $file['tmp_name'], $realMime);
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

        // Клиенту — подписанная ссылка: бакет приватный, прямой URL не откроется.
        echo json_encode(['success' => true, 'photo_url' => MinioHelper::refreshUrl($photoUrl)]);
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
        // В БД лежит прямой адрес объекта, а бакет приватный — отдаём временную
        // подписанную ссылку.
        foreach ($photos as &$p) {
            $p['photo_url'] = MinioHelper::refreshUrl($p['photo_url']);
        }
        unset($p);

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
}
