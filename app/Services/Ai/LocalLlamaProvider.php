<?php

namespace App\Services\Ai;

use App\Contracts\Ai\InferenceProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI-compatible chat completions against localhost llama-server only.
 */
final class LocalLlamaProvider implements InferenceProvider
{
    public function name(): string
    {
        return 'local_llama';
    }

    public function isHealthy(): bool
    {
        $base = rtrim((string) config('ota.ai_assistant.gateway_url', 'http://127.0.0.1:3921'), '/');
        if (! str_starts_with($base, 'http://127.0.0.1') && ! str_starts_with($base, 'http://localhost')) {
            return false;
        }

        try {
            $resp = Http::timeout(2)->get($base.'/health');
            if ($resp->successful()) {
                return true;
            }
        } catch (\Throwable) {
            // fall through
        }

        try {
            $resp = Http::timeout(2)->get($base.'/v1/models');

            return $resp->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function complete(array $messages, int $maxTokens = 160): array
    {
        $base = rtrim((string) config('ota.ai_assistant.gateway_url', 'http://127.0.0.1:3921'), '/');
        if (! str_starts_with($base, 'http://127.0.0.1') && ! str_starts_with($base, 'http://localhost')) {
            return [
                'ok' => false,
                'content' => '',
                'latency_ms' => 0,
                'mode' => 'AI_UNAVAILABLE',
                'error' => 'non_localhost_gateway',
            ];
        }

        $started = (int) (microtime(true) * 1000);
        try {
            $resp = Http::timeout((int) config('ota.ai_assistant.timeout_seconds', 45))
                ->post($base.'/v1/chat/completions', [
                    'model' => (string) config('ota.ai_assistant.model_id', 'local'),
                    'messages' => $messages,
                    'max_tokens' => max(16, min(512, $maxTokens)),
                    'temperature' => 0,
                    // Qwen3.5 otherwise fills reasoning_content and leaves content empty.
                    'chat_template_kwargs' => [
                        'enable_thinking' => false,
                    ],
                ]);
            $latency = (int) (microtime(true) * 1000) - $started;
            if (! $resp->successful()) {
                return [
                    'ok' => false,
                    'content' => '',
                    'latency_ms' => $latency,
                    'mode' => 'AI_UNAVAILABLE',
                    'error' => 'http_'.$resp->status(),
                ];
            }
            $json = $resp->json();
            $content = trim((string) data_get($json, 'choices.0.message.content', ''));
            if ($content === '') {
                $content = trim((string) (
                    data_get($json, 'choices.0.message.reasoning_content')
                    ?? data_get($json, 'choices.0.message.reasoning')
                    ?? ''
                ));
            }

            return [
                'ok' => $content !== '',
                'content' => $content,
                'latency_ms' => $latency,
                'mode' => 'AI_FULL',
            ];
        } catch (\Throwable $e) {
            Log::warning('ai.local_llama.complete_failed', ['error' => $e->getMessage()]);

            return [
                'ok' => false,
                'content' => '',
                'latency_ms' => (int) (microtime(true) * 1000) - $started,
                'mode' => 'AI_UNAVAILABLE',
                'error' => 'exception',
            ];
        }
    }
}
