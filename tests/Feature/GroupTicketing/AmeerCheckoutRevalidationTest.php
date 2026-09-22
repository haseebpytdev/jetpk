<?php

namespace Tests\Feature\GroupTicketing;

use App\Models\GroupInventory;
use App\Services\GroupTicketing\GroupInventoryAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmeerCheckoutRevalidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        Config::set('suppliers.ameer_e_millat.enabled', true);
        Config::set('suppliers.ameer_e_millat.token', 'revalidate-token');
        Config::set('suppliers.ameer_e_millat.default_base_url', 'https://ameer.test');
        Config::set('suppliers.ameer_e_millat.group_detail_path', '/api/group/detail/{id}');
        Config::set('suppliers.ameer_e_millat.seats_path', '/api/available/seats/{id}');
        Config::set('suppliers.ameer_e_millat.airlines_path', '/api/available/airlines');
        Config::set('ota.group_ticketing.require_live_provider_for_reservation', false);
        Config::set('ota_client.modules.ameer_e_millat_group_ticketing', true);
    }

    public function test_revalidation_refreshes_inventory_from_live_detail_and_seats(): void
    {
        $inventory = GroupInventory::query()->create([
            'supplier' => 'ameer_e_millat',
            'supplier_package_id' => '701',
            'public_id' => 'AEM-701',
            'title' => 'Stale title',
            'sector' => 'SKT-SHJ',
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 90000,
            'currency' => 'PKR',
            'is_active' => true,
            'synced_at' => now()->subDay(),
        ]);

        Http::fake([
            'ameer.test/api/available/airlines' => Http::response(['airlines' => []], 200),
            'ameer.test/api/group/detail/701' => Http::response([
                'group' => [
                    'id' => '701',
                    'sector' => 'SKT-SHJ',
                    'dept_date' => '2026-08-01',
                    'price' => 95000,
                    'available_no_of_pax' => 2,
                ],
            ], 200),
            'ameer.test/api/available/seats/701' => Http::response(['seats' => 2], 200),
        ]);

        $result = app(GroupInventoryAvailabilityService::class)->revalidate($inventory, 2);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['provider_confirmed']);
        $this->assertSame(2, $result['available_seats']);
        $this->assertSame(95000.0, (float) $result['inventory']->price);
    }

    public function test_revalidation_blocks_when_live_package_missing(): void
    {
        Config::set('ota.group_ticketing.require_live_provider_for_reservation', true);
        Config::set('ota.group_ticketing.block_booking_when_provider_unavailable', true);

        $inventory = GroupInventory::query()->create([
            'supplier' => 'ameer_e_millat',
            'supplier_package_id' => '702',
            'public_id' => 'AEM-702',
            'title' => 'Missing package',
            'total_seats' => 5,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 90000,
            'currency' => 'PKR',
            'is_active' => true,
            'synced_at' => now(),
        ]);

        Http::fake([
            'ameer.test/api/available/airlines' => Http::response(['airlines' => []], 200),
            'ameer.test/api/group/detail/702' => Http::response(['error' => true, 'message' => 'Not found'], 404),
        ]);

        $result = app(GroupInventoryAvailabilityService::class)->revalidate($inventory, 1);

        $this->assertFalse($result['ok']);
        $this->assertTrue($result['unavailable']);
    }
}
