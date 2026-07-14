<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Suppliers\Sabre\Gds\SabreBookingOfferRefreshService;
use App\Support\Bookings\ControlledStaffOfferRefreshDiagnostics;
use App\Support\Bookings\SabreSafeRefreshContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SabreSafeRefreshContextTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_build_from_checkout_persists_durable_context_on_sabre_meta(): void
    {
        $offer = $this->connectingSabreOffer();
        $criteria = [
            'trip_type' => 'one_way',
            'origin' => 'BAH',
            'destination' => 'DXB',
            'depart_date' => '2026-08-01',
            'cabin' => 'economy',
            'adults' => 1,
        ];
        $metaPatch = [
            'supplier_connection_id' => 7,
            'checkout_search_id' => 'search-uuid-1',
            'checkout_offer_id' => 'offer-gf-1',
            'supplier_total' => 45000.0,
            'supplier_currency' => 'PKR',
            'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
        ];

        $context = app(SabreSafeRefreshContext::class)->buildFromCheckout($offer, $criteria, $metaPatch);

        $this->assertSame(SupplierProvider::Sabre->value, $context['supplier']);
        $this->assertSame('BAH', $context['origin']);
        $this->assertSame('DXB', $context['destination']);
        $this->assertCount(2, $context['selected_segments']);
        $this->assertSame(['GF'], $context['carrier_chain']);
        $this->assertSame(45000.0, $context['supplier_total']);
        $this->assertSame('search-uuid-1', $context['checkout_search_id']);
        $this->assertNotEmpty($context['search_criteria']);
    }

    public function test_safe_refresh_context_excludes_raw_response_pii_and_credentials(): void
    {
        $offer = $this->connectingSabreOffer([
            'raw_payload' => [
                'response_body' => ['secret' => 'must-not-store'],
                'token' => 'abc',
                'passenger_email' => 'a@example.com',
                'sabre_shop_identifiers' => ['itinerary_ref' => '1'],
            ],
        ]);

        $context = app(SabreSafeRefreshContext::class)->buildFromCheckout($offer, [
            'trip_type' => 'one_way',
            'origin' => 'BAH',
            'destination' => 'DXB',
            'depart_date' => '2026-08-01',
        ], ['supplier_total' => 100.0, 'supplier_currency' => 'PKR']);

        $this->assertFalse(app(SabreSafeRefreshContext::class)->containsForbiddenKeys($context));
        $this->assertArrayNotHasKey('raw_payload', $context);
        $this->assertArrayNotHasKey('response_body', $context);
        $this->assertArrayNotHasKey('token', $context);
    }

    public function test_controlled_retry_uses_cache_when_present(): void
    {
        $criteria = [
            'trip_type' => 'one_way',
            'origin' => 'BAH',
            'destination' => 'DXB',
            'depart_date' => '2026-08-01',
            'adults' => 1,
        ];
        $offer = $this->connectingSabreOffer();
        $searchId = app(FlightSearchResultStore::class)->store($criteria, [$offer], []);

        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'meta' => $this->bookingMetaWithSafeContext($offer, $criteria, $searchId),
        ]);

        $context = app(ControlledStaffOfferRefreshDiagnostics::class)->assessBookingContext($booking);
        $this->assertTrue($context['checkout_search_cache_present']);
        $this->assertTrue($context['safe_refresh_context_present']);
        $this->assertTrue($context['refresh_available']);
    }

    public function test_controlled_retry_can_rebuild_when_cache_missing_but_safe_context_complete(): void
    {
        $criteria = [
            'trip_type' => 'one_way',
            'origin' => 'BAH',
            'destination' => 'DXB',
            'depart_date' => '2026-08-01',
            'adults' => 1,
        ];
        $offer = $this->connectingSabreOffer();
        $meta = $this->bookingMetaWithSafeContext($offer, $criteria, 'expired-search-id');
        unset($meta['search_criteria']);

        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'agency_id' => Agency::factory()->create()->id,
            'meta' => $meta,
        ]);

        $assess = app(ControlledStaffOfferRefreshDiagnostics::class)->assessBookingContext($booking);
        $this->assertFalse($assess['checkout_search_cache_present']);
        $this->assertTrue($assess['safe_refresh_context_complete']);
        $this->assertTrue($assess['can_rebuild_from_safe_context']);
        $this->assertTrue($assess['refresh_available']);
        $this->assertNotContains('checkout_search_cache', $assess['missing_context_fields']);

        $resolved = app(SabreSafeRefreshContext::class)->resolveSearchCriteriaForRefresh($meta);
        $this->assertSame('BAH', $resolved['origin']);
        $this->assertSame('DXB', $resolved['destination']);

        $mockSearch = Mockery::mock(FlightSearchService::class);
        $mockSearch->shouldReceive('searchWithMeta')
            ->once()
            ->with(Mockery::on(fn (array $c): bool => ($c['origin'] ?? '') === 'BAH'), Mockery::any(), 'admin_offer_refresh')
            ->andReturn(['offers' => [$offer]]);
        $this->app->instance(FlightSearchService::class, $mockSearch);

        $refresh = app(SabreBookingOfferRefreshService::class)->refresh($booking->fresh(), false);
        $this->assertSame('', $refresh['error'] ?? '');
        $this->assertTrue($refresh['match_found']);
    }

    public function test_resolve_search_criteria_prefers_non_empty_durable_context_over_empty_meta(): void
    {
        $criteria = [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => '2026-08-01',
            'adults' => 1,
        ];
        $offer = $this->connectingSabreOffer([
            'validating_carrier' => 'PK',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'RUH',
                    'departure_at' => '2026-08-01T08:00:00',
                    'carrier' => 'PK',
                    'flight_number' => '751',
                    'booking_class' => 'Y',
                ],
                [
                    'origin' => 'RUH',
                    'destination' => 'JED',
                    'departure_at' => '2026-08-01T12:00:00',
                    'carrier' => 'PK',
                    'flight_number' => '752',
                    'booking_class' => 'Y',
                ],
            ],
        ]);
        $context = app(SabreSafeRefreshContext::class)->buildFromCheckout($offer, $criteria, [
            'supplier_total' => 45000.0,
            'supplier_currency' => 'PKR',
        ]);

        $meta = [
            'search_criteria' => [
                'trip_type' => 'one_way',
                'origin' => '',
                'destination' => '',
                'depart_date' => '',
            ],
            SabreSafeRefreshContext::META_KEY => $context,
        ];

        $resolved = app(SabreSafeRefreshContext::class)->resolveSearchCriteriaForRefresh($meta);
        $this->assertSame('LHE', $resolved['origin']);
        $this->assertSame('JED', $resolved['destination']);
        $this->assertSame('2026-08-01', $resolved['depart_date']);
    }

    public function test_refresh_exception_returns_safe_stage_without_throwing(): void
    {
        $criteria = [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => '2026-08-01',
            'adults' => 1,
        ];
        $offer = $this->connectingSabreOffer(['validating_carrier' => 'PK']);
        $meta = $this->bookingMetaWithSafeContext($offer, $criteria, 'expired-search-id');
        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'agency_id' => Agency::factory()->create()->id,
            'meta' => $meta,
        ]);

        $mockSearch = Mockery::mock(FlightSearchService::class);
        $mockSearch->shouldReceive('searchWithMeta')
            ->once()
            ->andThrow(new \RuntimeException('simulated supplier search failure'));
        $this->app->instance(FlightSearchService::class, $mockSearch);

        $refresh = app(SabreBookingOfferRefreshService::class)->refresh($booking->fresh(), false);
        $this->assertSame('refresh_exception', $refresh['error'] ?? null);
        $this->assertSame('calling_flight_search', $refresh['refresh_stage'] ?? null);
        $this->assertTrue($refresh['fresh_search_attempted'] ?? false);
        $this->assertFalse($refresh['match_attempted'] ?? true);
        $this->assertSame('RuntimeException', $refresh['refresh_exception_class'] ?? null);
        $this->assertArrayNotHasKey('fresh_offer', $refresh);
    }

    public function test_controlled_refresh_apply_stage_stamps_meta_without_type_error(): void
    {
        $criteria = [
            'trip_type' => 'one_way',
            'origin' => 'BAH',
            'destination' => 'DXB',
            'depart_date' => '2026-08-01',
            'adults' => 1,
        ];
        $offer = $this->connectingSabreOffer();
        $meta = $this->bookingMetaWithSafeContext($offer, $criteria, 'expired-search-id');
        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'agency_id' => Agency::factory()->create()->id,
            'meta' => $meta,
        ]);

        $mockSearch = Mockery::mock(FlightSearchService::class);
        $mockSearch->shouldReceive('searchWithMeta')
            ->once()
            ->andReturn(['offers' => [$offer]]);
        $this->app->instance(FlightSearchService::class, $mockSearch);

        $refresh = app(SabreBookingOfferRefreshService::class)->refresh($booking->fresh(), true);

        $this->assertSame('', $refresh['error'] ?? '');
        $this->assertTrue($refresh['applied'] ?? false);
        $this->assertTrue($refresh['meta_stamp_attempted'] ?? false);
        $this->assertSame('stamping_booking_meta', $refresh['refresh_stage'] ?? null);

        $booking->refresh();
        $this->assertSame('success', data_get($booking->meta, 'selected_offer_revalidation_status'));
        $this->assertSame('refreshed', data_get($booking->meta, 'offer_refresh_status'));
        $this->assertNotEmpty(data_get($booking->meta, 'offer_validated_at'));
    }

    public function test_missing_safe_context_recommends_fresh_search_required(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'meta' => [
                'supplier_provider' => 'sabre',
                'checkout_search_id' => 'missing-cache',
                'normalized_offer_snapshot' => $this->connectingSabreOffer(),
            ],
        ]);

        $summary = app(ControlledStaffOfferRefreshDiagnostics::class)->buildAttemptSafeSummary(
            $booking,
            'offer_refresh_unavailable',
            'offer_refresh_unavailable',
            true,
            ['match_found' => false, 'reasons' => ['no_matching_offer_in_shop']],
        );

        $this->assertFalse($summary['safe_refresh_context_present']);
        $this->assertFalse($summary['can_rebuild_from_safe_context']);
        $this->assertSame(
            ControlledStaffOfferRefreshDiagnostics::ACTION_FRESH_SEARCH,
            $summary['recommended_staff_action'],
        );
    }

    public function test_fare_change_maps_to_fare_acceptance_required_with_safe_context(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'meta' => $this->bookingMetaWithSafeContext($this->connectingSabreOffer(), [
                'trip_type' => 'one_way',
                'origin' => 'BAH',
                'destination' => 'DXB',
                'depart_date' => '2026-08-01',
            ], 'sid-1'),
        ]);

        $summary = app(ControlledStaffOfferRefreshDiagnostics::class)->buildAttemptSafeSummary(
            $booking,
            'offer_validation_required',
            'offer_refresh_price_changed',
            true,
            ['match_found' => true, 'price_changed' => true, 'applied' => false],
        );

        $this->assertTrue($summary['safe_refresh_context_present']);
        $this->assertSame(
            ControlledStaffOfferRefreshDiagnostics::ACTION_FARE_ACCEPTANCE,
            $summary['recommended_staff_action'],
        );
    }

    public function test_deferred_connecting_meta_includes_safe_refresh_context_without_enabling_public_pnr(): void
    {
        $offer = $this->connectingSabreOffer();
        $criteria = [
            'trip_type' => 'one_way',
            'origin' => 'BAH',
            'destination' => 'DXB',
            'depart_date' => '2026-08-01',
        ];
        $context = app(SabreSafeRefreshContext::class)->buildFromCheckout($offer, $criteria, [
            'supplier_total' => 45000.0,
            'supplier_currency' => 'PKR',
        ]);

        $meta = [
            'supplier_provider' => 'sabre',
            'defer_supplier_booking_to_manual_review' => true,
            'supplier_pnr_deferred_reason' => 'complex_itinerary',
            'complex_itinerary_requires_staff_confirmation' => true,
            SabreSafeRefreshContext::META_KEY => $context,
        ];

        $this->assertTrue($meta['defer_supplier_booking_to_manual_review']);
        $this->assertNotEmpty($meta[SabreSafeRefreshContext::META_KEY]['selected_segments']);
    }

    /**
     * @param  array<string, mixed>  $offerOverrides
     * @return array<string, mixed>
     */
    private function connectingSabreOffer(array $offerOverrides = []): array
    {
        return array_merge([
            'id' => 'offer-gf-connect',
            'supplier_provider' => 'sabre',
            'validating_carrier' => 'GF',
            'currency' => 'PKR',
            'fare_breakdown' => ['supplier_total' => 45000.0, 'currency' => 'PKR'],
            'segments' => [
                [
                    'origin' => 'BAH',
                    'destination' => 'AUH',
                    'departure_at' => '2026-08-01T08:00:00',
                    'arrival_at' => '2026-08-01T09:30:00',
                    'carrier' => 'GF',
                    'flight_number' => '123',
                    'booking_class' => 'Y',
                    'fare_basis_code' => 'YLOW',
                ],
                [
                    'origin' => 'AUH',
                    'destination' => 'DXB',
                    'departure_at' => '2026-08-01T12:00:00',
                    'arrival_at' => '2026-08-01T13:00:00',
                    'carrier' => 'GF',
                    'flight_number' => '456',
                    'booking_class' => 'Y',
                    'fare_basis_code' => 'YLOW',
                ],
            ],
            'raw_payload' => [
                'sabre_shop_identifiers' => ['itinerary_ref' => '1', 'pricing_information_index' => 0],
            ],
        ], $offerOverrides);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>
     */
    private function bookingMetaWithSafeContext(array $offer, array $criteria, string $searchId): array
    {
        $context = app(SabreSafeRefreshContext::class)->buildFromCheckout($offer, $criteria, [
            'checkout_search_id' => $searchId,
            'checkout_offer_id' => 'offer-gf-connect',
            'supplier_total' => 45000.0,
            'supplier_currency' => 'PKR',
        ]);

        return [
            'supplier_provider' => 'sabre',
            'search_criteria' => $criteria,
            'checkout_search_id' => $searchId,
            'normalized_offer_snapshot' => $offer,
            'defer_supplier_booking_to_manual_review' => true,
            SabreSafeRefreshContext::META_KEY => $context,
        ];
    }
}
