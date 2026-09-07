<?php

namespace App\Services\Notifications;

use App\Enums\NotificationQueueName;
use App\Models\NotificationOutbox;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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

        try {
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
        } catch (QueryException $e) {
            if (! NotificationUniqueConstraint::causedBy($e)) {
                throw $e;
            }

            $existing = NotificationOutbox::query()->where('event_id', $eventId)->first();
            if ($existing === null) {
                throw $e;
            }

            Log::info('notification.outbox.created', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'agency_id' => $agencyId,
                'duplicate' => true,
            ]);

            return $existing;
        }

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
        $maxAttempts = $this->maxAttempts();
        $staleBefore = now()->subSeconds($this->lockTimeoutSeconds());

        $updated = NotificationOutbox::query()
            ->whereKey($row->id)
            ->where('attempt_count', '<', $maxAttempts)
            ->where(function ($query) use ($staleBefore): void {
                $query->where('status', 'pending')
                    ->orWhere(function ($processing) use ($staleBefore): void {
                        $processing->where('status', 'processing')
                            ->whereNotNull('locked_at')
                            ->where('locked_at', '<=', $staleBefore);
                    });
            })
            ->update([
                'status' => 'processing',
                'locked_at' => now(),
                'attempt_count' => DB::raw('attempt_count + 1'),
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            return null;
        }

        return $row->fresh();
    }

    public function recoverStale(): int
    {
        $staleBefore = now()->subSeconds($this->lockTimeoutSeconds());
        $maxAttempts = $this->maxAttempts();

        return NotificationOutbox::query()
            ->where('status', 'processing')
            ->whereNotNull('locked_at')
            ->where('locked_at', '<=', $staleBefore)
            ->where('attempt_count', '<', $maxAttempts)
            ->update([
                'status' => 'pending',
                'available_at' => now(),
                'locked_at' => null,
                'updated_at' => now(),
            ]);
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
        $maxAttempts = $this->maxAttempts();
        $permanent = $row->attempt_count >= $maxAttempts;

        $row->forceFill([
            'status' => $permanent ? 'failed' : 'pending',
            'available_at' => $permanent ? $row->available_at : now()->addSeconds(30),
            'locked_at' => null,
            'last_error' => $this->safeError($e->getMessage()),
        ])->save();
    }

    public function lockTimeoutSeconds(): int
    {
        return max(30, (int) config('notifications.pipeline.lock_timeout_seconds', 300));
    }

    public function maxAttempts(): int
    {
        return max(1, (int) config('notifications.pipeline.max_attempts', 8));
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
