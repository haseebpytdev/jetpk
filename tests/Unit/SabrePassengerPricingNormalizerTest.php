<?php

namespace Tests\Unit;

use App\Data\FlightSearchRequestData;
use App\Services\Suppliers\Sabre\SabreFlightSearchNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class SabrePassengerPricingNormalizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_extracts_adt_chd_inf_passenger_pricing_when_passenger_info_list_has_totals(): void
    {
        $fare = [
            'totalFare' => [
                'currency' => 'PKR',
                'totalPrice' => 150000,
                'baseFareAmount' => 120000,
                'totalTaxAmount' => 30000,
            ],
            'passengerInfoList' => [
                [
                    'passengerInfo' => [
                        'passengerType' => 'ADT',
                        'passengerNumber' => 1,
                        'passengerTotalFare' => [
                            'currency' => 'PKR',
                            'totalPrice' => 80000,
                            'baseFareAmount' => 65000,
                            'totalTaxAmount' => 15000,
                        ],
                    ],
                ],
                [
                    'passengerInfo' => [
                        'passengerTypeCode' => 'CNN',
                        'passengerNumber' => 1,
                        'passengerTotalFare' => [
                            'currency' => 'PKR',
                            'totalPrice' => 60000,
                            'baseFareAmount' => 48000,
                            'totalTaxAmount' => 12000,
                        ],
                    ],
                ],
                [
                    'passengerInfo' => [
                        'passengerType' => 'INF',
                        'passengerNumber' => 1,
                        'passengerTotalFare' => [
                            'currency' => 'PKR',
                            'totalPrice' => 10000,
                            'baseFareAmount' => 7000,
                            'totalTaxAmount' => 3000,
                        ],
                    ],
                ],
            ],
        ];

        $break = $this->extractFareBreakdown($fare);

        $this->assertTrue($break['passenger_pricing_available']);
        $this->assertIsArray($break['passenger_pricing']);
        $this->assertCount(3, $break['passenger_pricing']);
        $this->assertSame('adult', $break['passenger_pricing'][0]['passenger_type']);
        $this->assertSame('child', $break['passenger_pricing'][1]['passenger_type']);
        $this->assertSame('infant', $break['passenger_pricing'][2]['passenger_type']);
        $this->assertSame(80000.0, (float) $break['passenger_pricing'][0]['total_amount']);
        $this->assertEqualsWithDelta(150000.0, $break['supplier_total'], 0.01);
    }

    public function test_passenger_pricing_unavailable_when_passenger_info_list_missing_totals(): void
    {
        $fare = [
            'totalFare' => [
                'currency' => 'PKR',
                'totalPrice' => 12500,
                'baseFareAmount' => 10000,
                'totalTaxAmount' => 2500,
            ],
            'passengerInfoList' => [
                ['passengerInfo' => ['passengerType' => 'ADT', 'nonRefundable' => false]],
            ],
        ];

        $break = $this->extractFareBreakdown($fare);

        $this->assertFalse($break['passenger_pricing_available']);
        $this->assertNull($break['passenger_pricing']);
        $this->assertSame(12500.0, $break['supplier_total']);
    }

    public function test_passenger_pricing_unavailable_when_row_sum_does_not_match_supplier_total(): void
    {
        $fare = [
            'totalFare' => [
                'currency' => 'PKR',
                'totalPrice' => 100000,
                'baseFareAmount' => 80000,
                'totalTaxAmount' => 20000,
            ],
            'passengerInfoList' => [
                [
                    'passengerInfo' => [
                        'passengerType' => 'ADT',
                        'passengerNumber' => 1,
                        'passengerTotalFare' => [
                            'totalPrice' => 50000,
                            'baseFareAmount' => 40000,
                            'totalTaxAmount' => 10000,
                        ],
                    ],
                ],
            ],
        ];

        $break = $this->extractFareBreakdown($fare);

        $this->assertFalse($break['passenger_pricing_available']);
        $this->assertNull($break['passenger_pricing']);
    }

    public function test_child_c09_ptc_maps_to_child(): void
    {
        $pack = $this->extractPassengerPricing([
            'passengerInfoList' => [[
                'passengerInfo' => [
                    'passengerType' => 'C09',
                    'passengerNumber' => 1,
                    'passengerTotalFare' => [
                        'totalPrice' => 1000,
                        'baseFareAmount' => 800,
                        'totalTaxAmount' => 200,
                    ],
                ],
            ]],
        ], 1000.0);

        $this->assertTrue($pack['passenger_pricing_available']);
        $this->assertSame('child', $pack['passenger_pricing'][0]['passenger_type']);
    }

    public function test_passenger_number_sets_quantity_without_splitting_amounts(): void
    {
        $pack = $this->extractPassengerPricing([
            'passengerInfoList' => [[
                'passengerInfo' => [
                    'passengerType' => 'ADT',
                    'passengerNumber' => 2,
                    'passengerTotalFare' => [
                        'totalPrice' => 100000,
                        'baseFareAmount' => 80000,
                        'totalTaxAmount' => 20000,
                    ],
                ],
            ]],
        ], 100000.0);

        $this->assertTrue($pack['passenger_pricing_available']);
        $this->assertSame(2, $pack['passenger_pricing'][0]['passenger_count']);
        $this->assertSame(100000.0, (float) $pack['passenger_pricing'][0]['total_amount']);
    }

    public function test_build_passenger_counts_from_search_request(): void
    {
        $normalizer = app(SabreFlightSearchNormalizer::class);
        $method = new ReflectionMethod(SabreFlightSearchNormalizer::class, 'buildPassengerCountsFromSearchRequest');
        $counts = $method->invoke($normalizer, FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-06-25',
            'adults' => 1,
            'children' => 1,
            'infants' => 1,
        ]));

        $this->assertSame(1, $counts['adults']);
        $this->assertSame(1, $counts['children']);
        $this->assertSame(1, $counts['infants']);
        $this->assertSame(3, $counts['total']);
    }

    public function test_results_blade_contains_passenger_pricing_modal_path(): void
    {
        $contents = (string) file_get_contents(resource_path('views/frontend/flights/results.blade.php'));

        $this->assertStringContainsString('passenger_pricing_available', $contents);
        $this->assertStringContainsString('groupPassengerPricingRows', $contents);
        $this->assertStringContainsString('Agency charges', $contents);
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

    /**
     * @param  array<string, mixed>  $fareNode
     * @return array{passenger_pricing: list<array<string, mixed>>|null, passenger_pricing_available: bool}
     */
    protected function extractPassengerPricing(array $fareNode, float $supplierTotal): array
    {
        $normalizer = app(SabreFlightSearchNormalizer::class);
        $method = new ReflectionMethod(SabreFlightSearchNormalizer::class, 'extractPassengerPricingFromFare');

        return $method->invoke($normalizer, $fareNode, $supplierTotal);
    }
}
