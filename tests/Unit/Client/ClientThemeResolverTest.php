<?php

namespace Tests\Unit\Client;

use App\Models\ClientProfile;
use App\Models\ClientProfileModule;
use App\Services\Client\ClientThemeResolver;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientThemeResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_db_theme_when_preview_client_exists(): void
    {
        $this->makeProfile([
            'slug' => 'jetpk',
            'active_frontend_theme' => 'v2-modern',
            'active_admin_theme' => 'admin-dark',
            'active_staff_theme' => 'staff-lite',
            'asset_profile' => 'jetpk-assets',
        ]);

        $this->get(route('client.parity.home.alias', ['clientSlug' => 'jetpk']))
            ->assertOk();

        $resolver = app(ClientThemeResolver::class);

        $this->assertSame('v2-modern', $resolver->frontendTheme());
        $this->assertSame('default-admin', $resolver->adminTheme());
        $this->assertSame('default-staff', $resolver->staffTheme());
        $this->assertSame('jetpk-assets', $resolver->assetProfile());
        $this->assertSame(
            asset('themes/frontend/v2-modern/'),
            $resolver->frontendThemeUrl(),
        );
        $this->assertSame(
            asset('themes/admin/default-admin/'),
            $resolver->adminThemeUrl(),
        );
        $this->assertSame(
            asset('themes/staff/default-staff/'),
            $resolver->staffThemeUrl(),
        );
    }

    public function test_falls_back_safely_when_profile_theme_fields_empty(): void
    {
        config([
            'ota_client.slug' => 'fallback-slug',
            'ota_client.theme' => 'v1-classic',
            'ota_client.asset_profile' => 'fallback-assets',
        ]);

        $this->makeProfile([
            'slug' => 'haseeb-master',
            'active_frontend_theme' => '',
            'active_admin_theme' => '',
            'active_staff_theme' => '',
            'asset_profile' => '',
            'is_master_profile' => true,
        ]);

        $resolver = app(ClientThemeResolver::class);

        $this->assertSame('v1-classic', $resolver->frontendTheme());
        $this->assertSame('default-admin', $resolver->adminTheme());
        $this->assertSame('default-staff', $resolver->staffTheme());
        $this->assertSame('fallback-assets', $resolver->assetProfile());
        $this->assertFalse($resolver->themeExists('missing-theme-mc6a-fallback', 'frontend'));
    }

    public function test_theme_exists_checks_registry_and_public_theme_directory(): void
    {
        $themeDir = public_path('themes/frontend/v1-classic');
        $created = false;
        if (! is_dir($themeDir)) {
            mkdir($themeDir, 0755, true);
            $created = true;
        }

        try {
            $resolver = app(ClientThemeResolver::class);
            $this->assertTrue($resolver->themeExists('v1-classic', 'frontend'));
            $this->assertFalse($resolver->themeExists('missing-theme-mc6a', 'frontend'));
        } finally {
            if ($created && is_dir($themeDir)) {
                rmdir($themeDir);
            }
        }
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
