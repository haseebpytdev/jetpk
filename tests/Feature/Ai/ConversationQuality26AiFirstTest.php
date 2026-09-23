<?php

namespace Tests\Feature\Ai;

use App\Contracts\Ai\InferenceProvider;
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
        $scripted = new ScriptedInferenceProvider(
            '{"message":"MODEL_JP: JetPakistan is an online travel agency helping customers search flights and get travel support."}'
        );
        $this->app->instance(InferenceProvider::class, $scripted);

        $response = $this->chat(str_repeat('m6', 20), 'What is JetPakistan?');
        $response->assertOk();
        $this->assertStringContainsString('MODEL_JP', (string) $response->json('message'));
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
    }
}
