<?php

namespace Tests\Unit\FlightSearch;

use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use PHPUnit\Framework\TestCase;

class MarkSelectedFareAuthoritativeTest extends TestCase
{
    public function test_clears_approximate_flag_and_pins_total(): void
    {
        $intent = [
            'option_key' => 'ycomfort',
            'name' => 'Economy Comfort',
            'displayed_price' => 90000,
            'displayed_currency' => 'PKR',
            'price_display' => 'PKR 90,000',
            'price_is_approximate' => true,
            'is_price_approximate' => true,
        ];

        $marked = FlightOfferDisplayPresenter::markSelectedFareAuthoritative($intent, 100221, 'PKR');

        $this->assertFalse($marked['price_is_approximate']);
        $this->assertFalse($marked['is_price_approximate']);
        $this->assertTrue($marked['authoritative_after_revalidation']);
        $this->assertSame(100221, $marked['displayed_price']);
        $this->assertSame('PKR 100,221', $marked['price_display']);

        $estimate = FlightOfferDisplayPresenter::buildCheckoutSelectedFareEstimatePresentation($marked);
        $this->assertNotNull($estimate);
        $this->assertFalse((bool) ($estimate['price_is_approximate'] ?? true));
        $this->assertFalse((bool) ($estimate['price_needs_refresh'] ?? true));
    }
}
