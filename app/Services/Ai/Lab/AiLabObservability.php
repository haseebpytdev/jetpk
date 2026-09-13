<?php

namespace App\Services\Ai\Lab;

use Illuminate\Support\Facades\Storage;

/**
 * Redacted canary observability counters (no message content).
 */
final class AiLabObservability
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(string $event, array $meta = []): void
    {
        if (! (bool) config('ai_lab.learning_queue_enabled', true)) {
            return;
        }

        $row = [
            'ts' => now()->toIso8601String(),
            'event' => $event,
            'status' => $meta['status'] ?? null,
            'mode' => $meta['mode'] ?? null,
            'action_kind' => $meta['action_kind'] ?? null,
            'gateway_ms' => $meta['gateway_ms'] ?? null,
            'dialog_state' => $meta['dialog_state'] ?? null,
            'canary' => (bool) config('ai_lab.canary_only', false),
        ];

        Storage::disk('local')->append('ai-lab/metrics.jsonl', json_encode($row, JSON_UNESCAPED_UNICODE));
    }
}
