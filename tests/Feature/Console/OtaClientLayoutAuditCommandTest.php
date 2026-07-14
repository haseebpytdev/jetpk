<?php

namespace Tests\Feature\Console;

use App\Models\ClientProfile;
use App\Models\ClientProfileModule;
use App\Services\Client\RuntimeViewResolver;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtaClientLayoutAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_runs_and_reports_read_only_banner(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'is_master_profile' => true,
        ]);

        $this->artisan('ota:client-layout-audit', ['--client' => 'haseeb-master'])
            ->expectsOutputToContain('Classification: READ-ONLY client layout resolution audit (MC-8D).')
            ->expectsOutputToContain('db_write_attempted=false')
            ->expectsOutputToContain('Client slug: haseeb-master')
            ->assertSuccessful();
    }

    public function test_command_passes_for_haseeb_master_with_sample_resolution(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'active_frontend_theme' => 'v1-classic',
            'active_admin_theme' => 'default-admin',
            'active_staff_theme' => 'default-staff',
            'is_master_profile' => true,
        ]);

        $resolution = app(RuntimeViewResolver::class)
            ->resolveLayoutSample('frontend', 'frontend', $profile);
        $this->assertSame('themes.frontend.v1-classic.layouts.frontend', $resolution['resolved_layout_name']);

        $this->artisan('ota:client-layout-audit', ['--client' => 'haseeb-master'])
            ->expectsOutputToContain('Sample layout resolution')
            ->expectsOutputToContain('frontend layout')
            ->expectsOutputToContain('auth layout')
            ->expectsOutputToContain('admin dashboard layout')
            ->expectsOutputToContain('Resolved layout paths')
            ->expectsOutputToContain('MC-8D: client_layout()')
            ->expectsOutputToContain('Client layout audit completed for haseeb-master.')
            ->assertSuccessful();
    }

    public function test_command_fails_when_client_option_is_empty(): void
    {
        $this->artisan('ota:client-layout-audit', ['--client' => ''])
            ->expectsOutputToContain('Option --client must not be empty.')
            ->assertExitCode(1);
    }

    public function test_command_fails_when_profile_missing(): void
    {
        $this->artisan('ota:client-layout-audit', ['--client' => 'missing-client'])
            ->expectsOutputToContain('Client profile not found for slug: missing-client')
            ->assertExitCode(1);
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
