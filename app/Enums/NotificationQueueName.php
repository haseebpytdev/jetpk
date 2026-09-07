<?php

namespace App\Enums;

enum NotificationQueueName: string
{
    case Critical = 'notifications-critical';
    case Transactional = 'notifications-transactional';
    case Ops = 'notifications-ops';
    case Bulk = 'notifications-bulk';

    public static function forEventType(string $eventType): self
    {
        $event = strtolower($eventType);

        if (str_contains($event, 'otp') || str_contains($event, 'password') || str_contains($event, 'login') || str_contains($event, 'auth_') || str_contains($event, 'verif')) {
            return self::Critical;
        }

        if (str_contains($event, 'digest') || str_contains($event, 'report') || str_contains($event, 'summary') || str_contains($event, 'ledger')) {
            return self::Bulk;
        }

        if (str_contains($event, 'support') || str_contains($event, 'supplier') || str_contains($event, 'manual_review') || str_contains($event, 'ops')) {
            return self::Ops;
        }

        return self::Transactional;
    }
}
