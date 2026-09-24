<?php

namespace Tests\Feature\Ai;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Contracts\Ai\InferenceProvider;
use App\Services\Ai\NullInferenceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * JP-AI-CONVERSATION-QUALITY-26 matrix: HELP-FIRST lead, open-domain, knowledge grounding, rate limits.
 */
class ConversationQuality26Test extends TestCase
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
            'ota.ai_assistant.flight_search_enabled' => true,
            'ota.ai_assistant.conversational_enabled' => false,
            'ota.ai_assistant.optional_llm_assist' => false,
            'ota.ai_assistant.knowledge_enabled' => true,
            'ota.ai_assistant.anonymous_per_minute' => 30,
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

    public function test_legitimate_undefined_and_null_user_messages_are_preserved(): void
    {
        $this->enablePublicAi();
        $cases = [
            'Undefined behavior in C',
            'Null hypothesis in statistics',
            'NULL values in SQL',
            'What does undefined mean?',
            'What is JetPakistan?',
            'I need help',
        ];

        foreach ($cases as $i => $message) {
            $vid = str_repeat('f'.(string) $i, 20);
            $turn = $this->chat($vid, $message);
            $turn['response']->assertOk();
            $cid = $turn['conversation_id'];
            $userBodies = AiMessage::query()
                ->whereHas('conversation', static fn ($q) => $q->where('public_id', $cid))
                ->where('role', 'user')
                ->pluck('body')
                ->all();
            $this->assertCount(1, $userBodies, 'message: '.$message);
            $this->assertSame($message, $userBodies[0], 'message: '.$message);
        }
    }

    public function test_artifact_coercion_strings_are_not_semantically_rewritten(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('a9', 20);

        // Server must not heuristically rewrite; UAT/UI must not send these artifacts.
        $turn = $this->chat($vid, 'undefinedWhat is JetPakistan?');
        $turn['response']->assertOk();
        $cid = $turn['conversation_id'];
        $userBodies = AiMessage::query()
            ->whereHas('conversation', static fn ($q) => $q->where('public_id', $cid))
            ->where('role', 'user')
            ->pluck('body')
            ->all();
        $this->assertSame(['undefinedWhat is JetPakistan?'], $userBodies);

        $dup = $this->chat($vid, 'I need helpI need help', $cid);
        $dup['response']->assertOk();
        $bodies = AiMessage::query()
            ->whereHas('conversation', static fn ($q) => $q->where('public_id', $cid))
            ->where('role', 'user')
            ->orderBy('id')
            ->pluck('body')
            ->all();
        $this->assertSame('I need helpI need help', $bodies[1] ?? null);
    }

    public function test_what_is_jetpakistan_grounded_knowledge(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('q2', 20);

        $turn = $this->chat($vid, 'What is JetPakistan?');
        $turn['response']->assertOk();
        $this->assertNotTrue($turn['response']->json('meta.lead_capture_pending'));
        $this->assertSame('what-is-jetpakistan', $turn['response']->json('meta.KNOWLEDGE_SOURCE'));
        $this->assertGreaterThanOrEqual(1, (int) $turn['response']->json('meta.KNOWLEDGE_HITS'));
        $this->assertSame('YES', $turn['response']->json('meta.ANSWER_GROUNDED'));
        $this->assertSame('FALLBACK_STRUCTURED', $turn['response']->json('meta.LLM_SYNTHESIS'));
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertStringContainsString('jetpakistan', $body);
        $this->assertStringContainsString('travel', $body);
        $this->assertStringNotContainsString("i don't have enough verified", $body);
    }

    public function test_general_knowledge_emc2_without_lead_or_refusal(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('q3', 20);

        $turn = $this->chat($vid, 'What is E = mc2?');
        $turn['response']->assertOk();
        $this->assertNotTrue($turn['response']->json('meta.lead_capture_pending'));
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertTrue(str_contains($body, 'energy') || str_contains($body, 'einstein') || str_contains($body, 'mass'));
        $this->assertStringNotContainsString('i can only answer travel', $body);
        $this->assertSame('GENERAL_KNOWLEDGE', $turn['response']->json('meta.open_domain_category'));
    }

    public function test_off_domain_private_jet_smart_redirect(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('q4', 20);

        $turn = $this->chat($vid, 'Where can I buy a jet?');
        $turn['response']->assertOk();
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertStringContainsString('doesn\'t sell', $body);
        $this->assertTrue(
            str_contains($body, 'flight')
            || str_contains($body, 'group')
            || str_contains($body, 'travel')
            || str_contains($body, 'support')
        );
        $this->assertStringNotContainsString('aircraft marketplace', $body);
        $this->assertSame('FALLBACK_STRUCTURED', $turn['response']->json('meta.LLM_SYNTHESIS'));
        $this->assertSame('YES', $turn['response']->json('meta.SMART_REDIRECT'));
    }

    public function test_current_unverified_no_hallucination(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('q5', 20);

        $turn = $this->chat($vid, "What's Apple's stock price right now?");
        $turn['response']->assertOk();
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertStringContainsString('can\'t verify', $body);
        $this->assertDoesNotMatchRegularExpression('/\$\d+/', $body);
        $this->assertSame('CURRENT_UNVERIFIED', $turn['response']->json('meta.open_domain_category'));
    }

    public function test_casual_joke_no_lead_gate(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('q6', 20);

        $turn = $this->chat($vid, 'Tell me a joke.');
        $turn['response']->assertOk();
        $this->assertNotTrue($turn['response']->json('meta.lead_capture_pending'));
        $this->assertSame('CASUAL_CONVERSATION', $turn['response']->json('meta.open_domain_category'));
    }

    public function test_lead_not_blocking_travel_during_name_prompt(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('q7', 20);

        $first = $this->chat($vid, 'I need help');
        $cid = $first['conversation_id'];
        $first['response']->assertJsonPath('meta.lead_capture_pending', true);

        $travel = $this->chat($vid, 'I need Lahore to Dubai tomorrow for 2 adults', $cid);
        $travel['response']->assertOk();
        $body = mb_strtolower((string) $travel['response']->json('message'));
        $this->assertStringNotContainsString('doesn\'t look like a name', $body);
        $this->assertStringNotContainsString('may i start with your name', $body);

        $conversation = AiConversation::query()->where('public_id', $cid)->first();
        $this->assertTrue((bool) data_get($conversation?->shopping_state, 'lead_capture_pending'));
        $meta = $travel['response']->json('meta');
        $this->assertTrue(
            data_get($meta, 'intent.origin') === 'LHE'
            || data_get($meta, 'intent.destination') === 'DXB'
            || str_contains($body, 'dubai')
            || str_contains($body, 'lahore')
            || str_contains($body, 'confirm')
            || str_contains($body, 'date')
            || ! empty($travel['response']->json('recommendations'))
            || $travel['response']->json('status') === 'clarify'
            || $travel['response']->json('status') === 'ok'
        );
    }

    public function test_lead_not_blocking_knowledge_during_name_prompt(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('q8', 20);
        $cid = $this->chat($vid, 'I need help')['conversation_id'];

        $turn = $this->chat($vid, 'What is JetPakistan?', $cid);
        $turn['response']->assertOk();
        $this->assertSame('what-is-jetpakistan', $turn['response']->json('meta.KNOWLEDGE_SOURCE'));
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertStringNotContainsString('doesn\'t look like a name', $body);

        $conversation = AiConversation::query()->where('public_id', $cid)->first();
        $this->assertTrue((bool) data_get($conversation?->shopping_state, 'lead_capture_pending'));
    }

    public function test_lead_not_blocking_general_during_name_prompt(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('q9', 20);
        $cid = $this->chat($vid, 'I need help')['conversation_id'];

        $turn = $this->chat($vid, 'What is E = mc2?', $cid);
        $turn['response']->assertOk();
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertTrue(str_contains($body, 'energy') || str_contains($body, 'einstein') || str_contains($body, 'mass'));
        $this->assertStringNotContainsString('doesn\'t look like a name', $body);

        $conversation = AiConversation::query()->where('public_id', $cid)->first();
        $this->assertTrue((bool) data_get($conversation?->shopping_state, 'lead_capture_pending'));
    }

    public function test_name_and_intent_same_turn(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('qa', 20);
        $cid = $this->chat($vid, 'I need help')['conversation_id'];

        $turn = $this->chat($vid, "I'm Ahmed and I need Lahore to Dubai tomorrow for 2 adults.", $cid);
        $turn['response']->assertOk();

        $conversation = AiConversation::query()->where('public_id', $cid)->first();
        $this->assertSame('Ahmed', data_get($conversation?->shopping_state, 'lead_name'));
        $this->assertSame('LHE', data_get($conversation?->shopping_state, 'origin'));
        $this->assertSame('DXB', data_get($conversation?->shopping_state, 'destination'));
        $this->assertSame(2, (int) data_get($conversation?->shopping_state, 'adults'));
        $this->assertNotNull(data_get($conversation?->shopping_state, 'pending_flight_search_confirmation'));
        $this->assertTrue((bool) data_get($conversation?->shopping_state, 'lead_capture_pending'));
        $this->assertSame(0, (int) data_get($turn['response']->json(), 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $this->assertTrue(
            $turn['response']->json('status') === 'confirm'
            || (bool) data_get($turn['response']->json(), 'meta.CONFIRMATION_REQUIRED')
        );
    }

    public function test_contact_and_query_same_turn(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('qb', 20);
        $cid = $this->chat($vid, 'I need help')['conversation_id'];
        $this->chat($vid, 'Haseeb Asif', $cid);

        $turn = $this->chat($vid, 'My email is test@example.com, and what baggage is allowed?', $cid);
        $turn['response']->assertOk();
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertTrue(
            str_contains($body, 'baggage')
            || str_contains($body, 'kg')
            || str_contains($body, 'allowance')
            || (int) $turn['response']->json('meta.KNOWLEDGE_HITS') >= 1
        );
        $this->assertStringNotContainsString('now what\'s your phone', $body);

        $conversation = AiConversation::query()->where('public_id', $cid)->first();
        $this->assertSame('test@example.com', data_get($conversation?->shopping_state, 'lead_email'));
        $this->assertTrue((bool) data_get($conversation?->shopping_state, 'lead_capture_pending'));
    }

    public function test_consent_still_required_for_customer_query(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('qc', 20);
        $cid = $this->chat($vid, 'I need help')['conversation_id'];
        $this->chat($vid, 'Haseeb Asif', $cid);
        $this->chat($vid, 'lead@example.com 03001234567', $cid);

        $this->assertDatabaseCount('customer_queries', 0);
        $decline = $this->chat($vid, 'no thanks', $cid);
        $decline['response']->assertOk();
        $this->assertDatabaseCount('customer_queries', 0);
    }

    public function test_booking_lookup_still_requires_identity(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('qd', 20);

        $turn = $this->chat($vid, 'Can you look up ABC123?');
        $turn['response']->assertOk();
        $status = $turn['response']->json('status');
        $this->assertTrue(in_array($status, ['clarify', 'ok', 'not_found'], true));
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertTrue(
            str_contains($body, 'email')
            || str_contains($body, 'phone')
            || str_contains($body, 'verify')
            || str_contains($body, 'reference')
        );
    }

    public function test_topic_switch_from_physics_to_travel(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('qe', 20);
        $cid = $this->chat($vid, 'What is E = mc2?')['conversation_id'];

        $travel = $this->chat($vid, 'Anyway I need Dubai tomorrow.', $cid);
        $travel['response']->assertOk();
        $body = mb_strtolower((string) $travel['response']->json('message'));
        $this->assertStringNotContainsString('einstein', $body);
        $this->assertTrue(
            str_contains($body, 'dubai')
            || str_contains($body, 'origin')
            || str_contains($body, 'from')
            || str_contains($body, 'city')
            || str_contains($body, 'confirm')
            || $travel['response']->json('status') === 'clarify'
            || $travel['response']->json('status') === 'ok'
        );
    }

    public function test_twenty_normal_turns_no_429(): void
    {
        $this->enablePublicAi(['ota.ai_assistant.anonymous_per_minute' => 30]);
        $vid = str_repeat('qf', 20);
        RateLimiter::clear('ai-chat-send:'.hash('sha256', $vid));

        $cid = null;
        for ($i = 1; $i <= 20; $i++) {
            $turn = $this->chat($vid, "Hello turn {$i}", $cid);
            $turn['response']->assertOk();
            $cid = $turn['conversation_id'];
        }
    }

    public function test_abuse_burst_returns_429_with_retry_after(): void
    {
        $this->enablePublicAi(['ota.ai_assistant.anonymous_per_minute' => 5]);
        $vid = str_repeat('qg', 20);
        RateLimiter::clear('ai-chat-send:'.hash('sha256', $vid));

        for ($i = 1; $i <= 5; $i++) {
            $this->chat($vid, "burst {$i}")['response']->assertOk();
        }

        $limited = $this->withCookie('jp_ai_vid', $vid)
            ->postJson('/api/public/ai/chat', ['message' => 'burst overflow']);

        $limited->assertStatus(429)
            ->assertJsonPath('status', 'rate_limited')
            ->assertHeader('Retry-After');
        $this->assertGreaterThan(0, (int) $limited->json('retry_after'));
    }

    public function test_polling_does_not_consume_chat_send_budget(): void
    {
        $this->enablePublicAi(['ota.ai_assistant.anonymous_per_minute' => 3]);
        $vid = str_repeat('qh', 20);
        RateLimiter::clear('ai-chat-send:'.hash('sha256', $vid));

        $cid = $this->chat($vid, 'Hello')['conversation_id'];
        for ($i = 0; $i < 10; $i++) {
            $this->withCookie('jp_ai_vid', $vid)
                ->getJson('/api/public/ai/messages?conversation_id='.$cid.'&since_id=0')
                ->assertOk();
        }

        $this->chat($vid, 'Second', $cid)['response']->assertOk();
        $this->chat($vid, 'Third', $cid)['response']->assertOk();
    }
}
