<?php

namespace Tests\Feature\Jetpk;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Homepage\JetpkHomepageRouteFareRefreshService;
use App\Support\Homepage\JetpkHomepageRouteSearchUrlBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkHomepageTrendingRouteCtaTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedJetpkAirports();
        $this->seedJetpkAgency();
        config(['app.timezone' => 'Asia/Karachi']);
    }

    public function test_fare_refresh_persists_auto_generated_cta_url_matching_cheapest_date(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'routes' => [
                'items' => [[
                    'id' => 'route-dynamic',
                    'from' => 'LHE',
                    'to' => 'DXB',
                    'enabled' => '1',
                    'dynamic_fare_enabled' => '1',
                    'trip_type' => 'one_way',
                    'sort_order' => 0,
                ]],
            ],
        ]);

        $offset = (int) config('jetpk_homepage.route_date_offset_days', 7);
        $expectedDepart = now('Asia/Karachi')->addDays($offset)->toDateString();

        $this->mock(FlightSearchService::class, function ($mock) use ($expectedDepart): void {
            $mock->shouldReceive('search')->once()->andReturn([
                ['final_customer_price' => 59000, 'currency' => 'PKR', 'supplier_provider' => 'stub'],
            ]);
        });

        $summary = app(JetpkHomepageRouteFareRefreshService::class)->refreshProfile($profile, true);
        $this->assertSame(1, $summary['success']);

        $published = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();

        $this->assertNotNull($published);
        $item = $published->content_json['routes']['items'][0] ?? null;
        $this->assertIsArray($item);
        $this->assertSame('auto', $item['cta_mode'] ?? null);
        $this->assertNotEmpty($item['cta_url'] ?? null);
        $this->assertStringContainsString('from=LHE', (string) $item['cta_url']);
        $this->assertStringContainsString('to=DXB', (string) $item['cta_url']);
        $this->assertStringContainsString('depart='.$expectedDepart, (string) $item['cta_url']);
    }

    public function test_search_url_builder_ignores_stale_manual_cta_when_mode_is_auto(): void
    {
        $builder = app(JetpkHomepageRouteSearchUrlBuilder::class);
        $item = [
            'from' => 'ISB',
            'to' => 'LHR',
            'trip_type' => 'one_way',
            'cta_url' => '/flights/results?from=OLD&to=OLD',
            'cta_mode' => 'auto',
        ];
        $fareCache = ['travel_date' => '2026-10-01'];

        $url = $builder->fromRouteItem($item, $fareCache);
        $this->assertStringContainsString('from=ISB', $url);
        $this->assertStringContainsString('to=LHR', $url);
        $this->assertStringContainsString('depart=2026-10-01', $url);
        $this->assertStringNotContainsString('from=OLD', $url);
    }
}
