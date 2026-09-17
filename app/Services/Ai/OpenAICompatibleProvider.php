<?php

namespace App\Services\Ai;

use App\Contracts\Ai\InferenceProvider;

/**
 * Future OpenAI-compatible remote provider stub.
 * Not configured or called in JP-AI-ASSIST-01C (no paid external calls).
 */
final class OpenAICompatibleProvider implements InferenceProvider
{
    public function name(): string
    {
        return 'openai_compatible';
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
            'error' => 'provider_not_configured',
        ];
    }
}
