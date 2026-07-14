<?php

namespace Tests\Feature\GroupTicketing;

use App\Models\GroupInventory;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicGroupResultCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_header_hides_group_ticketing_nav_link(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertIsString($html);

        preg_match('/data-testid="public-nav-desktop"[^>]*>(.*?)<\/div>\s*<div class="ota-nav-actions"/s', $html, $desktop);
        $desktopNav = $desktop[1] ?? '';
        $this->assertStringNotContainsString('>Group Ticketing<', $desktopNav);

        preg_match('/data-testid="public-nav-mobile"[^>]*>(.*?)<\/nav>/s', $html, $mobile);
        $mobileNav = $mobile[1] ?? '';
        $this->assertStringNotContainsString('>Group Ticketing<', $mobileNav);
    }

    public function test_search_results_show_premium_card_with_book_now(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'card-1',
            'public_id' => 'ALH-CARD-1',
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
            ->assertSee('Sialkot (SKT)', false)
            ->assertSee('Sharjah (SHJ)', false)
            ->assertSee('ota-group-result-row__route-icon', false)
            ->assertSee('Sector: SKT-SHJ', false)
            ->assertSee('Checked 20kg', false)
            ->assertSee('Cabin 10kg', false)
            ->assertSee('PKR 99,000', false)
            ->assertSee('Book now', false)
            ->assertDontSee('→', false);
    }
}
