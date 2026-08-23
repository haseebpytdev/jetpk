<?php

namespace Tests\Feature\Dashboard;

use App\Services\Homepage\JetpkHomepageAssetService;
use App\Services\PublicContent\HomepagePublicContentPresenter;
use App\Support\BackOffice\BackOfficeCapabilitiesPresenter;
use App\Support\Client\ClientPageKeys;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * JP-ADMIN-CMS-03 navigation + featured-deal media authority gates.
 */
class JpAdminCms03NavigationAndMediaTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_integrations_is_single_nav_authority_without_api_connections(): void
    {
        $admin = $this->platformAdmin();
        $payload = app(BackOfficeCapabilitiesPresenter::class)->present($admin);

        $flat = collect($payload['navigation'] ?? []);
        $this->assertSame(0, $flat->where('key', 'api-settings')->count());
        $this->assertSame(0, $flat->where('label', 'API Connections')->count());
        $this->assertSame(0, $flat->where('label', 'CMS')->count());
        $this->assertSame(1, $flat->where('key', 'integrations')->count());
        $this->assertSame('/integrations', $flat->firstWhere('key', 'integrations')['href'] ?? null);

        $hrefs = $flat->pluck('href')->all();
        $this->assertSame(count($hrefs), count(array_unique($hrefs)), 'SIDEBAR_DUPLICATE_LINKS must be 0');

        $labels = collect($payload['navigation_groups'] ?? [])->pluck('label')->all();
        $this->assertContains('Operations', $labels);
        $this->assertContains('Website', $labels);
        $this->assertContains('Suppliers', $labels);
        $this->assertNotContains('Booking operations', $labels);
        $this->assertNotContains('Content & website', $labels);
    }

    public function test_featured_deal_asset_key_and_public_presenter_emit_image_fields(): void
    {
        $this->assertSame('featured_deal_deal_1', JetpkHomepageAssetService::featuredDealAssetKey('deal-1'));
        $this->assertSame('route_route_1', JetpkHomepageAssetService::routeAssetKey('route-1'));
        $this->assertSame('destination_dest_1', JetpkHomepageAssetService::destinationAssetKey('dest-1'));

        $presenter = app(HomepagePublicContentPresenter::class);
        $payload = $presenter->present();

        $this->assertArrayHasKey('featured_deals', $payload);
        $this->assertArrayHasKey('items', $payload['featured_deals']);

        foreach ($payload['featured_deals']['items'] as $item) {
            $this->assertArrayHasKey('image', $item);
            $this->assertArrayHasKey('image_alt', $item);
            $this->assertArrayHasKey('media_source', $item);
        }
    }

    public function test_legacy_api_connections_laravel_html_still_redirects_toward_dashboard_hub(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get('/admin/api-settings')
            ->assertRedirect('/admin/dashboard/integrations');
    }

    public function test_homepage_public_source_is_cms_or_empty_never_fixture_override_key(): void
    {
        $payload = app(HomepagePublicContentPresenter::class)->present();
        $this->assertContains($payload['source'], ['cms', 'empty']);
        $this->assertSame(ClientPageKeys::HOME, 'home');
    }
}
