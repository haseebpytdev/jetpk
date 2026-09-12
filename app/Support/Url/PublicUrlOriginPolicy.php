<?php

namespace App\Support\Url;

/**
 * Validates externally emitted absolute URL origins; rejects private/loopback hosts.
 */
final class PublicUrlOriginPolicy
{
    /** @var list<string> */
    private const BLOCKED_HOST_FRAGMENTS = [
        'localhost',
        '.internal',
        '.local',
    ];

    public static function isUnsafeHost(string $host): bool
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return true;
        }

        foreach (self::BLOCKED_HOST_FRAGMENTS as $fragment) {
            if (str_contains($host, $fragment)) {
                return true;
            }
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isUnsafeIp($host);
        }

        if (str_contains($host, ':')) {
            [$hostOnly, $port] = explode(':', $host, 2);
            if (self::isUnsafeHost($hostOnly)) {
                return true;
            }
            if (self::isUnapprovedPort((int) $port)) {
                return true;
            }
        }

        return false;
    }

    public static function isUnsafeAbsoluteUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return false;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return true;
        }

        $host = (string) ($parts['host'] ?? '');
        if (self::isUnsafeHost($host)) {
            return true;
        }

        if (isset($parts['port']) && self::isUnapprovedPort((int) $parts['port'])) {
            return true;
        }

        if (str_contains(strtolower($url), '/index.php/')) {
            return true;
        }

        return false;
    }

    public static function sanitizeAbsoluteUrl(string $url): string
    {
        if (! self::isUnsafeAbsoluteUrl($url)) {
            return self::stripIndexPhp($url);
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return PublicActionUrl::base();
        }

        $path = (string) ($parts['path'] ?? '/');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return PublicActionUrl::absolute($path.$query.$fragment);
    }

    public static function stripIndexPhp(string $url): string
    {
        return preg_replace('#/index\.php(?=/|$)#', '', $url) ?? $url;
    }

    private static function isUnsafeIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        if (in_array($ip, ['127.0.0.1', '0.0.0.0', '::1'], true)) {
            return true;
        }

        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }

    private static function isUnapprovedPort(int $port): bool
    {
        if ($port <= 0) {
            return false;
        }

        return ! in_array($port, [80, 443], true);
    }
}
