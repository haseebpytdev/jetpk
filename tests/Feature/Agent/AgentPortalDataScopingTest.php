<?php

namespace Tests\Feature\Agent;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\TestCase;

class AgentPortalDataScopingTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use RefreshDatabase;

    public function test_agent_a_admin_cannot_view_agent_b_booking(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])
            ->get(route('agent.bookings.show', $scenario['recordsB']['booking']))
            ->assertForbidden();
    }

    public function test_agent_a_staff_cannot_view_agent_b_booking(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A1'])
            ->get(route('agent.bookings.show', $scenario['recordsB']['booking']))
            ->assertForbidden();
    }

    public function test_agent_a_users_cannot_see_agent_b_ledger_transactions(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $txB = $scenario['recordsB']['transaction'];

        $this->actingAs($scenario['adminA'])->get(route('agent.ledger.index'))
            ->assertOk()
            ->assertDontSee('data-testid="agent-ledger-row-'.$txB->id.'"', false)
            ->assertDontSee('LEDGER-B-ONLY', false);

        $this->actingAs($scenario['staff']['A4'])->get(route('agent.ledger.index'))
            ->assertOk()
            ->assertDontSee('data-testid="agent-ledger-row-'.$txB->id.'"', false);
    }

    public function test_agent_a_users_cannot_see_agent_b_deposits_on_wallet_pages(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])->get(route('agent.deposits.index'))
            ->assertOk()
            ->assertDontSee('DEP-B-ONLY', false);

        $this->actingAs($scenario['staff']['A3'])->get(route('agent.deposits.index'))
            ->assertOk()
            ->assertDontSee('DEP-B-ONLY', false);
    }

    public function test_agent_a_users_cannot_manage_agent_b_staff(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staffB = $scenario['recordsB']['staff'];

        $this->actingAs($scenario['adminA'])
            ->get(route('agent.staff.edit', $staffB))
            ->assertForbidden();

        $this->actingAs($scenario['staff']['A10'])
            ->get(route('agent.staff.edit', $staffB))
            ->assertForbidden();
    }

    public function test_agent_a_users_cannot_see_agent_b_travelers(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $travelerB = $scenario['recordsB']['traveler'];

        $this->actingAs($scenario['adminA'])->get(route('agent.travelers.index'))
            ->assertOk()
            ->assertDontSee('Beta Traveler', false);

        $this->actingAs($scenario['staff']['A8'])
            ->get(route('agent.travelers.edit', $travelerB))
            ->assertForbidden();
    }

    public function test_agent_a_users_cannot_see_agent_b_support_tickets(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $ticketB = $scenario['recordsB']['ticket'];

        $this->actingAs($scenario['adminA'])->get(route('agent.support.tickets.index'))
            ->assertOk()
            ->assertDontSee('Ticket B isolation', false);

        $this->actingAs($scenario['staff']['A9'])
            ->get(route('agent.support.tickets.show', $ticketB))
            ->assertForbidden();
    }

    public function test_owner_agent_resolver_uses_agent_a_for_staff(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A1'];

        $this->assertSame($scenario['agentA']->id, $staff->agent()?->id);
        $this->assertSame($scenario['agentA']->id, (int) ($staff->meta['owner_agent_id'] ?? 0));
    }
}
