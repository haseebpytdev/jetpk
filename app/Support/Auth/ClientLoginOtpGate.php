<?php

namespace App\Support\Auth;

use App\Http\Middleware\PersistClientPreviewContext;
use App\Services\Client\ClientProfileResolver;
use Illuminate\Http\Request;

/**
 * Determines whether password login must complete an email OTP challenge for the active client.
 *
 * Resolution order (first explicit source wins):
 * 1. Client profile branding.config.auth.require_login_otp (persisted admin/runtime authority)
 * 2. Preview branding config when in client preview
 * 3. config('ota_client.auth.require_login_otp') / OTA_CLIENT_REQUIRE_LOGIN_OTP
 *
 * JetPakistan does NOT hard-force OTP ON. Admin/env/profile must control the gate.
 */
final class ClientLoginOtpGate
{
    public static function isRequired(?Request $request = null): bool
    {
        $fromProfile = self::profileRequireLoginOtp($request);
        if ($fromProfile !== null) {
            return $fromProfile;
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

    /**
     * Read persisted require_login_otp from the active client profile branding config.
     */
    private static function profileRequireLoginOtp(?Request $request = null): ?bool
    {
        $slug = self::resolvedClientSlug($request);
        if ($slug === null || $slug === '') {
            return null;
        }

        try {
            if (! function_exists('app')) {
                return null;
            }

            /** @var ClientProfileResolver $resolver */
            $resolver = app(ClientProfileResolver::class);
            $profile = $resolver->resolveBySlug($slug);
            $config = is_array($profile?->branding?->config) ? $profile->branding->config : [];
            $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
            if (array_key_exists('require_login_otp', $auth)) {
                return (bool) $auth['require_login_otp'];
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
