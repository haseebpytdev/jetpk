<?php

namespace Tests\Unit\Support\Pricing;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Services\Pricing\PricingRuleService;
use App\Support\Pricing\IatiFarePricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IatiFarePricingResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_pkr_passenger_pricing_overrides_usd_fare_currency(): void
    {
        $currency = IatiFarePricingResolver::resolveCurrency([
            'currency' => 'USD',
            'passenger_pricing' => [
                ['currency' => 'PKR', 'total' => 119090, 'base' => 101290, 'tax' => 17300, 'quantity' => 1],
            ],
        ]);

        $this->assertSame('PKR', $currency);
    }

    #[Test]
    public function test_booking_59_style_pricing_stores_pkr_total_not_inflated(): void
    {
        $agency = Agency::factory()->create();
        $fare = [
            'base_fare' => 101290.0,
            'taxes' => 17300.0,
            'supplier_total' => 119090.0,
            'currency' => 'USD',
            'passenger_pricing' => [
                ['type' => 'adult', 'quantity' => 1, 'total' => 119090.0, 'base' => 101290.0, 'tax' => 17300.0, 'currency' => 'PKR'],
            ],
        ];

        $pricing = app(PricingRuleService::class)->calculateMarkup(
            $agency,
            IatiFarePricingResolver::supplierFareFromBreakdown($fare),
            ['supplier' => SupplierProvider::Iati->value],
        );

        $this->assertSame('PKR', $pricing['supplier_currency']);
        $this->assertSame('PKR', $pricing['pricing_currency']);
        $this->assertSame('same_currency', $pricing['conversion_status']);
        $this->assertSame(1.0, (float) $pricing['fx_rate']);
        $this->assertEqualsWithDelta(119090.0, (float) $pricing['final_total'], 0.01);
        $this->assertEqualsWithDelta(101290.0, (float) $pricing['base_fare'], 0.01);
        $this->assertEqualsWithDelta(17300.0, (float) $pricing['taxes'], 0.01);
    }

    #[Test]
    public function test_persisted_detection_works_without_passenger_pricing_using_fx_ratio(): void
    {
        $detected = IatiFarePricingResolver::detectPersistedDoubleConversion(
            119090.0,
            33109533.63,
            [
                'supplier_total_source' => 119090.0,
                'supplier_currency' => 'USD',
                'pricing_currency' => 'PKR',
                'conversion_status' => 'converted',
                'fx_rate' => 278.021107,
                'base_fare' => 28160757.93,
                'taxes' => 4809765.15,
                'final_total' => 33109533.63,
            ],
            [
                'selected_fare_family_option' => [
                    'displayed_price' => 119090,
                    'displayed_currency' => 'PKR',
                ],
            ],
        );

        $this->assertNotNull($detected);
        $this->assertEqualsWithDelta(119090.0, $detected['expected_total_pkr'], 0.01);
        $this->assertEqualsWithDelta(101290.0, $detected['expected_base_pkr'], 1.0);
        $this->assertEqualsWithDelta(17300.0, $detected['expected_tax_pkr'], 1.0);
    }

    #[Test]
    public function test_double_conversion_guard_corrects_inflated_total(): void
    {
        $supplierFare = [
            'base_fare' => 101290.0,
            'taxes' => 17300.0,
            'supplier_total' => 119090.0,
            'currency' => 'USD',
            'passenger_pricing' => [
                ['currency' => 'PKR', 'total' => 119090.0, 'base' => 101290.0, 'tax' => 17300.0, 'quantity' => 1],
            ],
        ];
        $components = [
            'base_fare' => 28160757.93,
            'taxes' => 4809765.15,
            'supplier_total' => 33109533.63,
            'supplier_total_source' => 119090.0,
            'supplier_currency' => 'USD',
            'pricing_currency' => 'PKR',
            'conversion_status' => 'converted',
            'fx_rate' => 278.021107,
            'final_total' => 33109533.63,
        ];

        $detected = IatiFarePricingResolver::detectDoubleConversion($supplierFare, $components);
        $this->assertNotNull($detected);
        $this->assertTrue($detected['detected']);
        $this->assertEqualsWithDelta(119090.0, $detected['expected_total_pkr'], 0.01);

        $corrected = IatiFarePricingResolver::guardPricingComponents($supplierFare, $components);
        $this->assertSame('same_currency', $corrected['conversion_status']);
        $this->assertEqualsWithDelta(119090.0, (float) $corrected['final_total'], 0.01);
        $this->assertTrue($corrected['iati_double_conversion_corrected'] ?? false);
    }

    #[Test]
    public function test_true_usd_iati_fare_still_converts_when_passenger_currency_is_usd(): void
    {
        $agency = Agency::factory()->create();
        $fare = [
            'base_fare' => 280.0,
            'taxes' => 70.0,
            'supplier_total' => 350.0,
            'currency' => 'USD',
            'passenger_pricing' => [
                ['type' => 'adult', 'quantity' => 1, 'total' => 350.0, 'base' => 280.0, 'tax' => 70.0, 'currency' => 'USD'],
            ],
        ];

        $pricing = app(PricingRuleService::class)->calculateMarkup(
            $agency,
            IatiFarePricingResolver::supplierFareFromBreakdown($fare),
            ['supplier' => SupplierProvider::Iati->value],
        );

        $this->assertSame('USD', $pricing['supplier_currency']);
        $this->assertContains($pricing['conversion_status'], ['converted', 'conversion_missing']);
        if ($pricing['conversion_status'] === 'converted') {
            $this->assertGreaterThan(350.0, (float) $pricing['final_total']);
        }
    }

    #[Test]
    public function test_duffel_pricing_path_unchanged(): void
    {
        $agency = Agency::factory()->create();
        $pricing = app(PricingRuleService::class)->calculateMarkup($agency, [
            'base_fare' => 100.0,
            'taxes' => 20.0,
            'supplier_total' => 120.0,
            'currency' => 'USD',
        ], ['supplier' => SupplierProvider::Duffel->value]);

        $this->assertSame('USD', $pricing['supplier_currency']);
        $this->assertNotSame('same_currency', $pricing['conversion_status']);
    }
}
