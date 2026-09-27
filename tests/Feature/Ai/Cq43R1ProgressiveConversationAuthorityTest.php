<?php

namespace Tests\Feature\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Models\AiConversation;
use App\Services\Ai\Hybrid\LocationResolver;
use App\Services\Ai\Hybrid\PassengerExpressionResolver;
use App\Services\Ai\Hybrid\ServerTravelSignals;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Ai\ScriptedInferenceProvider;
use Tests\TestCase;

/**
 * JP-AI-CQ43-R1-PROGRESSIVE-CONVERSATION-AUTHORITY
 */
class Cq43R1ProgressiveConversationAuthorityTest extends TestCase
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

    private function supportHandoffPlan(): string
    {
        return $this->planJson([
            'domain' => 'support',
            'intent' => 'human',
            'operation' => 'handoff',
        ]);
    }

    private function reloadState(string $conversationId): array
    {
        $conv = AiConversation::query()->where('public_id', $conversationId)->firstOrFail();

        return is_array($conv->shopping_state) ? $conv->shopping_state : [];
    }

    public function test_partial_route_cannot_self_pair(): void
    {
        $locations = app(LocationResolver::class);
        [$o, $d] = $locations->extractRoute('from lahore', 'from Lahore');
        $this->assertSame('LHE', $o);
        $this->assertNull($d);

        [$o2, $d2] = $locations->extractRoute('lahore se', 'Lahore se');
        $this->assertSame('LHE', $o2);
        $this->assertNull($d2);

        $prog = $locations->extractProgressiveOd('from lahore', 'from Lahore');
        $this->assertTrue($prog['origin_only']);
        $this->assertFalse($prog['explicit_route']);
        $this->assertSame('LHE', $prog['origin']);
        $this->assertNull($prog['destination']);

        $open = $locations->extractOpenJawLegs('from lahore', 'from Lahore');
        $this->assertNull($open);
    }

    public function test_destination_led_and_corrections(): void
    {
        $locations = app(LocationResolver::class);
        [$o, $d] = $locations->extractRoute('i need dubai', 'I need Dubai');
        $this->assertNull($o);
        $this->assertSame('DXB', $d);

        [$o2, $d2] = $locations->extractRoute('dubai jana hai', 'Dubai jana hai');
        $this->assertNull($o2);
        $this->assertSame('DXB', $d2);

        [$o3, $d3] = $locations->extractRoute('make it doha', 'Make it Doha');
        $this->assertNull($o3);
        $this->assertSame('DOH', $d3);

        [$o4, $d4] = $locations->extractRoute('actually dubai again', 'actually Dubai again');
        $this->assertNull($o4);
        $this->assertSame('DXB', $d4);
    }

    public function test_hum_dono_relational_pair(): void
    {
        $pax = app(PassengerExpressionResolver::class)->resolve('hum dono', 'hum dono');
        $this->assertSame(2, $pax['adults']);
        $this->assertSame('RELATIONAL_PAIR', $pax['provenance']['adults'] ?? null);
        $this->assertNull($pax['children']);
        $this->assertNull($pax['infants']);
    }

    public function test_come_back_on_sunday_after_depart(): void
    {
        $signals = app(ServerTravelSignals::class);
        $dates = $signals->resolveTripDates('I also need to come back on Sunday', null, '2026-10-03');
        $this->assertNull($dates['depart_date']);
        $this->assertSame('2026-10-04', $dates['return_date']);
        $this->assertTrue($signals->explicitReturnTripCue('I also need to come back on Sunday'));

        $wapis = $signals->resolveTripDates('wapis Sunday', null, '2026-10-03');
        $this->assertSame('2026-10-04', $wapis['return_date']);
    }

    public function test_fresh_wapis_direction_not_return(): void
    {
        $signals = app(ServerTravelSignals::class);
        $route = $signals->explicitTravelRoute('Dubai se Lahore wapis');
        $this->assertTrue($route['explicit']);
        $this->assertSame('DXB', $route['origin']);
        $this->assertSame('LHE', $route['destination']);
        $this->assertFalse($signals->explicitReturnTripCue('Dubai se Lahore wapis'));
    }

    public function test_progressive_english_search_and_correction_chain(): void
    {
        $this->enableSemanticAi();
        $plans = [];
        for ($i = 0; $i < 12; $i++) {
            $plans[] = $this->planJson(['operation' => 'clarify']);
        }
        $this->rebindInference(new ScriptedInferenceProvider($plans));

        $vid = str_repeat('cq43e', 8);
        $cid = null;

        $t1 = $this->chat($vid, 'I need Dubai', $cid);
        $t1['response']->assertOk();
        $cid = $t1['conversation_id'];
        $body1 = mb_strtolower((string) $t1['response']->json('message'));
        $this->assertStringNotContainsString('may i start with your name', $body1);
        $this->assertStringNotContainsString('waiting_for_human', mb_strtolower((string) $t1['response']->json('status')));
        $s1 = $this->reloadState($cid);
        $this->assertSame('DXB', $s1['destination'] ?? null);
        $this->assertNull($s1['origin'] ?? null);
        $this->assertNotSame('Make it Doha', $s1['lead_name'] ?? null);

        $t2 = $this->chat($vid, 'from Lahore', $cid);
        $t2['response']->assertOk();
        $s2 = $this->reloadState($cid);
        $this->assertSame('LHE', $s2['origin'] ?? null);
        $this->assertSame('DXB', $s2['destination'] ?? null);
        $this->assertNotSame('open_jaw', $s2['trip_type'] ?? null);
        $this->assertNull($s2['legs'] ?? null);

        $t3 = $this->chat($vid, 'next Friday', $cid);
        $t3['response']->assertOk();
        $s3 = $this->reloadState($cid);
        $this->assertSame('2026-10-02', $s3['depart_date'] ?? null);

        $t4 = $this->chat($vid, '2 adults', $cid);
        $t4['response']->assertOk();
        $s4 = $this->reloadState($cid);
        $this->assertSame(2, (int) ($s4['adults'] ?? 0));

        $t5 = $this->chat($vid, 'economy', $cid);
        $t5['response']->assertOk();
        $s5 = $this->reloadState($cid);
        $this->assertSame('LHE', $s5['origin'] ?? null);
        $this->assertSame('DXB', $s5['destination'] ?? null);
        $this->assertSame(2, (int) ($s5['adults'] ?? 0));
        $this->assertSame('economy', $s5['cabin'] ?? null);
        $this->assertContains($s5['trip_type'] ?? 'one_way', ['one_way', null, '']);

        $t6 = $this->chat($vid, 'Make it Doha', $cid);
        $t6['response']->assertOk();
        $s6 = $this->reloadState($cid);
        $this->assertSame('DOH', $s6['destination'] ?? null);
        $this->assertNotSame('Make it Doha', $s6['lead_name'] ?? null);

        $t7 = $this->chat($vid, 'actually Dubai again', $cid);
        $t7['response']->assertOk();
        $s7 = $this->reloadState($cid);
        $this->assertSame('DXB', $s7['destination'] ?? null);

        $t8 = $this->chat($vid, '3 adults', $cid);
        $t8['response']->assertOk();
        $s8 = $this->reloadState($cid);
        $this->assertSame(3, (int) ($s8['adults'] ?? 0));

        $t9 = $this->chat($vid, 'business class', $cid);
        $t9['response']->assertOk();
        $s9 = $this->reloadState($cid);
        $this->assertSame('business', $s9['cabin'] ?? null);
        $this->assertSame('LHE', $s9['origin'] ?? null);
        $this->assertSame('DXB', $s9['destination'] ?? null);
        $this->assertNotSame(AiConversation::STATE_WAITING_FOR_HUMAN, AiConversation::query()->where('public_id', $cid)->value('state'));
    }

    public function test_roman_urdu_progressive_and_contextual_wapis(): void
    {
        $this->enableSemanticAi();
        $plans = array_fill(0, 10, $this->supportHandoffPlan());
        $this->rebindInference(new ScriptedInferenceProvider($plans));

        $vid = str_repeat('cq43u', 8);
        $cid = null;

        $t1 = $this->chat($vid, 'Dubai jana hai', $cid);
        $t1['response']->assertOk();
        $cid = $t1['conversation_id'];
        $this->assertStringNotContainsString('may i start with your name', mb_strtolower((string) $t1['response']->json('message')));
        $s1 = $this->reloadState($cid);
        $this->assertSame('DXB', $s1['destination'] ?? null);

        $t2 = $this->chat($vid, 'Lahore se', $cid);
        $t2['response']->assertOk();
        $s2 = $this->reloadState($cid);
        $this->assertSame('LHE', $s2['origin'] ?? null);
        $this->assertSame('DXB', $s2['destination'] ?? null);

        $t3 = $this->chat($vid, 'kal', $cid);
        $t3['response']->assertOk();
        $this->assertNotSame('WAITING_FOR_HUMAN', $t3['response']->json('state'));
        $s3 = $this->reloadState($cid);
        $this->assertSame('2026-09-28', $s3['depart_date'] ?? null);

        $t4 = $this->chat($vid, 'hum dono', $cid);
        $t4['response']->assertOk();
        $s4 = $this->reloadState($cid);
        $this->assertSame(2, (int) ($s4['adults'] ?? 0));

        $t5 = $this->chat($vid, 'wapis Sunday', $cid);
        $t5['response']->assertOk();
        $this->assertNotSame('WAITING_FOR_HUMAN', $t5['response']->json('state'));
        $s5 = $this->reloadState($cid);
        $this->assertSame('return', $s5['trip_type'] ?? null);
        $this->assertSame('2026-10-04', $s5['return_date'] ?? null);
        $this->assertSame('LHE', $s5['origin'] ?? null);
        $this->assertSame('DXB', $s5['destination'] ?? null);
    }

    public function test_come_back_on_sunday_oneway_to_return(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson(['operation' => 'clarify']),
            $this->planJson(['operation' => 'clarify']),
        ]));

        $vid = str_repeat('cq43r', 8);
        $conv = AiConversation::query()->create([
            'public_id' => (string) Str::uuid(),
            'visitor_token_hash' => hash('sha256', $vid),
            'channel' => 'web',
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => [
                'intent' => 'flight_search',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-10-03',
                'trip_type' => 'one_way',
                'adults' => 1,
            ],
        ]);

        $turn = $this->chat($vid, 'I also need to come back on Sunday', $conv->public_id);
        $turn['response']->assertOk();
        $state = $this->reloadState($conv->public_id);
        $this->assertSame('return', $state['trip_type'] ?? null);
        $this->assertSame('2026-10-04', $state['return_date'] ?? null);
        $this->assertSame('2026-10-03', $state['depart_date'] ?? null);
        $this->assertSame('LHE', $state['origin'] ?? null);
        $this->assertSame('DXB', $state['destination'] ?? null);
    }

    public function test_travel_refinement_overrides_lead_fsm_make_it_doha(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'operation' => 'clarify',
                'travel' => [
                    'origin' => 'LHE',
                    'destination' => 'DOH',
                    'legs' => [['origin' => 'LHE', 'destination' => 'DOH', 'departure_date' => null]],
                ],
            ]),
        ]));

        $vid = str_repeat('cq43l', 8);
        $conv = AiConversation::query()->create([
            'public_id' => (string) Str::uuid(),
            'visitor_token_hash' => hash('sha256', $vid),
            'channel' => 'web',
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => [
                'lead_capture_pending' => true,
                'lead_capture_stage' => 'name',
                'intent' => 'flight_search',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-10-02',
                'adults' => 2,
            ],
        ]);

        $turn = $this->chat($vid, 'Make it Doha', $conv->public_id);
        $turn['response']->assertOk();
        $state = $this->reloadState($conv->public_id);
        $this->assertNotSame('Make it Doha', $state['lead_name'] ?? null);
        $this->assertSame('DOH', $state['destination'] ?? null);
        $this->assertStringNotContainsString('may i start with your name', mb_strtolower((string) $turn['response']->json('message')));
    }

    public function test_bare_name_lead_non_regression(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson(['operation' => 'clarify']),
        ]));

        $vid = str_repeat('cq43n', 8);
        $conv = AiConversation::query()->create([
            'public_id' => (string) Str::uuid(),
            'visitor_token_hash' => hash('sha256', $vid),
            'channel' => 'web',
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => [
                'lead_capture_pending' => true,
                'lead_capture_stage' => 'name',
                'lead_capture_fields' => ['name', 'email', 'phone', 'contact_consent'],
            ],
        ]);

        $turn = $this->chat($vid, 'Muhammad Ali', $conv->public_id);
        $turn['response']->assertOk();
        $state = $this->reloadState($conv->public_id);
        $this->assertSame('Muhammad Ali', $state['lead_name'] ?? null);
    }

    public function test_qwen_model_only_handoff_blocked_on_refinements(): void
    {
        $this->enableSemanticAi();
        $phrases = ['kal', 'next Friday', '2 adults', 'hum dono', 'business class', 'from Lahore', 'Lahore se'];
        $plans = array_fill(0, count($phrases) + 2, $this->supportHandoffPlan());
        $this->rebindInference(new ScriptedInferenceProvider($plans));

        $vid = str_repeat('cq43h', 8);
        $conv = AiConversation::query()->create([
            'public_id' => (string) Str::uuid(),
            'visitor_token_hash' => hash('sha256', $vid),
            'channel' => 'web',
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => [
                'intent' => 'flight_search',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-10-02',
                'adults' => 2,
                'cabin' => 'economy',
            ],
        ]);

        foreach ($phrases as $phrase) {
            $turn = $this->chat($vid, $phrase, $conv->public_id);
            $turn['response']->assertOk();
            $this->assertNotSame('WAITING_FOR_HUMAN', $turn['response']->json('state'), $phrase);
            $this->assertNotSame('waiting_for_human', $turn['response']->json('status'), $phrase);
            $body = mb_strtolower((string) $turn['response']->json('message'));
            $this->assertStringNotContainsString('support queue', $body, $phrase);
        }

        $explicit = $this->chat($vid, 'Talk to support', $conv->public_id);
        $explicit['response']->assertOk();
        $this->assertTrue(
            $explicit['response']->json('state') === 'WAITING_FOR_HUMAN'
            || str_contains(mb_strtolower((string) $explicit['response']->json('message')), 'support')
        );
    }

    public function test_booking_detour_preserves_pending_travel_and_pax(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson(['operation' => 'clarify']),
            $this->planJson(['operation' => 'clarify']),
            $this->planJson(['operation' => 'clarify']),
        ]));

        $vid = str_repeat('cq43b', 8);
        $gate = app(\App\Services\Ai\FlightSearchConfirmationGate::class);
        $intent = \App\Data\Ai\TravelIntent::fromArray([
            'intent' => 'flight_search',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-10-02',
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
                'depart_date' => '2026-10-02',
                'adults' => 2,
                'cabin' => 'economy',
                'trip_type' => 'one_way',
            ],
        ]);
        $snapshot = $gate->buildSnapshot($intent);
        $gate->storePending($conv, $snapshot);
        $before = $this->reloadState($conv->public_id);

        $booking = $this->chat($vid, 'Check my booking', $conv->public_id);
        $booking['response']->assertOk();
        $body = mb_strtolower((string) $booking['response']->json('message'));
        $this->assertStringContainsString('booking', $body);
        $this->assertStringNotContainsString('confirm', $body);
        $this->assertFalse((bool) $booking['response']->json('requires_confirmation'));
        // CQ43-R1.1 public booking contract during detour.
        $this->assertSame('clarify', $booking['response']->json('status'));
        $this->assertNull($booking['response']->json('booking'));
        $actions = $booking['response']->json('actions');
        $this->assertIsArray($actions);
        $labels = array_map(static fn ($a) => is_array($a) ? (string) ($a['label'] ?? '') : '', $actions);
        $this->assertContains('Lookup Booking', $labels);
        $this->assertContains('Talk to Support', $labels);

        $afterBooking = $this->reloadState($conv->public_id);
        $this->assertSame('LHE', $afterBooking['origin'] ?? null);
        $this->assertSame('DXB', $afterBooking['destination'] ?? null);
        $this->assertSame('2026-10-02', $afterBooking['depart_date'] ?? null);
        $this->assertSame(2, (int) ($afterBooking['adults'] ?? 0));
        $this->assertNotSame('booking_lookup', $afterBooking['intent'] ?? null);
        $this->assertTrue(! empty($afterBooking['booking_detour_active']));
        $pendingStill = $gate->pendingSnapshot($conv->fresh());
        $this->assertNotNull($pendingStill);
        $this->assertSame(2, (int) ($pendingStill['adults'] ?? 0));

        $resume = $this->chat($vid, 'back to my Dubai search', $conv->public_id);
        $resume['response']->assertOk();
        $afterResume = $this->reloadState($conv->public_id);
        $this->assertSame('LHE', $afterResume['origin'] ?? null);
        $this->assertSame('DXB', $afterResume['destination'] ?? null);
        $this->assertSame(2, (int) ($afterResume['adults'] ?? 0));
        $this->assertEmpty($afterResume['booking_detour_active'] ?? null);
        $this->assertTrue((bool) $resume['response']->json('requires_confirmation')
            || $resume['response']->json('status') === 'confirm');
        $this->assertSame($before['origin'] ?? null, $afterResume['origin'] ?? null);
        $this->assertSame($before['adults'] ?? null, $afterResume['adults'] ?? null);
    }

    public function test_booking_public_contract_not_found_and_ok_payload(): void
    {
        $this->enableSemanticAi([
            'ota.ai_assistant.conversational_enabled' => false,
            'ota.ai_assistant.optional_llm_assist' => false,
            'ota.ai_assistant.semantic_planner_enabled' => false,
        ]);
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson(['operation' => 'clarify']),
        ]));

        $agency = \App\Models\Agency::factory()->create();
        $booking = \App\Models\Booking::factory()->create([
            'agency_id' => $agency->id,
            'booking_reference' => 'CQ43R1OK1',
            'status' => \App\Enums\BookingStatus::PaymentPending,
        ]);
        \App\Models\BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'cq43r1-ok@example.com',
            'phone' => '+923001112233',
        ]);

        $vid = str_repeat('cq43c', 8);

        // A. Incomplete
        $incomplete = $this->chat($vid, 'Check my booking');
        $incomplete['response']->assertOk()
            ->assertJsonPath('status', 'clarify')
            ->assertJsonPath('booking', null);
        $cid = $incomplete['conversation_id'];

        // B. Wrong verified details → not_found + booking=null
        $this->chat($vid, 'Reference CQ43R1OK1', $cid);
        $wrong = $this->chat($vid, 'My email is wrong@example.com', $cid);
        $wrong['response']->assertOk()
            ->assertJsonPath('status', 'not_found')
            ->assertJsonPath('booking', null);
        $this->assertStringNotContainsString('cq43r1-ok@example.com', (string) $wrong['response']->json('message'));

        // C. Successful verified lookup on a fresh conversation
        $vid2 = str_repeat('cq43d', 8);
        $ok1 = $this->chat($vid2, 'Look up my booking please');
        $cid2 = $ok1['conversation_id'];
        $this->chat($vid2, 'Reference CQ43R1OK1', $cid2);
        $ok = $this->chat($vid2, 'My email is cq43r1-ok@example.com', $cid2);
        $ok['response']->assertOk()->assertJsonPath('status', 'ok');
        $payload = $ok['response']->json('booking');
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('booking_reference', $payload);
        $this->assertSame('CQ43R1OK1', strtoupper((string) ($payload['booking_reference'] ?? '')));
        $this->assertArrayNotHasKey('found', $payload);
        $this->assertArrayNotHasKey('ok', $payload);
    }

    public function test_fresh_wapis_control_via_chat(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->supportHandoffPlan(),
        ]));

        $turn = $this->chat(str_repeat('cq43f', 8), 'Dubai se Lahore wapis');
        $turn['response']->assertOk();
        $state = $this->reloadState($turn['conversation_id']);
        $this->assertSame('DXB', $state['origin'] ?? null);
        $this->assertSame('LHE', $state['destination'] ?? null);
        $this->assertNotSame('return', $state['trip_type'] ?? null);
        $this->assertNotSame('open_jaw', $state['trip_type'] ?? null);
    }
}
