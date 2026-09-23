<?php

namespace Tests\Support\Ai;

use App\Contracts\Ai\InferenceProvider;

/**
 * Deterministic healthy inference provider for AI-first conversation tests.
 */
final class ScriptedInferenceProvider implements InferenceProvider
{
    /** @var list<string> */
    private array $queue;

    private int $calls = 0;

    /**
     * @param  string|list<string>  $responses  JSON or plain message bodies returned in order
     */
    public function __construct(
        string|array $responses,
        private readonly bool $healthy = true,
    ) {
        $this->queue = array_values(is_array($responses) ? $responses : [$responses]);
    }

    public function name(): string
    {
        return 'scripted';
    }

    public function isHealthy(): bool
    {
        return $this->healthy;
    }

    public function complete(array $messages, int $maxTokens = 160): array
    {
        $this->calls++;
        if (! $this->healthy) {
            return [
                'ok' => false,
                'content' => '',
                'latency_ms' => 1,
                'mode' => 'AI_UNAVAILABLE',
                'error' => 'unhealthy',
            ];
        }

        $content = $this->queue[min($this->calls - 1, count($this->queue) - 1)] ?? '';

        return [
            'ok' => true,
            'content' => $content,
            'latency_ms' => 1,
            'mode' => 'LLM_ASSISTED',
        ];
    }

    public function callCount(): int
    {
        return $this->calls;
    }
}
