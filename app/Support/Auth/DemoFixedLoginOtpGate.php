<?php

namespace App\Support\Auth;

use App\Models\DeveloperUser;
use Illuminate\Support\Facades\Log;

/**
 * Local/testing-only fixed OTP acceptance for explicitly allowlisted demo users.
 *
 * Never active in production. Does not log submitted codes.
 */
final class DemoFixedLoginOtpGate
{
    public static function isEnabled(): bool
    {
        if (app()->environment('production')) {
            return false;
        }

        if (! app()->environment(['local', 'testing'])) {
            return false;
        }

        if (! filter_var(config('ota_otp_demo.fixed_enabled', false), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        $code = self::configuredFixedCode();

        return $code !== null;
    }

    public static function isEmailAllowed(string $email): bool
    {
        if (! self::isEnabled()) {
            return false;
        }

        $normalized = strtolower(trim($email));
        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $allowed = config('ota_otp_demo.allowed_emails', []);
        if (! is_array($allowed)) {
            $allowed = [];
        }

        if (in_array($normalized, $allowed, true)) {
            return true;
        }

        if (! filter_var(config('ota_otp_demo.allow_devcp', false), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        return DeveloperUser::query()
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->where('is_active', true)
            ->exists();
    }

    public static function acceptsSubmittedCode(string $email, string $submittedCode): bool
    {
        if (! self::isEnabled() || ! self::isEmailAllowed($email)) {
            return false;
        }

        $expected = self::configuredFixedCode();
        if ($expected === null) {
            return false;
        }

        $submitted = trim($submittedCode);
        if (! preg_match('/^\d{6}$/', $submitted)) {
            return false;
        }

        return hash_equals($expected, $submitted);
    }

    public static function maskCode(?string $code): string
    {
        $code = trim((string) $code);
        if ($code === '' || strlen($code) < 3) {
            return '***';
        }

        return substr($code, 0, 3).str_repeat('*', max(0, strlen($code) - 3));
    }

    public static function logAccepted(string $email, int $userId): void
    {
        Log::info('demo fixed OTP accepted', [
            'user_id' => $userId,
            'masked_email' => self::maskEmail($email),
        ]);
    }

    public static function configuredFixedCode(): ?string
    {
        $code = trim((string) config('ota_otp_demo.fixed_code', ''));
        if ($code === '' || ! preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        return $code;
    }

    private static function maskEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '' || ! str_contains($email, '@')) {
            return '—';
        }

        [$local, $domain] = explode('@', $email, 2);
        $local = (string) $local;
        if (strlen($local) <= 2) {
            return substr($local, 0, 1).'*@'.$domain;
        }

        return substr($local, 0, 1).str_repeat('*', max(1, strlen($local) - 2)).substr($local, -1).'@'.$domain;
    }
}
