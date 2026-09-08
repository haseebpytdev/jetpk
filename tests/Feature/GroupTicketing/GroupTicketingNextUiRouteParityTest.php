<?php

namespace Tests\Feature\GroupTicketing;

use App\Models\GroupInventory;
use App\Support\GroupTicketing\GroupTicketingNextFrontend;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupTicketingNextUiRouteParityTest extends TestCase
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

    public function test_groups_search_html_requests_next_frontend_proxy(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $response = $this->get('/groups/search');

        $response->assertOk();
        $this->assertTrue($response->headers->has(GroupTicketingNextFrontend::HEADER));
    }

    public function test_groups_package_html_redirects_to_next_detail_path(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'parity-1',
            'public_id' => 'ALH-PARITY-1',
            'title' => 'Parity Package',
            'sector' => 'ISB-DXB',
            'departure_date' => now()->addDays(20)->toDateString(),
            'total_seats' => 8,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 99000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $this->get('/groups/package/ALH-PARITY-1')
            ->assertRedirect('/groups/ALH-PARITY-1');
    }

    public function test_groups_search_json_endpoints_remain_laravel(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $this->getJson('/groups/search/data')
            ->assertOk()
            ->assertJsonStructure(['filters', 'facets', 'cards', 'total']);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'parity-json',
            'public_id' => 'ALH-PARITY-JSON',
            'title' => 'JSON Package',
            'sector' => 'ISB-DXB',
            'departure_date' => now()->addDays(20)->toDateString(),
            'total_seats' => 8,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 99000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $this->getJson('/groups/package/ALH-PARITY-JSON?format=json')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['package', 'available']);
    }
}
