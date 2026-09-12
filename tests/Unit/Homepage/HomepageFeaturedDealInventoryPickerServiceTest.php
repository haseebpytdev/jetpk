<?php

namespace Tests\Unit\Homepage;

use App\Enums\AccountType;
use App\Models\GroupInventory;
use App\Models\User;
use App\Services\Homepage\FeaturedDeals\HomepageFeaturedDealInventoryPickerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageFeaturedDealInventoryPickerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ota.group_ticketing.inventory_search_sync_enabled' => false,
            'ota.group_ticketing.realtime_search_enabled' => false,
            'ota.group_ticketing.require_live_provider_for_public_results' => false,
        ]);

        $this->actingAs(User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]));
    }

    public function test_includes_future_active_inventory_with_positive_price(): void
    {
        $inventory = $this->seedInventory('ISB-DXB', 'Air Arabia', 89000, 'JP-FUTURE');

        $items = app(HomepageFeaturedDealInventoryPickerService::class)->listEligible();

        $this->assertCount(1, $items);
        $this->assertSame((int) $inventory->id, $items[0]['inventory_id']);
        $this->assertSame('JP-FUTURE', $items[0]['public_id']);
        $this->assertSame('ISB', $items[0]['origin']);
        $this->assertSame('DXB', $items[0]['destination']);
        $this->assertSame(89000, $items[0]['current_price']);
        $this->assertSame(20, $items[0]['available_seats']);
    }

    public function test_excludes_past_and_zero_price_inventory(): void
    {
        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'past',
            'public_id' => 'JP-PAST',
            'title' => 'Past',
            'sector' => 'ISB-DXB',
            'airline_name' => 'Air Arabia',
            'departure_date' => now()->subDay()->toDateString(),
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 50000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'free',
            'public_id' => 'JP-FREE',
            'title' => 'Free',
            'sector' => 'ISB-DXB',
            'airline_name' => 'Air Arabia',
            'departure_date' => now()->addDays(7)->toDateString(),
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 0,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $items = app(HomepageFeaturedDealInventoryPickerService::class)->listEligible();

        $this->assertSame([], $items);
    }

    public function test_search_filters_by_public_id(): void
    {
        $this->seedInventory('ISB-DXB', 'Air Arabia', 89000, 'JP-SEARCH-ME');
        $this->seedInventory('LHE-JED', 'Saudia', 120000, 'JP-OTHER');

        $items = app(HomepageFeaturedDealInventoryPickerService::class)->listEligible('SEARCH-ME');

        $this->assertCount(1, $items);
        $this->assertSame('JP-SEARCH-ME', $items[0]['public_id']);
    }

    public function test_orders_by_price_then_departure(): void
    {
        $cheapLater = $this->seedInventory('ISB-DXB', 'Air Arabia', 80000, 'JP-CHEAP', 21);
        $expensiveSoon = $this->seedInventory('ISB-DXB', 'Fly Jinnah', 95000, 'JP-EXP', 10);

        $items = app(HomepageFeaturedDealInventoryPickerService::class)->listEligible();

        $this->assertCount(2, $items);
        $this->assertSame((int) $cheapLater->id, $items[0]['inventory_id']);
        $this->assertSame((int) $expensiveSoon->id, $items[1]['inventory_id']);
    }

    private function seedInventory(
        string $sector,
        string $airline,
        int $price,
        string $publicId,
        int $daysAhead = 14,
    ): GroupInventory {
        return GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => $publicId,
            'public_id' => $publicId,
            'title' => $sector.' group',
            'sector' => $sector,
            'airline_name' => $airline,
            'departure_date' => now()->addDays($daysAhead)->toDateString(),
            'total_seats' => 20,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => $price,
            'currency' => 'PKR',
            'is_active' => true,
        ]);
    }
}
