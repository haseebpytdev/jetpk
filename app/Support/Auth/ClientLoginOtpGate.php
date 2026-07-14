<?php

namespace App\Support\Auth;

use App\Http\Middleware\PersistClientPreviewContext;
use Illuminate\Http\Request;

/**
 * Determines whether password login must complete an email OTP challenge for the active client.
 */
final class ClientLoginOtpGate
{
    public static function isRequired(?Request $request = null): bool
    {
        if (self::resolvedClientSlug($request) === 'jetpk') {
            return true;
        }

        if (is_client_preview()) {
            $profile = current_client_profile();
            $config = is_array($profile?->branding?->config) ? $profile->branding->config : [];
            $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
            if (array_key_exists('require_login_otp', $auth)) {
                return (bool) $auth['require_login_otp'];
            }
        }

        return (bool) config('ota_client.auth.require_login_otp', false);
    }

    public static function resolvedClientSlug(?Request $request = null): ?string
    {
        $slug = current_client_slug();
        if ($slug !== null && $slug !== '') {
            return $slug;
        }

        $request ??= request();
        if ($request instanceof Request && $request->hasSession()) {
            $sessionSlug = $request->session()->get(PersistClientPreviewContext::SESSION_KEY);
            if (is_string($sessionSlug) && trim($sessionSlug) !== '') {
                return trim($sessionSlug);
            }
        }

        return null;
    }

    public static function expiryMinutes(): int
    {
        return max(1, (int) config('ota_client.auth.login_otp_expiry_minutes', 10));
    }

    public static function resendCooldownSeconds(): int
    {
        return max(15, (int) config('ota_client.auth.login_otp_resend_cooldown_seconds', 60));
    }

    public static function maxAttempts(): int
    {
        return max(1, (int) config('ota_client.auth.login_otp_max_attempts', 5));
    }
}
