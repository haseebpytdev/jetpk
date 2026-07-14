<?php

namespace Tests\Feature;

use App\Models\GroupInventory;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UmrahGroupRoutesTest extends TestCase
{
    use RefreshDatabase;

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
            'departure_date' => '2026-03-01',
            'total_seats' => 12,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 185000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $this->get('/groups/search')
            ->assertOk()
            ->assertSee('Umrah Group — LHE-JED', false)
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
            ->assertSee('Sign in to book', false);
    }
}
