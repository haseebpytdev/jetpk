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

class SabreGdsLiveScenarioPlanExactOfferEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_output_contains_selected_total_currency_and_fingerprint(): void
    {
        Config::set('app.env', 'testing');
        Storage::fake('local');
        $conn = $this->seedSabreConnection();
        $this->fakeSabreShop();

        Artisan::call('sabre:gds-live-scenario-runner', [
            '--connection' => (string) $conn->id,
            '--departure-date' => '2026-08-15',
            '--mode' => 'plan',
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('selected_total=', $output);
        $this->assertStringContainsString('selected_currency=', $output);
        $this->assertStringContainsString('selected_offer_fingerprint=', $output);
        $this->assertStringContainsString('revalidation_linkage_ready=', $output);

        $files = Storage::disk('local')->allFiles('sabre-gds-scenario-runs');
        $this->assertNotEmpty($files);
        $summary = json_decode((string) Storage::disk('local')->get($files[0]), true);
        $this->assertIsArray($summary);
        $scenario = $summary['scenario_results'][0] ?? [];
        $this->assertIsArray($scenario['candidates'] ?? null);
        $this->assertNotEmpty($scenario['candidates']);
        $first = $scenario['candidates'][0];
        $this->assertNotNull($first['selected_total'] ?? null);
        $this->assertNotSame('', (string) ($first['currency'] ?? ''));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) ($first['safe_offer_fingerprint'] ?? ''));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) ($scenario['selected_offer_fingerprint'] ?? ''));
        $this->assertSame($first['safe_offer_fingerprint'], $scenario['selected_offer_fingerprint']);
    }

    public function test_plan_output_does_not_persist_raw_offer_tokens(): void
    {
        Config::set('app.env', 'testing');
        Storage::fake('local');
        $conn = $this->seedSabreConnection();
        $this->fakeSabreShop();

        Artisan::call('sabre:gds-live-scenario-runner', [
            '--connection' => (string) $conn->id,
            '--departure-date' => '2026-08-15',
            '--mode' => 'plan',
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
        ]);

        $files = Storage::disk('local')->allFiles('sabre-gds-scenario-runs');
        $json = (string) Storage::disk('local')->get($files[0]);
        foreach (['offer-item-test-1', 'FR-REF-1', 'fake-token-for-tests-only', 'raw_payload', 'client_secret'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json, 'Leaked token: '.$forbidden);
        }
    }

    public function test_plan_mode_creates_no_booking_pnr_or_supplier_attempts(): void
    {
        Config::set('app.env', 'testing');
        Storage::fake('local');
        $conn = $this->seedSabreConnection();
        $this->fakeSabreShop();
        $beforeBookings = Booking::query()->count();
        $beforeAttempts = SupplierBookingAttempt::query()->count();

        Artisan::call('sabre:gds-live-scenario-runner', [
            '--connection' => (string) $conn->id,
            '--departure-date' => '2026-08-15',
            '--mode' => 'plan',
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
        ]);

        $this->assertSame($beforeBookings, Booking::query()->count());
        $this->assertSame($beforeAttempts, SupplierBookingAttempt::query()->count());
        $this->assertStringContainsString('pnr_attempted=false', Artisan::output());
    }

    public function test_selected_candidate_fingerprint_matches_revalidation_gate_input(): void
    {
        Config::set('app.env', 'testing');
        $conn = $this->seedSabreConnection();
        $this->fakeSabreShopAndPnr();
        $this->configureScenarioRunnerSabre();

        $holder = new \stdClass;
        $holder->fingerprint = '';
        $gate = new class($holder) extends AlwaysSuccessfulScenarioRevalidationGate {
            public function __construct(private \stdClass $holder)
            {
                parent::__construct();
            }

            public function revalidateSelectedOffer(
                \App\Models\SupplierConnection $connection,
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

        $passengerPath = $this->writePassengerFixture();
        $summary = app(SabreGdsLiveScenarioRunner::class)->run([
            'connection_id' => $conn->id,
            'departure_date' => '2026-08-15',
            'mode' => 'book',
            'operator_approved' => true,
            'passenger_json' => $passengerPath,
            'max_bookings' => 1,
        ]);

        $scenario = $summary['scenario_results'][0] ?? [];
        $this->assertTrue(($scenario['booking_created'] ?? false) === true, json_encode($scenario));
        $this->assertNotSame('', $holder->fingerprint);
        $this->assertSame($holder->fingerprint, $scenario['selected_offer_fingerprint'] ?? null);
    }

    public function test_missing_exact_offer_linkage_blocks_before_booking_creation(): void
    {
        Config::set('app.env', 'testing');
        $conn = $this->seedSabreConnection();
        $this->configureScenarioRunnerSabre();
        $this->fakeSabreShopAndPnr();

        $this->app->bind(SabreGdsLiveScenarioExactOfferEvidence::class, function () {
            return new class(app(\App\Services\Suppliers\Sabre\Gds\SabreStoredPricingContextDigest::class)) extends SabreGdsLiveScenarioExactOfferEvidence {
                public function buildLinkageContext(
                    \App\Models\SupplierConnection $connection,
                    array $snap,
                    array $row,
                    ?array $selectedFareFamilyOption = null,
                    ?string $shopCapturedAt = null,
                ): array {
                    $evidence = parent::buildLinkageContext($connection, $snap, $row, $selectedFareFamilyOption, $shopCapturedAt);
                    $evidence['revalidation_linkage_ready'] = false;
                    $evidence['offer_identifier_present'] = false;
                    $evidence['revalidation_linkage_missing_components'] = [
                        self::MISSING_SOURCE_IDENTIFIER_HASH,
                    ];

                    return $evidence;
                }
            };
        });
        $this->app->forgetInstance(SabreGdsLiveScenarioRunner::class);

        $before = Booking::query()->count();
        $summary = app(SabreGdsLiveScenarioRunner::class)->run([
            'connection_id' => $conn->id,
            'departure_date' => '2026-08-15',
            'mode' => 'book',
            'operator_approved' => true,
            'passenger_json' => $this->writePassengerFixture(),
            'max_bookings' => 1,
        ]);

        $this->assertSame($before, Booking::query()->count());
        $scenario = $summary['scenario_results'][0] ?? [];
        $this->assertSame(
            SabreGdsLiveScenarioExactOfferEvidence::REASON_EXACT_OFFER_LINKAGE_UNAVAILABLE,
            $scenario['error'] ?? null,
        );
        $this->assertFalse($scenario['booking_created'] ?? true);
        $this->assertFalse($scenario['pnr_attempted'] ?? true);
    }

    public function test_changed_fingerprint_blocks_before_booking_creation(): void
    {
        Config::set('app.env', 'testing');
        $conn = $this->seedSabreConnection();
        $this->configureScenarioRunnerSabre();
        $this->fakeSabreShopAndPnr();

        $callCount = 0;
        $this->app->bind(SabreGdsLiveScenarioExactOfferEvidence::class, function () use (&$callCount) {
            return new class(app(\App\Services\Suppliers\Sabre\Gds\SabreStoredPricingContextDigest::class), $callCount) extends SabreGdsLiveScenarioExactOfferEvidence {
                public function __construct(
                    \App\Services\Suppliers\Sabre\Gds\SabreStoredPricingContextDigest $pricingDigest,
                    private int &$callCount,
                ) {
                    parent::__construct($pricingDigest);
                }

                public function buildLinkageContext(
                    \App\Models\SupplierConnection $connection,
                    array $snap,
                    array $row,
                    ?array $selectedFareFamilyOption = null,
                    ?string $shopCapturedAt = null,
                ): array {
                    $this->callCount++;
                    $context = parent::buildLinkageContext($connection, $snap, $row, $selectedFareFamilyOption, $shopCapturedAt);
                    if ($this->callCount >= 2) {
                        $context['safe_offer_fingerprint'] = str_repeat('b', 64);
                    }

                    return $context;
                }
            };
        });
        $this->app->forgetInstance(SabreGdsLiveScenarioRunner::class);

        $before = Booking::query()->count();
        $summary = app(SabreGdsLiveScenarioRunner::class)->run([
            'connection_id' => $conn->id,
            'departure_date' => '2026-08-15',
            'mode' => 'book',
            'operator_approved' => true,
            'passenger_json' => $this->writePassengerFixture(),
            'max_bookings' => 1,
        ]);

        $this->assertSame($before, Booking::query()->count());
        $scenario = $summary['scenario_results'][0] ?? [];
        $this->assertSame(
            SabreGdsLiveScenarioExactOfferEvidence::REASON_EXACT_OFFER_FINGERPRINT_MISMATCH,
            $scenario['error'] ?? null,
        );
        $this->assertFalse($scenario['booking_created'] ?? true);
    }

    protected function configureScenarioRunnerSabre(): void
    {
        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', true);
        Config::set('suppliers.sabre.pnr_create_enabled', true);
        Config::set('suppliers.sabre.revalidate_before_booking', true);
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.pnr_only_waive_mandatory_revalidation', true);
        Config::set('suppliers.sabre.admin_manual_pnr_enabled', true);

        $this->app->instance(SabreGdsLiveScenarioRevalidationGate::class, new AlwaysSuccessfulScenarioRevalidationGate);
        $this->app->forgetInstance(SabreGdsLiveScenarioRunner::class);
    }

    protected function fakeSabreShop(): void
    {
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($this->shopFixtureWithBookingCode('Y'), 200),
        ]);
    }

    protected function fakeSabreShopAndPnr(?float $revalidateTotal = 450.5): void
    {
        Http::fake(function (Request $request, array $options) use ($revalidateTotal) {
            $url = strtolower($request->url());
            $payload = $options['laravel_data'] ?? [];
            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            if (str_contains($url, $tokenPath) || (is_array($payload) && array_key_exists('grant_type', $payload))) {
                return Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, 'v4/offers/shop')) {
                return Http::response($this->shopFixtureWithBookingCode('Y'), 200);
            }
            if (str_contains($url, 'revalidate')) {
                return Http::response([
                    'pricedItineraries' => [[
                        'airItineraryPricingInfo' => [
                            'fareInfos' => [[
                                'fareBasisCode' => 'YOWPK',
                                'departureAirport' => 'LHE',
                                'arrivalAirport' => 'DXB',
                                'bookingCode' => 'Y',
                            ]],
                            'validatingCarrier' => 'PK',
                            'itinTotalFare' => [
                                'totalFare' => ['totalPrice' => $revalidateTotal, 'currencyCode' => 'PKR'],
                            ],
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
    protected function shopFixtureWithBookingCode(string $bookingCode = 'Y'): array
    {
        $shopFixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')),
            true,
        );
        $this->assertIsArray($shopFixture);
        data_set($shopFixture, 'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.fare.bookingCode', $bookingCode);
        data_set($shopFixture, 'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.fare.passengerInfoList.0.passengerInfo.fareComponents.0.segments.0.segment.bookingCode', $bookingCode);

        return $shopFixture;
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
        $path = storage_path('app/testing-scenario-passenger-exact-offer.json');
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
