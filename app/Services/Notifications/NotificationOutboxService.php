<?php

namespace App\Services\Notifications;

use App\Enums\NotificationQueueName;
use App\Models\NotificationOutbox;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class NotificationOutboxService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        string $eventType,
        array $payload,
        ?int $agencyId = null,
        ?string $aggregateType = null,
        ?string $aggregateId = null,
        ?int $actorUserId = null,
        ?string $eventId = null,
        int $schemaVersion = 1,
    ): NotificationOutbox {
        $eventId = $eventId !== null && $eventId !== '' ? $eventId : (string) Str::uuid();

        $existing = NotificationOutbox::query()->where('event_id', $eventId)->first();
        if ($existing !== null) {
            Log::info('notification.outbox.created', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'agency_id' => $agencyId,
                'duplicate' => true,
            ]);

            return $existing;
        }

        $row = NotificationOutbox::query()->create([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'schema_version' => $schemaVersion,
            'agency_id' => $agencyId,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'actor_user_id' => $actorUserId,
            'payload' => $this->sanitizePayload($payload),
            'status' => 'pending',
            'attempt_count' => 0,
            'available_at' => now(),
        ]);

        Log::info('notification.outbox.created', [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'agency_id' => $agencyId,
            'queue' => NotificationQueueName::forEventType($eventType)->value,
        ]);

        return $row;
    }

    public function claim(NotificationOutbox $row): ?NotificationOutbox
    {
        $updated = NotificationOutbox::query()
            ->whereKey($row->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'processing',
                'locked_at' => now(),
                'attempt_count' => $row->attempt_count + 1,
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            return null;
        }

        return $row->fresh();
    }

    public function markProcessed(NotificationOutbox $row): void
    {
        $row->forceFill([
            'status' => 'processed',
            'processed_at' => now(),
            'last_error' => null,
        ])->save();

        Log::info('notification.outbox.dispatched', [
            'event_id' => $row->event_id,
            'event_type' => $row->event_type,
            'agency_id' => $row->agency_id,
        ]);
    }

    public function markFailed(NotificationOutbox $row, Throwable $e): void
    {
        $row->forceFill([
            'status' => 'failed',
            'last_error' => $this->safeError($e->getMessage()),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function sanitizePayload(array $payload): array
    {
        foreach (['password', 'otp', 'otp_code', 'token', 'smtp_password', 'secret', 'session_id', 'cookie'] as $forbidden) {
            unset($payload[$forbidden]);
        }

        return $payload;
    }

    protected function safeError(string $message): string
    {
        $m = preg_replace('/(password|secret|token|api[_-]?key)\s*[:=]\s*\S+/i', '$1=[redacted]', $message) ?? $message;

        return mb_substr($m, 0, 2000);
    }
}
