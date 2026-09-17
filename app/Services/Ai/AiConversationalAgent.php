<?php

namespace App\Services\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Support\Facades\File;

/**
 * LLM-assisted conversational layer with approved tool dispatch.
 * Falls back to null when provider unavailable — caller uses structured pipeline.
 */
final class AiConversationalAgent
{
    public function __construct(
        private readonly InferenceProvider $provider,
        private readonly AiAssistantToolExecutor $tools,
    ) {}

    public function isEnabled(): bool
    {
        if (! (bool) config('ota.ai_assistant.conversational_enabled', true)) {
            return false;
        }

        return $this->provider->isHealthy();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>|null
     */
    public function tryHandle(AiConversation $conversation, string $message, array $meta): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $history = $this->buildHistory($conversation);
        $payload = json_encode([
            'message' => $message,
            'history' => $history,
            'state' => $conversation->shopping_state,
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->provider->complete([
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => (string) $payload],
        ], 420);

        if (! ($result['ok'] ?? false)) {
            return null;
        }

        $parsed = $this->decodeJsonObject((string) ($result['content'] ?? ''));
        if ($parsed === null) {
            return null;
        }

        $action = strtolower((string) ($parsed['action'] ?? 'respond'));
        if ($action === 'tool') {
            $tool = strtolower((string) ($parsed['tool'] ?? ''));
            if ($tool === 'human_handoff') {
                return ['handoff' => true, 'mode' => 'LLM_ASSISTED'];
            }

            $toolResult = $this->tools->execute($tool, is_array($parsed['args'] ?? null) ? $parsed['args'] : [], $conversation, $message);

            return [
                'mode' => 'LLM_ASSISTED',
                'tool' => $tool,
                'tool_result' => $toolResult,
                'message' => (string) ($toolResult['message'] ?? ''),
                'recommendations' => $toolResult['recommendations'] ?? [],
                'knowledge' => $toolResult['knowledge'] ?? [],
                'actions' => $toolResult['actions'] ?? [],
                'meta' => array_merge($meta, ['llm_tool' => $tool]),
            ];
        }

        $reply = trim((string) ($parsed['message'] ?? ''));
        if ($reply === '') {
            return null;
        }

        return [
            'mode' => 'LLM_ASSISTED',
            'message' => $reply,
            'meta' => $meta,
        ];
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function buildHistory(AiConversation $conversation): array
    {
        return $conversation->messages()
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->reverse()
            ->map(static fn (AiMessage $m): array => [
                'role' => $m->role === 'assistant' ? 'assistant' : 'user',
                'content' => $m->body,
            ])
            ->values()
            ->all();
    }

    private function systemPrompt(): string
    {
        $path = base_path('ai-assistant/prompts/assistant-conversational-system.txt');
        if (File::isFile($path)) {
            return trim((string) File::get($path));
        }

        return 'Return JSON only for Ask JetPakistan assistant actions.';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(string $content): ?array
    {
        $content = trim($content);
        if ($content === '') {
            return null;
        }

        if (preg_match('/\{[\s\S]*\}/', $content, $m) === 1) {
            $content = $m[0];
        }

        try {
            $decoded = json_decode($content, true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
