<?php

namespace Tests\Feature;

use Tests\Support\Sabre\SabrePublicCreatePhase17ETestCase;

/**
 * Phase 17E: guest (unauthenticated) fresh create dispatches exactly one fake Create PNR.
 */
class SabreFreshGuestCreateDispatchPhase17ETest extends SabrePublicCreatePhase17ETestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->stubSabreCreatePnrHttp();
    }

    public function test_guest_fresh_review_post_dispatches_exactly_one_fake_create_pnr(): void
    {
        $booking = $this->makeFreshSabreDraftBooking();

        $this->postBookingReview($booking)
            ->assertRedirect(route('booking.confirmation'));

        $this->assertExactlyOneCreatePnrDispatch('guest_supplier_create_dispatch_count=1');
        $this->assertNoRetrieveCancelOrTicketHttp();
        $this->assertFreshCreatePersistence($booking, $this->phase17eFakePnr);
    }
}
