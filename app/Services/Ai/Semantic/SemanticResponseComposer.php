<?php

namespace App\Services\Ai\Semantic;

use App\Contracts\Ai\InferenceProvider;
use App\Data\Ai\SemanticPlan;
use App\Models\AiConversation;

/**
 * Natural-language composition over server-approved facts only. Never invents live data.
 */
final class SemanticResponseComposer
{
    public function __construct(
        private readonly InferenceProvider $provider,
    ) {}

    public function isEnabled(): bool
    {
        if (! (bool) config('ota.ai_assistant.semantic_composer_enabled', true)) {
            return false;
        }

        return $this->provider->isHealthy();
    }

    /**
     * @param  array<string, mixed>  $allowedFacts
     * @return array{message: ?string, latency_ms: int, calls: int}
     */
    public function compose(
        AiConversation $conversation,
        string $userMessage,
        SemanticPlan $plan,
        array $allowedFacts,
        string $fallbackMessage,
    ): array {
        if (! $this->isEnabled()) {
            return ['message' => $fallbackMessage, 'latency_ms' => 0, 'calls' => 0];
        }

        $payload = [
            'task' => 'compose_reply',
            'user_message' => $userMessage,
            'domain' => $plan->domain,
            'operation' => $plan->operation,
            'allowed_facts' => $allowedFacts,
            'fallback_message' => $fallbackMessage,
            'policy' => [
                'only_use_allowed_facts' => true,
                'no_invented_fares_schedules_weather' => true,
                'preserve_material_confirmation_fields' => true,
                'no_brand_ads' => true,
            ],
        ];

        $result = $this->provider->complete([
            [
                'role' => 'system',
                'content' => 'Compose a brief helpful JetPakistan assistant reply. Use ONLY allowed_facts. '
                    .'If fallback_message is provided for confirmation/limitation, preserve its material fields. '
                    .'Output JSON {"message":"..."}. Never invent prices, weather, or schedules.',
            ],
            ['role' => 'user', 'content' => (string) json_encode($payload, JSON_UNESCAPED_UNICODE)],
        ], 280);

        $latency = (int) ($result['latency_ms'] ?? 0);
        if (! ($result['ok'] ?? false)) {
            return ['message' => $fallbackMessage, 'latency_ms' => $latency, 'calls' => 1];
        }

        $content = trim((string) ($result['content'] ?? ''));
        $message = $fallbackMessage;
        if (preg_match('/\{[\s\S]*\}/', $content, $m) === 1) {
            try {
                $decoded = json_decode($m[0], true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded) && filled($decoded['message'] ?? null)) {
                    $candidate = trim((string) $decoded['message']);
                    if ($candidate !== '' && ! $this->looksLikeHallucinatedLiveFact($candidate, $plan)) {
                        $message = $candidate;
                    }
                }
            } catch (\Throwable) {
                // keep fallback
            }
        }

        return ['message' => $message, 'latency_ms' => $latency, 'calls' => 1];
    }

    private function looksLikeHallucinatedLiveFact(string $message, SemanticPlan $plan): bool
    {
        if ($plan->domain !== 'current') {
            return false;
        }

        return (bool) preg_match('/\d{1,3}\s*°/', $message);
    }
}
