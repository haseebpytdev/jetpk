<?php

namespace Tests\Feature\Jetpk;

use App\Models\ClientPageAsset;
use App\Services\PublicContent\HomepagePublicContentPresenter;
use App\Support\Client\ClientPageKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class HomepagePublicContentResponsiveKeyTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seedJetpkAirports();
        config(['client_route_parity.enabled' => false]);
    }

    public function test_hyphenated_route_seed_khi_dxb_exposes_responsive_sources(): void
    {
        $this->seedRouteWithResponsive(
            itemId: 'seed-khi-dxb',
            assetKey: 'route_seed-khi-dxb',
            to: 'DXB',
        );

        $route = $this->routeFromApi('DXB');
        $this->assertTrue((bool) ($route['responsive']['has_responsive_variants'] ?? false));
        $this->assertNotEmpty($route['responsive']['sources'] ?? []);
        $this->assertStringContainsString('/card/', (string) ($route['responsive']['fallback_url'] ?? ''));
    }

    public function test_hyphenated_route_seed_lhe_jed_exposes_responsive_sources(): void
    {
        $this->seedRouteWithResponsive(
            itemId: 'seed-lhe-jed',
            assetKey: 'route_seed-lhe-jed',
            to: 'JED',
        );

        $route = $this->routeFromApi('JED');
        $this->assertTrue((bool) ($route['responsive']['has_responsive_variants'] ?? false));
        $this->assertNotEmpty($route['responsive']['sources'] ?? []);
    }

    public function test_underscored_route_seed_khi_ruh_exposes_responsive_sources(): void
    {
        $this->seedRouteWithResponsive(
            itemId: 'seed-khi-ruh',
            assetKey: 'route_seed_khi_ruh',
            to: 'RUH',
        );

        $route = $this->routeFromApi('RUH');
        $this->assertTrue((bool) ($route['responsive']['has_responsive_variants'] ?? false));
        $this->assertNotEmpty($route['responsive']['sources'] ?? []);
    }

    public function test_destination_responsive_behavior_unchanged(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->representativeValidFourCardHomeContent();
        $content['destinations']['items'][0]['id'] = 'seed-dxb';
        $content['destinations']['items'][0]['image_asset_key'] = 'destination_seed_dxb';
        $this->seedPublishedHome($profile, $content);

        $path = 'client-assets/jetpk-assets/pages/home/destination_seed_dxb.png';
        $this->putCardAsset(
            $profile->id,
            'destination_seed_dxb',
            $path,
            'https://jetpakistan.pk/storage/'.$path,
        );

        $dest = collect($this->getJson('/api/public/content/homepage')->json('destinations.items'))
            ->firstWhere('code', 'DXB');
        $this->assertNotNull($dest);
        $this->assertTrue((bool) ($dest['responsive']['has_responsive_variants'] ?? false));
    }

    public function test_featured_deal_responsive_behavior_unchanged(): void
    {
        $profile = $this->makeJetpkProfile();
        $dealId = '64fc959f-83a3-489c-85d6-5038164dce38';
        $assetKey = 'featured_deal_64fc959f_83a3_489c_85d6_5038164dce38';
        $content = $this->representativeValidFourCardHomeContent();
        $content['featured_deals'] = [
            'enabled' => '1',
            'items' => [[
                'id' => $dealId,
                'airline' => 'AirBlue',
                'from' => 'LHE',
                'to' => 'IST',
                'enabled' => '1',
                'sort_order' => 0,
                'price' => 85000,
                'image_asset_key' => $assetKey,
            ]],
        ];
        $this->seedPublishedHome($profile, $content);

        $path = 'client-assets/jetpk-assets/pages/home/'.$assetKey.'.png';
        $this->putCardAsset(
            $profile->id,
            $assetKey,
            $path,
            'https://jetpakistan.pk/storage/'.$path,
        );

        $deal = collect($this->getJson('/api/public/content/homepage')->json('featured_deals.items'))
            ->firstWhere('id', $dealId);
        $this->assertNotNull($deal);
        $this->assertTrue((bool) ($deal['responsive']['has_responsive_variants'] ?? false));
    }

    public function test_missing_responsive_metadata_falls_back_to_original(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->representativeValidFourCardHomeContent();
        $content['routes']['items'][0]['id'] = 'seed-khi-dxb';
        $content['routes']['items'][0]['image_asset_key'] = 'route_seed-khi-dxb';
        $this->seedPublishedHome($profile, $content);

        $path = 'client-assets/jetpk-assets/pages/home/route_seed_khi_dxb.png';
        $url = 'https://jetpakistan.pk/storage/'.$path;
        Storage::disk('public')->put($path, 'png-bytes');
        ClientPageAsset::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'asset_key' => 'route_seed-khi-dxb',
            'disk' => 'public',
            'path' => $path,
            'public_url' => $url,
            'meta_json' => [],
        ]);

        $route = $this->routeFromApi('DXB');
        $this->assertNotNull($route['responsive']);
        $this->assertFalse((bool) ($route['responsive']['has_responsive_variants'] ?? true));
        $this->assertStringContainsString('route_seed_khi_dxb.png', (string) $route['responsive']['fallback_url']);
    }

    public function test_source_path_mismatch_does_not_attach_manifest(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->representativeValidFourCardHomeContent();
        $content['routes']['items'][0]['id'] = 'seed-khi-dxb';
        $content['routes']['items'][0]['image_asset_key'] = 'route_seed-khi-dxb';
        $this->seedPublishedHome($profile, $content);

        $path = 'client-assets/jetpk-assets/pages/home/route_seed_khi_dxb.png';
        $url = 'https://jetpakistan.pk/storage/'.$path;
        Storage::disk('public')->put($path, 'source-png');
        $derivPath = 'client-assets/jetpk-assets/pages/home/card/deadbeefdeadbeef/card-320.jpg';
        Storage::disk('public')->put($derivPath, 'jpg');

        ClientPageAsset::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'asset_key' => 'route_seed-khi-dxb',
            'disk' => 'public',
            'path' => $path,
            'public_url' => $url,
            'meta_json' => [
                // Manifest claims a different source — must not be attached/activated.
                'responsive_media' => $this->cardManifest(
                    'client-assets/jetpk-assets/pages/home/unrelated-source.png',
                    $derivPath,
                ),
            ],
        ]);

        $route = $this->routeFromApi('DXB');
        $this->assertNotNull($route['responsive']);
        $this->assertFalse((bool) ($route['responsive']['has_responsive_variants'] ?? true));
        $this->assertStringContainsString('route_seed_khi_dxb.png', (string) $route['responsive']['fallback_url']);
    }

    public function test_present_loads_homepage_assets_once_without_per_card_queries(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->representativeValidFourCardHomeContent();
        foreach ([
            ['id' => 'seed-khi-dxb', 'key' => 'route_seed-khi-dxb', 'to' => 'DXB', 'from' => 'LHE'],
            ['id' => 'seed-lhe-jed', 'key' => 'route_seed-lhe-jed', 'to' => 'JED', 'from' => 'LHE'],
            ['id' => 'seed-isb-lhr', 'key' => 'route_seed_isb_lhr', 'to' => 'LHR', 'from' => 'ISB'],
            ['id' => 'seed-khi-ruh', 'key' => 'route_seed_khi_ruh', 'to' => 'RUH', 'from' => 'KHI'],
        ] as $index => $route) {
            $content['routes']['items'][$index]['id'] = $route['id'];
            $content['routes']['items'][$index]['from'] = $route['from'];
            $content['routes']['items'][$index]['to'] = $route['to'];
            $content['routes']['items'][$index]['image_asset_key'] = $route['key'];
            $path = 'client-assets/jetpk-assets/pages/home/'.$route['key'].'.png';
            $this->putCardAsset(
                $profile->id,
                $route['key'],
                $path,
                'https://jetpakistan.pk/storage/'.$path,
            );
        }
        $this->seedPublishedHome($profile, $content);

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'client_page_assets')) {
                $queries[] = $query->sql;
            }
        });

        app(HomepagePublicContentPresenter::class)->present();

        $batchLoads = array_values(array_filter(
            $queries,
            static fn (string $sql): bool => str_contains($sql, 'page_key')
                && ! str_contains($sql, 'asset_key'),
        ));
        $responsiveFallbackLookups = array_values(array_filter(
            $queries,
            static fn (string $sql): bool => str_contains(strtolower($sql), ' in (')
                && str_contains($sql, 'asset_key'),
        ));

        $this->assertCount(
            1,
            $batchLoads,
            'Expected one batched homepage asset load for responsive lookup, got: '.json_encode($batchLoads),
        );
        $this->assertSame(
            [],
            $responsiveFallbackLookups,
            'Responsive lookup must not issue per-card whereIn fallback queries during present()',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function routeFromApi(string $to): array
    {
        $response = $this->getJson('/api/public/content/homepage');
        $response->assertOk();
        $route = collect($response->json('routes.items'))->firstWhere('to', $to);
        $this->assertNotNull($route);

        return $route;
    }

    private function seedRouteWithResponsive(string $itemId, string $assetKey, string $to): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->representativeValidFourCardHomeContent();
        $content['routes']['items'][0]['id'] = $itemId;
        $content['routes']['items'][0]['to'] = $to;
        $content['routes']['items'][0]['from'] = $to === 'RUH' ? 'KHI' : 'LHE';
        $content['routes']['items'][0]['image_asset_key'] = $assetKey;
        $this->seedPublishedHome($profile, $content);

        $path = 'client-assets/jetpk-assets/pages/home/'.str_replace('-', '_', $assetKey).'.png';
        $this->putCardAsset(
            $profile->id,
            $assetKey,
            $path,
            'https://jetpakistan.pk/storage/'.$path,
        );
    }

    private function putCardAsset(int $profileId, string $assetKey, string $path, string $url): void
    {
        Storage::disk('public')->put($path, 'source-png');
        $derivPath = 'client-assets/jetpk-assets/pages/home/card/'.substr(md5($assetKey), 0, 16).'/card-320.jpg';
        Storage::disk('public')->put($derivPath, 'jpg-bytes');

        ClientPageAsset::query()->create([
            'client_profile_id' => $profileId,
            'page_key' => ClientPageKeys::HOME,
            'asset_key' => $assetKey,
            'disk' => 'public',
            'path' => $path,
            'public_url' => $url,
            'meta_json' => [
                'responsive_media' => $this->cardManifest($path, $derivPath),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cardManifest(string $sourcePath, string $derivPath): array
    {
        $url = 'https://jetpakistan.pk/storage/'.$derivPath;

        return [
            'profile' => 'card',
            'fingerprint' => substr(md5($sourcePath), 0, 16),
            'source_path' => $sourcePath,
            'source_width' => 640,
            'source_height' => 480,
            'source_bytes' => 1000,
            'formats' => ['jpg', 'webp'],
            'variants' => [
                '320' => [
                    'jpg' => [
                        'path' => $derivPath,
                        'url' => $url,
                        'width' => 320,
                        'height' => 240,
                        'bytes' => 100,
                    ],
                ],
            ],
            'validation' => [
                ['breakpoint' => '320', 'format' => 'jpg', 'valid' => true, 'reason' => 'ok'],
            ],
        ];
    }
}
