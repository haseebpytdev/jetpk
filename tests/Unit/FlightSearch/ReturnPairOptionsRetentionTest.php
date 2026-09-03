<?php

namespace Tests\Unit\FlightSearch;

use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\FlightSearch\ReturnSplitComboService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

/**
 * Progressive Return writes must not hide already-pollable pairs when a later
 * snapshot temporarily fails precompute / returns an empty pair list.
 */
class ReturnPairOptionsRetentionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        Cache::flush();
        parent::tearDown();
    }

    public function test_publish_progress_retains_prior_pairs_when_rebuild_returns_empty(): void
    {
        $criteria = [
            'origin' => 'ISB',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(21)->toDateString(),
            'return_date' => now()->addDays(28)->toDateString(),
            'trip_type' => 'round_trip',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];

        $priorPairs = [[
            'combo_id' => 'pair-1',
            'from_total_amount' => 90000,
            'total_amount' => 90000,
        ]];

        $split = Mockery::mock(ReturnSplitComboService::class);
        $split->shouldReceive('isEnabled')->andReturn(true);
        $split->shouldReceive('safeBuildIndexForStore')->andReturn([
            'combo_count' => 1,
            'outbound' => [],
            'inbound' => [],
        ]);
        $split->shouldReceive('buildPairedComboOptions')
            ->once()
            ->andReturn($priorPairs);
        $split->shouldReceive('buildPairedComboOptions')
            ->once()
            ->andReturn([]);
        $this->app->instance(ReturnSplitComboService::class, $split);

        $store = app(FlightSearchResultStore::class);
        $searchId = $store->beginSearch($criteria);

        $offerA = [
            'offer_id' => 'offer-a',
            'supplier_provider' => 'sabre',
            'airline_code' => 'PK',
            'flight_number' => 'PK100',
            'final_customer_price' => 45000,
            'pricing_currency' => 'PKR',
            'currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'segments' => [],
        ];
        $store->publishProgress(
            $searchId,
            $criteria,
            [$offerA],
            [],
            FlightSearchResultStore::SEARCH_STATUS_PARTIAL,
        );

        $first = $store->get($searchId);
        $this->assertNotNull($first);
        $this->assertCount(1, $first['return_pair_options'] ?? []);

        $offerB = array_merge($offerA, ['offer_id' => 'offer-b', 'flight_number' => 'PK200']);
        $store->publishProgress(
            $searchId,
            $criteria,
            [$offerA, $offerB],
            [],
            FlightSearchResultStore::SEARCH_STATUS_PARTIAL,
        );

        $second = $store->get($searchId);
        $this->assertNotNull($second);
        $this->assertCount(1, $second['return_pair_options'] ?? []);
        $this->assertSame('pair-1', (string) ($second['return_pair_options'][0]['combo_id'] ?? ''));
    }
}
