<?php

namespace Tests\Feature\Console;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingFareBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IatiPricingAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_audit_command_reports_double_conversion_for_inflated_booking(): void
    {
        $booking = $this->inflatedBooking();

        $this->artisan('ota:iati-pricing-audit', ['--booking-id' => $booking->id])
            ->assertExitCode(1)
            ->expectsOutputToContain('selected_display_total=119090')
            ->expectsOutputToContain('detected_double_conversion=true')
            ->expectsOutputToContain('expected_total_pkr=119090')
            ->expectsOutputToContain('safe_repair_available=true')
            ->expectsOutputToContain('repair_blockers=[]');
    }

    #[Test]
    public function test_audit_command_detects_booking_59_style_without_passenger_pricing_total(): void
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Iati->value,
            'payment_status' => 'unpaid',
            'selected_fare_total' => 33109533.63,
            'revalidated_fare_total' => 33109533.63,
            'meta' => [
                'supplier_provider' => SupplierProvider::Iati->value,
                'pricing_snapshot' => [
                    'supplier_total_source' => 119090.0,
                    'supplier_currency' => 'USD',
                    'pricing_currency' => 'PKR',
                    'conversion_status' => 'converted',
                    'fx_rate' => 278.021107,
                    'final_total' => 33109533.63,
                ],
            ],
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'total' => 33109533.63,
            'currency' => 'USD',
        ]);

        $this->artisan('ota:iati-pricing-audit', ['--booking-id' => $booking->id])
            ->assertExitCode(1)
            ->expectsOutputToContain('passenger_pricing_total=')
            ->expectsOutputToContain('detected_double_conversion=true')
            ->expectsOutputToContain('safe_repair_available=true');
    }

    #[Test]
    public function test_repair_command_dry_run_reports_planned_changes(): void
    {
        $booking = $this->inflatedBooking();

        $this->artisan('ota:iati-repair-pricing', [
            '--booking-id' => $booking->id,
            '--dry-run' => true,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('planned_changes=')
            ->expectsOutputToContain('Dry run only');
    }

    protected function inflatedBooking(): Booking
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Iati->value,
            'payment_status' => 'unpaid',
            'selected_fare_total' => 33109533.63,
            'revalidated_fare_total' => 33109533.63,
            'meta' => [
                'supplier_provider' => SupplierProvider::Iati->value,
                'passenger_pricing' => [
                    ['type' => 'adult', 'quantity' => 1, 'total' => 119090.0, 'base' => 101290.0, 'tax' => 17300.0, 'currency' => 'PKR'],
                ],
                'pricing_snapshot' => [
                    'supplier_total_source' => 119090.0,
                    'supplier_currency' => 'USD',
                    'pricing_currency' => 'PKR',
                    'conversion_status' => 'converted',
                    'fx_rate' => 278.021107,
                    'final_total' => 33109533.63,
                ],
            ],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 28160757.93,
            'taxes' => 4809765.15,
            'total' => 33109533.63,
            'currency' => 'USD',
        ]);

        return $booking;
    }
}
