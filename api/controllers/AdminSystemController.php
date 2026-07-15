<?php
// api/controllers/AdminSystemController.php — сотрудники, дашборд, пароль, настройки сайта
require_once __DIR__ . '/../config/admin_deps.php';

class AdminSystemController {

    private static function checkAdmin() {
        requireAdmin();
    }

    // ========== ДАШБОРД ==========
    public static function getDashboardStats() {
        self::checkAdmin();
        $db = new Database();
        $conn = $db->getConnection();

        // Заказы сегодня.
        // order_date — TIMESTAMP, а CURRENT_DATE приводится к полуночи, поэтому
        // сравнение без ::date совпадало только с заказом, созданным ровно в 00:00:00.
        $stmt = $conn->query("SELECT COUNT(*) as count FROM orders WHERE order_date::date = CURRENT_DATE");
        $todayOrders = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Активные заказы: 1-Новый, 2-В работе, 3-Готово (не выдан и не отменён).
        // Прежний комментарий описывал несуществующие статусы («2-подтверждён»).
        $stmt = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status_id IN (1,2,3)");
        $activeOrders = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Выручка за текущий месяц — только выданные заказы (4): машина отдана,
        // деньги получены. Раньше фильтра по статусу не было вовсе, и в выручку
        // попадали отменённые и ещё не начатые заказы.
        // Считается по дате создания заказа — даты выдачи в схеме нет.
        $stmt = $conn->query("SELECT COALESCE(SUM(total_price), 0) as total FROM orders WHERE status_id = 4 AND EXTRACT(YEAR FROM order_date) = EXTRACT(YEAR FROM CURRENT_DATE) AND EXTRACT(MONTH FROM order_date) = EXTRACT(MONTH FROM CURRENT_DATE)");
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
        // cnt — все заказы за день; revenue — только выданные (4), как в плитке
        // «Выручка за месяц», иначе график и плитка расходятся.
        $stmt = $conn->prepare(
            "SELECT order_date::date::text AS day, COUNT(*) AS cnt,
                    COALESCE(SUM(total_price) FILTER (WHERE status_id = 4), 0) AS revenue
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

    // ========== ОБЩИЕ НАСТРОЙКИ САЙТА ==========
    public static function getSettings() {
        self::checkAdmin();
        // order-photos сюда не входит: фото чужих машин отдаются только по
        // временной подписанной ссылке, публичная политика ему не нужна.
        MinioHelper::ensurePublicRead('portfolio');
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
        $realMime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($realMime, $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Разрешены только видео: MP4, WEBM, OGG']);
            return;
        }
        if ($file['size'] > 200 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Файл слишком большой. Максимум 200 МБ']);
            return;
        }

        $uploadPath = $file['tmp_name'];
        $uploadMime = $realMime;
        $transcodedPath = FfmpegHelper::transcodeToH264($file['tmp_name'], $realMime);
        if ($transcodedPath) {
            $uploadPath = $transcodedPath;
            $uploadMime = 'video/mp4';
        }

        $key = MinioHelper::generateKey('site', $transcodedPath ? 'video.mp4' : $file['name']);

        try {
            $url = MinioHelper::upload('portfolio', $key, $uploadPath, $uploadMime);
        } catch (Exception $e) {
            error_log('MinIO about video upload error: ' . $e->getMessage());
            if ($transcodedPath && file_exists($transcodedPath)) unlink($transcodedPath);
            echo json_encode(['success' => false, 'error' => 'Не удалось загрузить файл в хранилище']);
            return;
        } finally {
            if ($transcodedPath && file_exists($transcodedPath)) unlink($transcodedPath);
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
        // $file['type'] и расширение задаёт клиент. Проверяем реальный MIME, как
        // в остальных загрузчиках.
        $allowedMimes = ['application/pdf', 'application/x-pdf'];
        $realMime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($realMime, $allowedMimes)) {
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
