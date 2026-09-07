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
        $eventId = is_string($payload['notification_event_id'] ?? null) && $payload['notification_event_id'] !== ''
            ? (string) $payload['notification_event_id']
            : null;
        unset($payload['notification_event_id']);

        $row = $this->outbox->record(
            eventType: $eventKey,
            payload: [
                'event_key' => $eventKey,
                'fallback_subject' => $fallbackSubject,
                'fallback_body' => $fallbackBody,
                'template_variables' => $this->scalarOnly($templateVariables),
                'recipient_context' => $this->safeContext($recipientContext),
                'payload' => $payload,
            ],
            agencyId: $agency->id,
            aggregateType: $booking !== null ? 'booking' : null,
            aggregateId: $booking !== null ? (string) $booking->id : null,
            actorUserId: $actor?->id,
            eventId: $eventId,
        );

        $this->dispatchOutbox($row);

        return $row;
    }

    public function dispatchOutbox(NotificationOutbox $row): void
    {
        $job = new DispatchNotificationOutboxEvent($row->id);
        if ((bool) config('notifications.pipeline.async', false)) {
            dispatch($job);
        } else {
            dispatch_sync($job);
        }
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

            $resolvedRoutes = $this->routes->audiencesFor($eventKey);
            $audiences = $recipientContext['notify_buckets'] ?? $resolvedRoutes['audiences'];
            if (! is_array($audiences) || $audiences === []) {
                $audiences = ['admin'];
            }

            $queueName = NotificationRouteResolver::queueFor($eventKey);
            $priority = NotificationQueueName::forEventType($eventKey)->name;

            foreach ($audiences as $audience) {
                if (! is_string($audience) || $audience === '') {
                    continue;
                }
                $bucket = $this->recipients->resolveBucket($agency, $audience, $booking, $actor, $recipientContext);
                foreach ($bucket['emails'] as $email) {
                    $result = $this->deliveries->firstOrCreatePending(
                        eventId: $claimed->event_id,
                        eventType: $eventKey,
                        audience: $audience,
                        recipient: $email,
                        agencyId: $agency->id,
                        queueName: $queueName,
                        priority: $priority,
                        templateKey: $eventKey,
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
                            'notify_buckets' => [$audience],
                        ]),
                    );

                    if ((bool) config('notifications.pipeline.async', false)) {
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
