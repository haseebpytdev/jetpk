<?php

namespace Tests\Feature\Agent;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\TestCase;

class AgentPortalUiVisibilityTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use RefreshDatabase;

    public function test_agent_admin_sees_full_finance_and_operations_ctas(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $admin = $scenario['adminA'];
        $staffMember = $scenario['staff']['A0'];
        $booking = $scenario['recordsA']['bookings']['paymentPending'];

        $this->actingAs($admin)->get(route('agent.bookings.index'))
            ->assertSee('Create booking request', false)
            ->assertSee('data-testid="agent-bookings-create-link"', false);

        $this->actingAs($admin)->get(route('agent.wallet.show'))
            ->assertSee('Wallet', false)
            ->assertSee('data-testid="agent-wallet-view-ledger"', false)
            ->assertSee('Request deposit', false)
            ->assertSee('Credit limit', false);

        $this->actingAs($admin)->get(route('agent.ledger.index'))
            ->assertSee('Ledger', false);

        $this->actingAs($admin)->get(route('agent.deposits.index'))
            ->assertSee('New deposit request', false);

        $this->actingAs($admin)->get(route('agent.bookings.show', $booking))
            ->assertSee('Upload proof', false);

        $this->actingAs($admin)->get(route('agent.agency.show'))
            ->assertSee('Edit agency details', false);

        $this->actingAs($admin)->get(route('agent.staff.index'))
            ->assertSee('Add staff user', false);

        $this->actingAs($admin)->get(route('agent.staff.edit', $staffMember))
            ->assertSee('data-testid="agent-staff-edit-form"', false)
            ->assertSee('Disable staff user', false);

        $this->actingAs($admin)->get(route('agent.travelers.index'))
            ->assertSee('Add traveler', false);

        $this->actingAs($admin)->get(route('agent.support.tickets.index'))
            ->assertSee('New ticket', false);

        $this->actingAs($admin)->get(route('agent.support.tickets.show', $scenario['recordsA']['tickets']['pending']))
            ->assertSee('data-testid="customer-support-reply-form"', false);

        $this->actingAs($admin)->get(route('agent.dashboard'))
            ->assertSee('Commissions', false)
            ->assertSee('Wallet balance', false);
    }

    public function test_staff_a0_hides_restricted_nav_and_ctas(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A0'];

        $this->actingAs($staff)->get(route('agent.dashboard'))
            ->assertDontSee('Create booking request', false)
            ->assertDontSee('Wallet balance', false)
            ->assertDontSee('Request deposit', false)
            ->assertDontSee('View ledger', false)
            ->assertDontSee('Commissions', false)
            ->assertDontSee('Add staff user', false);

        $this->actingAs($staff)->get(route('agent.dashboard'))
            ->assertSee('data-testid="agent-portal-subnav"', false);
    }

    public function test_partial_permission_staff_sees_only_allowed_ui(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A1'])->get(route('agent.bookings.index'))
            ->assertSee('My bookings', false)
            ->assertDontSee('Create booking request', false);

        $this->actingAs($scenario['staff']['A4'])->get(route('agent.wallet.show'))
            ->assertSee('View ledger', false);

        $this->actingAs($scenario['staff']['A3'])->get(route('agent.wallet.show'))
            ->assertDontSee('View ledger', false);

        $this->actingAs($scenario['staff']['A11'])->get(route('agent.dashboard'))
            ->assertOk()
            ->assertDontSee('Commission earned', false)
            ->assertDontSee('Commission pending', false);
    }
}
