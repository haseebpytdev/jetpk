<?php

namespace Tests\Unit\Support\Bookings;

use App\Support\Bookings\CheckoutFareBreakdownPresenter;
use Tests\TestCase;

class ResultsPassengerPricingTrustTest extends TestCase
{
    public function test_passenger_pricing_not_trusted_when_converted(): void
    {
        $trusted = CheckoutFareBreakdownPresenter::passengerPricingTrustedForResultsRow([
            'passenger_pricing_available' => true,
            'passenger_pricing' => [
                ['passenger_type' => 'adult', 'base_amount' => 167, 'currency' => 'USD'],
            ],
            'pricing_currency' => 'PKR',
            'conversion_status' => 'converted',
        ]);

        $this->assertFalse($trusted);
    }

    public function test_passenger_pricing_trusted_for_native_pkr_rows(): void
    {
        $trusted = CheckoutFareBreakdownPresenter::passengerPricingTrustedForResultsRow([
            'passenger_pricing_available' => true,
            'passenger_pricing' => [
                ['passenger_type' => 'adult', 'base_amount' => 50000, 'currency' => 'PKR'],
            ],
            'pricing_currency' => 'PKR',
            'conversion_status' => 'same_currency',
        ]);

        $this->assertTrue($trusted);
    }
}
