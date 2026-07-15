<?php

namespace Tests\Feature\Console;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IatiBookingReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_readiness_command_reports_attempt_and_lock_diagnostics(): void
    {
        $booking = $this->iatiBooking();

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Iati->value,
            'action' => 'create_pnr',
            'status' => 'failed',
            'error_code' => 'supplier_booking_in_progress',
            'error_message' => 'Supplier booking already in progress.',
            'attempted_at' => now()->subMinute(),
            'completed_at' => now()->subMinute(),
        ]);

        $this->artisan('ota:iati-booking-readiness', ['--booking-id' => $booking->id])
            ->assertExitCode(0)
            ->expectsOutputToContain('last_supplier_attempt_status=failed')
            ->expectsOutputToContain('last_supplier_attempt_error_code=supplier_booking_in_progress')
            ->expectsOutputToContain('active_supplier_booking_attempt_id=')
            ->expectsOutputToContain('supplier_booking_lock_active=false');
    }

    #[Test]
    public function test_readiness_command_reports_eligible_paid_iati_booking_without_live_calls(): void
    {
        $booking = $this->iatiBooking();

        $this->artisan('ota:iati-booking-readiness', ['--booking-id' => $booking->id])
            ->assertExitCode(0)
            ->expectsOutputToContain('provider=iati')
            ->expectsOutputToContain('eligible_for_supplier_book=true')
            ->expectsOutputToContain('next_supplier_action=/book')
            ->expectsOutputToContain('live_supplier_call_attempted=false');
    }

    #[Test]
    public function test_readiness_command_reports_blocking_reasons_when_departure_key_missing(): void
    {
        $booking = $this->iatiBooking(includeDepartureFareKey: false);

        $this->artisan('ota:iati-booking-readiness', ['--booking-id' => $booking->id])
            ->assertExitCode(1)
            ->expectsOutputToContain('departure_fare_key_present=false')
            ->expectsOutputToContain('missing_departure_fare_key');
    }

    #[Test]
    public function test_readiness_command_unpaid_simple_iati_blocks_only_on_payment(): void
    {
        $booking = $this->simpleIatiBooking60Style('unpaid');

        $this->artisan('ota:iati-booking-readiness', ['--booking-id' => $booking->id])
            ->assertExitCode(1)
            ->expectsOutputToContain('eligible_for_supplier_book=false')
            ->expectsOutputToContain('payment_not_verified')
            ->doesntExpectOutputToContain('missing_selected_fare_option');
    }

    #[Test]
    public function test_readiness_command_paid_simple_iati_is_eligible_for_book(): void
    {
        $booking = $this->simpleIatiBooking60Style('paid');

        $this->artisan('ota:iati-booking-readiness', ['--booking-id' => $booking->id])
            ->assertExitCode(0)
            ->expectsOutputToContain('eligible_for_supplier_book=true')
            ->expectsOutputToContain('next_supplier_action=/book')
            ->doesntExpectOutputToContain('missing_selected_fare_option');
    }

    protected function iatiBooking(bool $includeDepartureFareKey = true): Booking
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Iati->value,
            'status' => BookingStatus::Pending,
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => SupplierProvider::Iati->value,
                'search_id' => '',
                'selected_fare_option_id' => 'iati-fare-2-85158-1',
                'selected_branded_fare_id' => 'iati_brand_1',
                'offer_validation_status' => 'valid',
                'selected_fare_family_option' => [
                    'option_key' => 'iati-fare-2-85158-1',
                    'departure_fare_key' => $includeDepartureFareKey ? 'dep-match-key' : null,
                    'name' => 'Fare 2',
                    'displayed_price' => 85158,
                ],
                'validated_offer_snapshot' => [
                    'offer_id' => 'offer-58',
                    'raw_payload' => [
                        'provider_context' => array_filter([
                            'departure_fare_key' => $includeDepartureFareKey ? 'dep-match-key' : null,
                        ]),
                    ],
                ],
            ],
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'pax@example.com',
            'phone' => '3001234567',
            'phone_country_code' => '92',
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'date_of_birth' => '1990-01-01',
            'passport_number' => 'AB1234567',
            'passport_expiry_date' => '2030-01-01',
            'nationality' => 'PK',
            'passenger_type' => 'adult',
        ]);

        return $booking;
    }

    protected function simpleIatiBooking60Style(string $paymentStatus): Booking
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Iati->value,
            'status' => BookingStatus::Pending,
            'payment_status' => $paymentStatus,
            'meta' => [
                'supplier_provider' => SupplierProvider::Iati->value,
                'offer_validation_status' => 'valid',
                'iati_context' => [
                    'departure_fare_key' => 'dep-pk-60',
                    'fare_detail_key' => 'fare-detail-pk-60',
                ],
                'validated_offer_snapshot' => [
                    'offer_id' => 'iati_7e96ed26e2213b49',
                    'raw_payload' => [
                        'provider_context' => [
                            'departure_fare_key' => 'dep-pk-60',
                            'fare_detail_key' => 'fare-detail-pk-60',
                            'offer_keys' => [],
                        ],
                    ],
                ],
            ],
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'pax@example.com',
            'phone' => '3001234567',
            'phone_country_code' => '92',
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'date_of_birth' => '1990-01-01',
            'passport_number' => 'AB1234567',
            'passport_expiry_date' => '2030-01-01',
            'nationality' => 'PK',
            'passenger_type' => 'adult',
        ]);

        return $booking;
    }
}
