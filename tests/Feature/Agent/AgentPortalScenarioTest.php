<?php

namespace Tests\Feature\Agent;

use App\Enums\AccountType;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\TestCase;

class AgentPortalScenarioTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_for_agent_portal(): void
    {
        $this->get(route('agent.dashboard'))->assertRedirect(route('login'));
    }

    public function test_agent_admin_can_access_all_agent_portal_routes(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $admin = $scenario['adminA'];
        $booking = $scenario['recordsA']['bookings']['pending'];

        $routes = [
            route('agent.dashboard'),
            route('agent.bookings.index'),
            route('agent.bookings.create'),
            route('agent.bookings.show', $booking),
            route('agent.wallet.show'),
            route('agent.ledger.index'),
            route('agent.deposits.index'),
            route('agent.deposits.create'),
            route('agent.commissions.index'),
            route('agent.travelers.index'),
            route('agent.support.tickets.index'),
            route('agent.agency.show'),
            route('agent.agency.edit'),
            route('agent.staff.index'),
            route('profile.edit'),
        ];

        foreach ($routes as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_customer_cannot_access_agent_portal(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $customer = User::query()->where('account_type', AccountType::Customer)->firstOrFail();

        $this->actingAs($customer)->get(route('agent.dashboard'))->assertForbidden();
    }

    public function test_platform_staff_cannot_access_agent_portal(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('account_type', AccountType::Staff)->firstOrFail();

        $this->actingAs($staff)->get(route('agent.dashboard'))->assertForbidden();
    }

    public function test_agent_staff_lands_on_agent_dashboard_not_staff_or_admin(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A1'];

        $this->actingAs($staff)->get(route('agent.dashboard'))->assertOk();
        $this->actingAs($staff)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($staff)->get(route('staff.dashboard'))->assertForbidden();
    }

    public function test_agent_staff_with_broad_permissions_can_access_allowed_modules(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A11'];
        $booking = $scenario['recordsA']['bookings']['pending'];

        $this->actingAs($staff)->get(route('agent.bookings.index'))->assertOk();
        $this->actingAs($staff)->get(route('agent.bookings.create'))->assertOk();
        $this->actingAs($staff)->get(route('agent.bookings.show', $booking))->assertOk();
        $this->actingAs($staff)->get(route('agent.wallet.show'))->assertOk();
        $this->actingAs($staff)->get(route('agent.ledger.index'))->assertOk();
        $this->actingAs($staff)->get(route('agent.deposits.create'))->assertOk();
        $this->actingAs($staff)->get(route('agent.agency.show'))->assertOk();
        $this->actingAs($staff)->get(route('agent.agency.edit'))->assertForbidden();
        $this->actingAs($staff)->get(route('agent.staff.index'))->assertOk();
        $this->actingAs($staff)->get(route('agent.travelers.index'))->assertOk();
        $this->actingAs($staff)->get(route('agent.support.tickets.index'))->assertOk();
        $this->actingAs($staff)->get(route('agent.commissions.index'))->assertForbidden();
    }
}
