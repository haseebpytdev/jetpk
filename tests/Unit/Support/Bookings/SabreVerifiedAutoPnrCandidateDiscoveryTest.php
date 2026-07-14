<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreSafeRefreshContext;
use App\Support\Bookings\SabreVerifiedAutoPnrCandidateDiscovery;
use App\Support\Bookings\SabreVerifiedAutoPnrReadiness;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SabreVerifiedAutoPnrCandidateDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->configureControlledConnecting();
    }

    public function test_booking_44_like_success_with_pnr_is_manual_review(): void
    {
        $booking = $this->gfVerifiedConnectingBooking(['pnr' => 'SZFXWM']);

        $diag = app(SabreVerifiedAutoPnrCandidateDiscovery::class)->diagnose($booking);

        $this->assertSame(SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_SUCCESS, $diag['evidence_status']);
        $this->assertSame(44, $diag['matched_success_booking_id']);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_PNR_ALREADY_EXISTS, $diag['readiness_reason_code']);
        $this->assertFalse($diag['public_auto_pnr_allowed_now']);
        $this->assertSame(SabreVerifiedAutoPnrCandidateDiscovery::ACTION_MANUAL_REVIEW, $diag['recommended_action']);
        $this->assertSame('present', $diag['pnr_status']);
        $this->assertSame('E5A_SAFE_STRUCTURE_V1', $diag['payload_strategy']);
    }

    public function test_create_pnr_success_strategy_survives_newer_pnr_retrieve_attempt(): void
    {
        $booking = $this->gfVerifiedConnectingBooking(['pnr' => 'SZFXWM']);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        unset($meta['create_payload_strategy_version']);
        $booking->forceFill(['meta' => $meta])->save();

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'success',
            'safe_summary' => [
                'create_payload_strategy_version' => 'E5A_SAFE_STRUCTURE_V1',
                'http_status' => 200,
            ],
            'attempted_at' => now()->subMinute(),
            'completed_at' => now()->subMinute(),
        ]);

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'pnr_retrieve',
            'status' => 'success',
            'safe_summary' => [
                'retrieve_http_status' => 200,
                'segment_count' => 2,
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $diag = app(SabreVerifiedAutoPnrCandidateDiscovery::class)->diagnose(
            $booking->fresh(['supplierBookings', 'latestSupplierBookingAttempt']),
        );

        $this->assertSame(SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_SUCCESS, $diag['evidence_status']);
        $this->assertSame(44, $diag['matched_success_booking_id']);
        $this->assertSame('E5A_SAFE_STRUCTURE_V1', $diag['payload_strategy']);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_PNR_ALREADY_EXISTS, $diag['readiness_reason_code']);
        $this->assertFalse($diag['public_auto_pnr_allowed_now']);
        $this->assertSame(SabreVerifiedAutoPnrCandidateDiscovery::ACTION_MANUAL_REVIEW, $diag['recommended_action']);
    }

    public function test_diagnose_includes_booking_reference(): void
    {
        $booking = $this->gfVerifiedConnectingBooking([
            'booking_reference' => 'PAR-1KWMA591',
        ]);

        $diag = app(SabreVerifiedAutoPnrCandidateDiscovery::class)->diagnose($booking);

        $this->assertSame('PAR-1KWMA591', $diag['booking_reference']);
    }

    public function test_booking_45_like_success_without_pnr_is_auto_pnr_candidate_when_flag_disabled(): void
    {
        $booking = $this->gfVerifiedConnectingBooking();

        $diag = app(SabreVerifiedAutoPnrCandidateDiscovery::class)->diagnose($booking);

        $this->assertSame(SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_SUCCESS, $diag['evidence_status']);
        $this->assertSame(44, $diag['matched_success_booking_id']);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_FEATURE_FLAG_DISABLED, $diag['readiness_reason_code']);
        $this->assertFalse($diag['public_auto_pnr_allowed_now']);
        $this->assertSame(SabreVerifiedAutoPnrCandidateDiscovery::ACTION_AUTO_PNR_CANDIDATE, $diag['recommended_action']);
        $this->assertSame('E5A_SAFE_STRUCTURE_V1', $diag['payload_strategy']);
    }

    public function test_booking_46_like_evidence_is_blocked_same_offer(): void
    {
        $booking = $this->gfBooking46LikeConnectingBooking();

        $diag = app(SabreVerifiedAutoPnrCandidateDiscovery::class)->diagnose($booking);

        $this->assertSame(SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_FAILED, $diag['evidence_status']);
        $this->assertSame(46, $diag['matched_failed_booking_id']);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_FARE_RBD_CARRIER_NOT_SELLABLE, $diag['readiness_reason_code']);
        $this->assertFalse($diag['public_auto_pnr_allowed_now']);
        $this->assertSame(SabreVerifiedAutoPnrCandidateDiscovery::ACTION_BLOCKED_SAME_OFFER, $diag['recommended_action']);
    }

    public function test_booking_43_like_host_noop_is_blocked(): void
    {
        $booking = $this->pkHostNoopConnectingBooking();

        $diag = app(SabreVerifiedAutoPnrCandidateDiscovery::class)->diagnose($booking);

        $this->assertSame(SabreCertifiedRouteSelector::EVIDENCE_STATUS_HOST_NOOP_BLOCKED, $diag['evidence_status']);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_HOST_NOOP_BLOCKED, $diag['readiness_reason_code']);
        $this->assertFalse($diag['public_auto_pnr_allowed_now']);
        $this->assertSame(SabreVerifiedAutoPnrCandidateDiscovery::ACTION_BLOCKED_SAME_OFFER, $diag['recommended_action']);
    }

    public function test_unknown_same_carrier_connecting_is_manual_review(): void
    {
        $booking = $this->unknownSameCarrierConnectingBooking();

        $diag = app(SabreVerifiedAutoPnrCandidateDiscovery::class)->diagnose($booking);

        $this->assertSame(SabreCertifiedRouteSelector::EVIDENCE_STATUS_UNKNOWN_CONTROLLED_ONLY, $diag['evidence_status']);
        $this->assertSame(SabreVerifiedAutoPnrCandidateDiscovery::ACTION_MANUAL_REVIEW, $diag['recommended_action']);
    }

    public function test_same_route_rbd_fare_different_flights_is_fresh_search_required(): void
    {
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
            ],
            [
                'origin' => 'BAH',
                'destination' => 'JED',
                'carrier' => 'GF',
                'flight_number' => '181',
                'booking_class' => 'W',
                'fare_basis_code' => 'WDLIT3PK',
                'departure_at' => '2026-07-24T02:30:00Z',
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

        $diag = app(SabreVerifiedAutoPnrCandidateDiscovery::class)->diagnose($booking->fresh(['supplierBookings', 'latestSupplierBookingAttempt']));

        $this->assertSame(SabreCertifiedRouteSelector::EVIDENCE_STATUS_INSUFFICIENT_FLIGHT_DATE, $diag['evidence_status']);
        $this->assertNull($diag['matched_success_booking_id']);
        $this->assertSame(SabreVerifiedAutoPnrCandidateDiscovery::ACTION_FRESH_SEARCH_REQUIRED, $diag['recommended_action']);
    }

    protected function configureControlledConnecting(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false,
            'suppliers.sabre.ticketing_enabled' => false,
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
                        ],
                        [
                            'origin' => 'BAH',
                            'destination' => 'JED',
                            'carrier' => 'GF',
                            'flight_number' => '171',
                            'booking_class' => 'W',
                            'fare_basis_code' => 'WDLIT3PK',
                            'departure_at' => '2026-07-30T10:05:00',
                        ],
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
            'checkout_search_id' => 'gf-verified-discovery-search',
            'checkout_offer_id' => 'gf-verified-discovery-offer',
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

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'latestSupplierBookingAttempt', 'fareBreakdown']);
    }

    protected function gfBooking46LikeConnectingBooking(): Booking
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
                        ],
                        [
                            'origin' => 'BAH',
                            'destination' => 'JED',
                            'carrier' => 'GF',
                            'flight_number' => '173',
                            'booking_class' => 'W',
                            'fare_basis_code' => 'WDLIT3PK',
                            'departure_at' => '2026-08-01T18:05:00',
                        ],
                    ],
                ],
            ],
        ]);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => '2026-07-31',
            'adults' => 1,
        ], [
            'checkout_search_id' => 'gf-booking-46-discovery-search',
            'checkout_offer_id' => 'gf-booking-46-discovery-offer',
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

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'latestSupplierBookingAttempt']);
    }

    protected function pkHostNoopConnectingBooking(): Booking
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => 1,
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
                        ],
                        [
                            'origin' => 'KHI',
                            'destination' => 'JED',
                            'carrier' => 'PK',
                            'flight_number' => '741',
                            'booking_class' => 'V',
                            'fare_basis_code' => 'VDLIT3PK',
                            'departure_at' => '2026-07-24T02:30:00Z',
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

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'latestSupplierBookingAttempt']);
    }

    protected function unknownSameCarrierConnectingBooking(): Booking
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => 1,
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
                        ],
                        [
                            'origin' => 'KHI',
                            'destination' => 'DXB',
                            'carrier' => 'SV',
                            'flight_number' => '702',
                            'booking_class' => 'Q',
                            'fare_basis_code' => 'QSV02',
                            'departure_at' => '2026-07-24T02:30:00Z',
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
            'checkout_search_id' => 'sv-unknown-discovery-search',
            'checkout_offer_id' => 'sv-unknown-discovery-offer',
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

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'latestSupplierBookingAttempt']);
    }
}
