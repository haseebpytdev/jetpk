<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\TestCase;

class SharedShellIntegrationTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use RefreshDatabase;

    public function test_customer_shell_renders_foundation_nav_and_layout(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);

        $response = $this->actingAs($customer)->get(route('customer.dashboard'));

        $response->assertOk()
            ->assertSee('data-testid="dashboard-shell-customer"', false)
            ->assertSee('data-testid="customer-account-subnav"', false)
            ->assertSee('ota-dashboard-foundation.css', false);
    }

    public function test_agent_shell_renders_permission_scoped_nav(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A0'];

        $this->actingAs($staff)->get(route('agent.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="dashboard-shell-agent"', false)
            ->assertSee('data-testid="agent-portal-subnav"', false)
            ->assertDontSee('>Commissions<', false)
            ->assertDontSee('Agency Staff', false);
    }

    public function test_agent_admin_sees_full_nav_without_admin_leakage(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $admin = $scenario['adminA'];

        $html = $this->actingAs($admin)->get(route('agent.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-testid="agent-portal-subnav"', $html);
        $this->assertStringContainsString('Commissions', $html);
        $this->assertStringContainsString('Agency Staff', $html);
        $this->assertStringNotContainsString('data-testid="dashboard-shell-admin"', $html);
        $this->assertStringNotContainsString('/admin', $html === '' ? '' : strip_tags($html));
    }

    public function test_staff_admin_scaffold_layouts_exist_but_remain_unwired(): void
    {
        $this->assertTrue(View::exists('layouts.staff-console'));
        $this->assertTrue(View::exists('layouts.admin-console'));

        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $staffResponse = $this->actingAs($staff)->get(route('staff.dashboard'));
        $this->assertContains($staffResponse->status(), [200, 302, 403]);
        $staffResponse->assertDontSee('data-testid="dashboard-shell-staff"', false);

        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $adminResponse = $this->actingAs($admin)->get(route('admin.dashboard'));
        $this->assertContains($adminResponse->status(), [200, 302, 403]);
        $adminResponse->assertDontSee('data-testid="dashboard-shell-admin"', false);
    }
}
