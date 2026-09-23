<?php

namespace Tests\Feature\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\AiEmbedSessionService;
use App\Services\Ai\NullInferenceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiEmbedSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const PARENT = 'https://client.example.com';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function enableEmbed(array $extra = []): void
    {
        config(array_merge([
            'ai_embed.enabled' => true,
            'ai_embed.session_ttl_seconds' => 3600,
            'ai_embed.tenants.jetpakistan.allowed_origins' => [self::PARENT, 'https://www.client.example.com'],
            'ota.ai_assistant.mode' => 'public',
            'ota.ai_assistant.enabled' => true,
            'ota.ai_assistant.hard_allow.master' => true,
            'ota.ai_assistant.hard_allow.public' => true,
            'ota.ai_assistant.conversational_enabled' => false,
            'ota.ai_assistant.optional_llm_assist' => false,
        ], $extra));

        \App\Models\AiAssistantSetting::query()->delete();
        app(\App\Services\Ai\AiAssistantSettingsService::class)->get();
    }

    private function createSession(string $parentOrigin = self::PARENT): string
    {
        $response = $this->postJson('/api/embed/ai/jetpakistan/session', [], [
            'X-JP-AI-Embed-Parent-Origin' => $parentOrigin,
        ]);

        $response->assertOk()->assertJsonPath('ok', true);

        return (string) $response->json('token');
    }

    public function test_homepage_still_sends_sameorigin_x_frame_options(): void
    {
        $this->enableEmbed();

        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_embed_page_uses_frame_ancestors_and_omits_x_frame_options(): void
    {
        $this->enableEmbed();

        $response = $this->get('/ai/embed/jetpakistan');

        $response->assertOk();
        $this->assertFalse($response->headers->has('X-Frame-Options'));
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('frame-ancestors', $csp);
        $this->assertStringContainsString(self::PARENT, $csp);
        $this->assertStringNotContainsString('*', $csp);
    }

    public function test_unknown_tenant_returns_not_found(): void
    {
        $this->enableEmbed();

        $this->get('/ai/embed/unknown-brand')->assertNotFound();
        $this->postJson('/api/embed/ai/unknown-brand/session')->assertNotFound();
    }

    public function test_embed_disabled_returns_not_found(): void
    {
        config([
            'ai_embed.enabled' => false,
            'ai_embed.tenants.jetpakistan.allowed_origins' => [self::PARENT],
        ]);

        $this->get('/ai/embed/jetpakistan')->assertNotFound();
    }

    public function test_session_rejects_unknown_parent_origin(): void
    {
        $this->enableEmbed();

        $this->postJson('/api/embed/ai/jetpakistan/session', [], [
            'X-JP-AI-Embed-Parent-Origin' => 'https://evil.example.com',
        ])->assertForbidden()
            ->assertJsonPath('status', 'forbidden');
    }

    public function test_session_accepts_allowlisted_parent_origin(): void
    {
        $this->enableEmbed();

        $token = $this->createSession();
        $this->assertGreaterThan(32, strlen($token));
    }

    public function test_chat_rejects_missing_session_token(): void
    {
        $this->enableEmbed();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);

        $this->postJson('/api/embed/ai/jetpakistan/chat', [
            'message' => 'What is JetPakistan?',
        ], [
            'X-JP-AI-Embed-Parent-Origin' => self::PARENT,
        ])->assertUnauthorized();
    }

    public function test_chat_rejects_invalid_session_token(): void
    {
        $this->enableEmbed();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);

        $this->postJson('/api/embed/ai/jetpakistan/chat', [
            'message' => 'What is JetPakistan?',
        ], [
            'X-JP-AI-Embed-Session' => str_repeat('x', 48),
            'X-JP-AI-Embed-Parent-Origin' => self::PARENT,
        ])->assertForbidden();
    }

    public function test_chat_rejects_origin_mismatch_for_bound_session(): void
    {
        $this->enableEmbed();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);

        $token = $this->createSession(self::PARENT);

        $this->postJson('/api/embed/ai/jetpakistan/chat', [
            'message' => 'What is JetPakistan?',
        ], [
            'X-JP-AI-Embed-Session' => $token,
            'X-JP-AI-Embed-Parent-Origin' => 'https://www.client.example.com',
        ])->assertForbidden();
    }

    public function test_expired_session_is_rejected(): void
    {
        $this->enableEmbed(['ai_embed.session_ttl_seconds' => 60]);
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);

        $token = $this->createSession();
        $this->travel(61)->seconds();

        $this->postJson('/api/embed/ai/jetpakistan/chat', [
            'message' => 'What is JetPakistan?',
        ], [
            'X-JP-AI-Embed-Session' => $token,
            'X-JP-AI-Embed-Parent-Origin' => self::PARENT,
        ])->assertForbidden();
    }

    public function test_messages_idor_blocked_for_foreign_conversation(): void
    {
        $this->enableEmbed();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);

        $foreign = AiConversation::query()->create([
            'public_id' => (string) Str::uuid(),
            'channel' => 'embed',
            'visitor_token_hash' => hash('sha256', str_repeat('z', 40)),
            'state' => AiConversation::STATE_AI_ACTIVE,
        ]);
        AiMessage::query()->create([
            'ai_conversation_id' => $foreign->id,
            'role' => 'assistant',
            'body' => 'secret embed transcript',
        ]);

        $token = $this->createSession();

        $this->getJson('/api/embed/ai/jetpakistan/messages?conversation_id='.$foreign->public_id, [
            'X-JP-AI-Embed-Session' => $token,
            'X-JP-AI-Embed-Parent-Origin' => self::PARENT,
        ])->assertForbidden();
    }

    public function test_origin_normalization_rejects_null_and_wildcard(): void
    {
        $service = app(AiEmbedSessionService::class);

        $this->assertNull($service->normalizeOrigin('null'));
        $this->assertNull($service->normalizeOrigin('*'));
        $this->assertNull($service->normalizeOrigin('http://client.example.com'));
        $this->assertSame(self::PARENT, $service->normalizeOrigin(self::PARENT));
    }

    public function test_public_ai_routes_remain_unchanged_without_embed_headers(): void
    {
        $this->enableEmbed();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);

        $response = $this->withCookie('jp_ai_vid', str_repeat('p', 40))
            ->postJson('/api/public/ai/chat', [
                'message' => 'What is JetPakistan?',
            ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertTrue($response->headers->has('Set-Cookie'));
    }
}
