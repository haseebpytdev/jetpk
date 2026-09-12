<?php

namespace Tests\Feature\Homepage;

use App\Enums\AccountType;
use App\Models\GroupInventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeaturedDealInventoryPickerRouteTest extends TestCase
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

    public function test_admin_can_fetch_featured_deal_inventory_json(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'route-test',
            'public_id' => 'JP-ROUTE',
            'title' => 'Route test',
            'sector' => 'ISB-DXB',
            'airline_name' => 'Air Arabia',
            'departure_date' => now()->addDays(10)->toDateString(),
            'total_seats' => 15,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 75000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->getJson('/admin/page-settings/home/featured-deal-inventory');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.public_id', 'JP-ROUTE');
    }

    public function test_guest_cannot_fetch_featured_deal_inventory(): void
    {
        $this->getJson('/admin/page-settings/home/featured-deal-inventory')
            ->assertUnauthorized();
    }
}
