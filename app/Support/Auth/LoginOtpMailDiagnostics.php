<?php

namespace App\Support\Auth;

use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sanitized logging for login OTP mail delivery failures (no secrets / full OTP).
 */
final class LoginOtpMailDiagnostics
{
    /**
     * @return array<string, mixed>
     */
    public static function mailConfigSnapshot(): array
    {
        $from = Config::get('mail.from', []);

        return [
            'mailer' => (string) Config::get('mail.default', ''),
            'from_address_configured' => filled($from['address'] ?? null),
            'from_name' => is_string($from['name'] ?? null) ? (string) $from['name'] : '',
            'queue_connection' => (string) Config::get('queue.default', ''),
        ];
    }

    public static function logFailure(
        Throwable $exception,
        int $userId,
        ?string $clientSlug,
        string $maskedEmail,
        string $phase = 'send',
    ): void {
        Log::warning('Login OTP email delivery failed.', [
            'phase' => $phase,
            'user_id' => $userId,
            'client_slug' => $clientSlug,
            'masked_email' => $maskedEmail,
            'mailer' => (string) Config::get('mail.default', ''),
            'exception_class' => $exception::class,
            'error' => SensitiveDataRedactor::sanitizeErrorMessage($exception->getMessage()),
            'mail_config' => self::mailConfigSnapshot(),
        ]);
    }

    public static function userFacingMessage(?string $clientSlug): string
    {
        if ($clientSlug === 'jetpk') {
            return 'We could not send your JetPakistan verification code right now. Please try again in a minute, or contact ticketingjp@jetpakistan.com if this continues.';
        }

        return 'We could not send a verification code right now. Please try again shortly or contact support.';
    }
}
