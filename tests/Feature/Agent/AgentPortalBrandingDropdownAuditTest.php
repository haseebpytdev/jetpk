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

    /** @var list<string> */
    private const FORBIDDEN_BRAND_FRAGMENTS = [
        'Parwaaz',
        'Master OTA',
        'YoursDomain',
        'YD Travel',
        'haseeb-master',
    ];

    public function test_guest_login_page_shows_jetpakistan_branding_without_legacy_names(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('JetPakistan', $html);
        $this->assertNoForbiddenBranding($html);
    }

    public function test_agent_admin_sees_jetpakistan_branding_not_personal_name_in_header(): void
    {
        $scenario = $this->prepareJetPakistanAdmin('Asif');

        $html = $this->actingAs($scenario['admin'])
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('alt="JetPakistan"', $html);
        $this->assertStringContainsString('aria-label="JetPakistan portal"', $html);
        $this->assertStringNotContainsString('data-testid="header-brand-name">Asif<', $html);
        $this->assertNoForbiddenBranding($html);
    }

    public function test_agent_staff_sees_jetpakistan_branding_in_portal_shell(): void
    {
        $scenario = $this->prepareJetPakistanAdmin('Asif');
        $staff = $this->createAgentStaffUser(
            $scenario['agent'],
            'ali@jetpakistan-staff.test',
            [],
            'Ali Raza',
        );

        $html = $this->actingAs($staff)
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('alt="JetPakistan"', $html);
        $this->assertNoForbiddenBranding($html);
    }

    public function test_header_logo_markup_uses_jetpakistan_portal_shell(): void
    {
        $scenario = $this->prepareJetPakistanAdmin('Asif');

        $html = $this->actingAs($scenario['admin'])
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="jp-portal__logo-img"', $html);
        $this->assertStringContainsString('data-testid="agent-portal-subnav"', $html);
    }

    public function test_agent_admin_portal_nav_includes_core_links(): void
    {
        $scenario = $this->prepareJetPakistanAdmin('Asif');

        $this->actingAs($scenario['admin'])
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="agent-portal-subnav"', false)
            ->assertSee('href="/agent/bookings"', false)
            ->assertSee('href="/agent/travelers"', false)
            ->assertSee('data-testid="jp-portal-sidebar-logout"', false);
    }

    public function test_agent_staff_a0_sees_minimal_portal_nav(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A0'];

        $this->actingAs($staff)
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="agent-portal-subnav"', false)
            ->assertSee('href="/agent"', false)
            ->assertDontSee('href="/agent/agency"', false);
    }

    public function test_agent_staff_with_bookings_view_sees_bookings_link(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A1'])
            ->get(route('agent.dashboard'))
            ->assertSee('href="/agent/bookings"', false);
    }

    public function test_agent_staff_with_agency_view_sees_agency_settings(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A6'])
            ->get(route('agent.dashboard'))
            ->assertSee('href="/agent/agency"', false);
    }

    public function test_agent_staff_with_wallet_view_sees_wallet_link(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A3'])
            ->get(route('agent.dashboard'))
            ->assertSee('href="/agent/wallet"', false);
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

    public function test_customer_dashboard_shows_jetpakistan_branding(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();

        $html = $this->actingAs($customer)
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('JetPakistan', $html);
        $this->assertNoForbiddenBranding($html);
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

    private function assertNoForbiddenBranding(string $html): void
    {
        foreach (self::FORBIDDEN_BRAND_FRAGMENTS as $fragment) {
            $this->assertStringNotContainsString($fragment, $html, "Forbidden branding fragment found: {$fragment}");
        }
    }
}
