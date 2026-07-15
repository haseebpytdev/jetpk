<?php

namespace App\Support\Client;

/**
 * Static deployment profile for multi-client OTA installs.
 *
 * Backed by config/ota_client.php (OTA_CLIENT_* / OTA_MODULE_* env vars).
 * Not wired into routes or PlatformModuleGate in the prep sprint — safe to
 * call without changing live behavior when env vars are unset.
 */
final class ClientProfile
{
    public static function slug(): string
    {
        return trim((string) config('ota_client.slug', ''));
    }

    public static function theme(): string
    {
        $theme = trim((string) config('ota_client.theme', 'v1-classic'));

        return $theme !== '' ? $theme : 'v1-classic';
    }

    public static function assetProfile(): string
    {
        return trim((string) config('ota_client.asset_profile', ''));
    }

    public static function moduleEnabled(string $module): bool
    {
        $key = self::normalizeModuleKey($module);
        $modules = config('ota_client.modules', []);

        if (! is_array($modules)) {
            return true;
        }

        if (! array_key_exists($key, $modules)) {
            return false;
        }

        return (bool) $modules[$key];
    }

    public static function assetPath(string $path): string
    {
        $relative = ltrim($path, '/');
        $profile = self::assetProfile();

        if ($profile === '') {
            return $relative;
        }

        return 'client-assets/'.$profile.'/'.$relative;
    }

    private static function normalizeModuleKey(string $module): string
    {
        return strtolower(trim(str_replace('-', '_', $module)));
    }
}
