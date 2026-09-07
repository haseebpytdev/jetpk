<?php

namespace App\Services\Notifications;

use App\Enums\NotificationQueueName;
use App\Jobs\Notifications\DeliverNotification;
use App\Jobs\Notifications\DispatchNotificationOutboxEvent;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\NotificationOutbox;
use App\Models\User;
use App\Services\Communication\NotificationRecipientResolver;
use Illuminate\Support\Facades\DB;
use Throwable;

class NotificationPipeline
{
    public function __construct(
        protected NotificationOutboxService $outbox,
        protected NotificationDeliveryService $deliveries,
        protected NotificationRouteResolver $routes,
        protected NotificationRecipientResolver $recipients,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('notifications.pipeline.enabled', true);
    }

    public function isAsync(): bool
    {
        return (bool) config('notifications.pipeline.async', false);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $templateVariables
     * @param  array<string, mixed>  $recipientContext
     */
    public function publishOperational(
        Agency $agency,
        string $eventKey,
        array $payload,
        ?Booking $booking,
        ?User $actor,
        ?string $fallbackSubject,
        string $fallbackBody,
        array $templateVariables,
        array $recipientContext,
    ): NotificationOutbox {
        $explicitId = is_string($payload['notification_event_id'] ?? null) && $payload['notification_event_id'] !== ''
            ? (string) $payload['notification_event_id']
            : null;
        unset($payload['notification_event_id']);

        $aggregateType = $booking !== null ? 'booking' : (isset($recipientContext['aggregate_type']) && is_string($recipientContext['aggregate_type']) ? $recipientContext['aggregate_type'] : null);
        $aggregateId = $booking !== null
            ? (string) $booking->id
            : (isset($recipientContext['aggregate_id']) && is_scalar($recipientContext['aggregate_id']) ? (string) $recipientContext['aggregate_id'] : null);
        $variant = isset($recipientContext['event_variant']) && is_string($recipientContext['event_variant'])
            ? $recipientContext['event_variant']
            : '';

        $identity = NotificationEventIdentity::resolve(
            $eventKey,
            $explicitId,
            $aggregateType,
            $aggregateId,
            $variant,
        );

        $row = $this->outbox->record(
            eventType: $eventKey,
            payload: [
                'event_key' => $eventKey,
                'fallback_subject' => $fallbackSubject,
                'fallback_body' => $fallbackBody,
                'template_variables' => $this->scalarOnly($templateVariables),
                'recipient_context' => $this->safeContext($recipientContext),
                'payload' => $payload,
                'event_id_source' => $identity['source'],
            ],
            agencyId: $agency->id,
            aggregateType: $aggregateType,
            aggregateId: $aggregateId,
            actorUserId: $actor?->id,
            eventId: $identity['event_id'],
        );

        $this->dispatchOutbox($row);

        return $row;
    }

    public function dispatchOutbox(NotificationOutbox $row): void
    {
        $queue = $this->jobQueueName($row->event_type);
        $run = function () use ($row, $queue): void {
            $job = new DispatchNotificationOutboxEvent($row->id, $queue);
            if ($this->isAsync()) {
                dispatch($job);
            } else {
                dispatch_sync($job);
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($run);

            return;
        }

        $run();
    }

    public function processOutbox(NotificationOutbox $row): void
    {
        if ($row->status === 'processed') {
            return;
        }

        $claimed = $this->outbox->claim($row);
        if ($claimed === null) {
            return;
        }

        try {
            $agency = Agency::query()->find($claimed->agency_id);
            if ($agency === null) {
                $this->outbox->markFailed($claimed, new \RuntimeException('Agency missing.'));

                return;
            }

            $payload = is_array($claimed->payload) ? $claimed->payload : [];
            $eventKey = (string) ($payload['event_key'] ?? $claimed->event_type);
            $inner = is_array($payload['payload'] ?? null) ? $payload['payload'] : [];
            $templateVariables = is_array($payload['template_variables'] ?? null) ? $payload['template_variables'] : [];
            $recipientContext = is_array($payload['recipient_context'] ?? null) ? $payload['recipient_context'] : [];
            $booking = $claimed->aggregate_type === 'booking' && $claimed->aggregate_id
                ? Booking::query()->find((int) $claimed->aggregate_id)
                : null;
            $actor = $claimed->actor_user_id !== null ? User::query()->find($claimed->actor_user_id) : null;

            $resolved = $this->routes->resolve($eventKey, $agency->id);
            $routes = $resolved->routes;
            if ($routes === []) {
                $routes = [
                    new ResolvedNotificationRoute(
                        audience: 'admin',
                        recipientStrategy: 'admin',
                        queueName: NotificationQueueName::forEventType($eventKey)->value,
                        priority: NotificationQueueName::forEventType($eventKey)->name,
                        templateKey: $eventKey,
                        provider: 'laravel_mail',
                        locale: null,
                        fromLegacyFallback: true,
                    ),
                ];
            }

            $notifyBuckets = $recipientContext['notify_buckets'] ?? null;
            if (is_array($notifyBuckets) && $notifyBuckets !== []) {
                $allowed = array_fill_keys(array_values(array_filter($notifyBuckets, 'is_string')), true);
                $routes = array_values(array_filter(
                    $routes,
                    fn (ResolvedNotificationRoute $route): bool => isset($allowed[$route->audience]) || isset($allowed[$route->recipientStrategy]),
                ));
            }

            foreach ($routes as $route) {
                $bucket = $this->recipients->resolveBucket($agency, $route->recipientStrategy, $booking, $actor, $recipientContext);
                $queueName = $this->isAsync() ? $route->queueName : (string) config('notifications.pipeline.compat_queue', 'default');
                foreach ($bucket['emails'] as $email) {
                    $result = $this->deliveries->firstOrCreatePending(
                        eventId: $claimed->event_id,
                        eventType: $eventKey,
                        audience: $route->audience,
                        recipient: $email,
                        agencyId: $agency->id,
                        queueName: $queueName,
                        priority: $route->priority,
                        templateKey: $route->templateKey ?? $eventKey,
                        provider: $route->provider,
                    );
                    if (! $result['created'] && in_array($result['delivery']->status, ['sent', 'delivered', 'processing'], true)) {
                        continue;
                    }

                    $job = new DeliverNotification(
                        deliveryId: $result['delivery']->id,
                        agencyId: $agency->id,
                        eventKey: $eventKey,
                        payload: $inner,
                        bookingId: $booking?->id,
                        actorId: $actor?->id,
                        fallbackSubject: is_string($payload['fallback_subject'] ?? null) ? $payload['fallback_subject'] : null,
                        fallbackBody: is_string($payload['fallback_body'] ?? null) ? $payload['fallback_body'] : 'A new OTA event was recorded.',
                        templateVariables: $templateVariables,
                        recipientContext: array_merge($recipientContext, [
                            'force_to' => [$email],
                            'notify_buckets' => [$route->audience],
                        ]),
                        queueName: $queueName,
                    );

                    if ($this->isAsync()) {
                        dispatch($job);
                    } else {
                        dispatch_sync($job);
                    }
                }
            }

            $this->outbox->markProcessed($claimed);
        } catch (Throwable $e) {
            $this->outbox->markFailed($claimed, $e);
            throw $e;
        }
    }

    public function jobQueueName(string $eventType): string
    {
        if (! $this->isAsync()) {
            return (string) config('notifications.pipeline.compat_queue', 'default');
        }

        return NotificationQueueName::forEventType($eventType)->value;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, scalar|null>
     */
    protected function scalarOnly(array $values): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function safeContext(array $context): array
    {
        $keep = [];
        foreach ($context as $key => $value) {
            if (! is_string($key) || str_starts_with($key, '_')) {
                continue;
            }
            if (is_scalar($value) || $value === null || is_array($value)) {
                $keep[$key] = $value;
            }
        }

        return $keep;
    }
}
