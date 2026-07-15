<?php
// api/controllers/AdminClientsController.php — клиенты
require_once __DIR__ . '/../config/admin_deps.php';

class AdminClientsController {

    private static function checkAdmin() {
        requireAdmin();
    }

    public static function updateClient($id) {
        self::checkAdmin();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        // Без проверок отсутствующие поля биндились как NULL: частичный PUT
        // либо ронял NOT NULL на имени/фамилии, либо молча стирал телефон и email.
        $firstName = trim($data['first_name'] ?? '');
        $lastName  = trim($data['last_name'] ?? '');
        $phone     = trim($data['phone_number'] ?? '') ?: null;
        $email     = trim($data['email'] ?? '') ?: null;

        if ($firstName === '' || $lastName === '') {
            echo json_encode(['success' => false, 'error' => 'Имя и фамилия обязательны']);
            return;
        }
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error' => 'Некорректный email']);
            return;
        }

        $conn = (new Database())->getConnection();

        $query = "UPDATE clients SET first_name = :first_name, last_name = :last_name, phone_number = :phone, email = :email WHERE client_id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':first_name', $firstName);
        $stmt->bindParam(':last_name', $lastName);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':email', $email);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Ошибка обновления']);
        }
    }

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
}
