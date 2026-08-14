<?php

namespace App\Support\Url;

/**
 * Absolute URLs for browser-facing emails and notifications.
 * Always uses APP_URL — never the proxied request root (127.0.0.1:8088).
 */
final class PublicActionUrl
{
    public static function base(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public static function absolute(string $path): string
    {
        $path = '/'.ltrim($path, '/');
        $path = preg_replace('#/index\.php(?=/|$)#', '', $path) ?? $path;

        return self::base().$path;
    }

    public static function passwordReset(string $token, string $email): string
    {
        $query = http_build_query([
            'email' => $email,
        ]);

        return self::absolute('/reset-password/'.urlencode($token).'?'.$query);
    }

    public static function emailVerification(string $signedPath): string
    {
        return self::absolute($signedPath);
    }
}
