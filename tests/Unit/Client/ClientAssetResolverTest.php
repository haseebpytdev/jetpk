<?php

namespace Tests\Unit\Client;

use App\Models\ClientProfile;
use App\Models\ClientProfileBranding;
use App\Models\ClientProfileModule;
use App\Services\Client\ClientAssetResolver;
use App\Services\Client\CurrentClientContext;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAssetResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_preview_client_when_preview_context_is_set(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'preview-client',
            'active_frontend_theme' => 'v2-modern',
            'asset_profile' => 'preview-assets',
        ]);

        ClientProfileBranding::query()->create([
            'client_profile_id' => $profile->id,
            'company_name' => 'Preview Client',
            'logo_path' => 'logo/preview.svg',
            'favicon_path' => 'favicon/preview.ico',
        ]);

        $this->get(route('client.preview.home', ['clientSlug' => 'preview-client']))
            ->assertOk();

        $resolver = app(ClientAssetResolver::class);

        $this->assertSame('v2-modern', $resolver->activeTheme());
        $this->assertSame('preview-assets', $resolver->activeAssetProfile());
        $this->assertSame('themes/frontend/v2-modern/', $resolver->frontendThemePath());
        $this->assertSame(
            asset('client-assets/preview-assets/logo/preview.svg'),
            $resolver->logoUrl(),
        );
        $this->assertSame(
            asset('client-assets/preview-assets/favicon/preview.ico'),
            $resolver->faviconUrl(),
        );
        $this->assertSame(
            asset('client-assets/preview-assets/banners/hero.jpg'),
            $resolver->bannerUrl('hero.jpg'),
        );
    }

    public function test_falls_back_to_config_when_no_preview_context(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'active_frontend_theme' => 'v2-modern',
            'asset_profile' => 'haseeb-master',
        ]);

        config([
            'ota_client.slug' => '',
            'ota_client.theme' => 'v1-classic',
            'ota_client.asset_profile' => 'config-assets',
        ]);

        $context = app(CurrentClientContext::class);
        $this->assertFalse($context->isPreview());
        $this->assertSame('haseeb-master', $context->slug());

        $resolver = app(ClientAssetResolver::class);

        $this->assertSame('v1-classic', $resolver->activeTheme());
        $this->assertSame('config-assets', $resolver->activeAssetProfile());
        $this->assertSame(
            asset('themes/frontend/v1-classic/'),
            $resolver->frontendThemeUrl(),
        );
        $this->assertSame(
            asset('client-assets/config-assets/logo/logo.svg'),
            $resolver->logoUrl(),
        );
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
