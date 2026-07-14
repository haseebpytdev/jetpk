<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabrePreCheckoutKnownFailureSoftBlock;
use App\Support\Bookings\SabrePreCheckoutSellabilityDryRun;
use App\Support\Bookings\SabrePreCheckoutSellabilityPresentation;
use App\Support\Bookings\SabreSafeRefreshContext;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabrePreCheckoutSellabilityPresentationTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    protected array $forbiddenCustomerTokens = [
        'fare_rbd_carrier_not_sellable',
        'host_noop_blocked',
        'exact_failed_evidence',
        'exact_success_evidence',
        'insufficient_flight_date_sellability_evidence',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->configureControlledConnecting();
        Http::fake();
    }

    public function test_booking_44_like_maps_to_safe_candidate_message_without_pnr_guarantee(): void
    {
        $booking = $this->gfVerifiedConnectingBooking();
        $dryRun = app(SabrePreCheckoutSellabilityDryRun::class)->evaluate($booking);
        $presentation = SabrePreCheckoutSellabilityPresentation::fromDryRun($dryRun);

        $this->assertSame('Verified automation candidate', $presentation['label']);
        $this->assertSame('success', $presentation['severity']);
        $this->assertFalse($presentation['should_block_public_checkout']);
        $this->assertFalse($presentation['should_attempt_auto_pnr']);
        $this->assertStringNotContainsStringIgnoringCase('PNR guaranteed', $presentation['customer_message']);
        $this->assertStringNotContainsStringIgnoringCase('ticket guaranteed', $presentation['customer_message']);

        $confirmNote = SabrePreCheckoutSellabilityPresentation::confirmationNote($presentation);
        $this->assertSame('Your itinerary is queued for reservation processing.', $confirmNote);
        $this->assertCustomerMessagesAreSafe($presentation, $confirmNote);
    }

    public function test_booking_46_like_maps_to_fresh_search_warning_without_blocking_checkout(): void
    {
        $booking = $this->gfBooking46LikeConnectingBooking();
        $dryRun = app(SabrePreCheckoutSellabilityDryRun::class)->evaluate($booking);
        $presentation = SabrePreCheckoutSellabilityPresentation::fromDryRun($dryRun);

        $this->assertSame('Fresh search recommended', $presentation['label']);
        $this->assertSame('warning', $presentation['severity']);
        $this->assertFalse($presentation['should_block_public_checkout']);
        $this->assertFalse($presentation['should_attempt_auto_pnr']);
        $this->assertStringContainsString('fresh search', strtolower($presentation['customer_message']));
        $this->assertStringContainsString('Do not retry same offer', $presentation['staff_message']);

        $confirmNote = SabrePreCheckoutSellabilityPresentation::confirmationNote($presentation);
        $this->assertCustomerMessagesAreSafe($presentation, $confirmNote);
    }

    public function test_booking_43_like_maps_to_do_not_retry_staff_message(): void
    {
        $booking = $this->pkHostNoopConnectingBooking();
        $dryRun = app(SabrePreCheckoutSellabilityDryRun::class)->evaluate($booking);
        $presentation = SabrePreCheckoutSellabilityPresentation::fromDryRun($dryRun);

        $this->assertSame('Do not retry same itinerary', $presentation['label']);
        $this->assertSame('danger', $presentation['severity']);
        $this->assertStringContainsString('Do not retry', $presentation['staff_message']);
        $this->assertFalse($presentation['should_block_public_checkout']);

        $confirmNote = SabrePreCheckoutSellabilityPresentation::confirmationNote($presentation);
        $this->assertCustomerMessagesAreSafe($presentation, $confirmNote);
    }

    public function test_booking_45_like_maps_to_availability_confirmation_message(): void
    {
        $booking = $this->gfBooking45LikeInsufficientConnectingBooking();
        $dryRun = app(SabrePreCheckoutSellabilityDryRun::class)->evaluate($booking);
        $presentation = SabrePreCheckoutSellabilityPresentation::fromDryRun($dryRun);

        $this->assertSame('Availability needs confirmation', $presentation['label']);
        $this->assertSame('info', $presentation['severity']);
        $this->assertStringContainsString('confirmation', strtolower($presentation['customer_message']));

        $confirmNote = SabrePreCheckoutSellabilityPresentation::confirmationNote($presentation);
        $this->assertSame('Airline availability will be confirmed before reservation is finalized.', $confirmNote);
        $this->assertCustomerMessagesAreSafe($presentation, $confirmNote);
    }

    public function test_resolve_for_booking_derives_from_dry_run_meta_when_presentation_meta_absent(): void
    {
        $booking = $this->gfVerifiedConnectingBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['pre_checkout_sellability_dry_run'] = [
            'status' => SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_SUCCESS,
            'reason_code' => '',
            'recommended_checkout_action' => SabrePreCheckoutSellabilityDryRun::ACTION_CANDIDATE_AUTO_PNR_LATER,
            'public_auto_pnr_allowed_now' => false,
            'live_supplier_call_attempted' => false,
            'booking_status_updated' => false,
            'evidence_booking_id_success' => 44,
            'evidence_booking_id_failed' => null,
            'generated_at' => now()->toIso8601String(),
        ];
        unset($meta['pre_checkout_sellability_presentation']);
        $booking->forceFill(['meta' => $meta])->save();

        $resolved = SabrePreCheckoutSellabilityPresentation::resolveForBooking($booking->fresh());

        $this->assertIsArray($resolved);
        $this->assertSame('Verified automation candidate', $resolved['label'] ?? null);
        $this->assertFalse($resolved['should_block_public_checkout'] ?? true);
    }

    public function test_resolve_for_booking_returns_null_for_non_sabre(): void
    {
        $booking = Booking::factory()->create([
            'meta' => ['supplier_provider' => SupplierProvider::Duffel->value],
        ]);

        $this->assertNull(SabrePreCheckoutSellabilityPresentation::resolveForBooking($booking));
    }

    public function test_config_true_booking_46_and_43_set_should_block_public_checkout(): void
    {
        config(['suppliers.sabre.precheckout_known_failure_soft_block_enabled' => true]);

        foreach ([$this->gfBooking46LikeConnectingBooking(), $this->pkHostNoopConnectingBooking()] as $booking) {
            $presentation = SabrePreCheckoutSellabilityPresentation::fromDryRun(
                app(SabrePreCheckoutSellabilityDryRun::class)->evaluate($booking)
            );
            $this->assertTrue($presentation['should_block_public_checkout']);
            $this->assertFalse($presentation['should_attempt_auto_pnr']);
        }
    }

    public function test_config_true_booking_44_and_45_do_not_block_public_checkout(): void
    {
        config(['suppliers.sabre.precheckout_known_failure_soft_block_enabled' => true]);

        foreach ([$this->gfVerifiedConnectingBooking(), $this->gfBooking45LikeInsufficientConnectingBooking()] as $booking) {
            $presentation = SabrePreCheckoutSellabilityPresentation::fromDryRun(
                app(SabrePreCheckoutSellabilityDryRun::class)->evaluate($booking)
            );
            $this->assertFalse($presentation['should_block_public_checkout']);
            $this->assertFalse($presentation['should_attempt_auto_pnr']);
        }
    }

    public function test_soft_block_redirect_message_hides_internal_codes(): void
    {
        $message = SabrePreCheckoutKnownFailureSoftBlock::customerRedirectMessage();

        foreach ($this->forbiddenCustomerTokens as $token) {
            $this->assertStringNotContainsStringIgnoringCase($token, $message);
        }
        $this->assertStringNotContainsStringIgnoringCase('Sabre', $message);
    }

    /**
     * @param  array<string, mixed>  $presentation
     */
    protected function assertCustomerMessagesAreSafe(array $presentation, ?string $confirmNote): void
    {
        $customerFacing = [
            (string) ($presentation['customer_message'] ?? ''),
            (string) ($confirmNote ?? ''),
        ];

        foreach ($customerFacing as $message) {
            if ($message === '') {
                continue;
            }
            foreach ($this->forbiddenCustomerTokens as $token) {
                $this->assertStringNotContainsStringIgnoringCase($token, $message, 'Customer-facing text must not expose internal code: '.$token);
            }
        }
    }

    protected function configureControlledConnecting(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.precheckout_known_failure_soft_block_enabled' => false,
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
