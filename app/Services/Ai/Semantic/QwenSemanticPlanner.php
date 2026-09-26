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
        if (! (bool) config('ota.ai_assistant.semantic_planner_enabled', false)) {
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

        // Normalize dotted keys some small models emit (travel.origin).
        foreach (array_keys($decoded) as $k) {
            if (! is_string($k) || ! str_contains($k, '.')) {
                continue;
            }
            $parts = explode('.', $k, 2);
            if (count($parts) !== 2) {
                continue;
            }
            if (! isset($decoded[$parts[0]]) || ! is_array($decoded[$parts[0]])) {
                $decoded[$parts[0]] = [];
            }
            $decoded[$parts[0]][$parts[1]] = $decoded[$k];
            unset($decoded[$k]);
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
You are the JetPakistan Ask AI semantic planner. Output ONLY one JSON object. No markdown.

Allowed domains: travel|booking|knowledge|current|general|support|casual
Allowed operations: answer|clarify|prepare_search|lookup|handoff|none

Example for "Lahore to Doha on 5 November 2026":
{"domain":"travel","intent":"flight_search","operation":"prepare_search","travel":{"trip_type":"one_way","origin":"Lahore","destination":"Doha","legs":[{"origin":"Lahore","destination":"Doha","departure_date":"2026-11-05"}],"return_date":null,"adults":1,"children":0,"infants":0,"cabin":null,"airline":null,"max_stops":null,"budget":null,"time_preference":null},"booking_reference":null,"knowledge_query":null,"references":{"active_search":false,"pending_confirmation":false},"corrections":{},"missing":[],"response_intent":"confirm_search"}

Example open-jaw "Lahore to Jeddah then Medina to Lahore":
{"domain":"travel","intent":"open_jaw","operation":"clarify","travel":{"trip_type":"open_jaw","origin":"Lahore","destination":"Jeddah","legs":[{"origin":"Lahore","destination":"Jeddah","departure_date":null},{"origin":"Medina","destination":"Lahore","departure_date":null}],"adults":1,"children":0,"infants":0,"cabin":null},"missing":["leg1_departure_date","leg2_departure_date"],"references":{"active_search":false,"pending_confirmation":false},"corrections":{},"booking_reference":null,"knowledge_query":null,"response_intent":"need_dates"}

Example weather now:
{"domain":"current","intent":"weather","operation":"answer","travel":{"trip_type":null,"origin":null,"destination":null,"legs":[],"adults":1,"children":0,"infants":0},"missing":[],"references":{"active_search":false,"pending_confirmation":false},"corrections":{},"booking_reference":null,"knowledge_query":null,"response_intent":"live_limitation"}

Example support:
{"domain":"support","intent":"human","operation":"handoff","travel":{"trip_type":null,"origin":null,"destination":null,"legs":[],"adults":1,"children":0,"infants":0},"missing":[],"references":{"active_search":false,"pending_confirmation":false},"corrections":{},"booking_reference":null,"knowledge_query":null,"response_intent":"handoff"}

Rules:
- Prefer CURRENT EXPLICIT user turn over older conversation state.
- Open-jaw / multi-city: trip_type open_jaw or multi_city with legs; never collapse to one O/D.
- "X se Y wapis/wapas" (or "X to Y return" without a second sector) is ONE route X→Y — NOT open-jaw. Never invent a reciprocal Y→X second leg.
- Do not invent IATA codes, fares, weather numbers, or live schedules.
- Do not authorize tools or mutations.
- Put cities/dates inside travel.origin travel.destination travel.legs — not dotted keys.
- If travel fields incomplete: operation=clarify and list missing.
- If travel ready for confirmation: operation=prepare_search.
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
