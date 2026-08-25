<?php

namespace Tests\Feature\FlightSearch;

use App\Services\FlightSearch\FlightSearchResultStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProgressiveFlightSearchResultStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_begin_search_publishes_searching_status_without_offers(): void
    {
        $store = app(FlightSearchResultStore::class);
        $searchId = $store->beginSearch([
            'origin' => 'ISB',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(14)->toDateString(),
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ]);

        $payload = $store->get($searchId);
        $this->assertNotNull($payload);
        $this->assertSame(FlightSearchResultStore::SEARCH_STATUS_SEARCHING, $store->resolveSearchStatus($payload));
        $this->assertSame([], $payload['offers']);
    }

    public function test_publish_progress_merges_partial_then_ready_without_duplicates(): void
    {
        $store = app(FlightSearchResultStore::class);
        $criteria = [
            'origin' => 'ISB',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(14)->toDateString(),
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];
        $searchId = $store->beginSearch($criteria);

        $batchA = [[
            'offer_id' => 'offer-a',
            'supplier_provider' => 'sabre',
            'airline_code' => 'PK',
            'flight_number' => 'PK123',
            'final_customer_price' => 50000,
            'pricing_currency' => 'PKR',
            'currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'segments' => [],
        ]];
        $store->publishProgress(
            $searchId,
            $criteria,
            $batchA,
            [],
            FlightSearchResultStore::SEARCH_STATUS_PARTIAL,
        );

        $partial = $store->get($searchId);
        $this->assertSame(FlightSearchResultStore::SEARCH_STATUS_PARTIAL, $store->resolveSearchStatus($partial ?? []));
        $this->assertCount(1, $partial['offers'] ?? []);

        $batchB = [
            $batchA[0],
            [
                'offer_id' => 'offer-b',
                'supplier_provider' => 'sabre',
                'airline_code' => 'EK',
                'flight_number' => 'EK601',
                'final_customer_price' => 70000,
                'pricing_currency' => 'PKR',
                'currency' => 'PKR',
                'conversion_status' => 'same_currency',
                'segments' => [],
            ],
        ];
        $merged = $store->mergeOffersByIdentity($partial['offers'] ?? [], $batchB);
        $store->publishProgress(
            $searchId,
            $criteria,
            $merged,
            [],
            FlightSearchResultStore::SEARCH_STATUS_READY,
        );

        $ready = $store->get($searchId);
        $this->assertSame(FlightSearchResultStore::SEARCH_STATUS_READY, $store->resolveSearchStatus($ready ?? []));
        $this->assertCount(2, $ready['offers'] ?? []);
        $ids = array_map(static fn (array $o): string => (string) ($o['offer_id'] ?? ''), $ready['offers'] ?? []);
        $this->assertSame(['offer-a', 'offer-b'], $ids);
    }

    public function test_mark_failed_keeps_prior_partial_offers(): void
    {
        $store = app(FlightSearchResultStore::class);
        $criteria = [
            'origin' => 'ISB',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(21)->toDateString(),
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];
        $searchId = $store->beginSearch($criteria);
        $store->publishProgress($searchId, $criteria, [[
            'offer_id' => 'offer-keep',
            'supplier_provider' => 'sabre',
            'final_customer_price' => 41000,
            'pricing_currency' => 'PKR',
            'currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'segments' => [],
        ]], [], FlightSearchResultStore::SEARCH_STATUS_PARTIAL);

        $this->assertTrue($store->markFailed($searchId, 'supplier timeout'));
        $payload = $store->get($searchId);
        $this->assertSame(FlightSearchResultStore::SEARCH_STATUS_FAILED, $store->resolveSearchStatus($payload ?? []));
        $this->assertCount(1, $payload['offers'] ?? []);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}
