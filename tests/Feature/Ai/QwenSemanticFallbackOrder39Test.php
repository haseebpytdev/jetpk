<?php

namespace Tests\Feature\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Models\AiConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Ai\ScriptedInferenceProvider;
use Tests\TestCase;

/**
 * JP-AI-CQ28-SEMANTIC-FALLBACK-ORDER-39
 *
 * Strong travel after semantic attempt must confirm via QWEN_SEMANTIC or STRUCTURED_FALLBACK,
 * never LLM_ASSISTED / PII-first lead monopolize.
 */
class QwenSemanticFallbackOrder39Test extends TestCase
{
    use RefreshDatabase;

    private const PRIMARY = 'Lahore to Dubai tomorrow for 2 adults';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function enablePlannerConversational(array $extra = []): void
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

    private function primaryPlanJson(): string
    {
        $depart = now()->addDay()->format('Y-m-d');

        return json_encode([
            'domain' => 'travel',
            'intent' => 'flight_search',
            'operation' => 'prepare_search',
            'travel' => [
                'trip_type' => 'one_way',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'legs' => [['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => $depart]],
                'return_date' => null,
                'adults' => 2,
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
        ], JSON_UNESCAPED_UNICODE);
    }

    private function assertNotPiiFirst(\Illuminate\Testing\TestResponse $response): void
    {
        $msg = mb_strtolower((string) $response->json('message'));
        $this->assertStringNotContainsString('may i start with your name', $msg);
        $this->assertDoesNotMatchRegularExpression('/\b(email|phone|contact number)\b.*\b(please|share|need)\b/u', $msg);
        $this->assertStringNotContainsString('name, email, and phone', $msg);
    }

    public function test_exact_primary_valid_qwen_confirms_without_pii_first(): void
    {
        $this->enablePlannerConversational();
        $scripted = new ScriptedInferenceProvider($this->primaryPlanJson());
        $this->app->instance(InferenceProvider::class, $scripted);

        $turn = $this->chat(str_repeat('p1', 20), self::PRIMARY);
        $turn['response']->assertOk();
        $this->assertSame('QWEN_SEMANTIC', $turn['response']->json('mode'));
        $this->assertSame('confirm', $turn['response']->json('status'));
        $this->assertSame(0, (int) $turn['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $this->assertSame('YES', $turn['response']->json('meta.SEMANTIC_BRAIN_CALLED'));
        $this->assertSame('YES', $turn['response']->json('meta.SEMANTIC_BRAIN_VALID'));
        $this->assertSame('YES', $turn['response']->json('meta.QWEN_SEMANTIC_VALID'));
        $this->assertNotSame('YES', $turn['response']->json('meta.SEMANTIC_BRAIN_FALLBACK'));
        $this->assertNotSame('LLM_ASSISTED', $turn['response']->json('mode'));
        $this->assertNotPiiFirst($turn['response']);

        $snap = $turn['response']->json('confirmation_snapshot');
        $this->assertIsArray($snap);
        $this->assertSame('LHE', $snap['origin'] ?? null);
        $this->assertSame('DXB', $snap['destination'] ?? null);
        $this->assertSame(2, (int) ($snap['adults'] ?? 0));

        $yes = $this->chat(str_repeat('p1', 20), 'Yes', $turn['conversation_id']);
        $yes['response']->assertOk();
        $this->assertSame(1, (int) $yes['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS'));
    }

    public function test_exact_primary_forced_semantic_failure_goes_hybrid_not_llm(): void
    {
        $this->enablePlannerConversational();
        // Call 1: semantic planner invalid JSON. Call 2 would be legacy LLM PII bait if not skipped.
        $scripted = new ScriptedInferenceProvider([
            'NOT_JSON{{{',
            'I need your name, email, and phone number to create a contactable inquiry before I can help.',
        ]);
        $this->app->instance(InferenceProvider::class, $scripted);

        $turn = $this->chat(str_repeat('p2', 20), self::PRIMARY);
        $turn['response']->assertOk();
        $this->assertSame('STRUCTURED_FALLBACK', $turn['response']->json('mode'));
        $this->assertSame('confirm', $turn['response']->json('status'));
        $this->assertSame(0, (int) $turn['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $this->assertSame('YES', $turn['response']->json('meta.SEMANTIC_BRAIN_CALLED'));
        $this->assertSame('NO', $turn['response']->json('meta.SEMANTIC_BRAIN_VALID'));
        $this->assertSame('YES', $turn['response']->json('meta.SEMANTIC_BRAIN_FALLBACK'));
        $this->assertNotEmpty((string) $turn['response']->json('meta.SEMANTIC_FALLBACK_REASON'));
        $this->assertSame(0, (int) $turn['response']->json('meta.LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK'));
        $this->assertNotSame('LLM_ASSISTED', $turn['response']->json('mode'));
        $this->assertNotPiiFirst($turn['response']);
        $this->assertSame(1, $scripted->callCount(), 'legacy conversational path must not call the model after semantic travel fallback');

        $snap = $turn['response']->json('confirmation_snapshot');
        $this->assertIsArray($snap);
        $this->assertSame('LHE', $snap['origin'] ?? null);
        $this->assertSame('DXB', $snap['destination'] ?? null);
        $this->assertSame(2, (int) ($snap['adults'] ?? 0));
    }

    public function test_lead_pending_plus_invalid_semantic_still_hybrid_confirms(): void
    {
        $this->enablePlannerConversational();
        $scripted = new ScriptedInferenceProvider([
            'NOT_JSON{{{',
            'Please share your email and phone number so I can continue.',
        ]);
        $this->app->instance(InferenceProvider::class, $scripted);

        $visitor = str_repeat('p3', 20);
        $hash = app(\App\Services\Ai\AiChatOrchestrator::class)->hashVisitor($visitor);
        $conversation = AiConversation::query()->create([
            'channel' => 'web',
            'visitor_token_hash' => $hash,
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => [
                'lead_capture_pending' => true,
                'lead_capture_stage' => 'ask_name',
            ],
        ]);

        $turn = $this->chat($visitor, self::PRIMARY, $conversation->public_id);
        $turn['response']->assertOk();
        $this->assertSame('STRUCTURED_FALLBACK', $turn['response']->json('mode'));
        $this->assertSame('confirm', $turn['response']->json('status'));
        $this->assertSame(0, (int) $turn['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $this->assertSame('YES', $turn['response']->json('meta.SEMANTIC_BRAIN_CALLED'));
        $this->assertSame('YES', $turn['response']->json('meta.SEMANTIC_BRAIN_FALLBACK'));
        $this->assertSame(0, (int) $turn['response']->json('meta.LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK'));
        $this->assertNotSame('LLM_ASSISTED', $turn['response']->json('mode'));
        $this->assertNotPiiFirst($turn['response']);
        $this->assertSame(1, $scripted->callCount());

        $conversation->refresh();
        $this->assertTrue((bool) data_get($conversation->shopping_state, 'lead_capture_pending'));
    }

    public function test_non_travel_semantic_fallback_may_still_use_open_domain_paths(): void
    {
        $this->enablePlannerConversational();
        // Invalid plan for a casual line — skip gate is travel-only; conversational/open-domain may continue.
        $scripted = new ScriptedInferenceProvider('NOT_JSON{{{');
        $this->app->instance(InferenceProvider::class, $scripted);

        $turn = $this->chat(str_repeat('p4', 20), 'Hello there');
        $turn['response']->assertOk();
        $this->assertNotSame('', (string) $turn['response']->json('message'));
    }
}
