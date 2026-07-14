<?php

namespace Tests\Unit\Support\Bookings;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\SabreBookingPayloadBuilder;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use Tests\TestCase;

class SabreCertifiedRouteSelectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => false,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
        ]);
    }

    public function test_one_way_direct_same_carrier_is_certified_passenger_records_v25_baseline(): void
    {
        $booking = $this->bookingWithMeta([
            'search_criteria' => ['trip_type' => 'one_way'],
            'normalized_offer_snapshot' => [
                'segments' => [
                    [
                        'origin' => 'KHI',
                        'destination' => 'DXB',
                        'carrier' => 'PK',
                        'booking_class' => 'Y',
                        'fare_basis_code' => 'YLOW',
                    ],
                ],
                'validating_carrier' => 'PK',
            ],
        ]);

        $selection = app(SabreCertifiedRouteSelector::class)->selectForBooking($booking);

        $this->assertSame(SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_DIRECT_SAME_CARRIER, $selection['category']);
        $this->assertSame(SabreCertifiedRouteSelector::STATUS_CERTIFIED, $selection['route_status']);
        $this->assertTrue($selection['live_booking_allowed']);
        $this->assertSame(SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V25_CREATE, $selection['endpoint_path']);
        $this->assertSame('create_passenger_name_record', $selection['booking_schema']);
    }

    public function test_same_carrier_two_segment_with_flag_false_is_pending_not_public_certified(): void
    {
        config(['suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => false]);

        $booking = $this->bookingWithMeta([
            'search_criteria' => ['trip_type' => 'one_way'],
            'normalized_offer_snapshot' => [
                'segments' => [
                    ['origin' => 'LHE', 'destination' => 'KHI', 'carrier' => 'PK', 'booking_class' => 'Y'],
                    ['origin' => 'KHI', 'destination' => 'DXB', 'carrier' => 'PK', 'booking_class' => 'Y'],
                ],
                'validating_carrier' => 'PK',
            ],
        ]);

        $selection = app(SabreCertifiedRouteSelector::class)->selectForBooking($booking);

        $this->assertSame(SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS, $selection['category']);
        $this->assertSame(SabreCertifiedRouteSelector::STATUS_PENDING_CERTIFICATION, $selection['route_status']);
        $this->assertFalse($selection['live_booking_allowed']);
        $this->assertSame(SabreCertifiedRouteSelector::ERROR_CODE_PENDING, $selection['error_code']);
    }

    public function test_unknown_same_carrier_two_segment_with_flag_true_is_controlled_only(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
        ]);

        $booking = $this->bookingWithMeta([
            'search_criteria' => ['trip_type' => 'one_way'],
            'normalized_offer_snapshot' => [
                'segments' => [
                    ['origin' => 'ISB', 'destination' => 'KHI', 'carrier' => 'PK', 'booking_class' => 'Y'],
                    ['origin' => 'KHI', 'destination' => 'DXB', 'carrier' => 'PK', 'booking_class' => 'Y'],
                ],
                'validating_carrier' => 'PK',
            ],
        ]);

        $selection = app(SabreCertifiedRouteSelector::class)->selectForBooking($booking);

        $this->assertSame(SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS, $selection['category']);
        $this->assertSame(SabreCertifiedRouteSelector::STATUS_CONTROLLED_CERTIFIED, $selection['route_status']);
        $this->assertFalse($selection['live_booking_allowed']);
        $this->assertTrue($selection['admin_staff_pnr_retry_allowed']);
        $this->assertSame(SabreCertifiedRouteSelector::CONTROLLED_PNR_UNKNOWN_CONTROLLED_ONLY, $selection['controlled_pnr_certification_status']);
        $this->assertSame(SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V24_CREATE, $selection['endpoint_path']);
        $this->assertSame(SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS, $selection['payload_style']);
    }

    public function test_gf_lhe_bah_jed_is_verified_controlled_pnr_capable(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
        ]);

        $booking = $this->bookingWithMeta([
            'search_criteria' => ['trip_type' => 'one_way'],
            'normalized_offer_snapshot' => [
                'segments' => [
                    ['origin' => 'LHE', 'destination' => 'BAH', 'carrier' => 'GF', 'booking_class' => 'V'],
                    ['origin' => 'BAH', 'destination' => 'JED', 'carrier' => 'GF', 'booking_class' => 'V'],
                ],
                'validating_carrier' => 'GF',
            ],
        ]);

        $selection = app(SabreCertifiedRouteSelector::class)->selectForBooking($booking);

        $this->assertSame(SabreCertifiedRouteSelector::STATUS_CONTROLLED_CERTIFIED, $selection['route_status']);
        $this->assertFalse($selection['live_booking_allowed']);
        $this->assertTrue($selection['admin_staff_pnr_retry_allowed']);
        $this->assertSame(SabreCertifiedRouteSelector::CONTROLLED_PNR_VERIFIED, $selection['controlled_pnr_certification_status']);
        $this->assertSame(44, $selection['controlled_pnr_verified_booking_id']);
        $this->assertTrue($selection['controlled_pnr_verified_pnr_present']);
        $this->assertTrue($selection['controlled_pnr_airline_locator_present']);
        $this->assertFalse($selection['controlled_pnr_ticketing_enabled']);
        $this->assertSame(SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS, $selection['payload_style']);
    }

    public function test_gf_booking_44_evidence_matches_success_assessment(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
        ]);

        $booking = $this->bookingWithMeta([
            'create_payload_strategy_version' => 'E5A_SAFE_STRUCTURE_V1',
            'search_criteria' => ['trip_type' => 'one_way'],
            'normalized_offer_snapshot' => [
                'segments' => [
                    ['origin' => 'LHE', 'destination' => 'BAH', 'carrier' => 'GF', 'flight_number' => '767', 'booking_class' => 'W', 'fare_basis_code' => 'WDLIT3PK', 'departure_at' => '2026-07-29T22:00:00'],
                    ['origin' => 'BAH', 'destination' => 'JED', 'carrier' => 'GF', 'flight_number' => '171', 'booking_class' => 'W', 'fare_basis_code' => 'WDLIT3PK', 'departure_at' => '2026-07-30T10:05:00'],
                ],
                'validating_carrier' => 'GF',
            ],
        ]);

        $assess = app(SabreCertifiedRouteSelector::class)->assessVerifiedPublicAutoPnrEvidence($booking);

        $this->assertSame(SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_SUCCESS, $assess['status']);
        $this->assertSame('gf_lhe_bah_jed_booking_44', $assess['success_evidence_key'] ?? null);
        $this->assertSame(44, $assess['matched_success_booking_id'] ?? null);
    }

    public function test_gf_booking_46_evidence_matches_static_failed_assessment(): void
    {
        $booking = $this->bookingWithMeta([
            'search_criteria' => ['trip_type' => 'one_way'],
            'normalized_offer_snapshot' => [
                'segments' => [
                    ['origin' => 'LHE', 'destination' => 'BAH', 'carrier' => 'GF', 'flight_number' => '765', 'booking_class' => 'W', 'fare_basis_code' => 'WDLIT3PK', 'departure_at' => '2026-07-31T15:10:00'],
                    ['origin' => 'BAH', 'destination' => 'JED', 'carrier' => 'GF', 'flight_number' => '173', 'booking_class' => 'W', 'fare_basis_code' => 'WDLIT3PK', 'departure_at' => '2026-08-01T18:05:00'],
                ],
                'validating_carrier' => 'GF',
            ],
        ]);

        $assess = app(SabreCertifiedRouteSelector::class)->assessVerifiedPublicAutoPnrEvidence($booking);

        $this->assertSame(SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_FAILED, $assess['status']);
        $this->assertSame(SabreCertifiedRouteSelector::REASON_FARE_RBD_CARRIER_NOT_SELLABLE, $assess['reason_code']);
        $this->assertSame('gf_lhe_bah_jed_booking_46', $assess['failed_evidence_key'] ?? null);
        $this->assertSame(46, $assess['matched_failed_booking_id'] ?? null);
    }

    public function test_gf_booking_46_does_not_match_booking_44_success_evidence(): void
    {
        $booking = $this->bookingWithMeta([
            'search_criteria' => ['trip_type' => 'one_way'],
            'normalized_offer_snapshot' => [
                'segments' => [
                    ['origin' => 'LHE', 'destination' => 'BAH', 'carrier' => 'GF', 'flight_number' => '765', 'booking_class' => 'W', 'fare_basis_code' => 'WDLIT3PK', 'departure_at' => '2026-07-31T15:10:00'],
                    ['origin' => 'BAH', 'destination' => 'JED', 'carrier' => 'GF', 'flight_number' => '173', 'booking_class' => 'W', 'fare_basis_code' => 'WDLIT3PK', 'departure_at' => '2026-08-01T18:05:00'],
                ],
                'validating_carrier' => 'GF',
            ],
        ]);

        $assess = app(SabreCertifiedRouteSelector::class)->assessVerifiedPublicAutoPnrEvidence($booking);

        $this->assertNotSame(SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_SUCCESS, $assess['status']);
    }

    public function test_gf_same_route_same_rbd_fare_different_flights_is_insufficient(): void
    {
        $booking = $this->bookingWithMeta([
            'search_criteria' => ['trip_type' => 'one_way'],
            'normalized_offer_snapshot' => [
                'segments' => [
                    ['origin' => 'LHE', 'destination' => 'BAH', 'carrier' => 'GF', 'flight_number' => '765', 'booking_class' => 'W', 'fare_basis_code' => 'WDLIT3PK', 'departure_at' => '2026-07-23T08:00:00'],
                    ['origin' => 'BAH', 'destination' => 'JED', 'carrier' => 'GF', 'flight_number' => '181', 'booking_class' => 'W', 'fare_basis_code' => 'WDLIT3PK', 'departure_at' => '2026-07-24T02:30:00'],
                ],
                'validating_carrier' => 'GF',
            ],
        ]);

        $assess = app(SabreCertifiedRouteSelector::class)->assessVerifiedPublicAutoPnrEvidence($booking);

        $this->assertSame(SabreCertifiedRouteSelector::EVIDENCE_STATUS_INSUFFICIENT_FLIGHT_DATE, $assess['status']);
        $this->assertSame(SabreCertifiedRouteSelector::REASON_INSUFFICIENT_FLIGHT_DATE_SELLABILITY_EVIDENCE, $assess['reason_code']);
        $this->assertStringContainsString('flight/date', strtolower((string) ($assess['reason_message'] ?? '')));
    }

    public function test_gf_same_route_different_fare_evidence_is_insufficient(): void
    {
        $booking = $this->bookingWithMeta([
            'search_criteria' => ['trip_type' => 'one_way'],
            'normalized_offer_snapshot' => [
                'segments' => [
                    ['origin' => 'LHE', 'destination' => 'BAH', 'carrier' => 'GF', 'flight_number' => '999', 'booking_class' => 'Q', 'fare_basis_code' => 'QDLIT3GF'],
                    ['origin' => 'BAH', 'destination' => 'JED', 'carrier' => 'GF', 'flight_number' => '888', 'booking_class' => 'Q', 'fare_basis_code' => 'QDLIT3GF'],
                ],
                'validating_carrier' => 'GF',
            ],
        ]);

        $assess = app(SabreCertifiedRouteSelector::class)->assessVerifiedPublicAutoPnrEvidence($booking);

        $this->assertSame(SabreCertifiedRouteSelector::EVIDENCE_STATUS_INSUFFICIENT_FLIGHT_DATE, $assess['status']);
        $this->assertSame(SabreCertifiedRouteSelector::REASON_INSUFFICIENT_FLIGHT_DATE_SELLABILITY_EVIDENCE, $assess['reason_code']);
    }

    public function test_pk_lhe_khi_jed_host_noop_route_is_blocked_not_payload_mutated(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
        ]);

        $booking = $this->bookingWithMeta([
            'search_criteria' => ['trip_type' => 'one_way'],
            'normalized_offer_snapshot' => [
                'segments' => [
                    ['origin' => 'LHE', 'destination' => 'KHI', 'carrier' => 'PK', 'booking_class' => 'V'],
                    ['origin' => 'KHI', 'destination' => 'JED', 'carrier' => 'PK', 'booking_class' => 'V'],
                ],
                'validating_carrier' => 'PK',
            ],
        ]);

        $selection = app(SabreCertifiedRouteSelector::class)->selectForBooking($booking);

        $this->assertSame(SabreCertifiedRouteSelector::STATUS_NOT_CERTIFIED, $selection['route_status']);
        $this->assertSame(SabreCertifiedRouteSelector::CONTROLLED_PNR_HOST_NOOP_BLOCKED, $selection['controlled_pnr_certification_status']);
        $this->assertFalse($selection['live_booking_allowed']);
        $this->assertFalse($selection['admin_staff_pnr_retry_allowed']);
        $this->assertSame(SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS, $selection['payload_style']);
        $this->assertSame(SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V24_CREATE, $selection['endpoint_path']);
    }

    public function test_same_carrier_two_segment_public_checkout_when_both_flags_true(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => true,
        ]);

        $booking = $this->bookingWithMeta([
            'search_criteria' => ['trip_type' => 'one_way'],
            'normalized_offer_snapshot' => [
                'segments' => [
                    ['origin' => 'ISB', 'destination' => 'KHI', 'carrier' => 'PK', 'booking_class' => 'Y'],
                    ['origin' => 'KHI', 'destination' => 'DXB', 'carrier' => 'PK', 'booking_class' => 'Y'],
                ],
                'validating_carrier' => 'PK',
            ],
        ]);

        $selection = app(SabreCertifiedRouteSelector::class)->selectForBooking($booking);

        $this->assertSame(SabreCertifiedRouteSelector::STATUS_CERTIFIED, $selection['route_status']);
        $this->assertTrue($selection['live_booking_allowed']);
    }

    public function test_mixed_carrier_two_segment_is_not_certified(): void
    {
        $booking = $this->bookingWithMeta([
            'search_criteria' => ['trip_type' => 'one_way'],
            'normalized_offer_snapshot' => [
                'segments' => [
                    ['origin' => 'LHE', 'destination' => 'KHI', 'carrier' => 'PK', 'booking_class' => 'Y'],
                    ['origin' => 'KHI', 'destination' => 'DXB', 'carrier' => 'EK', 'booking_class' => 'Y'],
                ],
                'validating_carrier' => 'PK',
            ],
        ]);

        $selection = app(SabreCertifiedRouteSelector::class)->selectForBooking($booking);

        $this->assertSame(SabreCertifiedRouteSelector::CATEGORY_MIXED_INTERLINE, $selection['category']);
        $this->assertSame(SabreCertifiedRouteSelector::STATUS_NOT_CERTIFIED, $selection['route_status']);
        $this->assertFalse($selection['live_booking_allowed']);
        $this->assertSame(SabreCertifiedRouteSelector::ERROR_CODE_NOT_CERTIFIED, $selection['error_code']);
    }

    public function test_three_plus_same_carrier_segments_remain_pending_not_mixed(): void
    {
        config(['suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true]);

        $booking = $this->bookingWithMeta([
            'search_criteria' => ['trip_type' => 'one_way'],
            'normalized_offer_snapshot' => [
                'segments' => [
                    ['origin' => 'LHE', 'destination' => 'KHI', 'carrier' => 'PK', 'booking_class' => 'Y'],
                    ['origin' => 'KHI', 'destination' => 'DXB', 'carrier' => 'PK', 'booking_class' => 'Y'],
                    ['origin' => 'DXB', 'destination' => 'LHR', 'carrier' => 'PK', 'booking_class' => 'Y'],
                ],
                'validating_carrier' => 'PK',
            ],
        ]);

        $selection = app(SabreCertifiedRouteSelector::class)->selectForBooking($booking);

        $this->assertSame(SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING, $selection['category']);
        $this->assertSame(SabreCertifiedRouteSelector::STATUS_PENDING_CERTIFICATION, $selection['route_status']);
        $this->assertFalse($selection['live_booking_allowed']);
    }

    public function test_round_trip_is_pending_certification(): void
    {
        $booking = $this->bookingWithMeta([
            'search_criteria' => ['trip_type' => 'round_trip'],
            'normalized_offer_snapshot' => [
                'segments' => [
                    ['origin' => 'KHI', 'destination' => 'DXB', 'carrier' => 'EK', 'booking_class' => 'Y'],
                    ['origin' => 'DXB', 'destination' => 'KHI', 'carrier' => 'EK', 'booking_class' => 'Y'],
                ],
            ],
        ]);

        $selection = app(SabreCertifiedRouteSelector::class)->selectForBooking($booking);

        $this->assertSame(SabreCertifiedRouteSelector::CATEGORY_RETURN, $selection['category']);
        $this->assertSame(SabreCertifiedRouteSelector::STATUS_PENDING_CERTIFICATION, $selection['route_status']);
        $this->assertFalse($selection['live_booking_allowed']);
    }

    public function test_v2_passengers_create_path_is_never_selected(): void
    {
        $booking = $this->bookingWithMeta([
            'search_criteria' => ['trip_type' => 'one_way'],
            'normalized_offer_snapshot' => [
                'segments' => [
                    ['origin' => 'KHI', 'destination' => 'DXB', 'carrier' => 'PK', 'booking_class' => 'Y'],
                ],
                'validating_carrier' => 'PK',
            ],
        ]);

        $selection = app(SabreCertifiedRouteSelector::class)->selectForBooking($booking);

        $this->assertNotSame('/v2/passengers/create', $selection['endpoint_path'] ?? '');
        $this->assertNotSame('/v1/trip/orders/createBooking', $selection['endpoint_path'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function bookingWithMeta(array $meta): Booking
    {
        $booking = new Booking;
        $booking->supplier = 'sabre';
        $booking->meta = array_merge(['supplier_provider' => 'sabre'], $meta);
        $booking->setRelation('passengers', collect());
        $booking->setRelation('contact', null);
        $booking->setRelation('fareBreakdown', null);

        return $booking;
    }
}
