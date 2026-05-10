<?php
// api/controllers/PortfolioController.php

require_once __DIR__ . '/../models/Portfolio.php';

class PortfolioController {
    
    public static function getPortfolio() {
        $portfolio = new Portfolio();
        $categoryId = isset($_GET['category']) ? (int)$_GET['category'] : null;
        $serviceId = isset($_GET['service']) ? (int)$_GET['service'] : null;
        $items = $portfolio->getActive($categoryId, $serviceId);
        echo json_encode(['success' => true, 'portfolio' => $items]);
    }
}