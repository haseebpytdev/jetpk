<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageAsset;
use App\Models\ClientPageSetting;
use App\Models\GroupInventory;
use App\Models\User;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Homepage\JetpkHomepageRouteFareRefreshService;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\JetpkHomepageFareDisplay;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkHomepageContentManagementTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ota-developer.enabled' => true]);
        config(['client_route_parity.enabled' => false]);
        Storage::fake('public');
        $this->seedJetpkAirports();
        $this->seedJetpkAgency();
    }

    public function test_admin_can_save_fifth_trending_route(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $profile = $this->makeJetpkProfile();
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $items = $this->sampleRoutes(5);

        $this->actingAs($admin)->patch('/admin/page-settings/home', [
            'content' => [
                'routes' => [
                    'enabled' => '1',
                    'items' => $items,
                ],
            ],
        ])->assertRedirect('/admin/page-settings/home');

        $draft = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::HOME)
            ->where('status', ClientPageSettingStatus::Draft)
            ->first();

        $this->assertCount(5, $draft->content_json['routes']['items']);
    }

    public function test_duplicate_route_is_rejected(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->makeJetpkProfile();
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);
        $items = $this->sampleRoutes(2);
        $items[1]['from'] = $items[0]['from'];
        $items[1]['to'] = $items[0]['to'];

        $this->actingAs($admin)->patch('/admin/page-settings/home', [
            'content' => ['routes' => ['enabled' => '1', 'items' => $items]],
        ])->assertSessionHasErrors();
    }

    public function test_dynamic_fare_refresh_uses_date_offset_and_preserves_previous_fare_on_failure(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = [
            'routes' => [
                'enabled' => '1',
                'items' => [[
                    'id' => 'route-1',
                    'from' => 'KHI',
                    'to' => 'DXB',
                    'enabled' => '1',
                    'dynamic_fare_enabled' => '1',
                    'trip_type' => 'one_way',
                    'sort_order' => 0,
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
        ];

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => $content,
        ]);

        $this->mock(FlightSearchService::class, function ($mock): void {
            $mock->shouldReceive('search')->once()->andThrow(new \RuntimeException('Transient supplier error'));
        });

        app(JetpkHomepageRouteFareRefreshService::class)->refreshProfile($profile, true);

        $published = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();

        $cache = $published->content_json['_fare_cache']['routes']['route-1'];
        $this->assertSame(45000.0, (float) $cache['resolved_fare']);
    }

    public function test_cheapest_valid_fare_is_selected_on_refresh(): void
    {
        $profile = $this->makeJetpkProfile();
        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => [
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
            ],
        ]);

        $offset = (int) config('jetpk_homepage.route_date_offset_days', 7);
        $expectedDate = now(config('app.timezone', 'Asia/Karachi'))->addDays($offset)->toDateString();

        $this->mock(FlightSearchService::class, function ($mock) use ($expectedDate): void {
            $mock->shouldReceive('search')->once()->withArgs(function (array $criteria) use ($expectedDate): bool {
                return $criteria['departure_date'] === $expectedDate
                    && $criteria['origin'] === 'KHI'
                    && $criteria['destination'] === 'DXB';
            })->andReturn([
                ['final_customer_price' => 0, 'currency' => 'PKR'],
                ['final_customer_price' => 52000, 'currency' => 'PKR', 'supplier_provider' => 'test'],
            ]);
        });

        $summary = app(JetpkHomepageRouteFareRefreshService::class)->refreshProfile($profile, true);
        $this->assertSame(1, $summary['success']);
    }

    public function test_public_homepage_never_renders_pkr_zero_for_destinations(): void
    {
        $profile = $this->makeJetpkProfile();
        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => [
                'destinations' => [
                    'enabled' => '1',
                    'items' => [[
                        'id' => 'd1',
                        'code' => 'DXB',
                        'title' => 'Dubai',
                        'enabled' => '1',
                        'manual_fallback_price' => '',
                        'sort_order' => 0,
                    ]],
                ],
            ],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('PKR 0', false)
            ->assertSee('Fares available', false);
    }

    public function test_destination_image_upload_on_save(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $profile = $this->makeJetpkProfile();
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->actingAs($admin)->patch('/admin/page-settings/home', [
            'content' => [
                'destinations' => [
                    'enabled' => '1',
                    'items' => [[
                        'id' => 'dest-dxb',
                        'code' => 'DXB',
                        'title' => 'Dubai',
                        'enabled' => '1',
                        'sort_order' => 0,
                    ]],
                ],
            ],
            'destination_files' => [
                'dest-dxb' => UploadedFile::fake()->image('dubai.jpg'),
            ],
        ])->assertRedirect();

        $asset = ClientPageAsset::query()->where('asset_key', 'destination_dest_dxb')->first();
        $this->assertNotNull($asset);
        Storage::disk('public')->assertExists($asset->path);
    }

    public function test_fare_display_rejects_zero_amounts(): void
    {
        $this->assertNull(JetpkHomepageFareDisplay::resolve(['dynamic_fare_enabled' => '1'], [
            'resolved_fare' => 0,
            'fare_refreshed_at' => now()->toIso8601String(),
            'fare_status' => 'success',
        ]));

        $manual = JetpkHomepageFareDisplay::resolve(['manual_fallback_price' => 42000], null, true);
        $this->assertSame('PKR 42,000', $manual['label']);
        $this->assertNull(JetpkHomepageFareDisplay::resolve(['manual_fallback_price' => 42000], null));
    }

    public function test_homepage_content_audit_command_runs(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, $this->representativeValidFourCardHomeContent());
        Artisan::call('jetpk:homepage-content-audit', ['--profile' => 'jetpk']);
        $this->assertStringContainsString('fail_count=0', Artisan::output());
    }

    public function test_support_cta_decodes_literal_unicode_escape(): void
    {
        $this->makeJetpkProfile();
        $this->seedPublishedHome(\App\Models\ClientProfile::query()->where('slug', 'jetpk')->first(), [
            'support_cta' => [
                'enabled' => '1',
                'title' => 'Human Support \\u2014 real people',
                'subtitle' => 'We are here',
                'call_enabled' => '0',
                'chat_enabled' => '1',
                'chat_url' => '/support',
            ],
        ]);

        $payload = app(\App\Services\PublicContent\HomepagePublicContentPresenter::class)->present();
        $this->assertSame('Human Support — real people', $payload['support_cta']['title']);
        $this->assertStringNotContainsString('\\u2014', $payload['support_cta']['title']);
    }

    public function test_featured_deals_come_from_group_inventory_not_cms_price(): void
    {
        $profile = $this->makeJetpkProfile();
        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'JP-HOME-1',
            'public_id' => 'JP-HOME-1',
            'title' => 'ISB DXB group',
            'sector' => 'ISB-DXB',
            'airline_name' => 'Air Arabia',
            'departure_date' => now(config('app.timezone'))->addDays(14)->toDateString(),
            'total_seats' => 20,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 89000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $this->seedPublishedHome($profile, [
            'featured_deals' => [
                'enabled' => '1',
                'items' => [[
                    'id' => 'cms-fake',
                    'from' => 'AAA',
                    'to' => 'BBB',
                    'price' => 1,
                    'enabled' => '1',
                ]],
            ],
        ]);

        $deals = app(\App\Support\Client\JetpkHomepageSectionData::class)->featuredDealsForDisplay();
        $this->assertNotEmpty($deals);
        $this->assertSame('group_ticket', $deals[0]['source']);
        $this->assertSame('ISB', $deals[0]['from']);
        $this->assertSame('DXB', $deals[0]['to']);
        $this->assertSame(89000, $deals[0]['price']);
        $this->assertNotEmpty($deals[0]['href']);
        $this->assertStringNotContainsString('127.0.0.1', $deals[0]['href']);
        $this->assertStringNotContainsString('8088', $deals[0]['href']);
    }

    public function test_destination_click_uses_winning_origin_from_cache(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'destinations' => [
                'enabled' => '1',
                'items' => [[
                    'id' => 'd-dxb',
                    'code' => 'DXB',
                    'title' => 'Dubai',
                    'enabled' => '1',
                    'manual_fallback_price' => 111,
                ]],
            ],
            '_fare_cache' => [
                'destinations' => [
                    'd-dxb' => [
                        'resolved_fare' => 54000,
                        'resolved_currency' => 'PKR',
                        'fare_refreshed_at' => now()->toIso8601String(),
                        'fare_status' => 'success',
                        'winning_origin' => 'LHE',
                        'travel_date' => now(config('app.timezone'))->addDays(7)->toDateString(),
                    ],
                ],
            ],
        ]);

        $cards = app(\App\Support\Client\JetpkHomepageSectionData::class)->destinationsForDisplay();
        $this->assertSame('LHE', $cards[0]['winning_origin']);
        $this->assertStringContainsString('from=LHE', $cards[0]['href']);
        $this->assertStringContainsString('to=DXB', $cards[0]['href']);
        $this->assertSame('PKR 54,000', $cards[0]['price_label']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sampleRoutes(int $count): array
    {
        $pairs = [
            ['KHI', 'DXB'],
            ['LHE', 'JED'],
            ['ISB', 'LHR'],
            ['KHI', 'RUH'],
            ['LHE', 'DXB'],
        ];

        $items = [];
        for ($i = 0; $i < $count; $i++) {
            [$from, $to] = $pairs[$i];
            $items[] = [
                'id' => 'route-'.$i,
                'from' => $from,
                'to' => $to,
                'enabled' => '1',
                'sort_order' => $i,
                'trip_type' => 'one_way',
                'manual_fallback_price' => 50000 + ($i * 1000),
            ];
        }

        return $items;
    }
}
