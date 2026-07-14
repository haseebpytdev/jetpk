<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Models\User;
use App\Support\Bookings\ControlledStaffSabreHostNoopRetryGate;
use App\Support\Bookings\SupplierBookingAttemptResolution;
use App\Support\Bookings\SupplierBookingPreflightGuard;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierBookingHostNoopRetryGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_resolution_skips_retry_blocked_wrapper_attempt(): void
    {
        $booking = Booking::factory()->create();
        $hostNoop = SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'attempted_at' => now()->subMinutes(5),
            'completed_at' => now()->subMinutes(5),
        ]);
        $wrapper = SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'blocked',
            'error_code' => 'supplier_booking_retry_not_allowed',
            'safe_summary' => [
                'source' => 'admin',
                'reason' => 'supplier_booking_retry_not_allowed',
                'prior_error_code' => 'sabre_booking_application_error',
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $meaningful = SupplierBookingAttemptResolution::resolveLatestMeaningfulCreateAttempt(
            $booking->fresh()->supplierBookingAttempts,
        );

        $this->assertNotNull($meaningful);
        $this->assertSame($hostNoop->id, $meaningful->id);
        $this->assertNotSame($wrapper->id, $meaningful->id);
        $this->assertTrue(SupplierBookingAttemptResolution::isRetryBlockedWrapperAttempt($wrapper));
        $this->assertFalse(SupplierBookingAttemptResolution::isRetryBlockedWrapperAttempt($hostNoop));
    }

    public function test_preflight_allows_controlled_host_noop_diagnostic_retry(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->hostNoopBookingFixture();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => $this->hostNoopSafeSummary(),
            'attempted_by' => $admin->id,
            'attempted_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(10),
        ]);

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['passengers', 'contact', 'supplierBookingAttempts']),
            $admin,
            'admin',
            false,
            true,
        );

        $this->assertNull($result);
    }

    public function test_preflight_blocks_host_noop_after_safe_create_summary_exists(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->hostNoopBookingFixture();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => array_merge($this->hostNoopSafeSummary(), $this->safeCreateSummary()),
            'attempted_by' => $admin->id,
            'attempted_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(10),
        ]);

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['passengers', 'contact', 'supplierBookingAttempts']),
            $admin,
            'admin',
            false,
            true,
        );

        $this->assertNotNull($result);
        $this->assertSame('supplier_booking_retry_not_allowed', $result->error_code);
    }

    public function test_preflight_allows_host_noop_retry_when_latest_is_blocked_wrapper(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->hostNoopBookingFixture();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => $this->hostNoopSafeSummary(),
            'attempted_by' => $admin->id,
            'attempted_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(10),
        ]);
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'blocked',
            'error_code' => 'supplier_booking_retry_not_allowed',
            'safe_summary' => [
                'source' => 'admin',
                'reason' => 'supplier_booking_retry_not_allowed',
                'prior_error_code' => 'sabre_booking_application_error',
            ],
            'attempted_by' => $admin->id,
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['passengers', 'contact', 'supplierBookingAttempts']),
            $admin,
            'admin',
            false,
            true,
        );

        $this->assertNull($result);
    }

    public function test_preflight_does_not_unlock_host_noop_for_public_checkout(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->hostNoopBookingFixture();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => $this->hostNoopSafeSummary(),
            'attempted_by' => $admin->id,
            'attempted_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(10),
        ]);

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['passengers', 'contact', 'supplierBookingAttempts']),
            $admin,
            'public_checkout',
            false,
            true,
        );

        $this->assertNotNull($result);
        $this->assertSame('supplier_booking_retry_not_allowed', $result->error_code);
    }

    public function test_preflight_blocks_unrelated_application_error(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->hostNoopBookingFixture();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => [
                'response_error_messages' => ['Unexpected supplier application fault'],
            ],
            'attempted_by' => $admin->id,
            'attempted_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(10),
        ]);

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['passengers', 'contact', 'supplierBookingAttempts']),
            $admin,
            'admin',
            false,
            true,
        );

        $this->assertNotNull($result);
        $this->assertSame('supplier_booking_retry_not_allowed', $result->error_code);
    }

    public function test_preflight_blocks_host_noop_when_pnr_exists(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->hostNoopBookingFixture(['pnr' => 'ABC123']);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $attempt = SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => $this->hostNoopSafeSummary(),
            'attempted_by' => $admin->id,
            'attempted_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(10),
        ]);

        $this->assertFalse(app(ControlledStaffSabreHostNoopRetryGate::class)->allows(
            $booking->fresh(),
            $attempt,
            true,
            'admin',
        ));
    }

    public function test_preflight_blocks_stale_segment_retry_not_allowed_wrapper(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->hostNoopBookingFixture();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'failed',
            'error_code' => 'sabre_passenger_records_stale_shop_segment',
            'safe_summary' => ['stale_segment_route' => 'LHE-DXB'],
            'attempted_by' => $admin->id,
            'attempted_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(10),
        ]);

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['passengers', 'contact', 'supplierBookingAttempts']),
            $admin,
            'admin',
            false,
            true,
        );

        $this->assertNotNull($result);
        $this->assertSame('supplier_booking_retry_not_allowed', $result->error_code);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function hostNoopBookingFixture(array $overrides = []): Booking
    {
        $booking = Booking::factory()->create(array_merge([
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => 'sabre',
                'offer_validation_status' => 'valid',
                'offer_refresh_status' => 'refreshed',
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
                            'booking_class' => 'V',
                            'fare_basis_code' => 'VDLIT3GF',
                            'departure_at' => '2026-07-23T08:00:00Z',
                            'arrival_at' => '2026-07-23T10:00:00Z',
                        ],
                        [
                            'origin' => 'BAH',
                            'destination' => 'JED',
                            'carrier' => 'GF',
                            'flight_number' => '181',
                            'booking_class' => 'V',
                            'fare_basis_code' => 'VDLIT3GF',
                            'departure_at' => '2026-07-24T02:30:00Z',
                            'arrival_at' => '2026-07-24T05:30:00Z',
                        ],
                    ],
                    'raw_payload' => [
                        'distribution_channel' => 'GDS',
                        'itinerary_reference' => '1',
                        'sabre_shop_context' => [
                            'distribution_channel' => 'GDS',
                            'itinerary_ref' => '1',
                            'pricing_information_index' => 0,
                            'validating_carrier' => 'GF',
                            'leg_refs' => [1],
                            'schedule_refs' => [1, 2],
                            'fare_basis_codes' => ['VDLIT3GF', 'VDLIT3GF'],
                        ],
                        'sabre_booking_context' => [
                            'itinerary_reference' => '1',
                            'pricing_information_index' => 0,
                            'booking_classes_by_segment' => ['V', 'V'],
                            'fare_basis_codes_by_segment' => ['VDLIT3PK', 'VDLIT3PK'],
                            'segment_slice_count' => 2,
                        ],
                    ],
                ],
                'validated_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'validating_carrier' => 'GF',
                ],
                'sabre_booking_context' => [
                    'itinerary_reference' => '1',
                    'pricing_information_index' => 0,
                    'booking_classes_by_segment' => ['V', 'V'],
                    'fare_basis_codes_by_segment' => ['VDLIT3GF', 'VDLIT3GF'],
                    'segment_slice_count' => 2,
                ],
            ],
        ], $overrides));

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
            'first_name' => 'Test',
            'last_name' => 'User',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passenger_type' => 'adult',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'test@example.com',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function hostNoopSafeSummary(): array
    {
        return [
            'http_status' => 200,
            'endpoint_path' => '/v2.4.0/passenger/records?mode=create',
            'payload_schema' => 'iati_like_cpnr_v2_4_gds',
            'response_error_codes' => [
                'ERR.SP.PROVIDER_ERROR',
                'WARN.SWS.HOST.ERROR_IN_RESPONSE',
                '0118',
            ],
            'response_error_messages' => [
                'EnhancedAirBookRQ: FLIGHT NOOP FOR THIS FLIGHT/DATE',
                'SYSTEM UNABLE TO PROCESS',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function safeCreateSummary(): array
    {
        return [
            'create_segment_count' => 2,
            'create_segment_source' => 'refreshed_offer',
            'create_segments_summary' => [
                ['carrier' => 'PK', 'flight_number' => '301', 'booking_class' => 'V'],
                ['carrier' => 'PK', 'flight_number' => '741', 'booking_class' => 'V'],
            ],
        ];
    }
}
