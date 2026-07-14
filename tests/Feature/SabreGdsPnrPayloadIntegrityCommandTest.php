<?php

namespace Tests\Feature;

use App\Console\Commands\SabreInspectGdsPnrPayloadIntegrityCommand;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SabreGdsPnrPayloadIntegrityCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');

        parent::tearDown();
    }

    public function test_local_runs_without_confirm_and_reports_readonly_safety_lines(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = $this->makeMismatchBooking($agency);

        $this->artisan('sabre:inspect-gds-pnr-payload-integrity', ['--booking' => (string) $booking->id])
            ->expectsOutputToContain('production_readonly_confirmed=false')
            ->expectsOutputToContain('live_supplier_call_attempted=false')
            ->expectsOutputToContain('booking_status_updated=false')
            ->assertExitCode(0);
    }

    public function test_production_without_confirm_is_blocked(): void
    {
        Config::set('app.env', 'production');

        $this->artisan('sabre:inspect-gds-pnr-payload-integrity', ['--booking' => '1'])
            ->expectsOutputToContain(
                '--confirm='.SabreInspectGdsPnrPayloadIntegrityCommand::PRODUCTION_READONLY_CONFIRM_PHRASE
            )
            ->assertExitCode(1);
    }

    public function test_production_with_wrong_confirm_is_blocked(): void
    {
        Config::set('app.env', 'production');

        $this->artisan('sabre:inspect-gds-pnr-payload-integrity', [
            '--booking' => '1',
            '--confirm' => 'WRONG-PHRASE',
        ])
            ->expectsOutputToContain('Invalid --confirm phrase')
            ->assertExitCode(1);
    }

    public function test_production_with_readonly_confirm_runs_and_reports_safety_lines(): void
    {
        Config::set('app.env', 'production');
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = $this->makeMismatchBooking($agency);

        $this->artisan('sabre:inspect-gds-pnr-payload-integrity', [
            '--booking' => (string) $booking->id,
            '--confirm' => SabreInspectGdsPnrPayloadIntegrityCommand::PRODUCTION_READONLY_CONFIRM_PHRASE,
        ])
            ->expectsOutputToContain('production_readonly_confirmed=true')
            ->expectsOutputToContain('live_supplier_call_attempted=false')
            ->expectsOutputToContain('booking_status_updated=false')
            ->expectsOutputToContain('selected_brand_context_consistent=false')
            ->assertExitCode(0);
    }

    public function test_inspect_command_reports_mismatch_for_fl_vs_lt_fare_basis(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = $this->makeMismatchBooking($agency);

        $this->artisan('sabre:inspect-gds-pnr-payload-integrity', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'READONLY-GDS-PNR-PAYLOAD-INTEGRITY',
        ])
            ->expectsOutputToContain('selected_brand_context_consistent=false')
            ->expectsOutputToContain('connection_sticky=true')
            ->assertExitCode(0);
    }

    public function test_inspect_command_reports_consistent_after_reconcile(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = $this->makeConsistentBooking($agency);

        $this->artisan('sabre:inspect-gds-pnr-payload-integrity', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'READONLY-GDS-PNR-PAYLOAD-INTEGRITY',
        ])
            ->expectsOutputToContain('selected_brand_context_consistent=true')
            ->assertExitCode(0);
    }

    protected function makeMismatchBooking(Agency $agency): Booking
    {
        $snapshot = [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 2,
            'validating_carrier' => 'PK',
            'distribution_channel' => 'gds',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'carrier' => 'PK',
                'flight_number' => '203',
                'departure_at' => '2026-08-15T08:00:00',
                'booking_class' => 'V',
                'fare_basis_code' => 'VOWNBAG',
            ]],
            'fare_breakdown' => ['supplier_total' => 78871, 'currency' => 'PKR'],
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => 2,
                'distribution_channel' => 'gds',
                'fare_option_key' => 'freedom-key',
                'selected_fare_family_option' => [
                    'option_key' => 'freedom-key',
                    'brand_code' => 'FL',
                    'name' => 'FREEDOM',
                    'fare_basis' => 'VOWFL',
                    'fare_basis_codes_by_segment' => ['VOWFL'],
                    'booking_classes_by_segment' => ['V'],
                    'baggage_summary' => '30kg',
                    'displayed_price' => 88602,
                ],
                'sabre_booking_context' => [
                    'selected_brand_code' => 'FL',
                    'brand_code' => 'FL',
                    'fare_basis_codes_by_segment' => ['VOWNBAG'],
                ],
                'normalized_offer_snapshot' => $snapshot,
            ],
            'selected_fare_total' => 88602,
            'revalidated_fare_total' => 78871,
        ]);

        $this->seedPassengers($booking);

        return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }

    protected function makeConsistentBooking(Agency $agency): Booking
    {
        $snapshot = [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 2,
            'validating_carrier' => 'PK',
            'distribution_channel' => 'gds',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'carrier' => 'PK',
                'flight_number' => '203',
                'departure_at' => '2026-08-15T08:00:00',
                'booking_class' => 'V',
                'fare_basis_code' => 'VOWFL',
            ]],
            'fare_breakdown' => ['supplier_total' => 88602, 'currency' => 'PKR'],
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => 2,
                'distribution_channel' => 'gds',
                'fare_option_key' => 'freedom-key',
                'selected_fare_family_option' => [
                    'option_key' => 'freedom-key',
                    'brand_code' => 'FL',
                    'name' => 'FREEDOM',
                    'fare_basis' => 'VOWFL',
                    'fare_basis_codes_by_segment' => ['VOWFL'],
                    'booking_classes_by_segment' => ['V'],
                    'baggage_summary' => '30kg',
                    'displayed_price' => 88602,
                ],
                'sabre_booking_context' => [
                    'selected_brand_code' => 'FL',
                    'brand_code' => 'FL',
                    'fare_basis_codes_by_segment' => ['VOWFL'],
                    'booking_classes_by_segment' => ['V'],
                    'baggage' => '30kg',
                ],
                'normalized_offer_snapshot' => $snapshot,
            ],
        ]);

        $this->seedPassengers($booking);

        return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }

    protected function seedPassengers(Booking $booking): void
    {
        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_type' => 'adult',
            'first_name' => 'Test',
            'last_name' => 'Traveler',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'booker@example.com',
            'phone' => '3001234567',
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'total' => 88602,
            'currency' => 'PKR',
        ]);
    }
}
