<?php
// Общие зависимости админских контроллеров.
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../helpers/MinioHelper.php';
require_once __DIR__ . '/../helpers/FfmpegHelper.php';
require_once __DIR__ . '/../models/Service.php';
require_once __DIR__ . '/../models/Portfolio.php';
require_once __DIR__ . '/../models/Client.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/CarBrand.php';
require_once __DIR__ . '/../models/CarModel.php';
require_once __DIR__ . '/../models/ServiceCategory.php';
require_once __DIR__ . '/../models/OrderStatus.php';
require_once __DIR__ . '/../models/SiteSettings.php';
