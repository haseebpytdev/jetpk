<?php

namespace Tests\Unit\Dashboard;

use App\Models\Booking;
use App\Support\Dashboard\DashboardMoneyPresenter;
use Tests\TestCase;

class DashboardMoneyPresenterTest extends TestCase
{
    public function test_resolve_booking_currency_prefers_supplier_and_fare_provenance(): void
    {
        $booking = new Booking([
            'currency' => 'usd',
            'meta' => ['offer_currency' => 'EUR', 'original_currency' => 'GBP'],
        ]);
        $booking->setRelation('fareBreakdown', (object) ['currency' => 'CHF']);

        $this->assertSame('GBP', DashboardMoneyPresenter::resolveBookingCurrency($booking));
    }

    public function test_resolve_booking_currency_prefers_fare_over_booking_default_pkr(): void
    {
        $booking = new Booking(['currency' => 'PKR']);
        $booking->setRelation('fareBreakdown', (object) ['currency' => 'USD']);

        $resolved = DashboardMoneyPresenter::resolveBookingCurrencyWithSource($booking);

        $this->assertSame('USD', $resolved['currency']);
        $this->assertSame('fareBreakdown.currency', $resolved['source']);
    }

    public function test_present_booking_total_flags_currency_conflict_for_review(): void
    {
        $booking = new Booking(['currency' => 'PKR']);
        $booking->setRelation('fareBreakdown', (object) ['currency' => 'USD', 'total' => 624]);

        $presented = DashboardMoneyPresenter::presentBookingTotal($booking, 624);

        $this->assertSame('USD', $presented['currency']);
        $this->assertTrue($presented['needsReview']);
    }

    public function test_resolve_booking_currency_returns_empty_when_unknown(): void
    {
        $booking = new Booking();
        $booking->setRelation('fareBreakdown', null);

        $this->assertSame('', DashboardMoneyPresenter::resolveBookingCurrency($booking));
    }

    public function test_present_minor_units_without_currency_never_returns_bare_amount(): void
    {
        $presented = DashboardMoneyPresenter::presentMinorUnits(564, null);

        $this->assertSame('unresolved', $presented['currencyStatus']);
        $this->assertNull($presented['currency']);
        $this->assertSame('Amount unavailable', $presented['displayLabel']);
        $this->assertSame('Currency not recorded', $presented['currencyLabel']);
        $this->assertStringNotContainsString('564', $presented['displayLabel']);
        $this->assertTrue($presented['needsReview']);
    }

    public function test_present_minor_units_with_pkr_currency(): void
    {
        $presented = DashboardMoneyPresenter::presentMinorUnits(564, 'PKR', 'booking.currency');

        $this->assertSame('resolved', $presented['currencyStatus']);
        $this->assertSame('PKR', $presented['currency']);
        $this->assertSame('Rs. 564.00', $presented['displayLabel']);
    }

    public function test_present_minor_units_with_usd_currency(): void
    {
        $presented = DashboardMoneyPresenter::presentMinorUnits(590, 'USD', 'fareBreakdown.currency');

        $this->assertSame('USD 590.00', $presented['displayLabel']);
    }

    public function test_format_display_label_pkr_owner_form(): void
    {
        $this->assertSame('Rs. 25,500.00', DashboardMoneyPresenter::formatDisplayLabel(25500, 'PKR'));
        $this->assertSame('USD 590.00', DashboardMoneyPresenter::formatDisplayLabel(590, 'USD'));
    }

    public function test_format_amount_label_without_currency_is_unavailable(): void
    {
        $this->assertSame('Amount unavailable', DashboardMoneyPresenter::formatAmountLabel(564, ''));
        $this->assertStringNotContainsString('564', DashboardMoneyPresenter::formatAmountLabel(564, ''));
    }
}
