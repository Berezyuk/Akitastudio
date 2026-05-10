<?php
// api/controllers/CarValidationController.php

class CarValidationController {
    private static $apiKey = '92fb0c168035ebecb044510a0ee4e92fbefd17d0';
    private static $secretKey = '862ac3beb0eaa9aaff447eb7a160adccd01c0b6d';

    public static function validateCar() {
        $data = json_decode(file_get_contents('php://input'), true);
        $query = trim(($data['brand'] ?? '') . ' ' . ($data['model'] ?? ''));
        if (empty($query)) {
            echo json_encode(['success' => false, 'error' => 'Не указана марка или модель']);
            return;
        }

        $url = 'https://cleaner.dadata.ru/api/v1/clean/vehicle';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([$query]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Token ' . self::$apiKey,
            'X-Secret: ' . self::$secretKey
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
        $qc = $item['qc'] ?? null;

        if ($qc === 0) {
            echo json_encode([
                'success' => true,
                'brand' => $item['brand'] ?? '',
                'model' => $item['model'] ?? '',
                'qc' => $qc
            ]);
        } elseif ($qc === 3) {
            echo json_encode([
                'success' => true,
                'brand' => $item['brand'] ?? '',
                'model' => '',
                'qc' => $qc,
                'warning' => 'Модель не распознана, уточните, пожалуйста'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Не удалось определить марку и модель. Проверьте правильность ввода.'
            ]);
        }
    }
}