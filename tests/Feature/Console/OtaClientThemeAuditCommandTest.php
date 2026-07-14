<?php

namespace Tests\Feature\Console;

use App\Models\ClientProfile;
use App\Models\ClientProfileModule;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtaClientThemeAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_runs_and_reports_read_only_banner(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'is_master_profile' => true,
        ]);

        $this->artisan('ota:client-theme-audit', ['--client' => 'haseeb-master'])
            ->expectsOutputToContain('Classification: READ-ONLY client theme audit (MC-8A).')
            ->expectsOutputToContain('db_write_attempted=false')
            ->expectsOutputToContain('Client slug: haseeb-master')
            ->assertSuccessful();
    }

    public function test_command_reports_resolved_themes_for_haseeb_master(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'active_frontend_theme' => 'v1-classic',
            'active_admin_theme' => 'default-admin',
            'active_staff_theme' => 'default-staff',
            'is_master_profile' => true,
        ]);

        $this->artisan('ota:client-theme-audit', ['--client' => 'haseeb-master'])
            ->expectsOutputToContain('v1-classic')
            ->expectsOutputToContain('default-admin')
            ->expectsOutputToContain('default-staff')
            ->expectsOutputToContain('asset_base')
            ->expectsOutputToContain('Client theme audit completed for haseeb-master.')
            ->assertSuccessful();
    }

    public function test_command_fails_when_client_option_is_empty(): void
    {
        $this->artisan('ota:client-theme-audit', ['--client' => ''])
            ->expectsOutputToContain('Option --client must not be empty.')
            ->assertExitCode(1);
    }

    public function test_command_fails_when_profile_missing(): void
    {
        $this->artisan('ota:client-theme-audit', ['--client' => 'missing-client'])
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
