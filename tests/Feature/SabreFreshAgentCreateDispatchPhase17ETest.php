<?php

namespace Tests\Feature;

use Tests\Support\Sabre\SabrePublicCreatePhase17ETestCase;

/**
 * Phase 17E: agent-portal checkout fresh create dispatches exactly one fake Create PNR.
 */
class SabreFreshAgentCreateDispatchPhase17ETest extends SabrePublicCreatePhase17ETestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->stubSabreCreatePnrHttp();
    }

    public function test_agent_fresh_review_post_dispatches_exactly_one_fake_create_pnr(): void
    {
        $booking = $this->makeFreshSabreDraftBooking();
        $agent = $this->agentUser();

        $this->actingAs($agent)
            ->postBookingReview($booking, $this->agentSessionContext())
            ->assertRedirect(route('booking.confirmation'));

        $this->assertExactlyOneCreatePnrDispatch('agent_supplier_create_dispatch_count=1');
        $this->assertNoRetrieveCancelOrTicketHttp();
        $this->assertFreshCreatePersistence($booking, $this->phase17eFakePnr);
    }
}
