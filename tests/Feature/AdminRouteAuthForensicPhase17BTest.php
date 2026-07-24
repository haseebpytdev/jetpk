<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\ClientProfile;
use App\Models\User;
use App\Services\Client\CurrentClientContext;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AdminRouteAuthForensicPhase17BTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedJetpkSingleClientContext();
    }

    public function test_admin_dashboard_route_is_registered_with_expected_middleware(): void
    {
        $this->assertTrue(Route::has('admin.dashboard'));
        $route = Route::getRoutes()->getByName('admin.dashboard');
        $this->assertNotNull($route);
        $this->assertContains('GET', $route->methods());
        $this->assertSame('admin', $route->uri());
        $this->assertSame(
            \App\Http\Controllers\Admin\DashboardController::class.'@index',
            $route->getAction('uses'),
        );

        $middleware = $route->gatherMiddleware();
        $this->assertContains('auth', $middleware);
        $this->assertTrue(
            collect($middleware)->contains(static fn (string $m): bool => str_contains($m, 'account.type')),
        );
    }

    public function test_unauthenticated_get_admin_redirects_to_login_not_public_404(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect();
        $this->assertStringContainsString('login', (string) $response->headers->get('Location'));
        $response->assertDontSee('Page not found', false);
    }

    public function test_platform_admin_get_admin_returns_200_with_jetpakistan_dashboard(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('data-testid="ota-dash-overview"', false)
            ->assertSee('Admin Dashboard', false);
    }

    public function test_customer_and_agent_get_admin_return_forbidden_not_404(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $customer = User::factory()->create(['account_type' => AccountType::Customer]);
        $this->actingAs($customer)->get('/admin')->assertForbidden()->assertSee('Access restricted', false);

        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $this->actingAs($agent)->get('/admin')->assertForbidden()->assertSee('Access restricted', false);
    }

    public function test_staff_get_admin_returns_forbidden(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)->get('/admin')->assertForbidden();
    }

    public function test_admin_home_path_is_not_found_while_admin_root_matches(): void
    {
        $this->get('/admin/home')->assertNotFound();
        $this->get('/admin')->assertRedirect(route('login', absolute: false));
    }

    public function test_forensic_command_render_view_succeeds_for_platform_admin(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->artisan('ota:admin-dashboard-forensic-diagnostic', [
            '--user-id' => $admin->id,
            '--auth-state' => 'platform-admin',
            '--render-view' => true,
            '--correlation' => 'phase17b-render-test',
        ])->assertSuccessful();
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
