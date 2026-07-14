<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabrePreCheckoutSellabilityDryRun;
use App\Support\Bookings\SabreSafeRefreshContext;
use App\Support\Bookings\SabreVerifiedAutoPnrReadiness;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabrePreCheckoutSellabilityDryRunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->configureControlledConnecting();
        Http::fake();
    }

    public function test_booking_44_like_returns_exact_success_without_pnr_creation(): void
    {
        $booking = $this->gfVerifiedConnectingBooking(['pnr' => 'SZFXWM']);
        $statusBefore = $booking->status;
        $pnrBefore = $booking->pnr;

        $dryRun = app(SabrePreCheckoutSellabilityDryRun::class)->evaluate($booking);

        $this->assertSame(SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_SUCCESS, $dryRun['dry_run_status']);
        $this->assertSame(44, $dryRun['evidence_booking_id_success']);
        $this->assertSame(SabrePreCheckoutSellabilityDryRun::ACTION_CANDIDATE_AUTO_PNR_LATER, $dryRun['recommended_checkout_action']);
        $this->assertFalse($dryRun['public_auto_pnr_allowed_now']);
        $this->assertFalse($dryRun['live_supplier_call_attempted']);
        $this->assertFalse($dryRun['booking_status_updated']);

        $booking->refresh();
        $this->assertSame($statusBefore, $booking->status);
        $this->assertSame($pnrBefore, $booking->pnr);
        $this->assertSame(0, SupplierBookingAttempt::query()->where('booking_id', $booking->id)->where('action', 'create_pnr')->count());

        Http::assertNothingSent();
    }

    public function test_booking_46_like_returns_exact_failed_evidence(): void
    {
        $booking = $this->gfBooking46LikeConnectingBooking();

        $dryRun = app(SabrePreCheckoutSellabilityDryRun::class)->evaluate($booking);

        $this->assertSame(SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_FAILED, $dryRun['dry_run_status']);
        $this->assertSame(46, $dryRun['evidence_booking_id_failed']);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_FARE_RBD_CARRIER_NOT_SELLABLE, $dryRun['dry_run_reason_code']);
        $this->assertSame(SabrePreCheckoutSellabilityDryRun::ACTION_BLOCKED_SAME_OFFER, $dryRun['recommended_checkout_action']);
        $this->assertFalse($dryRun['public_auto_pnr_allowed_now']);
        $this->assertFalse($dryRun['live_supplier_call_attempted']);

        Http::assertNothingSent();
    }

    public function test_booking_43_like_returns_host_noop_blocked(): void
    {
        $booking = $this->pkHostNoopConnectingBooking();

        $dryRun = app(SabrePreCheckoutSellabilityDryRun::class)->evaluate($booking);

        $this->assertSame(SabreCertifiedRouteSelector::EVIDENCE_STATUS_HOST_NOOP_BLOCKED, $dryRun['dry_run_status']);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_HOST_NOOP_BLOCKED, $dryRun['dry_run_reason_code']);
        $this->assertSame(SabrePreCheckoutSellabilityDryRun::ACTION_BLOCKED_SAME_OFFER, $dryRun['recommended_checkout_action']);
        $this->assertFalse($dryRun['public_auto_pnr_allowed_now']);

        Http::assertNothingSent();
    }

    public function test_booking_45_like_returns_insufficient_flight_date_sellability_evidence(): void
    {
        $booking = $this->gfBooking45LikeInsufficientConnectingBooking();

        $dryRun = app(SabrePreCheckoutSellabilityDryRun::class)->evaluate($booking);

        $this->assertSame(SabreCertifiedRouteSelector::EVIDENCE_STATUS_INSUFFICIENT_FLIGHT_DATE, $dryRun['dry_run_status']);
        $this->assertNull($dryRun['evidence_booking_id_success']);
        $this->assertNull($dryRun['evidence_booking_id_failed']);
        $this->assertSame(SabrePreCheckoutSellabilityDryRun::ACTION_FRESH_SEARCH_RECOMMENDED, $dryRun['recommended_checkout_action']);
        $this->assertFalse($dryRun['public_auto_pnr_allowed_now']);

        Http::assertNothingSent();
    }

    public function test_feature_flag_off_keeps_public_auto_pnr_allowed_now_false(): void
    {
        config(['suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false]);
        $booking = $this->gfVerifiedConnectingBooking();

        $dryRun = app(SabrePreCheckoutSellabilityDryRun::class)->evaluate($booking);

        $this->assertFalse($dryRun['public_auto_pnr_allowed_now']);
    }

    public function test_persist_checkout_meta_writes_safe_snapshot_without_status_change(): void
    {
        $booking = $this->gfVerifiedConnectingBooking();
        $statusBefore = $booking->status;
        $metaBefore = is_array($booking->meta) ? $booking->meta : [];
        $strategyBefore = $metaBefore['create_payload_strategy_version'] ?? null;

        $service = app(SabrePreCheckoutSellabilityDryRun::class);
        $dryRun = $service->evaluateAndPersist($booking);

        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['pre_checkout_sellability_dry_run'] ?? null) ? $meta['pre_checkout_sellability_dry_run'] : [];

        $this->assertSame($dryRun['dry_run_status'], $snapshot['status'] ?? null);
        $this->assertSame($dryRun['dry_run_reason_code'], $snapshot['reason_code'] ?? null);
        $this->assertSame($dryRun['recommended_checkout_action'], $snapshot['recommended_checkout_action'] ?? null);
        $this->assertFalse($snapshot['public_auto_pnr_allowed_now'] ?? true);
        $this->assertFalse($snapshot['live_supplier_call_attempted'] ?? true);
        $this->assertFalse($snapshot['booking_status_updated'] ?? true);
        $this->assertNotEmpty($snapshot['generated_at'] ?? '');
        $this->assertArrayNotHasKey('segment_summary', $snapshot);

        $presentation = is_array($meta['pre_checkout_sellability_presentation'] ?? null)
            ? $meta['pre_checkout_sellability_presentation']
            : [];
        $this->assertSame('Verified automation candidate', $presentation['label'] ?? null);
        $this->assertFalse($presentation['should_block_public_checkout'] ?? true);
        $this->assertFalse($presentation['should_attempt_auto_pnr'] ?? true);
        $this->assertNotEmpty($presentation['customer_message'] ?? '');
        $this->assertNotEmpty($presentation['staff_message'] ?? '');
        $this->assertNotEmpty($presentation['generated_at'] ?? '');

        $this->assertSame($statusBefore, $booking->status);
        $this->assertSame($strategyBefore, $meta['create_payload_strategy_version'] ?? null);
    }

    public function test_non_sabre_booking_skips_meta_persist(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Draft,
            'meta' => ['supplier_provider' => SupplierProvider::Duffel->value],
        ]);

        app(SabrePreCheckoutSellabilityDryRun::class)->evaluateAndPersist($booking);

        $meta = is_array($booking->fresh()->meta) ? $booking->fresh()->meta : [];
        $this->assertArrayNotHasKey('pre_checkout_sellability_dry_run', $meta);
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
            'checkout_search_id' => 'gf-verified-precheckout-search',
            'checkout_offer_id' => 'gf-verified-precheckout-offer',
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
            'checkout_search_id' => 'gf-booking-46-precheckout-search',
            'checkout_offer_id' => 'gf-booking-46-precheckout-offer',
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

    protected function gfBooking45LikeInsufficientConnectingBooking(): Booking
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
                            'booking_class' => 'N',
                            'fare_basis_code' => 'NDLIT3PK',
                            'departure_at' => '2026-07-31T15:10:00',
                        ],
                        [
                            'origin' => 'BAH',
                            'destination' => 'JED',
                            'carrier' => 'GF',
                            'flight_number' => '181',
                            'booking_class' => 'N',
                            'fare_basis_code' => 'NDLIT3PK',
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
            'checkout_search_id' => 'gf-booking-45-precheckout-search',
            'checkout_offer_id' => 'gf-booking-45-precheckout-offer',
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
}
