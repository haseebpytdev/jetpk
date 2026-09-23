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
use Tests\Support\InteractsWithEmbedTenants;
use Tests\TestCase;

class AiEmbedSecurityTest extends TestCase
{
    use InteractsWithEmbedTenants;
    use RefreshDatabase;

    private const PARENT = 'https://client.example.com';

    private const ENTRY_PATH = 'test-embed-path-token12';

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
        if (isset($extra['ai_embed.session_ttl_seconds'])) {
            config(['ai_embed.session_ttl_seconds' => $extra['ai_embed.session_ttl_seconds']]);
        }
        $this->enableEmbedTenant(
            embedKey: self::ENTRY_PATH,
            allowedOrigins: [self::PARENT, 'https://www.client.example.com'],
        );
    }

    private function createSession(string $parentOrigin = self::PARENT): string
    {
        $response = $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/session'), [], [
            'X-JP-AI-Embed-Parent-Origin' => $parentOrigin,
        ]);

        $response->assertOk()->assertJsonPath('ok', true);

        return (string) $response->json('token');
    }

    private function embedPageUrl(?string $path = null): string
    {
        return '/integrations/ai/'.($path ?? self::ENTRY_PATH);
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

        $response = $this->get($this->embedPageUrl());

        $response->assertOk();
        $this->assertFalse($response->headers->has('X-Frame-Options'));
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('frame-ancestors', $csp);
        $this->assertStringContainsString(self::PARENT, $csp);
        $this->assertStringNotContainsString('*', $csp);
    }

    public function test_wrong_private_path_returns_not_found_with_sameorigin(): void
    {
        $this->enableEmbed();

        $response = $this->get('/integrations/ai/wrong-token-value');

        $response->assertNotFound();
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function test_legacy_predictable_embed_path_returns_not_found(): void
    {
        $this->enableEmbed();

        $this->get('/ai/embed/jetpakistan')->assertNotFound();
    }

    public function test_embed_path_not_exposed_in_public_config_or_sitemap(): void
    {
        $this->enableEmbed();

        $config = $this->getJson('/api/public/content/config')->assertOk()->json();
        $encoded = json_encode($config);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString(self::ENTRY_PATH, $encoded);
        $this->assertStringNotContainsString('/integrations/ai/', $encoded);

        $sitemap = $this->getJson('/api/public/content/sitemap-routes')->assertOk()->json('routes') ?? [];
        $paths = collect($sitemap)->pluck('path')->all();
        $this->assertNotContains('/integrations/ai/'.self::ENTRY_PATH, $paths);
        $this->assertNotContains('/ai/embed/jetpakistan', $paths);
    }

    public function test_unknown_tenant_api_returns_not_found(): void
    {
        $this->enableEmbed();

        $this->postJson('/api/embed/ai/unknown-brand/session')->assertNotFound();
    }

    public function test_embed_disabled_returns_not_found(): void
    {
        $this->enableEmbedTenant(embedKey: self::ENTRY_PATH);
        config(['ai_embed.enabled' => false]);

        $this->get($this->embedPageUrl())->assertNotFound();
    }

    public function test_embed_missing_path_config_returns_not_found(): void
    {
        config(['ai_embed.enabled' => true]);
        app(\App\Services\Ai\Embed\EmbedTenantManager::class)->upsertTenant(
            slug: 'no-key-tenant',
            displayName: 'No Key',
            assistantName: 'No Key',
            allowedOrigins: [self::PARENT],
            capabilities: [],
            embedEnabled: true,
            status: \App\Models\AiEmbedTenant::STATUS_ACTIVE,
        );

        $this->get('/integrations/ai/missing-key-token123456')->assertNotFound();
    }

    public function test_session_rejects_unknown_parent_origin(): void
    {
        $this->enableEmbed();

        $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/session'), [], [
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

        $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/chat'), [
            'message' => 'What is JetPakistan?',
        ], [
            'X-JP-AI-Embed-Parent-Origin' => self::PARENT,
        ])->assertUnauthorized();
    }

    public function test_chat_rejects_invalid_session_token(): void
    {
        $this->enableEmbed();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);

        $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/chat'), [
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

        $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/chat'), [
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
        $cacheKey = 'ai_embed_sess:'.hash('sha256', $token);
        $payload = Cache::get($cacheKey);
        $this->assertIsArray($payload);
        $payload['expires_at'] = now()->subSeconds(5)->toIso8601String();
        Cache::put($cacheKey, $payload, now()->subSeconds(1));

        $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/chat'), [
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

        $this->getJson($this->embedApiPath(self::ENTRY_PATH, '/messages').'?conversation_id='.$foreign->public_id, [
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
        $resolver = app(\App\Services\Ai\Embed\EmbedTenantResolver::class);
        $this->assertNull($resolver->normalizeEmbedKey('short'));
        $this->assertSame(self::ENTRY_PATH, $resolver->normalizeEmbedKey(self::ENTRY_PATH));
    }

    public function test_chat_rejects_missing_parent_origin_header(): void
    {
        $this->enableEmbed();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);

        $token = $this->createSession();

        $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/chat'), [
            'message' => 'What is JetPakistan?',
        ], [
            'X-JP-AI-Embed-Session' => $token,
        ])->assertForbidden();
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
