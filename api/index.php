<?php
// api/index.php

require_once __DIR__ . '/config/env.php';

// Любая необработанная ошибка -> 500 + нейтральный JSON, детали только в лог.
// Иначе клиент получает либо стек-трейс (display_errors=On), либо пустой 200.
set_exception_handler(function (Throwable $e) {
    error_log(sprintf('Uncaught %s: %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode(['error' => 'Внутренняя ошибка сервера']);
    exit;
});

// Secure-cookie управляется через env: в проде за TLS ставить SESSION_COOKIE_SECURE=true.
// В dev (HTTP) оставлять пустым/false, иначе браузер не отдаст cookie.
if (filter_var(getenv('SESSION_COOKIE_SECURE'), FILTER_VALIDATE_BOOLEAN)) {
    ini_set('session.cookie_secure', '1');
}
session_start();

$corsOrigin = getenv('CORS_ORIGIN') ?: 'http://localhost:5173';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . $corsOrigin);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

foreach (glob(__DIR__ . '/controllers/*.php') as $controller) {
    require_once $controller;
}
require_once __DIR__ . '/models/SiteSettings.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = trim(str_replace('/api/', '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), '/');

// Таблица маршрутов: [метод, шаблон, обработчик].
// Шаблон — регэксп без якорей (якоря ^$ добавляются при матче).
// Группы (\d+) попадают в $m[1], $m[2]... Авторизация проверяется внутри контроллеров.
$routes = [
    // ── Публичные ──
    ['GET',    'settings',                          fn($m) => print(json_encode(['success' => true, 'settings' => (new SiteSettings())->getAll()]))],
    ['GET',    'services',                           fn($m) => ServiceController::getServices()],
    ['GET',    'portfolio',                          fn($m) => PortfolioController::getPortfolio()],
    ['POST',   'auth/login',                         fn($m) => AuthController::login()],
    ['POST',   'auth/logout',                        fn($m) => AuthController::logout()],
    ['GET',    'auth/me',                            fn($m) => AuthController::me()],
    ['POST',   'auth/register',                      fn($m) => AuthController::register()],
    ['POST',   'order/create',                       fn($m) => OrderController::createOrder()],
    ['POST',   'validate-car',                       fn($m) => CarValidationController::validateCar()],
    ['POST',   'car-brand-suggest',                  fn($m) => CarValidationController::suggestBrand()],
    ['POST',   'feedback',                           fn($m) => FeedbackController::sendFeedback()],
    ['GET',    'car-models',                         fn($m) => CarController::getModels()],
    ['GET',    'categories',                         fn($m) => CategoryController::getCategories()],

    // ── Личный кабинет клиента ──
    ['GET',    'user/orders',                        fn($m) => ProfileController::getOrders()],
    ['GET',    'user/orders/progress',               fn($m) => ProfileController::getOrdersProgress()],
    ['POST',   'user/orders/(\d+)/cancel',           fn($m) => ProfileController::cancelOrder($m[1])],
    ['POST',   'user/orders/(\d+)/reschedule',       fn($m) => ProfileController::rescheduleOrder($m[1])],
    ['GET',    'user/orders/(\d+)/photos',           fn($m) => ProfileController::getClientOrderPhotos($m[1])],
    ['GET',    'user/profile',                       fn($m) => ProfileController::getProfile()],
    ['PUT',    'user/profile',                       fn($m) => ProfileController::updateProfile()],

    // ── Админ: дашборд/пароль ──
    ['GET',    'admin/dashboard',                    fn($m) => AdminSystemController::getDashboardStats()],
    ['POST',   'admin/change-password',              fn($m) => AdminSystemController::changePassword()],

    // ── Админ: обратная связь ──
    ['GET',    'admin/feedbacks',                    fn($m) => FeedbackController::getAllFeedbacks()],
    ['PUT',    'admin/feedbacks/(\d+)/status',       fn($m) => FeedbackController::updateFeedbackStatus($m[1])],
    ['DELETE', 'admin/feedbacks/(\d+)',              fn($m) => FeedbackController::deleteFeedback($m[1])],

    // ── Админ: услуги ──
    ['GET',    'admin/services',                     fn($m) => AdminServicesController::getServices()],
    ['POST',   'admin/services',                     fn($m) => AdminServicesController::addService()],
    ['PUT',    'admin/services/(\d+)',               fn($m) => AdminServicesController::updateService($m[1])],
    ['DELETE', 'admin/services/(\d+)',               fn($m) => AdminServicesController::deleteService($m[1])],
    ['GET',    'admin/services-by-category/(\d+)',   fn($m) => AdminServicesController::getServicesByCategory($m[1])],

    // ── Админ: портфолио ──
    ['GET',    'admin/portfolio',                    fn($m) => AdminPortfolioController::getPortfolio()],
    ['POST',   'admin/portfolio/upload',             fn($m) => AdminPortfolioController::uploadPortfolioMedia()],
    ['POST',   'admin/portfolio',                    fn($m) => AdminPortfolioController::addPortfolio()],
    ['PUT',    'admin/portfolio/(\d+)',              fn($m) => AdminPortfolioController::updatePortfolio($m[1])],
    ['DELETE', 'admin/portfolio/(\d+)',              fn($m) => AdminPortfolioController::deletePortfolio($m[1])],

    // ── Админ: клиенты ──
    ['GET',    'admin/clients',                      fn($m) => AdminClientsController::getClientsList()],
    ['GET',    'admin/clients/export',               fn($m) => AdminClientsController::exportClientsCSV()],
    ['GET',    'admin/clients/(\d+)',                fn($m) => AdminClientsController::getClientDetails($m[1])],
    ['PUT',    'admin/clients/(\d+)',                fn($m) => AdminClientsController::updateClient($m[1])],

    // ── Админ: заказы ──
    ['GET',    'admin/orders',                       fn($m) => AdminOrdersController::getOrders()],
    ['GET',    'admin/orders/(\d+)/progress',        fn($m) => AdminOrdersController::getOrderServicesWithProgress($m[1])],
    ['PUT',    'admin/orders/(\d+)/services/(\d+)/progress', fn($m) => AdminOrdersController::updateServiceProgress($m[1], $m[2])],
    ['GET',    'admin/orders/(\d+)/photos',          fn($m) => AdminOrdersController::getOrderPhotos($m[1])],
    ['POST',   'admin/orders/(\d+)/photos/upload',   fn($m) => AdminOrdersController::uploadOrderPhoto($m[1])],
    ['PUT',    'admin/orders/(\d+)/status',          fn($m) => AdminOrdersController::updateOrderStatus($m[1])],
    ['DELETE', 'admin/photos/(\d+)',                 fn($m) => AdminOrdersController::deleteOrderPhoto($m[1])],

    // ── Админ: категории услуг ──
    ['GET',    'admin/service-categories',           fn($m) => AdminServicesController::getServiceCategories()],
    ['POST',   'admin/service-categories',           fn($m) => AdminServicesController::addServiceCategory()],
    ['POST',   'admin/service-categories/(\d+)/media', fn($m) => AdminServicesController::uploadCategoryMedia($m[1])],
    ['DELETE', 'admin/service-categories/(\d+)/media', fn($m) => AdminServicesController::deleteCategoryMedia($m[1])],
    ['PUT',    'admin/service-categories/(\d+)',     fn($m) => AdminServicesController::updateServiceCategory($m[1])],
    ['DELETE', 'admin/service-categories/(\d+)',     fn($m) => AdminServicesController::deleteServiceCategory($m[1])],

    // ── Админ: статусы заказов ──
    ['GET',    'admin/order-statuses',               fn($m) => AdminOrdersController::getOrderStatuses()],

    // ── Админ: настройки ──
    ['GET',    'admin/settings',                     fn($m) => AdminSystemController::getSettings()],
    ['POST',   'admin/settings/about-video/upload',  fn($m) => AdminSystemController::uploadAboutVideo()],
    ['POST',   'admin/settings/privacy-pdf/upload',  fn($m) => AdminSystemController::uploadPrivacyPdf()],
    ['DELETE', 'admin/settings/privacy-pdf',         fn($m) => AdminSystemController::deletePrivacyPdf()],
];

foreach ($routes as [$routeMethod, $pattern, $handler]) {
    if ($method === $routeMethod && preg_match('#^' . $pattern . '$#', $path, $m)) {
        $handler($m);
        exit;
    }
}

http_response_code(404);
echo json_encode(['error' => 'Endpoint not found']);
