<?php

namespace Tests\Unit\GroupTicketing;

use App\Models\GroupInventory;
use App\Services\GroupTicketing\GroupInventorySearchService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupInventorySearchTravelDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_travel_date_prefers_exact_match_over_nearby(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'exact-1',
            'public_id' => 'ALH-EXACT',
            'title' => 'Exact',
            'sector' => 'LHE-DXB',
            'airline_name' => 'Emirates',
            'departure_date' => '2026-09-10',
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 100000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'near-1',
            'public_id' => 'ALH-NEAR',
            'title' => 'Nearby',
            'sector' => 'LHE-DXB',
            'airline_name' => 'Emirates',
            'departure_date' => '2026-09-12',
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 100000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $results = app(GroupInventorySearchService::class)->search(['date_from' => '2026-09-10']);

        $this->assertCount(1, $results);
        $this->assertSame('ALH-EXACT', $results->first()->public_id);
    }

    public function test_travel_date_falls_back_to_plus_minus_three_days(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'near-only',
            'public_id' => 'ALH-NEAR-ONLY',
            'title' => 'Nearby only',
            'sector' => 'ISB-DXB',
            'airline_name' => 'Flydubai',
            'departure_date' => '2026-10-05',
            'total_seats' => 8,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 90000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'far',
            'public_id' => 'ALH-FAR',
            'title' => 'Far',
            'sector' => 'ISB-DXB',
            'airline_name' => 'Flydubai',
            'departure_date' => '2026-10-20',
            'total_seats' => 8,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 90000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $results = app(GroupInventorySearchService::class)->search(['date_from' => '2026-10-03']);

        $this->assertCount(1, $results);
        $this->assertSame('ALH-NEAR-ONLY', $results->first()->public_id);
    }

    public function test_airline_only_filter_works_without_sector(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'air-1',
            'public_id' => 'ALH-AIR',
            'title' => 'Airline only',
            'sector' => 'PES-MCT',
            'airline_name' => 'Pakistan International Airlines',
            'departure_date' => '2026-11-01',
            'total_seats' => 12,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 80000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $results = app(GroupInventorySearchService::class)->search([
            'airline' => 'Pakistan International Airlines',
        ]);

        $this->assertCount(1, $results);
        $this->assertSame('ALH-AIR', $results->first()->public_id);
    }
}
