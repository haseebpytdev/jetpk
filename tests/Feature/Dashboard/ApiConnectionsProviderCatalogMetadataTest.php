<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiConnectionsProviderCatalogMetadataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_provider_catalog_preserves_select_options_and_channel_metadata(): void
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        $providers = $this->actingAs($admin->fresh())
            ->getJson('/admin/api-settings?format=json')
            ->assertOk()
            ->json('providers');

        $alHaider = collect($providers)->firstWhere('key', 'al_haider');
        $this->assertNotNull($alHaider);

        $authMode = collect($alHaider['credentialFields'] ?? [])->firstWhere('key', 'auth_mode');
        $this->assertNotNull($authMode);
        $this->assertIsArray($authMode['options'] ?? null);
        $this->assertNotEmpty($authMode['options']);
        $this->assertArrayHasKey('value', $authMode['options'][0]);
        $this->assertArrayHasKey('label', $authMode['options'][0]);
    }
}
