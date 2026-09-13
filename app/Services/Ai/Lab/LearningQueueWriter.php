<?php

namespace App\Services\Ai\Lab;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Persist redacted structured learning events (shadow integration).
 */
final class LearningQueueWriter
{
    public function __construct(
        private readonly LearningEventRedactor $redactor,
    ) {}

    /**
     * @param  array<string, mixed>  $event
     */
    public function enqueue(array $event): void
    {
        if (! (bool) config('ai_lab.learning_queue_enabled', true)) {
            return;
        }

        $event = $this->redactor->redact($event);
        if (! $this->redactor->assertRedacted($event)) {
            return;
        }

        $event['event_id'] = (string) ($event['event_id'] ?? Str::uuid());
        $event['timestamp'] = (string) ($event['timestamp'] ?? now()->toIso8601String());
        $event['review_status'] = (string) ($event['review_status'] ?? 'pending');

        $driver = (string) config('ai_lab.learning_driver', 'jsonl');
        if ($driver === 'jsonl') {
            $path = 'ai-lab/learning/events.jsonl';
            Storage::disk('local')->append($path, json_encode($event, JSON_UNESCAPED_UNICODE));
        }
    }
}
