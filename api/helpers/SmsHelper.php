<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

use smsru\SmsRu;

function sendSms($phone, $message) {
    $apiKey = getenv('SMS_API_KEY');
    if (!$apiKey) {
        error_log('SMS_API_KEY not set');
        return ['success' => false, 'error' => 'SMS service not configured'];
    }

    $sms = new SmsRu($apiKey);

    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 10) {
        $phone = '7' . $phone;
    }

    $data = new \stdClass();
    $data->to   = $phone;
    $data->text = $message;

    $response = $sms->send_one($data);

    if ($response->status === 'OK') {
        return ['success' => true];
    }

    error_log('SMS error: ' . print_r($response, true));
    return ['success' => false, 'error' => $response->status_code ?? 'unknown'];
}
