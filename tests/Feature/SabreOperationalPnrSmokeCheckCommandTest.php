<?php

namespace Tests\Feature;

use App\Console\Commands\SabreOperationalPnrSmokeCheckCommand;
use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SabreOperationalPnrSmokeCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_smoke_check_passes_for_operational_success_booking(): void
    {
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => true,
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true,
        ]);

        $booking = $this->operationalSuccessBooking();

        $exit = Artisan::call('sabre:operational-pnr-smoke-check', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $payload = json_decode(Artisan::output(), true);
        $this->assertIsArray($payload);
        $this->assertTrue($payload['smoke_check_passed']);
        $this->assertSame('TQMNEV', $payload['booking']['pnr']);
        $this->assertSame('synced', $payload['sync']['status']);
        $this->assertSame('success', $payload['attempts']['create_pnr']['status']);
        $this->assertSame('success', $payload['attempts']['pnr_retrieve']['status']);
        $this->assertCount(2, $payload['snapshot_segments']);
        $this->assertFalse($payload['booking']['is_ticketed']);
    }

    public function test_smoke_check_fails_when_sync_missing(): void
    {
        $booking = $this->operationalSuccessBooking([
            'meta' => array_merge($this->operationalMeta(), [
                'pnr_itinerary_sync' => ['status' => 'retrieve_failed'],
            ]),
        ]);

        Artisan::call('sabre:operational-pnr-smoke-check', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true);
        $this->assertFalse($payload['smoke_check_passed']);
    }

    public function test_production_requires_confirm_phrase(): void
    {
        config(['app.env' => 'production']);

        $booking = $this->operationalSuccessBooking();

        $exit = Artisan::call('sabre:operational-pnr-smoke-check', [
            '--booking' => (string) $booking->id,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString(
            SabreOperationalPnrSmokeCheckCommand::PRODUCTION_READONLY_CONFIRM_PHRASE,
            Artisan::output()
        );
    }

    public function test_production_runs_with_confirm_phrase(): void
    {
        config(['app.env' => 'production']);

        $booking = $this->operationalSuccessBooking();

        $exit = Artisan::call('sabre:operational-pnr-smoke-check', [
            '--booking' => (string) $booking->id,
            '--confirm' => SabreOperationalPnrSmokeCheckCommand::PRODUCTION_READONLY_CONFIRM_PHRASE,
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $payload = json_decode(Artisan::output(), true);
        $this->assertTrue($payload['smoke_check_passed']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function operationalSuccessBooking(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        $booking = Booking::factory()->create(array_merge([
            'agency_id' => $agency->id,
            'status' => BookingStatus::PaymentPending,
            'pnr' => 'TQMNEV',
            'supplier_reference' => 'TQMNEV',
            'supplier_booking_status' => 'pending_payment_or_ticketing',
            'payment_status' => 'unpaid',
            'ticketing_status' => 'pending',
            'meta' => $this->operationalMeta(),
        ], $overrides));

        SupplierBookingAttempt::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'success',
            'attempted_at' => now()->subMinute(),
            'completed_at' => now()->subMinute(),
        ]);

        SupplierBookingAttempt::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'pnr_retrieve',
            'status' => 'success',
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        return $booking->fresh(['supplierBookingAttempts', 'tickets']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function operationalMeta(): array
    {
        return [
            'supplier_provider' => 'sabre',
            'pnr_itinerary_sync' => [
                'status' => 'synced',
                'endpoint_path' => '/v1/trip/orders/getBooking',
                'synced_at' => now()->toIso8601String(),
                'is_ticketed' => false,
                'airline_locator_present' => true,
            ],
            'pnr_itinerary_snapshot' => [
                'origin' => 'LHE',
                'destination' => 'DXB',
                'segments' => [
                    [
                        'origin' => 'LHE',
                        'destination' => 'BAH',
                        'airline_code' => 'GF',
                        'flight_number' => '765',
                        'booking_class' => 'N',
                        'segment_status' => 'HK',
                    ],
                    [
                        'origin' => 'BAH',
                        'destination' => 'DXB',
                        'airline_code' => 'GF',
                        'flight_number' => '500',
                        'booking_class' => 'N',
                        'segment_status' => 'HK',
                    ],
                ],
            ],
        ];
    }
}
