<?php

namespace Tests\Feature;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioExactOfferEvidence;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRevalidationGate;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRunner;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Sabre\AlwaysSuccessfulScenarioRevalidationGate;
use Tests\TestCase;

class SabreGdsLiveScenarioExactOfferLinkageComponentClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_output_includes_linkage_component_diagnostics(): void
    {
        Config::set('app.env', 'testing');
        Storage::fake('local');
        $conn = $this->seedSabreConnection();
        $this->fakeConnectingShop();

        Artisan::call('sabre:gds-live-scenario-runner', [
            '--connection' => (string) $conn->id,
            '--departure-date' => '2026-09-01',
            '--origin' => 'LHE',
            '--destination' => 'JED',
            '--preset' => 'qr-connecting',
            '--mode' => 'plan',
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('source_identifier_hash_present=', $output);
        $this->assertStringContainsString('segment_signature_present=', $output);
        $this->assertStringContainsString('revalidation_linkage_missing_components=', $output);

        $files = Storage::disk('local')->allFiles('sabre-gds-scenario-runs');
        $summary = json_decode((string) Storage::disk('local')->get($files[0]), true);
        $scenario = $summary['scenario_results'][0] ?? [];
        $candidate = $scenario['candidates'][0] ?? [];
        $this->assertTrue($candidate['source_identifier_hash_present'] ?? false);
        $this->assertTrue($candidate['segment_signature_present'] ?? false);
        $this->assertTrue($candidate['revalidation_linkage_ready'] ?? false);
        $this->assertSame([], $candidate['revalidation_linkage_missing_components'] ?? null);
    }

    public function test_same_exact_offer_produces_same_fingerprint_in_plan_and_revalidation(): void
    {
        Config::set('app.env', 'testing');
        $conn = $this->seedSabreConnection();
        $this->fakeConnectingShopAndPnr();
        $this->configureScenarioRunnerSabre();

        $holder = new \stdClass;
        $holder->fingerprint = '';
        $gate = new class($holder) extends AlwaysSuccessfulScenarioRevalidationGate {
            public function __construct(private \stdClass $holder)
            {
                parent::__construct();
            }

            public function revalidateSelectedOffer(
                SupplierConnection $connection,
                array $offerSnap,
                array $passengerBundle,
                float $selectedTotal,
                ?int $bookingId = null,
                array $continuity = [],
            ): array {
                $this->holder->fingerprint = (string) ($continuity['expected_fingerprint'] ?? '');

                return parent::revalidateSelectedOffer($connection, $offerSnap, $passengerBundle, $selectedTotal, $bookingId, $continuity);
            }
        };
        $this->app->instance(SabreGdsLiveScenarioRevalidationGate::class, $gate);
        $this->app->forgetInstance(SabreGdsLiveScenarioRunner::class);

        Storage::fake('local');
        Artisan::call('sabre:gds-live-scenario-runner', [
            '--connection' => (string) $conn->id,
            '--departure-date' => '2026-09-01',
            '--origin' => 'LHE',
            '--destination' => 'JED',
            '--preset' => 'qr-connecting',
            '--mode' => 'plan',
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
        ]);
        $planFiles = Storage::disk('local')->allFiles('sabre-gds-scenario-runs');
        $planFingerprint = data_get(json_decode((string) Storage::disk('local')->get($planFiles[0]), true), 'scenario_results.0.selected_offer_fingerprint');

        $summary = app(SabreGdsLiveScenarioRunner::class)->run([
            'connection_id' => $conn->id,
            'origin' => 'LHE',
            'destination' => 'JED',
            'departure_date' => '2026-09-01',
            'preset' => 'qr-connecting',
            'mode' => 'book',
            'operator_approved' => true,
            'passenger_json' => $this->writePassengerFixture(),
            'max_bookings' => 1,
        ]);

        $scenario = $summary['scenario_results'][0] ?? [];
        $this->assertTrue($scenario['booking_created'] ?? false, json_encode($scenario));
        $this->assertSame($planFingerprint, $holder->fingerprint);
        $this->assertSame($planFingerprint, $scenario['selected_offer_fingerprint'] ?? null);
    }

    public function test_changed_identifier_hash_blocks_before_booking(): void
    {
        Config::set('app.env', 'testing');
        $conn = $this->seedSabreConnection();
        $this->configureScenarioRunnerSabre();
        $this->fakeConnectingShopAndPnr();
        $this->bindEvidenceDoubleThatChangesSourceHashOnSecondBuild();

        $before = Booking::query()->count();
        $summary = app(SabreGdsLiveScenarioRunner::class)->run([
            'connection_id' => $conn->id,
            'origin' => 'LHE',
            'destination' => 'JED',
            'departure_date' => '2026-09-01',
            'preset' => 'qr-connecting',
            'mode' => 'book',
            'operator_approved' => true,
            'passenger_json' => $this->writePassengerFixture(),
            'max_bookings' => 1,
        ]);

        $this->assertSame($before, Booking::query()->count());
        $this->assertSame(
            SabreGdsLiveScenarioExactOfferEvidence::REASON_EXACT_OFFER_SOURCE_IDENTIFIER_MISMATCH,
            $summary['scenario_results'][0]['error'] ?? null,
        );
    }

    public function test_changed_segment_signature_blocks_before_booking(): void
    {
        Config::set('app.env', 'testing');
        $conn = $this->seedSabreConnection();
        $this->configureScenarioRunnerSabre();
        $this->fakeConnectingShopAndPnr();
        $this->bindEvidenceDoubleThatChangesSegmentSignatureOnSecondBuild();

        $before = Booking::query()->count();
        $summary = app(SabreGdsLiveScenarioRunner::class)->run([
            'connection_id' => $conn->id,
            'origin' => 'LHE',
            'destination' => 'JED',
            'departure_date' => '2026-09-01',
            'preset' => 'qr-connecting',
            'mode' => 'book',
            'operator_approved' => true,
            'passenger_json' => $this->writePassengerFixture(),
            'max_bookings' => 1,
        ]);

        $this->assertSame($before, Booking::query()->count());
        $this->assertSame(
            SabreGdsLiveScenarioExactOfferEvidence::REASON_EXACT_OFFER_SEGMENT_SIGNATURE_MISMATCH,
            $summary['scenario_results'][0]['error'] ?? null,
        );
    }

    public function test_plan_mode_performs_no_booking_or_supplier_mutations(): void
    {
        Config::set('app.env', 'testing');
        Storage::fake('local');
        $conn = $this->seedSabreConnection();
        $this->fakeConnectingShop();
        $beforeBookings = Booking::query()->count();
        $beforeAttempts = SupplierBookingAttempt::query()->count();

        Artisan::call('sabre:gds-live-scenario-runner', [
            '--connection' => (string) $conn->id,
            '--departure-date' => '2026-09-01',
            '--origin' => 'LHE',
            '--destination' => 'JED',
            '--preset' => 'qr-connecting',
            '--mode' => 'plan',
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
        ]);

        $this->assertSame($beforeBookings, Booking::query()->count());
        $this->assertSame($beforeAttempts, SupplierBookingAttempt::query()->count());
    }

    protected function bindEvidenceDoubleThatChangesSourceHashOnSecondBuild(): void
    {
        $buildCount = 0;
        $this->app->bind(SabreGdsLiveScenarioExactOfferEvidence::class, function () use (&$buildCount) {
            return new class(app(\App\Services\Suppliers\Sabre\Gds\SabreStoredPricingContextDigest::class), $buildCount) extends SabreGdsLiveScenarioExactOfferEvidence {
                public function __construct(
                    \App\Services\Suppliers\Sabre\Gds\SabreStoredPricingContextDigest $pricingDigest,
                    private int &$buildCount,
                ) {
                    parent::__construct($pricingDigest);
                }

                public function buildLinkageContext(
                    SupplierConnection $connection,
                    array $snap,
                    array $row,
                    ?array $selectedFareFamilyOption = null,
                    ?string $shopCapturedAt = null,
                ): array {
                    $context = parent::buildLinkageContext($connection, $snap, $row, $selectedFareFamilyOption, $shopCapturedAt);
                    $this->buildCount++;
                    if ($this->buildCount >= 2) {
                        $context['source_identifier_hash'] = str_repeat('a', 64);
                        $context['safe_offer_fingerprint'] = str_repeat('b', 64);
                    }

                    return $context;
                }
            };
        });
        $this->app->forgetInstance(SabreGdsLiveScenarioRunner::class);
    }

    protected function bindEvidenceDoubleThatChangesSegmentSignatureOnSecondBuild(): void
    {
        $buildCount = 0;
        $this->app->bind(SabreGdsLiveScenarioExactOfferEvidence::class, function () use (&$buildCount) {
            return new class(app(\App\Services\Suppliers\Sabre\Gds\SabreStoredPricingContextDigest::class), $buildCount) extends SabreGdsLiveScenarioExactOfferEvidence {
                public function __construct(
                    \App\Services\Suppliers\Sabre\Gds\SabreStoredPricingContextDigest $pricingDigest,
                    private int &$buildCount,
                ) {
                    parent::__construct($pricingDigest);
                }

                public function buildLinkageContext(
                    SupplierConnection $connection,
                    array $snap,
                    array $row,
                    ?array $selectedFareFamilyOption = null,
                    ?string $shopCapturedAt = null,
                ): array {
                    $context = parent::buildLinkageContext($connection, $snap, $row, $selectedFareFamilyOption, $shopCapturedAt);
                    $this->buildCount++;
                    if ($this->buildCount >= 2) {
                        $context['segment_signature'] = str_repeat('c', 64);
                        $context['safe_offer_fingerprint'] = str_repeat('d', 64);
                    }

                    return $context;
                }
            };
        });
        $this->app->forgetInstance(SabreGdsLiveScenarioRunner::class);
    }

    protected function configureScenarioRunnerSabre(): void
    {
        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', true);
        Config::set('suppliers.sabre.pnr_create_enabled', true);
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.pnr_only_waive_mandatory_revalidation', true);
        $this->app->instance(SabreGdsLiveScenarioRevalidationGate::class, new AlwaysSuccessfulScenarioRevalidationGate);
        $this->app->forgetInstance(SabreGdsLiveScenarioRunner::class);
    }

    protected function fakeConnectingShop(): void
    {
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($this->connectingShopFixture(), 200),
        ]);
    }

    protected function fakeConnectingShopAndPnr(): void
    {
        Http::fake(function (Request $request, array $options) {
            $url = strtolower($request->url());
            $payload = $options['laravel_data'] ?? [];
            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            if (str_contains($url, $tokenPath) || (is_array($payload) && array_key_exists('grant_type', $payload))) {
                return Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, 'v4/offers/shop')) {
                return Http::response($this->connectingShopFixture(), 200);
            }
            if (str_contains($url, 'revalidate')) {
                return Http::response([
                    'pricedItineraries' => [[
                        'airItineraryPricingInfo' => [
                            'validatingCarrier' => 'QR',
                            'itinTotalFare' => ['totalFare' => ['totalPrice' => 520.83, 'currencyCode' => 'USD']],
                        ],
                    ]],
                ], 200);
            }
            if (str_contains($url, 'passenger/records')) {
                return Http::response([
                    'CreatePassengerNameRecordRS' => [
                        'ApplicationResults' => ['status' => 'Complete'],
                        'ItineraryRef' => ['ID' => 'SCNR01'],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function connectingShopFixture(): array
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')), true);
        $this->assertIsArray($fixture);
        data_set($fixture, 'groupedItineraryResponse.scheduleDescs', [
            [
                'ref' => 1,
                'departure' => ['airport' => 'LHE', 'time' => '2026-09-01T02:15:00'],
                'arrival' => ['airport' => 'DOH', 'time' => '2026-09-01T04:30:00'],
                'carrier' => ['marketing' => 'QR', 'marketingFlightNumber' => '615'],
            ],
            [
                'ref' => 2,
                'departure' => ['airport' => 'DOH', 'time' => '2026-09-01T06:00:00'],
                'arrival' => ['airport' => 'JED', 'time' => '2026-09-01T08:15:00'],
                'carrier' => ['marketing' => 'QR', 'marketingFlightNumber' => '1184'],
            ],
        ]);
        data_set($fixture, 'groupedItineraryResponse.legDescs', [
            ['ref' => 1, 'schedules' => [['ref' => 1]]],
            ['ref' => 2, 'schedules' => [['ref' => 2]]],
        ]);
        data_set($fixture, 'groupedItineraryResponse.itineraryGroups.0.itineraries.0.id', '2');
        data_set($fixture, 'groupedItineraryResponse.itineraryGroups.0.itineraries.0.legs', [
            ['ref' => 1],
            ['ref' => 2],
        ]);
        data_set($fixture, 'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.fare.validatingCarrierCode', 'QR');
        data_set($fixture, 'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.fare.totalFare.totalPrice', 520.83);
        data_set($fixture, 'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.fare.totalFare.currencyCode', 'USD');
        data_set($fixture, 'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.fare.passengerInfoList.0.passengerInfo.fareComponents.0.segments', [
            ['segment' => ['bookingCode' => 'S', 'fareBasisCode' => 'SLOW1']],
            ['segment' => ['bookingCode' => 'S', 'fareBasisCode' => 'SLOW2']],
        ]);

        return $fixture;
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

        return $conn;
    }

    protected function writePassengerFixture(): string
    {
        $path = storage_path('app/testing-scenario-passenger-linkage.json');
        file_put_contents($path, json_encode([
            'title' => 'MR',
            'given_name' => 'Test',
            'surname' => 'Traveler',
            'gender' => 'M',
            'dob' => '1997-10-10',
            'nationality' => 'PK',
            'country' => 'PK',
            'passport_number' => 'AB1234567',
            'passport_issue_date' => '2020-10-10',
            'passport_expiry_date' => '2030-10-10',
            'phone' => '+923001234567',
            'email' => 'booker@example.com',
        ], JSON_PRETTY_PRINT));

        return $path;
    }
}
