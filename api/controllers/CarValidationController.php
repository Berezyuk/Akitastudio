<?php
require_once __DIR__ . '/../config/env.php';

class CarValidationController {

    // Проксирует запрос к DaData за подсказками марок авто.
    // Токен остаётся на сервере и не попадает в публичный JS.
    public static function suggestBrand() {
        $data  = json_decode(file_get_contents('php://input'), true);
        $query = trim($data['query'] ?? '');
        $count = min((int)($data['count'] ?? 20), 50);

        if ($query === '') {
            echo json_encode(['suggestions' => []]);
            return;
        }

        $token = getenv('DADATA_TOKEN');
        $ch = curl_init('https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/car_brand');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['query' => $query, 'count' => $count]),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Token ' . $token,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            echo json_encode(['suggestions' => []]);
            return;
        }

        echo $response; // пробрасываем ответ DaData напрямую
    }

    public static function validateCar() {
        $data  = json_decode(file_get_contents('php://input'), true);
        $query = trim(($data['brand'] ?? '') . ' ' . ($data['model'] ?? ''));

        if ($query === ' ' || $query === '') {
            echo json_encode(['success' => false, 'error' => 'Не указана марка или модель']);
            return;
        }

        $token  = getenv('DADATA_TOKEN');
        $secret = getenv('DADATA_SECRET');

        $ch = curl_init('https://cleaner.dadata.ru/api/v1/clean/vehicle');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([$query]),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Token ' . $token,
                'X-Secret: ' . $secret,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            echo json_encode(['success' => false, 'error' => 'Ошибка при проверке автомобиля']);
            return;
        }

        $result = json_decode($response, true);
        if (empty($result) || !isset($result[0])) {
            echo json_encode(['success' => false, 'error' => 'Не удалось распознать марку и модель']);
            return;
        }

        $item = $result[0];
        $qc   = $item['qc'] ?? null;

        if ($qc === 0) {
            echo json_encode([
                'success' => true,
                'brand'   => $item['brand'] ?? '',
                'model'   => $item['model'] ?? '',
                'qc'      => $qc,
            ]);
        } elseif ($qc === 3) {
            echo json_encode([
                'success' => true,
                'brand'   => $item['brand'] ?? '',
                'model'   => '',
                'qc'      => $qc,
                'warning' => 'Модель не распознана, уточните, пожалуйста',
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error'   => 'Не удалось определить марку и модель. Проверьте правильность ввода.',
            ]);
        }
    }
}
