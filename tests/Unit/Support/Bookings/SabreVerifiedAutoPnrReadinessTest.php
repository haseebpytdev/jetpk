<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierBooking;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreSafeRefreshContext;
use App\Support\Bookings\SabreVerifiedAutoPnrReadiness;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SabreVerifiedAutoPnrReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->configureControlledConnecting();
    }

    public function test_gf_verified_lane_defers_when_feature_flag_disabled(): void
    {
        $booking = $this->gfVerifiedConnectingBooking();

        $result = app(SabreVerifiedAutoPnrReadiness::class)->evaluate($booking);

        $this->assertFalse($result['eligible']);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::MODE_DRY_RUN_ONLY, $result['mode']);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_FEATURE_FLAG_DISABLED, $result['reason_code']);
        $this->assertFalse($result['public_auto_pnr_currently_enabled']);
        $this->assertFalse($result['pnr_present']);
        $this->assertFalse($result['supplier_booking_present']);
        $this->assertTrue($result['same_carrier']);
        $this->assertSame(2, $result['segment_count']);
        $this->assertSame(SabreCertifiedRouteSelector::CONTROLLED_PNR_VERIFIED, $result['controlled_pnr_certification_status']);
        $this->assertFalse(app(SabreVerifiedAutoPnrReadiness::class)->canAttemptLivePublicAutoPnr($booking));
    }

    public function test_gf_verified_lane_is_live_eligible_when_feature_flag_enabled(): void
    {
        config(['suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true]);
        $booking = $this->gfVerifiedConnectingBooking();

        $result = app(SabreVerifiedAutoPnrReadiness::class)->evaluate($booking);

        $this->assertTrue($result['eligible']);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::MODE_LIVE_ELIGIBLE, $result['mode']);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_ELIGIBLE_LIVE, $result['reason_code']);
        $this->assertTrue($result['public_auto_pnr_currently_enabled']);
        $this->assertSame(SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_SUCCESS, $result['public_auto_pnr_evidence_status'] ?? null);
        $this->assertTrue(app(SabreVerifiedAutoPnrReadiness::class)->canAttemptLivePublicAutoPnr($booking));
    }

    public function test_gf_route_only_without_exact_evidence_is_not_live_eligible(): void
    {
        config(['suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true]);
        $booking = $this->gfVerifiedConnectingBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $snapshot['segments'] = [
            [
                'origin' => 'LHE',
                'destination' => 'BAH',
                'carrier' => 'GF',
                'flight_number' => '999',
                'booking_class' => 'Q',
                'fare_basis_code' => 'QDLIT3GF',
                'departure_at' => '2026-07-23T08:00:00Z',
                'arrival_at' => '2026-07-23T10:00:00Z',
            ],
            [
                'origin' => 'BAH',
                'destination' => 'JED',
                'carrier' => 'GF',
                'flight_number' => '888',
                'booking_class' => 'Q',
                'fare_basis_code' => 'QDLIT3GF',
                'departure_at' => '2026-07-24T02:30:00Z',
                'arrival_at' => '2026-07-24T05:30:00Z',
            ],
        ];
        $meta['normalized_offer_snapshot'] = $snapshot;
        $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => '2026-07-23',
            'adults' => 1,
        ], [
            'checkout_search_id' => 'gf-route-only-search',
            'checkout_offer_id' => 'gf-route-only-offer',
            'supplier_total' => 100.0,
            'supplier_currency' => 'PKR',
        ]);
        $booking->forceFill(['meta' => $meta])->save();

        $result = app(SabreVerifiedAutoPnrReadiness::class)->evaluate($booking->fresh(['supplierBookings']));

        $this->assertFalse($result['eligible']);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_INSUFFICIENT_FLIGHT_DATE_SELLABILITY_EVIDENCE, $result['reason_code']);
        $this->assertStringContainsString('flight/date', strtolower((string) ($result['reason_message'] ?? '')));
        $this->assertFalse(app(SabreVerifiedAutoPnrReadiness::class)->canAttemptLivePublicAutoPnr($booking->fresh(['supplierBookings'])));
    }

    public function test_booking_46_like_evidence_is_static_failed_not_live_eligible(): void
    {
        config(['suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true]);
        $booking = $this->gfBooking46LikeConnectingBooking();

        $result = app(SabreVerifiedAutoPnrReadiness::class)->evaluate($booking);

        $this->assertFalse($result['eligible']);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_FARE_RBD_CARRIER_NOT_SELLABLE, $result['reason_code']);
        $this->assertFalse(app(SabreVerifiedAutoPnrReadiness::class)->canAttemptLivePublicAutoPnr($booking));
    }

    public function test_same_route_rbd_fare_different_flights_is_not_live_eligible(): void
    {
        config(['suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true]);
        $booking = $this->gfVerifiedConnectingBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $snapshot['segments'] = [
            [
                'origin' => 'LHE',
                'destination' => 'BAH',
                'carrier' => 'GF',
                'flight_number' => '765',
                'booking_class' => 'W',
                'fare_basis_code' => 'WDLIT3PK',
                'departure_at' => '2026-07-23T08:00:00Z',
                'arrival_at' => '2026-07-23T10:00:00Z',
            ],
            [
                'origin' => 'BAH',
                'destination' => 'JED',
                'carrier' => 'GF',
                'flight_number' => '181',
                'booking_class' => 'W',
                'fare_basis_code' => 'WDLIT3PK',
                'departure_at' => '2026-07-24T02:30:00Z',
                'arrival_at' => '2026-07-24T05:30:00Z',
            ],
        ];
        $meta['normalized_offer_snapshot'] = $snapshot;
        $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => '2026-07-23',
            'adults' => 1,
        ], [
            'checkout_search_id' => 'gf-wrong-flights-search',
            'checkout_offer_id' => 'gf-wrong-flights-offer',
            'supplier_total' => 100.0,
            'supplier_currency' => 'PKR',
        ]);
        $booking->forceFill(['meta' => $meta])->save();

        $result = app(SabreVerifiedAutoPnrReadiness::class)->evaluate($booking->fresh(['supplierBookings']));

        $this->assertFalse($result['eligible']);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_INSUFFICIENT_FLIGHT_DATE_SELLABILITY_EVIDENCE, $result['reason_code']);
        $this->assertFalse(app(SabreVerifiedAutoPnrReadiness::class)->canAttemptLivePublicAutoPnr($booking->fresh(['supplierBookings'])));
    }

    public function test_prior_terminal_fare_rbd_failure_blocks_readiness(): void
    {
        config(['suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true]);
        $booking = $this->gfVerifiedConnectingBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['verified_multiseg_auto_pnr_result'] = 'failed';
        $meta['verified_multiseg_auto_pnr_reason_code'] = SabreVerifiedAutoPnrReadiness::VERIFIED_AUTO_PNR_TERMINAL_FAILURE_REASON;
        $meta['verified_auto_pnr_evidence_fingerprint'] = 'abc123terminal';
        $booking->forceFill(['meta' => $meta])->save();

        $result = app(SabreVerifiedAutoPnrReadiness::class)->evaluate($booking->fresh(['supplierBookings']));

        $this->assertFalse($result['eligible']);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_PRIOR_FARE_RBD_FAILURE, $result['reason_code']);
    }

    public function test_feature_flag_does_not_require_broad_public_connecting_flag(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true,
        ]);

        $this->assertTrue(SabreVerifiedAutoPnrReadiness::isPublicVerifiedAutoPnrEnabled());
        $booking = $this->gfVerifiedConnectingBooking();
        $this->assertTrue(app(SabreVerifiedAutoPnrReadiness::class)->canAttemptLivePublicAutoPnr($booking));
    }

    public function test_pk_host_noop_booking_is_not_eligible(): void
    {
        $booking = $this->pkHostNoopConnectingBooking();

        $result = app(SabreVerifiedAutoPnrReadiness::class)->evaluate($booking);

        $this->assertFalse($result['eligible']);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_HOST_NOOP_BLOCKED, $result['reason_code']);
    }

    public function test_unknown_same_carrier_connecting_is_not_eligible(): void
    {
        $booking = $this->unknownSameCarrierConnectingBooking();

        $result = app(SabreVerifiedAutoPnrReadiness::class)->evaluate($booking);

        $this->assertFalse($result['eligible']);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_UNKNOWN_CONTROLLED_ONLY, $result['reason_code']);
    }

    public function test_pnr_present_blocks_readiness(): void
    {
        $booking = $this->gfVerifiedConnectingBooking(['pnr' => 'SZFXWM']);

        $result = app(SabreVerifiedAutoPnrReadiness::class)->evaluate($booking);

        $this->assertFalse($result['eligible']);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_PNR_ALREADY_EXISTS, $result['reason_code']);
        $this->assertTrue($result['pnr_present']);
    }

    public function test_supplier_booking_present_blocks_readiness(): void
    {
        $booking = $this->gfVerifiedConnectingBooking();
        SupplierBooking::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => 'sabre',
            'status' => 'created',
            'pnr' => 'SUP123',
        ]);

        $result = app(SabreVerifiedAutoPnrReadiness::class)->evaluate($booking->fresh(['supplierBookings']));

        $this->assertFalse($result['eligible']);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_SUPPLIER_BOOKING_EXISTS, $result['reason_code']);
        $this->assertTrue($result['supplier_booking_present']);
    }

    public function test_ticketing_enabled_blocks_readiness(): void
    {
        config(['suppliers.sabre.ticketing_enabled' => true]);
        $booking = $this->gfVerifiedConnectingBooking();

        $result = app(SabreVerifiedAutoPnrReadiness::class)->evaluate($booking);

        $this->assertFalse($result['eligible']);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_TICKETING_ENABLED, $result['reason_code']);
        $this->assertTrue($result['ticketing_enabled']);
    }

    public function test_public_checkout_still_defers_when_feature_flag_disabled(): void
    {
        $booking = $this->gfVerifiedConnectingBooking();
        $readiness = app(SabreVerifiedAutoPnrReadiness::class)->evaluate($booking);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_FEATURE_FLAG_DISABLED, $readiness['reason_code']);

        $result = app(SabreBookingService::class)->runPublicReviewDryRun($booking->fresh(['passengers', 'contact', 'fareBreakdown']));

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame(SabreCertifiedRouteSelector::ERROR_CODE_PENDING, $result['error_code'] ?? null);
        $this->assertFalse($result['live_call_attempted'] ?? true);

        $meta = is_array($booking->fresh()->meta) ? $booking->fresh()->meta : [];
        $this->assertSame('deferred', $meta['verified_multiseg_auto_pnr_result'] ?? null);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_FEATURE_FLAG_DISABLED, $meta['verified_multiseg_auto_pnr_reason_code'] ?? null);
    }

    protected function configureControlledConnecting(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => false,
            'suppliers.sabre.certified_route_selector_public_checkout_enabled' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function gfVerifiedConnectingBooking(array $overrides = []): Booking
    {
        $booking = Booking::factory()->create(array_merge([
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => 1,
                'create_payload_strategy_version' => 'E5A_SAFE_STRUCTURE_V1',
                'offer_validation_status' => 'valid',
                'search_criteria' => ['trip_type' => 'one_way'],
                'normalized_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'validating_carrier' => 'GF',
                    'segments' => [
                        [
                            'origin' => 'LHE',
                            'destination' => 'BAH',
                            'carrier' => 'GF',
                            'flight_number' => '767',
                            'booking_class' => 'W',
                            'fare_basis_code' => 'WDLIT3PK',
                            'departure_at' => '2026-07-29T22:00:00',
                            'arrival_at' => '2026-07-30T01:55:00',
                        ],
                        [
                            'origin' => 'BAH',
                            'destination' => 'JED',
                            'carrier' => 'GF',
                            'flight_number' => '171',
                            'booking_class' => 'W',
                            'fare_basis_code' => 'WDLIT3PK',
                            'departure_at' => '2026-07-30T10:05:00',
                            'arrival_at' => '2026-07-30T12:30:00',
                        ],
                    ],
                    'raw_payload' => [
                        'sabre_shop_context' => [
                            'pricing_information_ref' => 'pi-1',
                            'offer_ref' => 'offer-1',
                            'itinerary_ref' => 'itin-1',
                            'validating_carrier' => 'GF',
                            'fare_basis_codes' => ['WDLIT3PK', 'WDLIT3PK'],
                        ],
                    ],
                    'fare_breakdown' => [
                        'supplier_total' => 100.0,
                        'currency' => 'PKR',
                        'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
                    ],
                ],
            ],
        ], $overrides));

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => '2026-07-29',
            'adults' => 1,
        ], [
            'checkout_search_id' => 'gf-verified-readiness-search',
            'checkout_offer_id' => 'gf-verified-readiness-offer',
            'supplier_total' => 100.0,
            'supplier_currency' => 'PKR',
        ]);
        $booking->forceFill(['meta' => $meta])->save();

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passenger_type' => 'adult',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'fareBreakdown']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function gfBooking46LikeConnectingBooking(array $overrides = []): Booking
    {
        $booking = Booking::factory()->create(array_merge([
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => 1,
                'create_payload_strategy_version' => 'E5A_SAFE_STRUCTURE_V1',
                'offer_validation_status' => 'valid',
                'search_criteria' => ['trip_type' => 'one_way'],
                'normalized_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'validating_carrier' => 'GF',
                    'segments' => [
                        [
                            'origin' => 'LHE',
                            'destination' => 'BAH',
                            'carrier' => 'GF',
                            'flight_number' => '765',
                            'booking_class' => 'W',
                            'fare_basis_code' => 'WDLIT3PK',
                            'departure_at' => '2026-07-31T15:10:00',
                            'arrival_at' => '2026-07-31T18:40:00',
                        ],
                        [
                            'origin' => 'BAH',
                            'destination' => 'JED',
                            'carrier' => 'GF',
                            'flight_number' => '173',
                            'booking_class' => 'W',
                            'fare_basis_code' => 'WDLIT3PK',
                            'departure_at' => '2026-08-01T18:05:00',
                            'arrival_at' => '2026-08-01T20:30:00',
                        ],
                    ],
                    'raw_payload' => [
                        'sabre_shop_context' => [
                            'pricing_information_ref' => 'pi-1',
                            'offer_ref' => 'offer-1',
                            'itinerary_ref' => 'itin-1',
                            'validating_carrier' => 'GF',
                            'fare_basis_codes' => ['WDLIT3PK', 'WDLIT3PK'],
                        ],
                    ],
                    'fare_breakdown' => [
                        'supplier_total' => 100.0,
                        'currency' => 'PKR',
                        'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
                    ],
                ],
            ],
        ], $overrides));

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => '2026-07-31',
            'adults' => 1,
        ], [
            'checkout_search_id' => 'gf-booking-46-search',
            'checkout_offer_id' => 'gf-booking-46-offer',
            'supplier_total' => 100.0,
            'supplier_currency' => 'PKR',
        ]);
        $booking->forceFill(['meta' => $meta])->save();

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passenger_type' => 'adult',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'fareBreakdown']);
    }

    protected function pkHostNoopConnectingBooking(): Booking
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => 1,
                'create_payload_strategy_version' => 'E5A_SAFE_STRUCTURE_V1',
                'offer_validation_status' => 'valid',
                'search_criteria' => ['trip_type' => 'one_way'],
                'normalized_offer_snapshot' => [
                    'validating_carrier' => 'PK',
                    'segments' => [
                        [
                            'origin' => 'LHE',
                            'destination' => 'KHI',
                            'carrier' => 'PK',
                            'flight_number' => '301',
                            'booking_class' => 'V',
                            'fare_basis_code' => 'VDLIT3PK',
                            'departure_at' => '2026-07-23T08:00:00Z',
                            'arrival_at' => '2026-07-23T10:00:00Z',
                        ],
                        [
                            'origin' => 'KHI',
                            'destination' => 'JED',
                            'carrier' => 'PK',
                            'flight_number' => '741',
                            'booking_class' => 'V',
                            'fare_basis_code' => 'VDLIT3PK',
                            'departure_at' => '2026-07-24T02:30:00Z',
                            'arrival_at' => '2026-07-24T05:30:00Z',
                        ],
                    ],
                ],
            ],
        ]);

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passenger_type' => 'adult',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact', 'supplierBookings']);
    }

    protected function unknownSameCarrierConnectingBooking(): Booking
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => 1,
                'create_payload_strategy_version' => 'E5A_SAFE_STRUCTURE_V1',
                'offer_validation_status' => 'valid',
                'search_criteria' => ['trip_type' => 'one_way'],
                'normalized_offer_snapshot' => [
                    'validating_carrier' => 'SV',
                    'segments' => [
                        [
                            'origin' => 'ISB',
                            'destination' => 'KHI',
                            'carrier' => 'SV',
                            'flight_number' => '701',
                            'booking_class' => 'Q',
                            'fare_basis_code' => 'QSV01',
                            'departure_at' => '2026-07-23T08:00:00Z',
                            'arrival_at' => '2026-07-23T10:00:00Z',
                        ],
                        [
                            'origin' => 'KHI',
                            'destination' => 'DXB',
                            'carrier' => 'SV',
                            'flight_number' => '702',
                            'booking_class' => 'Q',
                            'fare_basis_code' => 'QSV02',
                            'departure_at' => '2026-07-24T02:30:00Z',
                            'arrival_at' => '2026-07-24T05:30:00Z',
                        ],
                    ],
                ],
            ],
        ]);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, [
            'trip_type' => 'one_way',
            'origin' => 'ISB',
            'destination' => 'DXB',
            'depart_date' => '2026-07-23',
            'adults' => 1,
        ], [
            'checkout_search_id' => 'sv-unknown-search',
            'checkout_offer_id' => 'sv-unknown-offer',
            'supplier_total' => 100.0,
            'supplier_currency' => 'PKR',
        ]);
        $booking->forceFill(['meta' => $meta])->save();

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passenger_type' => 'adult',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact', 'supplierBookings']);
    }
}
