<?php

namespace Tests\Feature\Agent;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\TestCase;

/**
 * Final permission matrix UAT for agent staff A0–A11 combinations.
 */
class AgentPortalPermissionMatrixFinalTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use RefreshDatabase;

    public function test_staff_a0_no_permissions_dashboard_and_profile_only(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A0'];
        $booking = $scenario['recordsA']['bookings']['pending'];

        $this->actingAs($staff)->get(route('agent.dashboard'))
            ->assertOk()
            ->assertDontSee('data-testid="agent-finance-summary"', false)
            ->assertDontSee('data-testid="agent-dashboard-wallet-quick"', false)
            ->assertDontSee('New booking', false)
            ->assertDontSee('Commissions', false);

        $this->actingAs($staff)->get(route('profile.edit'))->assertOk();

        foreach ([
            'agent.bookings.index',
            'agent.bookings.create',
            'agent.wallet.show',
            'agent.ledger.index',
            'agent.deposits.index',
            'agent.deposits.create',
            'agent.agency.show',
            'agent.agency.edit',
            'agent.staff.index',
            'agent.travelers.index',
            'agent.support.tickets.index',
            'agent.commissions.index',
        ] as $routeName) {
            $this->actingAs($staff)->get(route($routeName))->assertForbidden();
        }

        $this->actingAs($staff)->get(route('agent.bookings.show', $booking))->assertForbidden();
    }

    public function test_staff_a1_bookings_view_only(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A1'];
        $booking = $scenario['recordsA']['bookings']['pending'];

        $this->actingAs($staff)->get(route('agent.bookings.index'))
            ->assertOk()
            ->assertDontSee('data-testid="agent-bookings-create-link"', false)
            ->assertDontSee('Create booking request', false);

        $this->actingAs($staff)->get(route('agent.bookings.show', $booking))->assertOk();
        $this->actingAs($staff)->get(route('agent.bookings.create'))->assertForbidden();
    }

    public function test_staff_a2_bookings_create(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A2'];

        $this->actingAs($staff)->get(route('agent.bookings.index'))
            ->assertOk()
            ->assertSee('data-testid="agent-bookings-create-link"', false);

        $this->actingAs($staff)->get(route('agent.bookings.create'))->assertOk();
    }

    public function test_staff_a3_wallet_view_without_ledger(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A3'];

        $this->actingAs($staff)->get(route('agent.wallet.show'))
            ->assertOk()
            ->assertSee('data-testid="agent-wallet-kpis"', false)
            ->assertDontSee('data-testid="agent-wallet-view-ledger"', false);

        $this->actingAs($staff)->get(route('agent.ledger.index'))->assertForbidden();
    }

    public function test_staff_a4_ledger_view(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A4'];

        $this->actingAs($staff)->get(route('agent.ledger.index'))
            ->assertOk()
            ->assertSee('data-testid="agent-ledger-table"', false);

        $this->actingAs($staff)->get(route('agent.wallet.show'))
            ->assertOk()
            ->assertSee('data-testid="agent-wallet-view-ledger"', false);
    }

    public function test_staff_a5_payments_upload(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A5'];

        $this->actingAs($staff)->get(route('agent.deposits.create'))
            ->assertOk()
            ->assertSee('data-testid="agent-deposit-form"', false);

        $this->actingAs($scenario['staff']['A3'])->get(route('agent.deposits.create'))->assertForbidden();
        $this->actingAs($scenario['staff']['A3'])->get(route('agent.deposits.index'))
            ->assertOk()
            ->assertDontSee('data-testid="agent-deposits-create-link"', false);
    }

    public function test_staff_a6_agency_view_only(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A6'];

        $this->actingAs($staff)->get(route('agent.agency.show'))
            ->assertOk()
            ->assertSee('data-testid="agent-agency-details"', false)
            ->assertDontSee('data-testid="agent-agency-edit-link"', false);

        $this->actingAs($staff)->get(route('agent.agency.edit'))->assertForbidden();
    }

    public function test_staff_a7_agency_view_only_despite_legacy_edit_permission(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A7'];

        $this->actingAs($staff)->get(route('agent.agency.show'))
            ->assertOk()
            ->assertDontSee('data-testid="agent-agency-edit-link"', false);

        $this->actingAs($staff)->get(route('agent.agency.edit'))->assertForbidden();
    }

    public function test_staff_a8_travelers_manage(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A8'];
        $traveler = $scenario['recordsA']['travelers']['complete'];

        $this->actingAs($staff)->get(route('agent.travelers.index'))->assertOk();
        $this->actingAs($staff)->get(route('agent.travelers.create'))->assertOk();
        $this->actingAs($staff)->get(route('agent.travelers.edit', $traveler))->assertOk();
        $this->actingAs($scenario['staff']['A0'])->get(route('agent.travelers.index'))->assertForbidden();
    }

    public function test_staff_a9_support_manage(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A9'];
        $ticket = $scenario['recordsA']['tickets']['pending'];

        $this->actingAs($staff)->get(route('agent.support.tickets.index'))
            ->assertOk()
            ->assertSee('data-testid="agent-support-create-link"', false);

        $this->actingAs($staff)->get(route('agent.support.tickets.create'))->assertOk();
        $this->actingAs($staff)->get(route('agent.support.tickets.show', $ticket))->assertOk();
        $this->actingAs($scenario['staff']['A0'])->get(route('agent.support.tickets.index'))->assertForbidden();
    }

    public function test_staff_a10_staff_manage(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A10'];
        $target = $scenario['staff']['A0'];

        $this->actingAs($staff)->get(route('agent.staff.index'))
            ->assertOk()
            ->assertSee('data-testid="agent-staff-create-link"', false);

        $this->actingAs($staff)->get(route('agent.staff.create'))->assertOk();
        $this->actingAs($staff)->get(route('agent.staff.edit', $target))->assertOk();
        $this->actingAs($scenario['staff']['A0'])->get(route('agent.staff.index'))->assertForbidden();
    }

    public function test_commissions_are_agent_admin_only(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])->get(route('agent.commissions.index'))->assertOk();
        $this->actingAs($scenario['staff']['A11'])->get(route('agent.commissions.index'))->assertForbidden();

        $this->actingAs($scenario['staff']['A11'])->get(route('agent.dashboard'))
            ->assertOk()
            ->assertDontSee('data-testid="agent-dashboard-commissions-quick"', false)
            ->assertDontSee('Commission earned', false);
    }
}
