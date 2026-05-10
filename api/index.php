<?php
// api/index.php

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/ServiceController.php';
require_once __DIR__ . '/controllers/PortfolioController.php';
require_once __DIR__ . '/controllers/AdminController.php';

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];
$path = trim(str_replace('/api/', '', parse_url($requestUri, PHP_URL_PATH)), '/');

// Публичные маршруты (без авторизации)
if ($path === 'services' && $requestMethod === 'GET') {
    ServiceController::getServices();
    exit;
}
if ($path === 'portfolio' && $requestMethod === 'GET') {
    PortfolioController::getPortfolio();
    exit;
}
if ($path === 'auth/login' && $requestMethod === 'POST') {
    AuthController::login();
    exit;
}
if ($path === 'auth/logout' && $requestMethod === 'POST') {
    AuthController::logout();
    exit;
}
if ($path === 'auth/me' && $requestMethod === 'GET') {
    AuthController::me();
    exit;
}
if ($path === 'auth/create-test-admin' && $requestMethod === 'POST') {
    AuthController::createTestAdmin();
    exit;
}

if ($path === 'order/create' && $requestMethod === 'POST') {
    require_once __DIR__ . '/controllers/OrderController.php';
    OrderController::createOrder();
    exit;
}
if ($path === 'validate-car' && $requestMethod === 'POST') {
    require_once __DIR__ . '/controllers/CarValidationController.php';
    CarValidationController::validateCar();
    exit;
}
// Обратная связь (публичный)
if ($path === 'feedback' && $requestMethod === 'POST') {
    require_once __DIR__ . '/controllers/FeedbackController.php';
    FeedbackController::sendFeedback();
    exit;
}
// Регистрация (публичный маршрут)
if ($path === 'auth/register' && $requestMethod === 'POST') {
    AuthController::register();
    exit;
}

// ========== ЛИЧНЫЙ КАБИНЕТ КЛИЕНТА ==========
// Все эти маршруты требуют авторизации (клиент или админ)

// Получить заказы клиента
if ($path === 'user/orders' && $requestMethod === 'GET') {
    require_once __DIR__ . '/controllers/ProfileController.php';
    ProfileController::getOrders();
    exit;
}

// Отменить заказ
if (preg_match('/^user\/orders\/(\d+)\/cancel$/', $path, $matches) && $requestMethod === 'POST') {
    require_once __DIR__ . '/controllers/ProfileController.php';
    ProfileController::cancelOrder($matches[1]);
    exit;
}

// Перенести заказ
if (preg_match('/^user\/orders\/(\d+)\/reschedule$/', $path, $matches) && $requestMethod === 'POST') {
    require_once __DIR__ . '/controllers/ProfileController.php';
    ProfileController::rescheduleOrder($matches[1]);
    exit;
}

// Получить прогресс заказов клиента
if ($path === 'user/orders/progress' && $requestMethod === 'GET') {
    require_once __DIR__ . '/controllers/ProfileController.php';
    ProfileController::getOrdersProgress();
    exit;
}

// Админские роуты для прогресса
if (preg_match('/^admin\/orders\/(\d+)\/progress$/', $path, $matches) && $requestMethod === 'GET') {
    AdminController::getOrderServicesWithProgress($matches[1]);
    exit;
}
if (preg_match('/^admin\/orders\/(\d+)\/services\/(\d+)\/progress$/', $path, $matches) && $requestMethod === 'PUT') {
    AdminController::updateServiceProgress($matches[1], $matches[2]);
    exit;
}

// Получить автомобили клиента
if ($path === 'user/cars' && $requestMethod === 'GET') {
    require_once __DIR__ . '/controllers/ProfileController.php';
    ProfileController::getCars();
    exit;
}

// Получить профиль клиента
if ($path === 'user/profile' && $requestMethod === 'GET') {
    require_once __DIR__ . '/controllers/ProfileController.php';
    ProfileController::getProfile();
    exit;
}

// Обновить профиль клиента
if ($path === 'user/profile' && $requestMethod === 'PUT') {
    require_once __DIR__ . '/controllers/ProfileController.php';
    ProfileController::updateProfile();
    exit;
}
if (preg_match('/^user\/orders\/(\d+)\/photos$/', $path, $matches) && $requestMethod === 'GET') {
    require_once __DIR__ . '/controllers/ProfileController.php';
    ProfileController::getClientOrderPhotos($matches[1]);
    exit;
}


// Все остальные маршруты требуют авторизации (админ)
// В начале каждого метода AdminController уже проверяется сессия, но можно добавить middleware

