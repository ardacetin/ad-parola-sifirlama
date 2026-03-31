<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class LdapHelper
{
    private LDAP\Connection|false $connection;

    public function __construct()
    {
        $this->connection = ldap_connect(LDAP_SERVER, LDAP_PORT);

        if ($this->connection === false) {
            throw new RuntimeException('LDAP connection could not be established.');
        }

        ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->connection, LDAP_OPT_REFERRALS, 0);

        if (!@ldap_bind($this->connection, LDAP_BIND_USER, LDAP_BIND_PASS)) {
            throw new RuntimeException('LDAP bind failed.');
        }
    }

    public function validateUser(string $username, string $tcKimlik, string $phoneLast4): array|false
    {
        $filter = sprintf(
            '(sAMAccountName=%s)',
            ldap_escape($username, '', LDAP_ESCAPE_FILTER)
        );

        $attributes = ['dn', 'employeeID', 'mobile'];
        $search = @ldap_search($this->connection, LDAP_BASE_DN, $filter, $attributes);

        if ($search === false) {
            throw new RuntimeException('LDAP search failed.');
        }

        if (ldap_count_entries($this->connection, $search) < 1) {
            return false;
        }

        $entries = ldap_get_entries($this->connection, $search);

        if (($entries['count'] ?? 0) < 1) {
            return false;
        }

        $user = $entries[0];
        $adTcKimlik = isset($user['employeeid'][0]) ? trim((string) $user['employeeid'][0]) : '';

        if ($adTcKimlik === '' || $adTcKimlik !== $tcKimlik) {
            return false;
        }

        $adMobile = isset($user['mobile'][0]) ? (string) $user['mobile'][0] : '';
        $cleanPhone = $this->normalizeAdPhone($adMobile);

        if ($cleanPhone === '' || strlen($cleanPhone) < 4) {
            return false;
        }

        if (substr($cleanPhone, -4) !== $phoneLast4) {
            return false;
        }

        return [
            'dn' => (string) $user['dn'],
            'phone' => $cleanPhone,
        ];
    }

    public function setPassword(string $userDn, string $newPassword): bool
    {
        $encodedPassword = iconv('UTF-8', 'UTF-16LE', '"' . $newPassword . '"');

        if ($encodedPassword === false) {
            throw new RuntimeException('Password encoding failed.');
        }

        $entry = [
            'unicodePwd' => $encodedPassword,
        ];

        return @ldap_modify($this->connection, $userDn, $entry);
    }

    public function close(): void
    {
        if ($this->connection !== false) {
            @ldap_close($this->connection);
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    private function normalizeAdPhone(string $phone): string
    {
        $normalized = preg_replace('/(\+90|0090|90(?=\d{10}$)|[^0-9])/', '', $phone) ?? '';

        if (strlen($normalized) === 10) {
            $normalized = '0' . $normalized;
        }

        return $normalized;
    }
}
