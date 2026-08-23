<?php

namespace Tests\Feature\Client;

use App\Models\ClientPageAsset;
use App\Services\Homepage\JetpkHomepageAssetService;
use App\Services\PublicContent\HomepagePublicContentPresenter;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\JetpkHomepageSectionData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

/**
 * JP-ADMIN-CMS-03 local CMS text/media round-trip gates (no production mutation).
 */
class JpAdminCms03HomepageRoundtripTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['client_route_parity.enabled' => false]);
        Storage::fake('public');
        $this->seedJetpkAirports();
        $this->seedJetpkAgency();
        $this->seedCmsTestUsers(1, 5, 7, 42, 111, 222);
    }

    public function test_draft_isolation_preview_and_publish_parity_for_hero_headline(): void
    {
        $profile = $this->makeJetpkProfile();
        $baseline = 'BASELINE_HERO_CMS03';
        $draftMarker = 'UAT_CMS03_DRAFT_HERO';
        $this->seedPublishedHome($profile, ['hero' => ['headline' => $baseline]]);

        $resolver = app(\App\Services\Client\ClientPageContentResolver::class);
        $resolver->saveDraft($profile, ClientPageKeys::HOME, ['hero' => ['headline' => $draftMarker]], 1);

        $published = $resolver->contentFor(ClientPageKeys::HOME);
        $this->assertSame($baseline, data_get($published, 'hero.headline'));

        $resolver->publish($profile, ClientPageKeys::HOME, 1);
        $after = $resolver->contentFor(ClientPageKeys::HOME);
        $this->assertSame($draftMarker, data_get($after, 'hero.headline'));

        $api = app(HomepagePublicContentPresenter::class)->present();
        $this->assertSame('cms', $api['source']);
        $this->assertSame($draftMarker, $api['hero']['headline']);

        // Restore baseline
        $resolver->saveDraft($profile, ClientPageKeys::HOME, ['hero' => ['headline' => $baseline]], 1);
        $resolver->publish($profile, ClientPageKeys::HOME, 1);
        $restored = app(HomepagePublicContentPresenter::class)->present();
        $this->assertSame($baseline, $restored['hero']['headline']);
    }

    public function test_featured_deal_cms_media_appears_on_public_api_and_restores(): void
    {
        $profile = $this->makeJetpkProfile();
        $dealId = 'deal-cms03-1';
        $this->seedPublishedHome($profile, [
            'featured_deals' => [
                'enabled' => '1',
                'eyebrow' => 'Editorial picks',
                'title' => 'Featured deals',
                'subtitle' => 'Samples',
                'items' => [[
                    'id' => $dealId,
                    'airline' => 'PK',
                    'from' => 'LHE',
                    'to' => 'DXB',
                    'price' => 45000,
                    'title' => 'LHE-DXB sample',
                    'badge' => 'CMS03',
                    'description' => 'Roundtrip media gate',
                    'enabled' => '1',
                    'sort_order' => 1,
                    'image_alt' => 'Synthetic CMS03 deal image',
                    'image_asset_key' => JetpkHomepageAssetService::featuredDealAssetKey($dealId),
                ]],
            ],
        ]);

        $file = UploadedFile::fake()->image('cms03-deal.png', 320, 200);
        $asset = app(JetpkHomepageAssetService::class)->storeFeaturedDealImage(
            $profile,
            $dealId,
            $file,
            1,
            'Synthetic CMS03 deal image',
        );

        $this->assertInstanceOf(ClientPageAsset::class, $asset);
        $this->assertSame(JetpkHomepageAssetService::featuredDealAssetKey($dealId), $asset->asset_key);

        // Profile already bound via makeJetpkProfile() → CurrentClientContext.
        $payload = app(HomepagePublicContentPresenter::class)->present();
        $items = $payload['featured_deals']['items'] ?? [];
        $this->assertNotEmpty($items);
        $first = $items[0];
        $this->assertNotEmpty($first['image'] ?? null);
        $this->assertSame('cms', $first['media_source'] ?? null);
        $this->assertNotEmpty($first['image_alt'] ?? null);

        app(JetpkHomepageAssetService::class)->destroyAsset($asset->fresh() ?? $asset);
    }
}
