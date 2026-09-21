<?php

namespace Tests\Unit\PublicContent;

use App\Services\PublicContent\PublicShortRefService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicShortRefServiceTest extends TestCase
{
    public function test_mint_and_resolve_flight_search_ref(): void
    {
        Cache::flush();
        $service = app(PublicShortRefService::class);
        $code = $service->mintFlightSearch('abc-search-id-uuid', 600);

        $this->assertMatchesRegularExpression('/^[a-z0-9]{8,32}$/', $code);

        $record = $service->resolve($code);
        $this->assertNotNull($record);
        $this->assertSame(PublicShortRefService::PURPOSE_FLIGHT_SEARCH, $record['purpose']);
        $this->assertSame('search_id', $record['target_type']);
        $this->assertSame('abc-search-id-uuid', $record['target_key']);
    }

    public function test_mint_flight_search_is_idempotent_via_reverse_map(): void
    {
        Cache::flush();
        $service = app(PublicShortRefService::class);
        $first = $service->mintFlightSearch('same-search-id', 600);
        $second = $service->mintFlightSearch('same-search-id', 600);

        $this->assertSame($first, $second);
        $this->assertSame(
            $first,
            $service->findCodeForTarget(
                PublicShortRefService::PURPOSE_FLIGHT_SEARCH,
                'search_id',
                'same-search-id'
            )
        );
    }

    public function test_resolve_rejects_unknown_and_malformed_codes(): void
    {
        $service = app(PublicShortRefService::class);
        $this->assertNull($service->resolve('not-found-code-xx'));
        $this->assertNull($service->resolve('bad!'));
        $this->assertNull($service->resolve(''));
    }
}
