<?php

namespace Tests\Feature;

use App\Models\GroupInventory;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UmrahGroupRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ota.group_ticketing.inventory_search_sync_enabled' => false,
            'ota.group_ticketing.realtime_search_enabled' => false,
            'ota.group_ticketing.require_live_provider_for_public_results' => false,
            'ota.group_ticketing.require_live_provider_for_reservation' => false,
            'suppliers.al_haider.enabled' => false,
        ]);
    }

    public function test_umrah_groups_index_redirects_to_group_search(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $this->get('/umrah-groups')
            ->assertRedirect(route('group-ticketing.search'));
    }

    public function test_group_search_renders_inventory_from_database(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => '42',
            'public_id' => 'ALH-42',
            'title' => 'Umrah Group — LHE-JED',
            'sector' => 'LHE-JED',
            'departure_date' => now()->addDays(45)->toDateString(),
            'total_seats' => 12,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 185000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $this->get('/groups/search')
            ->assertOk()
            ->assertSee('LHE-JED', false)
            ->assertSee('185,000', false);
    }

    public function test_umrah_groups_show_redirects_to_group_package_page(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => '1',
            'public_id' => 'ALH-1',
            'title' => 'Fixture Umrah Package',
            'sector' => 'LHE-JED',
            'departure_date' => now()->addDays(50)->toDateString(),
            'total_seats' => 8,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 200000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $this->get('/umrah-groups/ALH-1')
            ->assertRedirect(route('group-ticketing.show', 'ALH-1'));

        $this->get('/groups/package/ALH-1')
            ->assertOk()
            ->assertSee('Fixture Umrah Package', false)
            ->assertSee('Book now', false);
    }

    public function test_group_inventory_model_resolves_public_id_without_route_bind(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $inventory = GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => '3278',
            'public_id' => 'ALH-3278',
            'title' => 'Binding Fixture',
            'sector' => 'ISB-DXB',
            'departure_date' => now()->addDays(10)->toDateString(),
            'total_seats' => 4,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 70000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $byPublicId = (new GroupInventory)->resolveRouteBinding('ALH-3278');
        $bySupplierId = (new GroupInventory)->resolveRouteBinding('3278');
        $byPrimaryKey = (new GroupInventory)->resolveRouteBinding((string) $inventory->id);

        $this->assertNotNull($byPublicId);
        $this->assertTrue($inventory->is($byPublicId));
        $this->assertTrue($inventory->is($bySupplierId));
        $this->assertTrue($inventory->is($byPrimaryKey));
        $this->assertSame('ALH-3278', $inventory->getRouteKey());
    }
}
