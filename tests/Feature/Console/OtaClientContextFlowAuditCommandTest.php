<?php

namespace Tests\Feature\Console;

use App\Models\ClientProfile;
use App\Models\ClientProfileModule;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class OtaClientContextFlowAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ota-developer.enabled', true);
        Config::set('ota_client.single_client_mode', false);
        Config::set('ota_client.single_client_root', false);
    }

    public function test_command_fails_when_client_profile_missing(): void
    {
        $this->artisan('ota:client-context-flow-audit', [
            '--client' => 'missing-client',
        ])->assertFailed();
    }

    public function test_command_succeeds_for_configured_client(): void
    {
        $this->makeProfile([
            'slug' => 'jetpk',
            'name' => 'Jet Pakistan',
            'active_frontend_theme' => 'jetpakistan',
            'active_admin_theme' => 'jetpakistan',
            'asset_profile' => 'jetpk-assets',
        ]);

        $exitCode = \Illuminate\Support\Facades\Artisan::call('ota:client-context-flow-audit', [
            '--client' => 'jetpk',
        ]);

        $this->assertSame(0, $exitCode, \Illuminate\Support\Facades\Artisan::output());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProfile(array $overrides = []): ClientProfile
    {
        $profile = ClientProfile::query()->create(array_merge([
            'name' => 'Test Client',
            'slug' => 'test-client-'.uniqid(),
            'domain' => null,
            'environment' => 'staging',
            'active_frontend_theme' => 'v1-classic',
            'active_admin_theme' => 'v1-classic',
            'active_staff_theme' => 'v1-classic',
            'asset_profile' => 'test-assets',
            'default_locale' => 'en',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'is_master_profile' => false,
            'is_active' => true,
        ], $overrides));

        foreach (ClientProfileConfigReader::MODULE_KEYS as $moduleKey) {
            ClientProfileModule::query()->create([
                'client_profile_id' => $profile->id,
                'module_key' => $moduleKey,
                'enabled' => false,
            ]);
        }

        return $profile;
    }
}
