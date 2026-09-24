<?php

namespace Tests\Feature\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Models\AiMessage;
use App\Services\Ai\NullInferenceProvider;
use App\Support\Ai\Embed\EmbedTenantCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Ai\ScriptedInferenceProvider;
use Tests\Support\InteractsWithEmbedTenants;
use Tests\TestCase;

/**
 * R3: prove conversational model is primary for open-domain / RAG synthesis;
 * OpenDomainResponseService is fallback-only.
 */
class ConversationQuality26AiFirstTest extends TestCase
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
            'ota.ai_assistant.hard_allow.lab_adapter' => false,
            'ota.ai_assistant.flight_search_enabled' => true,
            'ota.ai_assistant.conversational_enabled' => true,
            'ota.ai_assistant.optional_llm_assist' => false,
            'ota.ai_assistant.knowledge_enabled' => true,
            'ota.ai_assistant.anonymous_per_minute' => 30,
            'ai_lab.enabled' => false,
            'ai_lab.canary_only' => false,
        ], $extra));

        \App\Models\AiAssistantSetting::query()->delete();
        app(\App\Services\Ai\AiAssistantSettingsService::class)->get();
    }

    private function chat(string $visitorId, string $message): \Illuminate\Testing\TestResponse
    {
        return $this->withCookie('jp_ai_vid', $visitorId)
            ->postJson('/api/public/ai/chat', ['message' => $message]);
    }

    public function test_general_knowledge_uses_model_when_healthy(): void
    {
        $this->enablePublicAi();
        $scripted = new ScriptedInferenceProvider(
            '{"message":"MODEL_EMC2: mass and energy are equivalent forms of the same physical quantity."}'
        );
        $this->app->instance(InferenceProvider::class, $scripted);

        $response = $this->chat(str_repeat('m1', 20), 'What is E = mc2?');
        $response->assertOk();
        $body = (string) $response->json('message');
        $this->assertStringContainsString('MODEL_EMC2', $body);
        $this->assertStringNotContainsString("Einstein's mass-energy equivalence: mass and energy are two forms of the same thing", $body);
        $this->assertSame('YES', $response->json('meta.LLM_SYNTHESIS'));
        $this->assertSame('GENERAL_KNOWLEDGE', $response->json('meta.open_domain_category'));
        $this->assertSame('LLM_ASSISTED', $response->json('mode'));
        $this->assertGreaterThanOrEqual(1, $scripted->callCount());
    }

    public function test_terse_educational_topics_route_open_domain_without_mutating_user_text(): void
    {
        $this->enablePublicAi();
        $scripted = new ScriptedInferenceProvider(
            '{"message":"MODEL_UB: Undefined behavior means the C standard does not define the result."}'
        );
        $this->app->instance(InferenceProvider::class, $scripted);

        $message = 'Undefined behavior in C?';
        $response = $this->chat(str_repeat('t9', 20), $message);
        $response->assertOk();
        $this->assertStringContainsString('MODEL_UB', (string) $response->json('message'));
        $this->assertSame('GENERAL_KNOWLEDGE', $response->json('meta.open_domain_category'));
        $this->assertSame('YES', $response->json('meta.LLM_SYNTHESIS'));

        $stored = AiMessage::query()
            ->where('role', 'user')
            ->orderByDesc('id')
            ->value('body');
        $this->assertSame($message, $stored);
    }

    public function test_general_knowledge_falls_back_when_model_unavailable(): void
    {
        $this->enablePublicAi(['ota.ai_assistant.conversational_enabled' => false]);
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);

        $response = $this->chat(str_repeat('m2', 20), 'What is E = mc2?');
        $response->assertOk();
        $body = mb_strtolower((string) $response->json('message'));
        $this->assertTrue(str_contains($body, 'energy') || str_contains($body, 'einstein') || str_contains($body, 'mass'));
        $this->assertSame('FALLBACK_STRUCTURED', $response->json('meta.LLM_SYNTHESIS'));
        $this->assertSame('STRUCTURED_FALLBACK', $response->json('mode'));
    }

    public function test_out_of_domain_uses_model_tenant_aware_redirect(): void
    {
        $this->enablePublicAi();
        $scripted = new ScriptedInferenceProvider(
            '{"message":"MODEL_JET: JetPakistan does not sell aircraft — I can help search commercial flights or group travel instead."}'
        );
        $this->app->instance(InferenceProvider::class, $scripted);

        $response = $this->chat(str_repeat('m3', 20), 'Where can I buy a jet?');
        $response->assertOk();
        $this->assertStringContainsString('MODEL_JET', (string) $response->json('message'));
        $this->assertSame('YES', $response->json('meta.LLM_SYNTHESIS'));
        $this->assertSame('OUT_OF_DOMAIN_SAFE', $response->json('meta.open_domain_category'));
    }

    public function test_casual_conversation_uses_model_when_healthy(): void
    {
        $this->enablePublicAi();
        $scripted = new ScriptedInferenceProvider(
            '{"message":"MODEL_JOKE: Two suitcases walk into a bar — only one had baggage issues."}'
        );
        $this->app->instance(InferenceProvider::class, $scripted);

        $response = $this->chat(str_repeat('m4', 20), 'Tell me a joke.');
        $response->assertOk();
        $this->assertStringContainsString('MODEL_JOKE', (string) $response->json('message'));
        $this->assertSame('YES', $response->json('meta.LLM_SYNTHESIS'));
        $this->assertSame('CASUAL_CONVERSATION', $response->json('meta.open_domain_category'));
        $this->assertStringNotContainsString('Why did the suitcase break up', (string) $response->json('message'));
    }

    public function test_current_unverified_rejects_fabricated_live_answer_from_model(): void
    {
        $this->enablePublicAi();
        $scripted = new ScriptedInferenceProvider(
            '{"message":"Apple is currently trading at $189.42 right now.","can_verify_live":false}'
        );
        $this->app->instance(InferenceProvider::class, $scripted);

        $response = $this->chat(str_repeat('m5', 20), "What's Apple's stock price right now?");
        $response->assertOk();
        $body = mb_strtolower((string) $response->json('message'));
        $this->assertDoesNotMatchRegularExpression('/\$\s?189/', $body);
        $this->assertStringContainsString('can\'t verify', $body);
        $this->assertSame('FALLBACK_STRUCTURED', $response->json('meta.LLM_SYNTHESIS'));
        $this->assertSame('CURRENT_UNVERIFIED', $response->json('meta.open_domain_category'));
    }

    public function test_current_unverified_rejects_qualitative_stock_claim(): void
    {
        $this->enablePublicAi();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider(
            '{"message":"Apple is trading higher today.","can_verify_live":false}'
        ));

        $response = $this->chat(str_repeat('c1', 20), "What's Apple's stock price right now?");
        $response->assertOk();
        $this->assertSame('FALLBACK_STRUCTURED', $response->json('meta.LLM_SYNTHESIS'));
        $this->assertStringNotContainsString('trading higher', mb_strtolower((string) $response->json('message')));
    }

    public function test_current_unverified_rejects_qualitative_weather_claim(): void
    {
        $this->enablePublicAi();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider(
            '{"message":"It\'s raining in London right now.","can_verify_live":false}'
        ));

        $response = $this->chat(str_repeat('c2', 20), "What's the weather today in London?");
        $response->assertOk();
        $this->assertSame('FALLBACK_STRUCTURED', $response->json('meta.LLM_SYNTHESIS'));
        $this->assertStringNotContainsString('raining', mb_strtolower((string) $response->json('message')));
    }

    public function test_current_unverified_rejects_qualitative_sports_claim(): void
    {
        $this->enablePublicAi();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider(
            '{"message":"Arsenal is leading at the moment.","can_verify_live":false}'
        ));

        $response = $this->chat(str_repeat('c3', 20), 'Who is winning the live match?');
        $response->assertOk();
        $this->assertSame('FALLBACK_STRUCTURED', $response->json('meta.LLM_SYNTHESIS'));
        $this->assertStringNotContainsString('leading', mb_strtolower((string) $response->json('message')));
    }

    public function test_current_unverified_accepts_limitation_only_model_wording(): void
    {
        $this->enablePublicAi();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider(
            '{"message":"I can\'t verify Apple\'s live stock price through this assistant.","can_verify_live":false}'
        ));

        $response = $this->chat(str_repeat('c4', 20), "What's Apple's stock price right now?");
        $response->assertOk();
        $this->assertSame('YES', $response->json('meta.LLM_SYNTHESIS'));
        $this->assertSame('LLM_ASSISTED', $response->json('mode'));
        $this->assertStringContainsString('can\'t verify', mb_strtolower((string) $response->json('message')));
        $this->assertStringNotContainsString('trading higher', mb_strtolower((string) $response->json('message')));
    }

    public function test_current_unverified_rejects_mixed_limitation_plus_live_claim(): void
    {
        $this->enablePublicAi();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider(
            '{"message":"I can\'t verify it live, but Apple is up today.","can_verify_live":false}'
        ));

        $response = $this->chat(str_repeat('c5', 20), "What's Apple's stock price right now?");
        $response->assertOk();
        $this->assertSame('FALLBACK_STRUCTURED', $response->json('meta.LLM_SYNTHESIS'));
        $body = mb_strtolower((string) $response->json('message'));
        $this->assertStringNotContainsString('is up today', $body);
        $this->assertStringContainsString('can\'t verify', $body);
    }

    public function test_current_unverified_malformed_model_uses_structured_fallback(): void
    {
        $this->enablePublicAi();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider(
            'not-json-at-all'
        ));

        $response = $this->chat(str_repeat('c6', 20), "What's Apple's stock price right now?");
        $response->assertOk();
        $this->assertSame('FALLBACK_STRUCTURED', $response->json('meta.LLM_SYNTHESIS'));
        $this->assertSame('STRUCTURED_FALLBACK', $response->json('mode'));
    }

    public function test_current_unverified_rejects_qualitative_news_claim(): void
    {
        $this->enablePublicAi();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider(
            '{"message":"Apple has just announced a major product today.","can_verify_live":false}'
        ));

        $response = $this->chat(str_repeat('c7', 20), "Any breaking news about Apple right now?");
        $response->assertOk();
        $this->assertSame('FALLBACK_STRUCTURED', $response->json('meta.LLM_SYNTHESIS'));
        $body = mb_strtolower((string) $response->json('message'));
        $this->assertStringNotContainsString('has just announced', $body);
        $this->assertStringContainsString('can\'t verify', $body);
    }

    public function test_current_unverified_rejects_can_verify_live_true_flag(): void
    {
        $this->enablePublicAi();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider(
            '{"message":"I can\'t verify Apple\'s live stock price through this assistant.","can_verify_live":true}'
        ));

        $response = $this->chat(str_repeat('c8', 20), "What's Apple's stock price right now?");
        $response->assertOk();
        $this->assertSame('FALLBACK_STRUCTURED', $response->json('meta.LLM_SYNTHESIS'));
        $this->assertSame('CURRENT_UNVERIFIED', $response->json('meta.open_domain_category'));
    }

    public function test_what_is_jetpakistan_uses_rag_then_model_synthesis(): void
    {
        $this->enablePublicAi();
        // Marker stays ≤3 chars so it is not treated as an unsupported material claim token.
        $scripted = new ScriptedInferenceProvider(
            '{"message":"JPX: JetPakistan is an online travel agency helping customers search flights and get travel support."}'
        );
        $this->app->instance(InferenceProvider::class, $scripted);

        $response = $this->chat(str_repeat('m6', 20), 'What is JetPakistan?');
        $response->assertOk();
        $this->assertStringContainsString('JPX:', (string) $response->json('message'));
        $this->assertSame('what-is-jetpakistan', $response->json('meta.KNOWLEDGE_SOURCE'));
        $this->assertGreaterThanOrEqual(1, (int) $response->json('meta.KNOWLEDGE_HITS'));
        $this->assertSame('YES', $response->json('meta.ANSWER_GROUNDED'));
        $this->assertSame('YES', $response->json('meta.LLM_SYNTHESIS'));
        $this->assertSame('LLM_ASSISTED', $response->json('mode'));
    }

    public function test_what_is_jetpakistan_structured_fallback_when_model_down(): void
    {
        $this->enablePublicAi(['ota.ai_assistant.conversational_enabled' => false]);
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);

        $response = $this->chat(str_repeat('m7', 20), 'What is JetPakistan?');
        $response->assertOk();
        $this->assertSame('what-is-jetpakistan', $response->json('meta.KNOWLEDGE_SOURCE'));
        $this->assertSame('YES', $response->json('meta.ANSWER_GROUNDED'));
        $this->assertSame('FALLBACK_STRUCTURED', $response->json('meta.LLM_SYNTHESIS'));
        $body = mb_strtolower((string) $response->json('message'));
        $this->assertStringContainsString('jetpakistan', $body);
        $this->assertStringContainsString('travel', $body);
    }

    public function test_unknown_jetpakistan_policy_does_not_hallucinate(): void
    {
        $this->enablePublicAi([
            // Force zero approved hits while still routing as a JetPakistan knowledge question.
            'ota.ai_assistant.knowledge_enabled' => false,
        ]);
        $scripted = new ScriptedInferenceProvider(
            '{"message":"MODEL_SHOULD_NOT_APPEAR: JetPakistan refunds all tickets in 3 minutes guaranteed."}'
        );
        $this->app->instance(InferenceProvider::class, $scripted);

        $response = $this->chat(str_repeat('m8', 20), 'What is JetPakistan secret platinum refund SLA policy?');
        $response->assertOk();
        $body = mb_strtolower((string) $response->json('message'));
        $this->assertSame(0, (int) $response->json('meta.KNOWLEDGE_HITS'));
        $this->assertSame('NO', $response->json('meta.ANSWER_GROUNDED'));
        $this->assertSame(0, (int) $response->json('meta.JETPAKISTAN_FACT_HALLUCINATION'));
        $this->assertStringNotContainsString('3 minutes', $body);
        $this->assertStringNotContainsString('MODEL_SHOULD_NOT_APPEAR', (string) $response->json('message'));
    }

    public function test_generic_embed_tenant_redirect_excludes_jetpakistan(): void
    {
        $tenant = $this->enableEmbedTenant(
            slug: 'client-a',
            embedKey: 'client-a-embed-key12',
            allowedOrigins: ['https://client-a.example.com'],
            capabilities: [
                EmbedTenantCapability::GENERAL_AI,
                EmbedTenantCapability::SUPPORT_HANDOFF,
            ],
        );
        $tenant->forceFill([
            'display_name' => 'Client A',
            'assistant_name' => 'Ask Client A',
        ])->save();

        config([
            'ota.ai_assistant.conversational_enabled' => true,
            'ai_lab.enabled' => false,
        ]);
        \App\Models\AiAssistantSetting::query()->delete();
        app(\App\Services\Ai\AiAssistantSettingsService::class)->get();

        $scripted = new ScriptedInferenceProvider(
            '{"message":"MODEL_CLIENT_A: Client A does not sell aircraft — I can connect you with Client A support."}'
        );
        $this->app->instance(InferenceProvider::class, $scripted);

        $session = $this->postJson($this->embedApiPath('client-a-embed-key12', '/session'), [], [
            'X-JP-AI-Embed-Parent-Origin' => 'https://client-a.example.com',
        ])->assertOk();
        $token = (string) $session->json('token');

        $response = $this->postJson($this->embedApiPath('client-a-embed-key12', '/chat'), [
            'message' => 'Where can I buy a jet?',
        ], [
            'X-JP-AI-Embed-Session' => $token,
            'X-JP-AI-Embed-Parent-Origin' => 'https://client-a.example.com',
        ]);

        $response->assertOk();
        $body = mb_strtolower((string) $response->json('message'));
        $this->assertStringContainsString('model_client_a', $body);
        $this->assertStringContainsString('client a', $body);
        $this->assertStringNotContainsString('jetpakistan', $body);
        $this->assertStringNotContainsString('group ticketing', $body);
        $this->assertSame('YES', $response->json('meta.LLM_SYNTHESIS'));

        $actions = $response->json('actions');
        $this->assertIsArray($actions);
        $labels = array_map(
            static fn ($a) => mb_strtolower((string) ($a['label'] ?? '')),
            $actions
        );
        $hrefs = array_values(array_filter(array_map(
            static fn ($a) => (string) ($a['href'] ?? ''),
            $actions
        )));

        $this->assertNotContains('search flights', $labels);
        $this->assertNotContains('browse groups', $labels);
        $this->assertNotContains('manage booking', $labels);
        foreach ($hrefs as $href) {
            $this->assertDoesNotMatchRegularExpression('#(/groups\b|/lookup-booking\b|/support\b|#flight-search)#i', $href);
        }
        // SUPPORT_HANDOFF capability alone is insufficient without a live handoff adapter.
        $this->assertSame([], $actions);
    }

    public function test_generic_embed_client_a_handoff_waiting_followup_excludes_jp_hrefs(): void
    {
        $tenant = $this->enableEmbedTenant(
            slug: 'client-a',
            embedKey: 'client-a-embed-key12',
            allowedOrigins: ['https://client-a.example.com'],
            capabilities: [
                EmbedTenantCapability::GENERAL_AI,
                EmbedTenantCapability::SUPPORT_HANDOFF,
            ],
        );
        $tenant->forceFill([
            'display_name' => 'Client A',
            'assistant_name' => 'Ask Client A',
        ])->save();

        config([
            'ota.ai_assistant.conversational_enabled' => true,
            'ota.ai_assistant.human_handoff_enabled' => true,
            'ai_lab.enabled' => false,
        ]);
        \App\Models\AiAssistantSetting::query()->delete();
        app(\App\Services\Ai\AiAssistantSettingsService::class)->get();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider(
            '{"message":"MODEL_CLIENT_A: I can connect you with Client A support."}'
        ));

        $session = $this->postJson($this->embedApiPath('client-a-embed-key12', '/session'), [], [
            'X-JP-AI-Embed-Parent-Origin' => 'https://client-a.example.com',
        ])->assertOk();
        $headers = [
            'X-JP-AI-Embed-Session' => (string) $session->json('token'),
            'X-JP-AI-Embed-Parent-Origin' => 'https://client-a.example.com',
        ];

        $chat = $this->postJson($this->embedApiPath('client-a-embed-key12', '/chat'), [
            'message' => 'I need help from a human',
        ], $headers)->assertOk();
        $this->assertNoJetPakistanActionHrefs($chat->json('actions'));

        $cid = (string) $chat->json('conversation_id');
        $handoff = $this->postJson($this->embedApiPath('client-a-embed-key12', '/handoff'), [
            'conversation_id' => $cid,
        ], $headers)->assertOk();
        $this->assertSame('waiting_for_human', $handoff->json('status'));
        $this->assertNoJetPakistanActionHrefs($handoff->json('actions'));

        $waiting = $this->postJson($this->embedApiPath('client-a-embed-key12', '/chat'), [
            'conversation_id' => $cid,
            'message' => 'Still waiting — any update?',
        ], $headers)->assertOk();
        $this->assertSame('waiting_for_human', $waiting->json('status'));
        $this->assertNoJetPakistanActionHrefs($waiting->json('actions'));
    }

    public function test_grounded_knowledge_accepts_supported_paraphrase(): void
    {
        $this->enablePublicAi();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider(
            '{"message":"JetPakistan is an online travel platform that helps customers search flights and get travel support."}'
        ));

        $response = $this->chat(str_repeat('g1', 20), 'What is JetPakistan?');
        $response->assertOk();
        $this->assertSame('YES', $response->json('meta.ANSWER_GROUNDED'));
        $this->assertSame('YES', $response->json('meta.LLM_SYNTHESIS'));
        $this->assertSame('LLM_ASSISTED', $response->json('mode'));
        $this->assertStringContainsString('search flights', mb_strtolower((string) $response->json('message')));
        $this->assertStringNotContainsString('guarantees all refunds', mb_strtolower((string) $response->json('message')));
    }

    public function test_grounded_knowledge_rejects_unsupported_refund_claim_uses_structured_fallback(): void
    {
        $this->enablePublicAi();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider(
            '{"message":"JetPakistan helps customers search flights and guarantees all refunds within 24 hours."}'
        ));

        $response = $this->chat(str_repeat('g2', 20), 'What is JetPakistan?');
        $response->assertOk();
        $body = mb_strtolower((string) $response->json('message'));
        $this->assertStringNotContainsString('guarantees all refunds within 24 hours', $body);
        $this->assertStringNotContainsString('within 24 hours', $body);
        $this->assertSame('YES', $response->json('meta.ANSWER_GROUNDED'));
        $this->assertSame('FALLBACK_STRUCTURED', $response->json('meta.LLM_SYNTHESIS'));
        $this->assertGreaterThanOrEqual(1, (int) $response->json('meta.KNOWLEDGE_HITS'));
    }

    public function test_grounded_knowledge_rejects_unsupported_hotel_lounge_visa_fleet_claims(): void
    {
        $claims = [
            'JetPakistan also offers hotel bookings.',
            'JetPakistan operates airport lounges.',
            'JetPakistan issues visas directly.',
            'JetPakistan owns its own airline fleet.',
        ];

        foreach ($claims as $i => $claim) {
            $this->enablePublicAi();
            $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider(
                json_encode(['message' => $claim], JSON_THROW_ON_ERROR)
            ));

            $response = $this->chat(str_repeat('u'.(string) $i, 20), 'What is JetPakistan?');
            $response->assertOk();
            $body = mb_strtolower((string) $response->json('message'));
            $this->assertStringNotContainsString(mb_strtolower($claim), $body, 'claim leaked: '.$claim);
            $this->assertSame('FALLBACK_STRUCTURED', $response->json('meta.LLM_SYNTHESIS'), 'claim: '.$claim);
            $this->assertSame('YES', $response->json('meta.ANSWER_GROUNDED'), 'claim: '.$claim);
        }
    }

    public function test_grounded_knowledge_rejects_unsupported_qualifiers_and_superlatives(): void
    {
        $claims = [
            'JetPakistan is an award-winning online travel platform that helps customers search flights and get travel support.',
            'JetPakistan is government-approved for flight search and travel support.',
            'JetPakistan is Pakistan\'s largest travel platform for flight search.',
            'JetPakistan is the country\'s cheapest booking platform for flights.',
        ];

        foreach ($claims as $i => $claim) {
            $this->enablePublicAi();
            $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider(
                json_encode(['message' => $claim], JSON_THROW_ON_ERROR)
            ));

            $response = $this->chat(str_repeat('q'.(string) $i, 20), 'What is JetPakistan?');
            $response->assertOk();
            $body = mb_strtolower((string) $response->json('message'));
            $this->assertStringNotContainsString('award-winning', $body);
            $this->assertStringNotContainsString('government-approved', $body);
            $this->assertStringNotContainsString('largest travel platform', $body);
            $this->assertStringNotContainsString('cheapest booking platform', $body);
            $this->assertSame('FALLBACK_STRUCTURED', $response->json('meta.LLM_SYNTHESIS'), 'claim: '.$claim);
            $this->assertSame('YES', $response->json('meta.ANSWER_GROUNDED'), 'claim: '.$claim);
        }
    }

    /**
     * @param  list<array{label?: string, href?: string, action?: string}>|null  $actions
     */
    private function assertNoJetPakistanActionHrefs(?array $actions): void
    {
        $this->assertIsArray($actions);
        foreach ($actions as $action) {
            $href = (string) ($action['href'] ?? '');
            if ($href === '') {
                continue;
            }
            $this->assertDoesNotMatchRegularExpression(
                '#(/support\b|/lookup-booking\b|/groups\b|#flight-search)#i',
                $href
            );
        }
    }
}
