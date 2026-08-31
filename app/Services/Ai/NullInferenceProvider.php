<?php

namespace App\Services\Ai;

use App\Contracts\Ai\InferenceProvider;

/** Always-unhealthy provider used when AI runtime is disabled or load-shed. */
final class NullInferenceProvider implements InferenceProvider
{
    public function name(): string
    {
        return 'null';
    }

    public function isHealthy(): bool
    {
        return false;
    }

    public function complete(array $messages, int $maxTokens = 160): array
    {
        return [
            'ok' => false,
            'content' => '',
            'latency_ms' => 0,
            'mode' => 'AI_UNAVAILABLE',
            'error' => 'provider_disabled',
        ];
    }
}
