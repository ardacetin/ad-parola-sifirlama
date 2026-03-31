<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class DbHelper
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_NAME
            );

            self::$connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }

        return self::$connection;
    }

    public static function countSmsRequestsInLastHour(string $username): int
    {
        $statement = self::getConnection()->prepare(
            "SELECT COUNT(*) FROM reset_logs
             WHERE username = :username
               AND status = 'sms_sent'
               AND created_at >= (NOW() - INTERVAL 1 HOUR)"
        );
        $statement->execute(['username' => $username]);

        return (int) $statement->fetchColumn();
    }

    public static function logAction(string $username, string $status): void
    {
        $statement = self::getConnection()->prepare(
            'INSERT INTO reset_logs (username, ip_address, status) VALUES (:username, :ip_address, :status)'
        );

        $statement->execute([
            'username' => $username,
            'ip_address' => getClientIp(),
            'status' => $status,
        ]);
    }

    public static function createOtp(string $username, string $tcKimlik, string $phoneNumber, string $otpCode): void
    {
        $connection = self::getConnection();
        $connection->beginTransaction();

        try {
            $invalidateStatement = $connection->prepare(
                'UPDATE otp_requests SET is_used = 1 WHERE username = :username AND is_used = 0'
            );
            $invalidateStatement->execute(['username' => $username]);

            $expiresAt = (new DateTimeImmutable('now'))
                ->modify('+' . OTP_EXPIRY_MINUTES . ' minutes')
                ->format('Y-m-d H:i:s');

            $insertStatement = $connection->prepare(
                'INSERT INTO otp_requests (username, tc_kimlik, otp_code, phone_number, expires_at)
                 VALUES (:username, :tc_kimlik, :otp_code, :phone_number, :expires_at)'
            );

            $insertStatement->execute([
                'username' => $username,
                'tc_kimlik' => $tcKimlik,
                'otp_code' => $otpCode,
                'phone_number' => $phoneNumber,
                'expires_at' => $expiresAt,
            ]);

            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public static function verifyOtp(string $username, string $otpCode): bool
    {
        $connection = self::getConnection();
        $connection->beginTransaction();

        try {
            $selectStatement = $connection->prepare(
                'SELECT id
                 FROM otp_requests
                 WHERE username = :username
                   AND otp_code = :otp_code
                   AND is_used = 0
                   AND expires_at >= NOW()
                 ORDER BY created_at DESC
                 LIMIT 1
                 FOR UPDATE'
            );
            $selectStatement->execute([
                'username' => $username,
                'otp_code' => $otpCode,
            ]);

            $row = $selectStatement->fetch();

            if (!$row) {
                $connection->commit();
                return false;
            }

            $updateStatement = $connection->prepare(
                'UPDATE otp_requests SET is_used = 1 WHERE id = :id'
            );
            $updateStatement->execute(['id' => $row['id']]);

            $connection->commit();
            return true;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }
}
