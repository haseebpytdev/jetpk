<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Support\PublicBooking;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Sabre\SabrePublicCreatePhase17ETestCase;

/**
 * Phase 17E: duplicate submission scenarios (double-click, refresh, back, two tabs).
 */
class SabrePublicCreateDuplicateRequestPhase17ETest extends SabrePublicCreatePhase17ETestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->stubSabreCreatePnrHttp('DUPPNR1');
    }

    public function test_double_click_second_post_does_not_dispatch_second_create_pnr(): void
    {
        $booking = $this->makeFreshSabreDraftBooking();

        $this->postBookingReview($booking)->assertRedirect(route('booking.confirmation'));
        $this->postBookingReview($booking->fresh())->assertRedirect(route('booking.confirmation'));

        $this->assertExactlyOneCreatePnrDispatch('maximum_create_dispatch_count=1');
    }

    public function test_browser_refresh_after_success_does_not_redispatch(): void
    {
        $booking = $this->makeFreshSabreDraftBooking();
        $this->postBookingReview($booking)->assertRedirect(route('booking.confirmation'));

        $booking->refresh();
        $this->assertNotNull($booking->submitted_at);

        $this->postBookingReview($booking->fresh())->assertRedirect(route('booking.confirmation'));
        $this->assertExactlyOneCreatePnrDispatch();
    }

    public function test_back_and_resubmit_after_success_is_idempotent(): void
    {
        $booking = $this->makeFreshSabreDraftBooking();
        $this->postBookingReview($booking)->assertRedirect(route('booking.confirmation'));

        $booking->refresh();
        $booking->forceFill([
            'status' => BookingStatus::Draft,
            'submitted_at' => null,
            'pnr' => null,
            'supplier_reference' => null,
            'supplier_api_booking_id' => null,
        ])->save();

        $this->postBookingReview($booking->fresh())->assertRedirect(route('booking.confirmation'));
        $this->assertExactlyOneCreatePnrDispatch();
    }

    public function test_two_tabs_same_session_second_post_is_idempotent(): void
    {
        $booking = $this->makeFreshSabreDraftBooking();

        $this->postBookingReview($booking)->assertRedirect(route('booking.confirmation'));
        $this->postBookingReview($booking->fresh())->assertRedirect(route('booking.confirmation'));

        $this->assertExactlyOneCreatePnrDispatch();
    }
}
