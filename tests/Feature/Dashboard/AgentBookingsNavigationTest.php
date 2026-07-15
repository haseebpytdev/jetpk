<?php

namespace Tests\Feature\Dashboard;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class AgentBookingsNavigationTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('client_route_parity.enabled', false);
    }

    public function test_agent_bookings_index_renders_breadcrumbs_and_operational_table(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])->get(route('agent.bookings.index'))
            ->assertOk()
            ->assertSee('ota-dashboard-breadcrumbs', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('data-testid="agent-bookings-filters"', false);
    }

    public function test_agent_booking_detail_is_agency_scoped_with_breadcrumbs(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $booking = $scenario['recordsA']['bookings']['pending'];

        $this->actingAs($scenario['adminA'])->get(route('agent.bookings.show', $booking))
            ->assertOk()
            ->assertSee('ota-dashboard-breadcrumbs', false)
            ->assertSee($booking->display_reference, false)
            ->assertSee('ota-account-detail-grid', false);

        $this->actingAs($scenario['adminB'])->get(route('agent.bookings.show', $booking))->assertForbidden();
    }

    public function test_agent_booking_create_launcher_renders_breadcrumbs_and_mode_links(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])->get(route('agent.bookings.create'))
            ->assertOk()
            ->assertSee('ota-dashboard-breadcrumbs', false)
            ->assertSee('data-testid="agent-booking-search-flights"', false);
    }

    public function test_agent_staff_without_bookings_create_is_denied_create_route(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A1'];
        $booking = $scenario['recordsA']['bookings']['pending'];

        $this->actingAs($staff)->get(route('agent.bookings.index'))
            ->assertOk()
            ->assertSee('ota-dashboard-breadcrumbs', false)
            ->assertDontSee('data-testid="agent-bookings-create-link"', false);

        $this->actingAs($staff)->get(route('agent.bookings.show', $booking))->assertOk();
        $this->actingAs($staff)->get(route('agent.bookings.create'))->assertForbidden();
    }

    public function test_agent_staff_with_bookings_create_can_load_create_page(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A2'];

        $this->actingAs($staff)->get(route('agent.bookings.create'))
            ->assertOk()
            ->assertSee('ota-dashboard-breadcrumbs', false);
    }

    public function test_jetpk_themed_agent_bookings_index_resolves_with_breadcrumbs(): void
    {
        $this->seed(\Database\Seeders\OtaFoundationSeeder::class);
        $this->makeJetpkProfile();
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])->get(route('agent.bookings.index'))
            ->assertOk()
            ->assertSee('class="jp-portal__top"', false)
            ->assertSee('ota-dashboard-breadcrumbs', false);
    }

    public function test_agent_home_dashboard_is_preserved_without_breadcrumb_regression(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])->get(route('agent.dashboard'))
            ->assertOk()
            ->assertSee('Total Bookings', false)
            ->assertDontSee('ota-dashboard-breadcrumbs__current">My bookings</span>', false);
    }
}
