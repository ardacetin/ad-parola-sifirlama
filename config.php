<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }

    session_start();
}

date_default_timezone_set('Europe/Istanbul');

define('DB_HOST', 'localhost');
define('DB_NAME', 'sspr_db');
define('DB_USER', 'root');
define('DB_PASS', '');

define('LDAP_SERVER', 'ldaps://pdc.yourdomain.local');
define('LDAP_PORT', 636);
define('LDAP_BASE_DN', 'DC=yourdomain,DC=local');
define('LDAP_BIND_USER', 'sspr_service_account@yourdomain.local');
define('LDAP_BIND_PASS', 'Yetkili_Hesap_Sifreniz_Buraya');

define('SMS_USER', 'SenaGsm_Kullanici_Adiniz');
define('SMS_PASS', '32_Karakterlik_Gizli_API_Sifreniz_Buraya');
define('SMS_SENDER', 'BILGI ISLEM');
define('SMS_API_URL', 'https://api.senagsm.com.tr/api/smspost/v1');

define('HCAPTCHA_SITE_KEY', 'sizin-site-key-buraya');
define('HCAPTCHA_SECRET_KEY', 'sizin-secret-key-buraya');

define('DEFAULT_LANG', 'tr');
define('OTP_EXPIRY_MINUTES', 3);
define('SMS_HOURLY_LIMIT', 3);
define('OTP_LENGTH', 6);
define('PASSWORD_MIN_LENGTH', 8);

function availableLanguages(): array
{
    return ['tr', 'en'];
}

function resolveLanguage(?string $requested = null): string
{
    $availableLanguages = availableLanguages();

    if ($requested !== null && in_array($requested, $availableLanguages, true)) {
        $_SESSION['lang'] = $requested;
    }

    $sessionLanguage = $_SESSION['lang'] ?? DEFAULT_LANG;

    if (!in_array($sessionLanguage, $availableLanguages, true)) {
        $sessionLanguage = DEFAULT_LANG;
        $_SESSION['lang'] = $sessionLanguage;
    }

    return $sessionLanguage;
}

function loadTranslations(?string $language = null): array
{
    $language ??= resolveLanguage();
    $path = __DIR__ . '/lang/' . $language . '.php';

    if (!is_file($path)) {
        $path = __DIR__ . '/lang/' . DEFAULT_LANG . '.php';
    }

    return require $path;
}

function ensureCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validateCsrfToken(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function jsonResponse(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getClientIp(): string
{
    $serverKeys = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR',
    ];

    foreach ($serverKeys as $key) {
        if (empty($_SERVER[$key])) {
            continue;
        }

        $candidate = trim(explode(',', (string) $_SERVER[$key])[0]);

        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return '0.0.0.0';
}

function normalizeUsername(string $username): string
{
    return preg_replace('/[^a-zA-Z0-9._-]/', '', trim($username)) ?? '';
}

function normalizeIdentityNumber(string $value): string
{
    return preg_replace('/\D+/', '', trim($value)) ?? '';
}

function normalizePhoneLast4(string $value): string
{
    return preg_replace('/\D+/', '', trim($value)) ?? '';
}

function isPasswordComplex(string $password): bool
{
    return strlen($password) >= PASSWORD_MIN_LENGTH
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[a-z]/', $password) === 1
        && preg_match('/\d/', $password) === 1;
}

function maskPhoneNumber(string $phone): string
{
    $length = strlen($phone);

    if ($length <= 4) {
        return str_repeat('*', $length);
    }

    return substr($phone, 0, 3) . str_repeat('*', max($length - 5, 1)) . substr($phone, -2);
}

function resetFlowSession(): void
{
    unset(
        $_SESSION['reset_flow'],
        $_SESSION['otp_verified']
    );
}
