<?php

namespace Tests\Feature\FlightSearch;

use App\Services\FlightSearch\FlightSearchResultStore;
use App\Support\FlightSearch\SabreOfferFreshness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FlightSearchResultStoreStaleOfferTest extends TestCase
{
    use RefreshDatabase;

    public function test_malformed_cache_payload_is_not_readable(): void
    {
        $store = app(FlightSearchResultStore::class);
        Cache::put('flight_search:bad-payload', [
            'criteria' => ['trip_type' => 'one_way'],
            'offers' => 'not-an-array',
        ], 60);

        $this->assertNull($store->get('bad-payload'));
        $this->assertNull($store->findOffer('bad-payload', 'offer-1'));
    }

    public function test_schema_version_mismatch_rejects_payload(): void
    {
        $store = app(FlightSearchResultStore::class);
        Cache::put('flight_search:old-schema', [
            'schema_version' => 'v0',
            'criteria' => ['trip_type' => 'one_way'],
            'offers' => [],
            'created_at' => now()->toIso8601String(),
        ], 60);

        $this->assertNull($store->get('old-schema'));
    }

    public function test_stale_payload_blocks_selection_but_allows_display_read(): void
    {
        Carbon::setTestNow('2026-07-26 12:00:00');

        $store = app(FlightSearchResultStore::class);
        $searchId = $store->store([
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-08-01',
            'adults' => 1,
            'cabin' => 'economy',
        ], [
            [
                'id' => 'offer-stale-1',
                'offer_id' => 'offer-stale-1',
                'supplier_provider' => 'sabre',
            ],
        ], []);

        $payload = Cache::get('flight_search:'.$searchId);
        $this->assertIsArray($payload);
        $payload['created_at'] = now()->subSeconds(
            app(SabreOfferFreshness::class)->staleAfterSeconds() + 30
        )->toIso8601String();
        Cache::put('flight_search:'.$searchId, $payload, 1800);

        $displayPayload = $store->get($searchId);
        $this->assertNotNull($displayPayload);
        $this->assertSame(
            SabreOfferFreshness::STATUS_STALE,
            $displayPayload['offer_freshness']['offer_freshness_status'] ?? null
        );

        $this->assertNull($store->findOffer($searchId, 'offer-stale-1'));
        $this->assertNull($store->getReturnSplitIndex($searchId));

        Carbon::setTestNow();
    }

    public function test_fresh_payload_allows_offer_selection(): void
    {
        $store = app(FlightSearchResultStore::class);
        $searchId = $store->store([
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(10)->toDateString(),
            'adults' => 1,
            'cabin' => 'economy',
        ], [
            [
                'id' => 'offer-fresh-1',
                'offer_id' => 'offer-fresh-1',
                'supplier_provider' => 'sabre',
                'segments' => [
                    ['origin' => 'LHE', 'destination' => 'DXB', 'booking_class' => 'Y'],
                ],
            ],
        ], []);

        $offer = $store->findOffer($searchId, 'offer-fresh-1');
        $this->assertIsArray($offer);
        $this->assertSame('offer-fresh-1', $offer['offer_id']);
    }
}
