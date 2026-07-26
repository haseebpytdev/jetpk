<?php

namespace Tests\Feature\FlightSearch;

use App\Models\Agency;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\FlightSearch\FlightSearchSupplierResultCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FlightSearchSupplierResultCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_result_cache_stores_and_returns_offers_by_criteria_fingerprint(): void
    {
        Cache::flush();

        $agency = Agency::factory()->create(['slug' => config('ota.default_agency_slug')]);
        $cache = app(FlightSearchSupplierResultCache::class);

        $criteria = [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(14)->toDateString(),
            'adults' => 1,
            'cabin' => 'economy',
            'currency' => 'PKR',
        ];
        $context = [
            'agency_id' => $agency->id,
            'client_slug' => 'jetpk',
            'source_channel' => 'public_guest',
            'supplier_connection_scope' => [
                ['connection_id' => 1, 'provider' => 'sabre', 'lanes' => ['gds']],
            ],
        ];

        $this->assertNull($cache->get($criteria, $context));

        $fingerprint = $cache->put($criteria, $context, [
            ['offer_id' => 'sabre_test_1', 'supplier_provider' => 'sabre'],
        ], ['warning-a']);

        $hit = $cache->get($criteria, $context);
        $this->assertNotNull($hit);
        $this->assertCount(1, $hit['offers']);
        $this->assertSame('warning-a', $hit['warnings'][0]);
        $this->assertSame(64, strlen($fingerprint));
    }

    public function test_search_store_persists_criteria_cache_fingerprint(): void
    {
        $criteria = [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(10)->toDateString(),
            'adults' => 1,
            'cabin' => 'economy',
        ];

        $searchId = app(FlightSearchResultStore::class)->store($criteria, [], [], [
            'criteria_cache_context' => [
                'agency_id' => 1,
                'client_slug' => 'jetpk',
                'source_channel' => 'public_guest',
            ],
        ]);

        $payload = app(FlightSearchResultStore::class)->get($searchId);
        $this->assertIsArray($payload);
        $this->assertNotEmpty($payload['criteria_cache_fingerprint'] ?? null);
        $this->assertIsArray($payload['criteria_cache_summary'] ?? null);
    }
}
