<?php

namespace Tests\Feature\Agent;

use App\Enums\AccountType;
use App\Enums\BookingCancellationStatus;
use App\Enums\BookingStatus;
use App\Enums\UserAccountStatus;
use App\Models\AgencyUser;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\TestCase;

class AgentPortalOperationalClosureTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use RefreshDatabase;

    public function test_agent_capabilities_json_includes_owner_context_and_navigation(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.dashboard', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('capabilities.identity.is_owner', true)
            ->assertJsonPath('capabilities.agency.status', 'active')
            ->assertJsonPath('capabilities.capabilities.can_manage_staff', true)
            ->assertJsonStructure(['capabilities' => ['navigation', 'capabilities']]);
    }

    public function test_agent_staff_without_wallet_permission_is_denied_wallet_json(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A1'])
            ->getJson(route('agent.wallet.show', ['format' => 'json']))
            ->assertForbidden();
    }

    public function test_cross_agency_booking_detail_is_denied(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $booking = $scenario['recordsA']['bookings']['paid'];

        $this->actingAs($scenario['adminB'])
            ->getJson(route('agent.bookings.show', ['booking' => $booking->booking_reference, 'format' => 'json']))
            ->assertForbidden();
    }

    public function test_agent_cancellation_json_request_and_duplicate_conflict(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $scenario = $this->buildAgentPortalScenario();
        $booking = $scenario['recordsA']['bookings']['paid'];

        $this->actingAs($scenario['adminA'])
            ->postJson(route('agent.bookings.cancellations.store', ['booking' => $booking->booking_reference]), [
                'cancellation_type' => 'booking_cancel',
                'reason' => 'Customer changed plans',
            ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('cancellation_request.status', BookingCancellationStatus::Requested->value);

        $booking->refresh();
        $this->assertNotSame(BookingStatus::Cancelled, $booking->status);

        $this->actingAs($scenario['adminA'])
            ->postJson(route('agent.bookings.cancellations.store', ['booking' => $booking->booking_reference]), [
                'cancellation_type' => 'booking_cancel',
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'cancellation_already_requested');
    }

    public function test_agent_staff_json_index_and_owner_create(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A1'])
            ->getJson(route('agent.staff.index', ['format' => 'json']))
            ->assertForbidden();

        $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.staff.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['staff', 'capabilities']);
    }

    public function test_agent_reports_and_commissions_json_owner_only(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.reports.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['summary']);

        $this->actingAs($scenario['staff']['A3'])
            ->getJson(route('agent.commissions.index', ['format' => 'json']))
            ->assertForbidden();

        $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.commissions.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['balance', 'entries', 'statements']);
    }

    public function test_agent_booking_create_json_activates_mode(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.bookings.create', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('booking_mode_active', true)
            ->assertJsonPath('search_url', '/flights/search');
    }

    public function test_inactive_agent_business_is_denied_dashboard_json(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $scenario['agentA']->forceFill(['is_active' => false])->save();

        $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.dashboard', ['format' => 'json']))
            ->assertForbidden()
            ->assertJsonPath('code', 'agency_inactive');
    }

    public function test_removed_staff_membership_is_denied(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A3'];

        AgencyUser::query()
            ->where('agency_id', $scenario['agencyA']->id)
            ->where('user_id', $staff->id)
            ->delete();

        $this->actingAs($staff)
            ->getJson(route('agent.dashboard', ['format' => 'json']))
            ->assertForbidden();
    }
}
