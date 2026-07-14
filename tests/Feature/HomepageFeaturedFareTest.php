<?php

namespace Tests\Feature;

use App\Enums\HomepageFeaturedFareRefreshStatus;
use App\Models\Agency;
use App\Models\HomepageFeaturedFare;
use App\Models\User;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Homepage\HomepageFeaturedFareRefreshService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class HomepageFeaturedFareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_admin_can_create_featured_fare_with_allowed_offsets(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->adminUser();

        foreach ([3, 5, 7] as $offset) {
            $this->actingAs($admin)
                ->post(route('admin.settings.homepage-featured-fares.store'), [
                    'origin_code' => 'LHE',
                    'destination_code' => 'DXB',
                    'date_offset_days' => $offset,
                    'is_enabled' => 1,
                    'sort_order' => $offset,
                ])
                ->assertRedirect(route('admin.settings.homepage.edit').'#featured-fares');
        }

        $this->assertSame(3, HomepageFeaturedFare::query()->count());
    }

    public function test_invalid_offset_is_rejected(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post(route('admin.settings.homepage-featured-fares.store'), [
                'origin_code' => 'LHE',
                'destination_code' => 'DXB',
                'date_offset_days' => 10,
                'is_enabled' => 1,
            ])
            ->assertSessionHasErrors('date_offset_days');
    }

    public function test_admin_can_disable_and_sort_featured_fare_cards(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->adminUser();
        $agency = $this->defaultAgency();

        $first = HomepageFeaturedFare::query()->create([
            'agency_id' => $agency->id,
            'origin_code' => 'LHE',
            'destination_code' => 'DXB',
            'date_offset_days' => 7,
            'sort_order' => 20,
            'is_enabled' => true,
            'last_status' => HomepageFeaturedFareRefreshStatus::Success,
            'snapshot' => array_merge($this->sampleSnapshot('LHE', 'DXB', 150000), ['airline_name' => 'Route Alpha']),
        ]);
        $second = HomepageFeaturedFare::query()->create([
            'agency_id' => $agency->id,
            'origin_code' => 'KHI',
            'destination_code' => 'JED',
            'date_offset_days' => 5,
            'sort_order' => 10,
            'is_enabled' => true,
            'last_status' => HomepageFeaturedFareRefreshStatus::Success,
            'snapshot' => array_merge($this->sampleSnapshot('KHI', 'JED', 120000), ['airline_name' => 'Route Beta']),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.settings.homepage-featured-fares.update', $first), [
                'origin_code' => 'LHE',
                'destination_code' => 'DXB',
                'date_offset_days' => 7,
                'is_enabled' => 0,
                'sort_order' => 5,
            ])
            ->assertRedirect();

        $first->refresh();
        $this->assertFalse($first->is_enabled);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Route Beta')
            ->assertDontSee('Route Alpha');
    }

    public function test_homepage_falls_back_to_static_cards_when_no_dynamic_fares(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('LHE')
            ->assertSee('DXB');
    }

    public function test_homepage_displays_successful_snapshot_data(): void
    {
        $agency = $this->defaultAgency();
        HomepageFeaturedFare::query()->create([
            'agency_id' => $agency->id,
            'origin_code' => 'LHE',
            'destination_code' => 'DXB',
            'date_offset_days' => 7,
            'is_enabled' => true,
            'last_status' => HomepageFeaturedFareRefreshStatus::Success,
            'snapshot' => array_merge($this->sampleSnapshot('LHE', 'DXB', 175000), [
                'airline_name' => 'Emirates',
                'airline_code' => 'EK',
            ]),
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Emirates')
            ->assertSee('EK');
    }

    public function test_refresh_command_stores_cheapest_offer_and_computes_departure_date(): void
    {
        $agency = $this->defaultAgency();
        $fare = HomepageFeaturedFare::query()->create([
            'agency_id' => $agency->id,
            'origin_code' => 'LHE',
            'destination_code' => 'DXB',
            'date_offset_days' => 7,
            'is_enabled' => true,
            'last_status' => HomepageFeaturedFareRefreshStatus::Pending,
        ]);

        $expectedDate = now()->addDays(7)->toDateString();

        $this->mock(FlightSearchService::class, function ($mock) use ($expectedDate): void {
            $mock->shouldReceive('search')
                ->once()
                ->withArgs(function (array $criteria, ?Agency $agency, string $channel) use ($expectedDate): bool {
                    return $criteria['origin'] === 'LHE'
                        && $criteria['destination'] === 'DXB'
                        && $criteria['departure_date'] === $expectedDate
                        && $criteria['adults'] === 1
                        && $criteria['cabin'] === 'economy'
                        && $channel === 'homepage_featured_fare';
                })
                ->andReturn([
                    $this->displayOffer(250000, 'PK', 'Pakistan Intl'),
                    $this->displayOffer(175000, 'EK', 'Emirates'),
                ]);
        });

        app(HomepageFeaturedFareRefreshService::class)->refreshOne($fare->fresh());

        $fare->refresh();
        $this->assertSame(HomepageFeaturedFareRefreshStatus::Success, $fare->last_status);
        $this->assertSame(175000.0, (float) ($fare->snapshot['price_total'] ?? 0));
        $this->assertSame('Emirates', $fare->snapshot['airline_name'] ?? '');
        $this->assertSame($expectedDate, $fare->snapshot['departure_date'] ?? '');
        $this->assertArrayNotHasKey('raw_payload', $fare->snapshot ?? []);
    }

    public function test_refresh_command_sets_no_results_when_empty(): void
    {
        $fare = HomepageFeaturedFare::query()->create([
            'agency_id' => $this->defaultAgency()->id,
            'origin_code' => 'ISB',
            'destination_code' => 'IST',
            'date_offset_days' => 3,
            'is_enabled' => true,
        ]);

        $this->mock(FlightSearchService::class, function ($mock): void {
            $mock->shouldReceive('search')->once()->andReturn([]);
        });

        app(HomepageFeaturedFareRefreshService::class)->refreshOne($fare);

        $fare->refresh();
        $this->assertSame(HomepageFeaturedFareRefreshStatus::NoResults, $fare->last_status);
        $this->assertNull($fare->snapshot);
    }

    public function test_refresh_command_sets_failed_on_provider_error(): void
    {
        $fare = HomepageFeaturedFare::query()->create([
            'agency_id' => $this->defaultAgency()->id,
            'origin_code' => 'KHI',
            'destination_code' => 'JED',
            'date_offset_days' => 5,
            'is_enabled' => true,
        ]);

        $this->mock(FlightSearchService::class, function ($mock): void {
            $mock->shouldReceive('search')->once()->andThrow(new \RuntimeException('Supplier timeout'));
        });

        app(HomepageFeaturedFareRefreshService::class)->refreshOne($fare);

        $fare->refresh();
        $this->assertSame(HomepageFeaturedFareRefreshStatus::Failed, $fare->last_status);
        $this->assertNull($fare->snapshot);
        $this->assertStringContainsString('Supplier timeout', (string) $fare->last_error_message);
    }

    public function test_view_fares_url_uses_computed_date_and_one_adult(): void
    {
        $fare = HomepageFeaturedFare::query()->create([
            'agency_id' => $this->defaultAgency()->id,
            'origin_code' => 'LHE',
            'destination_code' => 'DXB',
            'date_offset_days' => 7,
            'adults' => 1,
            'cabin' => 'economy',
            'is_enabled' => true,
            'last_status' => HomepageFeaturedFareRefreshStatus::Success,
            'snapshot' => $this->sampleSnapshot('LHE', 'DXB', 100000),
        ]);

        $url = $fare->viewFaresUrl();
        $this->assertStringContainsString('from=LHE', $url);
        $this->assertStringContainsString('to=DXB', $url);
        $this->assertStringContainsString('depart='.now()->addDays(7)->toDateString(), $url);
        $this->assertStringContainsString('adults=1', $url);
        $this->assertStringContainsString('cabin=economy', $url);
    }

    public function test_artisan_refresh_command_runs_for_enabled_routes(): void
    {
        HomepageFeaturedFare::query()->create([
            'agency_id' => $this->defaultAgency()->id,
            'origin_code' => 'LHE',
            'destination_code' => 'DXB',
            'date_offset_days' => 3,
            'is_enabled' => true,
        ]);

        $this->mock(FlightSearchService::class, function ($mock): void {
            $mock->shouldReceive('search')->once()->andReturn([$this->displayOffer(100000)]);
        });

        $this->assertSame(0, Artisan::call('homepage:refresh-featured-fares'));
    }

    public function test_admin_settings_hub_lists_featured_fares_link(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Homepage Featured Fares');
    }

    public function test_homepage_sections_featured_fares_form_shows_route_fields_only(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('admin.settings.homepage.edit'))
            ->assertOk()
            ->assertSee('Route rules')
            ->assertSee('Date offset')
            ->assertSee('Today + 3 days')
            ->assertDontSee('Price (PKR)')
            ->assertDontSee('Badge text')
            ->assertDontSee('Button URL (optional, /path');
    }

    /**
     * @return array<string, mixed>
     */
    protected function sampleSnapshot(string $from, string $to, float $price): array
    {
        return [
            'origin_code' => $from,
            'destination_code' => $to,
            'airline_name' => 'Test Air',
            'airline_code' => 'TA',
            'departure_date' => now()->addDays(7)->toDateString(),
            'price_total' => $price,
            'currency' => 'PKR',
            'refundable_label' => 'Non-refundable',
            'retrieved_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function displayOffer(float $price, string $code = 'EK', string $name = 'Emirates'): array
    {
        return [
            'airline_code' => $code,
            'airline_name' => $name,
            'final_customer_price' => $price,
            'total' => $price,
            'departure_at' => now()->addDays(7)->toIso8601String(),
            'arrival_at' => now()->addDays(7)->addHours(4)->toIso8601String(),
            'duration_minutes' => 240,
            'baggage' => '20 kg',
            'refundable' => false,
            'supplier_provider' => 'sabre',
            'pricing_currency' => 'PKR',
        ];
    }

    protected function adminUser(): User
    {
        return User::query()->where('email', 'admin@ota.demo')->firstOrFail();
    }

    protected function defaultAgency(): Agency
    {
        return Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
    }
}
