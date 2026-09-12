<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingFareBreakdown;
use App\Support\Bookings\BookingPaymentEligibility;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingPaymentEligibilityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function validated_branded_booking_with_authoritative_payable_is_eligible(): void
    {
        $booking = $this->bookingWithMeta([
            'offer_validation_status' => 'valid',
            'offer_validated_at' => now()->toIso8601String(),
            'selected_fare_family_option' => [
                'name' => 'FREEDOM',
                'displayed_price' => 73241,
                'displayed_currency' => 'PKR',
                'authoritative_after_revalidation' => true,
                'price_is_approximate' => false,
            ],
        ]);

        $evaluation = BookingPaymentEligibility::evaluate($booking);

        $this->assertTrue($evaluation['eligible']);
        $this->assertSame('Validated', $evaluation['validation_label']);
        $this->assertStringContainsString('73,241', $evaluation['final_payable_status']);
        $this->assertSame('Pending', $evaluation['payment_state_label']);
    }

    #[Test]
    public function pending_validation_denies_payment(): void
    {
        $booking = $this->bookingWithMeta([
            'normalized_offer_snapshot' => ['segments' => []],
            'selected_fare_family_option' => [
                'name' => 'FREEDOM',
                'displayed_price' => 90062,
                'displayed_currency' => 'PKR',
                'price_is_approximate' => true,
            ],
        ]);

        $evaluation = BookingPaymentEligibility::evaluate($booking);

        $this->assertFalse($evaluation['eligible']);
        $this->assertContains('offer_validation_pending', $evaluation['reasons']);
        $this->assertSame('Awaiting fare validation', $evaluation['final_payable_status']);
    }

    #[Test]
    public function failed_validation_denies_payment(): void
    {
        $booking = $this->bookingWithMeta([
            'offer_validation_status' => 'failed',
            'selected_fare_family_option' => ['name' => 'FREEDOM'],
        ]);

        $this->assertFalse(BookingPaymentEligibility::allowsPayment($booking));
        $this->assertSame(
            'Fare validation failed; payment is not available.',
            BookingPaymentEligibility::denialMessage($booking),
        );
    }

    #[Test]
    public function cancelled_and_paid_bookings_are_denied(): void
    {
        $cancelled = $this->bookingWithMeta([], BookingStatus::Cancelled);
        $paid = $this->bookingWithMeta([
            'offer_validation_status' => 'valid',
            'offer_validated_at' => now()->toIso8601String(),
        ], BookingStatus::Paid, 'paid');

        $this->assertFalse(BookingPaymentEligibility::allowsPayment($cancelled));
        $this->assertFalse(BookingPaymentEligibility::allowsPayment($paid));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function bookingWithMeta(array $meta, BookingStatus $status = BookingStatus::Pending, string $paymentStatus = 'unpaid'): Booking
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'booking_reference' => 'PAYELIG-'.strtoupper(substr(uniqid(), -6)),
            'status' => $status,
            'payment_status' => $paymentStatus,
            'currency' => 'PKR',
            'meta' => $meta,
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 65000,
            'taxes' => 8241,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 73241,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        return $booking->fresh(['fareBreakdown']);
    }
}
