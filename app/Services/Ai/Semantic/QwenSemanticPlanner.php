<?php

namespace App\Services\Ai\Semantic;

use App\Contracts\Ai\InferenceProvider;
use App\Data\Ai\SemanticPlan;
use App\Models\AiConversation;
use App\Models\AiMessage;

/**
 * Qwen (local OpenAI-compatible) semantic planner — advisory only; never executes tools.
 */
final class QwenSemanticPlanner
{
    public function __construct(
        private readonly InferenceProvider $provider,
    ) {}

    public function isEnabled(): bool
    {
        if (! (bool) config('ota.ai_assistant.semantic_planner_enabled', true)) {
            return false;
        }
        if (! (bool) config('ota.ai_assistant.conversational_enabled', true)) {
            return false;
        }

        return $this->provider->isHealthy();
    }

    /**
     * @param  array<string, mixed>  $context  sanitized shopping/capability context
     * @return array{plan: ?SemanticPlan, latency_ms: int, calls: int, valid_json: bool, error: ?string}
     */
    public function plan(AiConversation $conversation, string $message, array $context): array
    {
        if (! $this->isEnabled()) {
            return [
                'plan' => null,
                'latency_ms' => 0,
                'calls' => 0,
                'valid_json' => false,
                'error' => 'disabled_or_unhealthy',
            ];
        }

        $payload = [
            'message' => $message,
            'history' => $this->buildHistory($conversation),
            'authoritative_state' => $context['shopping_state'] ?? [],
            'pending_confirmation' => $context['pending_confirmation'] ?? null,
            'last_flight_search' => $context['last_flight_search'] ?? null,
            'conversation_state' => $conversation->state,
            'brand' => $context['brand'] ?? 'JetPakistan',
            'capabilities' => $context['capabilities'] ?? [],
            'instruction' => 'Emit ONE JSON object matching the semantic plan schema. Do not authorize tools. Do not invent live fares/weather.',
        ];

        $result = $this->provider->complete([
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => (string) json_encode($payload, JSON_UNESCAPED_UNICODE)],
        ], 480);

        $latency = (int) ($result['latency_ms'] ?? 0);
        if (! ($result['ok'] ?? false)) {
            return [
                'plan' => null,
                'latency_ms' => $latency,
                'calls' => 1,
                'valid_json' => false,
                'error' => (string) ($result['error'] ?? 'inference_failed'),
            ];
        }

        $decoded = $this->decodeJsonObject((string) ($result['content'] ?? ''));
        if ($decoded === null) {
            return [
                'plan' => null,
                'latency_ms' => $latency,
                'calls' => 1,
                'valid_json' => false,
                'error' => 'invalid_json',
            ];
        }

        // Open-domain style payloads ({"message":"..."}) belong to tryOpenDomainRespond,
        // not the semantic travel planner.
        if (! isset($decoded['domain']) && ! isset($decoded['operation']) && isset($decoded['message'])) {
            return [
                'plan' => null,
                'latency_ms' => $latency,
                'calls' => 1,
                'valid_json' => true,
                'error' => 'open_domain_payload',
            ];
        }

        // Reject legacy tool-authority schema — SemanticBrain never executes tools.
        if (isset($decoded['action']) && in_array(strtolower((string) $decoded['action']), ['tool', 'respond'], true)
            && ! isset($decoded['domain']) && ! isset($decoded['operation'])) {
            return [
                'plan' => null,
                'latency_ms' => $latency,
                'calls' => 1,
                'valid_json' => true,
                'error' => 'legacy_schema',
            ];
        }

        // Reject forbidden authorization / mutation keys before DTO construction.
        foreach (['authorize', 'execute_now', 'supplier_credentials', 'mutation', 'database'] as $bad) {
            if (array_key_exists($bad, $decoded)) {
                return [
                    'plan' => null,
                    'latency_ms' => $latency,
                    'calls' => 1,
                    'valid_json' => true,
                    'error' => 'forbidden_key:'.$bad,
                ];
            }
        }
        unset(
            $decoded['tool'],
            $decoded['args'],
        );

        try {
            $plan = SemanticPlan::fromModelArray($decoded);
        } catch (\Throwable) {
            return [
                'plan' => null,
                'latency_ms' => $latency,
                'calls' => 1,
                'valid_json' => true,
                'error' => 'schema_reject',
            ];
        }

        return [
            'plan' => $plan,
            'latency_ms' => $latency,
            'calls' => 1,
            'valid_json' => true,
            'error' => null,
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are the JetPakistan Ask AI semantic planner. Output ONLY a single JSON object.

Schema:
{
  "domain": "travel|booking|knowledge|current|general|support|casual",
  "intent": "short label",
  "operation": "answer|clarify|prepare_search|lookup|handoff|none",
  "travel": {
    "trip_type": "one_way|return|open_jaw|multi_city|null",
    "origin": "city or IATA or null",
    "destination": "city or IATA or null",
    "legs": [{"origin":"...","destination":"...","departure_date":"YYYY-MM-DD|null"}],
    "return_date": null,
    "adults": 1, "children": 0, "infants": 0,
    "cabin": "economy|premium_economy|business|first|null",
    "airline": null, "max_stops": null, "budget": null, "time_preference": null
  },
  "booking_reference": null,
  "knowledge_query": null,
  "references": {"active_search": false, "pending_confirmation": false},
  "corrections": {},
  "missing": [],
  "response_intent": "brief"
}

Rules:
- Prefer CURRENT EXPLICIT user turn over older conversation state.
- Open-jaw / multi-city: use trip_type open_jaw or multi_city with legs; never collapse to one O/D.
- Do not invent IATA codes, dates, fares, weather, or live schedules.
- Do not authorize tools, mutations, or booking disclosure.
- For live/current questions (weather, now): domain=current, operation=answer.
- For support/human: domain=support, operation=handoff.
- For timeless general facts: domain=general, operation=answer.
- If travel fields incomplete: operation=clarify and list missing keys.
- If travel is ready for confirmation (not search execution): operation=prepare_search.
PROMPT;
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function buildHistory(AiConversation $conversation): array
    {
        $rows = AiMessage::query()
            ->where('ai_conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->reverse()
            ->values();

        $out = [];
        foreach ($rows as $row) {
            $role = $row->role === 'assistant' ? 'assistant' : 'user';
            $body = trim((string) $row->body);
            if ($body === '') {
                continue;
            }
            $out[] = ['role' => $role, 'content' => mb_substr($body, 0, 500)];
        }

        return $out;
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
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
