<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use Illuminate\Support\ViewErrorBag;
use Tests\Support\Sabre\SabrePublicCreatePhase17ETestCase;

/**
 * Phase 17E: confirmation UX for success, needs_review, blocked, and dry_run states.
 */
class SabrePublicConfirmationOutcomePhase17ETest extends SabrePublicCreatePhase17ETestCase
{
    public function test_definitive_success_shows_pnr_without_ticketed_claim(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'pnr' => 'SUCC1',
            'ticketing_status' => 'pending',
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'sabre_checkout_outcome' => ['status' => 'pending_payment_or_ticketing', 'live_call_attempted' => true],
            ],
        ]);

        $html = $this->renderConfirmation($booking, null);
        $this->assertStringContainsString('SUCC1', $html);
        $this->assertStringContainsString('Ticketing is still pending', $html);
        $this->assertStringNotContainsString('Booking confirmed', $html);
        $this->assertStringNotContainsString('Parwaaz', $html);
    }

    public function test_needs_review_shows_pending_verification_without_confirmed_claim(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'pnr' => null,
            'supplier' => SupplierProvider::Sabre->value,
        ]);

        $html = $this->renderConfirmation($booking, [
            'notice' => 'Booking request received. Supplier confirmation is pending verification. No ticket has been issued.',
        ]);
        $this->assertStringContainsString('pending verification', strtolower($html));
        $this->assertStringNotContainsString('Your booking is confirmed', $html);
        $this->assertStringNotContainsString('PNR created and ticketed', $html);
    }

    public function test_blocked_state_does_not_claim_supplier_pnr_created(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'pnr' => null,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'sabre_checkout_outcome' => [
                    'status' => 'validation_failed',
                    'live_call_attempted' => false,
                    'error_code' => 'sabre_booking_validation_failed',
                ],
            ],
        ]);

        $html = $this->renderConfirmation($booking, [
            'notice' => 'Supplier booking was not created or sent. Please contact support for the next safe action.',
        ]);
        $this->assertStringContainsString('not created', strtolower($html));
        $this->assertStringNotContainsString('SUCC', $html);
    }

    public function test_dry_run_confirmation_must_not_look_like_live_pnr(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'pnr' => null,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'sabre_checkout_outcome' => [
                    'status' => 'dry_run',
                    'live_call_attempted' => false,
                ],
            ],
        ]);

        $html = $this->renderConfirmation($booking, [
            'notice' => 'Non-live local validation only. No supplier PNR exists.',
        ]);
        $this->assertStringContainsString('non-live', strtolower($html));
        $this->assertStringContainsString('no supplier pnr', strtolower($html));
    }

    public function test_live_fresh_create_confirmation_via_http_shows_pnr_from_fake_response(): void
    {
        $this->seedPhase17eFoundation();
        $this->configureSabrePublicCreatePhase17E();
        $this->stubSabreCreatePnrHttp('LIVE17E');

        $booking = $this->makeFreshSabreDraftBooking();
        $this->postBookingReview($booking)->assertRedirect(route('booking.confirmation'));

        $booking->refresh();
        $html = $this->renderConfirmation($booking->fresh(), null);
        $this->assertStringContainsString('LIVE17E', $html);
    }

    /**
     * @param  array<string, string>|null  $notice
     */
    protected function renderConfirmation(Booking $booking, ?array $notice): string
    {
        return view('themes.frontend.jetpakistan.frontend.booking.partials.confirmation-body', [
            'booking' => $booking,
            'draft' => [],
            'offer' => null,
            'criteria' => [],
            'errors' => new ViewErrorBag,
            'supplierProvider' => SupplierProvider::Sabre->value,
            'supplierConfirmationNotice' => $notice,
        ])->render();
    }
}
