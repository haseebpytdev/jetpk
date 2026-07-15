<?php

namespace Tests\Feature\Agent;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\TestCase;

/**
 * Final support and traveler UAT for agent portal permission and scoping.
 */
class AgentPortalSupportTravelerFinalTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use RefreshDatabase;

    public function test_support_ticket_list_create_show_reply_permission_behavior(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $ticket = $scenario['recordsA']['tickets']['pending'];

        $this->actingAs($scenario['staff']['A9'])->get(route('agent.support.tickets.index'))
            ->assertOk()
            ->assertSee('Ticket pending by staff A9', false)
            ->assertSee('data-testid="agent-support-create-link"', false);

        $this->actingAs($scenario['staff']['A9'])->get(route('agent.support.tickets.create'))->assertOk();
        $this->actingAs($scenario['staff']['A9'])->get(route('agent.support.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('data-testid="customer-support-reply-form"', false);

        $this->actingAs($scenario['staff']['A0'])->get(route('agent.support.tickets.index'))->assertForbidden();
    }

    public function test_agent_admin_sees_staff_created_support_tickets(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])->get(route('agent.support.tickets.index'))
            ->assertOk()
            ->assertSee('Ticket open by admin A', false)
            ->assertSee('Ticket pending by staff A9', false);
    }

    public function test_support_tickets_scoped_to_owner_agent_agency(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])->get(route('agent.support.tickets.index'))
            ->assertDontSee('Ticket B isolation', false);

        $this->actingAs($scenario['staff']['A9'])
            ->get(route('agent.support.tickets.show', $scenario['recordsB']['ticket']))
            ->assertForbidden();
    }

    public function test_travelers_list_create_edit_delete_permission_behavior(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $traveler = $scenario['recordsA']['travelers']['complete'];

        $this->actingAs($scenario['staff']['A8'])->get(route('agent.travelers.index'))
            ->assertOk()
            ->assertSee('Complete Traveler', false)
            ->assertSee('Add traveler', false);

        $this->actingAs($scenario['staff']['A8'])->get(route('agent.travelers.create'))->assertOk();
        $this->actingAs($scenario['staff']['A8'])->get(route('agent.travelers.edit', $traveler))->assertOk();
        $this->actingAs($scenario['staff']['A0'])->get(route('agent.travelers.index'))->assertForbidden();
    }

    public function test_incomplete_and_default_traveler_display(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])->get(route('agent.travelers.index'))
            ->assertOk()
            ->assertSee('Complete Traveler', false)
            ->assertSee('Incomplete Traveler', false);
    }

    public function test_travelers_scoped_and_agent_b_traveler_blocked(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])->get(route('agent.travelers.index'))
            ->assertDontSee('Beta Traveler', false);

        $this->actingAs($scenario['staff']['A8'])
            ->get(route('agent.travelers.edit', $scenario['recordsB']['traveler']))
            ->assertForbidden();
    }
}
