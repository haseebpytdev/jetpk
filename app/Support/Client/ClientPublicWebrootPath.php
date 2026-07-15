<<<<<<< HEAD
<?php
=======
﻿<?php
>>>>>>> jetpk/main

namespace App\Support\Client;

/**
 * Resolves the live public web-root for on-disk client asset/theme checks.
 *
<<<<<<< HEAD
 * Production assets may live outside Laravel's public/ (e.g. public_html/ota.haseebasif.com).
=======
 * Production assets may live outside Laravel's public directory when OTA_PUBLIC_WEBROOT_PATH is explicitly configured.
>>>>>>> jetpk/main
 * URL generation via asset() is unchanged; this class is for filesystem existence checks only.
 */
final class ClientPublicWebrootPath
{
    public static function configuredPath(): string
    {
        return rtrim(str_replace('\\', '/', trim((string) config('ota_client.public_webroot_path', ''))), '/');
    }

    public static function configuredExists(): bool
    {
        $configured = self::configuredPath();

        return $configured !== '' && is_dir($configured);
    }

    public static function resolve(): string
    {
        if (self::configuredExists()) {
            return self::configuredPath();
        }

        return rtrim(str_replace('\\', '/', public_path()), '/');
    }

    public static function usingConfiguredPath(): bool
    {
        return self::configuredExists();
    }

    public static function path(string $relativePath = ''): string
    {
        $relative = ltrim(str_replace('\\', '/', $relativePath), '/');
        $root = self::resolve();

        return $relative === '' ? $root : $root.'/'.$relative;
    }

    public static function isDirectory(string $relativePath): bool
    {
        $path = self::path($relativePath);

        return $path !== '' && is_dir($path);
    }

    public static function isFile(string $relativePath): bool
    {
        $path = self::path($relativePath);

        return is_file($path);
    }

    public static function laravelPublicRoot(): string
    {
        return rtrim(str_replace('\\', '/', public_path()), '/');
    }

    /**
     * @return array{
     *     configured_public_webroot: string,
     *     laravel_public_path: string,
     *     resolved_asset_root: string,
     *     using_configured: bool
     * }
     */
    public static function auditContext(): array
    {
        return [
            'configured_public_webroot' => self::configuredPath(),
            'laravel_public_path' => self::laravelPublicRoot(),
            'resolved_asset_root' => self::resolve(),
            'using_configured' => self::usingConfiguredPath(),
        ];
    }

    /**
     * Resolve a path relative to Laravel public/ (e.g. public/themes/... or themes/...).
     */
    public static function publicRelativePath(string $publicRelative): string
    {
        $relative = ltrim(str_replace('\\', '/', $publicRelative), '/');
        if (str_starts_with($relative, 'public/')) {
            $relative = substr($relative, strlen('public/'));
        }

        return self::path($relative);
    }

    public static function publicRelativeExists(string $publicRelative): bool
    {
        return is_file(self::publicRelativePath($publicRelative));
    }

    public static function readPublicRelative(string $publicRelative): ?string
    {
        $path = self::publicRelativePath($publicRelative);

        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    public static function laravelPublicRelativeExists(string $publicRelative): bool
    {
        $relative = ltrim(str_replace('\\', '/', $publicRelative), '/');
        if (! str_starts_with($relative, 'public/')) {
            $relative = 'public/'.$relative;
        }

        return is_file(base_path($relative));
    }
}
<<<<<<< HEAD
=======

>>>>>>> jetpk/main
