<?php

namespace Tests\Feature\Ai\Embed;

use App\Contracts\Ai\InferenceProvider;
use App\Models\AiConversation;
use App\Models\AiEmbedTenant;
use App\Services\Ai\NullInferenceProvider;
use App\Support\Ai\Embed\EmbedTenantCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\Support\InteractsWithEmbedTenants;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use InteractsWithEmbedTenants;
    use RefreshDatabase;

    private const PARENT_A = 'https://tenant-a.example.com';

    private const PARENT_B = 'https://tenant-b.example.com';

    private const KEY_A = 'tenant-a-embed-key-token1234';

    private const KEY_B = 'tenant-b-embed-key-token5678';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seedKnowledgeFixtures();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('ai-assistant/knowledge/tenant_a'));
        File::deleteDirectory(base_path('ai-assistant/knowledge/tenant_b'));
        parent::tearDown();
    }

    private function seedKnowledgeFixtures(): void
    {
        foreach (['tenant_a' => 'Tenant A secret doc', 'tenant_b' => 'Tenant B secret doc'] as $ns => $title) {
            $dir = base_path('ai-assistant/knowledge/'.$ns);
            File::ensureDirectoryExists($dir);
            $marker = $ns === 'tenant_a' ? 'QX7A9ZTENANTAONLY' : 'QX7B9ZTENANTBONLY';
            File::put($dir.'/secret.md', "# {$title}\n\nIsolation marker {$marker}.");
        }
    }

    private function tenantA(): AiEmbedTenant
    {
        return $this->enableEmbedTenant(
            slug: 'tenant_a',
            embedKey: self::KEY_A,
            allowedOrigins: [self::PARENT_A],
            capabilities: [
                EmbedTenantCapability::GENERAL_AI,
                EmbedTenantCapability::KNOWLEDGE,
            ],
        );
    }

    private function tenantB(): AiEmbedTenant
    {
        return $this->enableEmbedTenant(
            slug: 'tenant_b',
            embedKey: self::KEY_B,
            allowedOrigins: [self::PARENT_B],
            capabilities: [
                EmbedTenantCapability::GENERAL_AI,
                EmbedTenantCapability::KNOWLEDGE,
            ],
        );
    }

    /**
     * @return array{token: string, headers: array<string, string>}
     */
    private function sessionFor(string $embedKey, string $origin): array
    {
        $session = $this->postJson($this->embedApiPath($embedKey, '/session'), [], [
            'X-JP-AI-Embed-Parent-Origin' => $origin,
        ])->assertOk();

        $token = (string) $session->json('token');

        return [
            'token' => $token,
            'headers' => [
                'X-JP-AI-Embed-Session' => $token,
                'X-JP-AI-Embed-Parent-Origin' => $origin,
            ],
        ];
    }

    public function test_cross_tenant_conversation_access_is_denied(): void
    {
        $tenantA = $this->tenantA();
        $tenantB = $this->tenantB();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);

        $a = $this->sessionFor(self::KEY_A, self::PARENT_A);
        $chatA = $this->postJson($this->embedApiPath(self::KEY_A, '/chat'), [
            'message' => 'Hello from tenant A',
        ], $a['headers'])->assertOk();
        $conversationId = (string) $chatA->json('conversation_id');

        $b = $this->sessionFor(self::KEY_B, self::PARENT_B);
        $this->getJson(
            $this->embedApiPath(self::KEY_B, '/messages').'?conversation_id='.$conversationId,
            $b['headers']
        )->assertForbidden();

        $this->assertDatabaseMissing('ai_conversations', [
            'public_id' => $conversationId,
            'ai_embed_tenant_id' => $tenantB->id,
        ]);
        $this->assertDatabaseHas('ai_conversations', [
            'public_id' => $conversationId,
            'ai_embed_tenant_id' => $tenantA->id,
        ]);
    }

    public function test_knowledge_is_isolated_by_namespace(): void
    {
        $tenantA = $this->tenantA();
        $tenantB = $this->tenantB();

        $providerA = new \App\Services\Ai\Embed\Adapters\JetPakistan\JetPakistanKnowledgeProvider($tenantA);
        $providerB = new \App\Services\Ai\Embed\Adapters\JetPakistan\JetPakistanKnowledgeProvider($tenantB);

        $hitsA = $providerA->search('QX7A9ZTENANTAONLY', 3);
        $this->assertNotEmpty($hitsA);
        $this->assertSame('secret', $hitsA[0]['slug'] ?? null);

        $crossA = $providerB->search('QX7A9ZTENANTAONLY', 3);
        $this->assertSame([], $crossA);

        $hitsB = $providerB->search('QX7B9ZTENANTBONLY', 3);
        $this->assertNotEmpty($hitsB);

        $crossB = $providerA->search('QX7B9ZTENANTBONLY', 3);
        $this->assertSame([], $crossB);
    }

    public function test_embed_key_rotation_invalidates_old_key(): void
    {
        $tenant = $this->tenantA();
        /** @var \App\Services\Ai\Embed\EmbedTenantManager $manager */
        $manager = app(\App\Services\Ai\Embed\EmbedTenantManager::class);
        $rotated = $manager->rotateKey($tenant);
        $newKey = (string) $rotated['embed_key'];

        $this->postJson($this->embedApiPath(self::KEY_A, '/session'), [], [
            'X-JP-AI-Embed-Parent-Origin' => self::PARENT_A,
        ])->assertNotFound();

        $this->postJson($this->embedApiPath($newKey, '/session'), [], [
            'X-JP-AI-Embed-Parent-Origin' => self::PARENT_A,
        ])->assertOk();
    }

    public function test_tenant_b_without_lead_capability_skips_lead_gate(): void
    {
        $this->enableEmbedTenant(
            slug: 'tenant_b',
            embedKey: self::KEY_B,
            allowedOrigins: [self::PARENT_B],
            capabilities: [EmbedTenantCapability::GENERAL_AI],
        );
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);

        $session = $this->sessionFor(self::KEY_B, self::PARENT_B);
        $response = $this->postJson($this->embedApiPath(self::KEY_B, '/chat'), [
            'message' => 'I need a one-way flight from Lahore to Dubai on 15 October for 2 adults',
        ], $session['headers'])->assertOk();

        $this->assertNotSame('lead_capture_required', $response->json('status'));
    }

    public function test_conversations_are_stored_with_tenant_id(): void
    {
        $tenant = $this->tenantA();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);
        $session = $this->sessionFor(self::KEY_A, self::PARENT_A);

        $this->postJson($this->embedApiPath(self::KEY_A, '/chat'), [
            'message' => 'Hello tenant scoped conversation',
        ], $session['headers'])->assertOk();

        $this->assertSame(1, AiConversation::query()->where('ai_embed_tenant_id', $tenant->id)->count());
    }

    public function test_embed_leads_persist_tenant_id(): void
    {
        $tenant = $this->enableEmbedTenant(
            slug: 'tenant_a',
            embedKey: self::KEY_A,
            allowedOrigins: [self::PARENT_A],
            capabilities: [
                EmbedTenantCapability::GENERAL_AI,
                EmbedTenantCapability::LEAD_CAPTURE,
            ],
        );
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);
        $session = $this->sessionFor(self::KEY_A, self::PARENT_A);

        $chat = $this->postJson($this->embedApiPath(self::KEY_A, '/chat'), [
            'message' => 'I need help booking a group to Dubai',
        ], $session['headers'])->assertOk();

        $conversation = AiConversation::query()->where('public_id', $chat->json('conversation_id'))->first();
        $this->assertNotNull($conversation);

        \App\Models\CustomerQuery::query()->create([
            'visitor_token_hash' => $conversation->visitor_token_hash,
            'ai_conversation_id' => $conversation->id,
            'ai_embed_tenant_id' => $conversation->ai_embed_tenant_id,
            'name' => 'Tenant A Lead',
            'email' => 'tenant-a-lead@example.com',
            'phone_raw' => '03001234567',
            'phone_e164' => '+923001234567',
            'phone_country' => 'PK',
            'contact_consent' => true,
            'consent_timestamp' => now(),
            'consent_source' => 'ask_jetpakistan',
            'source' => 'ask_jetpakistan',
            'status' => \App\Enums\CustomerQueryStatus::New,
            'last_activity_at' => now(),
        ]);

        $this->assertDatabaseHas('customer_queries', [
            'ai_embed_tenant_id' => $tenant->id,
            'email' => 'tenant-a-lead@example.com',
        ]);
        $this->assertDatabaseMissing('customer_queries', [
            'email' => 'tenant-a-lead@example.com',
            'ai_embed_tenant_id' => null,
        ]);
    }

    public function test_embed_clear_preserves_tenant_id_and_blocks_cross_tenant_access(): void
    {
        $tenantA = $this->tenantA();
        $tenantB = $this->tenantB();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);

        $a = $this->sessionFor(self::KEY_A, self::PARENT_A);
        $chat = $this->postJson($this->embedApiPath(self::KEY_A, '/chat'), [
            'message' => 'Hello from tenant A before clear',
        ], $a['headers'])->assertOk();
        $oldId = (string) $chat->json('conversation_id');

        $clear = $this->postJson($this->embedApiPath(self::KEY_A, '/clear'), [
            'conversation_id' => $oldId,
        ], $a['headers'])->assertOk();

        $newId = (string) $clear->json('conversation_id');
        $this->assertNotSame($oldId, $newId);

        $newConversation = AiConversation::query()->where('public_id', $newId)->first();
        $this->assertNotNull($newConversation);
        $this->assertSame('embed', $newConversation->channel);
        $this->assertSame($tenantA->id, (int) $newConversation->ai_embed_tenant_id);

        $postClear = $this->postJson($this->embedApiPath(self::KEY_A, '/chat'), [
            'conversation_id' => $newId,
            'message' => 'Continue after clear',
        ], $a['headers']);
        $postClear->assertOk();
        $this->assertSame($newId, (string) $postClear->json('conversation_id'));
        $this->assertDatabaseHas('ai_conversations', [
            'public_id' => $newId,
            'ai_embed_tenant_id' => $tenantA->id,
            'channel' => 'embed',
        ]);

        $b = $this->sessionFor(self::KEY_B, self::PARENT_B);
        $this->getJson(
            $this->embedApiPath(self::KEY_B, '/messages').'?conversation_id='.$newId,
            $b['headers']
        )->assertForbidden();
        $this->assertDatabaseMissing('ai_conversations', [
            'public_id' => $newId,
            'ai_embed_tenant_id' => $tenantB->id,
        ]);
    }
}
