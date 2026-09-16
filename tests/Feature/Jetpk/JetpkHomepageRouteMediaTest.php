<?php

namespace Tests\Feature\Jetpk;

use App\Enums\AccountType;
use App\Models\ClientPageAsset;
use App\Models\User;
use App\Services\PublicContent\HomepagePublicContentPresenter;
use App\Support\Client\ClientPageKeys;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkHomepageRouteMediaTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seedJetpkAirports();
    }

    public function test_khi_ruh_route_emits_cms_image_url_in_public_api(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->representativeValidFourCardHomeContent();
        $content['routes']['items'][3]['image_asset_key'] = 'route_seed_khi_ruh';
        $this->seedPublishedHome($profile, $content);

        $assetUrl = 'https://jetpakistan.pk/storage/client-assets/jetpk-assets/pages/home/route_seed_khi_ruh-20260913115311.png';
        ClientPageAsset::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'asset_key' => 'route_seed_khi_ruh',
            'disk' => 'public',
            'path' => 'client-assets/jetpk-assets/pages/home/route_seed_khi_ruh-20260913115311.png',
            'public_url' => $assetUrl,
        ]);

        $response = $this->getJson('/api/public/content/homepage');
        $response->assertOk();

        $routes = $response->json('routes.items');
        $ruhRoute = collect($routes)->firstWhere('to', 'RUH');
        $this->assertNotNull($ruhRoute);
        $this->assertStringStartsWith($assetUrl, (string) $ruhRoute['image']);
        $this->assertStringContainsString('route_seed_khi_ruh', (string) $ruhRoute['image']);
    }

    public function test_featured_deal_images_remain_bound_to_deal_id_when_reordered(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->representativeValidFourCardHomeContent();
        $content['featured_deals'] = [
            'enabled' => '1',
            'items' => [
                [
                    'id' => 'deal-alpha',
                    'airline' => 'PIA',
                    'from' => 'LHE',
                    'to' => 'DXB',
                    'enabled' => '1',
                    'sort_order' => 0,
                    'price' => 45000,
                    'image_asset_key' => 'featured_deal_alpha',
                ],
                [
                    'id' => 'deal-beta',
                    'airline' => 'AirBlue',
                    'from' => 'KHI',
                    'to' => 'JED',
                    'enabled' => '1',
                    'sort_order' => 1,
                    'price' => 55000,
                    'image_asset_key' => 'featured_deal_beta',
                ],
            ],
        ];
        $this->seedPublishedHome($profile, $content);

        foreach ([
            'featured_deal_alpha' => 'https://jetpakistan.pk/storage/deal-alpha.png',
            'featured_deal_beta' => 'https://jetpakistan.pk/storage/deal-beta.png',
        ] as $assetKey => $url) {
            ClientPageAsset::query()->create([
                'client_profile_id' => $profile->id,
                'page_key' => ClientPageKeys::HOME,
                'asset_key' => $assetKey,
                'disk' => 'public',
                'path' => 'client-assets/jetpk-assets/pages/home/'.$assetKey.'.png',
                'public_url' => $url,
            ]);
        }

        $presented = app(HomepagePublicContentPresenter::class)->present();
        $items = $presented['featured_deals']['items'];
        $this->assertCount(2, $items);

        $alpha = collect($items)->firstWhere('id', 'deal-alpha');
        $beta = collect($items)->firstWhere('id', 'deal-beta');
        $this->assertStringStartsWith('https://jetpakistan.pk/storage/deal-alpha.png', (string) $alpha['image']);
        $this->assertStringStartsWith('https://jetpakistan.pk/storage/deal-beta.png', (string) $beta['image']);

        $reordered = $content;
        $reordered['featured_deals']['items'] = array_reverse($content['featured_deals']['items']);
        foreach ($reordered['featured_deals']['items'] as $index => &$item) {
            $item['sort_order'] = $index;
        }
        unset($item);
        $this->seedPublishedHome($profile, $reordered);

        $presentedAfterReorder = app(HomepagePublicContentPresenter::class)->present();
        $alphaAfter = collect($presentedAfterReorder['featured_deals']['items'])->firstWhere('id', 'deal-alpha');
        $betaAfter = collect($presentedAfterReorder['featured_deals']['items'])->firstWhere('id', 'deal-beta');
        $this->assertStringStartsWith('https://jetpakistan.pk/storage/deal-alpha.png', (string) $alphaAfter['image']);
        $this->assertStringStartsWith('https://jetpakistan.pk/storage/deal-beta.png', (string) $betaAfter['image']);
    }

    public function test_route_without_cms_media_returns_null_image_for_explicit_frontend_fallback(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->representativeValidFourCardHomeContent();
        $this->seedPublishedHome($profile, $content);

        $routes = app(\App\Support\Client\JetpkHomepageSectionData::class)->routesForDisplay();
        $ruhRoute = collect($routes)->firstWhere('to', 'RUH');
        $this->assertNotNull($ruhRoute);
        $this->assertNull($ruhRoute['image']);
    }

    public function test_draft_save_does_not_trigger_next_revalidation(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'jetpk_public.next_revalidate_url' => 'https://jetpakistan.pk/api/internal/revalidate/seo',
            'jetpk_public.next_revalidate_secret' => 'test-secret',
        ]);
        Http::fake();

        $profile = $this->makeJetpkProfile();
        app(\App\Services\Client\CurrentClientContext::class)->set($profile);
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->actingAs($admin)->patch('/admin/page-settings/home', [
            'content' => $this->representativeValidFourCardHomeContent(),
        ])->assertRedirect();

        Http::assertNothingSent();
    }

    public function test_homepage_publish_triggers_homepage_revalidation_payload(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'jetpk_public.next_revalidate_url' => 'https://jetpakistan.pk/api/internal/revalidate/seo',
            'jetpk_public.next_revalidate_secret' => 'test-secret',
        ]);
        Http::fake([
            'https://jetpakistan.pk/api/internal/revalidate/seo' => Http::response(['ok' => true], 200),
        ]);

        $profile = $this->makeJetpkProfile();
        app(\App\Services\Client\CurrentClientContext::class)->set($profile);
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->seedDraftHome($profile, $this->representativeValidFourCardHomeContent());

        $this->actingAs($admin)
            ->post('/admin/page-settings/home/publish')
            ->assertRedirect();

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://jetpakistan.pk/api/internal/revalidate/seo'
                && $request['homepage'] === true
                && $request['page_keys'] === ['home']
                && in_array('/', $request['paths'], true);
        });
    }
}
