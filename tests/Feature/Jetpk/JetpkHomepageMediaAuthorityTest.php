<?php

namespace Tests\Feature\Jetpk;

use App\Services\Homepage\JetpkHomepageMediaAuthorityBackfill;
use App\Services\PublicContent\HomepagePublicContentPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkHomepageMediaAuthorityTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seedJetpkAirports();
    }

    public function test_media_authority_backfill_emits_cms_images_for_all_four_routes_and_deals(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, $this->productionLikeHomepageContent());

        app(JetpkHomepageMediaAuthorityBackfill::class)->runForProfile($profile);

        $response = $this->getJson('/api/public/content/homepage');
        $response->assertOk();

        $routes = collect($response->json('routes.items'));
        $this->assertRouteCmsImage($routes->firstWhere('to', 'DXB'), '/images/home/destination-dubai.jpg');
        $this->assertRouteCmsImage($routes->firstWhere('to', 'JED'), '/images/home/destination-jeddah.jpg');
        $this->assertRouteCmsImage($routes->firstWhere('to', 'LHR'), '/images/home/destination-london.jpg');
        $this->assertRouteCmsImage($routes->firstWhere('to', 'RUH'), 'route_seed_khi_ruh-20260913115311.png');

        $deals = collect($response->json('featured_deals.items'));
        $this->assertDealCmsImage(
            $deals->firstWhere('id', '2b3881220c1477fe53deab40b3d0170a'),
            '/images/home/offer-gcc.jpg',
        );
        $this->assertDealCmsImage(
            $deals->firstWhere('id', '9ae5ad6bca0bf3481c79635c582d4531'),
            '/images/home/offer-uk.jpg',
        );
        $this->assertDealCmsImage(
            $deals->firstWhere('id', 'aa6546f91e6984f9ae3ad93929189a6b'),
            '/images/home/offer-domestic.jpg',
        );

        $this->assertSame(0, $routes->whereNull('image')->count());
        $this->assertSame(0, $deals->whereNull('image')->count());
    }

    public function test_featured_deal_images_remain_attached_after_reorder(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->productionLikeHomepageContent();
        $this->seedPublishedHome($profile, $content);
        app(JetpkHomepageMediaAuthorityBackfill::class)->runForProfile($profile);

        $reordered = $content;
        $reordered['featured_deals']['items'] = array_reverse($content['featured_deals']['items']);
        foreach ($reordered['featured_deals']['items'] as $index => &$item) {
            $item['sort_order'] = $index;
        }
        unset($item);
        $this->seedPublishedHome($profile, $reordered);

        $presented = app(HomepagePublicContentPresenter::class)->present();
        $dealA = collect($presented['featured_deals']['items'])->firstWhere('id', '2b3881220c1477fe53deab40b3d0170a');
        $dealB = collect($presented['featured_deals']['items'])->firstWhere('id', '9ae5ad6bca0bf3481c79635c582d4531');

        $this->assertStringContainsString('offer-gcc.jpg', (string) $dealA['image']);
        $this->assertStringContainsString('offer-uk.jpg', (string) $dealB['image']);
    }

    public function test_unconfigured_deal_without_backfill_binding_has_null_image(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->productionLikeHomepageContent();
        $content['featured_deals']['items'][] = [
            'id' => 'unconfigured-deal',
            'airline' => 'Test',
            'from' => 'KHI',
            'to' => 'DXB',
            'enabled' => '1',
            'sort_order' => 3,
        ];
        $this->seedPublishedHome($profile, $content);
        app(JetpkHomepageMediaAuthorityBackfill::class)->runForProfile($profile);

        $deal = collect(app(HomepagePublicContentPresenter::class)->present()['featured_deals']['items'])
            ->firstWhere('id', 'unconfigured-deal');

        $this->assertNull($deal['image']);
    }

    public function test_backfill_does_not_overwrite_admin_selected_resolving_asset(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->productionLikeHomepageContent();
        $customKey = 'custom_route_admin_pick';
        $content['routes']['items'][0]['image_asset_key'] = $customKey;
        $this->seedPublishedHome($profile, $content);

        \App\Models\ClientPageAsset::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => 'home',
            'asset_key' => $customKey,
            'disk' => 'public',
            'path' => 'missing/on-disk.jpg',
            'public_url' => 'https://jetpakistan.pk/storage/custom/admin-route.jpg',
        ]);

        app(JetpkHomepageMediaAuthorityBackfill::class)->runForProfile($profile);

        $route = collect($this->getJson('/api/public/content/homepage')->json('routes.items'))
            ->firstWhere('to', 'DXB');

        $this->assertStringContainsString('admin-route.jpg', (string) $route['image']);
    }

    /**
     * @param  array<string, mixed>|null  $route
     */
    private function assertRouteCmsImage(?array $route, string $expectedFragment): void
    {
        $this->assertNotNull($route);
        $this->assertNotNull($route['image']);
        $this->assertStringContainsString($expectedFragment, (string) $route['image']);
    }

    /**
     * @param  array<string, mixed>|null  $deal
     */
    private function assertDealCmsImage(?array $deal, string $expectedFragment): void
    {
        $this->assertNotNull($deal);
        $this->assertNotNull($deal['image']);
        $this->assertStringContainsString($expectedFragment, (string) $deal['image']);
    }
}
