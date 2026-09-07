<?php

namespace App\Services\Notifications;

use App\Models\NotificationDelivery;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class NotificationDeliveryService
{
    /**
     * @return array{delivery: NotificationDelivery, created: bool}
     */
    public function firstOrCreatePending(
        string $eventId,
        string $eventType,
        string $audience,
        string $recipient,
        ?int $agencyId,
        string $queueName,
        string $priority,
        ?string $templateKey = null,
        string $channel = 'email',
        ?int $recipientUserId = null,
        ?string $provider = null,
    ): array {
        $key = NotificationIdempotency::key($eventId, $channel, $audience, $recipient);

        try {
            $delivery = NotificationDelivery::query()->create([
                'event_id' => $eventId,
                'event_type' => $eventType,
                'agency_id' => $agencyId,
                'channel' => $channel,
                'audience' => $audience,
                'recipient' => strtolower(trim($recipient)),
                'recipient_user_id' => $recipientUserId,
                'provider' => $provider ?: (string) config('mail.default'),
                'template_key' => $templateKey ?? $eventType,
                'template_version' => 'jetpk-local-v1',
                'queue_name' => $queueName,
                'priority' => $priority,
                'idempotency_key' => $key,
                'status' => 'pending',
                'queued_at' => now(),
            ]);
        } catch (QueryException $e) {
            if (! NotificationUniqueConstraint::causedBy($e)) {
                throw $e;
            }

            $existing = NotificationDelivery::query()->where('idempotency_key', $key)->first();
            if ($existing === null) {
                throw $e;
            }

            Log::info('notification.delivery.skipped_duplicate', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'delivery_id' => $existing->id,
                'audience' => $audience,
            ]);

            return ['delivery' => $existing, 'created' => false];
        }

        Log::info('notification.delivery.queued', [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'delivery_id' => $delivery->id,
            'agency_id' => $agencyId,
            'queue' => $queueName,
            'audience' => $audience,
            'provider' => $delivery->provider,
        ]);

        return ['delivery' => $delivery, 'created' => true];
    }

    public function beginAttempt(NotificationDelivery $delivery): bool
    {
        if (in_array($delivery->status, ['sent', 'delivered'], true)) {
            return false;
        }

        $delivery->forceFill([
            'status' => 'processing',
            'processing_at' => now(),
            'failed_at' => null,
            'attempt_count' => $delivery->attempt_count + 1,
        ])->save();

        return true;
    }

    public function markSent(NotificationDelivery $delivery, ?string $providerMessageId = null): void
    {
        $delivery->forceFill([
            'status' => 'sent',
            'sent_at' => now(),
            'provider_message_id' => $providerMessageId,
            'last_error' => null,
            'failed_at' => null,
        ])->save();

        Log::info('notification.delivery.sent', [
            'event_id' => $delivery->event_id,
            'event_type' => $delivery->event_type,
            'delivery_id' => $delivery->id,
            'agency_id' => $delivery->agency_id,
            'queue' => $delivery->queue_name,
            'audience' => $delivery->audience,
            'provider' => $delivery->provider,
            'attempt' => $delivery->attempt_count,
        ]);
    }

    public function markFailed(NotificationDelivery $delivery, string $error): void
    {
        $safe = preg_replace('/(password|secret|token|api[_-]?key)\s*[:=]\s*\S+/i', '$1=[redacted]', $error) ?? $error;
        $delivery->forceFill([
            'status' => 'failed',
            'failed_at' => now(),
            'last_error' => mb_substr($safe, 0, 2000),
        ])->save();

        Log::warning('notification.delivery.failed', [
            'event_id' => $delivery->event_id,
            'event_type' => $delivery->event_type,
            'delivery_id' => $delivery->id,
            'agency_id' => $delivery->agency_id,
            'attempt' => $delivery->attempt_count,
        ]);
    }
}
