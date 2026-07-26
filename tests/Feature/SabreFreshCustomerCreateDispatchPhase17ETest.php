<?php

namespace Tests\Feature;

use Tests\Support\Sabre\SabrePublicCreatePhase17ETestCase;

/**
 * Phase 17E: authenticated customer fresh create dispatches exactly one fake Create PNR.
 */
class SabreFreshCustomerCreateDispatchPhase17ETest extends SabrePublicCreatePhase17ETestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->stubSabreCreatePnrHttp();
    }

    public function test_customer_fresh_review_post_dispatches_exactly_one_fake_create_pnr(): void
    {
        $booking = $this->makeFreshSabreDraftBooking();
        $customer = $this->customerUser();

        $this->actingAs($customer)
            ->postBookingReview($booking)
            ->assertRedirect(route('booking.confirmation'));

        $this->assertExactlyOneCreatePnrDispatch('customer_supplier_create_dispatch_count=1');
        $this->assertNoRetrieveCancelOrTicketHttp();
        $this->assertFreshCreatePersistence($booking, $this->phase17eFakePnr);
    }
}
