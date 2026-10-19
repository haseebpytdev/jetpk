<?php

namespace Tests\Unit\Support\Booking;

use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingFareBreakdown;
use App\Support\Booking\StandardBookingJsonPresenter;
use App\Support\Payments\BookingPayableResolver;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class StandardBookingJsonPresenterPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_promo_payable_equals_fare_total(): void
    {
        $booking = $this->bookingWithFare(10000.00);
        $pricing = $this->presentPricing($booking, ['currency' => 'PKR', 'total' => 10000, 'rows' => []]);

        $this->assertSame(10000.0, $pricing['original_fare']);
        $this->assertSame(0.0, $pricing['promo_discount']);
        $this->assertSame(10000.0, $pricing['customer_payable']);
        $this->assertSame(10000.0, $pricing['total']);
        $this->assertSame(10000.0, BookingPayableResolver::customerPayableTotal($booking));
    }

    public function test_fixed_promo_total_uses_payable_after_promo(): void
    {
        $booking = $this->bookingWithFare(10000.00);
        $booking->forceFill([
            'promo_code' => 'SAVE10',
            'promo_discount_amount' => 1000,
            'payable_before_promo' => 10000,
            'payable_after_promo' => 9000,
        ])->save();
        $booking = $booking->fresh(['fareBreakdown']);

        $pricing = $this->presentPricing($booking, ['currency' => 'PKR', 'total' => 10000, 'rows' => []]);

        $this->assertSame(10000.0, $pricing['original_fare']);
        $this->assertSame(1000.0, $pricing['promo_discount']);
        $this->assertSame(9000.0, $pricing['customer_payable']);
        $this->assertSame(9000.0, $pricing['total']);
        $this->assertStringContainsString('9,000', $pricing['formatted_total']);
    }

    public function test_percentage_promo_two_decimal_rounding(): void
    {
        $booking = $this->bookingWithFare(9999.99);
        $booking->forceFill([
            'promo_code' => 'PCT10',
            'promo_discount_amount' => 1000.00,
            'payable_before_promo' => 9999.99,
            'payable_after_promo' => 8999.99,
        ])->save();
        $booking = $booking->fresh(['fareBreakdown']);

        $pricing = $this->presentPricing($booking, ['currency' => 'PKR', 'total' => 9999.99, 'rows' => []]);

        $this->assertEqualsWithDelta(8999.99, $pricing['total'], 0.001);
        $this->assertEqualsWithDelta(8999.99, $pricing['customer_payable'], 0.001);
        $this->assertSame('PKR', $pricing['currency']);
    }

    public function test_partially_paid_exposes_balance_due(): void
    {
        $booking = $this->bookingWithFare(10000.00);
        $booking->forceFill([
            'payable_after_promo' => 10000,
            'balance_due' => 4000,
        ])->save();
        $booking = $booking->fresh(['fareBreakdown']);

        $pricing = $this->presentPricing($booking, ['currency' => 'PKR', 'total' => 10000, 'rows' => []]);

        $this->assertSame(10000.0, $pricing['total']);
        $this->assertSame(4000.0, $pricing['balance_due']);
    }

    private function bookingWithFare(float $total): Booking
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'currency' => 'PKR',
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => round($total * 0.8, 2),
            'taxes' => round($total * 0.15, 2),
            'fees' => round($total * 0.05, 2),
            'total' => $total,
            'currency' => 'PKR',
        ]);

        return $booking->fresh(['fareBreakdown']);
    }

    /**
     * @param  array<string, mixed>  $checkoutFareBreakdown
     * @return array<string, mixed>
     */
    private function presentPricing(Booking $booking, array $checkoutFareBreakdown): array
    {
        $presenter = app(StandardBookingJsonPresenter::class);
        $method = new ReflectionMethod($presenter, 'presentAuthoritativePricing');
        $method->setAccessible(true);

        /** @var array<string, mixed> $pricing */
        $pricing = $method->invoke($presenter, $checkoutFareBreakdown, $booking);

        return $pricing;
    }
}
