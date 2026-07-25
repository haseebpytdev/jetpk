<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class DashboardFoundationDeploymentReadinessTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use JetpkHomepageFixture;
    use RefreshDatabase;

    /** @var list<string> */
    private const APPROVED_RUNTIME_FILES = [
        'resources/views/components/dashboard/flash.blade.php',
        'resources/views/components/dashboard/loading.blade.php',
        'resources/views/components/dashboard/page-header.blade.php',
        'resources/views/components/dashboard/permission-denied.blade.php',
        'resources/views/components/dashboard/responsive-table.blade.php',
        'resources/views/components/dashboard/shell.blade.php',
        'resources/views/components/dashboard/sidebar.blade.php',
        'resources/views/components/dashboard/topbar.blade.php',
        'public/css/ota-customer-dashboard.css',
        'public/css/ota-dashboard-foundation.css',
        'public/js/ota-dashboard-foundation.js',
        'resources/views/layouts/agent-portal.blade.php',
        'resources/views/layouts/customer-account.blade.php',
        'resources/views/dashboard/customer/dashboard.blade.php',
        'resources/views/dashboard/customer/index.blade.php',
        'resources/views/dashboard/customer/bookings/index.blade.php',
    ];

    /** @var list<string> */
    private const APPROVED_VIEWS = [
        'components.dashboard.flash',
        'components.dashboard.loading',
        'components.dashboard.page-header',
        'components.dashboard.permission-denied',
        'components.dashboard.responsive-table',
        'components.dashboard.shell',
        'components.dashboard.sidebar',
        'components.dashboard.topbar',
        'layouts.customer-account',
        'layouts.agent-portal',
        'dashboard.customer.dashboard',
        'dashboard.customer.index',
        'dashboard.customer.bookings.index',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeJetpkProfile();
    }

    public function test_approved_dashboard_runtime_files_exist_on_disk_and_resolve_in_laravel(): void
    {
        foreach (self::APPROVED_RUNTIME_FILES as $path) {
            $this->assertFileExists(base_path($path), "Missing runtime file: {$path}");
        }

        foreach (self::APPROVED_VIEWS as $view) {
            $this->assertTrue(View::exists($view), "Missing approved view: {$view}");
        }
    }

    public function test_jetpk_customer_dashboard_loads_shared_foundation_stylesheet(): void
    {
        Http::fake();
        $customer = $this->jetpkCustomer();

        $html = $this->actingAs($customer)->get(route('customer.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('ota-dashboard-foundation.css', $html);
        $this->assertStringContainsString('data-testid="jp-customer-dashboard"', $html);
        $this->assertStringNotContainsString('Parwaaz', $html);
        $this->assertStringNotContainsString('haseeb-master', $html);
        $this->assertStringNotContainsString('ota.haseebasif.com', $html);
        Http::assertNothingSent();
    }

    public function test_jetpk_customer_bookings_index_renders_portal_filters(): void
    {
        Http::fake();
        $customer = $this->jetpkCustomer();

        $html = $this->actingAs($customer)->get(route('customer.bookings.index'))->assertOk()->getContent();

        $this->assertStringContainsString('ota-dashboard-foundation.css', $html);
        $this->assertStringContainsString('Pending payment', $html);
        Http::assertNothingSent();
    }

    public function test_jetpk_agent_dashboard_loads_shared_foundation_stylesheet(): void
    {
        Http::fake();
        $scenario = $this->buildAgentPortalScenario();
        $admin = $scenario['adminA'];

        $html = $this->actingAs($admin)->get(route('agent.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('ota-dashboard-foundation.css', $html);
        $this->assertStringContainsString('data-testid="agent-portal-subnav"', $html);
        Http::assertNothingSent();
    }

    public function test_legacy_customer_account_layout_references_foundation_assets(): void
    {
        $source = file_get_contents(resource_path('views/layouts/customer-account.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('ota-dashboard-foundation.css', $source);
        $this->assertStringContainsString('ota-dashboard-foundation.js', $source);
        $this->assertStringContainsString('<x-dashboard.shell', $source);
    }

    private function jetpkCustomer(): User
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->seedJetpkAgency();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);
        Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
            'booking_reference' => 'BKG-JP-1001',
            'route' => 'LHE-DXB',
            'travel_date' => now()->addDays(6)->toDateString(),
        ]);

        return $customer;
    }
}
