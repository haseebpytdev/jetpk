<?php

namespace Tests\Feature\Homepage;

use App\Enums\JetpkHomepageFareRefreshStatus;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Homepage\JetpkHomepageFareProvenanceAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkHomepageFareProvenanceAuditTest extends TestCase
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

    public function test_trending_audit_price_matches_search_minimum_when_cache_fresh(): void
    {
        $profile = $this->makeJetpkProfile();
        $offset = (int) config('jetpk_homepage.route_date_offset_days', 7);
        $depart = now('Asia/Karachi')->addDays($offset)->toDateString();

        $this->seedPublishedHome($profile, [
            'routes' => [
                'items' => [[
                    'id' => 'route-audit',
                    'from' => 'LHE',
                    'to' => 'DXB',
                    'enabled' => '1',
                    'dynamic_fare_enabled' => '1',
                    'trip_type' => 'one_way',
                ]],
            ],
            '_fare_cache' => [
                'routes' => [
                    'route-audit' => [
                        'resolved_fare' => 59000,
                        'resolved_currency' => 'PKR',
                        'fare_refreshed_at' => now()->toIso8601String(),
                        'fare_status' => JetpkHomepageFareRefreshStatus::Success->value,
                        'travel_date' => $depart,
                    ],
                ],
            ],
        ]);

        $this->mock(FlightSearchService::class, function ($mock): void {
            $mock->shouldReceive('search')->andReturn([
                ['final_customer_price' => 59000, 'currency' => 'PKR', 'supplier_provider' => 'stub'],
            ]);
        });

        $report = app(JetpkHomepageFareProvenanceAuditor::class)->audit($profile);
        $row = $report['trending'][0] ?? null;

        $this->assertIsArray($row);
        $this->assertTrue($row['price_match']);
        $this->assertSame(59000.0, $row['min_eligible_customer_price']);
        $this->assertSame(59000.0, $row['displayed_price']);
    }
}
