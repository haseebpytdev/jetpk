<?php

namespace App\Support\Ui;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Reads and writes the public mobile-app-shell view preference (cookie + session).
 * Manual mobile/desktop overrides auto user-agent detection; absent preference = auto.
 */
class MobileViewPreference
{
    public const MODE_MOBILE = 'mobile';

    public const MODE_DESKTOP = 'desktop';

    public const MODE_AUTO = 'auto';

    /**
     * Stored preference only: mobile, desktop, or auto when unset.
     */
    public function currentMode(Request $request): string
    {
        $stored = $this->storedPreference($request);

        return $stored ?? self::MODE_AUTO;
    }

    public function hasManualPreference(Request $request): bool
    {
        return $this->storedPreference($request) !== null;
    }

    public function shouldUseMobileShell(Request $request, ?string $pageKey = null): bool
    {
        $pageKey = $this->resolvePageKey($request, $pageKey);

        if (! $this->pageHasMobileShell($pageKey)) {
            return false;
        }

        return $this->prefersMobileExperience($request);
    }

    public function isMobileDevice(Request $request): bool
    {
        $userAgent = (string) $request->userAgent();

        if ($userAgent === '') {
            return false;
        }

        $pattern = (string) config(
            'ota-mobile.device_patterns',
            '/(android|webos|iphone|ipod|blackberry|iemobile|opera mini|mobile|ipad|tablet|silk|playbook|kindle)/i',
        );

        return (bool) preg_match($pattern, $userAgent);
    }

    public function makePreferenceCookie(string $mode): Cookie
    {
        $configured = config('ota-mobile.values', []);
        $allowed = [
            (string) ($configured['mobile'] ?? self::MODE_MOBILE),
            (string) ($configured['desktop'] ?? self::MODE_DESKTOP),
        ];

        if (! in_array($mode, $allowed, true)) {
            $mode = self::MODE_DESKTOP;
        }

        $cookieName = (string) config('ota-mobile.cookie_name', 'ota_view_mode');
        $minutes = (int) config('ota-mobile.cookie_minutes', 525600);

        return cookie(
            name: $cookieName,
            value: $mode,
            minutes: $minutes,
            path: '/',
            secure: request()->isSecure(),
            httpOnly: false,
            raw: false,
            sameSite: 'lax',
        );
    }

    public function rememberInSession(Request $request, string $mode): void
    {
        $sessionKey = (string) config('ota-mobile.session_key', 'ota_view_mode');
        $request->session()->put($sessionKey, $mode);
    }

    public function safeRedirectUrl(?string $redirect, string $fallback = '/'): string
    {
        $fallback = $this->normalizeAppUrl($fallback) ?? url('/');

        if ($redirect === null || trim($redirect) === '') {
            return $fallback;
        }

        return $this->normalizeAppUrl($redirect) ?? $fallback;
    }

    protected function prefersMobileExperience(Request $request): bool
    {
        $mode = $this->currentMode($request);

        if ($mode === self::MODE_MOBILE) {
            return true;
        }

        if ($mode === self::MODE_DESKTOP) {
            return false;
        }

        return $this->isMobileDevice($request);
    }

    protected function storedPreference(Request $request): ?string
    {
        $configured = config('ota-mobile.values', []);
        $mobile = (string) ($configured['mobile'] ?? self::MODE_MOBILE);
        $desktop = (string) ($configured['desktop'] ?? self::MODE_DESKTOP);
        $allowed = [$mobile, $desktop];

        $sessionKey = (string) config('ota-mobile.session_key', 'ota_view_mode');
        $sessionValue = $request->session()->get($sessionKey);
        if (is_string($sessionValue) && in_array($sessionValue, $allowed, true)) {
            return $sessionValue;
        }

        $cookieName = (string) config('ota-mobile.cookie_name', 'ota_view_mode');
        $cookieValue = $request->cookie($cookieName);
        if (is_string($cookieValue) && in_array($cookieValue, $allowed, true)) {
            return $cookieValue;
        }

        return null;
    }

    protected function resolvePageKey(Request $request, ?string $pageKey): ?string
    {
        if ($pageKey !== null && $pageKey !== '') {
            return $pageKey;
        }

        $routeName = $request->route()?->getName();
        if (! is_string($routeName) || $routeName === '') {
            return null;
        }

        $aliases = config('ota-mobile.route_aliases', []);

        return is_array($aliases) && array_key_exists($routeName, $aliases)
            ? (string) $aliases[$routeName]
            : $routeName;
    }

    protected function pageHasMobileShell(?string $pageKey): bool
    {
        if ($pageKey === null || $pageKey === '') {
            return false;
        }

        $pages = config('ota-mobile.mobile_pages', []);

        return (bool) ($pages[$pageKey] ?? false);
    }

    protected function normalizeAppUrl(string $url): ?string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, '/')) {
            return $trimmed;
        }

        $appRoot = rtrim((string) config('app.url', ''), '/');
        if ($appRoot === '') {
            return null;
        }

        if (! str_starts_with($trimmed, $appRoot)) {
            return null;
        }

        $path = substr($trimmed, strlen($appRoot));
        if ($path === false || $path === '') {
            return '/';
        }

        if (! str_starts_with($path, '/')) {
            return null;
        }

        return $path;
    }
}
