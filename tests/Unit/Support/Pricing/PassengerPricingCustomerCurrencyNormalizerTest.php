<?php

namespace Tests\Unit\Support\Pricing;

use App\Support\Pricing\PassengerPricingCustomerCurrencyNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PassengerPricingCustomerCurrencyNormalizerTest extends TestCase
{
    #[Test]
    public function test_converts_usd_ptc_rows_with_fx_and_preserves_quantities(): void
    {
        $pack = PassengerPricingCustomerCurrencyNormalizer::normalize(
            [
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
            ],
            [
                'pricing_currency' => 'PKR',
                'conversion_status' => 'converted',
                'fx_rate' => 280.0,
                'supplier_total' => 134400.0, // (250+180+50)*280
            ],
            134400.0,
        );

        $this->assertTrue($pack['passenger_pricing_available']);
        $this->assertTrue($pack['components_trusted']);
        $this->assertCount(3, $pack['passenger_pricing']);
        $this->assertSame(2, $pack['passenger_pricing'][0]['passenger_count']);
        $this->assertSame(3, $pack['passenger_pricing'][1]['passenger_count']);
        $this->assertSame(1, $pack['passenger_pricing'][2]['passenger_count']);
        $this->assertSame('PKR', $pack['passenger_pricing'][0]['currency']);
        $this->assertSame(70000.0, (float) $pack['passenger_pricing'][0]['total_amount']);
        $this->assertSame(50400.0, (float) $pack['passenger_pricing'][1]['total_amount']);
        $this->assertSame(14000.0, (float) $pack['passenger_pricing'][2]['total_amount']);
    }

    #[Test]
    public function test_rejects_invented_split_when_rows_do_not_reconcile(): void
    {
        $pack = PassengerPricingCustomerCurrencyNormalizer::normalize(
            [
                [
                    'passenger_type' => 'adult',
                    'passenger_count' => 1,
                    'total_amount' => 100.0,
                    'currency' => 'USD',
                ],
            ],
            [
                'pricing_currency' => 'PKR',
                'conversion_status' => 'converted',
                'fx_rate' => 280.0,
                'supplier_total' => 999999.0,
            ],
            999999.0,
        );

        $this->assertFalse($pack['passenger_pricing_available']);
        $this->assertNull($pack['passenger_pricing']);
    }

    #[Test]
    public function test_native_pkr_rows_pass_through(): void
    {
        $pack = PassengerPricingCustomerCurrencyNormalizer::normalize(
            [
                [
                    'passenger_type' => 'adult',
                    'passenger_count' => 2,
                    'base_amount' => 80000.0,
                    'tax_amount' => 10000.0,
                    'total_amount' => 90000.0,
                    'currency' => 'PKR',
                ],
            ],
            [
                'pricing_currency' => 'PKR',
                'conversion_status' => 'same_currency',
                'fx_rate' => 1.0,
                'supplier_total' => 90000.0,
            ],
            90000.0,
        );

        $this->assertTrue($pack['passenger_pricing_available']);
        $this->assertSame(90000.0, (float) $pack['passenger_pricing'][0]['total_amount']);
    }
}
