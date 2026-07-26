<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\SupplierBookingAttempt;
use App\Services\Suppliers\Sabre\SabreBookingService;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Sabre\SabrePublicCreatePhase17ETestCase;

/**
 * Phase 17E: durable idempotency matrix — cache lock + DB attempt state.
 */
class SabrePublicCreateDurableIdempotencyPhase17ETest extends SabrePublicCreatePhase17ETestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->stubSabreCreatePnrHttp('DURPNR1');
    }

    public function test_existing_success_attempt_blocks_second_dispatch_even_after_cache_flush(): void
    {
        $booking = $this->makeFreshSabreDraftBooking();
        $this->postBookingReview($booking)->assertRedirect(route('booking.confirmation'));

        Cache::flush();
        $booking->forceFill(['status' => BookingStatus::Draft, 'submitted_at' => null])->save();

        $this->postBookingReview($booking->fresh())->assertRedirect(route('booking.confirmation'));
        $this->assertExactlyOneCreatePnrDispatch('maximum_create_dispatch_count=1');
    }

    public function test_cache_lock_expiry_with_durable_success_attempt_prevents_redispatch(): void
    {
        $booking = $this->makeFreshSabreDraftBooking();
        $this->createPublicCheckoutAttempt($booking, 'success', ['live_call_attempted' => true]);

        Cache::flush();
        $this->postBookingReview($booking->fresh())->assertRedirect(route('booking.confirmation'));
        $this->assertZeroCreatePnrDispatch();
    }

    public function test_existing_processing_attempt_blocks_without_second_dispatch(): void
    {
        $booking = $this->makeFreshSabreDraftBooking();
        $this->createPublicCheckoutAttempt($booking, 'processing', ['live_call_attempted' => true]);

        $this->postBookingReview($booking)
            ->assertRedirect(route('booking.review'))
            ->assertSessionHasErrors('booking');

        $this->assertZeroCreatePnrDispatch();
    }

    public function test_existing_needs_review_attempt_preserves_manual_review_without_dispatch(): void
    {
        $booking = $this->makeFreshSabreDraftBooking();
        $this->createPublicCheckoutAttempt(
            $booking,
            'needs_review',
            ['live_call_attempted' => true],
            'sabre_booking_application_error',
        );

        $this->postBookingReview($booking)
            ->assertRedirect(route('booking.review'))
            ->assertSessionHasErrors('booking');

        $this->assertZeroCreatePnrDispatch();
        $this->assertSame(
            1,
            SupplierBookingAttempt::query()->where('booking_id', $booking->id)->where('action', 'create_pnr')->count(),
        );
    }

    public function test_two_processes_same_booking_intent_second_service_call_is_blocked_by_attempt_guard(): void
    {
        $booking = $this->makeFreshSabreDraftBooking();
        $this->postBookingReview($booking)->assertRedirect(route('booking.confirmation'));
        $this->assertExactlyOneCreatePnrDispatch();

        $booking->refresh();
        $offer = is_array($booking->meta['normalized_offer_snapshot'] ?? null)
            ? $booking->meta['normalized_offer_snapshot']
            : $booking->meta['flight_offer_snapshot'];

        $second = app(SabreBookingService::class)->createBooking(
            $offer,
            app(SabreBookingService::class)->passengerDataFromBookingForCommand($booking),
            $booking->id,
        );

        $this->assertFalse($second['live_call_attempted'] ?? true);
        $this->assertExactlyOneCreatePnrDispatch();
    }

    public function test_definitive_pre_dispatch_failure_records_no_supplier_http(): void
    {
        $this->configureSabrePublicCreateDryRunPhase17E();
        $booking = $this->makeFreshSabreDraftBooking();

        $this->postBookingReview($booking)->assertRedirect(route('booking.confirmation'));
        $this->assertZeroCreatePnrDispatch();

        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'create_pnr')
            ->first();
        $this->assertNotNull($attempt);
        $this->assertContains($attempt->status, ['dry_run', 'success', 'needs_review']);
    }

    public function test_durable_idempotency_uses_controller_lock_key_pattern(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/Frontend/BookingController.php'));
        $this->assertStringContainsString("Cache::lock('public-booking-review-submit:'.\$booking->id", $source);
        $this->assertStringContainsString('maybeAbortDuplicatePublicSabreBookingSubmit', $source);
    }
}
