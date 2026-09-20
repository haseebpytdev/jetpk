<?php

namespace Tests\Feature\GroupTicketing;

use App\Models\GroupCategory;
use App\Models\GroupInventory;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupSearchFacetsContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_facets_returns_airlines_sectors_categories_and_tiles(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $category = GroupCategory::query()->create([
            'slug' => 'ksa',
            'name' => 'KSA',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'facet-1',
            'public_id' => 'ALH-FACET-1',
            'group_category_id' => $category->id,
            'title' => 'Facet Test',
            'sector' => 'LHE-JED',
            'airline_name' => 'PIA',
            'departure_date' => '2026-08-15',
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 150000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $response = $this->getJson(route('group-ticketing.search.facets'))
            ->assertOk()
            ->assertJsonPath('airlines.0.value', 'PIA')
            ->assertJsonPath('airlines.0.label', 'PIA')
            ->assertJsonPath('sectors.0.value', 'LHE-JED')
            ->assertJsonPath('sectors.0.label', 'LHE-JED')
            ->assertJsonPath('categories.0.value', 'ksa')
            ->assertJsonPath('date_bounds.minimum', '2026-08-15')
            ->assertJsonPath('date_bounds.maximum', '2026-08-15');

        $tiles = $response->json('tiles');
        $this->assertIsArray($tiles);
        $this->assertNotEmpty($tiles);
        $this->assertSame('all', $tiles[0]['key'] ?? null);
        $this->assertSame('All Groups', $tiles[0]['title'] ?? null);
        $this->assertArrayHasKey('image_url', $tiles[0]);
        $this->assertArrayHasKey('package_count', $tiles[0]);
        $this->assertArrayHasKey('url', $tiles[0]);
    }

    public function test_search_facets_excludes_unavailable_inventory(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'sold-out',
            'public_id' => 'ALH-SOLD',
            'title' => 'Sold Out',
            'sector' => 'LHE-DXB',
            'airline_name' => 'Airblue',
            'departure_date' => '2026-09-01',
            'total_seats' => 5,
            'held_seats' => 5,
            'sold_seats' => 0,
            'price' => 100000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'active-1',
            'public_id' => 'ALH-ACTIVE',
            'title' => 'Active',
            'sector' => 'LHE-RUH',
            'airline_name' => 'PIA',
            'departure_date' => '2026-10-01',
            'total_seats' => 5,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 100000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $response = $this->getJson(route('group-ticketing.search.facets'))->assertOk()->json();

        $sectorValues = array_column($response['sectors'] ?? [], 'value');
        $airlineValues = array_column($response['airlines'] ?? [], 'value');
        $this->assertContains('LHE-RUH', $sectorValues);
        $this->assertNotContains('LHE-DXB', $sectorValues);
        $this->assertContains('PIA', $airlineValues);
        $this->assertNotContains('Airblue', $airlineValues);
    }

    public function test_search_facets_empty_when_no_active_inventory(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $this->getJson(route('group-ticketing.search.facets'))
            ->assertOk()
            ->assertJsonPath('airlines', [])
            ->assertJsonPath('sectors', [])
            ->assertJsonPath('categories', [])
            ->assertJsonPath('tiles', [])
            ->assertJsonPath('date_bounds', null);
    }
}
