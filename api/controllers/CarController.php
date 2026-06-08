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
        $brandName = isset($_GET['brand_name']) ? trim($_GET['brand_name']) : null;
        $brandId   = isset($_GET['brand_id'])   ? (int)$_GET['brand_id']   : null;
        $carModel  = new CarModel();

        if ($brandName) {
            $models = $carModel->getByBrandName($brandName);
            echo json_encode(['success' => true, 'models' => $models]);
            return;
        }
        if (!$brandId) {
            echo json_encode(['error' => 'brand_id or brand_name is required']);
            return;
        }
        $models = $carModel->getByBrand($brandId);
        echo json_encode(['success' => true, 'models' => $models]);
    }
}