<?php

namespace Tests\Feature\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Data\Ai\TravelIntent;
use App\Models\AiConversation;
use App\Services\Ai\FlightSearchConfirmationGate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Ai\ScriptedInferenceProvider;
use Tests\TestCase;

/**
 * JP-AI-CQ43-R2 — widget identity, lead precedence during pending confirm, clarification order.
 */
class Cq43R2ClientLeadClosureTest extends TestCase
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

    private function reloadState(string $conversationId): array
    {
        $conv = AiConversation::query()->where('public_id', $conversationId)->firstOrFail();

        return is_array($conv->shopping_state) ? $conv->shopping_state : [];
    }

    private function seedPendingConfirmWithLeadName(string $vid): AiConversation
    {
        $gate = app(FlightSearchConfirmationGate::class);
        $intent = TravelIntent::fromArray([
            'intent' => 'flight_search',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-29',
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'economy',
            'trip_type' => 'one_way',
        ], 'STRUCTURED_FALLBACK');

        $conv = AiConversation::query()->create([
            'public_id' => (string) Str::uuid(),
            'visitor_token_hash' => hash('sha256', $vid),
            'channel' => 'web',
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => [
                'intent' => 'flight_search',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-09-29',
                'adults' => 2,
                'cabin' => 'economy',
                'trip_type' => 'one_way',
                'lead_capture_pending' => true,
                'lead_capture_stage' => 'name',
                'lead_capture_fields' => ['name', 'email', 'phone', 'contact_consent'],
            ],
        ]);
        $gate->storePending($conv, $gate->buildSnapshot($intent));

        return $conv->fresh();
    }

    public function test_bare_name_lead_during_pending_confirmation(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson(['operation' => 'clarify']),
            $this->planJson(['operation' => 'clarify']),
        ]));

        $vid = str_repeat('cq43r2a', 6);
        // Turn 1 — full travel + lead pending (mirrors production soak).
        $t1 = $this->chat($vid, 'Lahore to Dubai tomorrow for 2 adults economy');
        $t1['response']->assertOk();
        $cid = $t1['conversation_id'];
        $s1 = $this->reloadState($cid);
        $this->assertTrue((bool) ($s1['lead_capture_pending'] ?? false));
        $this->assertSame('name', $s1['lead_capture_stage'] ?? null);
        $this->assertNotNull($s1[FlightSearchConfirmationGate::STATE_KEY] ?? null);
        $pendingBefore = $s1[FlightSearchConfirmationGate::STATE_KEY];

        // Turn 2 — bare name must advance lead, preserve pending confirm, no search.
        $t2 = $this->chat($vid, 'Ali Khan', $cid);
        $t2['response']->assertOk();
        $this->assertSame(0, (int) data_get($t2['response']->json(), 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $s2 = $this->reloadState($cid);
        $this->assertSame('Ali Khan', $s2['lead_name'] ?? null);
        $this->assertNotSame('name', $s2['lead_capture_stage'] ?? 'name');
        $pendingAfter = $s2[FlightSearchConfirmationGate::STATE_KEY] ?? null;
        $this->assertIsArray($pendingAfter);
        $this->assertSame('LHE', $pendingAfter['origin'] ?? null);
        $this->assertSame('DXB', $pendingAfter['destination'] ?? null);
        $this->assertNotEmpty($pendingAfter['departure_date'] ?? null);
        $this->assertSame(2, (int) ($pendingAfter['adults'] ?? 0));
        $this->assertSame($pendingBefore, $pendingAfter);
    }

    public function test_contact_lead_during_pending_confirmation(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson(['operation' => 'clarify']),
        ]));

        $vid = str_repeat('cq43r2b', 6);
        $conv = $this->seedPendingConfirmWithLeadName($vid);
        $state = is_array($conv->shopping_state) ? $conv->shopping_state : [];
        $state['lead_name'] = 'Ali Khan';
        $state['lead_capture_stage'] = 'contact';
        $conv->shopping_state = $state;
        $conv->save();
        $pendingBefore = $state[FlightSearchConfirmationGate::STATE_KEY] ?? null;

        $turn = $this->chat($vid, 'ali@example.com Phone 03001234567', $conv->public_id);
        $turn['response']->assertOk();
        $this->assertSame(0, (int) data_get($turn['response']->json(), 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $after = $this->reloadState($conv->public_id);
        $this->assertSame('ali@example.com', $after['lead_email'] ?? null);
        $this->assertNotEmpty($after['lead_phone'] ?? null);
        $this->assertSame($pendingBefore, $after[FlightSearchConfirmationGate::STATE_KEY] ?? null);
        $this->assertSame('LHE', data_get($after, FlightSearchConfirmationGate::STATE_KEY.'.origin'));
        $this->assertSame('DXB', data_get($after, FlightSearchConfirmationGate::STATE_KEY.'.destination'));
    }

    public function test_travel_refinement_overrides_lead_fsm_while_pending(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider(array_fill(
            0,
            8,
            $this->planJson(['operation' => 'clarify'])
        )));

        $vid = str_repeat('cq43r2c', 6);
        $conv = $this->seedPendingConfirmWithLeadName($vid);
        $cid = $conv->public_id;

        foreach (['Make it Doha', '3 adults', 'business class', 'next Monday'] as $phrase) {
            $turn = $this->chat($vid, $phrase, $cid);
            $turn['response']->assertOk();
            $state = $this->reloadState($cid);
            $this->assertNotSame($phrase, $state['lead_name'] ?? null, $phrase);
            $this->assertNull($state['lead_name'] ?? null, 'LEAD_TRAVEL_TEXT_CAPTURED_AS_NAME for '.$phrase);
            $this->assertSame(0, (int) data_get($turn['response']->json(), 'meta.AI_FLIGHT_SEARCH_READ_CALLS'), $phrase);
        }
    }

    public function test_bare_yes_confirmation_authority_with_lead_pending(): void
    {
        $this->enableSemanticAi([
            'ota.ai_assistant.semantic_planner_enabled' => false,
        ]);
        $this->rebindInference(new ScriptedInferenceProvider([]));

        $vid = str_repeat('cq43r2d', 6);
        $conv = $this->seedPendingConfirmWithLeadName($vid);

        $yes = $this->chat($vid, 'yes', $conv->public_id);
        $yes['response']->assertOk();
        $this->assertTrue((bool) data_get($yes['response']->json(), 'meta.CONFIRMATION_BEFORE_SEARCH'));
        $this->assertSame(1, (int) data_get($yes['response']->json(), 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $this->assertNull(data_get($this->reloadState($conv->public_id), FlightSearchConfirmationGate::STATE_KEY));
        // Bare yes must not be captured as lead name.
        $this->assertNull(data_get($this->reloadState($conv->public_id), 'lead_name'));
    }

    public function test_destination_led_prompt_asks_origin_first(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'operation' => 'clarify',
                'travel' => [
                    'origin' => null,
                    'destination' => 'DXB',
                    'legs' => [['origin' => null, 'destination' => 'DXB', 'departure_date' => null]],
                    'adults' => 1,
                ],
                // Advisory missing puts date first — server must ignore order.
                'missing' => ['departure_date', 'origin'],
                'response_intent' => 'need_origin',
            ]),
        ]));

        $vid = str_repeat('cq43r2e', 6);
        $turn = $this->chat($vid, 'I need Dubai');
        $turn['response']->assertOk();
        $this->assertSame('clarify', $turn['response']->json('status'));
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertStringContainsString('travelling from', $body);
        $this->assertStringContainsString('dxb', $body);
        $this->assertStringNotContainsString('departure date', $body);
        $state = $this->reloadState($turn['conversation_id']);
        $this->assertNull($state['origin'] ?? null);
        $this->assertSame('DXB', $state['destination'] ?? null);
    }

    public function test_origin_only_prompt_asks_destination(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'operation' => 'clarify',
                'travel' => [
                    'origin' => 'LHE',
                    'destination' => null,
                    'legs' => [['origin' => 'LHE', 'destination' => null, 'departure_date' => null]],
                ],
                'missing' => ['destination', 'departure_date'],
                'response_intent' => 'need_destination',
            ]),
        ]));

        $vid = str_repeat('cq43r2f', 6);
        $conv = AiConversation::query()->create([
            'public_id' => (string) Str::uuid(),
            'visitor_token_hash' => hash('sha256', $vid),
            'channel' => 'web',
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => [
                'intent' => 'flight_search',
                'origin' => 'LHE',
            ],
        ]);

        $turn = $this->chat($vid, 'from Lahore', $conv->public_id);
        $turn['response']->assertOk();
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertTrue(
            str_contains($body, 'where would you like to go') || str_contains($body, 'destination'),
            $body
        );
        $this->assertStringNotContainsString('departure date', $body);
    }

    public function test_route_complete_asks_departure_date(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'operation' => 'clarify',
                'travel' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'legs' => [['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => null]],
                ],
                'missing' => ['departure_date'],
                'response_intent' => 'need_dates',
            ]),
        ]));

        $vid = str_repeat('cq43r2g', 6);
        $turn = $this->chat($vid, 'Lahore to Dubai');
        $turn['response']->assertOk();
        $state = $this->reloadState($turn['conversation_id']);
        $this->assertSame('LHE', $state['origin'] ?? null);
        $this->assertSame('DXB', $state['destination'] ?? null);
        $this->assertNull($state['depart_date'] ?? null);
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertStringContainsString('departure date', $body);
        $this->assertStringContainsString('lhe', $body);
        $this->assertStringContainsString('dxb', $body);
    }

    public function test_message_id_contract_chat_handoff_resume(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson(['operation' => 'clarify']),
        ]));

        $vid = str_repeat('cq43r2h', 6);
        $chat = $this->chat($vid, 'Hello');
        $chat['response']->assertOk();
        $this->assertIsInt($chat['response']->json('message_id'));
        $this->assertGreaterThan(0, (int) $chat['response']->json('message_id'));
        $cid = $chat['conversation_id'];

        $handoff = $this->withCookie('jp_ai_vid', $vid)
            ->postJson('/api/public/ai/handoff', ['conversation_id' => $cid]);
        $handoff->assertOk();
        $this->assertIsInt($handoff->json('message_id'));
        $this->assertGreaterThan(0, (int) $handoff->json('message_id'));

        $resume = $this->withCookie('jp_ai_vid', $vid)
            ->postJson('/api/public/ai/resume', ['conversation_id' => $cid]);
        $resume->assertOk();
        $this->assertIsInt($resume->json('message_id'));
        $this->assertGreaterThan(0, (int) $resume->json('message_id'));
    }
}
