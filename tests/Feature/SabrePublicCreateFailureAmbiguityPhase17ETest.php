<?php

namespace Tests\Feature;

use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Support\Bookings\SabrePnrFailureClassifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\Sabre\SabrePublicCreatePhase17ETestCase;

/**
 * Phase 17E/17F: definitive rejection, ambiguous timeout, blocked, and dry-run outcomes.
 */
class SabrePublicCreateFailureAmbiguityPhase17ETest extends SabrePublicCreatePhase17ETestCase
{
    public function test_definitive_supplier_rejection_records_failure_without_success_pnr(): void
    {
        $this->stubSabreCreatePnrHttp(responder: function (Request $request) {
            $url = strtolower($request->url());
            if (str_contains($url, '/v2/auth/token')) {
                return Http::response(['access_token' => 'tok-phase17f', 'expires_in' => 3600], 200);
            }
            if (str_contains($url, 'passenger/records')) {
                return Http::response([
                    'errors' => [
                        ['code' => 'INVALID_REQ', 'message' => 'Definitive supplier rejection'],
                    ],
                ], 400);
            }

            return Http::response([], 404);
        });

        $booking = $this->makeFreshSabreDraftBooking();
        $this->postBookingReview($booking)
            ->assertRedirect(route('booking.review'))
            ->assertSessionHasErrors('booking');

        $this->assertExactlyOneCanonicalCreateDispatch($booking);
        $this->assertNoRetrieveCancelOrTicketHttp();

        $booking->refresh();
        $this->assertNull($booking->pnr);
        $this->assertSame(0, SupplierBooking::query()->where('booking_id', $booking->id)->count());

        $attempts = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'create_pnr')
            ->orderBy('id')
            ->get();
        $this->assertCount(1, $attempts);
        $attempt = $attempts->first();
        $this->assertNotNull($attempt);
        $this->assertSame('failed', $attempt->status);
        $this->assertNotSame('needs_review', $attempt->status);
        $this->assertSame('sabre_booking_validation_failed', $attempt->error_code);
        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertTrue($summary['live_call_attempted'] ?? false);

        $this->postBookingReview($booking->fresh())
            ->assertRedirect(route('booking.review'));
        $this->assertExactlyOneCanonicalCreateDispatch($booking, 'no_retry_after_definitive_rejection');
    }

    public function test_ambiguous_timeout_records_needs_review_attempt(): void
    {
        $this->stubSabreCreatePnrHttp(responder: function (Request $request) {
            $url = strtolower($request->url());
            if (str_contains($url, '/v2/auth/token')) {
                return Http::response(['access_token' => 'tok-phase17f', 'expires_in' => 3600], 200);
            }
            if (str_contains($url, 'passenger/records')) {
                throw new ConnectionException('cURL error 28: Operation timed out after 30000 milliseconds');
            }

            return Http::response([], 404);
        });

        $booking = $this->makeFreshSabreDraftBooking();

        $this->postBookingReview($booking)
            ->assertRedirect(route('booking.confirmation'))
            ->assertSessionHas('sabre_checkout_notice');

        $this->assertExactlyOneCanonicalCreateDispatch($booking);
        $this->assertNoRetrieveCancelOrTicketHttp();

        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'create_pnr')
            ->orderBy('id')
            ->first();
        $this->assertNotNull($attempt);
        $this->assertSame('needs_review', $attempt->status);
        $this->assertSame('sabre_booking_connection_error', $attempt->error_code);
        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertTrue($summary['live_call_attempted'] ?? false);
        $this->assertTrue($summary['manual_reconciliation_required'] ?? false);

        $classification = SabrePnrFailureClassifier::classify($attempt->error_code, $summary);
        $this->assertFalse($classification['retry_allowed']);

        $booking->refresh();
        $this->assertNull($booking->pnr);
        $this->assertSame(0, SupplierBooking::query()->where('booking_id', $booking->id)->count());

        $dispatchCountAfterFirst = $this->countCreatePnrHttpDispatches();

        $this->postBookingReview($booking->fresh())
            ->assertRedirect(route('booking.confirmation'));

        $secondCreateDispatchCount = $this->countCreatePnrHttpDispatches() - $dispatchCountAfterFirst;
        $this->assertSame(0, $secondCreateDispatchCount);
        $this->assertExactlyOneCanonicalCreateDispatch($booking, 'no_retry_after_ambiguous_timeout');
    }

    public function test_blocked_before_dispatch_when_booking_disabled_emits_zero_http(): void
    {
        config(['suppliers.sabre.booking_enabled' => false]);
        $this->stubSabreCreatePnrHttp();

        $booking = $this->makeFreshSabreDraftBooking();
        $this->postBookingReview($booking)
            ->assertRedirect(route('booking.review'))
            ->assertSessionHasErrors('booking');

        $this->assertZeroCreatePnrDispatch();
    }

    public function test_dry_run_records_attempt_without_live_http(): void
    {
        $this->configureSabrePublicCreateDryRunPhase17E();
        Http::fake();

        $booking = $this->makeFreshSabreDraftBooking();
        $this->postBookingReview($booking)->assertRedirect(route('booking.confirmation'));

        $this->assertZeroCreatePnrDispatch();
        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'create_pnr')
            ->first();
        $this->assertNotNull($attempt);
        $this->assertSame('dry_run', $attempt->status);
    }
}
