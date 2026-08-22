<?php

namespace Tests\Feature\FlightSearch;

use App\Support\FlightSearch\FlightOfferFallbackDetailsPresenter;
use App\Support\Pricing\PassengerPricingCustomerCurrencyNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Wave7AuthoritativePassengerFareBreakdownTest extends TestCase
{
    #[Test]
    public function test_fallback_fare_details_exposes_adult_child_infant_quantities_after_fx(): void
    {
        $rows = [
            [
                'passenger_type' => 'adult',
                'passenger_count' => 2,
                'base_amount' => 200.0,
                'tax_amount' => 50.0,
                'total_amount' => 250.0,
                'currency' => 'USD',
            ],
            [
                'passenger_type' => 'child',
                'passenger_count' => 3,
                'base_amount' => 150.0,
                'tax_amount' => 30.0,
                'total_amount' => 180.0,
                'currency' => 'USD',
            ],
            [
                'passenger_type' => 'infant',
                'passenger_count' => 1,
                'base_amount' => 40.0,
                'tax_amount' => 10.0,
                'total_amount' => 50.0,
                'currency' => 'USD',
            ],
        ];

        $pricing = [
            'base_fare' => 109200.0,
            'taxes' => 25200.0,
            'supplier_total' => 134400.0,
            'pricing_currency' => 'PKR',
            'conversion_status' => 'converted',
            'fx_rate' => 280.0,
            'final_total' => 134400.0,
            'service_fee' => 0.0,
            'admin_markup' => 0.0,
        ];

        $pack = PassengerPricingCustomerCurrencyNormalizer::normalize($rows, $pricing, 134400.0);
        $this->assertTrue($pack['passenger_pricing_available']);

        $offer = [
            'displayed_price' => 134400,
            'final_customer_price' => 134400.0,
            'currency' => 'PKR',
            'pricing_currency' => 'PKR',
            'conversion_status' => 'converted',
            'supplier_currency' => 'USD',
            'pricing_components' => $pricing,
            'fare_breakdown' => [
                'currency' => 'USD',
                'base_fare' => 390.0,
                'taxes' => 90.0,
                'supplier_total' => 480.0,
                'passenger_pricing' => $rows,
                'passenger_pricing_available' => true,
            ],
        ];

        $fallback = FlightOfferFallbackDetailsPresenter::buildForOffer($offer, []);
        $breakdown = $fallback['fallback_details']['fare_breakdown'] ?? [];
        $this->assertIsArray($breakdown['passenger_pricing'] ?? null);
        $this->assertCount(3, $breakdown['passenger_pricing']);

        $byType = [];
        foreach ($breakdown['passenger_pricing'] as $row) {
            $byType[strtoupper((string) $row['passenger_type'])] = $row;
        }
        $this->assertSame(2, (int) $byType['ADULT']['passenger_count']);
        $this->assertSame(3, (int) $byType['CHILD']['passenger_count']);
        $this->assertSame(1, (int) $byType['INFANT']['passenger_count']);
        $this->assertSame('PKR', $byType['ADULT']['currency']);
        $sum = (float) $byType['ADULT']['total_amount']
            + (float) $byType['CHILD']['total_amount']
            + (float) $byType['INFANT']['total_amount'];
        $this->assertEqualsWithDelta(134400.0, $sum, 2.0);
    }
}
