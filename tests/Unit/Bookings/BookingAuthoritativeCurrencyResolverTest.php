<?php

namespace Tests\Unit\Bookings;

use App\Models\Booking;
use App\Support\Bookings\BookingAuthoritativeCurrencyResolver;
use Tests\TestCase;

class BookingAuthoritativeCurrencyResolverTest extends TestCase
{
    public function test_prefers_fare_currency_over_booking_default_pkr(): void
    {
        $booking = new Booking(['currency' => 'PKR']);
        $booking->setRelation('fareBreakdown', (object) ['currency' => 'USD']);

        $resolved = BookingAuthoritativeCurrencyResolver::resolveWithSource($booking);

        $this->assertSame('USD', $resolved['currency']);
        $this->assertSame('fareBreakdown.currency', $resolved['source']);
    }

    public function test_resolve_payment_default_uses_fare_when_booking_currency_is_stale(): void
    {
        $booking = new Booking(['currency' => 'PKR']);
        $booking->setRelation('fareBreakdown', (object) ['currency' => 'USD']);

        $this->assertSame('USD', BookingAuthoritativeCurrencyResolver::resolvePaymentDefault($booking));
    }

    public function test_resolve_payment_default_honors_explicit_currency(): void
    {
        $booking = new Booking(['currency' => 'PKR']);

        $this->assertSame('EUR', BookingAuthoritativeCurrencyResolver::resolvePaymentDefault($booking, 'eur'));
    }
}
