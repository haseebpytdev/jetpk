<?php

namespace Tests\Feature;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\SupplierConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class Phase22StagingPrepTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_check_passes_with_controlled_setup(): void
    {
        config()->set('app.debug', false);
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('app.url', 'https://jetpakistan.pk');
        config()->set('ota.default_agency_slug', 'asif-travels');
        if (! is_dir(public_path('storage'))) {
            mkdir(public_path('storage'), 0777, true);
        }

        $agency = Agency::factory()->create(['slug' => 'asif-travels']);
        User::factory()->create(['username' => 'admin', 'email' => 'admin@ota.demo', 'current_agency_id' => $agency->id]);
        Airport::query()->create([
            'iata_code' => 'LHE',
            'icao_code' => 'OPLA',
            'name' => 'Allama Iqbal International Airport',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'priority_score' => 250,
            'is_active' => true,
            'is_commercial' => true,
            'has_routes' => true,
            'route_count' => 1,
        ]);
        Airline::query()->create([
            'name' => 'Pakistan International Airlines',
            'iata_code' => 'PK',
            'icao_code' => 'PIA',
            'is_active' => true,
        ]);

        SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Duffel,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['access_token' => 'duffel_test_dummy'],
        ]);

        $this->artisan('ota:production-check')->assertExitCode(0);
    }

    public function test_production_check_fails_when_app_debug_true(): void
    {
        config()->set('app.debug', true);
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('app.url', 'https://jetpakistan.pk');
        config()->set('ota.default_agency_slug', 'asif-travels');

        Agency::factory()->create(['slug' => 'asif-travels']);
        User::factory()->create(['username' => 'admin', 'email' => 'admin@ota.demo']);
        Airport::query()->create([
            'iata_code' => 'LHE',
            'icao_code' => 'OPLA',
            'name' => 'Allama Iqbal International Airport',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'priority_score' => 250,
            'is_active' => true,
            'is_commercial' => true,
            'has_routes' => true,
            'route_count' => 1,
        ]);
        Airline::query()->create([
            'name' => 'Pakistan International Airlines',
            'iata_code' => 'PK',
            'icao_code' => 'PIA',
            'is_active' => true,
        ]);

        $this->artisan('ota:production-check')->assertExitCode(1);
    }

    public function test_error_pages_render_safe_branded_output(): void
    {
        config()->set('app.debug', false);
        $this->get('/non-existent-stage-route')
            ->assertStatus(404)
            ->assertSee('Back to Home')
            ->assertSee('Contact Support')
            ->assertDontSee('Stack trace')
            ->assertDontSee('Ignition');
    }

    public function test_env_example_does_not_contain_real_tokens(): void
    {
        $env = (string) file_get_contents(base_path('.env.example'));
        $this->assertStringNotContainsString('duffel_test_', $env);
        $this->assertStringNotContainsString('duffel_live_', $env);
        $this->assertStringNotContainsString('DUFFEL_ACCESS_TOKEN=', $env);
    }

    public function test_staging_docs_exist(): void
    {
        $this->assertFileExists(base_path('docs/staging-deployment.md'));
        $this->assertFileExists(base_path('docs/staging-smoke-test.md'));
    }

    public function test_route_and_config_cache_commands_are_compatible(): void
    {
        $routeCode = Artisan::call('route:cache');
        $configCode = Artisan::call('config:cache');
        Artisan::call('optimize:clear');

        $this->assertSame(0, $routeCode);
        $this->assertSame(0, $configCode);
    }
}

