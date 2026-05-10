<?php
// api/controllers/ServiceController.php

require_once __DIR__ . '/../models/Service.php';

class ServiceController {
    
    public static function getServices() {
        $service = new Service();
        $services = $service->getActive();
        echo json_encode(['success' => true, 'services' => $services]);
    }

    public static function getServicesByCategory($categoryId) {
    $service = new Service();
    $services = $service->getByCategory($categoryId);
    echo json_encode(['success' => true, 'services' => $services]);
    }
}

