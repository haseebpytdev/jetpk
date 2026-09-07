<?php

namespace App\Services\Notifications;

use App\Enums\NotificationQueueName;
use App\Enums\OtaNotificationEvent;
use App\Models\NotificationRoute;
use App\Services\Communication\NotificationRecipientResolver;

class NotificationRouteSeeder
{
    public function seedDefaults(): int
    {
        $count = 0;

        foreach (OtaNotificationEvent::cases() as $event) {
            $buckets = NotificationRecipientResolver::policyBucketsFor($event->value);
            if ($buckets === []) {
                continue;
            }

            $queue = NotificationQueueName::forEventType($event->value);
            foreach ($buckets as $bucket) {
                if (! in_array($bucket, NotificationRouteResolver::STRATEGY_ALLOWLIST, true)) {
                    continue;
                }

                $routeKey = 'global|'.$event->value.'|email|'.$bucket;
                NotificationRoute::query()->updateOrCreate(
                    ['route_key' => $routeKey],
                    [
                        'agency_id' => null,
                        'event_type' => $event->value,
                        'channel' => 'email',
                        'audience' => $bucket,
                        'recipient_strategy' => $bucket,
                        'provider' => 'laravel_mail',
                        'template_key' => $event->value,
                        'priority' => $queue->name,
                        'queue_name' => $queue->value,
                        'enabled' => true,
                    ],
                );
                $count++;
            }
        }

        return $count;
    }
}
