<?php

namespace Tests\Feature\Agent;

use App\Enums\BookingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\TestCase;

/**
 * Final booking UAT without supplier/Sabre calls.
 */
class AgentPortalBookingFinalTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use RefreshDatabase;

    public function test_booking_index_renders_agent_a_bookings_not_agent_b(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])->get(route('agent.bookings.index'))
            ->assertOk()
            ->assertSee('BKG-PENDING-A', false)
            ->assertSee('BKG-PAID-A', false)
            ->assertSee('BKG-PROOF-A', false)
            ->assertSee('BKG-CANCELLED-A', false)
            ->assertDontSee('BKG-B-ONLY', false);
    }

    public function test_booking_show_works_for_agent_a_and_blocks_agent_b(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $paid = $scenario['recordsA']['bookings']['paid'];

        $this->actingAs($scenario['adminA'])->get(route('agent.bookings.show', $paid))
            ->assertOk()
            ->assertSee('BKG-PAID-A', false)
            ->assertSee('PNR-A-123', false);

        $this->actingAs($scenario['adminA'])
            ->get(route('agent.bookings.show', $scenario['recordsB']['booking']))
            ->assertForbidden();
    }

    public function test_create_booking_route_and_button_follow_permissions(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])->get(route('agent.bookings.index'))
            ->assertSee('data-testid="agent-bookings-create-link"', false);

        $this->actingAs($scenario['staff']['A1'])->get(route('agent.bookings.index'))
            ->assertDontSee('data-testid="agent-bookings-create-link"', false);

        $this->actingAs($scenario['staff']['A2'])->get(route('agent.bookings.create'))->assertOk();
        $this->actingAs($scenario['staff']['A1'])->get(route('agent.bookings.create'))->assertForbidden();
    }

    public function test_payment_proof_action_visible_for_awaiting_proof_booking(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $booking = $scenario['recordsA']['bookings']['paymentPending'];

        $this->actingAs($scenario['adminA'])->get(route('agent.bookings.index'))
            ->assertSee('data-testid="agent-booking-upload-proof"', false);

        $this->actingAs($scenario['adminA'])->get(route('agent.bookings.show', $booking))
            ->assertSee('Upload proof', false);
    }

    public function test_cancellation_form_visible_for_eligible_booking(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $paid = $scenario['recordsA']['bookings']['paid'];

        $this->actingAs($scenario['adminA'])->get(route('agent.bookings.show', $paid))
            ->assertSee('Request cancellation', false);
    }

    public function test_cancelled_booking_status_displays_defensively(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $cancelled = $scenario['recordsA']['bookings']['cancelled'];

        $this->actingAs($scenario['adminA'])->get(route('agent.bookings.show', $cancelled))
            ->assertOk()
            ->assertSee('BKG-CANCELLED-A', false)
            ->assertSee(BookingStatus::Cancelled->value, false);
    }

    public function test_store_booking_skipped_because_it_triggers_supplier_search(): void
    {
        $this->markTestSkipped('agent.bookings.store triggers FlightSearchService/OfferValidationService; covered by AgentBookingCreationTest with mocks.');
    }
}
