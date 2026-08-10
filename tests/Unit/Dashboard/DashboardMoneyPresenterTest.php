<?php

namespace Tests\Unit\Dashboard;

use App\Models\Booking;
use App\Support\Dashboard\DashboardMoneyPresenter;
use Tests\TestCase;

class DashboardMoneyPresenterTest extends TestCase
{
    public function test_resolve_booking_currency_prefers_booking_and_fare_breakdown(): void
    {
        $booking = new Booking([
            'currency' => 'usd',
            'meta' => ['offer_currency' => 'EUR'],
        ]);
        $booking->setRelation('fareBreakdown', (object) ['currency' => 'GBP']);

        $this->assertSame('USD', DashboardMoneyPresenter::resolveBookingCurrency($booking));
    }

    public function test_resolve_booking_currency_returns_empty_when_unknown(): void
    {
        $booking = new Booking();
        $booking->setRelation('fareBreakdown', null);

        $this->assertSame('', DashboardMoneyPresenter::resolveBookingCurrency($booking));
    }

    public function test_format_amount_label_without_currency(): void
    {
        $this->assertSame('564.00', DashboardMoneyPresenter::formatAmountLabel(564, ''));
        $this->assertSame('564.00 USD', DashboardMoneyPresenter::formatAmountLabel(564, 'USD'));
    }
}
