<?php

namespace App\Support\Communication;

use Illuminate\Support\Str;

/**
 * Shared sanitizer for notification/mail error_message persistence.
 */
final class NotificationErrorSanitizer
{
    public static function sanitize(string $message, ?string $smtpPassword = null): string
    {
        $m = $message;
        if (filled($smtpPassword)) {
            $m = str_replace((string) $smtpPassword, '[REDACTED]', $m);
        }
        $m = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer [redacted]', $m) ?? $m;
        $m = preg_replace('/duffel_(test|live)_[A-Za-z0-9._\-]+/i', 'duffel_$1_[redacted]', $m) ?? $m;
        $m = preg_replace('/(api[_-]?key|access[_-]?token|client[_-]?secret|password)\s*[:=]\s*\S+/i', '$1=[redacted]', $m) ?? $m;
        $m = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '[REDACTED_EMAIL]', $m) ?? $m;

        return Str::limit((string) $m, 2000);
    }
}
