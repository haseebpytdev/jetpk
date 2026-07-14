<?php

namespace App\Support\Auth;

use App\Http\Middleware\PersistClientPreviewContext;
use Illuminate\Http\Request;

/**
 * Persists client preview slug across the shared OAuth callback (/auth/google/callback).
 *
 * Redirect entry points: /auth/google/redirect (master) and /{clientSlug}/auth/google/redirect.
 * Google always returns to the single configured GOOGLE_REDIRECT_URI.
 */
final class SocialOAuthClientContext
{
    public const SESSION_KEY = 'ota.social_oauth_client_slug';

    public static function captureForRedirect(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $slug = self::resolveSlugFromRequest($request);

        if ($slug !== null) {
            $request->session()->put(self::SESSION_KEY, $slug);
            $request->session()->put(PersistClientPreviewContext::SESSION_KEY, $slug);

            return;
        }

        $request->session()->forget(self::SESSION_KEY);
        $request->session()->forget(PersistClientPreviewContext::SESSION_KEY);
    }

    public static function restoreAfterCallback(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $slug = $request->session()->get(self::SESSION_KEY);
        if (! is_string($slug) || trim($slug) === '') {
            $sessionSlug = $request->session()->get(PersistClientPreviewContext::SESSION_KEY);
            $slug = is_string($sessionSlug) ? $sessionSlug : null;
        }

        $slug = self::normalizeSlug(is_string($slug) ? $slug : null);

        if ($slug !== null) {
            $request->session()->put(self::SESSION_KEY, $slug);
            $request->session()->put(PersistClientPreviewContext::SESSION_KEY, $slug);

            return;
        }

        $request->session()->forget(self::SESSION_KEY);
    }

    public static function resolveSlugFromRequest(Request $request): ?string
    {
        $routeSlug = $request->route('clientSlug');
        if (is_string($routeSlug) && trim($routeSlug) !== '') {
            return self::normalizeSlug(trim($routeSlug));
        }

        $attrSlug = $request->attributes->get(PersistClientPreviewContext::REQUEST_ATTR_SLUG);
        if (is_string($attrSlug) && trim($attrSlug) !== '') {
            return self::normalizeSlug(trim($attrSlug));
        }

        if ($request->hasSession()) {
            $oauthSlug = $request->session()->get(self::SESSION_KEY);
            if (is_string($oauthSlug) && trim($oauthSlug) !== '') {
                return self::normalizeSlug(trim($oauthSlug));
            }

            $sessionSlug = $request->session()->get(PersistClientPreviewContext::SESSION_KEY);
            if (is_string($sessionSlug) && trim($sessionSlug) !== '') {
                return self::normalizeSlug(trim($sessionSlug));
            }
        }

        return null;
    }

    public static function normalizeSlug(?string $slug): ?string
    {
        if ($slug === null || trim($slug) === '') {
            return null;
        }

        $slug = trim($slug);
        $masterSlug = (string) config('client_route_parity.default_client_slug', 'haseeb-master');

        if ($slug === $masterSlug || $slug === 'haseeb-master') {
            return null;
        }

        return $slug;
    }
}
