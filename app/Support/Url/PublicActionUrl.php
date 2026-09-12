<?php

namespace App\Support\Url;

use Illuminate\Support\Facades\Route;
use Throwable;

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
        $path = PublicUrlOriginPolicy::stripIndexPhp($path);

        return self::base().$path;
    }

    /**
     * @param  array<string, mixed>|string|int|null  $parameters
     */
    public static function route(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        if (! Route::has($name)) {
            return $absolute ? self::base() : '/';
        }

        try {
            $relative = route($name, $parameters, absolute: false);
        } catch (Throwable) {
            return $absolute ? self::base() : '/';
        }

        if (! $absolute) {
            return PublicUrlOriginPolicy::stripIndexPhp($relative);
        }

        return self::absolute($relative);
    }

    public static function sanitize(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (PublicUrlOriginPolicy::isUnsafeAbsoluteUrl($url)) {
            return PublicUrlOriginPolicy::sanitizeAbsoluteUrl($url);
        }

        return PublicUrlOriginPolicy::stripIndexPhp($url);
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
