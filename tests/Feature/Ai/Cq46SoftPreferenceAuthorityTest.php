<?php

namespace Tests\Feature\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Data\Ai\TravelIntent;
use App\Models\AiConversation;
use App\Services\Ai\FlightSearchConfirmationGate;
use App\Services\Ai\Hybrid\ServerTravelSignals;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Ai\ScriptedInferenceProvider;
use Tests\TestCase;

/**
 * CQ46 — soft ranking/time preference authority (B SOFT_PREFERENCE_STATE).
 */
class Cq46SoftPreferenceAuthorityTest extends TestCase
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

    public function test_progressive_ranking_time_signals(): void
    {
        $signals = app(ServerTravelSignals::class);
        $prior = [
            'origin' => 'KHI',
            'destination' => 'JED',
            'intent' => 'flight_search',
            'depart_date' => '2026-10-06',
            'adults' => 2,
        ];

        $cheap = $signals->progressiveTravelAuthority('cheapest', $prior);
        $this->assertTrue($cheap['ranking_refinement']);
        $this->assertFalse($cheap['time_refinement']);
        $this->assertTrue($cheap['active']);
        $this->assertTrue($cheap['travel_refinement']);
        $this->assertFalse($cheap['travel_start']);

        $fast = $signals->progressiveTravelAuthority('fastest', $prior);
        $this->assertTrue($fast['ranking_refinement']);

        $morning = $signals->progressiveTravelAuthority('morning', $prior);
        $this->assertTrue($morning['time_refinement']);
        $this->assertFalse($morning['ranking_refinement']);
        $this->assertTrue($morning['active']);

        $name = $signals->progressiveTravelAuthority('Ahmed', $prior);
        $this->assertFalse($name['ranking_refinement']);
        $this->assertFalse($name['time_refinement']);

        $bare = $signals->progressiveTravelAuthority('cheapest', null);
        $this->assertTrue($bare['ranking_refinement']);
        $this->assertTrue($bare['active']);
        $this->assertFalse($bare['travel_start']);
        $this->assertFalse($bare['travel_refinement']);

        $det = $signals->deterministicAuthorityComplete('cheapest', $prior);
        $this->assertFalse($det['complete']);
        $this->assertNotContains('ranking_refinement', $det['classes'] ?? []);
    }

    public function test_pending_cheapest_and_morning_soft_preference(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([]));
        $gate = app(FlightSearchConfirmationGate::class);

        $vid = str_repeat('cq46pch', 6);
        $conv = $this->seedKhiJedPendingLeadName($vid);
        $beforePending = $gate->pendingSnapshot($conv);
        $this->assertIsArray($beforePending);

        $turn = $this->chat($vid, 'cheapest', $conv->public_id);
        $turn['response']->assertOk();
        $json = $turn['json'];
        $this->assertSame('YES', data_get($json, 'meta.SOFT_PREFERENCE_AUTHORITY'));
        $this->assertSame('CHEAPEST', data_get($json, 'meta.RANKING_PREFERENCE'));
        $this->assertSame(0, (int) data_get($json, 'meta.MODEL_CALLS', 0));
        $this->assertSame(0, (int) data_get($json, 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $this->assertSame('NO', data_get($json, 'meta.SEMANTIC_BRAIN_CALLED'));
        $this->assertNull(data_get($json, 'meta.DETERMINISTIC_AUTHORITY_COMPLETE'));

        $st = $this->reloadState($conv->public_id);
        $this->assertNull($st['lead_name'] ?? null);
        $this->assertSame('name', $st['lead_capture_stage'] ?? null);
        $this->assertSame('CHEAPEST', $st['ranking_preference'] ?? null);
        $this->assertSame('flight_search', $st['intent'] ?? null);
        $this->assertSame('KHI', $st['origin'] ?? null);
        $this->assertSame('JED', $st['destination'] ?? null);
        $this->assertSame(2, (int) ($st['adults'] ?? 0));
        $afterPending = $st[FlightSearchConfirmationGate::STATE_KEY] ?? null;
        $this->assertIsArray($afterPending);
        $this->assertTrue($gate->snapshotsEqual($beforePending, $afterPending));
        $body = mb_strtolower((string) data_get($json, 'message', ''));
        $this->assertStringContainsString('noted', $body);
        $this->assertStringNotContainsString('only show', $body);
        $this->assertFalse(str_contains($body, 'email') && str_contains($body, 'phone'));

        $vid2 = str_repeat('cq46pmr', 6);
        $conv2 = $this->seedKhiJedPendingLeadName($vid2);
        $bp2 = $gate->pendingSnapshot($conv2);
        $t2 = $this->chat($vid2, 'morning', $conv2->public_id);
        $t2['response']->assertOk();
        $this->assertSame('YES', data_get($t2['json'], 'meta.SOFT_PREFERENCE_AUTHORITY'));
        $this->assertSame('morning', data_get($t2['json'], 'meta.TIME_PREFERENCE'));
        $this->assertSame(0, (int) data_get($t2['json'], 'meta.MODEL_CALLS', 0));
        $s2 = $this->reloadState($conv2->public_id);
        $this->assertNull($s2['lead_name'] ?? null);
        $this->assertSame('morning', $s2['time_preference'] ?? null);
        $this->assertSame('flight_search', $s2['intent'] ?? null);
        $this->assertTrue($gate->snapshotsEqual($bp2, $s2[FlightSearchConfirmationGate::STATE_KEY] ?? []));
    }

    public function test_business_cabin_vocabulary_unchanged(): void
    {
        $resolver = app(\App\Services\Ai\Hybrid\TravelConstraintResolver::class);

        foreach (['business class', 'business cabin', 'make it business'] as $phrase) {
            $resolved = $resolver->resolve($phrase, $phrase);
            $this->assertSame('business', $resolved['cabin'] ?? null, $phrase);
        }

        // Pre-CQ46: bare "business" is not a cabin token.
        $bare = $resolver->resolve('business', 'business');
        $this->assertNull($bare['cabin'] ?? null);
    }

    public function test_ranking_and_time_families(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([]));

        $ranking = [
            'cheapest' => 'CHEAPEST',
            'cheap' => 'CHEAPEST',
            'sasti' => 'CHEAPEST',
            'سستی' => 'CHEAPEST',
            'fastest' => 'FASTEST',
            'fast' => 'FASTEST',
            'jaldi' => 'FASTEST',
            'shortest layover' => 'SHORTEST_LAYOVER',
            'long layover nahi' => 'SHORTEST_LAYOVER',
            'best value' => 'BEST_VALUE',
            'best option' => 'BEST_VALUE',
            'best' => 'BEST_VALUE',
        ];
        foreach ($ranking as $msg => $expected) {
            $vid = substr(preg_replace('/[^a-z0-9]/', '', 'cq46r'.md5($msg)).str_repeat('x', 32), 0, 42);
            $conv = $this->seedKhiJedPendingLeadName($vid);
            $turn = $this->chat($vid, $msg, $conv->public_id);
            $turn['response']->assertOk();
            $this->assertSame(0, (int) data_get($turn['json'], 'meta.MODEL_CALLS', 0), $msg);
            $st = $this->reloadState($conv->public_id);
            $this->assertNull($st['lead_name'] ?? null, $msg);
            $this->assertSame($expected, $st['ranking_preference'] ?? null, $msg);
            $this->assertSame('flight_search', $st['intent'] ?? null, $msg);
        }

        $times = [
            'morning' => 'morning',
            'subah' => 'morning',
            'صبح' => 'morning',
            'evening' => 'evening',
            'shaam' => 'evening',
            'شام' => 'evening',
            'night' => 'night',
            'raat' => 'night',
            'رات' => 'night',
        ];
        foreach ($times as $msg => $expected) {
            $vid = substr(preg_replace('/[^a-z0-9]/', '', 'cq46t'.md5($msg)).str_repeat('x', 32), 0, 42);
            $conv = $this->seedKhiJedPendingLeadName($vid);
            $turn = $this->chat($vid, $msg, $conv->public_id);
            $turn['response']->assertOk();
            $st = $this->reloadState($conv->public_id);
            $this->assertNull($st['lead_name'] ?? null, $msg);
            $this->assertSame($expected, $st['time_preference'] ?? null, $msg);
            $this->assertSame(0, (int) data_get($turn['json'], 'meta.MODEL_CALLS', 0), $msg);
            $this->assertSame('flight_search', $st['intent'] ?? null, $msg);
        }
    }

    public function test_soft_pref_then_yes_and_no(): void
    {
        $this->enableSemanticAi([
            'ota.ai_assistant.semantic_planner_enabled' => false,
            'ota.ai_assistant.conversational_enabled' => false,
        ]);
        $this->rebindInference(new ScriptedInferenceProvider([]));

        $vid = str_repeat('cq46yes', 6);
        $conv = $this->seedKhiJedPendingLeadName($vid);
        $pref = $this->chat($vid, 'cheapest', $conv->public_id);
        $pref['response']->assertOk();
        $this->assertSame('CHEAPEST', $this->reloadState($conv->public_id)['ranking_preference'] ?? null);

        $yes = $this->chat($vid, 'Yes', $conv->public_id);
        $yes['response']->assertOk();
        $this->assertTrue((bool) data_get($yes['json'], 'meta.CONFIRMATION_BEFORE_SEARCH'));
        $this->assertSame(1, (int) data_get($yes['json'], 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $afterYes = $this->reloadState($conv->public_id);
        $this->assertNull($afterYes['lead_name'] ?? null);
        $this->assertSame('CHEAPEST', $afterYes['ranking_preference'] ?? null);

        $vidN = str_repeat('cq46bno', 6);
        $convN = $this->seedKhiJedPendingLeadName($vidN);
        $this->chat($vidN, 'morning', $convN->public_id);
        $no = $this->chat($vidN, 'No', $convN->public_id);
        $no['response']->assertOk();
        $this->assertSame(0, (int) data_get($no['json'], 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $afterNo = $this->reloadState($convN->public_id);
        $this->assertNull($afterNo['lead_name'] ?? null);
        $this->assertNull($afterNo[FlightSearchConfirmationGate::STATE_KEY] ?? null);
        $this->assertSame('morning', $afterNo['time_preference'] ?? null);
    }

    public function test_standalone_and_open_jaw(): void
    {
        $this->enableSemanticAi();
        $provider = new ScriptedInferenceProvider([
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
                ],
                'missing' => [],
                'references' => [],
                'corrections' => new \stdClass,
                'response_intent' => 'need_clarification',
            ], JSON_UNESCAPED_UNICODE),
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
                ],
                'missing' => [],
                'references' => [],
                'corrections' => new \stdClass,
                'response_intent' => 'need_clarification',
            ], JSON_UNESCAPED_UNICODE),
        ]);
        $this->rebindInference($provider);

        $vidS = str_repeat('cq46std', 6);
        $help = $this->chat($vidS, 'I need help');
        $cid = $help['conversation_id'];
        $stand = $this->chat($vidS, 'cheapest', $cid);
        $stand['response']->assertOk();
        $this->assertSame(0, (int) data_get($stand['json'], 'meta.MODEL_CALLS', 0));
        $ss = $this->reloadState($cid);
        $this->assertNotSame('cheapest', $ss['lead_name'] ?? null);
        $this->assertNull($ss['lead_name'] ?? null);
        $this->assertSame('CHEAPEST', $ss['ranking_preference'] ?? null);

        $vidOj = str_repeat('cq46ojr', 6);
        $ojPrior = [
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
        ];
        $convOj = AiConversation::query()->create([
            'public_id' => (string) Str::uuid(),
            'visitor_token_hash' => hash('sha256', $vidOj),
            'channel' => 'web',
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => $ojPrior,
        ]);
        $before = $provider->callCount();
        $oj = $this->chat($vidOj, 'cheapest', $convOj->public_id);
        $oj['response']->assertOk();
        $this->assertSame($before + 1, $provider->callCount());
        $this->assertNotSame('YES', data_get($oj['json'], 'meta.SOFT_PREFERENCE_AUTHORITY'));
        $soj = $this->reloadState($convOj->public_id);
        $this->assertNull($soj['lead_name'] ?? null);
        $this->assertSame('open_jaw', $soj['trip_type'] ?? null);
        $this->assertCount(2, $soj['legs'] ?? []);

        $vidOjT = str_repeat('cq46ojt', 6);
        $convOjT = AiConversation::query()->create([
            'public_id' => (string) Str::uuid(),
            'visitor_token_hash' => hash('sha256', $vidOjT),
            'channel' => 'web',
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => $ojPrior,
        ]);
        $beforeT = $provider->callCount();
        $ojt = $this->chat($vidOjT, 'morning', $convOjT->public_id);
        $ojt['response']->assertOk();
        $this->assertSame($beforeT + 1, $provider->callCount());
        $sot = $this->reloadState($convOjT->public_id);
        $this->assertNull($sot['lead_name'] ?? null);
        $this->assertCount(2, $sot['legs'] ?? []);
    }

    public function test_mixed_material_and_names_and_cq45(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider(array_fill(0, 20, (string) json_encode([
            'domain' => 'travel', 'intent' => 'flight_search', 'operation' => 'clarify',
            'travel' => ['trip_type' => 'one_way', 'origin' => 'KHI', 'destination' => 'JED', 'adults' => 2],
            'missing' => [], 'references' => ['pending_confirmation' => true], 'corrections' => new \stdClass,
            'response_intent' => 'confirm_search',
        ], JSON_UNESCAPED_UNICODE))));

        $vid = str_repeat('cq46mix', 6);
        $conv = $this->seedKhiJedPendingLeadName($vid);
        $t = $this->chat($vid, 'direct and cheapest', $conv->public_id);
        $t['response']->assertOk();
        $st = $this->reloadState($conv->public_id);
        $this->assertSame(0, (int) ($st['max_stops'] ?? -1));
        $this->assertSame('CHEAPEST', $st['ranking_preference'] ?? null);
        $this->assertNull($st['lead_name'] ?? null);
        $this->assertSame(0, (int) data_get($t['json'], 'meta.MODEL_CALLS', 0));

        $vidC = str_repeat('cq46cab', 6);
        $cC = $this->seedKhiJedPendingLeadName($vidC);
        $tC = $this->chat($vidC, 'business class and cheapest', $cC->public_id);
        $tC['response']->assertOk();
        $sC = $this->reloadState($cC->public_id);
        $this->assertSame('business', $sC['cabin'] ?? null);
        $this->assertSame('CHEAPEST', $sC['ranking_preference'] ?? null);
        $this->assertSame(0, (int) data_get($tC['json'], 'meta.MODEL_CALLS', 0));

        $vidP = str_repeat('cq46pax', 6);
        $cP = $this->seedKhiJedPendingLeadName($vidP);
        // Seed adults=2; phrase "2 adults and morning" keeps 2 + morning.
        $tP = $this->chat($vidP, '2 adults and morning', $cP->public_id);
        $tP['response']->assertOk();
        $sP = $this->reloadState($cP->public_id);
        $this->assertSame(2, (int) ($sP['adults'] ?? 0));
        $this->assertSame('morning', $sP['time_preference'] ?? null);

        $vidA = str_repeat('cq46ahm', 6);
        $cA = $this->seedKhiJedPendingLeadName($vidA);
        $tA = $this->chat($vidA, 'Ahmed', $cA->public_id);
        $tA['response']->assertOk();
        $this->assertSame('Ahmed', $this->reloadState($cA->public_id)['lead_name'] ?? null);

        $vidD = str_repeat('cq46dir', 6);
        $cD = $this->seedKhiJedPendingLeadName($vidD);
        $tD = $this->chat($vidD, 'direct only', $cD->public_id);
        $tD['response']->assertOk();
        $sD = $this->reloadState($cD->public_id);
        $this->assertNull($sD['lead_name'] ?? null);
        $this->assertSame(0, (int) ($sD['max_stops'] ?? -1));
        $this->assertSame(0, (int) data_get($tD['json'], 'meta.MODEL_CALLS', 0));
    }

    public function test_confirmation_snapshot_schema_unchanged(): void
    {
        $gate = app(FlightSearchConfirmationGate::class);
        $snap = $gate->buildSnapshot(TravelIntent::fromArray([
            'intent' => 'flight_search',
            'origin' => 'KHI',
            'destination' => 'JED',
            'depart_date' => '2026-10-06',
            'adults' => 2,
            'time_preference' => 'morning',
            'max_stops' => 0,
        ], 'STRUCTURED_FALLBACK'));
        $this->assertArrayNotHasKey('ranking_preference', $snap);
        $this->assertArrayNotHasKey('time_preference', $snap);
        $this->assertArrayHasKey('max_stops', $snap);
    }

    public function test_active_without_pending_and_standalone_morning(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([]));

        $vid = str_repeat('cq46act', 6);
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
                'adults' => 1,
                'trip_type' => 'one_way',
                'lead_capture_pending' => true,
                'lead_capture_stage' => 'name',
                'lead_name' => null,
            ],
        ]);
        $fast = $this->chat($vid, 'fastest', $conv->public_id);
        $fast['response']->assertOk();
        $this->assertSame(0, (int) data_get($fast['json'], 'meta.MODEL_CALLS', 0));
        $this->assertSame(0, (int) data_get($fast['json'], 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $sf = $this->reloadState($conv->public_id);
        $this->assertSame('FASTEST', $sf['ranking_preference'] ?? null);
        $this->assertNull($sf['lead_name'] ?? null);
        $this->assertSame('flight_search', $sf['intent'] ?? null);
        $this->assertSame('KHI', $sf['origin'] ?? null);
        $this->assertSame('JED', $sf['destination'] ?? null);
        $this->assertSame('2026-10-06', $sf['depart_date'] ?? null);

        // Ranking then time on active travel — intent/route preserved.
        $cheap = $this->chat($vid, 'cheapest', $conv->public_id);
        $cheap['response']->assertOk();
        $sc = $this->reloadState($conv->public_id);
        $this->assertSame('CHEAPEST', $sc['ranking_preference'] ?? null);
        $this->assertSame('flight_search', $sc['intent'] ?? null);

        $eve = $this->chat($vid, 'evening', $conv->public_id);
        $eve['response']->assertOk();
        $this->assertSame(0, (int) data_get($eve['json'], 'meta.MODEL_CALLS', 0));
        $se = $this->reloadState($conv->public_id);
        $this->assertSame('evening', $se['time_preference'] ?? null);
        $this->assertSame('flight_search', $se['intent'] ?? null);

        $morn = $this->chat($vid, 'morning', $conv->public_id);
        $morn['response']->assertOk();
        $smAct = $this->reloadState($conv->public_id);
        $this->assertSame('morning', $smAct['time_preference'] ?? null);
        $this->assertSame('flight_search', $smAct['intent'] ?? null);

        $vidM = str_repeat('cq46stm', 6);
        $help = $this->chat($vidM, 'I need help');
        $stand = $this->chat($vidM, 'morning', $help['conversation_id']);
        $stand['response']->assertOk();
        $this->assertSame(0, (int) data_get($stand['json'], 'meta.MODEL_CALLS', 0));
        $sm = $this->reloadState($help['conversation_id']);
        $this->assertNull($sm['lead_name'] ?? null);
        $this->assertNotSame('morning', $sm['lead_name'] ?? null);
        $this->assertSame('morning', $sm['time_preference'] ?? null);
    }

    public function test_explicit_route_preference_persist_and_names_yes_no(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider(array_fill(0, 12, (string) json_encode([
            'domain' => 'travel', 'intent' => 'flight_search', 'operation' => 'clarify',
            'travel' => ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'DXB', 'adults' => 1],
            'missing' => [], 'references' => [], 'corrections' => new \stdClass,
            'response_intent' => 'confirm_search',
        ], JSON_UNESCAPED_UNICODE))));

        $vidR = str_repeat('cq46xrt', 6);
        $route = $this->chat($vidR, 'Lahore to Dubai tomorrow cheapest');
        $route['response']->assertOk();
        $this->assertSame(0, (int) data_get($route['json'], 'meta.MODEL_CALLS', 0));
        $sr = $this->reloadState($route['conversation_id']);
        $this->assertSame('LHE', $sr['origin'] ?? null);
        $this->assertSame('DXB', $sr['destination'] ?? null);
        $this->assertSame('CHEAPEST', $sr['ranking_preference'] ?? null);
        $this->assertNotNull($sr[FlightSearchConfirmationGate::STATE_KEY] ?? null);

        $vidT = str_repeat('cq46xtm', 6);
        $routeT = $this->chat($vidT, 'Islamabad to Dubai tomorrow morning');
        $routeT['response']->assertOk();
        $st = $this->reloadState($routeT['conversation_id']);
        $this->assertSame('ISB', $st['origin'] ?? null);
        $this->assertSame('DXB', $st['destination'] ?? null);
        $this->assertSame('morning', $st['time_preference'] ?? null);

        $vidA = str_repeat('cq46ali', 6);
        $cA = $this->seedKhiJedPendingLeadName($vidA);
        $ali = $this->chat($vidA, 'Ali Khan', $cA->public_id);
        $ali['response']->assertOk();
        $this->assertSame('Ali Khan', $this->reloadState($cA->public_id)['lead_name'] ?? null);

        // Characterization only — do not claim EXPLICIT_NAME_*=PASS unless name captured.
        $explicitBehaviors = [];
        foreach (['My name is Morning' => 'MORNING', 'My name is Fast' => 'FAST', 'My name is Best' => 'BEST'] as $phrase => $label) {
            $vid = substr('cq46en'.md5($label).str_repeat('z', 40), 0, 42);
            $c = $this->seedKhiJedPendingLeadName($vid);
            $turn = $this->chat($vid, $phrase, $c->public_id);
            $turn['response']->assertOk();
            $name = $this->reloadState($c->public_id)['lead_name'] ?? null;
            $explicitBehaviors[$label] = $name;
            $this->assertNotSame('cheapest', $name, $phrase);
            $this->assertNotSame('fastest', $name, $phrase);
            $this->assertNotSame('morning', mb_strtolower((string) $name), $phrase);
        }
        // Expose for evidence: actual captured name or null (not a CQ46.1 pass claim).
        $this->assertIsArray($explicitBehaviors);

        $vidY = str_repeat('cq46byes', 5).'x';
        $cY = $this->seedKhiJedPendingLeadName($vidY);
        $yes = $this->chat($vidY, 'Yes', $cY->public_id);
        $yes['response']->assertOk();
        $this->assertTrue((bool) data_get($yes['json'], 'meta.CONFIRMATION_BEFORE_SEARCH'));
        $this->assertSame(1, (int) data_get($yes['json'], 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $this->assertNull($this->reloadState($cY->public_id)['lead_name'] ?? null);

        $vidN = str_repeat('cq46bno2', 5);
        $cN = $this->seedKhiJedPendingLeadName($vidN);
        $no = $this->chat($vidN, 'No', $cN->public_id);
        $no['response']->assertOk();
        $this->assertSame(0, (int) data_get($no['json'], 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $this->assertNull($this->reloadState($cN->public_id)[FlightSearchConfirmationGate::STATE_KEY] ?? null);
        unset($gate);
    }
}
