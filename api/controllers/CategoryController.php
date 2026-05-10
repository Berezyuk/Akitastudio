<?php
// api/controllers/CategoryController.php

require_once __DIR__ . '/../models/ServiceCategory.php';

class CategoryController {
    public static function getCategories() {
        $cat = new ServiceCategory();
        $categories = $cat->getAll();
        echo json_encode(['success' => true, 'categories' => $categories]);
    }
}