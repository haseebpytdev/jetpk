<?php

namespace Tests\Feature;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Support\Sabre\GdsPnrCreate\SabreGdsAutoPnrContextCompletionService;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyRegistry;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategySelector;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRunner;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioMulticityClassifier;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioPresetResolver;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRunnerPassengerLoader;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRunnerPnrExecutor;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SabreGdsLiveScenarioRunnerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');
        parent::tearDown();
    }

    public function test_command_registered(): void
    {
        Artisan::call('list');
        $this->assertStringContainsString('sabre:gds-live-scenario-runner', Artisan::output());
    }

    public function test_plan_mode_creates_no_booking_and_no_pnr(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.ticketing_enabled', false);

        $this->fakeSabreShop();
        $conn = $this->seedSabreConnection();
        $before = Booking::query()->count();

        $exit = Artisan::call('sabre:gds-live-scenario-runner', [
            '--connection' => (string) $conn->id,
            '--departure-date' => '2026-08-15',
            '--mode' => 'plan',
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame($before, Booking::query()->count());
        $output = Artisan::output();
        $this->assertStringContainsString('pnr_attempted=false', $output);
        $this->assertStringNotContainsString('Haseeb', $output);
        $this->assertStringNotContainsString('EX1345432', $output);
    }

    public function test_plan_mode_with_mixed_approval_never_posts_passenger_records(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.ticketing_enabled', false);

        Http::fake(function (Request $request) {
            $url = strtolower($request->url());
            $this->assertStringNotContainsString('passenger/records', $url);

            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            if (str_contains($url, $tokenPath)) {
                return Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, 'v4/offers/shop')) {
                return Http::response($this->shopFixtureWithBookingCode('Y'), 200);
            }

            return Http::response([], 404);
        });

        $conn = $this->seedSabreConnection();
        $before = Booking::query()->count();

        $exit = Artisan::call('sabre:gds-live-scenario-runner', [
            '--connection' => (string) $conn->id,
            '--departure-date' => '2026-08-15',
            '--mode' => 'plan',
            '--preset' => 'mixed-connecting',
            '--mixed-carrier-certification-approval' => SabreGdsLiveScenarioRunner::MIXED_CARRIER_CERTIFICATION_APPROVAL_PHRASE,
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame($before, Booking::query()->count());
        $this->assertStringContainsString('pnr_attempted=false', Artisan::output());
    }

    public function test_book_mode_creates_one_booking_and_one_supplier_booking_attempt(): void
    {
        $this->configureScenarioRunnerSabre();
        $this->fakeSabreShopAndPnr();
        $conn = $this->seedSabreConnection();
        $passengerPath = $this->writePassengerFixture();
        $before = Booking::query()->count();
        $attemptsBefore = SupplierBookingAttempt::query()->count();

        $exit = Artisan::call('sabre:gds-live-scenario-runner', [
            '--connection' => (string) $conn->id,
            '--departure-date' => '2026-08-15',
            '--mode' => 'book',
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
            '--passenger-json' => $passengerPath,
            '--max-bookings' => '1',
        ]);

        $output = Artisan::output();
        $this->assertSame(0, $exit, $output);
        $this->assertSame($before + 1, Booking::query()->count());
        $this->assertSame($attemptsBefore + 1, SupplierBookingAttempt::query()->count());
        $this->assertStringContainsString('booking_id=', $output);
        $this->assertStringContainsString('scenario_live_pnr_create_approved=true', $output);
        $this->assertStringContainsString('selected_strategy=', $output);
        $this->assertStringContainsString('live_call_attempted=true', $output);
        $this->assertStringContainsString('pnr_attempted=true', $output);
        $this->assertStringContainsString('ticketing_attempted=false', $output);
        $this->assertStringContainsString('airticket_attempted=false', $output);
        $this->assertStringNotContainsString('public_review_dry_run_failed', $output);

        $booking = Booking::query()->orderByDesc('id')->first();
        $this->assertNotNull($booking);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertIsArray($meta['sabre_checkout_outcome'] ?? null);
    }

    public function test_public_auto_pnr_enabled_false_does_not_block_scenario_runner_with_approval(): void
    {
        $this->configureScenarioRunnerSabre();
        Config::set('suppliers.sabre.certified_route_selector_public_checkout_enabled', false);
        Config::set('suppliers.sabre.public_auto_pnr_enabled', false);

        $booking = $this->makePkDirectStaleFareScenarioBooking();
        $this->fakeSabreShopAndPnr();
        $attemptsBefore = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count();

        $result = app(SabreGdsLiveScenarioRunnerPnrExecutor::class)->execute($booking, true);

        $this->assertTrue(($result['scenario_live_pnr_create_approved'] ?? false) === true);
        $this->assertNotSame('scenario_runner_operator_approval_missing', $result['reason_code'] ?? null);
        $this->assertNotSame('public_review_dry_run_failed', $result['reason_code'] ?? null);
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $result['selected_strategy'] ?? $result['pnr_strategy_used'] ?? null,
        );
        $this->assertTrue(($result['live_call_attempted'] ?? false) === true);
        $this->assertTrue(($result['pnr_attempted'] ?? false) === true);
        $this->assertSame($attemptsBefore + 1, SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count());
        $this->assertFalse($result['ticketing_attempted'] ?? true);
        $this->assertFalse($result['airticket_attempted'] ?? true);
    }

    public function test_stale_selected_fare_readiness_does_not_block_scenario_runner_strategy_selection(): void
    {
        $this->configureScenarioRunnerSabre();
        $booking = $this->makePkDirectStaleFareScenarioBooking();

        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForScenarioRunner($booking);

        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['selected_strategy'] ?? null,
        );
        $this->assertTrue(($selection['scenario_runner_override_applied'] ?? false) === true);
        $this->assertContains(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['eligible_strategies'] ?? [],
        );
        $this->assertNotContains(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
            $selection['eligible_strategies'] ?? [],
        );
    }

    public function test_explicit_strategy_option_forces_iati_like_cpnr(): void
    {
        $this->configureScenarioRunnerSabre();
        $booking = $this->makePkDirectStaleFareScenarioBooking();

        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForScenarioRunner($booking, [
            'strategy' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
        ]);

        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['selected_strategy'] ?? null,
        );
        $this->assertSame('iati_like_cpnr_v2_4_gds', $selection['strategy_option'] ?? null);
    }

    public function test_iati_scenario_runner_skips_bfm_revalidation_when_revalidation_enabled(): void
    {
        $this->configureScenarioRunnerSabre();
        Config::set('suppliers.sabre.revalidate_before_booking', true);
        Config::set('suppliers.sabre.pnr_only_waive_mandatory_revalidation', false);

        $revalidationCalled = false;
        Http::fake(function (Request $request, array $options) use (&$revalidationCalled) {
            $url = strtolower($request->url());
            $payload = $options['laravel_data'] ?? [];
            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            if (str_contains($url, $tokenPath) || (is_array($payload) && array_key_exists('grant_type', $payload))) {
                return Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, 'revalidate') || str_contains($url, 'offers/shop')) {
                $revalidationCalled = true;

                return Http::response([], 400);
            }
            if (str_contains($url, 'passenger/records')) {
                $this->assertStringContainsString('v2.4.0', $url);

                return Http::response([
                    'CreatePassengerNameRecordRS' => [
                        'ApplicationResults' => ['status' => 'Complete'],
                        'ItineraryRef' => ['ID' => 'SCNR02'],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $booking = $this->makePkDirectStaleFareScenarioBooking();
        $result = app(SabreGdsLiveScenarioRunnerPnrExecutor::class)->execute($booking, true);

        $this->assertFalse($revalidationCalled);
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $result['selected_strategy'] ?? null,
        );
        $this->assertTrue(($result['live_call_attempted'] ?? false) === true);
        $this->assertFalse($result['revalidation_attempted'] ?? true);
        $this->assertSame(1, SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count());
    }

    public function test_scenario_runner_override_persisted_in_safe_summary_and_checkout_outcome(): void
    {
        $this->configureScenarioRunnerSabre();
        $this->fakeSabreShopAndPnr();
        $booking = $this->makePkDirectStaleFareScenarioBooking();

        $result = app(SabreGdsLiveScenarioRunnerPnrExecutor::class)->execute($booking, true);

        $this->assertTrue(($result['scenario_runner_override_applied'] ?? false) === true);
        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertTrue(data_get($meta, 'sabre_checkout_outcome.scenario_runner_override_applied') === true);
        $this->assertTrue(
            data_get($meta, 'sabre_checkout_outcome.booking_context_summary.scenario_runner_override_applied') === true
            || data_get($meta, 'sabre_checkout_outcome.gds_strategy_selection.scenario_runner_override_applied') === true,
        );

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->latest('id')->first();
        $this->assertNotNull($attempt);
        $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertTrue(
            ($safe['scenario_runner_override_applied'] ?? false) === true
            || data_get($safe, 'gds_strategy_selection.scenario_runner_override_applied') === true,
        );
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $safe['payload_schema'] ?? data_get($safe, 'gds_strategy_selection.selected_strategy'),
        );
    }

    public function test_scenario_runner_no_strategy_emits_candidate_exclusion_diagnostics(): void
    {
        $this->configureScenarioRunnerSabre();
        $booking = $this->makePkDirectStaleFareScenarioBooking([
            'meta' => array_merge($this->pkDirectStaleFareMeta(), [
                'normalized_offer_snapshot' => [
                    'validating_carrier' => 'PK',
                    'supplier_connection_id' => 2,
                    'segments' => [],
                ],
            ]),
        ]);

        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForScenarioRunner($booking);

        $this->assertNull($selection['selected_strategy'] ?? null);
        $diagnostics = is_array($selection['candidate_exclusion_diagnostics'] ?? null)
            ? $selection['candidate_exclusion_diagnostics']
            : [];
        $this->assertNotEmpty($diagnostics);
        $iati = collect($diagnostics)->firstWhere(
            'strategy_code',
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
        );
        $this->assertIsArray($iati);
        $this->assertNotNull($iati['exclusion_reason'] ?? null);
        $this->assertArrayHasKey('context_ready', $iati);
        $this->assertArrayHasKey('sabre_booking_context_ready', $iati);
    }

    public function test_context_completion_persist_mirrors_repaired_readiness_flags(): void
    {
        $this->configureScenarioRunnerSabre();
        $booking = $this->makePkDirectStaleFareScenarioBooking();
        $completion = app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking);
        app(SabreGdsAutoPnrContextCompletionService::class)->persistCompletedContext($booking->fresh(), $completion);

        $meta = is_array($booking->fresh()->meta) ? $booking->fresh()->meta : [];
        $this->assertTrue(data_get($meta, SabreGdsAutoPnrContextCompletionService::META_KEY.'.public_auto_pnr_attempt_ready') === true);
        $this->assertTrue(data_get($meta, 'sabre_booking_context.ready_for_booking_payload') === true);
        $this->assertSame(1, count(data_get($meta, 'sabre_booking_context.booking_classes_by_segment', [])));
        $this->assertSame(1, count(data_get($meta, 'sabre_booking_context.fare_basis_codes_by_segment', [])));
        $this->assertSame(1, count(data_get($meta, 'sabre_booking_context.selected_fare_family_option.booking_classes_by_segment', [])));
        $this->assertSame(1, count(data_get($meta, 'sabre_booking_context.selected_fare_family_option.fare_basis_codes_by_segment', [])));
    }

    public function test_repaired_return_context_reaches_one_pnr_create_attempt(): void
    {
        $this->configureScenarioRunnerSabre();
        $this->fakeSabreShopAndPnr();
        $booking = $this->makeScenarioRunnerReadyReturnBooking();

        $result = app(SabreGdsLiveScenarioRunnerPnrExecutor::class)->execute($booking, true);

        $this->assertSame('repaired', data_get($result, 'auto_pnr_context_completion.auto_pnr_context_completion_status')
            ?? data_get($booking->fresh()->meta, SabreGdsAutoPnrContextCompletionService::META_KEY.'.auto_pnr_context_completion_status'));
        $this->assertTrue(
            ($result['live_call_attempted'] ?? false) === true
            || ($result['reason_code'] ?? '') === SabreGdsLiveScenarioRunnerPnrExecutor::REASON_NO_SELECTED_STRATEGY,
        );
        if (($result['live_call_attempted'] ?? false) === true) {
            $this->assertSame(1, SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count());
            $this->assertNotNull($result['selected_strategy'] ?? $result['pnr_strategy_used'] ?? null);
        }
    }

    public function test_blocked_result_uses_safe_subreason_not_generic_public_review_failure(): void
    {
        $booking = $this->makeScenarioRunnerReadyDirectBooking();
        $result = app(SabreGdsLiveScenarioRunnerPnrExecutor::class)->execute($booking, false);

        $this->assertSame(
            SabreGdsLiveScenarioRunnerPnrExecutor::REASON_OPERATOR_APPROVAL_MISSING,
            $result['reason_code'] ?? null,
        );
        $this->assertNotSame('public_review_dry_run_failed', $result['reason_code'] ?? null);
    }

    public function test_advanced_plan_presets_are_registered(): void
    {
        foreach (['three-stop', 'four-stop', 'mixed-connecting', 'mixed-multistop', 'mixed-return'] as $preset) {
            $this->assertContains($preset, SabreGdsLiveScenarioPresetResolver::PRESET_KEYS);
        }
    }

    public function test_three_stop_plan_preset_does_not_create_booking(): void
    {
        Config::set('app.env', 'testing');
        $this->fakeSabreShop();
        $conn = $this->seedSabreConnection();
        $before = Booking::query()->count();

        $exit = Artisan::call('sabre:gds-live-scenario-runner', [
            '--connection' => (string) $conn->id,
            '--departure-date' => '2026-08-15',
            '--mode' => 'plan',
            '--preset' => 'three-stop',
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame($before, Booking::query()->count());
        $this->assertStringContainsString('pnr_attempted=false', Artisan::output());
    }

    public function test_advanced_plan_preset_blocks_book_mode(): void
    {
        Config::set('app.env', 'testing');
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:gds-live-scenario-runner', [
            '--connection' => (string) $conn->id,
            '--departure-date' => '2026-08-15',
            '--mode' => 'book',
            '--preset' => 'mixed-connecting',
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
            '--passenger-json' => storage_path('app/testing-scenario-passenger.json'),
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('mixed-carrier-certification-approval', Artisan::output());
    }

    public function test_passenger_json_validation_works(): void
    {
        $loader = app(SabreGdsLiveScenarioRunnerPassengerLoader::class);
        $this->expectException(\InvalidArgumentException::class);
        $loader->loadFromPath('/nonexistent/passenger.json');
    }

    public function test_missing_production_approval_blocks_production(): void
    {
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.inspect_commands_allowed', false);

        $exit = Artisan::call('sabre:gds-live-scenario-runner', [
            '--connection' => '2',
            '--departure-date' => '2026-08-15',
            '--mode' => 'plan',
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('production-ops-approval', Artisan::output());
    }

    public function test_no_ticketing_airticket_or_payment_mutation(): void
    {
        $this->configureScenarioRunnerSabre();

        Http::fake(function (Request $request, array $options) {
            $url = strtolower($request->url());
            $this->assertStringNotContainsString('airticket', $url);

            $payload = $options['laravel_data'] ?? [];
            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            if (str_contains($url, $tokenPath) || (is_array($payload) && array_key_exists('grant_type', $payload))) {
                return Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, 'v4/offers/shop')) {
                return Http::response($this->shopFixtureWithBookingCode('Y'), 200);
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

        $conn = $this->seedSabreConnection();
        $passengerPath = $this->writePassengerFixture();

        Artisan::call('sabre:gds-live-scenario-runner', [
            '--connection' => (string) $conn->id,
            '--departure-date' => '2026-08-15',
            '--mode' => 'book',
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
            '--passenger-json' => $passengerPath,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('ticketing_attempted=false', $output);
        $this->assertStringContainsString('airticket_attempted=false', $output);
    }

    public function test_completion_status_persisted_on_book(): void
    {
        $this->configureScenarioRunnerSabre();
        $this->fakeSabreShopAndPnr();
        $conn = $this->seedSabreConnection();
        $passengerPath = $this->writePassengerFixture();

        Artisan::call('sabre:gds-live-scenario-runner', [
            '--connection' => (string) $conn->id,
            '--departure-date' => '2026-08-15',
            '--mode' => 'book',
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
            '--passenger-json' => $passengerPath,
        ]);

        $booking = Booking::query()->orderByDesc('id')->first();
        $this->assertNotNull($booking);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertNotNull(data_get($meta, SabreGdsAutoPnrContextCompletionService::META_KEY.'.auto_pnr_context_completion_status'));
    }

    public function test_output_json_path_exists_and_is_readable(): void
    {
        Config::set('app.env', 'testing');
        Storage::fake('local');
        $this->fakeSabreShop();
        $conn = $this->seedSabreConnection();

        Artisan::call('sabre:gds-live-scenario-runner', [
            '--connection' => (string) $conn->id,
            '--departure-date' => '2026-08-15',
            '--mode' => 'plan',
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
        ]);

        $output = Artisan::output();
        $this->assertMatchesRegularExpression('/output_json_path=.+sabre-gds-scenario-runs.+\.json/', $output);

        $files = Storage::disk('local')->allFiles('sabre-gds-scenario-runs');
        $this->assertNotEmpty($files);
        $json = Storage::disk('local')->get($files[0]);
        $this->assertIsString($json);
        $this->assertStringContainsString('run_id', $json);
    }

    public function test_output_safe_summary_hides_pii_and_secrets(): void
    {
        Config::set('app.env', 'testing');
        Storage::fake('local');
        $this->fakeSabreShop();
        $conn = $this->seedSabreConnection();

        Artisan::call('sabre:gds-live-scenario-runner', [
            '--connection' => (string) $conn->id,
            '--departure-date' => '2026-08-15',
            '--mode' => 'plan',
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
        ]);

        $output = Artisan::output();
        foreach (['Haseeb', 'Asif', 'EX1345432', 'myworkhaseeb@gmail.com', '+92387656789', 'cpnr_ci', 'cpnr_cs', 'fake-token-for-tests-only'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $output, 'Output leaked: '.$forbidden);
        }

        $files = Storage::disk('local')->allFiles('sabre-gds-scenario-runs');
        $this->assertNotEmpty($files);
        $json = Storage::disk('local')->get($files[0]);
        $this->assertIsString($json);
        foreach (['Haseeb', 'EX1345432', 'myworkhaseeb@gmail.com', 'cpnr_ci'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json, 'JSON leaked: '.$forbidden);
        }
    }

    public function test_cancel_mode_refuses_without_cancel_approval(): void
    {
        Config::set('app.env', 'testing');
        $this->fakeSabreShopAndPnr();
        $conn = $this->seedSabreConnection();
        $passengerPath = $this->writePassengerFixture();

        $exit = Artisan::call('sabre:gds-live-scenario-runner', [
            '--connection' => (string) $conn->id,
            '--departure-date' => '2026-08-15',
            '--mode' => 'book-retrieve-and-cancel',
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
            '--passenger-json' => $passengerPath,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('cancel_approval_required', Artisan::output());
    }

    protected function configureScenarioRunnerSabre(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', true);
        Config::set('suppliers.sabre.pnr_create_enabled', true);
        Config::set('suppliers.sabre.revalidate_before_booking', false);
        Config::set('suppliers.sabre.refresh_offer_before_public_pnr', false);
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.certified_route_selector_public_checkout_enabled', false);
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.booking_schema', 'create_passenger_name_record');
        Config::set('suppliers.sabre.pnr_only_waive_mandatory_revalidation', true);
        Config::set('suppliers.sabre.admin_manual_pnr_enabled', true);
    }

    protected function makeScenarioRunnerReadyDirectBooking(): Booking
    {
        $conn = $this->seedSabreConnection();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $snapshot = [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => $conn->id,
            'supplier_offer_id' => 'ek-lhe-dxb-direct-scnr',
            'offer_id' => 'ek-lhe-dxb-direct-scnr',
            'validating_carrier' => 'EK',
            'distribution_channel' => 'gds',
            'total' => 88602,
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'carrier' => 'EK', 'flight_number' => '623', 'departure_at' => '2026-08-15T09:00:00', 'arrival_at' => '2026-08-15T11:30:00', 'booking_class' => 'Y', 'fare_basis_code' => 'YLOWPK'],
            ],
            'fare_breakdown' => ['supplier_total' => 88602, 'currency' => 'PKR', 'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0]],
        ];
        $booking = Booking::query()->create([
            'agency_id' => $agency->id,
            'booking_reference' => 'BR-SCNR-DIRECT',
            'status' => 'pending',
            'supplier' => SupplierProvider::Sabre->value,
            'selected_fare_total' => 88602,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
                'distribution_channel' => 'gds',
                'normalized_offer_snapshot' => $snapshot,
                'selected_fare_family_option' => [
                    'brand_code' => 'ECONOMY',
                    'displayed_price' => 88602,
                    'booking_classes_by_segment' => ['Y'],
                    'fare_basis_codes_by_segment' => ['YLOWPK'],
                ],
                'sabre_booking_context' => [
                    'selected_brand_code' => 'ECONOMY',
                    'validating_carrier' => 'EK',
                    'booking_classes_by_segment' => ['Y'],
                    'fare_basis_codes_by_segment' => ['YLOWPK'],
                    'segment_slice_count' => 1,
                    'ready_for_booking_payload' => true,
                ],
                'search_criteria' => ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => '2026-08-15'],
            ],
        ]);
        $this->seedPassengers($booking, 88602);

        return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makePkDirectStaleFareScenarioBooking(array $overrides = []): Booking
    {
        $conn = $this->seedSabreConnection();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::query()->create(array_merge([
            'agency_id' => $agency->id,
            'booking_reference' => 'BR-SCNR-PK-STALE',
            'status' => 'pending',
            'supplier' => SupplierProvider::Sabre->value,
            'route' => 'LHE-DXB',
            'selected_fare_total' => 82485,
            'meta' => $this->pkDirectStaleFareMeta($conn->id),
        ], $overrides));
        $this->seedPassengers($booking, 82485);

        return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function pkDirectStaleFareMeta(int $connectionId = 2): array
    {
        return [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => $connectionId,
            'distribution_channel' => 'gds',
            'scenario_runner' => true,
            'origin_channel' => 'scenario_runner',
            'selected_fare_family_option' => [
                'brand_code' => 'FL',
                'selectable' => false,
                'ready_for_booking_payload' => false,
                'readiness_reasons' => ['index_only_linkage', 'missing_pricing_ref'],
                'booking_classes_by_segment' => [],
                'fare_basis_codes_by_segment' => [],
            ],
            'sabre_booking_context' => [
                'selected_brand_code' => 'FL',
                'brand_code' => 'FL',
                'validating_carrier' => 'PK',
                'booking_classes_by_segment' => ['V'],
                'fare_basis_codes_by_segment' => ['VOWFL/V'],
                'segment_slice_count' => 1,
                'ready_for_booking_payload' => true,
            ],
            'normalized_offer_snapshot' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $connectionId,
                'supplier_offer_id' => 'pk-lhe-dxb-scnr-stale',
                'offer_id' => 'pk-lhe-dxb-scnr-stale',
                'validating_carrier' => 'PK',
                'distribution_channel' => 'gds',
                'total' => 82485,
                'segments' => [[
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'carrier' => 'PK',
                    'flight_number' => '233',
                    'departure_at' => '2026-08-15T08:00:00',
                    'arrival_at' => '2026-08-15T11:00:00',
                    'booking_class' => 'V',
                    'fare_basis_code' => 'VOWFL/V',
                ]],
                'fare_breakdown' => [
                    'supplier_total' => 82485,
                    'currency' => 'PKR',
                    'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
                ],
            ],
            'search_criteria' => [
                'trip_type' => 'one_way',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-08-15',
            ],
        ];
    }

    protected function makeScenarioRunnerReadyReturnBooking(): Booking
    {
        $conn = $this->seedSabreConnection();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $snapshot = [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => $conn->id,
            'validating_carrier' => 'PK',
            'distribution_channel' => 'gds',
            'total' => 210000,
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'carrier' => 'PK', 'flight_number' => '233', 'departure_at' => '2026-09-01T02:00:00', 'arrival_at' => '2026-09-01T04:30:00', 'booking_class' => 'V', 'fare_basis_code' => 'VNBAG'],
                ['origin' => 'DXB', 'destination' => 'LHE', 'carrier' => 'PK', 'flight_number' => '234', 'departure_at' => '2026-09-08T06:00:00', 'arrival_at' => '2026-09-08T10:30:00', 'booking_class' => 'V', 'fare_basis_code' => 'VNBAG'],
            ],
            'fare_breakdown' => ['supplier_total' => 210000, 'currency' => 'PKR', 'passenger_counts' => ['adults' => 1]],
        ];
        $booking = Booking::query()->create([
            'agency_id' => $agency->id,
            'booking_reference' => 'BR-SCNR-RETURN',
            'status' => 'pending',
            'supplier' => SupplierProvider::Sabre->value,
            'route' => 'LHE-DXB-LHE',
            'selected_fare_total' => 210000,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
                'distribution_channel' => 'gds',
                'normalized_offer_snapshot' => $snapshot,
                'selected_fare_family_option' => [
                    'brand_code' => 'ECONOMY',
                    'displayed_price' => 210000,
                    'booking_classes_by_segment' => ['V', 'V'],
                    'fare_basis_codes_by_segment' => ['VNBAG', 'VNBAG'],
                ],
                'sabre_booking_context' => [
                    'selected_brand_code' => 'ECONOMY',
                    'validating_carrier' => 'PK',
                    'booking_classes_by_segment' => ['V'],
                    'fare_basis_codes_by_segment' => ['VNBAG'],
                    'segment_slice_count' => 2,
                    'ready_for_booking_payload' => true,
                ],
                'search_criteria' => ['trip_type' => 'return', 'origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => '2026-09-01', 'return_date' => '2026-09-08'],
            ],
        ]);
        $this->seedPassengers($booking, 210000);

        return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }

    protected function seedPassengers(Booking $booking, float $total): void
    {
        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_type' => 'adult',
            'first_name' => 'Test',
            'last_name' => 'Traveler',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passport_number' => 'AB1234567',
            'passport_issuing_country' => 'PK',
            'passport_expiry_date' => now()->addYears(5)->toDateString(),
            'nationality' => 'PK',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'booker@example.com',
            'phone' => '3001234567',
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'total' => $total,
            'currency' => 'PKR',
        ]);
    }

    protected function fakeSabreShop(): void
    {
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($this->shopFixtureWithBookingCode('Y'), 200),
        ]);
    }

    protected function fakeSabreShopAndPnr(): void
    {
        Http::fake(function (Request $request, array $options) {
            $url = strtolower($request->url());
            $payload = $options['laravel_data'] ?? [];
            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            if (str_contains($url, $tokenPath) || (is_array($payload) && array_key_exists('grant_type', $payload))) {
                return Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, 'v4/offers/shop')) {
                return Http::response($this->shopFixtureWithBookingCode('Y'), 200);
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

    protected function seedSabreConnection(string $baseUrl = 'https://api.cert.platform.sabre.com'): SupplierConnection
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $conn->base_url = $baseUrl;
        $conn->is_active = true;
        $conn->status = SupplierConnectionStatus::Active;
        $conn->credentials = ['client_id' => 'cpnr_ci', 'client_secret' => 'cpnr_cs', 'pcc' => 'TEST'];
        $conn->save();
        Cache::flush();

        return $conn;
    }

    protected function writePassengerFixture(): string
    {
        $path = storage_path('app/testing-scenario-passenger.json');
        file_put_contents($path, json_encode([
            'title' => 'MR',
            'given_name' => 'Haseeb',
            'surname' => 'Asif',
            'gender' => 'M',
            'dob' => '1997-10-10',
            'nationality' => 'PK',
            'country' => 'PK',
            'passport_number' => 'EX1345432',
            'passport_issue_date' => '2020-10-10',
            'passport_expiry_date' => '2030-10-10',
            'phone' => '+92387656789',
            'email' => 'myworkhaseeb@gmail.com',
        ], JSON_PRETTY_PRINT));

        return $path;
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

        return $shopFixture;
    }

    public function test_multicity_plan_mode_executes_single_shop_and_no_booking(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.ticketing_enabled', false);

        $shopCalls = 0;
        Http::fake(function (Request $request) use (&$shopCalls) {
            $url = strtolower($request->url());
            $this->assertStringNotContainsString('passenger/records', $url);

            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            if (str_contains($url, $tokenPath)) {
                return Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, 'v4/offers/shop')) {
                $shopCalls++;
                $body = $request->data();
                $odis = data_get($body, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation', []);
                $this->assertIsArray($odis);
                $this->assertCount(3, $odis);

                return Http::response(
                    json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_multicity_three_slice_response.json')), true),
                    200,
                );
            }

            return Http::response([], 404);
        });

        $conn = $this->seedSabreConnection();
        $before = Booking::query()->count();
        $inputPath = base_path('tests/Fixtures/sabre/multicity/three_slice_plan_input.json');

        $exit = Artisan::call('sabre:gds-live-scenario-runner', [
            '--connection' => (string) $conn->id,
            '--mode' => 'plan',
            '--preset' => 'multicity',
            '--multicity-json' => $inputPath,
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
        ]);

        $output = Artisan::output();
        $this->assertSame(0, $exit, $output);
        $this->assertSame(1, $shopCalls, 'Expected exactly one true multicity shop POST');
        $this->assertSame($before, Booking::query()->count());
        $this->assertStringContainsString('pnr_attempted=false', $output);
        $this->assertStringContainsString('multicity_search_executed=true', $output);
        $this->assertStringContainsString('multicity_plan_ready=false', $output);
        $this->assertStringContainsString('multicity_block_reason=multicity_all_offers_filtered_by_mixed_carrier_policy', $output);
        $this->assertStringContainsString('customer_message=No same-carrier multi-city fares found', $output);
        $this->assertStringContainsString('admin_debug_message=Sabre returned multi-city offers', $output);
        $this->assertStringContainsString('offers_before_mixed_filter=', $output);
        $this->assertStringContainsString('mixed_carrier_offers_filtered_count=', $output);
        $this->assertStringContainsString('candidate_count=0', $output);
        $this->assertStringNotContainsString('Haseeb', $output);
    }

    public function test_multicity_book_and_retrieve_blocks_before_post(): void
    {
        Config::set('app.env', 'testing');

        Http::fake(function (Request $request) {
            $url = strtolower($request->url());
            $this->assertStringNotContainsString('v4/offers/shop', $url);
            $this->assertStringNotContainsString('passenger/records', $url);

            return Http::response([], 404);
        });

        $conn = $this->seedSabreConnection();
        $inputPath = base_path('tests/Fixtures/sabre/multicity/three_slice_plan_input.json');

        $exit = Artisan::call('sabre:gds-live-scenario-runner', [
            '--connection' => (string) $conn->id,
            '--mode' => 'book-and-retrieve',
            '--preset' => 'multicity',
            '--multicity-json' => $inputPath,
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('plan-only', strtolower(Artisan::output()));
    }

    public function test_multicity_invalid_json_fails_safely(): void
    {
        Config::set('app.env', 'testing');
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:gds-live-scenario-runner', [
            '--connection' => (string) $conn->id,
            '--mode' => 'plan',
            '--preset' => 'multicity',
            '--multicity-json' => '{"slices":[{"origin":"LHE","destination":"DXB","departure_date":"2026-08-01"}]}',
            '--confirm' => SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('multicity_slices_invalid', Artisan::output());
    }

    public function test_multicity_plan_candidates_preserve_slice_grouping_and_classify(): void
    {
        Config::set('app.env', 'testing');

        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response(
                json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_multicity_three_slice_response.json')), true),
                200,
            ),
        ]);

        $conn = $this->seedSabreConnection();
        $runner = app(SabreGdsLiveScenarioRunner::class);
        $summary = $runner->run([
            'connection_id' => $conn->id,
            'mode' => 'plan',
            'preset' => 'multicity',
            'multicity_json' => base_path('tests/Fixtures/sabre/multicity/three_slice_plan_input.json'),
            'operator_approved' => true,
            'plan_only_candidates' => 5,
            'include_mixed_carrier_results' => true,
        ]);

        $result = $summary['scenario_results'][0] ?? [];
        $this->assertSame('multicity', $result['scenario'] ?? null);
        $this->assertTrue(($result['multicity_search_executed'] ?? false) === true);
        $this->assertFalse($result['pnr_attempted'] ?? true);
        $candidates = is_array($result['candidates'] ?? null) ? $result['candidates'] : [];
        $this->assertNotEmpty($candidates);
        $first = $candidates[0];
        $this->assertSame('multicity', $first['trip_type'] ?? null);
        $this->assertSame('multicity', $first['route_shape'] ?? null);
        $this->assertSame(3, $first['slice_count'] ?? 0);
        $this->assertCount(3, $first['requested_slices'] ?? []);
        $this->assertSame(
            SabreGdsLiveScenarioMulticityClassifier::CATEGORY_DISCONTINUOUS,
            $first['classification'] ?? null,
        );
        $this->assertTrue($first['discontinuity_detected'] ?? false);
        $this->assertFalse($first['automatic_booking_allowed'] ?? true);
    }
}
