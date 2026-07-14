<?php

namespace Tests\Feature\GroupTicketing;

use App\Models\GroupInventory;
use App\Services\GroupTicketing\GroupInventoryFreshnessService;
use App\Support\GroupTicketing\GroupTicketingLivePolicy;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchResultsFailClosedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ota.group_ticketing.realtime_search_enabled' => true,
            'ota.group_ticketing.require_live_provider_for_public_results' => true,
            'ota.group_ticketing.allow_stale_public_results' => false,
        ]);
    }

    public function test_search_hides_bookable_results_when_provider_refresh_fails(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'stale-1',
            'public_id' => 'ALH-STALE-1',
            'title' => 'Stale Package',
            'sector' => 'SKT-SHJ',
            'airline_name' => 'AIR ARABIA',
            'departure_date' => '2026-06-21',
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 99000,
            'currency' => 'PKR',
            'is_active' => true,
            'synced_at' => now()->subDay(),
        ]);

        $freshness = $this->createMock(GroupInventoryFreshnessService::class);
        $freshness->method('ensureFreshForSearch')->willReturn([
            'synced' => false,
            'skipped' => true,
            'skip_reason' => 'sync_skipped',
            'last_synced_at' => now()->subDay(),
            'minutes_ago' => 1440,
            'refresh_attempted' => true,
            'refresh_failed' => true,
            'provider_confirmed' => false,
            'bookable' => false,
            'user_notice' => GroupTicketingLivePolicy::PUBLIC_SEARCH_UNAVAILABLE_MESSAGE,
        ]);
        $freshness->method('publicResultsAreBookable')->willReturn(false);
        $this->app->instance(GroupInventoryFreshnessService::class, $freshness);

        $this->get(route('group-ticketing.search'))
            ->assertOk()
            ->assertSee(GroupTicketingLivePolicy::PUBLIC_SEARCH_UNAVAILABLE_MESSAGE, false)
            ->assertDontSee('data-testid="group-result-row"', false)
            ->assertDontSee('data-testid="group-load-more"', false);
    }
}
