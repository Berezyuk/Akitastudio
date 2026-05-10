<?php
// api/controllers/FeedbackController.php

require_once __DIR__ . '/../config/database.php';

class FeedbackController {
    
    // Отправка формы обратной связи (публичный эндпоинт)
    public static function sendFeedback() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Валидация
        if (empty($data['name']) || empty($data['phone']) || empty($data['message'])) {
            echo json_encode(['error' => 'Заполните обязательные поля']);
            return;
        }
        
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
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Не авторизован']);
            exit;
        }
    }
    
    // Получить все заявки
    public static function getAllFeedbacks() {
        self::checkAdmin();
        
        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;
        
        $db = new Database();
        $conn = $db->getConnection();
        
        $sql = "SELECT * FROM feedbacks WHERE 1=1";
        $params = [];
        
        if ($status && $status !== 'all') {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }
        
        if ($search) {
            $sql .= " AND (name ILIKE :search OR phone ILIKE :search OR email ILIKE :search OR message ILIKE :search)";
            $params[':search'] = "%$search%";
        }
        
        $sql .= " ORDER BY feedback_id DESC";
        
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        
        $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'feedbacks' => $feedbacks]);
    }
    
    // Получить одну заявку
    public static function getFeedback($id) {
        self::checkAdmin();
        
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT * FROM feedbacks WHERE feedback_id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $feedback = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$feedback) {
            echo json_encode(['error' => 'Заявка не найдена']);
            return;
        }
        
        echo json_encode(['success' => true, 'feedback' => $feedback]);
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