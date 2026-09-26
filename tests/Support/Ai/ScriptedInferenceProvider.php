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

    /** @var list<int> */
    private array $latencies;

    private int $calls = 0;

    /**
     * @param  string|list<string>  $responses  JSON or plain message bodies returned in order
     * @param  int|list<int>|null  $latencies  Optional per-call latency_ms (cycles last when shorter than queue)
     */
    public function __construct(
        string|array $responses,
        private readonly bool $healthy = true,
        int|array|null $latencies = null,
    ) {
        $this->queue = array_values(is_array($responses) ? $responses : [$responses]);
        if ($latencies === null) {
            $this->latencies = [1];
        } elseif (is_int($latencies)) {
            $this->latencies = [$latencies];
        } else {
            $this->latencies = array_values(array_map(static fn ($v): int => max(0, (int) $v), $latencies));
            if ($this->latencies === []) {
                $this->latencies = [1];
            }
        }
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
        $latency = $this->latencies[min($this->calls - 1, count($this->latencies) - 1)] ?? 1;
        if (! $this->healthy) {
            return [
                'ok' => false,
                'content' => '',
                'latency_ms' => $latency,
                'mode' => 'AI_UNAVAILABLE',
                'error' => 'unhealthy',
            ];
        }

        $content = $this->queue[min($this->calls - 1, count($this->queue) - 1)] ?? '';

        return [
            'ok' => true,
            'content' => $content,
            'latency_ms' => $latency,
            'mode' => 'LLM_ASSISTED',
        ];
    }

    public function callCount(): int
    {
        return $this->calls;
    }
}
