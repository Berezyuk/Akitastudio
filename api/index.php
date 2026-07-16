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

// Secure-флаг на session-cookie.
//
// Раньше это зависело только от SESSION_COOKIE_SECURE, и настройка проваливалась
// в небезопасную сторону: на проде переменной в .env не оказалось -> дефолт false
// -> кука на HTTPS-сайте уходила без Secure. Забыть одну строку в .env не должно
// стоить защиты сессии, поэтому HTTPS определяется сам.
//
// $_SERVER['HTTPS'] — прямой TLS на веб-сервере; X-Forwarded-Proto — терминация
// выше (у нас это хостовой nginx, см. docs/nginx-host.conf). Заголовок приходит
// от клиента и подделывается, но худшее, что даст подделка, — Secure-кука на
// HTTP: браузер просто не пришлёт её обратно. Своя же сессия и сломается, чужая
// не пострадает. Ошибиться в эту сторону безопасно.
$isHttps = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

// Явный SESSION_COOKIE_SECURE остаётся: им можно включить Secure там, где схему
// не определить. Выключить автоопределение он не может — это и есть цель.
if ($isHttps || filter_var(getenv('SESSION_COOKIE_SECURE'), FILTER_VALIDATE_BOOLEAN)) {
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

// HEAD матчим как GET: иначе health-check'и и мониторинги получали 404 на всех
// GET-роутах. Тело ответа nginx для HEAD отбросит сам.
$method = $_SERVER['REQUEST_METHOD'] === 'HEAD' ? 'GET' : $_SERVER['REQUEST_METHOD'];
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
    ['POST',   'visit',                             fn($m) => VisitController::track()],
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
    ['GET',    'admin/analytics',                   fn($m) => AdminSystemController::getAnalytics()],
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
        // Контроллеры сообщают об ошибке через echo json_encode(['error' => ...]) и
        // статус не трогают — клиент получал HTTP 200 и не мог отличить отказ от
        // успеха, не разбирая тело. Проставляем 400 здесь, а не правим ~68 точек:
        // так корректный статус работает и для нового кода по умолчанию.
        // Явно выставленный контроллером код (401/403/429 из guard'ов и RateLimiter)
        // не трогаем — до этой строки они доходят через exit, с уже готовым статусом.
        ob_start();
        $handler($m);
        $body = ob_get_clean();

        if (http_response_code() === 200) {
            $decoded = json_decode($body, true);
            if (is_array($decoded) && isset($decoded['error']) && empty($decoded['success'])) {
                http_response_code(400);
            }
        }

        echo $body;
        exit;
    }
}

http_response_code(404);
echo json_encode(['error' => 'Endpoint not found']);
