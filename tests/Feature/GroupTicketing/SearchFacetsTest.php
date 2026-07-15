<?php

namespace Tests\Feature\GroupTicketing;

use App\Models\GroupCategory;
use App\Models\GroupInventory;
use App\Services\GroupTicketing\GroupInventoryFacetService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchFacetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_dropdowns_use_database_inventory_only(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $category = GroupCategory::query()->create([
            'slug' => 'umrah',
            'name' => 'Umrah',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => '99',
            'public_id' => 'ALH-99',
            'group_category_id' => $category->id,
            'title' => 'Test Umrah Group',
            'sector' => 'LHE-JED',
            'airline_id' => 7,
            'airline_name' => 'Saudi Airlines',
            'departure_date' => '2026-07-01',
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 150000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $this->get('/groups/search')
            ->assertOk()
            ->assertSee('LHE-JED', false)
            ->assertSee('Saudi Airlines', false)
            ->assertSee('data-testid="group-result-row"', false)
            ->assertSee('value="umrah"', false);
    }

    public function test_airline_facets_use_distinct_airline_name_without_airline_id(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'flynas-1',
            'public_id' => 'ALH-FN-1',
            'title' => 'Flynas Group',
            'sector' => 'LHE-RUH',
            'airline_id' => null,
            'airline_name' => 'FLYNAS',
            'departure_date' => '2026-08-01',
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 120000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $this->get('/groups/search')
            ->assertOk()
            ->assertSee('value="FLYNAS"', false);

        $this->getJson('/groups/facets')
            ->assertOk()
            ->assertJsonFragment(['name' => 'FLYNAS']);
    }

    public function test_categories_exclude_empty_inventory(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $withInventory = GroupCategory::query()->create([
            'slug' => 'uae',
            'name' => 'UAE',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        GroupCategory::query()->create([
            'slug' => 'featured',
            'name' => 'Featured',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'uae-1',
            'public_id' => 'ALH-UAE-1',
            'group_category_id' => $withInventory->id,
            'title' => 'UAE Group',
            'sector' => 'LHE-DXB',
            'airline_name' => 'AIR ARABIA',
            'departure_date' => '2026-09-01',
            'total_seats' => 8,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 100000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $facets = $this->getJson('/groups/facets')->assertOk()->json();

        $slugs = array_column($facets['categories'] ?? [], 'slug');
        $this->assertContains('uae', $slugs);
        $this->assertNotContains('featured', $slugs);
    }

    public function test_airline_filter_returns_matching_inventory(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'flynas-1',
            'public_id' => 'ALH-FN-1',
            'title' => 'Flynas Group',
            'sector' => 'LHE-RUH',
            'airline_name' => 'FLYNAS',
            'departure_date' => '2026-08-01',
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 120000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'sial-1',
            'public_id' => 'ALH-SIAL-1',
            'title' => 'Air Sial Group',
            'sector' => 'LHE-MCT',
            'airline_name' => 'AIR SIAL',
            'departure_date' => '2026-08-15',
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 110000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $this->get('/groups/search?airline=FLYNAS')
            ->assertOk()
            ->assertSee('Showing 1 of 1 group departure', false)
            ->assertSee('FLYNAS', false)
            ->assertSee('LHE-RUH', false);
    }

    public function test_facets_endpoint_matches_homepage_facet_logic(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $category = GroupCategory::query()->create([
            'slug' => 'ksa-oneway',
            'name' => 'KSA ONEWAY',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'ksa-1',
            'public_id' => 'ALH-KSA-1',
            'group_category_id' => $category->id,
            'title' => 'KSA Group',
            'sector' => 'LHE-RUH',
            'airline_name' => 'FLYNAS',
            'departure_date' => '2026-07-01',
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 150000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $apiFacets = $this->getJson('/groups/facets')->assertOk()->json();
        $serviceFacets = app(GroupInventoryFacetService::class)->all();

        $this->assertSame($serviceFacets, $apiFacets);
    }

    public function test_homepage_groups_form_excludes_category_and_flexible(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'hero-1',
            'public_id' => 'ALH-HERO-1',
            'title' => 'Hero Group Package',
            'sector' => 'LHE-JED',
            'airline_name' => 'FLYNAS',
            'departure_date' => '2026-07-01',
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 150000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('ota-hero-group-search-form', false)
            ->assertSee('name="date_from"', false)
            ->assertSee('name="date_to"', false)
            ->assertSee('Search Groups', false)
            ->assertDontSee('group-category', false)
            ->assertDontSee('name="flexible"', false)
            ->assertDontSee('name="dept_date"', false);
    }

    public function test_date_range_filter_returns_matching_inventory(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'july-1',
            'public_id' => 'ALH-JUL-1',
            'title' => 'July Group',
            'sector' => 'LHE-JED',
            'airline_name' => 'FLYNAS',
            'departure_date' => '2026-07-15',
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 150000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'aug-1',
            'public_id' => 'ALH-AUG-1',
            'title' => 'August Group',
            'sector' => 'LHE-JED',
            'airline_name' => 'FLYNAS',
            'departure_date' => '2026-08-15',
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 150000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $this->get('/groups/search?date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk()
            ->assertSee('15 Jul 2026', false)
            ->assertSee('FLYNAS', false)
            ->assertDontSee('15 Aug 2026', false);
    }
}
