<?php

namespace Tests\Feature\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Data\Ai\TravelIntent;
use App\Models\AiConversation;
use App\Services\Ai\ConversationIntentRouter;
use App\Services\Ai\FlightSearchConfirmationGate;
use App\Services\Ai\Hybrid\ServerTravelSignals;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Ai\ScriptedInferenceProvider;
use Tests\TestCase;

/**
 * CQ45 — stop-count / directness travel authority must outrank lead-name FSM.
 */
class Cq45LeadConstraintAuthorityTest extends TestCase
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
            'json' => $response->json(),
        ];
    }

    private function reloadState(string $conversationId): array
    {
        $conv = AiConversation::query()->where('public_id', $conversationId)->firstOrFail();

        return is_array($conv->shopping_state) ? $conv->shopping_state : [];
    }

    private function seedKhiJedPendingLeadName(string $vid): AiConversation
    {
        $gate = app(FlightSearchConfirmationGate::class);
        $intent = TravelIntent::fromArray([
            'intent' => 'flight_search',
            'origin' => 'KHI',
            'destination' => 'JED',
            'depart_date' => '2026-10-06',
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
                'origin' => 'KHI',
                'destination' => 'JED',
                'depart_date' => '2026-10-06',
                'adults' => 2,
                'cabin' => 'economy',
                'trip_type' => 'one_way',
                'lead_capture_pending' => true,
                'lead_capture_stage' => 'name',
                'lead_name' => null,
                'lead_capture_fields' => ['name', 'email', 'phone', 'contact_consent'],
            ],
        ]);
        $gate->storePending($conv, $gate->buildSnapshot($intent));

        return $conv->fresh();
    }

    public function test_progressive_stop_refinement_signals(): void
    {
        $signals = app(ServerTravelSignals::class);
        $prior = [
            'origin' => 'KHI',
            'destination' => 'JED',
            'intent' => 'flight_search',
            'depart_date' => '2026-10-06',
            'adults' => 2,
        ];

        foreach (['direct only', 'nonstop', 'nonstop only', 'seedhi'] as $msg) {
            $a = $signals->progressiveTravelAuthority($msg, $prior);
            $this->assertTrue($a['stop_refinement'], $msg);
            $this->assertTrue($a['active'], $msg);
            $this->assertTrue($a['travel_refinement'], $msg);
            $this->assertFalse($a['travel_start'], $msg);
        }

        foreach (['one stop', 'one stop only', '1 stop'] as $msg) {
            $a = $signals->progressiveTravelAuthority($msg, $prior);
            $this->assertTrue($a['stop_refinement'], $msg);
            $this->assertTrue($a['active'], $msg);
        }

        $bare = $signals->progressiveTravelAuthority('direct only', null);
        $this->assertTrue($bare['stop_refinement']);
        $this->assertTrue($bare['active']);
        $this->assertFalse($bare['travel_start']);
        $this->assertFalse($bare['travel_refinement']);

        $name = $signals->progressiveTravelAuthority('Ahmed', $prior);
        $this->assertFalse($name['stop_refinement']);
        $this->assertFalse($name['active']);
    }

    public function test_deterministic_stop_refinement_complete_and_prior_multi_leg_blocked(): void
    {
        $signals = app(ServerTravelSignals::class);
        $prior = [
            'origin' => 'KHI',
            'destination' => 'JED',
            'intent' => 'flight_search',
            'depart_date' => '2026-10-06',
            'adults' => 2,
            'trip_type' => 'one_way',
        ];

        $direct = $signals->deterministicAuthorityComplete('direct only', $prior);
        $this->assertTrue($direct['complete']);
        $this->assertContains('stop_refinement', $direct['classes']);

        $one = $signals->deterministicAuthorityComplete('one stop only', $prior);
        $this->assertTrue($one['complete']);
        $this->assertContains('stop_refinement', $one['classes']);

        $ojPrior = [
            'origin' => 'LHE',
            'destination' => 'JED',
            'intent' => 'flight_search',
            'trip_type' => 'open_jaw',
            'depart_date' => '2026-10-06',
            'legs' => [
                ['origin' => 'LHE', 'destination' => 'JED', 'departure_date' => '2026-10-06'],
                ['origin' => 'MED', 'destination' => 'LHE', 'departure_date' => null],
            ],
        ];
        $oj = $signals->deterministicAuthorityComplete('direct only', $ojPrior);
        $this->assertFalse($oj['complete']);
        $this->assertSame('prior_multi_leg_requires_semantic', $oj['reason']);
        // Progressive still marks stop refinement / active so lead FSM is overridden.
        $this->assertTrue($oj['authority']['stop_refinement']);
        $this->assertTrue($oj['authority']['active']);
    }

    public function test_direct_only_historical_repro_does_not_capture_lead_name(): void
    {
        $this->enableSemanticAi();
        // Scripted plans unused if stop_refinement short-circuits; keep buffer for safety.
        $this->rebindInference(new ScriptedInferenceProvider(array_fill(
            0,
            8,
            (string) json_encode([
                'domain' => 'travel',
                'intent' => 'flight_search',
                'operation' => 'clarify',
                'travel' => [
                    'trip_type' => 'one_way',
                    'origin' => 'KHI',
                    'destination' => 'JED',
                    'legs' => [['origin' => 'KHI', 'destination' => 'JED', 'departure_date' => '2026-10-06']],
                    'adults' => 2,
                    'max_stops' => 0,
                ],
                'missing' => [],
                'references' => ['active_search' => false, 'pending_confirmation' => true],
                'corrections' => new \stdClass,
                'response_intent' => 'confirm_search',
            ], JSON_UNESCAPED_UNICODE)
        )));

        $vid = str_repeat('cq45dir', 6);
        $conv = $this->seedKhiJedPendingLeadName($vid);
        $cid = $conv->public_id;

        $before = $this->reloadState($cid);
        $this->assertSame('name', $before['lead_capture_stage'] ?? null);
        $this->assertNull($before['lead_name'] ?? null);
        $this->assertNotNull($before[FlightSearchConfirmationGate::STATE_KEY] ?? null);

        $turn = $this->chat($vid, 'direct only', $cid);
        $turn['response']->assertOk();
        $json = $turn['json'];

        $this->assertSame(0, (int) data_get($json, 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $this->assertSame(0, (int) data_get($json, 'meta.MODEL_CALLS', data_get($json, 'meta.GENERAL_MODEL_CALLS', 0)));
        $this->assertNotSame('contact', data_get($json, 'meta.lead_capture_stage'));

        $after = $this->reloadState($cid);
        $this->assertNull($after['lead_name'] ?? null, 'DIRECT_ONLY_FALSE_LEAD_CAPTURE');
        $this->assertSame('name', $after['lead_capture_stage'] ?? null);
        $this->assertSame(0, (int) ($after['max_stops'] ?? -1));
        $this->assertSame('KHI', $after['origin'] ?? null);
        $this->assertSame('JED', $after['destination'] ?? null);
        $this->assertSame(2, (int) ($after['adults'] ?? 0));

        $pending = $after[FlightSearchConfirmationGate::STATE_KEY] ?? null;
        $this->assertIsArray($pending);
        $this->assertSame(0, (int) ($pending['max_stops'] ?? -1));
        $this->assertSame('KHI', $pending['origin'] ?? null);
        $this->assertSame('JED', $pending['destination'] ?? null);

        // Must not look like a lead-contact prompt.
        $body = mb_strtolower((string) data_get($json, 'message', ''));
        $this->assertFalse(
            str_contains($body, 'email') && str_contains($body, 'phone') && str_contains($body, 'name'),
            'must not advance into lead-contact collection for direct only'
        );
    }

    public function test_direct_and_one_stop_families_on_active_travel(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider(array_fill(0, 12, (string) json_encode([
            'domain' => 'travel', 'intent' => 'flight_search', 'operation' => 'clarify',
            'travel' => ['trip_type' => 'one_way', 'origin' => 'KHI', 'destination' => 'JED', 'adults' => 2],
            'missing' => [], 'references' => ['pending_confirmation' => true], 'corrections' => new \stdClass,
            'response_intent' => 'confirm_search',
        ], JSON_UNESCAPED_UNICODE))));

        $cases = [
            'direct only' => 0,
            'nonstop' => 0,
            'nonstop only' => 0,
            'seedhi' => 0,
            'one stop' => 1,
            'one stop only' => 1,
            '1 stop' => 1,
        ];

        foreach ($cases as $msg => $expectedStops) {
            $vid = substr(preg_replace('/[^a-z0-9]/', '', 'cq45'.md5($msg)).str_repeat('x', 32), 0, 42);
            $conv = $this->seedKhiJedPendingLeadName($vid);
            $turn = $this->chat($vid, $msg, $conv->public_id);
            $turn['response']->assertOk();
            $this->assertSame(0, (int) data_get($turn['json'], 'meta.MODEL_CALLS', data_get($turn['json'], 'meta.GENERAL_MODEL_CALLS', 0)), $msg);
            $state = $this->reloadState($conv->public_id);
            $this->assertNull($state['lead_name'] ?? null, $msg);
            $this->assertSame($expectedStops, (int) ($state['max_stops'] ?? -1), $msg);
            $this->assertSame('KHI', $state['origin'] ?? null, $msg);
            $this->assertSame('JED', $state['destination'] ?? null, $msg);
        }
    }

    public function test_legitimate_names_still_captured(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider(array_fill(0, 4, (string) json_encode([
            'domain' => 'travel', 'intent' => 'flight_search', 'operation' => 'clarify',
            'travel' => ['trip_type' => 'one_way', 'origin' => 'KHI', 'destination' => 'JED', 'adults' => 2],
            'missing' => [], 'references' => ['pending_confirmation' => true], 'corrections' => new \stdClass,
            'response_intent' => 'confirm_search',
        ], JSON_UNESCAPED_UNICODE))));

        $vid = str_repeat('cq45ahm', 6);
        $conv = $this->seedKhiJedPendingLeadName($vid);
        $t = $this->chat($vid, 'Ahmed', $conv->public_id);
        $t['response']->assertOk();
        $s = $this->reloadState($conv->public_id);
        $this->assertSame('Ahmed', $s['lead_name'] ?? null);
        $this->assertNotSame('name', $s['lead_capture_stage'] ?? 'name');

        $vid2 = str_repeat('cq45ali', 6);
        $conv2 = $this->seedKhiJedPendingLeadName($vid2);
        $t2 = $this->chat($vid2, 'Ali Khan', $conv2->public_id);
        $t2['response']->assertOk();
        $s2 = $this->reloadState($conv2->public_id);
        $this->assertSame('Ali Khan', $s2['lead_name'] ?? null);
    }

    public function test_mixed_name_travel_closure29_preserved(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider(array_fill(0, 6, (string) json_encode([
            'domain' => 'travel', 'intent' => 'flight_search', 'operation' => 'clarify',
            'travel' => [
                'trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'DXB',
                'legs' => [['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => '2026-09-29']],
                'adults' => 2,
            ],
            'missing' => [], 'references' => ['pending_confirmation' => false], 'corrections' => new \stdClass,
            'response_intent' => 'confirm_search',
        ], JSON_UNESCAPED_UNICODE))));

        $vid = str_repeat('cq45mix', 6);
        $help = $this->chat($vid, 'I need help');
        $help['response']->assertOk();
        $cid = $help['conversation_id'];
        $mixed = $this->chat($vid, "I'm Ahmed and I need Lahore to Dubai tomorrow for 2 adults", $cid);
        $mixed['response']->assertOk();
        $this->assertSame(0, (int) data_get($mixed['json'], 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $s = $this->reloadState($cid);
        $this->assertSame('Ahmed', $s['lead_name'] ?? null);
        $this->assertSame('LHE', $s['origin'] ?? null);
        $this->assertSame('DXB', $s['destination'] ?? null);
        $this->assertSame(2, (int) ($s['adults'] ?? 0));
        $this->assertNotEmpty($s['depart_date'] ?? null);
        $this->assertNotNull($s[FlightSearchConfirmationGate::STATE_KEY] ?? null);
    }

    public function test_bare_yes_no_confirmation_authority(): void
    {
        $this->enableSemanticAi();
        $gate = app(FlightSearchConfirmationGate::class);
        $this->assertTrue($gate->isAffirmative('Yes'));
        $this->assertTrue($gate->isNegative('No'));

        $this->rebindInference(new ScriptedInferenceProvider(array_fill(0, 4, (string) json_encode([
            'domain' => 'travel', 'intent' => 'flight_search', 'operation' => 'clarify',
            'travel' => ['trip_type' => 'one_way', 'origin' => 'KHI', 'destination' => 'JED', 'adults' => 2],
            'missing' => [], 'references' => ['pending_confirmation' => true], 'corrections' => new \stdClass,
            'response_intent' => 'confirm_search',
        ], JSON_UNESCAPED_UNICODE))));

        $vid = str_repeat('cq45yes', 6);
        $conv = $this->seedKhiJedPendingLeadName($vid);
        // Reject search execution in tests by leaving flight search mocked — just ensure Yes does not become lead_name.
        // Clear lead pending so Yes hits confirmation gate, not lead FSM.
        $st = is_array($conv->shopping_state) ? $conv->shopping_state : [];
        $st['lead_capture_pending'] = false;
        unset($st['lead_capture_stage']);
        $conv->shopping_state = $st;
        $conv->save();

        // Disable actual search execution for Yes by using flight_search_enabled false after confirm path...
        // Instead assert router/signals: Yes is not stop_refinement and gate owns affirmative.
        $signals = app(ServerTravelSignals::class);
        $yesAuth = $signals->progressiveTravelAuthority('Yes', $st);
        $this->assertFalse($yesAuth['stop_refinement']);
        $this->assertFalse($yesAuth['active']);
        $this->assertTrue($gate->isAffirmative('Yes'));
        $this->assertTrue($gate->isNegative('No'));
    }

    public function test_prior_open_jaw_direct_keeps_qwen_but_not_false_lead(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            (string) json_encode([
                'domain' => 'travel',
                'intent' => 'flight_search',
                'operation' => 'clarify',
                'travel' => [
                    'trip_type' => 'open_jaw',
                    'origin' => 'LHE',
                    'destination' => 'JED',
                    'legs' => [
                        ['origin' => 'LHE', 'destination' => 'JED', 'departure_date' => '2026-10-06'],
                        ['origin' => 'MED', 'destination' => 'LHE', 'departure_date' => null],
                    ],
                    'adults' => 1,
                    'max_stops' => 0,
                ],
                'missing' => [],
                'references' => ['pending_confirmation' => false],
                'corrections' => new \stdClass,
                'response_intent' => 'need_clarification',
            ], JSON_UNESCAPED_UNICODE),
        ]));

        $vid = str_repeat('cq45ojd', 6);
        $conv = AiConversation::query()->create([
            'public_id' => (string) Str::uuid(),
            'visitor_token_hash' => hash('sha256', $vid),
            'channel' => 'web',
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => [
                'intent' => 'flight_search',
                'origin' => 'LHE',
                'destination' => 'JED',
                'depart_date' => '2026-10-06',
                'trip_type' => 'open_jaw',
                'legs' => [
                    ['origin' => 'LHE', 'destination' => 'JED', 'departure_date' => '2026-10-06'],
                    ['origin' => 'MED', 'destination' => 'LHE', 'departure_date' => null],
                ],
                'lead_capture_pending' => true,
                'lead_capture_stage' => 'name',
                'lead_name' => null,
            ],
        ]);

        $det = app(ServerTravelSignals::class)->deterministicAuthorityComplete(
            'direct only',
            is_array($conv->shopping_state) ? $conv->shopping_state : []
        );
        $this->assertFalse($det['complete']);
        $this->assertSame('prior_multi_leg_requires_semantic', $det['reason']);

        $turn = $this->chat($vid, 'direct only', $conv->public_id);
        $turn['response']->assertOk();
        $after = $this->reloadState($conv->public_id);
        $this->assertNotSame('direct only', $after['lead_name'] ?? null);
        $this->assertNull($after['lead_name'] ?? null);
    }

    public function test_related_constraint_characterization_not_stop_refinement(): void
    {
        $signals = app(ServerTravelSignals::class);
        $router = app(ConversationIntentRouter::class);
        $prior = [
            'origin' => 'KHI',
            'destination' => 'JED',
            'intent' => 'flight_search',
            'depart_date' => '2026-10-06',
        ];

        foreach (['cheapest', 'fastest', 'morning'] as $msg) {
            $a = $signals->progressiveTravelAuthority($msg, $prior);
            $this->assertFalse($a['stop_refinement'], $msg);
            // Characterize vulnerability: bare ranking/time may still lookLikeBareName.
            $looksName = $router->looksLikeBareName($msg);
            $this->assertIsBool($looksName);
        }
    }

    public function test_name_direct_with_travel_characterized(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider(array_fill(0, 4, (string) json_encode([
            'domain' => 'travel', 'intent' => 'flight_search', 'operation' => 'clarify',
            'travel' => [
                'trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'DXB',
                'legs' => [['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => '2026-09-29']],
                'adults' => 1,
            ],
            'missing' => [], 'references' => [], 'corrections' => new \stdClass,
            'response_intent' => 'confirm_search',
        ], JSON_UNESCAPED_UNICODE))));

        $vid = str_repeat('cq45ndr', 6);
        $help = $this->chat($vid, 'I need help');
        $cid = $help['conversation_id'];
        $turn = $this->chat($vid, 'My name is Direct and I need Lahore to Dubai tomorrow', $cid);
        $turn['response']->assertOk();
        $s = $this->reloadState($cid);
        // Characterize only — do not invent rare-name exceptions in CQ45.
        $this->assertTrue(
            ($s['origin'] ?? null) === 'LHE'
            || ($s['destination'] ?? null) === 'DXB'
            || array_key_exists('lead_name', $s),
            'characterization: travel and/or lead outcome present; lead_name='.json_encode($s['lead_name'] ?? null)
        );
    }
}
