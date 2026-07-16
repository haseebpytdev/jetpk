<?php

namespace Tests\Feature\Jetpk;

use App\Support\Agents\AgentPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Jetpk\Concerns\BuildsJetpkPortalTestFixtures;
use Tests\TestCase;

/**
 * JP-PORTAL-4A — Agent operations resolver migration + permission matrix.
 */
class JetpkAgentOperationsResolverTest extends TestCase
{
    use BuildsJetpkPortalTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootJetpkPortalContext();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function operationsRoutes(): array
    {
        return [
            'agency show' => ['agent.agency.show', AgentPermission::AgencyView],
            'staff index' => ['agent.staff.index', AgentPermission::StaffManage],
            'staff create' => ['agent.staff.create', AgentPermission::StaffManage],
            'support index' => ['agent.support.tickets.index', AgentPermission::SupportManage],
            'support create' => ['agent.support.tickets.create', AgentPermission::SupportManage],
            'travelers index' => ['agent.travelers.index', AgentPermission::TravelersManage],
            'travelers create' => ['agent.travelers.create', AgentPermission::TravelersManage],
        ];
    }

    public function test_agent_admin_can_edit_agency(): void
    {
        $this->actingAs($this->agentAdminUser())
            ->get(route('agent.agency.edit'))
            ->assertOk();
    }

    public function test_agent_staff_cannot_edit_agency_even_with_agency_edit_permission(): void
    {
        $this->actingAs($this->agentStaffUser([AgentPermission::AgencyEdit]))
            ->get(route('agent.agency.edit'))
            ->assertForbidden();
    }

    #[DataProvider('operationsRoutes')]
    public function test_agent_admin_can_open_operations_page(string $routeName, string $permission): void
    {
        $this->actingAs($this->agentAdminUser())->get(route($routeName))->assertOk();
    }

    #[DataProvider('operationsRoutes')]
    public function test_permitted_agent_staff_can_open_operations_page(string $routeName, string $permission): void
    {
        $this->actingAs($this->agentStaffUser([$permission]))->get(route($routeName))->assertOk();
    }

    #[DataProvider('operationsRoutes')]
    public function test_unpermitted_agent_staff_is_denied(string $routeName, string $permission): void
    {
        $this->actingAs($this->agentStaffUser([]))->get(route($routeName))->assertForbidden();
    }

    public function test_agent_staff_edit_requires_staff_manage_permission(): void
    {
        $scenario = $this->agentPortalScenario();
        $staffUser = $scenario['staff']['A10'];

        $this->actingAs($this->agentAdminUser())
            ->get(route('agent.staff.edit', $staffUser))
            ->assertOk();

        $this->actingAs($this->agentStaffUser([]))
            ->get(route('agent.staff.edit', $staffUser))
            ->assertForbidden();
    }

    public function test_agent_support_show_resolves_for_permitted_staff(): void
    {
        $scenario = $this->agentPortalScenario();
        $ticket = $scenario['recordsA']['tickets']['pending'];

        $this->actingAs($this->agentStaffUser([AgentPermission::SupportManage]))
            ->get(route('agent.support.tickets.show', $ticket))
            ->assertOk();
    }

    public function test_agent_travelers_edit_resolves_for_permitted_staff(): void
    {
        $scenario = $this->agentPortalScenario();
        $traveler = $scenario['recordsA']['travelers']['complete'];

        $this->actingAs($this->agentStaffUser([AgentPermission::TravelersManage]))
            ->get(route('agent.travelers.edit', $traveler))
            ->assertOk();
    }

    public function test_operations_views_resolve_through_the_resolver(): void
    {
        $resolver = app(\App\Services\Client\RuntimeViewResolver::class);

        foreach ([
            'agency', 'agency-edit',
            'staff.index', 'staff.create', 'staff.edit',
            'support.tickets.index', 'support.tickets.create', 'support.tickets.show',
            'travelers.index', 'travelers.create', 'travelers.edit',
        ] as $logical) {
            $resolved = $resolver->view($logical, 'agent');
            $this->assertTrue(
                view()->exists($resolved),
                "client_view('{$logical}', 'agent') resolved to a missing view: {$resolved}"
            );
        }
    }

    public function test_legacy_operations_views_still_exist(): void
    {
        foreach ([
            'dashboard.agent.agency', 'dashboard.agent.agency-edit',
            'dashboard.agent.staff.index', 'dashboard.agent.staff.create', 'dashboard.agent.staff.edit',
            'dashboard.agent.support.tickets.index', 'dashboard.agent.support.tickets.create',
            'dashboard.agent.support.tickets.show',
            'dashboard.travelers.index', 'dashboard.travelers.create', 'dashboard.travelers.edit',
        ] as $legacy) {
            $this->assertTrue(view()->exists($legacy), "fallback view missing: {$legacy}");
        }
    }

}
