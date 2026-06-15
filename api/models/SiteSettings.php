<?php
require_once __DIR__ . '/../config/database.php';

class SiteSettings {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getAll() {
        $stmt = $this->conn->query("SELECT key, value FROM site_settings");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['key']] = $row['value'];
        }
        return $result;
    }

    public function set($key, $value) {
        $stmt = $this->conn->prepare(
            "INSERT INTO site_settings (key, value) VALUES (:key, :value)
             ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value"
        );
        $stmt->bindParam(':key', $key);
        $stmt->bindParam(':value', $value);
        return $stmt->execute();
    }
}