// Дашборд (админ)
if ($path === 'admin/dashboard' && $requestMethod === 'GET') {
    AdminController::getDashboardStats();
    exit;
}
if ($path === 'admin/change-password' && $requestMethod === 'POST') {
    AdminController::changePassword();
    exit;
}

// Обратная связь (админ)
if ($path === 'admin/feedbacks' && $requestMethod === 'GET') {
    require_once __DIR__ . '/controllers/FeedbackController.php';
    FeedbackController::getAllFeedbacks();
    exit;
}
if (preg_match('/^admin\/feedbacks\/(\d+)$/', $path, $matches) && $requestMethod === 'GET') {
    require_once __DIR__ . '/controllers/FeedbackController.php';
    FeedbackController::getFeedback($matches[1]);
    exit;
}
if (preg_match('/^admin\/feedbacks\/(\d+)\/status$/', $path, $matches) && $requestMethod === 'PUT') {
    require_once __DIR__ . '/controllers/FeedbackController.php';
    FeedbackController::updateFeedbackStatus($matches[1]);
    exit;
}
if (preg_match('/^admin\/feedbacks\/(\d+)$/', $path, $matches) && $requestMethod === 'DELETE') {
    require_once __DIR__ . '/controllers/FeedbackController.php';
    FeedbackController::deleteFeedback($matches[1]);
    exit;
}


// Услуги (админ)
if ($path === 'admin/services' && $requestMethod === 'GET') {
    AdminController::getServices();
    exit;
}
if ($path === 'admin/services' && $requestMethod === 'POST') {
    AdminController::addService();
    exit;
}
if (preg_match('/^admin\/services\/(\d+)$/', $path, $matches) && $requestMethod === 'PUT') {
    AdminController::updateService($matches[1]);
    exit;
}
if (preg_match('/^admin\/services\/(\d+)$/', $path, $matches) && $requestMethod === 'DELETE') {
    AdminController::deleteService($matches[1]);
    exit;
}

// Портфолио (админ)
if ($path === 'admin/portfolio' && $requestMethod === 'GET') {
    AdminController::getPortfolio();
    exit;
}
if ($path === 'admin/portfolio' && $requestMethod === 'POST') {
    AdminController::addPortfolio();
    exit;
}
if (preg_match('/^admin\/portfolio\/(\d+)$/', $path, $matches) && $requestMethod === 'PUT') {
    AdminController::updatePortfolio($matches[1]);
    exit;
}
if (preg_match('/^admin\/portfolio\/(\d+)$/', $path, $matches) && $requestMethod === 'DELETE') {
    AdminController::deletePortfolio($matches[1]);
    exit;
}

// Клиенты (админ)

if ($path === 'admin/clients' && $requestMethod === 'GET') {
    AdminController::getClientsList();
    exit;
}
if (preg_match('/^admin\/clients\/(\d+)$/', $path, $matches) && $requestMethod === 'GET') {
    AdminController::getClientDetails($matches[1]);
    exit;
}
if ($path === 'admin/clients' && $requestMethod === 'GET') {
    AdminController::getClients();
    exit;
}
if ($path === 'admin/clients' && $requestMethod === 'POST') {
    AdminController::addClient();
    exit;
}
if (preg_match('/^admin\/clients\/(\d+)$/', $path, $matches) && $requestMethod === 'PUT') {
    AdminController::updateClient($matches[1]);
    exit;
}
if (preg_match('/^admin\/clients\/(\d+)$/', $path, $matches) && $requestMethod === 'DELETE') {
    AdminController::deleteClient($matches[1]);
    exit;
}
if ($path === 'admin/clients/export' && $requestMethod === 'GET') {
    AdminController::exportClientsCSV();
    exit;
}

// Заказы (админ)
if ($path === 'admin/orders' && $requestMethod === 'GET') {
    AdminController::getOrders();
    exit;
}
if ($path === 'admin/orders/export' && $requestMethod === 'GET') {
    AdminController::exportOrders();
    exit;
}
if (preg_match('/^admin\/orders\/(\d+)$/', $path, $matches) && $requestMethod === 'PUT') {
    AdminController::updateOrder($matches[1]);
    exit;
}
if (preg_match('/^admin\/orders\/(\d+)$/', $path, $matches) && $requestMethod === 'DELETE') {
    AdminController::deleteOrder($matches[1]);
    exit;
}
if (preg_match('/^admin\/orders\/(\d+)\/photos$/', $path, $matches) && $requestMethod === 'GET') {
    AdminController::getOrderPhotos($matches[1]);
    exit;
}
if (preg_match('/^admin\/orders\/(\d+)\/photos\/upload$/', $path, $matches) && $requestMethod === 'POST') {
    AdminController::uploadOrderPhoto($matches[1]);
    exit;
}
if (preg_match('/^admin\/photos\/(\d+)$/', $path, $matches) && $requestMethod === 'DELETE') {
    AdminController::deleteOrderPhoto($matches[1]);
    exit;
}


