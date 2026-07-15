<?php

namespace Tests\Feature\Platform;

use App\Models\DeveloperUser;
use App\Models\PlatformModuleSetting;
use App\Models\User;
use App\Services\Platform\PlatformModuleSettingsService;
use App\Support\Platform\PlatformModuleGate;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class PlatformModuleNavigationVisibilityTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OtaFoundationSeeder::class);
        Config::set('ota-developer.enabled', true);
    }

    public function test_public_flight_search_planned_off_hides_home_search_and_blocks_results_route(): void
    {
        $this->planModuleOff('public_flight_search');

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-testid="public-home-flight-search"', false)
            ->assertDontSee('ota-hero-search-card', false)
            ->assertDontSee('Leaving from', false);

        $this->assertTrue(PlatformModuleGate::allows('public_flight_search'));

        $this->get(route('flights.results', [
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => now()->addDays(30)->format('Y-m-d'),
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ]))->assertForbidden();
    }

    public function test_customer_booking_lookup_planned_off_hides_lookup_nav_but_route_works(): void
    {
        $this->planModuleOff('customer_booking_lookup');

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('>Booking</a>', false);

        $this->get(route('booking.lookup'))
            ->assertForbidden()
            ->assertSee('This module is disabled for this deployment.', false);
    }

    public function test_agent_wallet_planned_off_hides_wallet_nav_and_blocks_wallet_route(): void
    {
        $this->planModuleOff('agent_wallet');

        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agent)
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->assertDontSee('Wallet / Deposits', false)
            ->assertDontSee('data-testid="agent-sidebar-wallet"', false);

        $this->actingAs($agent)
            ->get(route('agent.wallet.show'))
            ->assertForbidden()
            ->assertSee('This module is disabled for this deployment.', false);
    }

    public function test_agent_deposits_planned_off_hides_deposits_subnav_and_blocks_deposits_index(): void
    {
        $this->planModuleOff('agent_deposits');

        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agent)
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->assertDontSee('>Deposits</a>', false);

        $this->actingAs($agent)
            ->get(route('agent.deposits.index'))
            ->assertForbidden()
            ->assertSee('This module is disabled for this deployment.', false);
    }

    public function test_agent_reports_planned_off_hides_reports_subnav(): void
    {
        $this->planModuleOff('agent_reports');

        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agent)
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->assertDontSee('Agency Reports', false)
            ->assertDontSee('>Statement</a>', false);
    }

    public function test_agent_staff_planned_off_hides_staff_subnav(): void
    {
        $this->planModuleOff('agent_staff');

        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agent)
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->assertDontSee('>Staff</a>', false);
    }

    public function test_agent_ledger_planned_off_hides_ledger_subnav_and_blocks_ledger_routes(): void
    {
        $this->planModuleOff('agent_ledger');

        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agent)
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->assertDontSee('My Ledger', false)
            ->assertDontSee('Accounting Ledger', false);

        $this->actingAs($agent)
            ->get(route('agent.ledger.index'))
            ->assertForbidden()
            ->assertSee('This module is disabled for this deployment.', false);

        $this->actingAs($agent)
            ->get(route('agent.accounting.ledger.index'))
            ->assertForbidden();
    }

    public function test_saved_travelers_planned_off_hides_travelers_in_customer_and_agent_nav(): void
    {
        $this->planModuleOff('saved_travelers');

        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $this->actingAs($customer)
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertDontSee('data-testid="customer-sidebar-travelers"', false);

        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $this->actingAs($agent)
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->assertDontSee('data-testid="agent-sidebar-travelers"', false);

        $this->actingAs($agent)->get(route('agent.travelers.index'))->assertForbidden();
    }

    public function test_support_system_planned_off_hides_support_links(): void
    {
        $this->planModuleOff('support_system');

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('support'), false);

        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $this->actingAs($customer)
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertDontSee(route('customer.support.tickets.index'), false);

        $this->get(route('support'))
            ->assertForbidden();
    }

    public function test_api_settings_planned_off_hides_admin_api_settings_nav(): void
    {
        $this->planModuleOff('api_settings');

        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('data-testid="admin-nav-api-settings"', false)
            ->assertDontSee('>API Settings</a>', false);

        $this->actingAs($admin)->get(route('admin.api-settings'))->assertForbidden();
    }

    public function test_branding_settings_planned_off_hides_branding_and_media_nav(): void
    {
        $this->planModuleOff('branding_settings');

        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('>Branding</a>', false)
            ->assertDontSee('>Media Library</a>', false)
            ->assertDontSee('>Homepage</a>', false);
    }

    public function test_markup_settings_planned_off_hides_markup_nav(): void
    {
        $this->planModuleOff('markup_settings');

        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('data-testid="admin-nav-markups"', false);

        $this->actingAs($admin)->get(route('admin.markups'))->assertForbidden();
    }

    public function test_finance_reports_planned_off_hides_finance_and_report_nav(): void
    {
        $this->planModuleOff('finance_reports');

        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('data-testid="admin-nav-finance-dashboard"', false)
            ->assertDontSee('Platform Reports', false);

        $this->actingAs($admin)->get(route('admin.finance.dashboard'))->assertForbidden();
    }

    public function test_admin_portal_remains_visible_when_db_plans_it_off(): void
    {
        PlatformModuleSetting::query()->create([
            'module_key' => 'admin_portal',
            'enabled' => false,
        ]);
        app(PlatformModuleSettingsService::class)->forgetCache();

        $this->assertTrue(PlatformModuleGate::visible('admin_portal'));
        $this->assertTrue(PlatformModuleGate::allows('admin_portal'));

        $admin = $this->platformAdmin();
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('>Dashboard</a>', false);
    }

    public function test_developer_cp_reachable_when_other_modules_planned_off(): void
    {
        foreach (['public_flight_search', 'agent_portal', 'customer_portal', 'support_system'] as $key) {
            $this->planModuleOff($key);
        }

        $developer = DeveloperUser::query()->create([
            'name' => 'Dev Owner',
            'email' => 'dev-nav@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->get(route('dev.cp.modules.index'))
            ->assertOk()
            ->assertSee('Deployment Control Panel', false)
            ->assertSee('data-testid="dev-cp-deployment-modes"', false);
    }

    public function test_allows_returns_true_for_planned_disabled_known_module(): void
    {
        $this->planModuleOff('agent_portal');

        $this->assertFalse(PlatformModuleGate::visible('agent_portal'));
        $this->assertTrue(PlatformModuleGate::allows('agent_portal'));
    }

    private function planModuleOff(string $key): void
    {
        PlatformModuleSetting::query()->create([
            'module_key' => $key,
            'enabled' => false,
        ]);
        app(PlatformModuleSettingsService::class)->forgetCache();
    }
}
