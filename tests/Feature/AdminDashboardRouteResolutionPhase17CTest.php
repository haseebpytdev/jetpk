<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\ClientProfile;
use App\Models\User;
use App\Services\Client\CurrentClientContext;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AdminDashboardRouteResolutionPhase17CTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedJetpkSingleClientContext();
    }

    public function test_route_admin_dashboard_generates_admin_uri(): void
    {
        $this->assertSame(url('/admin'), route('admin.dashboard'));
    }

    public function test_guest_get_admin_redirects_to_login_with_admin_dashboard_route(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect();
        $this->assertSame('admin.dashboard', $this->matchRouteName('/admin'));
        $this->assertStringContainsString('login', (string) $response->headers->get('Location'));
    }

    public function test_platform_admin_without_password_force_receives_dashboard(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
            'must_change_password' => false,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('data-testid="ota-dash-overview"', false);
    }

    public function test_platform_admin_with_forced_password_change_redirects_to_live_password_url(): void
    {
        Config::set('app.url', 'https://jetpakistan.pk');
        URL::forceRootUrl('https://jetpakistan.pk');
        URL::forceScheme('https');

        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($admin)->get('/admin');
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('jetpakistan.pk/password/force-change', $location);
        $this->assertStringNotContainsString('localhost', $location);
    }

    public function test_customer_agent_staff_cannot_access_admin_dashboard(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $customer = User::factory()->create(['account_type' => AccountType::Customer]);
        $this->actingAs($customer)->get('/admin')->assertForbidden();

        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $this->actingAs($agent)->get('/admin')->assertForbidden();

        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $this->actingAs($staff)->get('/admin')->assertForbidden();
    }

    public function test_forensic_diagnostic_simulated_redirect_uses_configured_host(): void
    {
        Config::set('app.url', 'https://jetpakistan.pk');

        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
            'must_change_password' => true,
        ]);

        $this->artisan('ota:admin-dashboard-forensic-diagnostic', [
            '--user-id' => $admin->id,
            '--auth-state' => 'platform-admin',
            '--render-view' => true,
            '--simulate-host' => 'jetpakistan.pk',
            '--simulate-scheme' => 'https',
            '--correlation' => 'phase17c-redirect-host',
        ])->assertSuccessful();
    }

    protected function matchRouteName(string $uri): string
    {
        $request = Request::create($uri, 'GET');
        $route = Route::getRoutes()->match($request);

        return (string) $route->getName();
    }

    protected function seedJetpkSingleClientContext(): void
    {
        Config::set([
            'ota_client.slug' => 'jetpk',
            'ota_client.single_client_mode' => true,
            'ota_client.single_client_root' => true,
        ]);

        $profile = ClientProfile::query()->firstOrCreate(
            ['slug' => 'jetpk'],
            [
                'name' => 'Jet Pakistan',
                'environment' => 'staging',
                'active_frontend_theme' => 'jetpakistan',
                'active_admin_theme' => 'jetpakistan',
                'active_staff_theme' => 'jetpakistan',
                'asset_profile' => 'jetpk-assets',
                'default_locale' => 'en',
                'timezone' => 'Asia/Karachi',
                'currency' => 'PKR',
                'is_master_profile' => false,
                'is_active' => true,
            ],
        );
        app(CurrentClientContext::class)->set($profile);
    }
}
