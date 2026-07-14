<?php

namespace Tests\Unit\Services\FlightSearch;

use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\FlightSearch\ReturnSplitComboService;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnSplitComboServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReturnSplitComboService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ota.return_split_select_enabled' => true]);
        $this->service = app(ReturnSplitComboService::class);
    }

    public function test_build_index_includes_same_carrier_round_trip_combo(): void
    {
        $criteria = [
            'trip_type' => 'round_trip',
            'origin' => 'LHE',
            'destination' => 'DXB',
        ];

        $offers = [
            $this->roundTripOffer('combo-a', 'PK', 90000),
        ];

        $index = $this->service->buildIndex($criteria, $offers);

        $this->assertSame(1, $index['combo_count']);
        $this->assertCount(1, $index['combos']);
        $this->assertSame('combo-a', $index['combos'][0]['combo_id']);
        $this->assertNotSame('', $index['combos'][0]['outbound_key']);
        $this->assertNotSame('', $index['combos'][0]['return_key']);
    }

    public function test_mixed_carrier_combo_is_excluded(): void
    {
        $criteria = [
            'trip_type' => 'round_trip',
            'origin' => 'LHE',
            'destination' => 'DXB',
        ];

        $offer = $this->roundTripOffer('combo-mixed', 'PK', 95000);
        $offer['segments'][1]['airline_code'] = 'EK';
        $offer['segments'][1]['flight_number'] = '602';

        $index = $this->service->buildIndex($criteria, [$offer]);

        $this->assertSame(0, $index['combo_count']);
        $this->assertSame(1, $index['excluded_count']);
    }

    public function test_outbound_grouping_uses_lowest_total_return_fare(): void
    {
        $criteria = [
            'trip_type' => 'round_trip',
            'origin' => 'LHE',
            'destination' => 'DXB',
        ];

        $sharedOutbound = [
            ['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-07-01T08:00:00', 'arrival_at' => '2026-07-01T11:00:00', 'airline_code' => 'PK', 'flight_number' => '201', 'booking_class' => 'Y'],
            ['origin' => 'DXB', 'destination' => 'LHE', 'departure_at' => '2026-07-08T14:00:00', 'arrival_at' => '2026-07-08T19:00:00', 'airline_code' => 'PK', 'flight_number' => '202', 'booking_class' => 'Y'],
        ];

        $offerCheapReturn = $this->offerFromSegments('combo-cheap', $sharedOutbound, 80000);
        $offerAltReturn = $this->offerFromSegments('combo-alt', [
            $sharedOutbound[0],
            ['origin' => 'DXB', 'destination' => 'LHE', 'departure_at' => '2026-07-08T18:00:00', 'arrival_at' => '2026-07-08T23:00:00', 'airline_code' => 'PK', 'flight_number' => '204', 'booking_class' => 'Y'],
        ], 95000);

        $index = $this->service->buildIndex($criteria, [$offerCheapReturn, $offerAltReturn]);
        $options = $this->service->buildOutboundOptions(
            $index,
            [$offerCheapReturn, $offerAltReturn],
            $criteria,
            [],
            [],
            [],
            'test-search-id',
        );

        $this->assertCount(1, $options);
        $this->assertSame(80000.0, $options[0]['from_total_amount']);
        $this->assertStringContainsString('PKR', (string) $options[0]['from_total_display']);
        $this->assertStringNotContainsString('total return fare', strtolower((string) $options[0]['from_total_display']));
        $this->assertSame(2, $options[0]['combo_count']);
    }

    public function test_return_options_filtered_by_outbound_key(): void
    {
        $criteria = [
            'trip_type' => 'round_trip',
            'origin' => 'LHE',
            'destination' => 'DXB',
        ];

        $offerA = $this->roundTripOffer('combo-a', 'PK', 90000);
        $offerB = $this->offerFromSegments('combo-b', [
            ['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-07-01T10:00:00', 'arrival_at' => '2026-07-01T13:00:00', 'airline_code' => 'PK', 'flight_number' => '211', 'booking_class' => 'Y'],
            ['origin' => 'DXB', 'destination' => 'LHE', 'departure_at' => '2026-07-08T14:00:00', 'arrival_at' => '2026-07-08T19:00:00', 'airline_code' => 'PK', 'flight_number' => '202', 'booking_class' => 'Y'],
        ], 92000);

        $offers = [$offerA, $offerB];
        $index = $this->service->buildIndex($criteria, $offers);
        $outboundKey = (string) $index['combos'][0]['outbound_key'];

        $built = $this->service->buildReturnOptions(
            $index,
            $outboundKey,
            $offers,
            $criteria,
            [],
            [],
            [],
            'test-search-id',
        );

        $this->assertCount(1, $built['options']);
        $this->assertSame('combo-a', $built['options'][0]['combo_id']);
    }

    public function test_one_way_criteria_yields_empty_index(): void
    {
        $index = $this->service->buildIndex(
            ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'DXB'],
            [$this->roundTripOffer('x', 'PK', 1000)],
        );

        $this->assertSame(0, $index['combo_count']);
    }

    public function test_build_checkout_split_summary_keeps_separate_leg_fare_family_labels(): void
    {
        $store = app(FlightSearchResultStore::class);
        $criteria = [
            'trip_type' => 'round_trip',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-07-01',
            'return_date' => '2026-07-08',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];

        $offer = $this->roundTripOffer('combo-a', 'PK', 100000);
        $offer['branded_fares'] = [
            [
                'name' => 'FREEDOM',
                'brand_code' => 'FRD',
                'price_total' => 110000,
                'currency' => 'PKR',
                'pricing_information_index' => 0,
                'baggage_summary' => '30kg',
            ],
            [
                'name' => 'ECO LIGHT',
                'brand_code' => 'ECL',
                'price_total' => 100000,
                'currency' => 'PKR',
                'pricing_information_index' => 1,
                'baggage_summary' => '0kg',
            ],
        ];

        $searchId = $store->store($criteria, [$offer], []);
        $index = $store->getReturnSplitIndex($searchId);
        $this->assertNotNull($index);
        $outboundKey = (string) ($index['combos'][0]['outbound_key'] ?? '');
        $keys = FlightOfferDisplayPresenter::fareFamilyOptionKeysSample($offer);
        $this->assertCount(2, $keys);

        $summary = $this->service->buildCheckoutSplitSummary(
            $searchId,
            'combo-a',
            $outboundKey,
            $keys[0],
            $keys[1],
            $offer,
            $criteria,
        );

        $this->assertIsArray($summary);
        $this->assertTrue($summary['is_return_split']);
        $this->assertSame('FREEDOM', $summary['outbound']['branded_fare_title']);
        $this->assertSame('ECO LIGHT', $summary['return']['branded_fare_title']);
        $this->assertSame('FREEDOM', $summary['outbound']['fare_family_title']);
        $this->assertSame('ECO LIGHT', $summary['return']['fare_family_title']);
        $this->assertSame($keys[0], $summary['outbound_fare_option_key']);
        $this->assertSame($keys[1], $summary['return_fare_option_key']);
        $this->assertSame('combo_total', $summary['pricing_mode']);
        $this->assertSame('combo_total', $summary['totals']['pricing_mode']);
        $this->assertNull($summary['outbound']['price'] ?? null);
        $this->assertNull($summary['return']['price'] ?? null);
        $this->assertNotNull($summary['totals']['base_price_display']);
        $this->assertNotNull($summary['totals']['grand_total_display']);
        $this->assertNotNull($summary['totals']['fare_difference_display']);
    }

    public function test_build_checkout_split_summary_resolves_outbound_fare_from_non_cheapest_combo(): void
    {
        $store = app(FlightSearchResultStore::class);
        $criteria = [
            'trip_type' => 'round_trip',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-07-01',
            'return_date' => '2026-07-08',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];

        $sharedOutbound = [
            ['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-07-01T08:00:00', 'arrival_at' => '2026-07-01T11:00:00', 'airline_code' => 'PK', 'flight_number' => '201', 'booking_class' => 'Y'],
        ];
        $cheapReturn = ['origin' => 'DXB', 'destination' => 'LHE', 'departure_at' => '2026-07-08T14:00:00', 'arrival_at' => '2026-07-08T19:00:00', 'airline_code' => 'PK', 'flight_number' => '202', 'booking_class' => 'Y'];
        $altReturn = ['origin' => 'DXB', 'destination' => 'LHE', 'departure_at' => '2026-07-08T18:00:00', 'arrival_at' => '2026-07-08T23:00:00', 'airline_code' => 'PK', 'flight_number' => '204', 'booking_class' => 'Y'];

        $cheapOffer = $this->offerFromSegments('combo-cheap', array_merge($sharedOutbound, [$cheapReturn]), 86074);
        $cheapOffer['branded_fares'] = [[
            'name' => 'ECO LIGHT',
            'brand_code' => 'ECL',
            'price_total' => 86074,
            'currency' => 'PKR',
            'pricing_information_index' => 0,
        ]];

        $selectedOffer = $this->offerFromSegments('combo-selected', array_merge($sharedOutbound, [$altReturn]), 90806);
        $selectedOffer['branded_fares'] = [
            [
                'name' => 'FREEDOM',
                'brand_code' => 'FRD',
                'price_total' => 90806,
                'currency' => 'PKR',
                'pricing_information_index' => 0,
                'baggage_summary' => '30kg',
            ],
            [
                'name' => 'SMART',
                'brand_code' => 'SMT',
                'price_total' => 90806,
                'currency' => 'PKR',
                'pricing_information_index' => 1,
                'baggage_summary' => '20kg',
            ],
        ];

        $searchId = $store->store($criteria, [$cheapOffer, $selectedOffer], []);
        $index = $store->getReturnSplitIndex($searchId);
        $outboundKey = (string) ($index['combos'][0]['outbound_key'] ?? '');
        $freedomKey = FlightOfferDisplayPresenter::fareFamilyOptionKeysSample($selectedOffer)[0];
        $smartKey = FlightOfferDisplayPresenter::fareFamilyOptionKeysSample($selectedOffer)[1];

        $summary = $this->service->buildCheckoutSplitSummary(
            $searchId,
            'combo-selected',
            $outboundKey,
            $freedomKey,
            $smartKey,
            $selectedOffer,
            $criteria,
        );

        $this->assertIsArray($summary);
        $this->assertSame('FREEDOM', $summary['outbound']['fare_family_title']);
        $this->assertSame('SMART', $summary['return']['fare_family_title']);
        $this->assertSame(86074.0, $summary['totals']['base_price']);
        $this->assertSame(90806.0, $summary['totals']['selected_total']);
        $this->assertSame('+ PKR 4,732', $summary['totals']['fare_difference_display']);
    }

    /**
     * @return array<string, mixed>
     */
    private function roundTripOffer(string $id, string $carrier, float $price): array
    {
        return $this->offerFromSegments($id, [
            ['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-07-01T08:00:00', 'arrival_at' => '2026-07-01T11:00:00', 'airline_code' => $carrier, 'flight_number' => '201', 'booking_class' => 'Y'],
            ['origin' => 'DXB', 'destination' => 'LHE', 'departure_at' => '2026-07-08T14:00:00', 'arrival_at' => '2026-07-08T19:00:00', 'airline_code' => $carrier, 'flight_number' => '202', 'booking_class' => 'Y'],
        ], $price);
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return array<string, mixed>
     */
    private function offerFromSegments(string $id, array $segments, float $price): array
    {
        return [
            'id' => $id,
            'offer_id' => $id,
            'supplier_provider' => 'sabre',
            'airline_code' => (string) ($segments[0]['airline_code'] ?? 'PK'),
            'origin' => 'LHE',
            'destination' => 'LHE',
            'base_fare' => $price * 0.8,
            'taxes' => $price * 0.2,
            'final_customer_price' => $price,
            'currency' => 'PKR',
            'segments' => $segments,
            'validating_carrier' => (string) ($segments[0]['airline_code'] ?? 'PK'),
        ];
    }
}
