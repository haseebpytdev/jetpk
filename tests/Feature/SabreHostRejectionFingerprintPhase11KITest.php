<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Services\Suppliers\Sabre\Gds\SabreSelectedOfferRevalidationGate;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Support\Bookings\SabreHostErrorClassifier;
use App\Support\Bookings\SabreHostRejectionFingerprint;
use App\Support\FlightSearch\SabreOfferFreshness;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sprint 11K-I — host-rejection fingerprint persistence + pre-checkout risk overlay.
 */
class SabreHostRejectionFingerprintPhase11KITest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config([
            'ota.offer_freshness.refresh_due_seconds' => 300,
            'ota.offer_freshness.stale_after_seconds' => 600,
            'ota.host_rejection_fingerprint.lookback_days' => 30,
            'ota.host_rejection_fingerprint.max_bookings_scan' => 40,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => false,
            'suppliers.sabre.revalidate_before_booking' => false,
        ]);
        Http::fake();
    }

    public function test_host_segment_status_failure_persists_safe_fingerprint(): void
    {
        $snapshot = $this->booking33LikeSnapshot();
        $booking = $this->makeSabreBooking($snapshot);

        app(SabreBookingService::class)->finalizePublicCheckoutSabreStorage($booking, [
            'success' => false,
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'live_call_attempted' => true,
            'http_status' => 200,
            'airline_segment_status' => 'NN',
            'halt_on_status_received' => true,
            'response_error_messages' => ['Flight EK615 returned status code NN'],
            'segment_count' => 1,
            'passenger_count' => 1,
        ]);

        $booking->refresh();
        $fingerprint = data_get($booking->meta, 'sabre_checkout_outcome.sabre_host_rejection_fingerprint');
        $this->assertIsArray($fingerprint);
        $this->assertSame(SabreHostRejectionFingerprint::FINGERPRINT_VERSION, $fingerprint['fingerprint_version'] ?? null);
        $this->assertSame('LHE', $fingerprint['origin'] ?? null);
        $this->assertSame('DXB', $fingerprint['destination'] ?? null);
        $this->assertSame('EK', $fingerprint['validating_carrier'] ?? null);
        $this->assertSame(['T'], $fingerprint['booking_classes_by_segment'] ?? null);
        $this->assertSame(['TAAOPPK1'], $fingerprint['fare_basis_codes_by_segment'] ?? null);
        $this->assertSame(
            SabreHostErrorClassifier::HOST_ERROR_FAMILY_HOST_SEGMENT_STATUS,
            $fingerprint['host_error_family'] ?? null
        );
        $this->assertSame(
            SabreHostErrorClassifier::REASON_HOST_SEGMENT_STATUS_UNCONFIRMED,
            $fingerprint['safe_reason_code'] ?? null
        );
        $this->assertSame(SabreHostErrorClassifier::RETRY_NO_RETRY_SAME_OFFER, $fingerprint['retry_policy'] ?? null);
        $this->assertNotEmpty($fingerprint['fingerprint_hash'] ?? null);

        $encoded = json_encode($fingerprint, JSON_THROW_ON_ERROR);
        foreach ([
            'response_error_messages',
            'CreatePassengerNameRecordRQ',
            'bookingSignature',
            'NN',
            'HALT_ON_STATUS_RECEIVED',
            'NO FARES',
            'UC',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $encoded, 'Fingerprint leaked: '.$forbidden);
        }
    }

    public function test_no_fares_rbd_carrier_failure_persists_safe_fingerprint(): void
    {
        $snapshot = $this->booking33LikeSnapshot();
        $snapshot['segments'][0]['booking_class'] = 'K';
        $snapshot['segments'][0]['fare_basis_code'] = 'KLOW';
        $snapshot['raw_payload']['sabre_shop_context']['booking_classes_by_segment'] = ['K'];
        $snapshot['raw_payload']['sabre_shop_context']['fare_basis_codes_by_segment'] = ['KLOW'];
        $booking = $this->makeSabreBooking($snapshot);

        app(SabreBookingService::class)->finalizePublicCheckoutSabreStorage($booking, [
            'success' => false,
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'live_call_attempted' => true,
            'response_error_messages' => ['EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
            'segment_count' => 1,
            'passenger_count' => 1,
        ]);

        $fingerprint = data_get($booking->fresh()->meta, 'sabre_checkout_outcome.sabre_host_rejection_fingerprint');
        $this->assertIsArray($fingerprint);
        $this->assertSame(
            SabreHostErrorClassifier::HOST_ERROR_FAMILY_NO_FARES_RBD_CARRIER,
            $fingerprint['host_error_family'] ?? null
        );
        $this->assertStringNotContainsString('NO FARES', json_encode($fingerprint, JSON_THROW_ON_ERROR));
    }

    public function test_certified_route_pending_does_not_persist_fingerprint(): void
    {
        $booking = $this->makeSabreBooking($this->booking33LikeSnapshot());

        app(SabreBookingService::class)->finalizePublicCheckoutSabreStorage($booking, [
            'success' => false,
            'status' => 'needs_review',
            'error_code' => 'sabre_certified_route_pending',
            'live_call_attempted' => false,
        ]);

        $this->assertNull(data_get($booking->fresh()->meta, 'sabre_checkout_outcome.sabre_host_rejection_fingerprint'));
        $this->assertNull(data_get($booking->fresh()->meta, 'sabre_checkout_outcome.sabre_host_classification'));
    }

    public function test_matching_future_offer_is_marked_high_risk(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $prior = $this->makeSabreBooking($this->booking33LikeSnapshot(), [
            'sabre_checkout_outcome' => [
                'sabre_host_rejection_fingerprint' => $this->persistedFingerprintFromSnapshot($this->booking33LikeSnapshot()),
            ],
        ]);
        $prior->forceFill(['route' => 'LHE → DXB'])->save();

        $offer = $this->searchOfferFromSnapshot($this->booking33LikeSnapshot());
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(1)->toIso8601String(), $offer);

        $gate = app(SabreSelectedOfferRevalidationGate::class)->evaluateCheckoutTransition(
            $agency,
            $offer,
            $this->searchCriteria(),
            $searchId,
            Cache::get('flight_search:'.$searchId),
        );

        $this->assertFalse($gate['allowed']);
        $this->assertSame('selected_offer_revalidation_required', $gate['block_code']);
        $this->assertTrue($gate['freshness_meta']['high_risk_cached_offer'] ?? false);
        $this->assertContains(
            'prior_host_rejection_fingerprint_match',
            $gate['freshness_meta']['high_risk_reasons'] ?? []
        );
    }

    public function test_stale_guard_still_takes_precedence_over_fingerprint_match(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $prior = $this->makeSabreBooking($this->booking33LikeSnapshot(), [
            'sabre_checkout_outcome' => [
                'sabre_host_rejection_fingerprint' => $this->persistedFingerprintFromSnapshot($this->booking33LikeSnapshot()),
            ],
        ]);
        $prior->forceFill(['route' => 'LHE → DXB'])->save();

        $offer = $this->searchOfferFromSnapshot($this->booking33LikeSnapshot());
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(11)->toIso8601String(), $offer);

        $gate = app(SabreSelectedOfferRevalidationGate::class)->evaluateCheckoutTransition(
            $agency,
            $offer,
            $this->searchCriteria(),
            $searchId,
            Cache::get('flight_search:'.$searchId),
        );

        $this->assertSame('offer_stale_before_checkout', $gate['block_code']);
    }

    public function test_missing_fare_basis_still_takes_precedence_over_fingerprint_match(): void
    {
        $freshness = app(SabreOfferFreshness::class);
        $offer = $this->searchOfferFromSnapshot($this->booking33LikeSnapshot());
        unset($offer['segments'][0]['fare_basis_code']);
        unset($offer['raw_payload']['sabre_shop_context']['fare_basis_codes_by_segment']);
        unset($offer['raw_payload']['sabre_booking_context']['fare_basis_codes_by_segment']);
        unset($offer['fare_breakdown']['fare_basis_codes']);

        $reasons = $freshness->assessHighRiskReasons($offer, 120, [
            'persisted_host_rejection_for_offer' => true,
        ]);

        $this->assertContains('missing_fare_basis', $reasons);
        $this->assertContains('prior_host_rejection_fingerprint_match', $reasons);
    }

    public function test_missing_rbd_still_takes_precedence_over_fingerprint_match(): void
    {
        $freshness = app(SabreOfferFreshness::class);
        $offer = $this->searchOfferFromSnapshot($this->booking33LikeSnapshot());
        $offer['segments'][0]['booking_class'] = '';

        $reasons = $freshness->assessHighRiskReasons($offer, 120, [
            'persisted_host_rejection_for_offer' => true,
        ]);

        $this->assertContains('missing_rbd', $reasons);
        $this->assertContains('prior_host_rejection_fingerprint_match', $reasons);
    }

    public function test_fingerprint_match_blocks_booking_submit_after_revalidation(): void
    {
        $freshness = app(SabreOfferFreshness::class);
        $offer = $this->searchOfferFromSnapshot($this->booking33LikeSnapshot());
        $now = now()->toIso8601String();
        $meta = [
            'persisted_host_rejection_for_offer' => true,
            'host_rejection_fingerprint_match' => ['fingerprint_match' => true],
            'selected_offer_revalidation_status' => 'success',
            'selected_offer_last_revalidated_at' => $now,
            'last_revalidated_at' => $now,
            'revalidation_status' => 'success',
        ];

        $block = $freshness->blocksBookingSubmit($offer, $meta, null);
        $this->assertNotNull($block);
        $this->assertSame('high_risk_cached_offer', $block['code']);
        $message = (string) ($block['message'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('NN', $message);
        $this->assertStringNotContainsStringIgnoringCase('UC', $message);
        $this->assertStringNotContainsStringIgnoringCase('NO FARES', $message);
    }

    public function test_initial_search_results_data_does_not_query_fingerprints(): void
    {
        $this->makeSabreBooking($this->booking33LikeSnapshot(), [
            'sabre_checkout_outcome' => [
                'sabre_host_rejection_fingerprint' => $this->persistedFingerprintFromSnapshot($this->booking33LikeSnapshot()),
            ],
        ]);

        Booking::query()->count();

        $searchId = $this->storeSabreSearchPayload(now()->toIso8601String(), $this->searchOfferFromSnapshot($this->booking33LikeSnapshot()));
        $response = $this->getJson('/flights/results/data?search_id='.$searchId);

        $response->assertOk();
        $offers = $response->json('offers');
        $sabre = collect($offers)->first(fn (array $o) => ($o['supplier_provider'] ?? '') === 'sabre');
        $this->assertIsArray($sabre);
        $this->assertFalse($sabre['offer_freshness']['high_risk_cached_offer'] ?? true);
        $this->assertArrayNotHasKey('high_risk_reasons', $sabre['offer_freshness'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    protected function booking33LikeSnapshot(): array
    {
        return [
            'offer_id' => '11ki-ek-lhe-dxb',
            'supplier_offer_id' => '11ki-ek-lhe-dxb',
            'supplier_provider' => 'sabre',
            'validating_carrier' => 'EK',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-10-01T10:00:00',
                'arrival_at' => '2026-10-01T14:00:00',
                'carrier' => 'EK',
                'marketing_carrier' => 'EK',
                'operating_carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'T',
                'fare_basis_code' => 'TAAOPPK1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 500,
                'currency' => 'USD',
                'fare_basis_codes' => ['TAAOPPK1'],
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'raw_payload' => [
                'sabre_shop_context' => [
                    'leg_refs' => [3],
                    'schedule_refs' => [9],
                    'validating_carrier' => 'EK',
                    'booking_classes_by_segment' => ['T'],
                    'fare_basis_codes_by_segment' => ['TAAOPPK1'],
                ],
                'sabre_booking_context' => [
                    'validating_carrier' => 'EK',
                    'booking_classes_by_segment' => ['T'],
                    'fare_basis_codes_by_segment' => ['TAAOPPK1'],
                    'leg_refs' => [3],
                    'schedule_refs' => [9],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    protected function searchOfferFromSnapshot(array $snapshot): array
    {
        return array_merge($snapshot, [
            'id' => (string) ($snapshot['offer_id'] ?? '11ki-offer'),
            'offer_id' => (string) ($snapshot['offer_id'] ?? '11ki-offer'),
            'supplier_connection_id' => 1,
            'airline_code' => 'EK',
            'final_customer_price' => 150000,
            'currency' => 'PKR',
        ]);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    protected function persistedFingerprintFromSnapshot(array $snapshot): array
    {
        $fields = SabreHostRejectionFingerprint::extractMatchFieldsFromOffer($snapshot);

        return array_merge($fields, [
            'fingerprint_version' => SabreHostRejectionFingerprint::FINGERPRINT_VERSION,
            'fingerprint_hash' => SabreHostRejectionFingerprint::computeFingerprintHash($fields),
            'host_error_family' => SabreHostErrorClassifier::HOST_ERROR_FAMILY_HOST_SEGMENT_STATUS,
            'safe_reason_code' => SabreHostErrorClassifier::REASON_HOST_SEGMENT_STATUS_UNCONFIRMED,
            'retry_policy' => SabreHostErrorClassifier::RETRY_NO_RETRY_SAME_OFFER,
            'recommended_admin_action' => 'Re-shop before retry.',
            'recorded_at' => now()->toIso8601String(),
            'source_booking_id' => 1,
            'source_layer' => SabreHostErrorClassifier::LAYER_AIRBOOK_SELL,
        ]);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $metaExtra
     */
    protected function makeSabreBooking(array $snapshot, array $metaExtra = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'route' => 'LHE → DXB',
            'meta' => array_merge([
                'supplier_provider' => SupplierProvider::Sabre->value,
                'normalized_offer_snapshot' => $snapshot,
            ], $metaExtra),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function searchCriteria(): array
    {
        return [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-10-01',
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function storeSabreSearchPayload(string $createdAt, array $offer): string
    {
        $searchId = (string) Str::uuid();
        Cache::put('flight_search:'.$searchId, [
            'search_id' => $searchId,
            'criteria' => $this->searchCriteria(),
            'offers' => [$offer],
            'warnings' => [],
            'created_at' => $createdAt,
            'search_created_at' => $createdAt,
        ], 1800);

        return $searchId;
    }
}
