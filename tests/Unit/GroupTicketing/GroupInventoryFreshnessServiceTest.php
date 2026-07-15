<?php

namespace Tests\Unit\GroupTicketing;

use App\Services\GroupTicketing\GroupInventoryFacetService;
use App\Services\GroupTicketing\GroupInventoryFreshnessService;
use App\Services\GroupTicketing\GroupInventorySyncService;
use App\Support\GroupTicketing\GroupTicketingLivePolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GroupInventoryFreshnessServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->resetFreshnessRequestFlag();
    }

    public function test_skips_when_disabled(): void
    {
        config(['ota.group_ticketing.realtime_search_enabled' => false]);

        $facet = $this->createMock(GroupInventoryFacetService::class);
        $facet->method('lastInventorySyncAt')->willReturn(Carbon::now()->subMinutes(30));

        $sync = $this->createMock(GroupInventorySyncService::class);
        $sync->expects($this->never())->method('sync');

        $service = new GroupInventoryFreshnessService($facet, $sync);
        $result = $service->ensureFreshForSearch();

        $this->assertTrue($result['skipped']);
        $this->assertFalse($result['synced']);
        $this->assertSame('disabled', $result['skip_reason']);
        $this->assertFalse($result['refresh_attempted']);
        $this->assertFalse($result['provider_confirmed']);
        $this->assertFalse($result['bookable']);
    }

    public function test_syncs_when_realtime_ttl_zero(): void
    {
        config([
            'ota.group_ticketing.realtime_search_enabled' => true,
            'ota.group_ticketing.realtime_search_ttl_seconds' => 0,
        ]);

        $facet = $this->createMock(GroupInventoryFacetService::class);
        $facet->method('lastInventorySyncAt')->willReturnOnConsecutiveCalls(
            Carbon::now()->subMinutes(10),
            Carbon::now(),
        );

        $sync = $this->createMock(GroupInventorySyncService::class);
        $sync->expects($this->once())->method('sync')
            ->with(false, true)
            ->willReturn([
                'synced' => 5,
                'deactivated' => 0,
                'skipped' => false,
                'message' => null,
            ]);

        $service = new GroupInventoryFreshnessService($facet, $sync);
        $result = $service->ensureFreshForSearch();

        $this->assertTrue($result['synced']);
        $this->assertFalse($result['skipped']);
        $this->assertTrue($result['refresh_attempted']);
        $this->assertFalse($result['refresh_failed']);
        $this->assertTrue($result['provider_confirmed']);
        $this->assertTrue($result['bookable']);
    }

    public function test_skips_when_ttl_fresh_and_stale_results_allowed(): void
    {
        config([
            'ota.group_ticketing.realtime_search_enabled' => true,
            'ota.group_ticketing.realtime_search_ttl_seconds' => 120,
            'ota.group_ticketing.require_live_provider_for_public_results' => false,
            'ota.group_ticketing.allow_stale_public_results' => true,
        ]);

        Cache::put('group-ticketing:realtime-sync-at', time(), 120);

        $facet = $this->createMock(GroupInventoryFacetService::class);
        $facet->method('lastInventorySyncAt')->willReturn(Carbon::now()->subMinutes(10));

        $sync = $this->createMock(GroupInventorySyncService::class);
        $sync->expects($this->never())->method('sync');

        $service = new GroupInventoryFreshnessService($facet, $sync);
        $result = $service->ensureFreshForSearch();

        $this->assertTrue($result['skipped']);
        $this->assertSame('ttl_fresh', $result['skip_reason']);
    }

    public function test_request_dedupe_skips_second_call(): void
    {
        config([
            'ota.group_ticketing.realtime_search_enabled' => true,
            'ota.group_ticketing.realtime_search_ttl_seconds' => 0,
        ]);

        $facet = $this->createMock(GroupInventoryFacetService::class);
        $facet->method('lastInventorySyncAt')->willReturn(Carbon::now());

        $sync = $this->createMock(GroupInventorySyncService::class);
        $sync->expects($this->once())->method('sync')
            ->with(false, true)
            ->willReturn([
                'synced' => 1,
                'deactivated' => 0,
                'skipped' => false,
                'message' => null,
            ]);

        $service = new GroupInventoryFreshnessService($facet, $sync);
        $first = $service->ensureFreshForSearch();
        $second = $service->ensureFreshForSearch();

        $this->assertTrue($first['synced']);
        $this->assertTrue($second['skipped']);
        $this->assertSame('request_dedupe', $second['skip_reason']);
    }

    public function test_uses_current_inventory_when_lock_busy(): void
    {
        config([
            'ota.group_ticketing.realtime_search_enabled' => true,
            'ota.group_ticketing.realtime_search_ttl_seconds' => 0,
        ]);

        $facet = $this->createMock(GroupInventoryFacetService::class);
        $facet->method('lastInventorySyncAt')->willReturn(Carbon::now()->subMinutes(10));

        $sync = $this->createMock(GroupInventorySyncService::class);
        $sync->expects($this->never())->method('sync');

        Cache::lock('group-ticketing:inventory-search-sync', 60)->get();

        $service = new GroupInventoryFreshnessService($facet, $sync);
        $result = $service->ensureFreshForSearch();

        $this->assertTrue($result['skipped']);
        $this->assertSame('lock_busy', $result['skip_reason']);

        Cache::lock('group-ticketing:inventory-search-sync', 60)->forceRelease();
    }

    public function test_returns_user_notice_when_sync_skipped_by_provider(): void
    {
        config([
            'ota.group_ticketing.realtime_search_enabled' => true,
            'ota.group_ticketing.realtime_search_ttl_seconds' => 0,
        ]);

        $facet = $this->createMock(GroupInventoryFacetService::class);
        $facet->method('lastInventorySyncAt')->willReturn(Carbon::now()->subHour());

        $sync = $this->createMock(GroupInventorySyncService::class);
        $sync->expects($this->once())->method('sync')
            ->with(false, true)
            ->willReturn([
                'synced' => 0,
                'deactivated' => 0,
                'skipped' => true,
                'message' => 'Al-Haider API unavailable.',
            ]);

        $service = new GroupInventoryFreshnessService($facet, $sync);
        $result = $service->ensureFreshForSearch();

        $this->assertTrue($result['refresh_failed']);
        $this->assertSame(
            GroupTicketingLivePolicy::PUBLIC_SEARCH_UNAVAILABLE_MESSAGE,
            $result['user_notice'],
        );
        $this->assertFalse($result['bookable']);
    }

    private function resetFreshnessRequestFlag(): void
    {
        $reflection = new \ReflectionClass(GroupInventoryFreshnessService::class);
        $property = $reflection->getProperty('refreshedThisRequest');
        $property->setAccessible(true);
        $property->setValue(null, false);
    }
}