// Марки авто (админ)
if ($path === 'admin/car-brands' && $requestMethod === 'GET') {
    AdminController::getCarBrands();
    exit;
}
if ($path === 'admin/car-brands' && $requestMethod === 'POST') {
    AdminController::addCarBrand();
    exit;
}
if (preg_match('/^admin\/car-brands\/(\d+)$/', $path, $matches) && $requestMethod === 'PUT') {
    AdminController::updateCarBrand($matches[1]);
    exit;
}
if (preg_match('/^admin\/car-brands\/(\d+)$/', $path, $matches) && $requestMethod === 'DELETE') {
    AdminController::deleteCarBrand($matches[1]);
    exit;
}

// Модели авто (админ)
if ($path === 'admin/car-models' && $requestMethod === 'GET') {
    AdminController::getCarModels();
    exit;
}
if ($path === 'admin/car-models' && $requestMethod === 'POST') {
    AdminController::addCarModel();
    exit;
}
if (preg_match('/^admin\/car-models\/(\d+)$/', $path, $matches) && $requestMethod === 'PUT') {
    AdminController::updateCarModel($matches[1]);
    exit;
}
if (preg_match('/^admin\/car-models\/(\d+)$/', $path, $matches) && $requestMethod === 'DELETE') {
    AdminController::deleteCarModel($matches[1]);
    exit;
}

// Категории услуг (админ)
if ($path === 'admin/service-categories' && $requestMethod === 'GET') {
    AdminController::getServiceCategories();
    exit;
}
if ($path === 'admin/service-categories' && $requestMethod === 'POST') {
    AdminController::addServiceCategory();
    exit;
}
if (preg_match('/^admin\/service-categories\/(\d+)$/', $path, $matches) && $requestMethod === 'PUT') {
    AdminController::updateServiceCategory($matches[1]);
    exit;
}
if (preg_match('/^admin\/service-categories\/(\d+)$/', $path, $matches) && $requestMethod === 'DELETE') {
    AdminController::deleteServiceCategory($matches[1]);
    exit;
}

// Сотрудники (админ)
if ($path === 'admin/employees' && $requestMethod === 'GET') {
    AdminController::getEmployees();
    exit;
}
if ($path === 'admin/employees' && $requestMethod === 'POST') {
    AdminController::addEmployee();
    exit;
}
if (preg_match('/^admin\/employees\/(\d+)$/', $path, $matches) && $requestMethod === 'DELETE') {
    AdminController::deleteEmployee($matches[1]);
    exit;
}

// Статусы заказов (админ)
if ($path === 'admin/order-statuses' && $requestMethod === 'GET') {
    AdminController::getOrderStatuses();
    exit;
}
if (preg_match('/^admin\/orders\/(\d+)\/status$/', $path, $matches) && $requestMethod === 'PUT') {
    AdminController::updateOrderStatus($matches[1]);
    exit;
}

// Получение категорий услуг (для админки)
if ($path === 'admin/service-categories' && $requestMethod === 'GET') {
    AdminController::getServiceCategories();
    exit;
}
// Получение услуг по категории (для админки)
if (preg_match('/^admin\/services-by-category\/(\d+)$/', $path, $matches) && $requestMethod === 'GET') {
    AdminController::getServicesByCategory($matches[1]);
    exit;
}

// Публичные маршруты
//if ($path === 'categories' && $requestMethod === 'GET') {
   // CategoryController::getCategories();
   // exit;
//}
if (preg_match('/^services-by-category\/(\d+)$/', $path, $matches) && $requestMethod === 'GET') {
    ServiceController::getServicesByCategory($matches[1]);
    exit;
}
if ($path === 'car-brands' && $requestMethod === 'GET') {
    require_once __DIR__ . '/controllers/CarController.php';
    CarController::getBrands();
    exit;
}
if ($path === 'car-models' && $requestMethod === 'GET') {
    require_once __DIR__ . '/controllers/CarController.php';
    CarController::getModels();
    exit;
}
if ($path === 'categories' && $requestMethod === 'GET') {
    require_once __DIR__ . '/controllers/CategoryController.php';
    CategoryController::getCategories();
    exit;
}


http_response_code(404);
echo json_encode(['error' => 'Endpoint not found']);