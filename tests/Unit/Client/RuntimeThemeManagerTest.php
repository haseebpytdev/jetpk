<?php

namespace Tests\Unit\Client;

use App\Models\ClientProfile;
use App\Models\ClientProfileModule;
use App\Services\Client\RuntimeThemeManager;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuntimeThemeManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_haseeb_master_frontend_theme_from_profile(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'active_frontend_theme' => 'v1-classic',
            'active_admin_theme' => 'default-admin',
            'active_staff_theme' => 'default-staff',
            'is_master_profile' => true,
        ]);

        $manager = app(RuntimeThemeManager::class);

        $this->assertSame('v1-classic', $manager->frontend($profile));
        $this->assertSame('default-admin', $manager->admin($profile));
        $this->assertSame('default-staff', $manager->staff($profile));
        $this->assertSame('themes/frontend/v1-classic', $manager->assetBase('frontend', $profile));
    }

    public function test_falls_back_when_invalid_theme_configured(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'active_frontend_theme' => 'not-a-real-theme',
            'active_admin_theme' => 'admin-dark',
            'active_staff_theme' => 'staff-lite',
            'is_master_profile' => true,
        ]);

        $manager = app(RuntimeThemeManager::class);
        $summary = $manager->summary($profile);

        $this->assertSame('v1-classic', $manager->frontend($profile));
        $this->assertSame('default-admin', $manager->admin($profile));
        $this->assertSame('default-staff', $manager->staff($profile));
        $this->assertTrue($summary['areas']['frontend']['used_fallback']);
        $this->assertTrue($summary['areas']['admin']['used_fallback']);
        $this->assertTrue($summary['areas']['staff']['used_fallback']);
        $this->assertNotEmpty($summary['warnings']);
    }

    public function test_summary_includes_selected_and_resolved_per_area(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'active_frontend_theme' => 'v2-modern',
            'active_admin_theme' => 'bento-admin',
            'active_staff_theme' => 'bento-staff',
            'is_master_profile' => true,
        ]);

        $summary = app(RuntimeThemeManager::class)->summary($profile);

        $this->assertSame('haseeb-master', $summary['client_slug']);
        $this->assertSame('v2-modern', $summary['areas']['frontend']['selected']);
        $this->assertSame('v2-modern', $summary['areas']['frontend']['resolved']);
        $this->assertFalse($summary['areas']['frontend']['used_fallback']);
        $this->assertSame('themes/frontend/v2-modern', $summary['areas']['frontend']['asset_base']);
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
            'active_admin_theme' => 'default-admin',
            'active_staff_theme' => 'default-staff',
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
