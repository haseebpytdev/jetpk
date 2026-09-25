<?php

namespace Tests\Feature\Ai;

use App\Models\AiConversation;
use App\Models\AiHandoffAudit;
use App\Contracts\Ai\InferenceProvider;
use App\Services\Ai\NullInferenceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * JP-AI-CQ27-CONVERSATIONAL-QUALITY-RECOVERY-33
 * Screenshot-derived mobile transcript regressions (synthetic identity only).
 */
class ConversationQuality27RecoveryTest extends TestCase
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
    private function enablePublicAi(array $extra = []): void
    {
        config(array_merge([
            'ota.ai_assistant.mode' => 'public',
            'ota.ai_assistant.enabled' => true,
            'ota.ai_assistant.hard_allow.master' => true,
            'ota.ai_assistant.hard_allow.public' => true,
            'ota.ai_assistant.hard_allow.lab_adapter' => false,
            'ota.ai_assistant.hard_allow.human_handoff' => true,
            'ota.ai_assistant.flight_search_enabled' => true,
            'ota.ai_assistant.conversational_enabled' => false,
            'ota.ai_assistant.optional_llm_assist' => false,
            'ota.ai_assistant.knowledge_enabled' => true,
            'ota.ai_assistant.human_handoff_enabled' => true,
            'ota.ai_assistant.anonymous_per_minute' => 60,
            'ai_lab.enabled' => false,
            'ai_lab.canary_only' => false,
        ], $extra));

        \App\Models\AiAssistantSetting::query()->delete();
        app(\App\Services\Ai\AiAssistantSettingsService::class)->get();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);
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

    private function conversation(string $publicId): AiConversation
    {
        return AiConversation::query()->where('public_id', $publicId)->firstOrFail();
    }

    public function test_a_open_jaw_lahore_jeddah_medina_lahore_not_one_way(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('a1', 20);

        $turn = $this->chat(
            $vid,
            'I want to go from Lahore to Jeddah and then come back from Medina to Lahore'
        );
        $turn['response']->assertOk();

        $meta = $turn['response']->json('meta') ?? [];
        $this->assertSame('YES', $meta['OPEN_JAW_DETECTED'] ?? null);
        $this->assertSame('LHE-JED', $meta['LEG1'] ?? null);
        $this->assertSame('MED-LHE', $meta['LEG2'] ?? null);
        $this->assertSame('NO', $meta['FALSE_ONE_WAY'] ?? null);
        $this->assertSame(0, (int) ($meta['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0));

        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertTrue(
            str_contains($body, 'multi-city')
            || str_contains($body, 'multi city')
            || str_contains($body, 'open-jaw')
            || str_contains($body, 'open jaw'),
            'Should explain multi-city/open-jaw itinerary'
        );

        $conv = $this->conversation($turn['conversation_id']);
        $state = is_array($conv->shopping_state) ? $conv->shopping_state : [];
        $legs = $state['legs'] ?? null;
        $this->assertIsArray($legs);
        $this->assertCount(2, $legs);
        $this->assertSame('LHE', $legs[0]['origin'] ?? null);
        $this->assertSame('JED', $legs[0]['destination'] ?? null);
        $this->assertSame('MED', $legs[1]['origin'] ?? null);
        $this->assertSame('LHE', $legs[1]['destination'] ?? null);
        $this->assertNotSame('one_way', $state['trip_type'] ?? 'one_way');
        // Must not collapse to MED → LHE one-way searchable confirmation.
        $this->assertNotSame('confirm', $turn['response']->json('status'));
        $snap = $turn['response']->json('confirmation_snapshot');
        if (is_array($snap)) {
            $this->assertFalse(
                ($snap['origin'] ?? null) === 'MED'
                && ($snap['destination'] ?? null) === 'LHE'
                && ($snap['trip_type'] ?? null) === 'one_way'
            );
        }
    }

    public function test_b_explicit_route_overrides_stale_med_lhe_state(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('b2', 20);

        $seed = $this->chat($vid, 'Flights from Medina to Lahore on 10 November 2026');
        $seed['response']->assertOk();
        $cid = $seed['conversation_id'];

        $turn = $this->chat($vid, "What's the price of a one way ticket to Doha from Lahore?", $cid);
        $turn['response']->assertOk();

        $this->assertSame('PASS', $turn['response']->json('meta.EXPLICIT_ROUTE_PRECEDENCE'));
        $this->assertSame(0, (int) ($turn['response']->json('meta.STALE_ROUTE_CONTAMINATION') ?? -1));

        $conv = $this->conversation($cid);
        $state = is_array($conv->shopping_state) ? $conv->shopping_state : [];
        $this->assertSame('LHE', $state['origin'] ?? null);
        $this->assertSame('DOH', $state['destination'] ?? null);
        $this->assertNotSame('MED', $state['origin'] ?? null);
    }

    public function test_c_date_required_before_search_no_silent_fallback(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('c3', 20);

        $turn = $this->chat($vid, "What's the price of a one way ticket to Doha from Lahore?");
        $turn['response']->assertOk();
        $this->assertSame(0, (int) ($turn['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS') ?? 0));
        $this->assertNotSame('confirm', $turn['response']->json('status'));

        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertTrue(str_contains($body, 'date') || str_contains($body, 'when'), 'Should ask for travel date');
        $this->assertStringNotContainsString('your selected date', $body);

        $conv = $this->conversation($turn['conversation_id']);
        $state = is_array($conv->shopping_state) ? $conv->shopping_state : [];
        $this->assertSame('LHE', $state['origin'] ?? null);
        $this->assertSame('DOH', $state['destination'] ?? null);
        $this->assertTrue(($state['trip_type'] ?? 'one_way') === 'one_way' || ($state['return_date'] ?? null) === null);

        $dated = $this->chat($vid, '5 November 2026', $turn['conversation_id']);
        $dated['response']->assertOk()->assertJsonPath('status', 'confirm');
        $snap = $dated['response']->json('confirmation_snapshot')
            ?? $dated['response']->json('meta.CONFIRMATION_SNAPSHOT');
        $this->assertSame('2026-11-05', $snap['departure_date'] ?? null);
        $this->assertSame('LHE', $snap['origin'] ?? null);
        $this->assertSame('DOH', $snap['destination'] ?? null);
        $confirmBody = mb_strtolower((string) $dated['response']->json('message'));
        $this->assertStringNotContainsString('your selected date', $confirmBody);
    }

    public function test_d_business_cabin_persists_in_confirmation_and_deeplink(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('d4', 20);

        $turn = $this->chat($vid, 'I need business class from Lahore to Doha on 5 November 2026');
        $turn['response']->assertOk()->assertJsonPath('status', 'confirm');

        $snap = $turn['response']->json('confirmation_snapshot')
            ?? $turn['response']->json('meta.CONFIRMATION_SNAPSHOT');
        $this->assertSame('business', $snap['cabin'] ?? null);
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertStringContainsString('business', $body);

        $yes = $this->chat($vid, 'Yes', $turn['conversation_id']);
        $yes['response']->assertOk();
        $this->assertSame(1, (int) ($yes['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS') ?? 0));
        $recs = $yes['response']->json('recommendations') ?? [];
        $url = (string) (($recs[0]['results_url'] ?? $recs[0]['view_and_book_url'] ?? ''));
        $this->assertStringContainsString('cabin=business', $url);
    }

    public function test_e_business_fare_during_lead_pending_helps_first(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('e5', 20);

        // Seed a searchable flight context then force lead-pending shopping interruption scenario.
        $seed = $this->chat($vid, 'Lahore to Doha on 5 November 2026');
        $seed['response']->assertOk();
        $cid = $seed['conversation_id'];

        $conv = $this->conversation($cid);
        $state = is_array($conv->shopping_state) ? $conv->shopping_state : [];
        $state['lead_capture_pending'] = true;
        $state['lead_capture_stage'] = 'ask_email';
        $state['lead_name'] = 'Test Traveler';
        $conv->shopping_state = $state;
        $conv->save();

        $turn = $this->chat($vid, 'Can you tell me about business class fare?', $cid);
        $turn['response']->assertOk();
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertStringNotContainsString('email', $body);
        $this->assertStringNotContainsString('phone', $body);
        $this->assertTrue(
            str_contains($body, 'business')
            || str_contains($body, 'cabin')
            || str_contains($body, 'confirm')
            || str_contains($body, 'search'),
            'Travel task should continue'
        );
    }

    public function test_f_is_it_business_class_uses_active_search_context(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('f6', 20);

        $confirm = $this->chat($vid, 'Business class from Lahore to Doha on 5 November 2026');
        $confirm['response']->assertOk()->assertJsonPath('status', 'confirm');
        $cid = $confirm['conversation_id'];

        $yes = $this->chat($vid, 'Yes search', $cid);
        $yes['response']->assertOk();
        $this->assertSame(1, (int) ($yes['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS') ?? 0));

        $follow = $this->chat($vid, 'Is it business class?', $cid);
        $follow['response']->assertOk();
        $body = mb_strtolower((string) $follow['response']->json('message'));
        $this->assertTrue(str_contains($body, 'yes') || str_contains($body, 'business'));
        $this->assertNotSame('GENERAL_KNOWLEDGE', $follow['response']->json('meta.open_domain_category'));
        $this->assertNotSame('CASUAL_CONVERSATION', $follow['response']->json('meta.open_domain_category'));
    }

    public function test_g_affirmative_without_pending_action_does_not_execute_search(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('g7', 20);

        $turn = $this->chat($vid, 'Sure go ahead');
        $turn['response']->assertOk();
        $this->assertSame(0, (int) ($turn['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS') ?? 0));
        $this->assertNotSame('confirm', $turn['response']->json('status'));
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertFalse(str_contains($body, 'shall i search'));
        $this->assertSame('PASS', $turn['response']->json('meta.AFFIRMATIVE_WITHOUT_PENDING_ACTION_NO_EXECUTION'));
    }

    public function test_h_current_weather_no_hallucination_no_route_contamination(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('h8', 20);

        $seed = $this->chat($vid, 'Lahore to Jeddah on 5 November 2026');
        $seed['response']->assertOk();
        $cid = $seed['conversation_id'];

        $turn = $this->chat($vid, "Can you tell me what's the weather situation in Saudi Arabia right now?", $cid);
        $turn['response']->assertOk();
        $this->assertSame('CURRENT_UNVERIFIED', $turn['response']->json('meta.open_domain_category'));
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertTrue(
            str_contains($body, 'can\'t verify')
            || str_contains($body, 'cannot verify')
            || str_contains($body, 'don\'t have')
            || str_contains($body, 'live weather')
            || str_contains($body, 'current'),
            'Should return current-unverified limitation'
        );
        $this->assertDoesNotMatchRegularExpression('/\b\d{1,3}\s*°\s*[cf]\b/i', $body);
        $this->assertSame(0, (int) ($turn['response']->json('meta.FLIGHT_STATE_CONTAMINATION') ?? 0));
        $this->assertNotEmpty((string) $turn['response']->json('message'));
    }

    public function test_i_waiting_for_human_ordinary_message_stays_queued(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('i9', 20);

        $handoff = $this->chat($vid, 'Talk to support');
        $handoff['response']->assertOk()
            ->assertJsonPath('state', AiConversation::STATE_WAITING_FOR_HUMAN);
        $cid = $handoff['conversation_id'];

        $follow = $this->chat($vid, 'Hello is anyone there?', $cid);
        $follow['response']->assertOk()
            ->assertJsonPath('status', 'waiting_for_human')
            ->assertJsonPath('state', AiConversation::STATE_WAITING_FOR_HUMAN);
        $this->assertSame('PASS', $follow['response']->json('meta.WAITING_MESSAGE_STAYS_HUMAN_QUEUE'));
    }

    public function test_j_explicit_resume_ai_returns_to_ai_active(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('j0', 20);

        $handoff = $this->chat($vid, 'Talk to support');
        $cid = $handoff['conversation_id'];
        $handoff['response']->assertJsonPath('state', AiConversation::STATE_WAITING_FOR_HUMAN);
        $beforeAudits = AiHandoffAudit::query()->whereHas('conversation', static fn ($q) => $q->where('public_id', $cid))->count();

        $resume = $this->withCookie('jp_ai_vid', $vid)
            ->postJson('/api/public/ai/resume', ['conversation_id' => $cid]);
        $resume->assertOk()
            ->assertJsonPath('state', AiConversation::STATE_AI_ACTIVE)
            ->assertJsonPath('meta.RESUME_AI_EXPLICIT', 'PASS');

        $afterAudits = AiHandoffAudit::query()->whereHas('conversation', static fn ($q) => $q->where('public_id', $cid))->count();
        $this->assertSame($beforeAudits + 1, $afterAudits);
        $this->assertSame(0, (int) ($resume->json('meta.HANDOFF_AUDIT_DUPLICATES') ?? 0));

        // Implicit ordinary chat must not resume.
        $again = $this->chat($vid, 'Talk to support', $cid);
        $again['response']->assertJsonPath('state', AiConversation::STATE_WAITING_FOR_HUMAN);
        $implicit = $this->chat($vid, 'ok thanks', $cid);
        $implicit['response']->assertJsonPath('state', AiConversation::STATE_WAITING_FOR_HUMAN);
        $this->assertSame('NO', $implicit['response']->json('meta.RESUME_AI_IMPLICIT') ?? 'NO');
    }

    public function test_j_resume_ai_phrase_in_chat_is_explicit(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('jp', 20);

        $handoff = $this->chat($vid, 'Talk to support');
        $cid = $handoff['conversation_id'];

        $resume = $this->chat($vid, 'Resume AI', $cid);
        $resume['response']->assertOk()
            ->assertJsonPath('state', AiConversation::STATE_AI_ACTIVE);
        $this->assertSame('PASS', $resume['response']->json('meta.RESUME_AI_EXPLICIT'));
    }

    public function test_k_concurrent_chat_serialization_guard(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('k1', 20);

        $first = $this->chat($vid, 'Lahore to Doha on 5 November 2026');
        $first['response']->assertOk();
        $cid = $first['conversation_id'];

        $lock = Cache::lock('ai:chat:write:'.$cid, 10);
        $this->assertTrue($lock->get());

        try {
            $blocked = $this->withCookie('jp_ai_vid', $vid)
                ->postJson('/api/public/ai/chat', [
                    'message' => 'Make that business class',
                    'conversation_id' => $cid,
                ]);
            $blocked->assertStatus(409);
            $this->assertSame('conflict', $blocked->json('status'));
        } finally {
            $lock->release();
        }

        $retry = $this->chat($vid, 'Make that business class', $cid);
        $retry['response']->assertOk();
        $this->assertNotSame('conflict', $retry['response']->json('status'));
    }
}
