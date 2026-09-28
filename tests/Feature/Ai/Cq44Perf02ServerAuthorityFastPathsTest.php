<?php

namespace Tests\Feature\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Models\AiConversation;
use App\Services\Ai\FlightSearchConfirmationGate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Ai\ScriptedInferenceProvider;
use Tests\TestCase;

/**
 * CQ44-PERF-02 — CURRENT_UNVERIFIED + complete dated explicit A→B server fast paths.
 */
class Cq44Perf02ServerAuthorityFastPathsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
        Carbon::setTestNow(Carbon::parse('2026-09-28 12:00:00', 'Asia/Karachi'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function enableSemanticAi(): void
    {
        config([
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
        ]);

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
            \App\Services\Ai\OpenDomainResponseService::class,
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
            'json' => $response->json(),
        ];
    }

    private function planJson(array $overrides = []): string
    {
        $base = [
            'domain' => 'travel',
            'intent' => 'flight_search',
            'operation' => 'clarify',
            'travel' => [
                'trip_type' => 'one_way',
                'origin' => null,
                'destination' => 'DXB',
                'legs' => [['origin' => null, 'destination' => 'DXB', 'departure_date' => null]],
                'return_date' => null,
                'adults' => 1,
            ],
            'missing' => ['origin'],
            'references' => ['active_search' => false, 'pending_confirmation' => false],
            'corrections' => new \stdClass,
            'response_intent' => 'need_origin',
        ];

        return (string) json_encode(array_replace_recursive($base, $overrides), JSON_UNESCAPED_UNICODE);
    }

    private function reloadState(string $conversationId): array
    {
        $conv = AiConversation::query()->where('public_id', $conversationId)->firstOrFail();

        return is_array($conv->shopping_state) ? $conv->shopping_state : [];
    }

    private function assertSafeCurrentRefusal(array $turn, string $needle): void
    {
        $turn['response']->assertOk();
        $this->assertSame(0, (int) data_get($turn['json'], 'meta.MODEL_CALLS', -1));
        $this->assertSame(
            'DETERMINISTIC_CURRENT_UNVERIFIED',
            (string) data_get($turn['json'], 'meta.FINAL_RESPONSE_SOURCE')
        );
        $this->assertSame('YES', (string) data_get($turn['json'], 'meta.SEMANTIC_PLANNER_BYPASSED'));
        $this->assertSame('NO', (string) data_get($turn['json'], 'meta.HALLUCINATED_LIVE_FACT'));
        $this->assertSame(0, (int) data_get($turn['json'], 'meta.FLIGHT_STATE_CONTAMINATION', -1));
        $body = mb_strtolower((string) ($turn['json']['message'] ?? ''));
        $this->assertTrue(
            str_contains($body, 'approved')
            || str_contains($body, 'verify')
            || str_contains($body, 'live')
            || str_contains($body, $needle),
            $body
        );
    }

    public function test_bitcoin_current_fast_path_zero_qwen(): void
    {
        $this->enableSemanticAi();
        $provider = new ScriptedInferenceProvider(['SHOULD_NOT_BE_CALLED']);
        $this->rebindInference($provider);

        $vid = str_repeat('p02b', 8);
        $t = $this->chat($vid, "What is Bitcoin's price right now?", null);
        $this->assertSafeCurrentRefusal($t, 'market');
        $this->assertSame('market', (string) data_get($t['json'], 'meta.CURRENT_TOPIC'));
        $this->assertSame(0, $provider->callCount());
    }

    public function test_weather_current_fast_path_zero_qwen(): void
    {
        $this->enableSemanticAi();
        $provider = new ScriptedInferenceProvider(['SHOULD_NOT_BE_CALLED']);
        $this->rebindInference($provider);

        $vid = str_repeat('p02w', 8);
        $t = $this->chat($vid, "What's the weather in Dubai right now?", null);
        $this->assertSafeCurrentRefusal($t, 'weather');
        $this->assertSame('weather', (string) data_get($t['json'], 'meta.CURRENT_TOPIC'));
        $this->assertSame(0, $provider->callCount());
    }

    public function test_current_mid_trip_preserves_travel_state(): void
    {
        $this->enableSemanticAi();
        $provider = new ScriptedInferenceProvider([
            $this->planJson(),
            'SHOULD_NOT_BE_USED_FOR_CURRENT',
            'SHOULD_NOT_BE_USED_FOR_BACK',
        ]);
        $this->rebindInference($provider);

        $vid = str_repeat('p02m', 8);
        $t1 = $this->chat($vid, 'I need Dubai', null);
        $cid = $t1['conversation_id'];
        $this->chat($vid, 'from Lahore', $cid);
        $this->chat($vid, 'next Friday', $cid);
        $this->chat($vid, '2 adults', $cid);
        $before = $provider->callCount();
        $this->assertSame(1, $before);

        $stateBefore = $this->reloadState($cid);
        $this->assertSame('LHE', $stateBefore['origin'] ?? null);
        $this->assertSame('DXB', $stateBefore['destination'] ?? null);
        $this->assertSame('2026-10-02', $stateBefore['depart_date'] ?? null);
        $this->assertSame(2, (int) ($stateBefore['adults'] ?? 0));
        $this->assertNotNull($stateBefore[FlightSearchConfirmationGate::STATE_KEY] ?? null);

        $cur = $this->chat($vid, "What is Bitcoin's price right now?", $cid);
        $this->assertSafeCurrentRefusal($cur, 'market');
        $this->assertSame($before, $provider->callCount());

        $stateAfter = $this->reloadState($cid);
        foreach (['origin', 'destination', 'depart_date', 'return_date', 'trip_type', 'adults', 'children', 'infants', 'cabin', 'legs'] as $k) {
            $this->assertSame($stateBefore[$k] ?? null, $stateAfter[$k] ?? null, "Travel field {$k} must be preserved");
        }
        $this->assertNotNull($stateAfter[FlightSearchConfirmationGate::STATE_KEY] ?? null);

        $back = $this->chat($vid, 'back to my flight', $cid);
        $back['response']->assertOk();
        $stateBack = $this->reloadState($cid);
        $this->assertSame('LHE', $stateBack['origin'] ?? null);
        $this->assertSame('DXB', $stateBack['destination'] ?? null);
        $this->assertSame('2026-10-02', $stateBack['depart_date'] ?? null);
        $this->assertSame(2, (int) ($stateBack['adults'] ?? 0));
    }

    public function test_gold_and_fx_and_gk_classification_non_regression(): void
    {
        $this->enableSemanticAi();
        $provider = new ScriptedInferenceProvider([
            'Gold price answer from open domain.',
            'FX answer from open domain.',
            'Gravity is the attractive force between masses.',
        ]);
        $this->rebindInference($provider);

        $vid = str_repeat('p02g', 8);

        $gold = $this->chat($vid, 'What is gold price right now?', null);
        $gold['response']->assertOk();
        $this->assertNotSame(
            'DETERMINISTIC_CURRENT_UNVERIFIED',
            (string) data_get($gold['json'], 'meta.FINAL_RESPONSE_SOURCE')
        );

        $fx = $this->chat($vid, "What's USD to PKR right now?", $gold['conversation_id']);
        $fx['response']->assertOk();
        $this->assertNotSame(
            'DETERMINISTIC_CURRENT_UNVERIFIED',
            (string) data_get($fx['json'], 'meta.FINAL_RESPONSE_SOURCE')
        );

        $beforeGk = $provider->callCount();
        $gk = $this->chat($vid, 'What is gravity?', $fx['conversation_id']);
        $gk['response']->assertOk();
        $this->assertNotSame('', trim((string) ($gk['json']['message'] ?? '')));
        // GK may call open-domain inference.
        $this->assertGreaterThanOrEqual($beforeGk, $provider->callCount());
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function freshExplicitRoutes(): array
    {
        return [
            ['Now Islamabad to Dubai next Monday', 'ISB', 'DXB', '2026-10-05'],
            ['Lahore to Dubai next Monday', 'LHE', 'DXB', '2026-10-05'],
            ['Karachi to Jeddah tomorrow', 'KHI', 'JED', '2026-09-29'],
            ['Dubai to Lahore tomorrow', 'DXB', 'LHE', '2026-09-29'],
            ['Islamabad se Dubai kal', 'ISB', 'DXB', '2026-09-29'],
        ];
    }

    #[DataProvider('freshExplicitRoutes')]
    public function test_fresh_dated_explicit_routes_skip_qwen(
        string $message,
        string $origin,
        string $destination,
        string $depart,
    ): void {
        $this->enableSemanticAi();
        $provider = new ScriptedInferenceProvider(['SHOULD_NOT_BE_CALLED']);
        $this->rebindInference($provider);

        $vid = str_repeat('p02f', 8).substr(md5($message), 0, 4);
        $t = $this->chat($vid, $message, null);
        $t['response']->assertOk();
        $this->assertSame(0, $provider->callCount(), $message);
        $this->assertSame(0, (int) data_get($t['json'], 'meta.MODEL_CALLS', -1));
        $this->assertSame('STRUCTURED_FALLBACK', (string) data_get($t['json'], 'meta.FINAL_RESPONSE_SOURCE'));
        $classes = data_get($t['json'], 'meta.DETERMINISTIC_AUTHORITY_CLASSES');
        $this->assertIsArray($classes);
        $this->assertContains('explicit_route_complete', $classes);

        $state = $this->reloadState($t['conversation_id']);
        $this->assertSame($origin, $state['origin'] ?? null, $message);
        $this->assertSame($destination, $state['destination'] ?? null, $message);
        $this->assertSame($depart, $state['depart_date'] ?? null, $message);
        $this->assertSame('one_way', $state['trip_type'] ?? null, $message);
        $this->assertNotNull($state[FlightSearchConfirmationGate::STATE_KEY] ?? null, $message);
        $this->assertSame(0, (int) data_get($t['json'], 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $this->assertTrue(
            ($t['json']['status'] ?? null) === 'confirm'
            || (bool) data_get($t['json'], 'meta.CONFIRMATION_REQUIRED')
            || (bool) data_get($t['json'], 'requires_confirmation')
        );
    }

    public function test_explicit_route_after_active_search_resets(): void
    {
        $this->enableSemanticAi();
        $provider = new ScriptedInferenceProvider([
            $this->planJson(),
            'SHOULD_NOT_BE_CALLED',
        ]);
        $this->rebindInference($provider);

        $vid = str_repeat('p02a', 8);
        $t1 = $this->chat($vid, 'I need Dubai', null);
        $cid = $t1['conversation_id'];
        $this->chat($vid, 'from Lahore', $cid);
        $this->chat($vid, 'next Friday', $cid);
        $before = $provider->callCount();

        $t = $this->chat($vid, 'Now Islamabad to Dubai next Monday', $cid);
        $t['response']->assertOk();
        $this->assertSame($before, $provider->callCount(), 'Complete dated A→B must not call Qwen');
        $state = $this->reloadState($cid);
        $this->assertSame('ISB', $state['origin'] ?? null);
        $this->assertSame('DXB', $state['destination'] ?? null);
        $this->assertSame('2026-10-05', $state['depart_date'] ?? null);
        $this->assertSame('one_way', $state['trip_type'] ?? null);
        $this->assertNotNull($state[FlightSearchConfirmationGate::STATE_KEY] ?? null);
    }

    public function test_explicit_route_after_open_jaw_resets_to_one_way(): void
    {
        $this->enableSemanticAi();
        $provider = new ScriptedInferenceProvider([
            $this->planJson([
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
            ]),
            'SHOULD_NOT_BE_CALLED',
        ]);
        $this->rebindInference($provider);

        $vid = str_repeat('p02o', 8);
        $oj = $this->chat($vid, 'Lahore to Jeddah then Medina to Lahore', null);
        $cid = $oj['conversation_id'];
        $this->assertGreaterThanOrEqual(1, $provider->callCount());
        $before = $provider->callCount();
        $ojState = $this->reloadState($cid);
        $this->assertSame('open_jaw', $ojState['trip_type'] ?? null);

        $t = $this->chat($vid, 'Now Islamabad to Dubai next Monday', $cid);
        $t['response']->assertOk();
        $this->assertSame($before, $provider->callCount());
        $state = $this->reloadState($cid);
        $this->assertSame('ISB', $state['origin'] ?? null);
        $this->assertSame('DXB', $state['destination'] ?? null);
        $this->assertSame('2026-10-05', $state['depart_date'] ?? null);
        $this->assertSame('one_way', $state['trip_type'] ?? null);
        $legs = $state['legs'] ?? null;
        $this->assertTrue($legs === null || $legs === [] || (is_array($legs) && count($legs) < 2));
        $this->assertTrue(
            ! array_key_exists('return_date', $state) || $state['return_date'] === null
        );
    }

    public function test_controls_stay_on_qwen(): void
    {
        $this->enableSemanticAi();
        $provider = new ScriptedInferenceProvider([
            $this->planJson(),
            $this->planJson(['operation' => 'clarify', 'missing' => ['origin', 'date']]),
            $this->planJson(['operation' => 'clarify', 'missing' => ['date']]),
            $this->planJson([
                'operation' => 'clarify',
                'travel' => [
                    'trip_type' => 'open_jaw',
                    'origin' => 'LHE',
                    'destination' => 'JED',
                    'legs' => [
                        ['origin' => 'LHE', 'destination' => 'JED'],
                        ['origin' => 'MED', 'destination' => 'LHE'],
                    ],
                ],
            ]),
            $this->planJson(['operation' => 'clarify', 'missing' => ['airport']]),
            $this->planJson(['operation' => 'clarify', 'missing' => ['airport']]),
            $this->planJson(['operation' => 'prepare_search']),
        ]);
        $this->rebindInference($provider);

        $vid = str_repeat('p02c', 8);

        $cases = [
            'I need Dubai',
            'Lahore to Dubai',
            'Lahore to Jeddah then Medina to Lahore',
            'London to Dubai next Monday',
            'New York to Lahore next Monday',
        ];
        $cid = null;
        foreach ($cases as $msg) {
            $before = $provider->callCount();
            $t = $this->chat($vid, $msg, $cid);
            $t['response']->assertOk();
            $cid = $t['conversation_id'];
            $this->assertGreaterThan($before, $provider->callCount(), "Expected Qwen for: {$msg}");
            $this->assertNotContains(
                'explicit_route_complete',
                (array) data_get($t['json'], 'meta.DETERMINISTIC_AUTHORITY_CLASSES'),
                $msg
            );
        }
    }

    public function test_prior_open_jaw_bare_refinements_still_call_qwen(): void
    {
        $this->enableSemanticAi();
        $provider = new ScriptedInferenceProvider([
            $this->planJson(['operation' => 'clarify']),
            $this->planJson(['operation' => 'clarify']),
        ]);
        $this->rebindInference($provider);

        $vid = str_repeat('p02p', 8);
        $boot = $this->chat($vid, 'hello', null);
        $cid = $boot['conversation_id'];
        // Match PERF-01.1 seeding: open-jaw shopping state without pending-confirmation
        // short-circuit (pending confirm can apply date/dest without SemanticBrain).
        $openJawState = [
            'origin' => 'LHE',
            'destination' => 'JED',
            'intent' => 'flight_search',
            'trip_type' => 'open_jaw',
            'depart_date' => '2026-10-02',
            'legs' => [
                ['origin' => 'LHE', 'destination' => 'JED', 'departure_date' => '2026-10-02'],
                ['origin' => 'MED', 'destination' => 'LHE', 'departure_date' => null],
            ],
        ];

        foreach (['next Friday', 'Make it Doha'] as $msg) {
            $conv = AiConversation::query()->where('public_id', $cid)->firstOrFail();
            $conv->shopping_state = $openJawState;
            $conv->save();

            $before = $provider->callCount();
            $t = $this->chat($vid, $msg, $cid);
            $t['response']->assertOk();
            $this->assertGreaterThan($before, $provider->callCount(), "Prior multi-leg must keep Qwen: {$msg}");
        }
    }

    public function test_mixed_name_and_complete_route_closure29(): void
    {
        $this->enableSemanticAi();
        $provider = new ScriptedInferenceProvider(['SHOULD_NOT_BE_CALLED']);
        $this->rebindInference($provider);

        $vid = str_repeat('p02n', 8);
        $cid = $this->chat($vid, 'I need help')['conversation_id'];
        $before = $provider->callCount();

        $turn = $this->chat($vid, "I'm Ahmed and I need Lahore to Dubai tomorrow for 2 adults", $cid);
        $turn['response']->assertOk();
        $this->assertSame($before, $provider->callCount());

        $state = $this->reloadState($cid);
        $this->assertSame('Ahmed', $state['lead_name'] ?? null);
        $this->assertSame('LHE', $state['origin'] ?? null);
        $this->assertSame('DXB', $state['destination'] ?? null);
        $this->assertSame(2, (int) ($state['adults'] ?? 0));
        $this->assertSame('2026-09-29', $state['depart_date'] ?? null);
        $this->assertNotNull($state[FlightSearchConfirmationGate::STATE_KEY] ?? null);
        $this->assertSame(0, (int) data_get($turn['json'], 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $this->assertNotSame('Lahore to Dubai tomorrow for 2 adults', $state['lead_name'] ?? null);
    }

    public function test_explicit_return_route_not_enabled_for_fast_path(): void
    {
        $this->enableSemanticAi();
        $provider = new ScriptedInferenceProvider([
            $this->planJson(['operation' => 'clarify', 'missing' => ['return_handling']]),
        ]);
        $this->rebindInference($provider);

        $vid = str_repeat('p02r', 8);
        $t = $this->chat($vid, 'Lahore to Dubai tomorrow return Sunday', null);
        $t['response']->assertOk();
        // Must not claim explicit_route_complete (return fast path not enabled).
        $this->assertNotContains(
            'explicit_route_complete',
            (array) data_get($t['json'], 'meta.DETERMINISTIC_AUTHORITY_CLASSES')
        );
    }

    public function test_via_route_keeps_qwen_english_and_iata(): void
    {
        $this->enableSemanticAi();
        $provider = new ScriptedInferenceProvider([
            $this->planJson(['operation' => 'clarify', 'missing' => ['via_leg']]),
            $this->planJson(['operation' => 'clarify', 'missing' => ['via_leg']]),
            $this->planJson(['operation' => 'clarify', 'missing' => ['via_leg']]),
        ]);
        $this->rebindInference($provider);

        $vid = str_repeat('p021', 8);
        $cid = null;
        foreach ([
            'Lahore to Dubai via Doha next Monday',
            'LHE to DXB via DOH next Monday',
            'Lahore to Dubai through Doha next Monday',
        ] as $msg) {
            $before = $provider->callCount();
            $t = $this->chat($vid, $msg, $cid);
            $t['response']->assertOk();
            $cid = $t['conversation_id'];
            $this->assertGreaterThan($before, $provider->callCount(), "Via/through must keep Qwen: {$msg}");
            $this->assertNotContains(
                'explicit_route_complete',
                (array) data_get($t['json'], 'meta.DETERMINISTIC_AUTHORITY_CLASSES'),
                $msg
            );
        }
    }

    public function test_airline_constrained_explicit_route_still_bypasses(): void
    {
        $this->enableSemanticAi();
        $provider = new ScriptedInferenceProvider(['SHOULD_NOT_BE_CALLED']);
        $this->rebindInference($provider);

        $vid = str_repeat('p02k', 8);
        $t = $this->chat($vid, 'Lahore to Dubai on Emirates next Monday', null);
        $t['response']->assertOk();
        $this->assertSame(0, $provider->callCount());
        $this->assertContains(
            'explicit_route_complete',
            (array) data_get($t['json'], 'meta.DETERMINISTIC_AUTHORITY_CLASSES')
        );
        $state = $this->reloadState($t['conversation_id']);
        $this->assertSame('LHE', $state['origin'] ?? null);
        $this->assertSame('DXB', $state['destination'] ?? null);
        $this->assertSame('2026-10-05', $state['depart_date'] ?? null);
        $airline = $state['airline'] ?? data_get($t['json'], 'confirmation_snapshot.airline');
        $this->assertNotNull($airline, 'Emirates constraint should be captured by Hybrid');
    }

    public function test_direct_explicit_route_preserves_max_stops(): void
    {
        $this->enableSemanticAi();
        $provider = new ScriptedInferenceProvider(['SHOULD_NOT_BE_CALLED']);
        $this->rebindInference($provider);

        $vid = str_repeat('p02d', 8);
        $t = $this->chat($vid, 'Lahore to Dubai direct next Monday', null);
        $t['response']->assertOk();
        $this->assertSame(0, $provider->callCount());
        $this->assertContains(
            'explicit_route_complete',
            (array) data_get($t['json'], 'meta.DETERMINISTIC_AUTHORITY_CLASSES')
        );
        $state = $this->reloadState($t['conversation_id']);
        $this->assertSame('LHE', $state['origin'] ?? null);
        $this->assertSame('DXB', $state['destination'] ?? null);
        $this->assertSame('2026-10-05', $state['depart_date'] ?? null);
        $this->assertSame(0, (int) ($state['max_stops'] ?? -1), 'direct constraint must not be silently lost');
    }
}
