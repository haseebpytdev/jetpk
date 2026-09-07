<?php

namespace App\Services\Notifications;

use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

final class NotificationEventIdentity
{
    public const NAMESPACE_UUID = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';

    /**
     * @return array{event_id: string, source: string, kind: string}
     */
    public static function resolve(
        string $eventType,
        ?string $explicitEventId,
        ?string $aggregateType,
        ?string $aggregateId,
        string $variant = '',
        ?string $occurrenceId = null,
    ): array {
        $kind = NotificationEventIdentityPolicy::kind($eventType);

        if (is_string($explicitEventId) && $explicitEventId !== '') {
            return ['event_id' => $explicitEventId, 'source' => 'explicit', 'kind' => $kind->value];
        }

        if (is_string($occurrenceId) && $occurrenceId !== '') {
            $name = strtolower($eventType).'|occurrence:'.$occurrenceId;
            if (is_string($aggregateType) && $aggregateType !== '' && is_string($aggregateId) && $aggregateId !== '') {
                $name = strtolower($eventType).'|'.$aggregateType.':'.$aggregateId.'|occurrence:'.$occurrenceId;
            }

            return [
                'event_id' => Uuid::uuid5(self::NAMESPACE_UUID, $name)->toString(),
                'source' => 'occurrence_record',
                'kind' => $kind->value,
            ];
        }

        if ($variant !== '') {
            $name = strtolower($eventType).'|variant:'.$variant;
            if (is_string($aggregateType) && $aggregateType !== '' && is_string($aggregateId) && $aggregateId !== '') {
                $name = strtolower($eventType).'|'.$aggregateType.':'.$aggregateId.'|variant:'.$variant;
            }

            return [
                'event_id' => Uuid::uuid5(self::NAMESPACE_UUID, $name)->toString(),
                'source' => 'variant',
                'kind' => $kind->value,
            ];
        }

        if (
            $kind === NotificationEventIdentityKind::OneShotAggregate
            && is_string($aggregateType) && $aggregateType !== ''
            && is_string($aggregateId) && $aggregateId !== ''
        ) {
            $name = strtolower($eventType).'|'.$aggregateType.':'.$aggregateId;

            return [
                'event_id' => Uuid::uuid5(self::NAMESPACE_UUID, $name)->toString(),
                'source' => 'one_shot_aggregate',
                'kind' => $kind->value,
            ];
        }

        return [
            'event_id' => (string) Str::uuid(),
            'source' => 'occurrence',
            'kind' => $kind->value,
        ];
    }
}
