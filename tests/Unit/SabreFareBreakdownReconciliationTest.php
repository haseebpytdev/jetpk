<?php

namespace Tests\Unit;

use App\Models\Agency;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Pricing\PricingRuleService;
use App\Services\Suppliers\Sabre\SabreFlightSearchNormalizer;
use App\Support\FlightSearch\SabreFareVerificationDigest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class SabreFareBreakdownReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mismatched_raw_base_reconciles_to_total_minus_taxes(): void
    {
        $break = $this->extractFareBreakdown([
            'totalFare' => [
                'currency' => 'PKR',
                'totalPrice' => 22508,
                'baseFareAmount' => 45,
                'totalTaxAmount' => 9938,
            ],
        ]);

        $this->assertSame(45.0, $break['base_fare']);
        $this->assertSame(12570.0, $break['display_base_fare']);
        $this->assertSame(9938.0, $break['display_taxes']);
        $this->assertSame(22508.0, $break['supplier_total']);
        $this->assertTrue($break['breakdown_reconciled']);
        $this->assertSame('total_minus_taxes', $break['base_fare_display_source']);
        $this->assertSame(45.0, $break['raw_base_fare']);
    }

    public function test_display_components_sum_to_supplier_total(): void
    {
        $break = $this->extractFareBreakdown([
            'totalFare' => [
                'currency' => 'PKR',
                'totalPrice' => 22508,
                'baseFareAmount' => 45,
                'totalTaxAmount' => 9938,
            ],
        ]);

        $this->assertEqualsWithDelta(
            $break['supplier_total'],
            $break['display_base_fare'] + $break['display_taxes'],
            0.01
        );
    }

    public function test_equiv_fare_used_when_it_reconciles_with_total_and_tax(): void
    {
        $break = $this->extractFareBreakdown([
            'totalFare' => [
                'currency' => 'PKR',
                'totalPrice' => 10000,
                'baseFareAmount' => 45,
                'totalTaxAmount' => 2000,
                'equivFareAmount' => 8000,
            ],
        ]);

        $this->assertSame(8000.0, $break['display_base_fare']);
        $this->assertSame('equiv_fare_amount', $break['base_fare_display_source']);
        $this->assertTrue($break['breakdown_reconciled']);
    }

    public function test_missing_tax_does_not_invent_display_base(): void
    {
        $break = $this->extractFareBreakdown([
            'totalFare' => [
                'currency' => 'PKR',
                'totalPrice' => 22508,
                'baseFareAmount' => 45,
            ],
        ]);

        $this->assertFalse($break['breakdown_reconciled']);
        $this->assertSame('supplier_raw', $break['base_fare_display_source']);
        $this->assertSame(45.0, $break['display_base_fare']);
    }

    public function test_display_offer_uses_reconciled_base_without_changing_pricing_markup(): void
    {
        $offer = [
            'offer_id' => 'test-offer',
            'supplier_provider' => 'sabre',
            'airline_code' => 'PK',
            'departure_at' => '2026-05-30T11:00:00',
            'arrival_at' => '2026-05-30T13:00:00',
            'duration_minutes' => 120,
            'fare_breakdown' => [
                'base_fare' => 45.0,
                'taxes' => 9938.0,
                'supplier_total' => 22508.0,
                'currency' => 'PKR',
                'display_base_fare' => 12570.0,
                'display_taxes' => 9938.0,
                'breakdown_reconciled' => true,
                'base_fare_display_source' => 'total_minus_taxes',
            ],
        ];

        $pricing = [
            'base_fare' => 45.0,
            'taxes' => 9938.0,
            'supplier_total' => 22508.0,
            'supplier_total_source' => 22508.0,
            'admin_markup' => 1.58,
            'route_markup' => 0.0,
            'airline_markup' => 0.0,
            'agent_markup_or_commission' => 0.0,
            'service_fee' => 2499.0,
            'final_total' => 25008.58,
            'pricing_currency' => 'PKR',
            'supplier_currency' => 'PKR',
            'conversion_status' => 'same_currency',
        ];

        $service = app(FlightSearchService::class);
        $method = new ReflectionMethod(FlightSearchService::class, 'toDisplayOffer');
        $display = $method->invoke($service, $offer, $pricing);

        $this->assertSame(12570.0, $display['base_fare']);
        $this->assertSame(9938.0, $display['taxes']);
        $this->assertSame(25008.58, $display['final_customer_price']);
        $this->assertEqualsWithDelta(
            25008.58,
            $display['base_fare'] + $display['taxes'] + $display['markup'] + $display['service_fee'],
            0.05
        );

        $digest = SabreFareVerificationDigest::buildFromDisplayOffer($display);
        $this->assertTrue($digest['breakdown_reconciled']);
        $this->assertSame('total_minus_taxes', $digest['base_display_source']);
        $this->assertTrue($digest['breakdown_sum_matches_supplier_total']);
        $this->assertTrue($digest['breakdown_sum_matches_total']);
    }

    public function test_pricing_rule_service_applies_no_default_markup_without_active_rules(): void
    {
        $agency = Agency::factory()->create();
        $priced = app(PricingRuleService::class)->calculateMarkup($agency, [
            'base_fare' => 45.0,
            'taxes' => 9938.0,
            'supplier_total' => 22508.0,
            'currency' => 'PKR',
        ], [
            'route' => 'LHE-KHI',
            'origin' => 'LHE',
            'destination' => 'KHI',
            'airline' => 'pk',
            'supplier' => 'sabre',
        ]);

        $markup = (float) ($priced['admin_markup'] ?? 0)
            + (float) ($priced['route_markup'] ?? 0)
            + (float) ($priced['airline_markup'] ?? 0)
            + (float) ($priced['agent_markup_or_commission'] ?? 0);

        $this->assertSame(0.0, $markup);
        $this->assertSame(0.0, (float) ($priced['service_fee'] ?? 0));
        $this->assertEqualsWithDelta(22508.0, (float) ($priced['final_total'] ?? 0), 0.05);
    }

    /**
     * @param  array<string, mixed>  $fareNode
     * @return array<string, mixed>
     */
    protected function extractFareBreakdown(array $fareNode): array
    {
        $normalizer = app(SabreFlightSearchNormalizer::class);
        $method = new ReflectionMethod(SabreFlightSearchNormalizer::class, 'extractFareBreakdownFromFare');

        return $method->invoke($normalizer, $fareNode);
    }
}
