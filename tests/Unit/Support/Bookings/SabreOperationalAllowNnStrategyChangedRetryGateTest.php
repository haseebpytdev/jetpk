<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Support\Bookings\SabreOperationalAllowNnStrategyChangedRetryGate;
use App\Support\Bookings\SabreOperationalPnrReadiness;
use App\Support\Bookings\SabrePnrFailureClassifier;
use App\Support\Bookings\SabreSafeRefreshContext;
use App\Support\Bookings\SupplierBookingPreflightGuard;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SabreOperationalAllowNnStrategyChangedRetryGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->configureOperationalNnRetryBase();
    }

    public function test_preflight_allows_operational_nn_halt_strategy_changed_retry_with_message_only_safe_summary(): void
    {
        $booking = $this->operationalBookingFixture();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $this->seedPriorNnHaltAttempt($booking, $admin, $this->productionLikeNnHaltSafeSummary());

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['passengers', 'contact', 'supplierBookings', 'supplierBookingAttempts']),
            $admin,
            'admin',
            false,
            true,
        );

        $this->assertNull($result);
        $this->assertTrue(app(SabreOperationalAllowNnStrategyChangedRetryGate::class)->allows(
            $booking->fresh(['passengers', 'contact', 'supplierBookings', 'supplierBookingAttempts']),
            SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderBy('id')->first(),
            true,
            'admin',
        ));
    }

    public function test_preflight_blocks_when_allow_nn_flag_off(): void
    {
        config(['suppliers.sabre.cpnr_allow_nn_halt_on_status_cert_operational' => false]);

        $booking = $this->operationalBookingFixture();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $this->seedPriorNnHaltAttempt($booking, $admin, $this->productionLikeNnHaltSafeSummary());

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['passengers', 'contact', 'supplierBookings', 'supplierBookingAttempts']),
            $admin,
            'admin',
            false,
            true,
        );

        $this->assertNotNull($result);
        $this->assertSame('supplier_booking_retry_not_allowed', $result->error_code);
    }

    public function test_preflight_blocks_when_pnr_exists(): void
    {
        $booking = $this->operationalBookingFixture(['pnr' => 'ABCDEF']);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $this->seedPriorNnHaltAttempt($booking, $admin, $this->productionLikeNnHaltSafeSummary());

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['passengers', 'contact', 'supplierBookings', 'supplierBookingAttempts']),
            $admin,
            'admin',
            false,
            true,
        );

        $this->assertNotNull($result);
        $this->assertTrue($result->success);
        $this->assertSame('existing_supplier_identity', $result->safe_summary['reason'] ?? null);
    }

    public function test_preflight_blocks_when_supplier_reference_exists(): void
    {
        $booking = $this->operationalBookingFixture(['supplier_reference' => 'SUPREF1']);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $this->seedPriorNnHaltAttempt($booking, $admin, $this->productionLikeNnHaltSafeSummary());

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['passengers', 'contact', 'supplierBookings', 'supplierBookingAttempts']),
            $admin,
            'admin',
            false,
            true,
        );

        $this->assertNotNull($result);
        $this->assertTrue($result->success);
        $this->assertSame('existing_supplier_identity', $result->safe_summary['reason'] ?? null);
    }

    public function test_preflight_blocks_when_prior_attempt_had_nn_omitted(): void
    {
        $booking = $this->operationalBookingFixture();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $this->seedPriorNnHaltAttempt($booking, $admin, array_merge(
            $this->productionLikeNnHaltSafeSummary(),
            ['create_halt_on_status_nn_omitted' => true],
        ));

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['passengers', 'contact', 'supplierBookings', 'supplierBookingAttempts']),
            $admin,
            'admin',
            false,
            true,
        );

        $this->assertNotNull($result);
        $this->assertSame('supplier_booking_retry_not_allowed', $result->error_code);
    }

    public function test_preflight_blocks_after_exhausted_strategy_retry_with_nn_omitted_failure(): void
    {
        $booking = $this->operationalBookingFixture();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $this->seedPriorNnHaltAttempt($booking, $admin, $this->productionLikeNnHaltSafeSummary());
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => array_merge($this->productionLikeNnHaltSafeSummary(), [
                'create_halt_on_status_nn_omitted' => true,
                'retry_policy' => SabreOperationalAllowNnStrategyChangedRetryGate::RETRY_POLICY,
            ]),
            'attempted_by' => $admin->id,
            'attempted_at' => now()->subMinutes(2),
            'completed_at' => now()->subMinutes(2),
        ]);

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['passengers', 'contact', 'supplierBookings', 'supplierBookingAttempts']),
            $admin,
            'admin',
            false,
            true,
        );

        $this->assertNotNull($result);
        $this->assertSame('supplier_booking_retry_not_allowed', $result->error_code);
    }

    public function test_preflight_blocks_non_nn_application_error(): void
    {
        $booking = $this->operationalBookingFixture();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => [
                'response_error_messages' => ['EnhancedAirBookRQ: FLIGHT NOOP FOR THIS FLIGHT/DATE'],
                'response_error_codes' => ['ERR.SP.PROVIDER_ERROR', '0118'],
            ],
            'attempted_by' => $admin->id,
            'attempted_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(10),
        ]);

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['passengers', 'contact', 'supplierBookings', 'supplierBookingAttempts']),
            $admin,
            'admin',
            false,
            true,
        );

        $this->assertNotNull($result);
        $this->assertSame('supplier_booking_retry_not_allowed', $result->error_code);
    }

    public function test_preflight_blocks_mixed_carrier_operational_readiness_false(): void
    {
        $booking = $this->mixedCarrierOperationalBooking();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $this->seedPriorNnHaltAttempt($booking, $admin, $this->productionLikeNnHaltSafeSummary());

        $this->assertFalse(app(SabreOperationalPnrReadiness::class)->wouldAttemptPnr(
            $booking->fresh(['passengers', 'contact', 'supplierBookings']),
        ));

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['passengers', 'contact', 'supplierBookings', 'supplierBookingAttempts']),
            $admin,
            'admin',
            false,
            true,
        );

        $this->assertNotNull($result);
        $this->assertSame('supplier_booking_retry_not_allowed', $result->error_code);
    }

    public function test_preflight_blocks_missing_docs_operational_readiness_false(): void
    {
        $booking = $this->operationalBookingFixture();
        $booking->passengers()->update(['passport_number' => null, 'passport_expiry_date' => null]);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $this->seedPriorNnHaltAttempt($booking, $admin, $this->productionLikeNnHaltSafeSummary());

        $this->assertFalse(app(SabreOperationalPnrReadiness::class)->wouldAttemptPnr(
            $booking->fresh(['passengers', 'contact', 'supplierBookings']),
        ));

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['passengers', 'contact', 'supplierBookings', 'supplierBookingAttempts']),
            $admin,
            'admin',
            false,
            true,
        );

        $this->assertNotNull($result);
        $this->assertSame('supplier_booking_retry_not_allowed', $result->error_code);
    }

    public function test_classifier_detects_nn_halt_from_response_messages_only(): void
    {
        $this->assertTrue(SabrePnrFailureClassifier::safeSummaryIndicatesPriorNnHaltOnStatusFailure([
            'response_error_messages' => [
                'WARN.SP.HALT_ON_STATUS_RECEIVED',
                'Flight GF767 returned status code NN',
            ],
        ]));
    }

    protected function configureOperationalNnRetryBase(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => true,
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true,
            'suppliers.sabre.cpnr_allow_nn_halt_on_status_cert_operational' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.certified_route_selector_public_checkout_enabled' => true,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $conn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://api.cert.platform.sabre.com',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function operationalBookingFixture(array $overrides = []): Booking
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
                    'supplier_connection_id' => $conn->id,
                    'id' => 'ops-offer',
                    'offer_id' => 'ops-offer',
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
                        'distribution_channel' => 'GDS',
                        'sabre_booking_context' => [
                            'itinerary_reference' => '1',
                            'pricing_information_index' => 0,
                            'booking_classes_by_segment' => ['W', 'W'],
                            'fare_basis_codes_by_segment' => ['WDLIT3PK', 'WDLIT3PK'],
                            'segment_slice_count' => 2,
                        ],
                    ],
                ],
                'sabre_booking_context' => [
                    'itinerary_reference' => '1',
                    'pricing_information_index' => 0,
                    'booking_classes_by_segment' => ['W', 'W'],
                    'fare_basis_codes_by_segment' => ['WDLIT3PK', 'WDLIT3PK'],
                    'segment_slice_count' => 2,
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
            'checkout_search_id' => 'ops-checkout-search',
            'checkout_offer_id' => 'ops-checkout-offer',
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

        return $booking->fresh(['passengers', 'contact', 'supplierBookings']);
    }

    protected function mixedCarrierOperationalBooking(): Booking
    {
        $booking = $this->operationalBookingFixture();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $snapshot['segments'][1]['carrier'] = 'EK';
        $meta['normalized_offer_snapshot'] = $snapshot;
        $booking->forceFill(['meta' => $meta])->save();

        return $booking->fresh(['passengers', 'contact', 'supplierBookings']);
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    protected function seedPriorNnHaltAttempt(Booking $booking, User $admin, array $safeSummary): void
    {
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => $safeSummary,
            'attempted_by' => $admin->id,
            'attempted_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(10),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function productionLikeNnHaltSafeSummary(): array
    {
        return [
            'source' => 'sabre_booking_service',
            'http_status' => 200,
            'live_call_attempted' => true,
            'reason_code' => 'sabre_passenger_records_halt_on_status_nn',
            'response_error_messages' => [
                'WARN.SP.HALT_ON_STATUS_RECEIVED',
                'Flight GF767 returned status code NN',
            ],
        ];
    }
}
