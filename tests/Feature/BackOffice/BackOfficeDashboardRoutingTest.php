<?php

namespace Tests\Feature\BackOffice;

use App\Enums\AccountType;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class BackOfficeDashboardRoutingTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->seedBackOfficeDashboardExport();
    }

    public function test_generic_dashboard_redirects_admin_to_admin_dashboard(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_generic_dashboard_redirects_staff_to_staff_dashboard(): void
    {
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $this->platformAdmin()->current_agency_id,
        ]);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertRedirect(route('staff.dashboard'));
    }

    public function test_generic_dashboard_redirects_agent_to_agent_dashboard(): void
    {
        $agentUser = User::factory()->create([
            'account_type' => AccountType::Agent,
        ]);

        $this->actingAs($agentUser)
            ->get(route('dashboard'))
            ->assertRedirect(route('agent.dashboard'));
    }

    public function test_generic_dashboard_redirects_customer_to_customer_dashboard(): void
    {
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
        ]);

        $this->actingAs($customer)
            ->get(route('dashboard'))
            ->assertRedirect('/customer/dashboard');
    }

    public function test_legacy_admin_root_redirects_to_admin_dashboard(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertRedirect('/admin/dashboard');
    }

    public function test_legacy_staff_root_redirects_to_staff_dashboard(): void
    {
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $this->platformAdmin()->current_agency_id,
        ]);

        $this->actingAs($staff)
            ->get('/staff')
            ->assertRedirect('/staff/dashboard');
    }

    public function test_admin_dashboard_serves_exported_shell_when_authenticated(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8');
    }

    public function test_staff_dashboard_requires_staff_account_type(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get('/staff/dashboard')
            ->assertForbidden();
    }

    public function test_admin_dashboard_requires_platform_admin(): void
    {
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $this->platformAdmin()->current_agency_id,
        ]);

        $this->actingAs($staff)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_testdash_redirects_authenticated_admin_to_admin_dashboard(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get('/testdash/bookings')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_testdash_redirects_unauthenticated_to_login(): void
    {
        $this->get('/testdash')
            ->assertRedirect(route('login'));
    }

    public function test_admin_nested_dashboard_route_serves_shell(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get('/admin/dashboard/bookings')
            ->assertOk();
    }

    private function seedBackOfficeDashboardExport(): void
    {
        $portals = ['admin', 'staff'];
        foreach ($portals as $portal) {
            $base = storage_path("app/back-office-dashboard/{$portal}/dashboard");
            File::ensureDirectoryExists($base);
            File::put("{$base}/index.html", '<!DOCTYPE html><html><body>JetPakistan</body></html>');
            File::ensureDirectoryExists("{$base}/bookings");
            File::put("{$base}/bookings/index.html", '<!DOCTYPE html><html><body>Bookings</body></html>');
        }
    }
}
