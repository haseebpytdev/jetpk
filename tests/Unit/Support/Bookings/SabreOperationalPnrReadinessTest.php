<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Support\Bookings\SabreOperationalPnrReadiness;
use App\Support\Bookings\SabreSafeRefreshContext;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SabreOperationalPnrReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->configureOperationalFlags();
    }

    public function test_valid_same_carrier_pay_later_with_flags_on_would_attempt_pnr(): void
    {
        $booking = $this->operationalConnectingBooking();

        $result = app(SabreOperationalPnrReadiness::class)->evaluate($booking);

        $this->assertTrue($result['would_attempt_pnr']);
        $this->assertSame(SabreOperationalPnrReadiness::REASON_ELIGIBLE_OPERATIONAL, $result['reason_code']);
        $this->assertSame([], $result['blocking_conditions']);
        $this->assertTrue(app(SabreOperationalPnrReadiness::class)->wouldAttemptPnr($booking));
    }

    public function test_same_booking_with_flags_off_is_blocked_by_flags(): void
    {
        config(['suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false]);
        $booking = $this->operationalConnectingBooking();

        $result = app(SabreOperationalPnrReadiness::class)->evaluate($booking);

        $this->assertFalse($result['would_attempt_pnr']);
        $this->assertSame(SabreOperationalPnrReadiness::REASON_BLOCKED_BY_FLAGS, $result['reason_code']);
        $this->assertContains('operational_auto_pnr_enabled', $result['blocking_conditions']);
    }

    public function test_unknown_controlled_only_with_valid_structure_and_flags_on_would_attempt_pnr(): void
    {
        $booking = $this->unknownSameCarrierOperationalBooking();

        $result = app(SabreOperationalPnrReadiness::class)->evaluate($booking);

        $this->assertTrue($result['would_attempt_pnr']);
        $this->assertSame(SabreOperationalPnrReadiness::REASON_ELIGIBLE_OPERATIONAL, $result['reason_code']);
        $this->assertSame('unknown_controlled_only', $result['controlled_pnr_certification_status']);
    }

    public function test_existing_pnr_is_blocked_already_has_pnr(): void
    {
        $booking = $this->operationalConnectingBooking(['pnr' => 'ABC123']);

        $result = app(SabreOperationalPnrReadiness::class)->evaluate($booking);

        $this->assertFalse($result['would_attempt_pnr']);
        $this->assertSame(SabreOperationalPnrReadiness::REASON_BLOCKED_ALREADY_HAS_PNR, $result['reason_code']);
        $this->assertTrue($result['pnr_present']);
    }

    public function test_existing_supplier_reference_is_blocked(): void
    {
        $booking = $this->operationalConnectingBooking(['supplier_reference' => 'SUP-REF-1']);

        $result = app(SabreOperationalPnrReadiness::class)->evaluate($booking);

        $this->assertFalse($result['would_attempt_pnr']);
        $this->assertSame(SabreOperationalPnrReadiness::REASON_BLOCKED_ALREADY_HAS_SUPPLIER_REFERENCE, $result['reason_code']);
        $this->assertTrue($result['supplier_reference_present']);
    }

    public function test_missing_passenger_docs_is_blocked_missing_required_documents(): void
    {
        $booking = $this->operationalConnectingBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['search_criteria'] = ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'DXB'];
        $booking->forceFill(['meta' => $meta, 'travel_date' => now()->addMonths(2)])->save();
        $booking->passengers()->delete();
        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passenger_type' => 'adult',
            'passport_number' => null,
        ]);

        $result = app(SabreOperationalPnrReadiness::class)->evaluate($booking->fresh(['passengers', 'contact', 'supplierBookings']));

        $this->assertFalse($result['would_attempt_pnr']);
        $this->assertSame(SabreOperationalPnrReadiness::REASON_BLOCKED_MISSING_REQUIRED_DOCUMENTS, $result['reason_code']);
    }

    public function test_mixed_carrier_is_blocked_mixed_carrier(): void
    {
        $booking = $this->mixedCarrierOperationalBooking();

        $result = app(SabreOperationalPnrReadiness::class)->evaluate($booking);

        $this->assertFalse($result['would_attempt_pnr']);
        $this->assertSame(SabreOperationalPnrReadiness::REASON_BLOCKED_MIXED_CARRIER, $result['reason_code']);
        $this->assertTrue($result['mixed_carrier']);
    }

    protected function configureOperationalFlags(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => true,
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.ticketing_enabled' => false,
            'ota.passport_required_for_international' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function operationalConnectingBooking(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        $booking = Booking::factory()->create(array_merge([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'unpaid',
            'confirmation_method' => 'pay_later_booking_request',
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => $conn->id,
                'booking_method' => 'pay_later_booking_request',
                'confirmation_method' => 'pay_later_booking_request',
                'offer_validation_status' => 'valid',
                'search_criteria' => ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'JED'],
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
                        'sabre_booking_context' => [
                            'itinerary_reference' => '1',
                            'pricing_information_index' => 0,
                            'booking_classes_by_segment' => ['W', 'W'],
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
            'checkout_search_id' => 'ops-connecting-search',
            'checkout_offer_id' => 'ops-connecting-offer',
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
            'passport_number' => 'AB1234567',
            'passport_expiry_date' => now()->addYears(2)->toDateString(),
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'fareBreakdown']);
    }

    protected function unknownSameCarrierOperationalBooking(): Booking
    {
        $booking = $this->operationalConnectingBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $snapshot['validating_carrier'] = 'SV';
        $snapshot['segments'] = [
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
        ];
        $meta['normalized_offer_snapshot'] = $snapshot;
        $meta['search_criteria'] = ['trip_type' => 'one_way', 'origin' => 'ISB', 'destination' => 'DXB'];
        $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, [
            'trip_type' => 'one_way',
            'origin' => 'ISB',
            'destination' => 'DXB',
            'depart_date' => '2026-07-23',
            'adults' => 1,
        ], [
            'checkout_search_id' => 'sv-unknown-ops-search',
            'checkout_offer_id' => 'sv-unknown-ops-offer',
            'supplier_total' => 100.0,
            'supplier_currency' => 'PKR',
        ]);
        $booking->forceFill(['meta' => $meta])->save();

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'fareBreakdown']);
    }

    protected function mixedCarrierOperationalBooking(): Booking
    {
        $booking = $this->operationalConnectingBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $snapshot['segments'] = [
            [
                'origin' => 'LHE',
                'destination' => 'KHI',
                'carrier' => 'PK',
                'flight_number' => '301',
                'booking_class' => 'Y',
                'fare_basis_code' => 'YLOW',
                'departure_at' => '2026-07-29T08:00:00',
                'arrival_at' => '2026-07-29T10:00:00',
            ],
            [
                'origin' => 'KHI',
                'destination' => 'UET',
                'carrier' => 'GF',
                'flight_number' => '171',
                'booking_class' => 'Y',
                'fare_basis_code' => 'YLOW',
                'departure_at' => '2026-07-30T10:05:00',
                'arrival_at' => '2026-07-30T12:30:00',
            ],
        ];
        $meta['normalized_offer_snapshot'] = $snapshot;
        $meta['search_criteria'] = ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'UET'];
        $booking->forceFill(['meta' => $meta])->save();

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'fareBreakdown']);
    }
}
