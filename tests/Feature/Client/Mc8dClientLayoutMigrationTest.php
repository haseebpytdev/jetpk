<?php

namespace Tests\Feature\Client;

use App\Models\ClientProfile;
use App\Models\ClientProfileModule;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class Mc8dClientLayoutMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagnostic_layout_view_resolves_theme_layout(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'active_frontend_theme' => 'v1-classic',
            'is_master_profile' => true,
        ]);

        $this->assertSame('themes.frontend.v1-classic.layouts.frontend', client_layout('frontend', 'frontend'));
        $this->assertTrue(View::exists('themes.frontend.v1-classic.diagnostics.layout-resolution-smoke'));
    }

    public function test_root_homepage_still_returns_200_after_mc8d(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'active_frontend_theme' => 'v1-classic',
            'is_master_profile' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('MC-8C: theme-resolved homepage', false);
    }

    public function test_haseeb_master_prefixed_home_redirects_to_canonical_home_after_mc8d(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'active_frontend_theme' => 'v1-classic',
            'is_master_profile' => true,
        ]);

        $this->get('/haseeb-master/home')->assertRedirect('/');
    }

    public function test_route_safety_audit_still_passes_after_mc8d(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'is_master_profile' => true,
        ]);

        $this->artisan('ota:route-safety-audit', ['--client' => 'haseeb-master'])
            ->expectsOutputToContain('Route safety audit passed.')
            ->assertSuccessful();
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
            'environment' => 'production',
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
