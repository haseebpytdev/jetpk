<?php

namespace Tests\Feature;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Homepage\JetpkHomepageRouteFareRefreshService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkHomepageFareRefreshPipelineTest extends TestCase
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

    public function test_refresh_selects_cheapest_positive_fare_with_karachi_date_offset(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'routes' => [
                'items' => [[
                    'id' => 'route-dynamic',
                    'from' => 'KHI',
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
            $mock->shouldReceive('search')->once()->withArgs(function (array $criteria, $agency, string $channel) use ($expectedDepart): bool {
                return $criteria['departure_date'] === $expectedDepart
                    && $criteria['origin'] === 'KHI'
                    && $criteria['destination'] === 'DXB'
                    && $channel === 'jetpk_homepage_route_fare';
            })->andReturn([
                ['final_customer_price' => -100],
                ['final_customer_price' => 0],
                ['final_customer_price' => null],
                ['final_customer_price' => 61000, 'currency' => 'PKR', 'supplier_provider' => 'stub'],
                ['total' => 59000, 'currency' => 'PKR', 'supplier_provider' => 'stub2'],
            ]);
        });

        $summary = app(JetpkHomepageRouteFareRefreshService::class)->refreshProfile($profile, true);
        $this->assertSame(1, $summary['success']);

        $published = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();
        $cache = $published->content_json['_fare_cache']['routes']['route-dynamic'];
        $this->assertSame(59000.0, (float) $cache['resolved_fare']);
    }

    public function test_return_trip_uses_configured_stay_duration(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'routes' => [
                'items' => [[
                    'id' => 'route-return',
                    'from' => 'KHI',
                    'to' => 'DXB',
                    'enabled' => '1',
                    'dynamic_fare_enabled' => '1',
                    'trip_type' => 'return',
                    'return_stay_days' => 10,
                    'sort_order' => 0,
                ]],
            ],
        ]);

        $offset = (int) config('jetpk_homepage.route_date_offset_days', 7);
        $depart = now('Asia/Karachi')->addDays($offset)->toDateString();
        $return = now('Asia/Karachi')->addDays($offset + 10)->toDateString();

        $this->mock(FlightSearchService::class, function ($mock) use ($depart, $return): void {
            $mock->shouldReceive('search')->once()->withArgs(function (array $criteria) use ($depart, $return): bool {
                return $criteria['trip_type'] === 'return'
                    && $criteria['departure_date'] === $depart
                    && ($criteria['return_date'] ?? '') === $return;
            })->andReturn([
                ['final_customer_price' => 120000, 'currency' => 'PKR'],
            ]);
        });

        app(JetpkHomepageRouteFareRefreshService::class)->refreshProfile($profile, true);
    }

    public function test_refresh_preserves_previous_valid_fare_on_failure(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'routes' => [
                'items' => [[
                    'id' => 'route-1',
                    'from' => 'KHI',
                    'to' => 'DXB',
                    'enabled' => '1',
                    'dynamic_fare_enabled' => '1',
                    'trip_type' => 'one_way',
                ]],
            ],
            '_fare_cache' => [
                'routes' => [
                    'route-1' => [
                        'resolved_fare' => 45000,
                        'resolved_currency' => 'PKR',
                        'fare_refreshed_at' => now()->subDay()->toIso8601String(),
                        'fare_status' => 'success',
                    ],
                ],
            ],
        ]);

        $this->mock(FlightSearchService::class, function ($mock): void {
            $mock->shouldReceive('search')->once()->andThrow(new \RuntimeException('Transient'));
        });

        app(JetpkHomepageRouteFareRefreshService::class)->refreshProfile($profile, true);

        $published = ClientPageSetting::query()
            ->where('status', ClientPageSettingStatus::Published)
            ->first();
        $this->assertSame(45000.0, (float) $published->content_json['_fare_cache']['routes']['route-1']['resolved_fare']);
    }

    public function test_dry_run_does_not_persist_fare_cache(): void
    {
        $profile = $this->makeJetpkProfile();
        $before = [
            'routes' => [
                'items' => [[
                    'id' => 'route-1',
                    'from' => 'KHI',
                    'to' => 'DXB',
                    'enabled' => '1',
                    'dynamic_fare_enabled' => '1',
                    'trip_type' => 'one_way',
                ]],
            ],
            '_fare_cache' => ['routes' => []],
        ];
        $this->seedPublishedHome($profile, $before);

        $this->mock(FlightSearchService::class, function ($mock): void {
            $mock->shouldReceive('search')->once()->andReturn([
                ['final_customer_price' => 52000, 'currency' => 'PKR'],
            ]);
        });

        $exitCode = Artisan::call('jetpk:homepage-route-fares-refresh', ['--profile' => 'jetpk', '--dry-run' => true]);
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('refreshed=', Artisan::output());

        $published = ClientPageSetting::query()
            ->where('status', ClientPageSettingStatus::Published)
            ->first();
        $this->assertSame([], $published->content_json['_fare_cache']['routes'] ?? []);
    }

    public function test_refresh_updates_only_fare_cache_branch_on_draft_and_published(): void
    {
        $profile = $this->makeJetpkProfile();
        $publishedContent = [
            'hero' => ['headline' => 'Published headline'],
            'routes' => [
                'items' => [[
                    'id' => 'route-1',
                    'from' => 'KHI',
                    'to' => 'DXB',
                    'enabled' => '1',
                    'dynamic_fare_enabled' => '1',
                    'trip_type' => 'one_way',
                    'manual_fallback_price' => 99999,
                ]],
            ],
        ];
        $draftContent = [
            'hero' => ['headline' => 'Draft headline'],
            'routes' => [
                'items' => [[
                    'id' => 'route-1',
                    'from' => 'KHI',
                    'to' => 'DXB',
                    'enabled' => '1',
                    'dynamic_fare_enabled' => '1',
                    'trip_type' => 'one_way',
                    'manual_fallback_price' => 88888,
                ]],
            ],
        ];
        $this->seedPublishedHome($profile, $publishedContent);
        $this->seedDraftHome($profile, $draftContent);

        $this->mock(FlightSearchService::class, function ($mock): void {
            $mock->shouldReceive('search')->once()->andReturn([
                ['final_customer_price' => 52000, 'currency' => 'PKR'],
            ]);
        });

        app(JetpkHomepageRouteFareRefreshService::class)->refreshProfile($profile, true);

        $published = ClientPageSetting::query()->where('status', ClientPageSettingStatus::Published)->first();
        $draft = ClientPageSetting::query()->where('status', ClientPageSettingStatus::Draft)->first();

        $this->assertSame('Published headline', data_get($published->content_json, 'hero.headline'));
        $this->assertSame(99999, (int) data_get($published->content_json, 'routes.items.0.manual_fallback_price'));
        $this->assertSame(52000.0, (float) $published->content_json['_fare_cache']['routes']['route-1']['resolved_fare']);

        $this->assertSame('Draft headline', data_get($draft->content_json, 'hero.headline'));
        $this->assertSame(88888, (int) data_get($draft->content_json, 'routes.items.0.manual_fallback_price'));
        $this->assertSame(52000.0, (float) $draft->content_json['_fare_cache']['routes']['route-1']['resolved_fare']);
    }

    public function test_concurrent_refresh_is_blocked_by_lock(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'routes' => [
                'items' => [[
                    'id' => 'route-1',
                    'from' => 'KHI',
                    'to' => 'DXB',
                    'enabled' => '1',
                    'dynamic_fare_enabled' => '1',
                    'trip_type' => 'one_way',
                ]],
            ],
        ]);

        $lock = Cache::lock('jetpk:homepage-route-fares:'.$profile->id, 60);
        $this->assertTrue($lock->get());

        $this->mock(FlightSearchService::class, function ($mock): void {
            $mock->shouldReceive('search')->never();
        });

        $summary = app(JetpkHomepageRouteFareRefreshService::class)->refreshProfile($profile, true);
        $this->assertSame('locked', $summary['results'][0]['status'] ?? '');

        $lock->release();
    }
}
