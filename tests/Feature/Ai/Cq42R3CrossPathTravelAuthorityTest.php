<?php

namespace Tests\Feature\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Data\Ai\SemanticPlan;
use App\Models\AiConversation;
use App\Services\Ai\ConversationIntentRouter;
use App\Services\Ai\Hybrid\HybridTravelPipeline;
use App\Services\Ai\Hybrid\ServerTravelSignals;
use App\Services\Ai\Semantic\SemanticPlanValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Ai\ScriptedInferenceProvider;
use Tests\TestCase;

/**
 * JP-AI-CQ42-R3-CROSS-PATH-TRAVEL-AUTHORITY
 */
class Cq42R3CrossPathTravelAuthorityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
        Carbon::setTestNow(Carbon::parse('2026-09-27 12:00:00', 'Asia/Karachi'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

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
            'ai_embed.enabled' => false,
        ], $extra));

        \App\Models\AiAssistantSetting::query()->delete();
        app(\App\Services\Ai\AiAssistantSettingsService::class)->get();
    }

    private function rebindInference(InferenceProvider $provider): void
    {
        $this->app->instance(InferenceProvider::class, $provider);
        foreach ([
            \App\Services\Ai\Semantic\SemanticBrain::class,
            \App\Services\Ai\Semantic\QwenSemanticPlanner::class,
            \App\Services\Ai\Semantic\SemanticResponseComposer::class,
            \App\Services\Ai\AiConversationalAgent::class,
            \App\Services\Ai\AiChatOrchestrator::class,
        ] as $abstract) {
            $this->app->forgetInstance($abstract);
        }
    }

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

    private function planJson(array $overrides = []): string
    {
        $base = [
            'domain' => 'travel',
            'intent' => 'flight_search',
            'operation' => 'prepare_search',
            'travel' => [
                'trip_type' => 'one_way',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'legs' => [['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => null]],
                'return_date' => null,
                'adults' => 1,
            ],
            'missing' => [],
            'references' => ['active_search' => false, 'pending_confirmation' => false],
            'corrections' => new \stdClass,
            'response_intent' => 'need_dates',
        ];
        $merged = array_replace_recursive($base, $overrides);
        foreach (['missing', 'references'] as $listKey) {
            if (array_key_exists($listKey, $overrides)) {
                $merged[$listKey] = $overrides[$listKey];
            }
        }
        if (isset($overrides['travel']['legs'])) {
            $merged['travel']['legs'] = $overrides['travel']['legs'];
        }

        return (string) json_encode($merged, JSON_UNESCAPED_UNICODE);
    }

    public function test_server_signals_route_and_dates(): void
    {
        $signals = app(ServerTravelSignals::class);
        $route = $signals->explicitTravelRoute('dubay se lahor wapis');
        $this->assertTrue($route['explicit']);
        $this->assertSame('DXB', $route['origin']);
        $this->assertSame('LHE', $route['destination']);
        $this->assertSame('DXB-LHE', $route['server_single_route']);
        $this->assertFalse($signals->explicitReturnTripCue('Dubai se Lahore wapas'));
        $this->assertTrue($signals->explicitReturnTripCue('Lahore to Dubai tomorrow return'));

        $dates = $signals->resolveTripDates('Lahore to Dubai on 10 October, return 15 October');
        $this->assertSame('2026-10-10', $dates['depart_date']);
        $this->assertSame('2026-10-15', $dates['return_date']);
        $this->assertTrue($dates['depart_explicit']);
        $this->assertTrue($dates['return_explicit']);

        $follow = $signals->resolveTripDates('return 15 October', null, '2026-10-10');
        $this->assertNull($follow['depart_date']);
        $this->assertSame('2026-10-15', $follow['return_date']);
    }

    public function test_qwen_support_handoff_cannot_hijack_wapas_route(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'domain' => 'support',
                'intent' => 'human',
                'operation' => 'handoff',
                'travel' => [
                    'trip_type' => 'one_way',
                    'origin' => 'DXB',
                    'destination' => 'LHE',
                    'legs' => [['origin' => 'DXB', 'destination' => 'LHE', 'departure_date' => null]],
                ],
            ]),
        ]));

        $turn = $this->chat(str_repeat('r3a', 16), 'Dubai se Lahore wapas');
        $turn['response']->assertOk();
        $this->assertNotSame('WAITING_FOR_HUMAN', $turn['response']->json('state'));
        $this->assertNotSame('waiting_for_human', $turn['response']->json('status'));
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertStringNotContainsString('support queue', $body);
        $this->assertSame('YES', $turn['response']->json('meta.SERVER_ROUTE_OVERRIDES_QWEN_DOMAIN'));
        $this->assertSame('YES', $turn['response']->json('meta.SERVER_EXPLICIT_TRAVEL_ROUTE'));
        $this->assertSame('DXB-LHE', $turn['response']->json('meta.SERVER_SINGLE_ROUTE'));
        $this->assertSame(0, (int) ($turn['response']->json('meta.LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK') ?? 0));
        $this->assertMatchesRegularExpression('/DXB|Dubai/i', (string) $turn['response']->json('message'));
        $this->assertMatchesRegularExpression('/LHE|Lahore/i', (string) $turn['response']->json('message'));
    }

    public function test_qwen_booking_cannot_hijack_explicit_travel_route(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'domain' => 'booking',
                'intent' => 'booking_lookup',
                'operation' => 'lookup',
            ]),
        ]));

        $turn = $this->chat(str_repeat('r3b', 16), 'Dubai se Lahore wapas');
        $turn['response']->assertOk();
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertStringNotContainsString('booking reference', $body);
        $this->assertSame('YES', $turn['response']->json('meta.SERVER_ROUTE_OVERRIDES_QWEN_DOMAIN'));
    }

    public function test_explicit_user_handoff_still_wins(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson(['domain' => 'travel', 'operation' => 'clarify']),
        ]));

        $turn = $this->chat(str_repeat('r3c', 16), 'Talk to support');
        $turn['response']->assertOk();
        $this->assertTrue(
            str_contains(mb_strtolower((string) $turn['response']->json('message')), 'support')
            || $turn['response']->json('state') === 'WAITING_FOR_HUMAN'
        );
    }

    public function test_help_first_alias_travel_overrides_pending_lead(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'domain' => 'travel',
                'operation' => 'clarify',
                'travel' => [
                    'trip_type' => 'one_way',
                    'origin' => 'DXB',
                    'destination' => 'LHE',
                    'legs' => [['origin' => 'DXB', 'destination' => 'LHE', 'departure_date' => null]],
                ],
            ]),
        ]));

        $conv = AiConversation::query()->create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'visitor_token_hash' => hash('sha256', str_repeat('r3d', 16)),
            'channel' => 'web',
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => [
                'lead_capture_pending' => true,
                'lead_capture_stage' => 'name',
            ],
        ]);

        $turn = $this->chat(str_repeat('r3d', 16), 'dubay se lahor wapis', $conv->public_id);
        $turn['response']->assertOk();
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertStringNotContainsString('your name', $body);
        $this->assertSame('YES', $turn['response']->json('meta.LEAD_CAPTURE_OVERRIDDEN'));
        $this->assertSame('DXB-LHE', $turn['response']->json('meta.SERVER_SINGLE_ROUTE')
            ?? $turn['response']->json('meta.SERVER_SINGLE_ROUTE'));
        $intent = $turn['response']->json('intent') ?? [];
        if (is_array($intent) && ($intent['origin'] ?? null)) {
            $this->assertSame('DXB', $intent['origin']);
            $this->assertSame('LHE', $intent['destination']);
        }
    }

    public function test_bare_name_lead_non_regression(): void
    {
        $router = app(ConversationIntentRouter::class);
        $this->assertFalse($router->hasStrongActionableIntent('Muhammad Ali'));
        $this->assertFalse($router->shouldOverrideLeadCapture('Muhammad Ali'));
        $this->assertTrue($router->hasStrongActionableIntent('dubay se lahor wapis'));
    }

    public function test_hybrid_return_cue_on_invalid_json_fallback(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            'not-valid-json{{{',
        ]));

        $turn = $this->chat(str_repeat('r3e', 16), 'Lahore to Dubai tomorrow return');
        $turn['response']->assertOk();
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertStringNotContainsString('shall i search', $body);
        $this->assertStringNotContainsString('one-way', $body);
        $meta = $turn['response']->json('meta') ?? [];
        $intent = $turn['response']->json('intent')
            ?? ($meta['intent'] ?? []);
        $this->assertSame('return', $intent['trip_type'] ?? null);
        $this->assertNull($intent['return_date'] ?? null);
        $this->assertSame('clarify', $turn['response']->json('status'));
        $this->assertSame(0, (int) ($meta['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0));
        $this->assertSame(0, (int) ($meta['LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK'] ?? 0));
        $this->assertTrue(
            ($meta['RETURN_DATE_REQUIRED'] ?? '') === 'YES'
            || str_contains($body, 'return date')
        );
    }

    public function test_hybrid_dated_return_confirmation_on_invalid_json(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            'not-valid-json{{{',
        ]));

        $turn = $this->chat(str_repeat('r3f', 16), 'Lahore to Dubai on 10 October, return 15 October');
        $turn['response']->assertOk();
        $this->assertSame(0, (int) ($turn['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS') ?? 0));
        $intent = $turn['response']->json('intent')
            ?? ($turn['response']->json('meta.intent') ?? []);
        $this->assertSame('return', $intent['trip_type'] ?? null);
        $this->assertSame('2026-10-10', $intent['depart_date'] ?? null);
        $this->assertSame('2026-10-15', $intent['return_date'] ?? null);
        $confirmed = (bool) $turn['response']->json('requires_confirmation')
            || (bool) ($turn['response']->json('meta.CONFIRMATION_REQUIRED') ?? false)
            || str_contains(mb_strtolower((string) $turn['response']->json('message')), 'confirm')
            || str_contains(mb_strtolower((string) $turn['response']->json('message')), 'shall i');
        $this->assertTrue($confirmed);
    }

    public function test_semantic_dated_return_overrides_qwen_null_return(): void
    {
        $validator = app(SemanticPlanValidator::class);
        $plan = SemanticPlan::fromModelArray([
            'domain' => 'travel',
            'intent' => 'flight_search',
            'operation' => 'prepare_search',
            'travel' => [
                'trip_type' => 'return',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'legs' => [['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => '2026-10-10']],
                'return_date' => null,
                'adults' => 1,
            ],
            'missing' => [],
        ]);
        $validated = $validator->validate($plan, [], 'Lahore to Dubai on 10 October, return 15 October');
        $this->assertTrue($validated['valid']);
        $this->assertSame('return', $validated['intent']->tripType);
        $this->assertSame('2026-10-10', $validated['intent']->departDate);
        $this->assertSame('2026-10-15', $validated['intent']->returnDate);
        $this->assertNotContains('return_date', $validated['missing']);
    }

    public function test_hybrid_pipeline_return_cue_unit(): void
    {
        $hybrid = app(HybridTravelPipeline::class);
        $result = $hybrid->parse('Lahore to Dubai tomorrow return', []);
        $this->assertSame('return', $result->intent->tripType);
        $this->assertNull($result->intent->returnDate);
        $this->assertTrue($result->clarificationRequired);
        $this->assertSame('YES', $result->provenance['RETURN_DATE_REQUIRED'] ?? null);
        $this->assertSame('YES', $result->provenance['EXPLICIT_RETURN_TRIP_CUE'] ?? null);

        $dated = $hybrid->parse('Lahore to Dubai on 10 October, return 15 October', []);
        $this->assertSame('return', $dated->intent->tripType);
        $this->assertSame('2026-10-10', $dated->intent->departDate);
        $this->assertSame('2026-10-15', $dated->intent->returnDate);
        $this->assertFalse($dated->clarificationRequired);
    }

    public function test_invalid_json_spelling_alias_not_handoff(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider(['{{{bad']));

        $turn = $this->chat(str_repeat('r3g', 16), 'dubay se lahor wapis');
        $turn['response']->assertOk();
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertStringNotContainsString('support queue', $body);
        $this->assertStringNotContainsString('your name', $body);
        $this->assertNotSame('YES', $turn['response']->json('meta.OPEN_JAW_DETECTED'));
        $this->assertSame(0, (int) ($turn['response']->json('meta.LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK') ?? 0));
    }
}
