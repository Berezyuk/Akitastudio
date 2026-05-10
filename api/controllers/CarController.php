<?php
// api/controllers/CarController.php

require_once __DIR__ . '/../models/CarBrand.php';
require_once __DIR__ . '/../models/CarModel.php';

class CarController {
    public static function getBrands() {
        $brand = new CarBrand();
        $brands = $brand->getAll();
        echo json_encode(['success' => true, 'brands' => $brands]);
    }
    
    public static function getModels() {
        $brandId = isset($_GET['brand_id']) ? (int)$_GET['brand_id'] : null;
        if (!$brandId) {
            echo json_encode(['error' => 'brand_id is required']);
            return;
        }
        $model = new CarModel();
        $models = $model->getByBrand($brandId);
        echo json_encode(['success' => true, 'models' => $models]);
    }
}