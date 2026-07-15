<?php
require_once __DIR__ . '/env.php';

class Database {
    // Одно соединение на весь запрос (PHP-FPM — процесс на запрос),
    // вместо нового PDO на каждый new Database()->getConnection().
    private static $conn = null;

    public function getConnection() {
        if (self::$conn !== null) {
            return self::$conn;
        }

        $host     = getenv('DB_HOST')     ?: 'localhost';
        $port     = getenv('DB_PORT')     ?: '5433';
        $dbname   = getenv('DB_NAME')     ?: 'AkitaStudio';
        $user     = getenv('DB_USER')     ?: 'postgres';
        $password = getenv('DB_PASSWORD') ?: '';

        try {
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
            $conn = new PDO($dsn, $user, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }

        self::$conn = $conn;
        return self::$conn;
    }
}
