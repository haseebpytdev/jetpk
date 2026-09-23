<?php

namespace Tests\Feature\Ai\Embed;

use App\Models\AiEmbedTenant;
use App\Services\Ai\Embed\Adapters\JetPakistan\JetPakistanKnowledgeProvider;
use App\Services\Ai\Embed\EmbedTenantManager;
use App\Services\Ai\KnowledgeSearchService;
use App\Support\Ai\Embed\EmbedTenantCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class EmbedKnowledgeIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('ai-assistant/knowledge/tenant_a'));
        File::deleteDirectory(base_path('ai-assistant/knowledge/tenant_b'));
        parent::tearDown();
    }

    private function seedTenantKnowledge(): void
    {
        foreach (['tenant_a' => 'QX7A9ZTENANTAONLY', 'tenant_b' => 'QX7B9ZTENANTBONLY'] as $ns => $marker) {
            $dir = base_path('ai-assistant/knowledge/'.$ns);
            File::ensureDirectoryExists($dir);
            File::put($dir.'/secret.md', "# {$ns}\n\nMarker {$marker}.");
        }
    }

    public function test_tenant_scoped_search_never_falls_back_to_jetpakistan_root(): void
    {
        $service = new KnowledgeSearchService;

        $this->assertSame([], $service->search('refund policy', 3, 'missing_namespace_dir'));
        $this->assertSame([], $service->search('refund policy', 3, '!!!'));
        $this->assertSame([], $service->search('refund policy', 3, 'jetpakistan'));
    }

    public function test_generic_tenant_cannot_use_reserved_jetpakistan_namespace(): void
    {
        /** @var EmbedTenantManager $manager */
        $manager = app(EmbedTenantManager::class);
        $tenant = $manager->upsertTenant(
            slug: 'client_x',
            displayName: 'Client X',
            assistantName: 'Assistant',
            allowedOrigins: [],
            capabilities: [EmbedTenantCapability::KNOWLEDGE],
            embedEnabled: false,
            status: AiEmbedTenant::STATUS_ACTIVE,
            knowledgeNamespace: 'jetpakistan',
        );

        $provider = new JetPakistanKnowledgeProvider($tenant);
        $this->assertSame([], $provider->search('refund policy', 3));
    }

    public function test_cross_tenant_knowledge_namespaces_are_isolated(): void
    {
        $this->seedTenantKnowledge();
        /** @var EmbedTenantManager $manager */
        $manager = app(EmbedTenantManager::class);

        $tenantA = $manager->upsertTenant(
            slug: 'tenant_a',
            displayName: 'Tenant A',
            assistantName: 'Assistant A',
            allowedOrigins: [],
            capabilities: [EmbedTenantCapability::KNOWLEDGE],
            embedEnabled: false,
            status: AiEmbedTenant::STATUS_ACTIVE,
            knowledgeNamespace: 'tenant_a',
        );
        $tenantB = $manager->upsertTenant(
            slug: 'tenant_b',
            displayName: 'Tenant B',
            assistantName: 'Assistant B',
            allowedOrigins: [],
            capabilities: [EmbedTenantCapability::KNOWLEDGE],
            embedEnabled: false,
            status: AiEmbedTenant::STATUS_ACTIVE,
            knowledgeNamespace: 'tenant_b',
        );

        $providerA = new JetPakistanKnowledgeProvider($tenantA);
        $providerB = new JetPakistanKnowledgeProvider($tenantB);

        $this->assertNotEmpty($providerA->search('QX7A9ZTENANTAONLY', 3));
        $this->assertSame([], $providerB->search('QX7A9ZTENANTAONLY', 3));
        $this->assertNotEmpty($providerB->search('QX7B9ZTENANTBONLY', 3));
        $this->assertSame([], $providerA->search('QX7B9ZTENANTBONLY', 3));
    }

    public function test_jetpakistan_tenant_can_search_root_corpus(): void
    {
        /** @var EmbedTenantManager $manager */
        $manager = app(EmbedTenantManager::class);
        $tenant = $manager->upsertTenant(
            slug: 'jetpakistan',
            displayName: 'JetPakistan',
            assistantName: 'Ask JetPakistan',
            allowedOrigins: [],
            capabilities: [EmbedTenantCapability::KNOWLEDGE],
            embedEnabled: false,
            status: AiEmbedTenant::STATUS_ACTIVE,
            knowledgeNamespace: 'jetpakistan',
        );

        $provider = new JetPakistanKnowledgeProvider($tenant);
        $hits = $provider->search('what is jetpakistan', 3);

        $this->assertNotEmpty($hits);
        $this->assertContains('what-is-jetpakistan', array_column($hits, 'slug'));
    }
}
