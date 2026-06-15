<?php
// api/models/ServiceCategory.php

require_once __DIR__ . '/../config/database.php';

class ServiceCategory {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getAll() {
        $query = "SELECT category_id, name, sort_order, icon, show_on_home, home_media_url
                  FROM service_categories ORDER BY sort_order";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['show_on_home'] = (bool)$row['show_on_home'];
        }
        return $rows;
    }

    public function countHomeItems($excludeId = null) {
        $sql = "SELECT COUNT(*) FROM service_categories WHERE show_on_home = TRUE";
        if ($excludeId !== null) {
            $sql .= " AND category_id != :exclude_id";
        }
        $stmt = $this->conn->prepare($sql);
        if ($excludeId !== null) {
            $stmt->bindValue(':exclude_id', (int)$excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function create($name, $sortOrder, $icon = '', $showOnHome = false) {
        $query = "INSERT INTO service_categories (name, sort_order, icon, show_on_home)
                  VALUES (:name, :sort_order, :icon, :show_on_home)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':sort_order', $sortOrder);
        $stmt->bindParam(':icon', $icon);
        $show = $showOnHome ? 'true' : 'false';
        $stmt->bindParam(':show_on_home', $show);
        if ($stmt->execute()) {
            return ['success' => true, 'category_id' => $this->conn->lastInsertId()];
        }
        return ['error' => 'Ошибка создания категории'];
    }

    public function update($id, $name, $sortOrder, $icon = '', $showOnHome = false) {
        $query = "UPDATE service_categories
                  SET name = :name, sort_order = :sort_order, icon = :icon, show_on_home = :show_on_home
                  WHERE category_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':sort_order', $sortOrder);
        $stmt->bindParam(':icon', $icon);
        $show = $showOnHome ? 'true' : 'false';
        $stmt->bindParam(':show_on_home', $show);
        return ['success' => $stmt->execute()];
    }

    public function updateMedia($id, $url) {
        $stmt = $this->conn->prepare(
            "UPDATE service_categories SET home_media_url = :url WHERE category_id = :id"
        );
        $stmt->bindParam(':url', $url);
        $stmt->bindParam(':id', $id);
        return ['success' => $stmt->execute()];
    }

    public function getMediaUrl($id) {
        $stmt = $this->conn->prepare(
            "SELECT home_media_url FROM service_categories WHERE category_id = :id"
        );
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['home_media_url'] : null;
    }

    public function delete($id) {
        $query = "DELETE FROM service_categories WHERE category_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return ['success' => $stmt->execute()];
    }
}
