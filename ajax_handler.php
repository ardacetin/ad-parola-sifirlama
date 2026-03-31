<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/DbHelper.php';
require_once __DIR__ . '/LdapHelper.php';
require_once __DIR__ . '/SmsHelper.php';

$language = resolveLanguage($_GET['lang'] ?? null);
$messages = loadTranslations($language);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse([
        'status' => false,
        'message' => $messages['invalid_request'],
    ], 405);
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    jsonResponse([
        'status' => false,
        'message' => $messages['csrf_error'],
    ], 419);
}

$action = (string) ($_POST['action'] ?? '');

try {
    switch ($action) {
        case 'step1':
            handleStep1($messages);
            break;

        case 'step2':
            handleStep2($messages);
            break;

        case 'step3':
            handleStep3($messages);
            break;

        default:
            jsonResponse([
                'status' => false,
                'message' => $messages['invalid_request'],
            ], 400);
    }
} catch (Throwable $exception) {
    error_log('[SSPR] ' . $exception->getMessage());

    jsonResponse([
        'status' => false,
        'message' => $messages['error_system'],
    ], 500);
}

function handleStep1(array $messages): never
{
    $username = normalizeUsername((string) ($_POST['username'] ?? ''));
    $tcKimlik = normalizeIdentityNumber((string) ($_POST['tc_kimlik'] ?? ''));
    $phoneLast4 = normalizePhoneLast4((string) ($_POST['phone_last4'] ?? ''));
    $captchaResponse = trim((string) ($_POST['h-captcha-response'] ?? ''));

    if ($username === '' || $tcKimlik === '' || $phoneLast4 === '' || $captchaResponse === '') {
        jsonResponse(['status' => false, 'message' => $messages['error_fill_all']], 422);
    }

    if (strlen($username) < 3) {
        jsonResponse(['status' => false, 'message' => $messages['error_invalid_username']], 422);
    }

    if (strlen($tcKimlik) < 4) {
        jsonResponse(['status' => false, 'message' => $messages['error_invalid_tc']], 422);
    }

    if (!preg_match('/^\d{4}$/', $phoneLast4)) {
        jsonResponse(['status' => false, 'message' => $messages['error_invalid_phone_last4']], 422);
    }

    if (!verifyHCaptcha($captchaResponse)) {
        DbHelper::logAction($username, 'captcha_failed');
        jsonResponse(['status' => false, 'message' => $messages['error_captcha']], 422);
    }

    if (DbHelper::countSmsRequestsInLastHour($username) >= SMS_HOURLY_LIMIT) {
        DbHelper::logAction($username, 'sms_limit_reached');
        jsonResponse(['status' => false, 'message' => $messages['error_limit_exceeded']], 429);
    }

    $ldap = new LdapHelper();
    $userData = $ldap->validateUser($username, $tcKimlik, $phoneLast4);
    $ldap->close();

    if ($userData === false) {
        DbHelper::logAction($username, 'identity_mismatch');
        jsonResponse(['status' => false, 'message' => $messages['error_user_not_found']], 404);
    }

    $otpCode = str_pad((string) random_int(0, 999999), OTP_LENGTH, '0', STR_PAD_LEFT);
    $phone = $userData['phone'];

    DbHelper::createOtp($username, $tcKimlik, $phone, $otpCode);

    $smsMessage = sprintf($messages['sms_message'], $otpCode, OTP_EXPIRY_MINUTES);

    if (!SmsHelper::sendOtp($phone, $smsMessage)) {
        DbHelper::logAction($username, 'sms_failed');
        jsonResponse(['status' => false, 'message' => $messages['error_sms_failed']], 502);
    }

    DbHelper::logAction($username, 'sms_sent');

    $_SESSION['reset_flow'] = [
        'username' => $username,
        'tc_kimlik' => $tcKimlik,
        'dn' => $userData['dn'],
        'phone' => $phone,
        'otp_verified' => false,
    ];

    jsonResponse([
        'status' => true,
        'message' => sprintf($messages['success_sms_sent'], maskPhoneNumber($phone)),
    ]);
}

function handleStep2(array $messages): never
{
    $otpCode = preg_replace('/\D+/', '', (string) ($_POST['otp_code'] ?? '')) ?? '';
    $resetFlow = $_SESSION['reset_flow'] ?? null;

    if (!is_array($resetFlow) || empty($resetFlow['username']) || strlen($otpCode) !== OTP_LENGTH) {
        jsonResponse(['status' => false, 'message' => $messages['error_invalid_otp']], 422);
    }

    $isValid = DbHelper::verifyOtp((string) $resetFlow['username'], $otpCode);

    if (!$isValid) {
        DbHelper::logAction((string) $resetFlow['username'], 'otp_failed');
        jsonResponse(['status' => false, 'message' => $messages['error_invalid_otp']], 422);
    }

    $_SESSION['reset_flow']['otp_verified'] = true;

    jsonResponse([
        'status' => true,
        'message' => $messages['success_otp_verified'],
    ]);
}

function handleStep3(array $messages): never
{
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $newPasswordConfirm = (string) ($_POST['new_password_confirm'] ?? '');
    $resetFlow = $_SESSION['reset_flow'] ?? null;

    if (!is_array($resetFlow) || empty($resetFlow['username']) || empty($resetFlow['dn']) || empty($resetFlow['otp_verified'])) {
        jsonResponse(['status' => false, 'message' => $messages['invalid_request']], 403);
    }

    if ($newPassword === '' || $newPasswordConfirm === '') {
        jsonResponse(['status' => false, 'message' => $messages['error_fill_all']], 422);
    }

    if ($newPassword !== $newPasswordConfirm) {
        jsonResponse(['status' => false, 'message' => $messages['error_password_match']], 422);
    }

    if (!isPasswordComplex($newPassword)) {
        jsonResponse(['status' => false, 'message' => $messages['error_password_complexity']], 422);
    }

    $ldap = new LdapHelper();
    $updated = $ldap->setPassword((string) $resetFlow['dn'], $newPassword);
    $ldap->close();

    if (!$updated) {
        DbHelper::logAction((string) $resetFlow['username'], 'password_reset_failed');
        jsonResponse(['status' => false, 'message' => $messages['error_ldap_modify']], 422);
    }

    DbHelper::logAction((string) $resetFlow['username'], 'success');
    resetFlowSession();
    ensureCsrfToken();

    jsonResponse([
        'status' => true,
        'message' => $messages['success_password_reset'],
    ]);
}

function verifyHCaptcha(string $captchaResponse): bool
{
    $curlHandle = curl_init('https://hcaptcha.com/siteverify');

    if ($curlHandle === false) {
        return false;
    }

    curl_setopt_array($curlHandle, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => HCAPTCHA_SECRET_KEY,
            'response' => $captchaResponse,
            'remoteip' => getClientIp(),
        ]),
    ]);

    $response = curl_exec($curlHandle);
    $curlError = curl_error($curlHandle);
    curl_close($curlHandle);

    if ($response === false || $curlError !== '') {
        return false;
    }

    $decoded = json_decode((string) $response, true);

    return is_array($decoded) && !empty($decoded['success']);
}
