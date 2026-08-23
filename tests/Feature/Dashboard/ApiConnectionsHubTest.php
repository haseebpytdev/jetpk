<?php

namespace Tests\Feature\Dashboard;

use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class ApiConnectionsHubTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_api_settings_index_redirects_to_integrations_hub(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get('/admin/api-settings')
            ->assertRedirect('/admin/dashboard/integrations');
    }

    public function test_json_list_includes_provider_catalog_and_provider_cards(): void
    {
        $admin = $this->platformAdmin();

        $response = $this->actingAs($admin)
            ->getJson('/admin/api-settings?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $providers = $response->json('providers');
        $this->assertIsArray($providers);
        $this->assertNotEmpty($providers);
        $this->assertArrayHasKey('key', $providers[0]);
        $this->assertArrayHasKey('label', $providers[0]);
        $this->assertArrayHasKey('installed', $providers[0]);

        $cards = $response->json('providerCards');
        $this->assertIsArray($cards);
        $this->assertNotEmpty($cards);
        $this->assertArrayHasKey('key', $cards[0]);
        $this->assertArrayHasKey('label', $cards[0]);
    }

    public function test_admin_navigation_exposes_integrations_not_api_connections(): void
    {
        $admin = $this->platformAdmin();

        $response = $this->actingAs($admin)
            ->getJson(route('api.dashboard.session', ['portal' => 'admin']))
            ->assertOk();

        $navigation = collect($response->json('data.navigation'));
        $this->assertNull($navigation->firstWhere('key', 'api-settings'));
        $this->assertSame(0, $navigation->where('label', 'API Connections')->count());

        $integrations = $navigation->firstWhere('key', 'integrations');
        $this->assertNotNull($integrations);
        $this->assertSame('/integrations', $integrations['href']);
        $this->assertSame('dashboard', $integrations['target']);
        $this->assertSame(1, $navigation->where('key', 'integrations')->count());
    }
}
