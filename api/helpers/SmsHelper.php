<?php
// api/helpers/SmsHelper.php

require_once __DIR__ . '/../vendor/autoload.php';

use smsru\SmsRu;

function sendSms($phone, $message) {
    // Ваш API-ключ из личного кабинета SMS.ru
    $apiKey = 'CC340A95-7692-249D-E8BE-4065CA6FBB29';
    
    $sms = new SmsRu($apiKey);
    
    // Очищаем номер: оставляем только цифры
    $phone = preg_replace('/[^0-9]/', '', $phone);
    // Если номер начинается с 9 (10 цифр), добавляем 7
    if (strlen($phone) === 10) {
        $phone = '7' . $phone;
    }
    
    $data = new \stdClass();
    $data->to = $phone;
    $data->text = $message;
    
    $response = $sms->send_one($data);
    
    if ($response->status === 'OK') {
        return ['success' => true];
    } else {
        // Логируем ошибку для отладки
        error_log("SMS error: " . print_r($response, true));
        return ['success' => false, 'error' => $response->status_code ?? 'unknown'];
    }
}