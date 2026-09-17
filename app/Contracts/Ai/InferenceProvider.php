<?php

namespace App\Contracts\Ai;

/**
 * Narrow inference provider. Application owns tools; provider only generates text/JSON.
 */
interface InferenceProvider
{
    public function name(): string;

    public function isHealthy(): bool;

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{ok: bool, content: string, latency_ms: int, mode: string, error?: string}
     */
    public function complete(array $messages, int $maxTokens = 160): array;
}
