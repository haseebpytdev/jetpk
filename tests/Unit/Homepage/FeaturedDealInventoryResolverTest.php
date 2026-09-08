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
        return GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => $publicId,
            'public_id' => $publicId,
            'title' => $sector.' group',
            'sector' => $sector,
            'airline_name' => $airline,
            'departure_date' => now()->addDays(14)->toDateString(),
            'total_seats' => 20,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => $price,
            'currency' => 'PKR',
            'is_active' => true,
        ]);
    }
}
