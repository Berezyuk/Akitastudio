<?php
// api/controllers/ServiceController.php

require_once __DIR__ . '/../models/Service.php';

class ServiceController {
    
    public static function getServices() {
        $service = new Service();
        $services = $service->getActive();
        echo json_encode(['success' => true, 'services' => $services]);
    }
}

