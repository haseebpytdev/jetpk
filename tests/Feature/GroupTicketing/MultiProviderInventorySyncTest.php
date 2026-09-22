<?php

namespace Tests\Feature\GroupTicketing;

use App\Models\GroupInventory;
use App\Services\GroupTicketing\GroupInventorySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MultiProviderInventorySyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        Config::set('suppliers.al_haider.enabled', true);
        Config::set('suppliers.al_haider.token', 'alhaider-token');
        Config::set('suppliers.al_haider.default_base_url', 'https://alhaider.test');
        Config::set('suppliers.al_haider.groups_path', '/api/available/groups');
        Config::set('suppliers.al_haider.airlines_path', '/api/available/airlines');

        Config::set('suppliers.ameer_e_millat.enabled', true);
        Config::set('suppliers.ameer_e_millat.token', 'ameer-token');
        Config::set('suppliers.ameer_e_millat.default_base_url', 'https://ameer.test');
        Config::set('suppliers.ameer_e_millat.groups_path', '/api/available/groups');
        Config::set('suppliers.ameer_e_millat.airlines_path', '/api/available/airlines');

        Config::set('ota_client.modules.al_haider_group_ticketing', true);
        Config::set('ota_client.modules.ameer_e_millat_group_ticketing', true);
    }

    public function test_syncs_both_providers_without_id_collision(): void
    {
        Http::fake([
            'alhaider.test/api/available/airlines' => Http::response(['airlines' => []], 200),
            'alhaider.test/api/available/groups*' => Http::response([
                'groups' => [[
                    'id' => '100',
                    'sector' => 'SKT-SHJ',
                    'dept_date' => '2026-06-21',
                    'price' => 90000,
                    'available_no_of_pax' => 5,
                ]],
            ], 200),
            'ameer.test/api/available/airlines' => Http::response(['airlines' => []], 200),
            'ameer.test/api/available/groups*' => Http::response([
                'groups' => [[
                    'id' => '100',
                    'sector' => 'LHE-DXB',
                    'dept_date' => '2026-07-01',
                    'price' => 110000,
                    'available_no_of_pax' => 6,
                ]],
            ], 200),
        ]);

        $result = app(GroupInventorySyncService::class)->sync();

        $this->assertSame(2, $result['synced']);
        $this->assertCount(2, GroupInventory::query()->where('supplier_package_id', '100')->get());
        $this->assertDatabaseHas('group_inventories', [
            'supplier' => 'alhaider',
            'supplier_package_id' => '100',
            'sector' => 'SKT-SHJ',
        ]);
        $this->assertDatabaseHas('group_inventories', [
            'supplier' => 'ameer_e_millat',
            'supplier_package_id' => '100',
            'sector' => 'LHE-DXB',
        ]);
    }

    public function test_one_provider_failure_does_not_deactivate_other_provider_rows(): void
    {
        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'stale-alh',
            'public_id' => 'ALH-STALE',
            'title' => 'Al-Haider stale',
            'total_seats' => 5,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 50000,
            'currency' => 'PKR',
            'is_active' => true,
            'synced_at' => now()->subDay(),
        ]);

        GroupInventory::query()->create([
            'supplier' => 'ameer_e_millat',
            'supplier_package_id' => 'active-aem',
            'public_id' => 'AEM-ACTIVE',
            'title' => 'Ameer active',
            'total_seats' => 5,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 60000,
            'currency' => 'PKR',
            'is_active' => true,
            'synced_at' => now()->subDay(),
        ]);

        Http::fake([
            'alhaider.test/api/available/airlines' => Http::response(['message' => 'down'], 500),
            'ameer.test/api/available/airlines' => Http::response(['airlines' => []], 200),
            'ameer.test/api/available/groups*' => Http::response([
                'groups' => [[
                    'id' => 'active-aem',
                    'sector' => 'SKT-SHJ',
                    'dept_date' => '2026-06-21',
                    'price' => 60000,
                    'available_no_of_pax' => 4,
                ]],
            ], 200),
        ]);

        $result = app(GroupInventorySyncService::class)->sync();

        $this->assertContains('ameer_e_millat', $result['successful_providers']);
        $this->assertContains('alhaider', $result['failed_providers']);
        $this->assertTrue(GroupInventory::query()->where('supplier', 'alhaider')->where('supplier_package_id', 'stale-alh')->value('is_active'));
        $this->assertTrue(GroupInventory::query()->where('supplier', 'ameer_e_millat')->where('supplier_package_id', 'active-aem')->value('is_active'));
    }
}
