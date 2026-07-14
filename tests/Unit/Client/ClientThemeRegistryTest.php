<?php

namespace Tests\Unit\Client;

use App\Services\Client\ClientThemeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientThemeRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_frontend_admin_and_staff_themes(): void
    {
        $registry = app(ClientThemeRegistry::class);

        $this->assertCount(6, $registry->all());
        $this->assertCount(2, $registry->all('frontend'));
        $this->assertCount(2, $registry->all('admin'));
        $this->assertCount(2, $registry->all('staff'));
    }

    public function test_active_filters_inactive_themes(): void
    {
        config([
            'client_themes.areas.frontend.themes.inactive-demo' => [
                'key' => 'inactive-demo',
                'name' => 'Inactive Demo',
                'area' => 'frontend',
                'version' => '1',
                'status' => 'inactive',
                'asset_base' => 'themes/frontend/inactive-demo',
                'preview_image' => null,
                'description' => 'Inactive test theme.',
                'supports' => ['css'],
            ],
        ]);

        $registry = app(ClientThemeRegistry::class);

        $this->assertFalse($registry->validateTheme('inactive-demo', 'frontend'));
        $this->assertNotContains('inactive-demo', array_column($registry->active('frontend'), 'key'));
    }

    public function test_fallback_returns_area_defaults(): void
    {
        $registry = app(ClientThemeRegistry::class);

        $this->assertSame('v1-classic', $registry->fallback('frontend'));
        $this->assertSame('default-admin', $registry->fallback('admin'));
        $this->assertSame('default-staff', $registry->fallback('staff'));
    }

    public function test_asset_base_uses_registry_or_synthesizes_path(): void
    {
        $registry = app(ClientThemeRegistry::class);

        $this->assertSame('themes/frontend/v2-modern', $registry->assetBase('v2-modern', 'frontend'));
        $this->assertSame('themes/admin/bento-admin', $registry->assetBase('bento-admin', 'admin'));
        $this->assertSame('themes/staff/unknown-staff', $registry->assetBase('unknown-staff', 'staff'));
    }

    public function test_get_exists_and_validate_theme(): void
    {
        $registry = app(ClientThemeRegistry::class);

        $this->assertTrue($registry->exists('v1-classic', 'frontend'));
        $this->assertTrue($registry->validateTheme('v1-classic', 'frontend'));
        $this->assertFalse($registry->exists('missing-theme', 'frontend'));
        $this->assertFalse($registry->validateTheme('missing-theme', 'frontend'));

        $theme = $registry->get('bento-staff', 'staff');
        $this->assertIsArray($theme);
        $this->assertSame('bento-staff', $theme['key']);
        $this->assertSame('staff', $theme['area']);
    }
}
