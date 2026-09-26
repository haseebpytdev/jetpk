<?php

namespace Tests\Feature\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Models\AiConversation;
use App\Services\Ai\NullInferenceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Ai\ScriptedInferenceProvider;
use Tests\TestCase;

/**
 * JP-AI-CQ28-QWEN-SEMANTIC-BRAIN-35 — scripted semantic planner authority tests.
 */
class QwenSemanticBrain28Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function enableSemanticAi(array $extra = []): void
    {
        config(array_merge([
            'ota.ai_assistant.mode' => 'public',
            'ota.ai_assistant.enabled' => true,
            'ota.ai_assistant.hard_allow.master' => true,
            'ota.ai_assistant.hard_allow.public' => true,
            'ota.ai_assistant.hard_allow.lab_adapter' => false,
            'ota.ai_assistant.hard_allow.human_handoff' => true,
            'ota.ai_assistant.flight_search_enabled' => true,
            'ota.ai_assistant.conversational_enabled' => true,
            'ota.ai_assistant.optional_llm_assist' => false,
            'ota.ai_assistant.semantic_planner_enabled' => true,
            'ota.ai_assistant.semantic_composer_enabled' => false,
            'ota.ai_assistant.knowledge_enabled' => true,
            'ota.ai_assistant.human_handoff_enabled' => true,
            'ota.ai_assistant.anonymous_per_minute' => 120,
            'ai_lab.enabled' => false,
        ], $extra));

        \App\Models\AiAssistantSetting::query()->delete();
        app(\App\Services\Ai\AiAssistantSettingsService::class)->get();
    }

    /**
     * @return array{response: \Illuminate\Testing\TestResponse, conversation_id: string}
     */
    private function chat(string $visitorId, string $message, ?string $conversationId = null): array
    {
        $payload = ['message' => $message];
        if ($conversationId !== null) {
            $payload['conversation_id'] = $conversationId;
        }

        $response = $this->withCookie('jp_ai_vid', $visitorId)
            ->postJson('/api/public/ai/chat', $payload);

        return [
            'response' => $response,
            'conversation_id' => (string) $response->json('conversation_id'),
        ];
    }

    private function planJson(array $overrides): string
    {
        $base = [
            'domain' => 'travel',
            'intent' => 'flight_search',
            'operation' => 'prepare_search',
            'travel' => [
                'trip_type' => 'one_way',
                'origin' => 'Lahore',
                'destination' => 'Doha',
                'legs' => [],
                'return_date' => null,
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
                'cabin' => null,
                'airline' => null,
                'max_stops' => null,
                'budget' => null,
                'time_preference' => null,
            ],
            'booking_reference' => null,
            'knowledge_query' => null,
            'references' => ['active_search' => false, 'pending_confirmation' => false],
            'corrections' => [],
            'missing' => [],
            'response_intent' => 'confirm_search',
        ];

        return json_encode(array_replace_recursive($base, $overrides), JSON_UNESCAPED_UNICODE);
    }

    public function test_valid_plan_requires_confirmation_before_search(): void
    {
        $this->enableSemanticAi();
        $scripted = new ScriptedInferenceProvider($this->planJson([
            'travel' => [
                'origin' => 'LHE',
                'destination' => 'DOH',
                'legs' => [['origin' => 'LHE', 'destination' => 'DOH', 'departure_date' => '2026-11-05']],
                'cabin' => 'business',
            ],
            'operation' => 'prepare_search',
        ]));
        $this->app->instance(InferenceProvider::class, $scripted);

        $turn = $this->chat(str_repeat('s1', 20), 'I need business class from Lahore to Doha on 5 November 2026');
        $turn['response']->assertOk();
        $this->assertSame('confirm', $turn['response']->json('status'));
        $this->assertSame(0, (int) $turn['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $this->assertSame('YES', $turn['response']->json('meta.SEMANTIC_BRAIN_VALID'));
        $this->assertSame('business', $turn['response']->json('confirmation_snapshot.cabin'));
        $this->assertLessThanOrEqual(2, $scripted->callCount());
    }

    public function test_open_jaw_semantic_plan_not_collapsed(): void
    {
        $this->enableSemanticAi();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider($this->planJson([
            'operation' => 'clarify',
            'travel' => [
                'trip_type' => 'open_jaw',
                'origin' => 'LHE',
                'destination' => 'JED',
                'legs' => [
                    ['origin' => 'LHE', 'destination' => 'JED', 'departure_date' => null],
                    ['origin' => 'MED', 'destination' => 'LHE', 'departure_date' => null],
                ],
            ],
            'missing' => ['leg1_departure_date', 'leg2_departure_date'],
        ])));

        $turn = $this->chat(str_repeat('s2', 20), 'I want Lahore to Jeddah and come back from Medina to Lahore');
        $turn['response']->assertOk();
        $this->assertSame('YES', $turn['response']->json('meta.OPEN_JAW_DETECTED'));
        $this->assertSame('LHE-JED', $turn['response']->json('meta.LEG1'));
        $this->assertSame('MED-LHE', $turn['response']->json('meta.LEG2'));
        $this->assertSame(0, (int) $turn['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $this->assertNotSame('confirm', $turn['response']->json('status'));
    }

    public function test_invalid_json_falls_back_without_frozen_turn(): void
    {
        $this->enableSemanticAi();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider('NOT_JSON{{{'));

        $turn = $this->chat(str_repeat('s3', 20), 'Lahore to Doha on 5 November 2026');
        $turn['response']->assertOk();
        $this->assertNotSame('', (string) $turn['response']->json('message'));
        // Hybrid fallback still produces a useful travel response.
        $this->assertContains($turn['response']->json('status'), ['confirm', 'clarify', 'ok']);
    }

    public function test_invented_iata_rejected_falls_back(): void
    {
        $this->enableSemanticAi();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider($this->planJson([
            'travel' => [
                'origin' => 'ZZZ',
                'destination' => 'DOH',
                'legs' => [['origin' => 'ZZZ', 'destination' => 'DOH', 'departure_date' => '2026-11-05']],
            ],
        ])));

        $turn = $this->chat(str_repeat('s4', 20), 'Fly ZZZ to Doha on 5 November 2026');
        $turn['response']->assertOk();
        $this->assertSame(0, (int) $turn['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS'));
    }

    public function test_forbidden_execute_now_key_rejected(): void
    {
        $this->enableSemanticAi();
        $payload = json_decode($this->planJson([
            'travel' => [
                'origin' => 'LHE',
                'destination' => 'DOH',
                'legs' => [['origin' => 'LHE', 'destination' => 'DOH', 'departure_date' => '2026-11-05']],
            ],
        ]), true);
        $payload['execute_now'] = true;
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider(json_encode($payload)));

        $turn = $this->chat(str_repeat('s5', 20), 'Lahore to Doha on 5 November 2026');
        $turn['response']->assertOk();
        $this->assertSame(0, (int) $turn['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $this->assertNotSame('ok', $turn['response']->json('status')); // must not skip confirmation
    }

    public function test_current_weather_no_hallucinated_temp(): void
    {
        $this->enableSemanticAi();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider($this->planJson([
            'domain' => 'current',
            'intent' => 'weather',
            'operation' => 'answer',
            'travel' => ['trip_type' => null, 'origin' => null, 'destination' => null, 'legs' => []],
            'response_intent' => 'current_limitation',
        ])));

        $turn = $this->chat(str_repeat('s6', 20), "What's the weather in Jeddah right now?");
        $turn['response']->assertOk();
        $this->assertSame('CURRENT_UNVERIFIED', $turn['response']->json('meta.open_domain_category'));
        $this->assertSame(0, (int) ($turn['response']->json('meta.FLIGHT_STATE_CONTAMINATION') ?? 0));
        $this->assertDoesNotMatchRegularExpression('/\d{1,3}\s*°/', (string) $turn['response']->json('message'));
    }

    public function test_handoff_via_semantic_plan(): void
    {
        $this->enableSemanticAi();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider($this->planJson([
            'domain' => 'support',
            'intent' => 'human',
            'operation' => 'handoff',
            'travel' => ['trip_type' => null, 'origin' => null, 'destination' => null, 'legs' => []],
        ])));

        $turn = $this->chat(str_repeat('s7', 20), 'Can someone on your team handle this?');
        $turn['response']->assertOk();
        $this->assertSame(AiConversation::STATE_WAITING_FOR_HUMAN, $turn['response']->json('state'));
    }

    public function test_explicit_route_overrides_stale_context(): void
    {
        $this->enableSemanticAi();
        // Seed with hybrid (null provider) then switch to semantic for override turn.
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);
        config(['ota.ai_assistant.conversational_enabled' => false, 'ota.ai_assistant.semantic_planner_enabled' => false]);
        $seed = $this->chat(str_repeat('s8', 20), 'Flights from Medina to Lahore on 10 November 2026');
        $seed['response']->assertOk();
        $cid = $seed['conversation_id'];

        config([
            'ota.ai_assistant.conversational_enabled' => true,
            'ota.ai_assistant.semantic_planner_enabled' => true,
        ]);
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider($this->planJson([
            'operation' => 'clarify',
            'travel' => [
                'trip_type' => 'one_way',
                'origin' => 'LHE',
                'destination' => 'DOH',
                'legs' => [['origin' => 'LHE', 'destination' => 'DOH', 'departure_date' => null]],
                'cabin' => null,
            ],
            'missing' => ['departure_date'],
        ])));

        $turn = $this->chat(str_repeat('s8', 20), "What's the price of a one way ticket to Doha from Lahore?", $cid);
        $turn['response']->assertOk();
        $conv = AiConversation::query()->where('public_id', $cid)->firstOrFail();
        $state = is_array($conv->shopping_state) ? $conv->shopping_state : [];
        $this->assertSame('LHE', $state['origin'] ?? null);
        $this->assertSame('DOH', $state['destination'] ?? null);
        $this->assertSame(0, (int) $turn['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS'));
    }

    public function test_missing_date_asks_only_for_date(): void
    {
        $this->enableSemanticAi();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider($this->planJson([
            'operation' => 'clarify',
            'travel' => [
                'origin' => 'LHE',
                'destination' => 'DOH',
                'cabin' => 'business',
                'legs' => [['origin' => 'LHE', 'destination' => 'DOH', 'departure_date' => null]],
            ],
            'missing' => ['departure_date'],
        ])));

        $turn = $this->chat(str_repeat('s9', 20), 'I need business class Lahore to Doha');
        $turn['response']->assertOk();
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertTrue(str_contains($body, 'date'));
        $this->assertFalse((bool) preg_match('/\bemail\b|\bphone\b/', $body));
        $this->assertSame(0, (int) $turn['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS'));
    }
}
