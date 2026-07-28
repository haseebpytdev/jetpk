<?php

namespace App\Support\Dashboard;

/**
 * Masks audit network identifiers for dashboard read-only responses.
 */
final class AuditFieldMasker
{
    public static function maskIp(?string $ip): ?string
    {
        if ($ip === null || trim($ip) === '') {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                return $parts[0].'.'.$parts[1].'.'.$parts[2].'.xxx';
            }
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return '2001:db8::/32';
        }

        return 'masked';
    }

    public static function maskNetworkRange(?string $ip): ?string
    {
        if ($ip === null || trim($ip) === '') {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                return $parts[0].'.'.$parts[1].'.'.$parts[2].'.0/24';
            }
        }

        return '2001:db8::/32';
    }

    public static function summarizeUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return null;
        }

        if (stripos($userAgent, 'Chrome') !== false) {
            return 'Chrome browser';
        }
        if (stripos($userAgent, 'Firefox') !== false) {
            return 'Firefox browser';
        }
        if (stripos($userAgent, 'Safari') !== false) {
            return 'Safari browser';
        }
        if (stripos($userAgent, 'Playwright') !== false) {
            return 'Automated test client';
        }

        return 'Web client';
    }

    /**
     * @param  array<string, mixed>|null  $properties
     * @return array<string, mixed>
     */
    public static function sanitizeProperties(?array $properties): array
    {
        if ($properties === null) {
            return [];
        }

        $blocked = [
            'password', 'password_hash', 'token', 'session_id', 'csrf_token',
            'authorization', 'cookie', 'api_key', 'secret', 'credentials',
            'mfa_secret', 'recovery_codes', 'headers', 'request_headers',
        ];

        $safe = [];
        foreach ($properties as $key => $value) {
            $normalized = strtolower((string) $key);
            if (in_array($normalized, $blocked, true)) {
                continue;
            }
            if (is_array($value)) {
                $safe[$key] = self::sanitizeProperties($value);
            } elseif (is_string($value)) {
                $safe[$key] = mb_strlen($value) > 240 ? mb_substr($value, 0, 237).'…' : $value;
            } else {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }
}
