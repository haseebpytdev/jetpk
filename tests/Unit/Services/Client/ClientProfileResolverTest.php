<?php

namespace Tests\Unit\Services\Client;

use App\Models\ClientProfile;
use App\Models\ClientProfileBranding;
use App\Models\ClientProfileModule;
use App\Services\Client\ClientProfileResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientProfileResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_by_slug_returns_db_profile_when_present(): void
    {
        $profile = ClientProfile::query()->create([
            'name' => 'DB Profile Travel',
            'slug' => 'db-profile-client',
            'domain' => 'db-profile.test',
            'environment' => 'production',
            'active_frontend_theme' => 'v2-modern',
            'asset_profile' => 'db-profile-client',
            'default_locale' => 'en',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'is_master_profile' => false,
            'is_active' => true,
        ]);

        ClientProfileBranding::query()->create([
            'client_profile_id' => $profile->id,
            'company_name' => 'DB Profile Travel',
            'primary_color' => '#aabbcc',
        ]);

        ClientProfileModule::query()->create([
            'client_profile_id' => $profile->id,
            'module_key' => 'sabre',
            'enabled' => false,
        ]);

        $resolver = app(ClientProfileResolver::class);
        $resolved = $resolver->resolveBySlug('db-profile-client');

        $this->assertNotNull($resolved);
        $this->assertSame('db-profile-client', $resolved->slug);
        $this->assertSame('DB Profile Travel', $resolved->branding?->company_name);

        $runtime = $resolver->toRuntimeConfig($resolved);
        $this->assertSame('v2-modern', $runtime['theme']);
        $this->assertFalse($runtime['modules']['sabre']);
        $this->assertSame('#aabbcc', $runtime['branding']['primary_color']);
    }

    public function test_resolve_by_slug_returns_null_when_missing(): void
    {
        $resolver = app(ClientProfileResolver::class);

        $this->assertNull($resolver->resolveBySlug('missing-client'));
    }

    public function test_profile_payload_from_config_falls_back_when_db_missing(): void
    {
        config([
            'ota_client.slug' => 'config-only-client',
            'ota_client.theme' => 'v1-classic',
            'ota_client.asset_profile' => 'config-only-client',
            'ota_client.modules' => [
                'sabre' => false,
                'al_haider_group_ticketing' => true,
                'accounting' => true,
                'hotels' => true,
                'visa' => true,
                'payment_gateway' => true,
                'dev_cp' => true,
                'staff_panel' => true,
                'admin_panel' => true,
            ],
            'ota-client.agency_name' => 'Config Only Travel',
            'ota-client.domain_preview' => 'config-only.test',
            'app.url' => 'https://config-only.test',
        ]);

        $resolver = app(ClientProfileResolver::class);
        $payload = $resolver->profilePayloadFromConfig('config-only-client');

        $this->assertSame('config-only-client', $payload['slug']);
        $this->assertSame('Config Only Travel', $payload['name']);
        $this->assertFalse($payload['modules']['sabre']);
        $this->assertTrue($payload['modules']['al_haider_group_ticketing']);
    }

    public function test_modules_from_config_reads_ota_client_modules(): void
    {
        config([
            'ota_client.modules' => [
                'sabre' => false,
                'al_haider_group_ticketing' => false,
                'accounting' => false,
                'hotels' => false,
                'visa' => false,
                'payment_gateway' => false,
                'dev_cp' => false,
                'staff_panel' => false,
                'admin_panel' => false,
            ],
        ]);

        $modules = app(ClientProfileResolver::class)->modulesFromConfig();

        $this->assertFalse($modules['sabre']);
        $this->assertFalse($modules['admin_panel']);
    }
}
