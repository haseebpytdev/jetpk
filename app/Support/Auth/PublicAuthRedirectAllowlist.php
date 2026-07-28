<?php

namespace App\Support\Auth;

/**
 * Safe internal paths for post-auth redirects returned to the Next.js frontend.
 */
final class PublicAuthRedirectAllowlist
{
    /**
     * @var list<string>
     */
    private const EXACT_PATHS = [
        '/',
        '/login',
        '/login/otp',
        '/register',
        '/agent/register',
        '/agent/register/submitted',
        '/forgot-password',
        '/verify-email',
        '/password/force-change',
        '/customer',
        '/agent',
        '/admin/dashboard',
        '/staff/dashboard',
        '/account/legacy',
    ];

    /**
     * @var list<string>
     */
    private const PREFIXES = [
        '/customer/',
        '/agent/',
        '/admin/',
        '/staff/',
        '/reset-password/',
        '/verify-email/',
        '/jetpk/',
    ];

    public static function sanitize(string $path, string $fallback = '/'): string
    {
        $normalized = self::normalize($path);
        if ($normalized === null) {
            return $fallback;
        }

        return $normalized;
    }

    public static function isAllowed(string $path): bool
    {
        return self::normalize($path) !== null;
    }

    private static function normalize(string $path): ?string
    {
        $trimmed = trim($path);
        if ($trimmed === '' || ! str_starts_with($trimmed, '/')) {
            return null;
        }

        if (str_contains($trimmed, '//') || str_contains($trimmed, '\\')) {
            return null;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $trimmed) === 1) {
            return null;
        }

        $pathOnly = (string) parse_url($trimmed, PHP_URL_PATH);
        if ($pathOnly === '' || ! str_starts_with($pathOnly, '/')) {
            return null;
        }

        $query = parse_url($trimmed, PHP_URL_QUERY);
        $suffix = is_string($query) && $query !== '' ? '?'.$query : '';

        if (in_array($pathOnly, self::EXACT_PATHS, true)) {
            return $pathOnly.$suffix;
        }

        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($pathOnly, $prefix)) {
                return $pathOnly.$suffix;
            }
        }

        return null;
    }
}
