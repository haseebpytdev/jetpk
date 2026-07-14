<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabrePreCheckoutKnownFailureSoftBlock;
use App\Support\Bookings\SabreSafeRefreshContext;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreDiagnoseVerifiedAutoPnrPrecheckoutCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.verified_multiseg_auto_pnr_enabled', false);
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.precheckout_known_failure_soft_block_enabled', false);
        Config::set('suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled', true);
        Config::set('suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled', false);
        Http::fake();
    }

    public function test_precheckout_command_outputs_presentation_fields_without_live_sabre_call(): void
    {
        $booking = $this->gfVerifiedConnectingBooking();

        $exit = Artisan::call('sabre:diagnose-verified-auto-pnr-candidate', [
            '--booking' => (string) $booking->id,
            '--precheckout' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('presentation_label=Verified automation candidate', $output);
        $this->assertStringContainsString('presentation_severity=success', $output);
        $this->assertStringContainsString('customer_message=', $output);
        $this->assertStringContainsString('staff_message=', $output);
        $this->assertStringContainsString('should_block_public_checkout=false', $output);
        $this->assertStringContainsString('should_attempt_auto_pnr=false', $output);
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        $this->assertStringContainsString('booking_status_updated=false', $output);
        $this->assertStringContainsString('soft_block_config_enabled=false', $output);
        $this->assertStringContainsString('would_soft_block_public_checkout=false', $output);
        $this->assertStringContainsString('safe_customer_redirect_message=', $output);

        Http::assertNothingSent();
    }

    public function test_precheckout_command_soft_block_fields_when_config_true_for_booking_46_like(): void
    {
        Config::set('suppliers.sabre.precheckout_known_failure_soft_block_enabled', true);
        $booking = $this->gfBooking46LikeConnectingBooking();

        Artisan::call('sabre:diagnose-verified-auto-pnr-candidate', [
            '--booking' => (string) $booking->id,
            '--precheckout' => true,
        ]);

        $output = Artisan::output();

        $this->assertStringContainsString('soft_block_config_enabled=true', $output);
        $this->assertStringContainsString('would_soft_block_public_checkout=true', $output);
        $this->assertStringContainsString('should_block_public_checkout=true', $output);
        $this->assertStringContainsString('soft_block_reason='.SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_FAILED, $output);
        $this->assertStringContainsString(
            'safe_customer_redirect_message='.SabrePreCheckoutKnownFailureSoftBlock::customerRedirectMessage(),
            $output
        );

        Http::assertNothingSent();
    }

    public function test_precheckout_command_would_not_soft_block_when_config_false_for_booking_46_like(): void
    {
        Config::set('suppliers.sabre.precheckout_known_failure_soft_block_enabled', false);
        $booking = $this->gfBooking46LikeConnectingBooking();

        Artisan::call('sabre:diagnose-verified-auto-pnr-candidate', [
            '--booking' => (string) $booking->id,
            '--precheckout' => true,
        ]);

        $output = Artisan::output();

        $this->assertStringContainsString('would_soft_block_public_checkout=false', $output);
        $this->assertStringContainsString('should_block_public_checkout=false', $output);

        Http::assertNothingSent();
    }

    public function test_precheckout_json_includes_presentation_key(): void
    {
        $booking = $this->gfVerifiedConnectingBooking();

        Artisan::call('sabre:diagnose-verified-auto-pnr-candidate', [
            '--booking' => (string) $booking->id,
            '--precheckout' => true,
            '--json' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('pre_checkout_sellability_dry_run_json=', $output);
        $this->assertStringContainsString('"presentation"', $output);
        $this->assertStringContainsString('"label":"Verified automation candidate"', $output);

        Http::assertNothingSent();
    }

    protected function gfVerifiedConnectingBooking(): Booking
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
        ]);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => '2026-07-29',
            'adults' => 1,
        ], [
            'checkout_search_id' => 'gf-verified-precheckout-cli-search',
            'checkout_offer_id' => 'gf-verified-precheckout-cli-offer',
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
            'checkout_search_id' => 'gf-booking-46-precheckout-cli-search',
            'checkout_offer_id' => 'gf-booking-46-precheckout-cli-offer',
            'supplier_total' => 100.0,
            'supplier_currency' => 'PKR',
        ]);
        $booking->forceFill(['meta' => $meta])->save();

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'latestSupplierBookingAttempt']);
    }
}
