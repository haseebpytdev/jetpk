<?php

namespace Tests\Feature\GroupTicketing;

use App\Models\GroupInventory;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchResultsUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ota.group_ticketing.realtime_search_enabled' => false,
            'ota.group_ticketing.inventory_search_sync_enabled' => false,
            'ota.group_ticketing.require_live_provider_for_public_results' => false,
        ]);
    }

    private function seedInventory(int $count = 20): void
    {
        for ($i = 1; $i <= $count; $i++) {
            GroupInventory::query()->create([
                'supplier' => 'alhaider',
                'supplier_package_id' => 'pkg-'.$i,
                'public_id' => 'ALH-PKG-'.$i,
                'title' => 'Group '.$i,
                'sector' => 'SKT-SHJ',
                'airline_name' => 'AIR ARABIA',
                'departure_date' => '2026-06-'.str_pad((string) min(28, $i), 2, '0', STR_PAD_LEFT),
                'baggage' => '20+10',
                'total_seats' => 10,
                'held_seats' => 0,
                'sold_seats' => 0,
                'price' => 90000 + ($i * 1000),
                'currency' => 'PKR',
                'is_active' => true,
            ]);
        }
    }

    public function test_search_page_renders_top_group_search_box(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->seedInventory(1);

        $this->get(route('group-ticketing.search'))
            ->assertOk()
            ->assertSee('ota-hero-group-search-form', false)
            ->assertSee('Search Groups', false);
    }

    public function test_search_shows_fifteen_results_initially(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->seedInventory(20);

        $response = $this->get(route('group-ticketing.search'));
        $response->assertOk();
        $this->assertSame(15, substr_count($response->getContent(), 'data-testid="group-result-row"'));
        $response->assertSee('Showing 15 of 20 group departures', false);
        $response->assertSee('data-testid="group-load-more"', false);
        $response->assertDontSee('gt-pagination-fallback', false);
    }

    public function test_load_more_endpoint_returns_next_page(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->seedInventory(20);

        $this->getJson(route('group-ticketing.search.results', ['page' => 2]))
            ->assertOk()
            ->assertJsonPath('page', 2)
            ->assertJsonPath('has_more', false)
            ->assertJsonStructure(['html', 'total', 'count_label']);
    }

    public function test_ajax_sort_by_price(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->seedInventory(3);

        $json = $this->getJson(route('group-ticketing.search.results', ['sort' => 'price', 'page' => 1]))
            ->assertOk()
            ->json();

        $this->assertStringContainsString('group-result-row', (string) ($json['html'] ?? ''));
    }

    public function test_result_row_uses_compact_layout_and_route(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'row-1',
            'public_id' => 'ALH-ROW-1',
            'title' => 'UAE — SKT-SHJ',
            'sector' => 'SKT-SHJ',
            'airline_name' => 'AIR ARABIA',
            'departure_date' => '2026-06-21',
            'baggage' => '20+10',
            'total_seats' => 2,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 99000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $this->get(route('group-ticketing.search'))
            ->assertOk()
            ->assertSee('data-testid="group-result-row"', false)
            ->assertSee('ota-group-result-row', false)
            ->assertSee('Sialkot (SKT)', false)
            ->assertSee('Sharjah (SHJ)', false)
            ->assertSee('Baggage: Checked 20kg · Cabin 10kg', false)
            ->assertSee('Sector: SKT-SHJ', false)
            ->assertSee('2 seats left', false);
    }
}
