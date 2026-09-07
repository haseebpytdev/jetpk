<?php

namespace App\Services\Notifications;

final class NotificationIdempotency
{
    public static function key(
        string $eventId,
        string $channel,
        string $audience,
        string $recipient,
        string $variant = 'default',
    ): string {
        $normalized = strtolower(trim($recipient));

        return hash('sha256', implode('|', [
            $eventId,
            strtolower($channel),
            strtolower($audience),
            $normalized,
            strtolower($variant),
        ]));
    }
}
