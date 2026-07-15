<?php

namespace Tests\Unit\Support\Client;

use App\Support\Client\ClientProfile;
use Tests\TestCase;

class ClientProfileTest extends TestCase
{
    public function test_module_flags_default_true_when_unset(): void
    {
        config()->set('ota_client.modules', [
            'sabre' => true,
            'admin_panel' => true,
            'staff_panel' => true,
        ]);

        $this->assertTrue(ClientProfile::moduleEnabled('sabre'));
        $this->assertTrue(ClientProfile::moduleEnabled('admin_panel'));
    }

    public function test_module_enabled_respects_config_override(): void
    {
        config()->set('ota_client.modules', [
            'sabre' => false,
            'admin_panel' => true,
        ]);

        $this->assertFalse(ClientProfile::moduleEnabled('sabre'));
        $this->assertTrue(ClientProfile::moduleEnabled('admin_panel'));
    }

    public function test_unknown_module_returns_false(): void
    {
        config()->set('ota_client.modules', [
            'sabre' => true,
        ]);

        $this->assertFalse(ClientProfile::moduleEnabled('nonexistent_module'));
    }

    public function test_asset_path_without_profile_returns_path_unchanged(): void
    {
        config()->set('ota_client.asset_profile', '');

        $this->assertSame('css/ota-public.css', ClientProfile::assetPath('css/ota-public.css'));
        $this->assertSame('css/ota-public.css', ClientProfile::assetPath('/css/ota-public.css'));
    }

    public function test_asset_path_with_profile_prefixes_client_assets(): void
    {
        config()->set('ota_client.asset_profile', 'client-demo');

        $this->assertSame(
            'client-assets/client-demo/logo/logo.svg',
            ClientProfile::assetPath('logo/logo.svg')
        );
    }

    public function test_slug_theme_and_asset_profile_read_from_config(): void
    {
        config()->set('ota_client.slug', 'client-demo');
        config()->set('ota_client.theme', 'v2-modern');
        config()->set('ota_client.asset_profile', 'client-demo');

        $this->assertSame('client-demo', ClientProfile::slug());
        $this->assertSame('v2-modern', ClientProfile::theme());
        $this->assertSame('client-demo', ClientProfile::assetProfile());
    }
}
