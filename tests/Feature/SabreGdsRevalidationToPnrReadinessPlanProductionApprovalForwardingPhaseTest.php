<?php

namespace Tests\Feature;

use App\Console\Commands\SabreGdsRevalidationToPnrReadinessPlanCommand;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRunner;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use Symfony\Component\Console\Input\ArrayInput;
use Tests\TestCase;

/**
 * Phase SABRE-GDS-REVALIDATION-TO-PNR-READINESS-PLAN-PRODUCTION-APPROVAL-FORWARDING-1
 */
class SabreGdsRevalidationToPnrReadinessPlanProductionApprovalForwardingPhaseTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');
        parent::tearDown();
    }

    public function test_production_without_approval_fails_before_scenario_runner(): void
    {
        Config::set('app.env', 'production');
        Http::fake();

        $exit = Artisan::call('sabre:gds-revalidation-to-pnr-readiness-plan', [
            '--connection' => '1',
            '--departure-date' => '2026-09-15',
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
        ]);

        $this->assertSame(1, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString(
            'Production readiness plan requires --production-ops-approval='.SabreGdsLiveScenarioRunner::PRODUCTION_OPS_APPROVAL_PHRASE,
            $output,
        );
        $this->assertStringNotContainsString('Production scenario runner requires', $output);
        Http::assertNothingSent();
    }

    public function test_production_with_wrong_approval_fails_before_scenario_runner(): void
    {
        Config::set('app.env', 'production');
        Http::fake();

        $exit = Artisan::call('sabre:gds-revalidation-to-pnr-readiness-plan', [
            '--connection' => '1',
            '--departure-date' => '2026-09-15',
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
            '--production-ops-approval' => 'WRONG-PHRASE',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString(
            'Invalid --production-ops-approval phrase for production readiness plan.',
            Artisan::output(),
        );
        Http::assertNothingSent();
    }

    public function test_production_with_correct_approval_forwards_and_runs_plan_mode(): void
    {
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        $this->fakeSabreShop();
        $conn = $this->seedSabreConnection();
        $before = Booking::query()->count();

        $exit = Artisan::call('sabre:gds-revalidation-to-pnr-readiness-plan', [
            '--connection' => (string) $conn->id,
            '--departure-date' => '2026-09-15',
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
            '--production-ops-approval' => SabreGdsLiveScenarioRunner::PRODUCTION_OPS_APPROVAL_PHRASE,
        ]);

        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('mode=readiness_plan_only', $output);
        $this->assertStringContainsString('pnr_create_authorized=false', $output);
        $this->assertStringContainsString('pnr_attempted=false', $output);
        $this->assertSame($before, Booking::query()->count());
        Http::assertSent(fn (Request $request) => str_contains(strtolower($request->url()), 'v4/offers/shop'));
        Http::assertNotSent(fn (Request $request) => str_contains(strtolower($request->url()), 'passenger/records'));
    }

    public function test_delegate_options_forward_approval_and_remain_plan_only(): void
    {
        $command = new SabreGdsRevalidationToPnrReadinessPlanCommand;
        $command->setLaravel($this->app);
        $command->setInput(new ArrayInput([
            '--connection' => '1',
            '--departure-date' => '2026-09-15',
            '--fare-pick' => 'brand',
            '--production-ops-approval' => SabreGdsLiveScenarioRunner::PRODUCTION_OPS_APPROVAL_PHRASE,
        ], $command->getDefinition()));

        $method = (new ReflectionClass(SabreGdsRevalidationToPnrReadinessPlanCommand::class))
            ->getMethod('buildScenarioRunnerDelegateOptions');
        $options = $method->invoke($command, SabreGdsLiveScenarioRunner::CONFIRM_PHRASE);

        $this->assertSame('plan', $options['--mode'] ?? null);
        $this->assertSame(SabreGdsLiveScenarioRunner::PRODUCTION_OPS_APPROVAL_PHRASE, $options['--production-ops-approval'] ?? null);
        $this->assertSame('qr-connecting', $options['--preset'] ?? null);
        $this->assertArrayNotHasKey('--passenger-json', $options);
        $this->assertArrayNotHasKey('--cancel-approval', $options);
        $this->assertArrayNotHasKey('--mixed-carrier-certification-approval', $options);
        $this->assertNotSame('book', $options['--mode'] ?? null);
        $this->assertNotSame('book-and-retrieve', $options['--mode'] ?? null);
        $this->assertNotSame('book-retrieve-and-cancel', $options['--mode'] ?? null);
    }

    public function test_non_production_does_not_require_production_approval(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.ticketing_enabled', false);
        $this->fakeSabreShop();
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:gds-revalidation-to-pnr-readiness-plan', [
            '--connection' => (string) $conn->id,
            '--departure-date' => '2026-09-15',
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
        ]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('mode=readiness_plan_only', Artisan::output());
    }

    public function test_non_production_does_not_forward_production_approval_when_absent(): void
    {
        $command = new SabreGdsRevalidationToPnrReadinessPlanCommand;
        $command->setLaravel($this->app);
        $command->setInput(new ArrayInput([
            '--connection' => '1',
            '--departure-date' => '2026-09-15',
            '--fare-pick' => 'brand',
        ], $command->getDefinition()));

        $method = (new ReflectionClass(SabreGdsRevalidationToPnrReadinessPlanCommand::class))
            ->getMethod('buildScenarioRunnerDelegateOptions');
        $options = $method->invoke($command, SabreGdsLiveScenarioRunner::CONFIRM_PHRASE);

        $this->assertArrayNotHasKey('--production-ops-approval', $options);
        $this->assertSame('plan', $options['--mode'] ?? null);
    }

    protected function fakeSabreShop(): void
    {
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($this->shopFixtureWithBookingCode('Y'), 200),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function shopFixtureWithBookingCode(string $bookingCode): array
    {
        return [
            'groupedItineraryResponse' => [
                'version' => '7.0.3',
                'messages' => [],
                'statistics' => ['itineraryCount' => 1],
                'scheduleDescs' => [[
                    'id' => 1,
                    'frequency' => 'SMTWTFS',
                    'stopCount' => 1,
                    'eTicketable' => true,
                    'totalMilesFlown' => 2200,
                    'elapsedTime' => 480,
                    'departure' => ['airport' => 'LHE', 'time' => '02:30:00+05:00'],
                    'arrival' => ['airport' => 'JED', 'time' => '10:30:00+03:00'],
                    'carrier' => ['marketing' => 'QR', 'marketingFlightNumber' => 601, 'operating' => 'QR'],
                ]],
                'legDescs' => [['id' => 1, 'schedules' => [['ref' => 1]]]],
                'itineraryGroups' => [[
                    'groupDescription' => [
                        'legDescriptions' => [['departureDate' => '2026-09-15', 'departureLocation' => 'LHE', 'arrivalLocation' => 'JED']],
                    ],
                    'itineraries' => [[
                        'id' => 1,
                        'pricingSource' => 'ADVJR1',
                        'legs' => [['ref' => 1]],
                        'pricingInformation' => [[
                            'fare' => [
                                'validatingCarrierCode' => 'QR',
                                'totalFare' => ['totalPrice' => 520.83, 'currency' => 'USD'],
                                'passengerInfoList' => [[
                                    'passengerInfo' => [
                                        'fareComponents' => [[
                                            'segments' => [['segment' => ['bookingCode' => $bookingCode]]],
                                        ]],
                                    ],
                                ]],
                            ],
                        ]],
                    ]],
                ]],
            ],
        ];
    }

    protected function seedSabreConnection(): SupplierConnection
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $conn->base_url = 'https://api.cert.platform.sabre.com';
        $conn->is_active = true;
        $conn->status = SupplierConnectionStatus::Active;
        $conn->credentials = ['client_id' => 'cpnr_ci', 'client_secret' => 'cpnr_cs', 'pcc' => 'TEST'];
        $conn->save();
        Cache::flush();

        return $conn;
    }
}
