<?php

namespace Tests\Feature\Console;

use App\Models\ClientProfile;
use App\Models\ClientProfileModule;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OtaRouteSafetyAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_runs_and_reports_read_only_banner(): void
    {
        $this->artisan('ota:route-safety-audit', ['--client' => 'haseeb-master'])
            ->expectsOutputToContain('Classification: READ-ONLY route registry + collision audit (MC-5C).')
            ->expectsOutputToContain('live_supplier_call_attempted=false db_write_attempted=false')
            ->expectsOutputToContain('Audit summary:')
            ->assertSuccessful();
    }

    public function test_command_output_includes_core_production_routes_as_ok(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'is_master_profile' => true,
        ]);

        $this->artisan('ota:route-safety-audit', ['--client' => 'haseeb-master'])
            ->expectsOutputToContain('Route safety audit passed.')
            ->expectsOutputToContain('Default slug /home → production home')
            ->expectsOutputToContain('Default slug /login → production login')
            ->assertSuccessful();
    }

    public function test_command_fails_when_client_option_is_empty(): void
    {
        $this->artisan('ota:route-safety-audit', ['--client' => ''])
            ->expectsOutputToContain('Option --client must not be empty.')
            ->assertExitCode(1);
    }

    public function test_no_supplier_http_call_attempted(): void
    {
        Http::fake();

        $this->artisan('ota:route-safety-audit', ['--client' => 'haseeb-master'])
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProfile(array $overrides = []): ClientProfile
    {
        $profile = ClientProfile::query()->create(array_merge([
            'name' => 'Test Client',
            'slug' => 'haseeb-master',
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
