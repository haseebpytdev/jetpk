<?php

namespace Tests\Feature\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Enums\CustomerQueryStatus;
use App\Models\AiConversation;
use App\Models\AiHandoffAudit;
use App\Models\CustomerQuery;
use App\Services\Ai\FlightSearchConfirmationGate;
use App\Services\Ai\NullInferenceProvider;
use App\Support\Ai\Embed\EmbedTenantCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Ai\ScriptedInferenceProvider;
use Tests\Support\InteractsWithEmbedTenants;
use Tests\TestCase;

/**
 * JP-AI-CQ26-PROD-CLOSURE-29: confirmation before read-only search + explicit handoff precedence.
 */
class FlightSearchConfirmationAndHandoffClosure29Test extends TestCase
{
    use InteractsWithEmbedTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
        Cache::flush();
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
            'ota.ai_assistant.hard_allow.human_handoff' => true,
            'ota.ai_assistant.flight_search_enabled' => true,
            'ota.ai_assistant.human_handoff_enabled' => true,
            'ota.ai_assistant.conversational_enabled' => false,
            'ota.ai_assistant.optional_llm_assist' => false,
            'ota.ai_assistant.knowledge_enabled' => true,
            'ai_lab.enabled' => false,
        ], $extra));

        \App\Models\AiAssistantSetting::query()->delete();
        app(\App\Services\Ai\AiAssistantSettingsService::class)->get();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);
    }

    private function seedLeadComplete(string $visitorId): void
    {
        CustomerQuery::query()->create([
            'visitor_token_hash' => hash('sha256', $visitorId),
            'name' => 'QA Guest',
            'email' => 'qa-guest@example.com',
            'phone_raw' => '03001234567',
            'phone_e164' => '+923001234567',
            'phone_country' => 'PK',
            'contact_consent' => true,
            'consent_timestamp' => now(),
            'consent_source' => 'ask_jetpakistan',
            'source' => 'ask_jetpakistan',
            'status' => CustomerQueryStatus::New,
            'last_activity_at' => now(),
        ]);
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

    public function test_complete_flight_intent_requires_confirmation_before_search(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('c1', 20);
        $this->seedLeadComplete($vid);

        $turn = $this->chat(
            $vid,
            'I need a one-way flight from Lahore to Dubai on 15 October for 2 adults'
        );
        $turn['response']->assertOk()
            ->assertJsonPath('status', 'confirm')
            ->assertJsonPath('meta.CONFIRMATION_REQUIRED', true)
            ->assertJsonPath('meta.confirmation_type', 'flight_search')
            ->assertJsonPath('meta.AI_FLIGHT_SEARCH_READ_CALLS', 0);

        $this->assertSame('LHE', data_get($turn['response']->json(), 'meta.intent.origin')
            ?? data_get($turn['response']->json(), 'confirmation_snapshot.origin')
            ?? data_get($turn['response']->json(), 'meta.CONFIRMATION_SNAPSHOT.origin'));
        $this->assertSame('DXB', data_get($turn['response']->json(), 'meta.intent.destination')
            ?? data_get($turn['response']->json(), 'confirmation_snapshot.destination')
            ?? data_get($turn['response']->json(), 'meta.CONFIRMATION_SNAPSHOT.destination'));
        $depart = (string) (
            data_get($turn['response']->json(), 'confirmation_snapshot.departure_date')
            ?? data_get($turn['response']->json(), 'meta.CONFIRMATION_SNAPSHOT.departure_date')
            ?? data_get($turn['response']->json(), 'meta.intent.depart_date')
            ?? ''
        );
        $this->assertMatchesRegularExpression('/^\d{4}-10-15$/', $depart);
        $this->assertSame(2, (int) (
            data_get($turn['response']->json(), 'confirmation_snapshot.adults')
            ?? data_get($turn['response']->json(), 'meta.CONFIRMATION_SNAPSHOT.adults')
            ?? data_get($turn['response']->json(), 'meta.intent.adults')
        ));
        $this->assertSame('one_way', data_get($turn['response']->json(), 'confirmation_snapshot.trip_type')
            ?? data_get($turn['response']->json(), 'meta.CONFIRMATION_SNAPSHOT.trip_type'));
        $this->assertEmpty($turn['response']->json('recommendations') ?? []);

        $conversation = AiConversation::query()->where('public_id', $turn['conversation_id'])->first();
        $this->assertNotNull(data_get($conversation?->shopping_state, FlightSearchConfirmationGate::STATE_KEY));
    }

    public function test_affirmative_executes_pending_confirmed_search_once(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('c2', 20);
        $this->seedLeadComplete($vid);

        $pending = $this->chat($vid, 'LHE to DXB tomorrow for 2 adults');
        $pending['response']->assertJsonPath('status', 'confirm');
        $cid = $pending['conversation_id'];

        $yes = $this->chat($vid, 'Yes, search', $cid);
        $yes['response']->assertOk()->assertJsonPath('status', 'ok');
        $this->assertSame(1, (int) data_get($yes['response']->json(), 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $this->assertTrue((bool) data_get($yes['response']->json(), 'meta.CONFIRMATION_BEFORE_SEARCH'));
        $this->assertNotEmpty($yes['response']->json('recommendations') ?? []);

        $conversation = AiConversation::query()->where('public_id', $cid)->first();
        $this->assertNull(data_get($conversation?->shopping_state, FlightSearchConfirmationGate::STATE_KEY));
    }

    public function test_flight_correction_invalidates_pending_confirmation(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('c3', 20);
        $this->seedLeadComplete($vid);

        $pending = $this->chat($vid, 'Lahore to Dubai on 15 October for 2 adults');
        $pending['response']->assertJsonPath('status', 'confirm');
        $cid = $pending['conversation_id'];

        $fix = $this->chat($vid, 'Actually make it 3 adults', $cid);
        $fix['response']->assertOk()
            ->assertJsonPath('status', 'confirm')
            ->assertJsonPath('meta.AI_FLIGHT_SEARCH_READ_CALLS', 0);

        $adults = (int) (
            data_get($fix['response']->json(), 'confirmation_snapshot.adults')
            ?? data_get($fix['response']->json(), 'meta.CONFIRMATION_SNAPSHOT.adults')
        );
        $this->assertSame(3, $adults);
        $this->assertTrue((bool) data_get($fix['response']->json(), 'meta.confirmation_invalidated'));

        $yes = $this->chat($vid, 'yes', $cid);
        $yes['response']->assertOk();
        $this->assertSame(1, (int) data_get($yes['response']->json(), 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $url = (string) data_get($yes['response']->json(), 'recommendations.0.view_and_book_url');
        $this->assertStringContainsString('adults=3', $url);
    }

    public function test_negative_clears_confirmation_without_search(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('c4', 20);
        $this->seedLeadComplete($vid);

        $pending = $this->chat($vid, 'LHE to DXB tomorrow for 2 adults');
        $cid = $pending['conversation_id'];

        $no = $this->chat($vid, 'cancel', $cid);
        $no['response']->assertOk();
        $this->assertSame(0, (int) data_get($no['response']->json(), 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $this->assertEmpty($no['response']->json('recommendations') ?? []);

        $conversation = AiConversation::query()->where('public_id', $cid)->first();
        $this->assertNull(data_get($conversation?->shopping_state, FlightSearchConfirmationGate::STATE_KEY));
    }

    public function test_consumed_confirmation_does_not_double_search(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('c5', 20);
        $this->seedLeadComplete($vid);

        $pending = $this->chat($vid, 'LHE to DXB tomorrow for 2 adults');
        $cid = $pending['conversation_id'];
        $this->chat($vid, 'Yes, search', $cid);

        $again = $this->chat($vid, 'yes', $cid);
        $again['response']->assertOk();
        $this->assertSame(0, (int) data_get($again['response']->json(), 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));
    }

    public function test_explicit_handoff_bypasses_fresh_lead_capture(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('h1', 20);

        $turn = $this->chat($vid, 'Talk to support');
        $turn['response']->assertOk()
            ->assertJsonPath('status', 'waiting_for_human')
            ->assertJsonPath('state', AiConversation::STATE_WAITING_FOR_HUMAN);

        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertStringNotContainsString('may i start with your name', $body);
        $this->assertStringNotContainsString("doesn't look like a name", $body);
    }

    public function test_explicit_handoff_bypasses_pending_name_capture(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('h2', 20);

        $first = $this->chat($vid, 'I need help');
        $first['response']->assertJsonPath('meta.lead_capture_pending', true);
        $cid = $first['conversation_id'];

        $handoff = $this->chat($vid, 'Talk to support', $cid);
        $handoff['response']->assertOk()
            ->assertJsonPath('status', 'waiting_for_human')
            ->assertJsonPath('state', AiConversation::STATE_WAITING_FOR_HUMAN);

        $body = mb_strtolower((string) $handoff['response']->json('message'));
        $this->assertStringNotContainsString('may i start with your name', $body);
        $this->assertStringNotContainsString("doesn't look like a name", $body);
    }

    public function test_handoff_followup_stays_human_queue(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('h3', 20);

        $handoff = $this->chat($vid, 'Talk to support');
        $cid = $handoff['conversation_id'];
        $handoff['response']->assertJsonPath('status', 'waiting_for_human');

        $follow = $this->chat($vid, 'Hello?', $cid);
        $follow['response']->assertOk()
            ->assertJsonPath('status', 'waiting_for_human')
            ->assertJsonPath('mode', 'HUMAN_QUEUE');
        $this->assertStringNotContainsString(
            'may i start with your name',
            mb_strtolower((string) $follow['response']->json('message'))
        );
    }

    public function test_name_and_travel_intent_both_persist_before_confirmation(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('n1', 20);
        $cid = $this->chat($vid, 'I need help')['conversation_id'];

        $turn = $this->chat($vid, "I'm Ahmed and I need Lahore to Dubai tomorrow for 2 adults", $cid);
        $turn['response']->assertOk();

        $conversation = AiConversation::query()->where('public_id', $cid)->first();
        $this->assertSame('Ahmed', data_get($conversation?->shopping_state, 'lead_name'));
        $this->assertSame('LHE', data_get($conversation?->shopping_state, 'origin'));
        $this->assertSame('DXB', data_get($conversation?->shopping_state, 'destination'));
        $this->assertSame(2, (int) data_get($conversation?->shopping_state, 'adults'));
        $this->assertNotNull(data_get($conversation?->shopping_state, FlightSearchConfirmationGate::STATE_KEY));
        $this->assertSame(0, (int) data_get($turn['response']->json(), 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));
        $this->assertTrue(
            $turn['response']->json('status') === 'confirm'
            || (bool) data_get($turn['response']->json(), 'meta.CONFIRMATION_REQUIRED')
        );

        $yes = $this->chat($vid, 'Yes', $cid);
        $this->assertSame(1, (int) data_get($yes['response']->json(), 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));
    }

    public function test_generic_embed_without_support_handoff_capability_blocks_chat_handoff(): void
    {
        $this->enableEmbedTenant(
            slug: 'client-b',
            embedKey: 'client-b-embed-key12',
            allowedOrigins: ['https://client-b.example.com'],
            capabilities: [
                EmbedTenantCapability::GENERAL_AI,
                EmbedTenantCapability::KNOWLEDGE,
            ],
        );
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);

        $session = $this->postJson($this->embedApiPath('client-b-embed-key12', '/session'), [], [
            'X-JP-AI-Embed-Parent-Origin' => 'https://client-b.example.com',
        ])->assertOk();
        $headers = [
            'X-JP-AI-Embed-Session' => (string) $session->json('token'),
            'X-JP-AI-Embed-Parent-Origin' => 'https://client-b.example.com',
        ];

        $response = $this->postJson($this->embedApiPath('client-b-embed-key12', '/chat'), [
            'message' => 'Talk to support',
        ], $headers);

        $response->assertOk();
        $this->assertNotSame('waiting_for_human', $response->json('status'));
        $this->assertNotSame(AiConversation::STATE_WAITING_FOR_HUMAN, $response->json('state'));
        $this->assertStringContainsString('capability', mb_strtolower((string) $response->json('message')));
    }

    public function test_generic_embed_with_support_handoff_uses_tenant_safe_handoff(): void
    {
        // JetPakistan embed has a live SupportHandoffProvider; generic tenants with the
        // capability alone still resolve DisabledHandoffProvider (tenant isolation).
        $this->enableEmbedTenant(
            slug: 'jetpakistan',
            embedKey: 'jp-embed-handoff-key12',
            allowedOrigins: ['https://embed.jetpakistan.pk'],
        );
        config(['ota.ai_assistant.human_handoff_enabled' => true]);
        \App\Models\AiAssistantSetting::query()->delete();
        app(\App\Services\Ai\AiAssistantSettingsService::class)->get();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);

        $session = $this->postJson($this->embedApiPath('jp-embed-handoff-key12', '/session'), [], [
            'X-JP-AI-Embed-Parent-Origin' => 'https://embed.jetpakistan.pk',
        ])->assertOk();
        $headers = [
            'X-JP-AI-Embed-Session' => (string) $session->json('token'),
            'X-JP-AI-Embed-Parent-Origin' => 'https://embed.jetpakistan.pk',
        ];

        $response = $this->postJson($this->embedApiPath('jp-embed-handoff-key12', '/chat'), [
            'message' => 'Talk to support',
        ], $headers);

        $response->assertOk()
            ->assertJsonPath('status', 'waiting_for_human')
            ->assertJsonPath('state', AiConversation::STATE_WAITING_FOR_HUMAN);

        $actions = $response->json('actions') ?? [];
        $this->assertIsArray($actions);
    }

    public function test_llm_human_handoff_tool_blocked_for_generic_embed_without_provider(): void
    {
        $this->enableEmbedTenant(
            slug: 'client-b',
            embedKey: 'client-b-llm-handoff-key12',
            allowedOrigins: ['https://client-b.example.com'],
            capabilities: [
                EmbedTenantCapability::GENERAL_AI,
            ],
        );
        config([
            'ota.ai_assistant.conversational_enabled' => true,
            'ota.ai_assistant.human_handoff_enabled' => true,
            'ota.ai_assistant.hard_allow.human_handoff' => true,
            'ai_lab.enabled' => false,
        ]);
        \App\Models\AiAssistantSetting::query()->delete();
        app(\App\Services\Ai\AiAssistantSettingsService::class)->get();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider(
            '{"action":"tool","tool":"human_handoff","args":{}}'
        ));

        $session = $this->postJson($this->embedApiPath('client-b-llm-handoff-key12', '/session'), [], [
            'X-JP-AI-Embed-Parent-Origin' => 'https://client-b.example.com',
        ])->assertOk();
        $headers = [
            'X-JP-AI-Embed-Session' => (string) $session->json('token'),
            'X-JP-AI-Embed-Parent-Origin' => 'https://client-b.example.com',
        ];

        $beforeAudits = AiHandoffAudit::query()->count();
        $response = $this->postJson($this->embedApiPath('client-b-llm-handoff-key12', '/chat'), [
            'message' => 'Please escalate my request to someone who can help further.',
        ], $headers);

        $response->assertOk();
        $this->assertNotSame('waiting_for_human', $response->json('status'));
        $this->assertNotSame(AiConversation::STATE_WAITING_FOR_HUMAN, $response->json('state'));
        $this->assertSame($beforeAudits, AiHandoffAudit::query()->count());
        $this->assertStringContainsString('capability', mb_strtolower((string) $response->json('message')));
    }

    public function test_llm_human_handoff_tool_allowed_for_jetpakistan_embed_with_provider(): void
    {
        $tenant = $this->enableEmbedTenant(
            slug: 'jetpakistan',
            embedKey: 'jp-llm-handoff-key12',
            allowedOrigins: ['https://embed.jetpakistan.pk'],
        );
        config([
            'ota.ai_assistant.conversational_enabled' => true,
            'ota.ai_assistant.human_handoff_enabled' => true,
            'ota.ai_assistant.hard_allow.human_handoff' => true,
            'ai_lab.enabled' => false,
        ]);
        \App\Models\AiAssistantSetting::query()->delete();
        app(\App\Services\Ai\AiAssistantSettingsService::class)->get();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider(
            '{"action":"tool","tool":"human_handoff","args":{}}'
        ));

        $session = $this->postJson($this->embedApiPath('jp-llm-handoff-key12', '/session'), [], [
            'X-JP-AI-Embed-Parent-Origin' => 'https://embed.jetpakistan.pk',
        ])->assertOk();
        $token = (string) $session->json('token');
        $headers = [
            'X-JP-AI-Embed-Session' => $token,
            'X-JP-AI-Embed-Parent-Origin' => 'https://embed.jetpakistan.pk',
        ];

        $sessionPayload = Cache::get('ai_embed_sess:'.hash('sha256', $token));
        $visitorHash = hash('sha256', (string) ($sessionPayload['visitor_raw'] ?? ''));
        CustomerQuery::query()->create([
            'visitor_token_hash' => $visitorHash,
            'ai_embed_tenant_id' => $tenant->id,
            'name' => 'JP Embed Guest',
            'email' => 'jp-embed-guest@example.com',
            'phone_raw' => '03001234567',
            'phone_e164' => '+923001234567',
            'phone_country' => 'PK',
            'contact_consent' => true,
            'consent_timestamp' => now(),
            'consent_source' => 'ask_jetpakistan',
            'source' => 'ask_jetpakistan',
            'status' => CustomerQueryStatus::New,
            'last_activity_at' => now(),
        ]);

        $beforeAudits = AiHandoffAudit::query()->count();
        $response = $this->postJson($this->embedApiPath('jp-llm-handoff-key12', '/chat'), [
            'message' => 'Please escalate my request to someone who can help further.',
        ], $headers);

        $response->assertOk()
            ->assertJsonPath('status', 'waiting_for_human')
            ->assertJsonPath('state', AiConversation::STATE_WAITING_FOR_HUMAN);

        $this->assertSame($beforeAudits + 1, AiHandoffAudit::query()->count());
        $cid = (string) $response->json('conversation_id');
        $conversation = AiConversation::query()->where('public_id', $cid)->first();
        $this->assertNotNull($conversation);
        $this->assertSame(1, AiHandoffAudit::query()->where('ai_conversation_id', $conversation->id)->count());
    }
}
