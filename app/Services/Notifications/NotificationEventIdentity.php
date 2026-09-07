<?php

namespace App\Services\Notifications;

use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

final class NotificationEventIdentity
{
    public const NAMESPACE_UUID = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';

    /**
     * @return array{event_id: string, source: string}
     */
    public static function resolve(
        string $eventType,
        ?string $explicitEventId,
        ?string $aggregateType,
        ?string $aggregateId,
        string $variant = '',
    ): array {
        if (is_string($explicitEventId) && $explicitEventId !== '') {
            return ['event_id' => $explicitEventId, 'source' => 'explicit'];
        }

        if (self::isOccurrenceScoped($eventType)) {
            return ['event_id' => (string) Str::uuid(), 'source' => 'occurrence'];
        }

        if (is_string($aggregateType) && $aggregateType !== '' && is_string($aggregateId) && $aggregateId !== '') {
            $name = strtolower($eventType).'|'.$aggregateType.':'.$aggregateId;
            if ($variant !== '') {
                $name .= '|'.$variant;
            }

            return [
                'event_id' => Uuid::uuid5(self::NAMESPACE_UUID, $name)->toString(),
                'source' => 'semantic',
            ];
        }

        return ['event_id' => (string) Str::uuid(), 'source' => 'generated_no_aggregate'];
    }

    public static function isOccurrenceScoped(string $eventType): bool
    {
        $event = strtolower($eventType);

        return str_contains($event, 'login')
            || str_contains($event, 'otp')
            || str_contains($event, 'password_reset')
            || str_contains($event, 'auth_new_device');
    }
}
