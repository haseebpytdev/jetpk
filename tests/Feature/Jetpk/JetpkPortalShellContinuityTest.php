<?php

namespace Tests\Feature\Jetpk;

use App\Support\Agents\AgentPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Jetpk\Concerns\BuildsJetpkPortalTestFixtures;
use Tests\TestCase;

/**
 * JP-PORTAL-1 — shell continuity + Profile/Logout navigation.
 */
class JetpkPortalShellContinuityTest extends TestCase
{
    use BuildsJetpkPortalTestFixtures;
    use RefreshDatabase;

    private const PORTAL_SHELL_MARKER = 'jp-portal';
    private const LEGACY_SHELL_MARKER = 'ota-dashboard-shell';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootJetpkPortalContext();
    }

    public function test_customer_travelers_uses_the_jetpk_customer_shell(): void
    {
        $this->actingAs($this->customerUser())
            ->get(route('customer.travelers.index'))
            ->assertOk()
            ->assertSee(self::PORTAL_SHELL_MARKER, false)
            ->assertDontSee(self::LEGACY_SHELL_MARKER, false);
    }

    public function test_agent_travelers_uses_the_jetpk_agent_shell(): void
    {
        $this->actingAs($this->agentAdminUser())
            ->get(route('agent.travelers.index'))
            ->assertOk()
            ->assertSee(self::PORTAL_SHELL_MARKER, false)
            ->assertDontSee(self::LEGACY_SHELL_MARKER, false);
    }

    public function test_agent_staff_travelers_uses_the_agent_shell_when_permitted(): void
    {
        $this->actingAs($this->agentStaffUser([AgentPermission::TravelersManage]))
            ->get(route('agent.travelers.index'))
            ->assertOk()
            ->assertSee(self::PORTAL_SHELL_MARKER, false)
            ->assertDontSee(self::LEGACY_SHELL_MARKER, false);
    }

    public function test_shell_is_identical_across_the_customer_journey(): void
    {
        $this->actingAs($this->customerUser());

        foreach ([
            route('customer.dashboard'),
            route('customer.bookings.index'),
            route('customer.travelers.index'),
            route('profile.edit'),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee(self::PORTAL_SHELL_MARKER, false)
                ->assertDontSee(self::LEGACY_SHELL_MARKER, false);
        }
    }

    public function test_sidebar_exposes_profile_and_post_logout_for_every_portal_role(): void
    {
        foreach ([$this->customerUser(), $this->agentAdminUser(), $this->agentStaffUser([])] as $user) {
            $res = $this->actingAs($user)->get(route('profile.edit'))->assertOk();

            $res->assertSee('jp-portal-sidebar-profile', false);
            $res->assertSee('jp-portal-sidebar-logout', false);
            $res->assertSee('action="'.route('logout').'"', false);
            $res->assertSee('name="_token"', false);
        }
    }

    public function test_logout_survives_zero_permission_agent_staff(): void
    {
        $this->actingAs($this->agentStaffUser([]))
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->assertSee('jp-portal-sidebar-logout', false)
            ->assertSee('jp-portal-sidebar-profile', false);
    }

    public function test_limited_agent_staff_does_not_see_unauthorized_modules(): void
    {
        $this->actingAs($this->agentStaffUser([AgentPermission::TravelersManage]))
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->assertDontSee(route('agent.wallet.show'), false)
            ->assertDontSee(route('agent.commissions.index'), false);
    }

    public function test_there_is_no_get_logout_route(): void
    {
        $this->assertFalse(
            collect(app('router')->getRoutes())->contains(
                fn ($r) => $r->getName() === 'logout' && in_array('GET', $r->methods(), true)
            ),
            'logout must be POST-only'
        );
    }
}
