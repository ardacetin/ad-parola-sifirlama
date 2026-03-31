<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class SmsHelper
{
    public static function sendOtp(string $phone, string $message): bool
    {
        $timestamp = date('H:i:s');
        $finalMessage = trim($message . ' ' . $timestamp);

        $xmlPayload = self::buildXmlPayload($phone, $finalMessage);

        $curlHandle = curl_init(SMS_API_URL);

        if ($curlHandle === false) {
            return false;
        }

        curl_setopt_array($curlHandle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/xml; charset=UTF-8',
                'Accept: application/xml',
            ],
            CURLOPT_POSTFIELDS => $xmlPayload,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($curlHandle);
        $curlError = curl_error($curlHandle);
        $httpCode = (int) curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
        curl_close($curlHandle);

        if ($curlError !== '' || $response === false || $httpCode < 200 || $httpCode >= 300) {
            return false;
        }

        return self::isSuccessfulResponse((string) $response);
    }

    private static function buildXmlPayload(string $phone, string $message): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<sms>'
            . '<header>'
            . '<username>' . htmlspecialchars(SMS_USER, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</username>'
            . '<password>' . htmlspecialchars(SMS_PASS, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</password>'
            . '<from>' . htmlspecialchars(SMS_SENDER, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</from>'
            . '<validity>2880</validity>'
            . '</header>'
            . '<message>'
            . '<gsm><no>' . htmlspecialchars($phone, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</no></gsm>'
            . '<msg><![CDATA[' . str_replace(']]>', ' ', $message) . ']]></msg>'
            . '</message>'
            . '</sms>';
    }

    private static function isSuccessfulResponse(string $response): bool
    {
        $trimmed = trim($response);

        if ($trimmed === '') {
            return false;
        }

        $xml = @simplexml_load_string($trimmed);

        if ($xml === false) {
            return stripos($trimmed, 'error') === false && stripos($trimmed, 'hata') === false;
        }

        $flattened = strtolower(preg_replace('/\s+/', ' ', $trimmed) ?? '');

        return !str_contains($flattened, 'error') && !str_contains($flattened, 'hata');
    }
}
