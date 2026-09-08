<?php

namespace Tests\Feature\Homepage;

use App\Models\GroupInventory;
use App\Services\PublicContent\HomepagePublicContentPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class FeaturedDealHomepageAuthorityTest extends TestCase
{
    use JetpkHomepageFixture;
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

    public function test_public_api_emits_resolved_deal_from_cms_target(): void
    {
        $profile = $this->makeJetpkProfile();
        $inventory = $this->seedInventory('ISB-DXB', 'Air Arabia', 89000, 'JP-HOME-AUTH');

        $this->seedPublishedHome($profile, [
            'featured_deals' => [
                'enabled' => '1',
                'items' => [[
                    'id' => 'cms-slot-1',
                    'from' => 'ISB',
                    'to' => 'DXB',
                    'airline' => 'Air Arabia',
                    'price' => 1,
                    'enabled' => '1',
                ]],
            ],
        ]);

        $payload = app(HomepagePublicContentPresenter::class)->present();
        $deal = $payload['featured_deals']['items'][0] ?? null;

        $this->assertIsArray($deal);
        $this->assertSame('ISB', $deal['from']);
        $this->assertSame('DXB', $deal['to']);
        $this->assertSame(89000, $deal['price']);
        $this->assertSame((int) $inventory->id, $deal['inventory_id']);
        $this->assertSame('JP-HOME-AUTH', $deal['public_id']);
    }

    public function test_href_points_to_next_groups_public_id(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedInventory('ISB-DXB', 'Air Arabia', 89000, 'JP-HOME-HREF');

        $this->seedPublishedHome($profile, [
            'featured_deals' => [
                'enabled' => '1',
                'items' => [[
                    'id' => 'cms-slot-1',
                    'from' => 'ISB',
                    'to' => 'DXB',
                    'enabled' => '1',
                ]],
            ],
        ]);

        $payload = app(HomepagePublicContentPresenter::class)->present();
        $href = (string) ($payload['featured_deals']['items'][0]['href'] ?? '');

        $this->assertSame('/groups/JP-HOME-HREF', $href);
        $this->assertStringNotContainsString('/groups/package/', $href);
    }

    public function test_unresolved_slot_suppresses_featured_section_when_empty(): void
    {
        $profile = $this->makeJetpkProfile();

        $this->seedPublishedHome($profile, [
            'featured_deals' => [
                'enabled' => '1',
                'items' => [[
                    'id' => 'cms-slot-1',
                    'from' => 'AAA',
                    'to' => 'BBB',
                    'enabled' => '1',
                ]],
            ],
        ]);

        $payload = app(HomepagePublicContentPresenter::class)->present();

        $this->assertFalse($payload['featured_deals']['enabled']);
        $this->assertSame([], $payload['featured_deals']['items']);
    }

    private function seedInventory(string $sector, string $airline, int $price, string $publicId): GroupInventory
    {
        return GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => $publicId,
            'public_id' => $publicId,
            'title' => $sector.' group',
            'sector' => $sector,
            'airline_name' => $airline,
            'departure_date' => now()->addDays(14)->toDateString(),
            'total_seats' => 20,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => $price,
            'currency' => 'PKR',
            'is_active' => true,
        ]);
    }
}
