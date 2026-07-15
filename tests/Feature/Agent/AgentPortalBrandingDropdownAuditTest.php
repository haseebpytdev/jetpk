<?php

namespace Tests\Feature\Agent;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\TestCase;

class AgentPortalBrandingDropdownAuditTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use RefreshDatabase;

    public function test_guest_sees_default_ota_header_title(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-testid="header-brand-name"', false)
            ->assertDontSee('data-testid="footer-partner-agency"', false);
    }

    public function test_agent_admin_sees_agency_name_in_header_not_personal_name(): void
    {
        $scenario = $this->prepareJetPakistanAdmin('Asif');

        $this->actingAs($scenario['admin'])
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="header-brand-name">JetPakistan<', false)
            ->assertSee('data-testid="footer-partner-agency">Partner Agency: JetPakistan<', false)
            ->assertDontSee('data-testid="header-brand-name">Asif<', false);
    }

    public function test_agent_staff_sees_owner_agency_name_in_header(): void
    {
        $scenario = $this->prepareJetPakistanAdmin('Asif');
        $staff = $this->createAgentStaffUser(
            $scenario['agent'],
            'ali@jetpakistan-staff.test',
            [],
            'Ali Raza',
        );

        $this->actingAs($staff)
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="header-brand-name">JetPakistan<', false)
            ->assertSee('data-testid="footer-partner-agency">Partner Agency: JetPakistan<', false);
    }

    public function test_header_logo_markup_unchanged_for_agent_portal(): void
    {
        $scenario = $this->prepareJetPakistanAdmin('Asif');

        $html = $this->actingAs($scenario['admin'])
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="ota-brand ota-brand-with-mark"', $html);
        $this->assertStringContainsString('class="ota-brand-mark"', $html);
    }

    public function test_agent_admin_dropdown_is_compact_with_balance(): void
    {
        $scenario = $this->prepareJetPakistanAdmin('Asif');

        $this->actingAs($scenario['admin'])
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="account-dropdown-link-dashboard"', false)
            ->assertSee('data-testid="account-dropdown-link-bookings"', false)
            ->assertSee('data-testid="account-dropdown-link-profile-settings"', false)
            ->assertSee('data-testid="account-dropdown-link-agency-settings"', false)
            ->assertSee('data-testid="account-dropdown-link-logout"', false)
            ->assertSee('data-testid="account-dropdown-balance"', false)
            ->assertSee('Available Balance', false)
            ->assertSee('PKR 75,000.00', false)
            ->assertDontSee('data-testid="account-dropdown-link-wallet"', false)
            ->assertDontSee('data-testid="account-dropdown-link-deposits"', false)
            ->assertDontSee('data-testid="account-dropdown-link-travelers"', false)
            ->assertDontSee('data-testid="account-dropdown-link-support-tickets"', false)
            ->assertDontSee('data-testid="account-dropdown-link-commissions"', false);
    }

    public function test_agent_staff_a0_dropdown_minimal(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A0'];

        $this->actingAs($staff)
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="account-dropdown-link-dashboard"', false)
            ->assertSee('data-testid="account-dropdown-link-profile-settings"', false)
            ->assertSee('data-testid="account-dropdown-link-logout"', false)
            ->assertDontSee('data-testid="account-dropdown-link-bookings"', false)
            ->assertDontSee('data-testid="account-dropdown-link-agency-settings"', false)
            ->assertDontSee('data-testid="account-dropdown-balance"', false);
    }

    public function test_agent_staff_with_bookings_view_sees_bookings_link(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A1'])
            ->get(route('agent.dashboard'))
            ->assertSee('data-testid="account-dropdown-link-bookings"', false);
    }

    public function test_agent_staff_with_agency_view_sees_agency_settings(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A6'])
            ->get(route('agent.dashboard'))
            ->assertSee('data-testid="account-dropdown-link-agency-settings"', false);
    }

    public function test_agent_staff_with_wallet_view_sees_balance_linked_to_wallet(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A3'])
            ->get(route('agent.dashboard'))
            ->assertSee('data-testid="account-dropdown-balance"', false)
            ->assertSee(route('agent.wallet.show'), false);
    }

    public function test_agent_admin_can_edit_agency_staff_cannot_even_with_legacy_permission(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])
            ->get(route('agent.agency.edit'))
            ->assertOk();

        $this->actingAs($scenario['staff']['A7'])
            ->get(route('agent.agency.show'))
            ->assertOk()
            ->assertDontSee('data-testid="agent-agency-edit-link"', false);

        $this->actingAs($scenario['staff']['A7'])
            ->get(route('agent.agency.edit'))
            ->assertForbidden();

        $this->actingAs($scenario['staff']['A7'])
            ->patch(route('agent.agency.update'), ['agency_name' => 'Blocked Staff Edit'])
            ->assertForbidden();
    }

    public function test_agent_staff_with_agency_view_can_open_show_only(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A6'])
            ->get(route('agent.agency.show'))
            ->assertOk()
            ->assertSee('data-testid="agent-agency-details"', false);
    }

    public function test_audit_identity_format_and_fallbacks(): void
    {
        $scenario = $this->prepareJetPakistanAdmin('Asif');
        $this->assertSame('AGT-JetPakistan-Asif', $scenario['admin']->fresh()->agentAuditIdentity());

        $staff = $this->createAgentStaffUser(
            $scenario['agent'],
            'staff-audit@jetpakistan.test',
            [],
            'Ali Raza',
        );
        $this->assertSame('AGT-JetPakistan-AliRaza', $staff->agentAuditIdentity());

        $agentNoName = Agent::factory()->create([
            'agency_id' => $scenario['agency']->id,
            'user_id' => User::factory()->agent()->create([
                'name' => '',
                'current_agency_id' => $scenario['agency']->id,
            ])->id,
            'meta' => ['agency_name' => 'Easy Ticket'],
        ]);
        $userNoName = $agentNoName->user;
        $this->assertStringStartsWith('AGT-EasyTicket-', $userNoName?->agentAuditIdentity() ?? '');

        $orphanStaff = User::factory()->agentStaff()->create([
            'name' => 'Sara',
            'meta' => ['agent_permissions' => []],
        ]);
        $this->assertSame('AGT-UnknownAgency-Sara', $orphanStaff->agentAuditIdentity());
        $this->assertLessThanOrEqual(64, mb_strlen($orphanStaff->agentAuditIdentity()));

        $dirty = Agent::auditCodePartFromLabel('Jet@Pakistan! Ltd');
        $this->assertSame('JetPakistanLtd', $dirty);
    }

    public function test_customer_dropdown_compact(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();

        $this->actingAs($customer)
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="account-dropdown-link-dashboard"', false)
            ->assertSee('data-testid="account-dropdown-link-bookings"', false)
            ->assertSee('data-testid="account-dropdown-link-profile-settings"', false)
            ->assertDontSee('data-testid="account-dropdown-link-agency-settings"', false)
            ->assertDontSee('data-testid="account-dropdown-balance"', false);
    }

    /**
     * @return array{agency: Agency, agent: Agent, admin: User}
     */
    protected function prepareJetPakistanAdmin(string $actorName): array
    {
        $scenario = $this->buildAgentPortalScenario();
        $scenario['adminA']->forceFill(['name' => $actorName])->save();
        $scenario['agentA']->forceFill([
            'meta' => array_merge($scenario['agentA']->meta ?? [], ['agency_name' => 'JetPakistan']),
        ])->save();

        return [
            'agency' => $scenario['agencyA'],
            'agent' => $scenario['agentA']->fresh(),
            'admin' => $scenario['adminA']->fresh(),
        ];
    }
}
