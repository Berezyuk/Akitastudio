<?php
// api/controllers/FeedbackController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../helpers/RateLimiter.php';

class FeedbackController {
    
    // Отправка формы обратной связи (публичный эндпоинт)
    public static function sendFeedback() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Валидация
        if (empty($data['name']) || empty($data['phone'])) {
            echo json_encode(['error' => 'Заполните обязательные поля']);
            return;
        }

        // Троттл: публичный эндпоинт пишет в БД, без лимита — флуд inbox.
        $conn = (new Database())->getConnection();
        if (RateLimiter::tooManyAttempts($conn, 'feedback', 20, 900)) {
            http_response_code(429);
            echo json_encode(['error' => 'Слишком много сообщений. Попробуйте позже.']);
            return;
        }
        RateLimiter::hit($conn, 'feedback');

        // Очистка телефона (только цифры)
        $phone = preg_replace('/[^0-9]/', '', $data['phone']);
        
        // Обрезаем длинные сообщения до 255 символов
        $message = mb_substr($data['message'], 0, 255);
        
        $db = new Database();
        $conn = $db->getConnection();
        
        $query = "INSERT INTO feedbacks (name, phone, email, message, status) 
                  VALUES (:name, :phone, :email, :message, 'new')";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':message', $message);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Сообщение отправлено']);
        } else {
            echo json_encode(['error' => 'Ошибка при сохранении']);
        }
    }
    
    // ========== АДМИНСКИЕ МЕТОДЫ ==========
    
    private static function checkAdmin() {
        requireAdmin();
    }
    
    // Получить все заявки
    public static function getAllFeedbacks() {
        self::checkAdmin();

        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;
        $page   = max(1, (int)($_GET['page']  ?? 1));
        $limit  = min(100, max(1, (int)($_GET['limit'] ?? 30)));
        $offset = ($page - 1) * $limit;

        $conn   = (new Database())->getConnection();
        $where  = '1=1';
        $params = [];

        if ($status && $status !== 'all') {
            $where .= ' AND status = :status';
            $params[':status'] = $status;
        }
        if ($search) {
            $where .= ' AND (name ILIKE :search OR phone ILIKE :search OR email ILIKE :search OR message ILIKE :search)';
            $params[':search'] = "%{$search}%";
        }

        $stmtCount = $conn->prepare("SELECT COUNT(*) FROM feedbacks WHERE {$where}");
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $stmtData = $conn->prepare(
            "SELECT * FROM feedbacks WHERE {$where} ORDER BY feedback_id DESC LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $stmtData->bindValue($k, $v);
        }
        $stmtData->bindValue(':limit',  $limit,  \PDO::PARAM_INT);
        $stmtData->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmtData->execute();

        echo json_encode([
            'success'   => true,
            'feedbacks' => $stmtData->fetchAll(PDO::FETCH_ASSOC),
            'total'     => $total,
            'page'      => $page,
            'limit'     => $limit,
        ]);
    }
    
    // Обновить статус и заметки
    public static function updateFeedbackStatus($id) {
        self::checkAdmin();
        
        $data = json_decode(file_get_contents('php://input'), true);
        $status = $data['status'] ?? null;
        $adminNotes = $data['admin_notes'] ?? null;
        
        // Обрезаем заметки до 255 символов
        if ($adminNotes) {
            $adminNotes = mb_substr($adminNotes, 0, 255);
        }
        
        $db = new Database();
        $conn = $db->getConnection();
        
        $query = "UPDATE feedbacks SET status = :status, admin_notes = :admin_notes WHERE feedback_id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':admin_notes', $adminNotes);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Ошибка обновления']);
        }
    }
    
    // Удалить заявку
    public static function deleteFeedback($id) {
        self::checkAdmin();
        
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("DELETE FROM feedbacks WHERE feedback_id = :id");
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Ошибка удаления']);
        }
    }
}