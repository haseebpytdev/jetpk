<?php

namespace Tests\Unit\Homepage;

use App\Models\GroupInventory;
use App\Services\Homepage\FeaturedDeals\FeaturedDealInventoryResolver;
use App\Services\Homepage\FeaturedDeals\FeaturedDealInventoryResolver as Resolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeaturedDealInventoryResolverTest extends TestCase
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
    }

    public function test_picks_cheapest_among_exact_airline_matches(): void
    {
        $this->seedInventory('ISB-DXB', 'Air Arabia', 95000, 'JP-EXPENSIVE');
        $cheap = $this->seedInventory('ISB-DXB', 'Air Arabia', 89000, 'JP-CHEAP');

        $resolved = $this->resolve([[
            'id' => 'slot-1',
            'from' => 'ISB',
            'to' => 'DXB',
            'airline' => 'Air Arabia',
            'enabled' => '1',
        ]]);

        $this->assertCount(1, $resolved);
        $this->assertSame((int) $cheap->id, (int) $resolved[0]['inventory']->id);
        $this->assertSame(Resolver::RULE_EXACT_AIRLINE, $resolved[0]['rule']);
    }

    public function test_falls_back_to_same_sector_when_airline_unmatched(): void
    {
        $winner = $this->seedInventory('ISB-DXB', 'Fly Jinnah', 91000, 'JP-SECTOR');

        $resolved = $this->resolve([[
            'id' => 'slot-1',
            'from' => 'ISB',
            'to' => 'DXB',
            'airline' => 'Air Arabia',
            'enabled' => '1',
        ]]);

        $this->assertCount(1, $resolved);
        $this->assertSame((int) $winner->id, (int) $resolved[0]['inventory']->id);
        $this->assertSame(Resolver::RULE_SAME_SECTOR, $resolved[0]['rule']);
    }

    public function test_falls_back_globally_with_deterministic_order(): void
    {
        $winner = $this->seedInventory('LHE-JED', 'Saudia', 120000, 'JP-GLOBAL');

        $resolved = $this->resolve([[
            'id' => 'slot-1',
            'from' => 'ISB',
            'to' => 'DXB',
            'airline' => 'Missing Airline',
            'enabled' => '1',
        ]]);

        $this->assertCount(1, $resolved);
        $this->assertSame((int) $winner->id, (int) $resolved[0]['inventory']->id);
        $this->assertSame(Resolver::RULE_GLOBAL_FALLBACK, $resolved[0]['rule']);
    }

    public function test_returns_empty_when_no_eligible_inventory(): void
    {
        $resolved = $this->resolve([[
            'id' => 'slot-1',
            'from' => 'ISB',
            'to' => 'DXB',
            'enabled' => '1',
        ]]);

        $this->assertSame([], $resolved);
    }

    public function test_excludes_unavailable_inventory(): void
    {
        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'sold-out',
            'public_id' => 'JP-SOLD',
            'title' => 'Sold out',
            'sector' => 'ISB-DXB',
            'airline_name' => 'Air Arabia',
            'departure_date' => now()->addDays(14)->toDateString(),
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 10,
            'price' => 50000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $resolved = $this->resolve([[
            'id' => 'slot-1',
            'from' => 'ISB',
            'to' => 'DXB',
            'enabled' => '1',
        ]]);

        $this->assertSame([], $resolved);
    }

    public function test_resolves_exact_inventory_by_public_id(): void
    {
        $pinned = $this->seedInventory('ISB-DXB', 'Air Arabia', 99000, 'JP-PINNED');
        $this->seedInventory('ISB-DXB', 'Air Arabia', 89000, 'JP-CHEAPER');

        $resolved = $this->resolve([[
            'id' => 'slot-1',
            'resolution_mode' => 'exact_inventory',
            'inventory_public_id' => 'JP-PINNED',
            'enabled' => '1',
        ]]);

        $this->assertCount(1, $resolved);
        $this->assertSame((int) $pinned->id, (int) $resolved[0]['inventory']->id);
        $this->assertSame(Resolver::RULE_EXACT_INVENTORY, $resolved[0]['rule']);
        $this->assertSame(Resolver::MODE_EXACT_INVENTORY, $resolved[0]['resolution_mode']);
        $this->assertFalse($resolved[0]['fallback_used']);
    }

    public function test_dedupes_inventory_already_used_by_prior_slot(): void
    {
        $first = $this->seedInventory('ISB-DXB', 'Air Arabia', 89000, 'JP-1');
        $second = $this->seedInventory('ISB-DXB', 'Fly Jinnah', 91000, 'JP-2');

        $resolved = $this->resolve([
            ['id' => 'slot-1', 'from' => 'ISB', 'to' => 'DXB', 'enabled' => '1'],
            ['id' => 'slot-2', 'from' => 'ISB', 'to' => 'DXB', 'enabled' => '1'],
        ]);

        $this->assertCount(2, $resolved);
        $this->assertSame((int) $first->id, (int) $resolved[0]['inventory']->id);
        $this->assertSame((int) $second->id, (int) $resolved[1]['inventory']->id);
    }

    public function test_auto_airline_only_filter_picks_cheapest_matching_airline(): void
    {
        $this->seedInventory('ISB-DXB', 'Fly Jinnah', 85000, 'JP-OTHER');
        $winner = $this->seedInventory('LHE-JED', 'Air Arabia', 90000, 'JP-AIR');

        $resolved = $this->resolve([[
            'id' => 'slot-1',
            'resolution_mode' => 'auto_cheapest_by_criteria',
            'auto_filter' => 'airline_only',
            'airline' => 'Air Arabia',
            'enabled' => '1',
        ]]);

        $this->assertCount(1, $resolved);
        $this->assertSame((int) $winner->id, (int) $resolved[0]['inventory']->id);
        $this->assertSame(Resolver::RULE_AIRLINE_ONLY, $resolved[0]['rule']);
        $this->assertFalse($resolved[0]['fallback_used']);
    }

    public function test_auto_destination_only_filter_picks_cheapest_matching_destination(): void
    {
        $this->seedInventory('ISB-DXB', 'Air Arabia', 95000, 'JP-DXB-EXP');
        $winner = $this->seedInventory('LHE-DXB', 'Fly Jinnah', 88000, 'JP-DXB-CHEAP');

        $resolved = $this->resolve([[
            'id' => 'slot-1',
            'resolution_mode' => 'auto_cheapest_by_criteria',
            'auto_filter' => 'destination_only',
            'to' => 'DXB',
            'enabled' => '1',
        ]]);

        $this->assertCount(1, $resolved);
        $this->assertSame((int) $winner->id, (int) $resolved[0]['inventory']->id);
        $this->assertSame(Resolver::RULE_DESTINATION_ONLY, $resolved[0]['rule']);
    }

    public function test_auto_airline_destination_filter_with_optional_origin(): void
    {
        $this->seedInventory('LHE-DXB', 'Air Arabia', 86000, 'JP-LHE-DXB');
        $this->seedInventory('ISB-DXB', 'Air Arabia', 82000, 'JP-ISB-DXB');
        $this->seedInventory('ISB-DXB', 'Fly Jinnah', 80000, 'JP-WRONG-AIR');

        $resolved = $this->resolve([[
            'id' => 'slot-1',
            'resolution_mode' => 'auto_cheapest_by_criteria',
            'auto_filter' => 'airline_destination',
            'from' => 'ISB',
            'to' => 'DXB',
            'airline' => 'Air Arabia',
            'enabled' => '1',
        ]]);

        $this->assertCount(1, $resolved);
        $this->assertSame('JP-ISB-DXB', $resolved[0]['inventory']->public_id);
        $this->assertSame(Resolver::RULE_EXACT_AIRLINE, $resolved[0]['rule']);
    }

    public function test_excludes_expired_inventory(): void
    {
        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'expired',
            'public_id' => 'JP-EXPIRED',
            'title' => 'Expired',
            'sector' => 'ISB-DXB',
            'airline_name' => 'Air Arabia',
            'departure_date' => now()->subDay()->toDateString(),
            'total_seats' => 20,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 10000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $resolved = $this->resolve([[
            'id' => 'slot-1',
            'from' => 'ISB',
            'to' => 'DXB',
            'enabled' => '1',
        ]]);

        $this->assertSame([], $resolved);
    }

    public function test_deterministic_tie_ordering_by_departure_date_then_public_id(): void
    {
        $later = $this->seedInventoryOnDate('ISB-DXB', 'Air Arabia', 90000, 'JP-B', now()->addDays(20)->toDateString());
        $earlier = $this->seedInventoryOnDate('ISB-DXB', 'Air Arabia', 90000, 'JP-A', now()->addDays(10)->toDateString());

        $resolved = $this->resolve([[
            'id' => 'slot-1',
            'from' => 'ISB',
            'to' => 'DXB',
            'airline' => 'Air Arabia',
            'enabled' => '1',
        ]]);

        $this->assertCount(1, $resolved);
        $this->assertSame((int) $earlier->id, (int) $resolved[0]['inventory']->id);
        $this->assertNotSame((int) $later->id, (int) $resolved[0]['inventory']->id);
    }

    public function test_global_fallback_is_marked_when_no_criteria_match(): void
    {
        $winner = $this->seedInventory('KHI-LHE', 'Serene', 70000, 'JP-FALLBACK');

        $resolved = $this->resolve([[
            'id' => 'slot-1',
            'resolution_mode' => 'auto_cheapest_by_criteria',
            'auto_filter' => 'destination_only',
            'to' => 'ZZZ',
            'enabled' => '1',
        ]]);

        $this->assertCount(1, $resolved);
        $this->assertSame((int) $winner->id, (int) $resolved[0]['inventory']->id);
        $this->assertSame(Resolver::RULE_GLOBAL_FALLBACK, $resolved[0]['rule']);
        $this->assertTrue($resolved[0]['fallback_used']);
    }

    public function test_price_authority_comes_from_inventory_not_editorial_slot(): void
    {
        $inventory = $this->seedInventory('ISB-DXB', 'Air Arabia', 89000, 'JP-PRICE');

        $resolved = $this->resolve([[
            'id' => 'slot-1',
            'from' => 'ISB',
            'to' => 'DXB',
            'airline' => 'Air Arabia',
            'price' => 1,
            'enabled' => '1',
        ]]);

        $this->assertCount(1, $resolved);
        $this->assertSame((int) $inventory->id, (int) $resolved[0]['inventory']->id);
        $this->assertSame(89000, (int) $resolved[0]['inventory']->price);
        $this->assertNotSame(1, (int) $resolved[0]['inventory']->price);
    }

    /**
     * @param  list<array<string, mixed>>  $slots
     * @return list<array{inventory: GroupInventory, editorial: array<string, mixed>, rule: string}>
     */
    private function resolve(array $slots): array
    {
        return app(FeaturedDealInventoryResolver::class)->resolve($slots);
    }

    private function seedInventory(string $sector, string $airline, int $price, string $publicId): GroupInventory
    {
        return $this->seedInventoryOnDate($sector, $airline, $price, $publicId, now()->addDays(14)->toDateString());
    }

    private function seedInventoryOnDate(string $sector, string $airline, int $price, string $publicId, string $departureDate): GroupInventory
    {
        return GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => $publicId,
            'public_id' => $publicId,
            'title' => $sector.' group',
            'sector' => $sector,
            'airline_name' => $airline,
            'departure_date' => $departureDate,
            'total_seats' => 20,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => $price,
            'currency' => 'PKR',
            'is_active' => true,
        ]);
    }
}
