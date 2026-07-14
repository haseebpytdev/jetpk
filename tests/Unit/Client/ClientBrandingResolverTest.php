<?php

namespace Tests\Unit\Client;

use App\Models\ClientProfile;
use App\Models\ClientProfileBranding;
use App\Models\ClientProfileModule;
use App\Services\Client\ClientBrandingResolver;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientBrandingResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_db_branding_when_preview_client_exists(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'jetpk',
            'name' => 'Jet Pakistan Profile',
            'asset_profile' => 'jetpk-assets',
        ]);

        ClientProfileBranding::query()->create([
            'client_profile_id' => $profile->id,
            'company_name' => 'Jet Pakistan',
            'logo_path' => 'logo/jetpk.svg',
            'favicon_path' => 'favicon/jetpk.ico',
            'primary_color' => '#112233',
            'secondary_color' => '#445566',
            'accent_color' => '#778899',
            'phone' => '+92 300 1112233',
            'email' => 'hello@jetpakistan.com',
            'address' => 'Karachi, PK',
            'footer_text' => 'Fly with Jet Pakistan',
        ]);

        $this->get(route('client.preview.home', ['clientSlug' => 'jetpk']))
            ->assertOk();

        $resolver = app(ClientBrandingResolver::class);

        $this->assertSame('Jet Pakistan', $resolver->companyName());
        $this->assertSame('#112233', $resolver->primaryColor());
        $this->assertSame('#445566', $resolver->secondaryColor());
        $this->assertSame('#778899', $resolver->accentColor());
        $this->assertSame('+92 300 1112233', $resolver->phone());
        $this->assertSame('hello@jetpakistan.com', $resolver->email());
        $this->assertSame('Karachi, PK', $resolver->address());
        $this->assertSame('Fly with Jet Pakistan', $resolver->footerText());
        $this->assertSame(
            asset('client-assets/jetpk-assets/logo/jetpk.svg'),
            $resolver->logoUrl(),
        );
        $this->assertSame(
            asset('client-assets/jetpk-assets/favicon/jetpk.ico'),
            $resolver->faviconUrl(),
        );
    }

    public function test_falls_back_safely_when_no_db_branding(): void
    {
        config([
            'ota-client.agency_name' => 'Config Agency',
            'ota-client.primary_color' => '#abcdef',
            'ota-client.support_phone' => '+92 300 9998877',
            'ota-client.support_email' => 'support@config.test',
            'ota-client.footer_text' => 'Config footer',
            'ota_client.slug' => '',
            'ota_client.theme' => 'v1-classic',
            'ota_client.asset_profile' => 'config-assets',
        ]);

        $this->makeProfile([
            'slug' => 'haseeb-master',
            'is_master_profile' => true,
        ]);

        $resolver = app(ClientBrandingResolver::class);

        $this->assertSame('Config Agency', $resolver->companyName());
        $this->assertSame('#abcdef', $resolver->primaryColor());
        $this->assertSame('#0ea5e9', $resolver->secondaryColor());
        $this->assertSame('#f59e0b', $resolver->accentColor());
        $this->assertSame('+92 300 9998877', $resolver->phone());
        $this->assertSame('support@config.test', $resolver->email());
        $this->assertSame('Config footer', $resolver->footerText());
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
