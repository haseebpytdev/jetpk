<?php

namespace Tests\Feature;

use App\Console\Commands\SabreControlledPnrReadinessCommand;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SabreControlledPnrReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_command_without_booking_option_fails(): void
    {
        $exit = Artisan::call('sabre:controlled-pnr-readiness');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Pass --booking', Artisan::output());
    }

    public function test_command_outputs_read_only_flags(): void
    {
        $booking = $this->sabreBooking();

        $exit = Artisan::call('sabre:controlled-pnr-readiness', [
            '--booking' => (string) $booking->id,
        ]);

        $output = Artisan::output();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        $this->assertStringContainsString('is_sabre_booking=true', $output);
        $this->assertStringNotContainsString('passport', strtolower($output));
        $this->assertStringNotContainsString('access_token', strtolower($output));
    }

    public function test_command_json_mode_returns_normalized_result(): void
    {
        $booking = $this->sabreBooking();

        Artisan::call('sabre:controlled-pnr-readiness', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('eligible', $payload);
        $this->assertArrayHasKey('blockers', $payload);
        $this->assertFalse($payload['live_supplier_call_attempted']);
    }

    public function test_production_requires_confirm_phrase(): void
    {
        config(['app.env' => 'production']);
        $booking = $this->sabreBooking();

        $exit = Artisan::call('sabre:controlled-pnr-readiness', [
            '--booking' => (string) $booking->id,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString(SabreControlledPnrReadinessCommand::PRODUCTION_READONLY_CONFIRM_PHRASE, Artisan::output());
    }

    protected function sabreBooking(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => $conn->id,
                'normalized_offer_snapshot' => [
                    'validating_carrier' => 'GF',
                    'segments' => [
                        ['origin' => 'LHE', 'destination' => 'DXB', 'carrier' => 'GF', 'booking_class' => 'Y'],
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

        return $booking->fresh(['passengers', 'contact']);
    }
}
