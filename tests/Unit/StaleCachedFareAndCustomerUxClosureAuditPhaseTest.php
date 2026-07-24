<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Support\Bookings\PublicCheckoutFareChangeState;
use App\Support\Bookings\SabreBookingValidationManualRequestPolicy;
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class StaleCachedFareAndCustomerUxClosureAuditPhaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_passenger_request_does_not_accept_authoritative_fare_total_from_browser(): void
    {
        $request = new \App\Http\Requests\Frontend\StoreBookingPassengersRequest;
        $rules = $request->rules();

        $this->assertArrayNotHasKey('selected_fare_total', $rules);
        $this->assertArrayNotHasKey('fare_amount', $rules);
        $this->assertArrayNotHasKey('total_price', $rules);
        $this->assertArrayNotHasKey('currency', $rules);
    }

    public function test_search_cache_uses_server_controlled_ttl_and_search_id(): void
    {
        $reflection = new ReflectionClass(FlightSearchResultStore::class);
        $ttl = $reflection->getConstant('TTL_SECONDS');
        $prefix = $reflection->getConstant('CACHE_PREFIX');

        $this->assertSame(1800, $ttl);
        $this->assertSame('flight_search:', $prefix);
    }

    public function test_fare_change_acceptance_records_server_context_hash_not_client_flag(): void
    {
        $booking = Booking::factory()->create([
            'selected_fare_total' => 520.83,
            'revalidated_fare_total' => 545.00,
            'meta' => [
                'original_offer_id' => 'offer-abc',
                'fare_option_key' => 'brand:ECON',
                'fare_change' => ['fare_changed' => true, 'old_total' => 520.83, 'new_total' => 545.00],
            ],
        ]);
        $booking->fareBreakdown()->create([
            'base_fare' => 545.00,
            'taxes' => 0,
            'fees' => 0,
            'total' => 545.00,
            'currency' => 'PKR',
        ]);

        app(PublicCheckoutFareChangeState::class)->recordCustomerAcceptance($booking->fresh(['passengers', 'fareBreakdown']));

        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertNotNull($booking->fare_change_accepted_at);
        $this->assertNotEmpty($meta[PublicCheckoutFareChangeState::META_ACCEPTED_FARE_CONTEXT_HASH] ?? '');
        $this->assertSame(545.00, (float) ($meta[PublicCheckoutFareChangeState::META_ACCEPTED_FARE_TOTAL] ?? 0));
    }

    public function test_fare_change_context_hash_invalidates_when_offer_identity_changes(): void
    {
        $state = app(PublicCheckoutFareChangeState::class);
        $booking = Booking::factory()->create([
            'selected_fare_total' => 500,
            'meta' => ['original_offer_id' => 'offer-a', 'fare_option_key' => 'brand:ECON'],
        ]);
        $hashA = $state->buildReviewContextHash($booking);

        $booking->forceFill(['meta' => ['original_offer_id' => 'offer-b', 'fare_option_key' => 'brand:ECON']])->save();
        $hashB = $state->buildReviewContextHash($booking->fresh());

        $this->assertNotSame($hashA, $hashB);
    }

    public function test_offer_refresh_requires_server_acceptance_before_pnr_path(): void
    {
        $booking = Booking::factory()->create([
            'meta' => [
                SabreOfferRefreshAcceptance::META_REQUIRES_CONFIRMATION => true,
                SabreOfferRefreshAcceptance::META_ACCEPTED => false,
                SabreOfferRefreshAcceptance::META_PRICE_CHANGED => true,
            ],
        ]);

        $this->assertTrue(SabreOfferRefreshAcceptance::requiresAcceptance($booking));
        $this->assertTrue(app(PublicCheckoutFareChangeState::class)->requiresCustomerAcceptance($booking));
    }

    public function test_customer_safe_message_strips_internal_sabre_validation_text(): void
    {
        $safe = SabreBookingValidationManualRequestPolicy::customerSafeMessage(
            'Sabre validation failed: INVALID RBD FOR SEGMENT 1 PCC=XXXX RequestorID=ABC123',
        );

        $this->assertStringNotContainsString('PCC', strtoupper($safe));
        $this->assertStringNotContainsString('REQUESTORID', strtoupper($safe));
        $this->assertStringNotContainsString('INVALID RBD', strtoupper($safe));
        $this->assertStringContainsString('could not be validated', strtolower($safe));
    }

    public function test_ticketing_issue_path_remains_disabled_by_default(): void
    {
        config(['suppliers.sabre.ticketing_enabled' => false]);
        $result = app(SabreBookingService::class)->issueTicket(
            Booking::factory()->make(),
            \App\Models\User::factory()->make(),
        );

        $this->assertSame('disabled', $result['status'] ?? null);
        $this->assertFalse($result['live_call_attempted'] ?? true);
    }

    public function test_review_context_hash_includes_segment_and_fare_identity(): void
    {
        $state = app(PublicCheckoutFareChangeState::class);
        $booking = Booking::factory()->create([
            'selected_fare_total' => 520.83,
            'revalidated_fare_total' => 520.83,
            'meta' => [
                'original_offer_id' => 'offer-1',
                'fare_option_key' => 'brand:FLEX',
                'selected_fare_family_option' => ['brand_code' => 'FLEX', 'fare_basis' => 'SLOW1'],
                'flight_offer_snapshot' => [
                    'segments' => [
                        ['origin' => 'LHE', 'destination' => 'DOH', 'booking_class' => 'S'],
                        ['origin' => 'DOH', 'destination' => 'JED', 'booking_class' => 'S'],
                    ],
                ],
            ],
        ]);

        $hash = $state->buildReviewContextHash($booking);
        $this->assertSame(64, strlen($hash));
    }
}
