<?php

namespace Tests\Feature\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Models\AiConversation;
use App\Services\Ai\Hybrid\LocationResolver;
use App\Services\Ai\Hybrid\PassengerExpressionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Ai\ScriptedInferenceProvider;
use Tests\TestCase;

/**
 * JP-AI-CQ41-LIVE-UAT-RESIDUAL-CLOSURE
 *
 * Closes Live Canary 40 residuals: relational pax, explicit open-jaw legs,
 * general knowledge, booking lookup routing, and Order-39 non-regression.
 */
class QwenLiveUatResidualClosureCq41Test extends TestCase
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

    private function planJson(array $overrides = []): string
    {
        $depart = now()->next('Friday')->format('Y-m-d');
        $base = [
            'domain' => 'travel',
            'intent' => 'flight_search',
            'operation' => 'prepare_search',
            'travel' => [
                'trip_type' => 'one_way',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'legs' => [['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => $depart]],
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
            'corrections' => new \stdClass,
            'missing' => [],
            'response_intent' => 'confirm_search',
        ];

        return (string) json_encode(array_replace_recursive($base, $overrides), JSON_UNESCAPED_UNICODE);
    }

    public function test_relational_passenger_me_and_my_wife_adults_two(): void
    {
        $resolver = app(PassengerExpressionResolver::class);
        $r = $resolver->resolve(
            'i need to go dubai next friday from lahore, me and my wife',
            'I need to go Dubai next Friday from Lahore, me and my wife'
        );
        $this->assertSame(2, $r['adults']);
        $this->assertSame('RELATIONAL_PAIR', $r['provenance']['adults'] ?? null);

        $variants = [
            'my wife and I',
            'me and my husband',
            'my husband and I',
            'me and my partner',
            'my partner and I',
            'myself and my wife',
            'myself and my husband',
            'myself and my partner',
            'both of us',
            'us two',
        ];
        foreach ($variants as $phrase) {
            $got = $resolver->resolve(mb_strtolower($phrase), $phrase);
            $this->assertSame(2, $got['adults'], 'Failed for: '.$phrase);
        }
    }

    public function test_explicit_adult_count_outranks_relational_pair(): void
    {
        $resolver = app(PassengerExpressionResolver::class);
        $r = $resolver->resolve(
            'me and my wife, 3 adults total',
            'me and my wife, 3 adults total'
        );
        $this->assertSame(3, $r['adults']);
        $this->assertSame('EXPLICIT_USER', $r['provenance']['adults'] ?? null);

        $mixed = $resolver->resolve(
            'me, my wife and 2 children',
            'me, my wife and 2 children'
        );
        $this->assertSame(2, $mixed['adults']);
        $this->assertSame(2, $mixed['children']);
    }

    public function test_live_wife_prompt_confirms_two_adults_no_search(): void
    {
        $this->enableSemanticAi();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider($this->planJson([
            'travel' => [
                'origin' => 'LHE',
                'destination' => 'DXB',
                'adults' => 1,
            ],
        ])));

        $msg = 'I need to go Dubai next Friday from Lahore, me and my wife';
        $turn = $this->chat(str_repeat('w1', 20), $msg);
        $turn['response']->assertOk()->assertStatus(200);
        $this->assertSame('confirm', $turn['response']->json('status'));
        $this->assertSame(2, (int) $turn['response']->json('confirmation_snapshot.adults'));
        $this->assertSame(0, (int) $turn['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $this->assertDoesNotMatchRegularExpression('/\b(email|phone|whatsapp|cnic)\b/i', (string) $turn['response']->json('message'));

        $cid = $turn['conversation_id'];
        $conv = AiConversation::query()->where('public_id', $cid)->first();
        $this->assertNotNull($conv);
        $this->assertSame(2, (int) data_get($conv->shopping_state, 'adults'));
    }

    public function test_explicit_open_jaw_legs_not_rewritten(): void
    {
        $locations = app(LocationResolver::class);
        $phrases = [
            'Lahore to Jeddah then Medina to Lahore' => [['LHE', 'JED'], ['MED', 'LHE']],
            'Lahore to Dubai then Abu Dhabi to Lahore' => [['LHE', 'DXB'], ['AUH', 'LHE']],
            'Karachi to Istanbul then Ankara to Karachi' => [['KHI', 'IST'], ['ESB', 'KHI']],
            'LHE to JED then MED to LHE' => [['LHE', 'JED'], ['MED', 'LHE']],
        ];
        foreach ($phrases as $phrase => $expected) {
            $legs = $locations->extractOpenJawLegs(mb_strtolower($phrase), $phrase);
            $this->assertNotNull($legs, 'Failed extract: '.$phrase);
            $this->assertCount(2, $legs);
            $this->assertSame($expected[0][0], $legs[0]['origin']);
            $this->assertSame($expected[0][1], $legs[0]['destination']);
            $this->assertSame($expected[1][0], $legs[1]['origin']);
            $this->assertSame($expected[1][1], $legs[1]['destination']);
        }
    }

    public function test_semantic_contradictory_open_jaw_legs_canonicalized(): void
    {
        $this->enableSemanticAi();
        // Qwen wrongly invents JED→MED second leg; server must keep MED→LHE.
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider($this->planJson([
            'operation' => 'clarify',
            'travel' => [
                'trip_type' => 'open_jaw',
                'origin' => 'LHE',
                'destination' => 'JED',
                'adults' => 1,
                'legs' => [
                    ['origin' => 'LHE', 'destination' => 'JED', 'departure_date' => null],
                    ['origin' => 'JED', 'destination' => 'MED', 'departure_date' => null],
                ],
            ],
            'missing' => ['leg1_departure_date', 'leg2_departure_date'],
        ])));

        $turn = $this->chat(str_repeat('oj1', 20), 'Lahore to Jeddah then Medina to Lahore');
        $turn['response']->assertOk();
        $this->assertSame('YES', $turn['response']->json('meta.OPEN_JAW_DETECTED'));
        $this->assertSame('LHE-JED', $turn['response']->json('meta.LEG1'));
        $this->assertSame('MED-LHE', $turn['response']->json('meta.LEG2'));
        $this->assertSame('NO', $turn['response']->json('meta.FALSE_ONE_WAY'));
        $this->assertSame(0, (int) ($turn['response']->json('meta.WRONG_ROUTE_ACTION_READY') ?? 0));
        $this->assertNotSame('confirm', $turn['response']->json('status'));
        $this->assertSame(0, (int) $turn['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $body = (string) $turn['response']->json('message');
        $this->assertStringContainsString('LHE-JED', $body);
        $this->assertStringContainsString('MED-LHE', $body);
        $this->assertStringNotContainsString('JED-MED', $body);
    }

    public function test_stale_one_way_state_yields_to_explicit_open_jaw(): void
    {
        $this->enableSemanticAi();
        $vid = str_repeat('oj2', 20);

        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider($this->planJson([
            'travel' => [
                'origin' => 'LHE',
                'destination' => 'DXB',
                'adults' => 1,
                'legs' => [['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => now()->addDays(3)->format('Y-m-d')]],
            ],
        ])));
        $first = $this->chat($vid, 'Lahore to Dubai tomorrow for 1 adult');
        $first['response']->assertOk();
        $cid = $first['conversation_id'];

        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider($this->planJson([
            'operation' => 'prepare_search',
            'travel' => [
                'trip_type' => 'one_way',
                'origin' => 'LHE',
                'destination' => 'JED',
                'adults' => 1,
                'legs' => [
                    ['origin' => 'LHE', 'destination' => 'JED', 'departure_date' => now()->addWeek()->format('Y-m-d')],
                ],
            ],
        ])));

        $second = $this->chat($vid, 'Lahore to Jeddah then Medina to Lahore', $cid);
        $second['response']->assertOk();
        $this->assertSame('YES', $second['response']->json('meta.OPEN_JAW_DETECTED'));
        $this->assertSame('LHE-JED', $second['response']->json('meta.LEG1'));
        $this->assertSame('MED-LHE', $second['response']->json('meta.LEG2'));
        $this->assertNotSame('confirm', $second['response']->json('status'));
        $this->assertSame(0, (int) $second['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS'));
    }

    public function test_general_knowledge_photosynthesis(): void
    {
        $this->enableSemanticAi();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider($this->planJson([
            'domain' => 'knowledge',
            'intent' => 'faq',
            'operation' => 'answer',
            'knowledge_query' => 'photosynthesis',
            'travel' => ['trip_type' => null, 'origin' => null, 'destination' => null, 'legs' => [], 'adults' => 1],
            'response_intent' => 'answer',
        ])));

        $turn = $this->chat(str_repeat('gk1', 20), 'What is photosynthesis?');
        $turn['response']->assertOk();
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertStringContainsString('photosynthesis', $body);
        $this->assertStringNotContainsString('could not find an approved jetpakistan answer', $body);
        $this->assertSame(0, (int) $turn['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS'));
    }

    public function test_current_weather_remains_source_limited(): void
    {
        $this->enableSemanticAi();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider($this->planJson([
            'domain' => 'knowledge',
            'intent' => 'weather',
            'operation' => 'answer',
            'travel' => ['trip_type' => null, 'origin' => null, 'destination' => null, 'legs' => []],
        ])));

        $turn = $this->chat(str_repeat('cf1', 20), 'What is the weather in Dubai right now?');
        $turn['response']->assertOk();
        $this->assertSame('CURRENT_UNVERIFIED', $turn['response']->json('meta.open_domain_category'));
        $this->assertDoesNotMatchRegularExpression('/\d{1,3}\s*°/', (string) $turn['response']->json('message'));
        $this->assertSame('NO', $turn['response']->json('meta.HALLUCINATED_LIVE_FACT'));
    }

    public function test_booking_lookup_not_generic_handoff(): void
    {
        $this->enableSemanticAi();
        // Misclassified as support/handoff — server must still route booking verification.
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider($this->planJson([
            'domain' => 'support',
            'intent' => 'lookup',
            'operation' => 'handoff',
            'travel' => ['trip_type' => null, 'origin' => null, 'destination' => null, 'legs' => []],
            'response_intent' => 'handoff',
        ])));

        $variants = [
            'Check my booking please',
            'I want to see my booking',
            'Can you look up my booking?',
            'Booking status',
            'Where is my booking?',
            'I need to retrieve my reservation',
        ];

        foreach ($variants as $i => $msg) {
            $turn = $this->chat(str_repeat('bk', 18).sprintf('%02d', $i), $msg);
            $turn['response']->assertOk();
            $this->assertNotSame('WAITING_FOR_HUMAN', $turn['response']->json('state'), $msg);
            $body = mb_strtolower((string) $turn['response']->json('message'));
            $this->assertTrue(
                str_contains($body, 'booking reference')
                    || str_contains($body, 'email')
                    || str_contains($body, 'phone'),
                'Expected verification prompt for: '.$msg
            );
            $this->assertNull($turn['response']->json('booking'));
            $this->assertSame(0, (int) ($turn['response']->json('meta.BOOKING_IDENTITY_BYPASS') ?? 0));
            $this->assertSame(0, (int) ($turn['response']->json('meta.BOOKING_DATA_LEAK') ?? 0));
        }
    }

    public function test_talk_to_support_still_handoffs(): void
    {
        $this->enableSemanticAi();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider($this->planJson([
            'domain' => 'support',
            'intent' => 'human',
            'operation' => 'handoff',
            'travel' => ['trip_type' => null, 'origin' => null, 'destination' => null, 'legs' => []],
        ])));

        $turn = $this->chat(str_repeat('ho1', 20), 'Talk to support');
        $turn['response']->assertOk();
        $this->assertSame('WAITING_FOR_HUMAN', $turn['response']->json('state'));
    }

    public function test_cross_precedence_wife_doha_then_three_adults(): void
    {
        $this->enableSemanticAi();
        $vid = str_repeat('xp1', 20);

        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider($this->planJson([
            'travel' => ['origin' => 'LHE', 'destination' => 'DXB', 'adults' => 1],
        ])));
        $a = $this->chat($vid, 'I need to go Dubai next Friday from Lahore, me and my wife');
        $a['response']->assertOk();
        $this->assertSame('confirm', $a['response']->json('status'));
        $this->assertSame(2, (int) $a['response']->json('confirmation_snapshot.adults'));
        $this->assertSame('DXB', $a['response']->json('confirmation_snapshot.destination'));
        $this->assertSame(0, (int) $a['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $cid = $a['conversation_id'];

        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider($this->planJson([
            'travel' => [
                'origin' => 'LHE',
                'destination' => 'DOH',
                'adults' => 2,
                'legs' => [['origin' => 'LHE', 'destination' => 'DOH', 'departure_date' => now()->next('Friday')->format('Y-m-d')]],
            ],
        ])));
        $b = $this->chat($vid, 'Actually make that Doha instead', $cid);
        $b['response']->assertOk();
        $this->assertSame('confirm', $b['response']->json('status'));
        $this->assertSame('DOH', $b['response']->json('confirmation_snapshot.destination'));
        $this->assertSame(2, (int) $b['response']->json('confirmation_snapshot.adults'));
        $this->assertSame(0, (int) $b['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS'));

        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider($this->planJson([
            'travel' => [
                'origin' => 'LHE',
                'destination' => 'DOH',
                'adults' => 2,
                'legs' => [['origin' => 'LHE', 'destination' => 'DOH', 'departure_date' => now()->next('Friday')->format('Y-m-d')]],
            ],
        ])));
        $c = $this->chat($vid, 'Make it 3 adults instead', $cid);
        $c['response']->assertOk();
        $this->assertSame('confirm', $c['response']->json('status'));
        $this->assertSame('DOH', $c['response']->json('confirmation_snapshot.destination'));
        $this->assertSame(3, (int) $c['response']->json('confirmation_snapshot.adults'));
        $this->assertSame(0, (int) $c['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS'));
    }

    public function test_order39_valid_qwen_confirms(): void
    {
        $this->enableSemanticAi();
        $primary = 'Lahore to Dubai tomorrow for 2 adults';
        $depart = now()->addDay()->format('Y-m-d');

        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider($this->planJson([
            'travel' => [
                'origin' => 'LHE',
                'destination' => 'DXB',
                'adults' => 2,
                'legs' => [['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => $depart]],
            ],
        ])));
        $ok = $this->chat(str_repeat('o39a', 19), $primary);
        $ok['response']->assertOk();
        $this->assertSame('QWEN_SEMANTIC', $ok['response']->json('mode'));
        $this->assertSame('confirm', $ok['response']->json('status'));
        $this->assertSame(0, (int) $ok['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $this->assertDoesNotMatchRegularExpression('/\b(email|phone|whatsapp)\b/i', (string) $ok['response']->json('message'));
    }

    public function test_order39_forced_fallback_skips_legacy_llm(): void
    {
        $this->enableSemanticAi();
        $primary = 'Lahore to Dubai tomorrow for 2 adults';

        // Call 1: semantic planner invalid JSON. Extra queue slots are PII bait if legacy LLM runs.
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider([
            'NOT_JSON{{{',
            json_encode(['message' => 'May I start with your name, email, and phone?']),
        ]));
        $fb = $this->chat(str_repeat('o39b', 19), $primary);
        $fb['response']->assertOk();
        $this->assertSame('STRUCTURED_FALLBACK', $fb['response']->json('mode'));
        $this->assertSame('confirm', $fb['response']->json('status'));
        $this->assertSame(0, (int) $fb['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $this->assertNotSame('LLM_ASSISTED', $fb['response']->json('mode'));
        $this->assertSame(0, (int) ($fb['response']->json('meta.LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK') ?? 0));
        $this->assertDoesNotMatchRegularExpression('/\b(email|phone|whatsapp)\b/i', (string) $fb['response']->json('message'));
    }
}
