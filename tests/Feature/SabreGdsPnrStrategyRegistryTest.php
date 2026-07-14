<?php

namespace Tests\Feature;

use App\Console\Commands\SabreGdsPnrCreateWithStrategyCommand;
use App\Console\Commands\SabreGdsPnrStrategyDigestCommand;
use App\Enums\SupplierProvider;
use App\Enums\SupplierConnectionStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Models\SabreGdsPnrCreateStrategyEvidence;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Support\Bookings\SabreAdminManualPnrFallbackReadiness;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreOperationalPnrReadiness;
use App\Support\Bookings\SabrePassengerRecordsItineraryGuardPolicy;
use App\Support\Bookings\SabrePnrCertificationSupport;
use App\Support\Sabre\GdsPnrCreate\SabreGdsAutoPnrContextCompletionService;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyDigest;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyEvidenceRecorder;
use App\Support\Sabre\GdsPnrCreate\SabreGdsMixedCarrierCertificationGate;
use App\Support\Sabre\GdsPnrCreate\SabreGdsMixedCarrierFareBasisPayloadPreflight;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyRegistry;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyResultClassifier;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategySelector;
use App\Support\Sabre\GdsPnrCreate\SabreGdsOneWayTripShapeClassifier;
use App\Support\Sabre\GdsPnrCreate\SabreGdsReturnTripClassifier;
use App\Support\Sabre\GdsPnrCreate\SabreConnectingBrandedFarePublicAutoCertification;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioPlanCandidateDiagnostics;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRunnerPnrExecutor;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioPresetResolver;
use App\Support\Suppliers\SupplierPnrFlagGate;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class SabreGdsPnrStrategyRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->configureOperationalFlags();
    }

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');
        Mockery::close();
        parent::tearDown();
    }

    public function test_strategy_registry_returns_all_supported_strategy_codes(): void
    {
        $registry = app(SabreGdsPnrCreateStrategyRegistry::class);

        $this->assertSame([
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_MINIMAL_AIRBOOK_AIRPRICE_ENDTRANSACTION_GDS,
        ], $registry->supportedCodes());
    }

    public function test_strategy_digest_builds_safe_summaries_for_all_strategies(): void
    {
        $booking = $this->makeDirectPkBooking();
        $candidates = app(SabreGdsPnrCreateStrategyDigest::class)->buildCandidateDigests($booking);

        $this->assertCount(4, $candidates);
        foreach ($candidates as $candidate) {
            $this->assertArrayHasKey('strategy_code', $candidate);
            $this->assertArrayHasKey('context_ready', $candidate);
            $this->assertArrayHasKey('fare_basis_present', $candidate);
            $this->assertArrayHasKey('validating_carrier_present', $candidate);
        }
    }

    public function test_digest_redacts_forbidden_output_keys(): void
    {
        $booking = $this->makeDirectPkBooking();
        $output = json_encode(app(SabreGdsPnrCreateStrategyDigest::class)->buildCandidateDigests($booking));
        $this->assertIsString($output);
        $this->assertStringNotContainsString('createpassengernamerecordrq', strtolower($output));
        $this->assertStringNotContainsString('passport', strtolower($output));
        $this->assertStringNotContainsString('targetcity', strtolower($output));
    }

    public function test_selector_chooses_known_good_iati_when_stronger_than_traditional(): void
    {
        $booking = $this->makeDirectPkBooking();
        SabreGdsPnrCreateStrategyEvidence::query()->create([
            'supplier_connection_id' => 2,
            'provider' => 'sabre',
            'distribution_channel' => 'gds',
            'strategy_code' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            'endpoint_path' => '/v2.5.0/passenger/records?mode=create',
            'payload_schema' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            'carrier_chain' => 'PK',
            'validating_carrier' => 'PK',
            'route_pattern' => 'one_way_direct_same_carrier',
            'trip_type' => 'one_way_direct',
            'segment_count' => 1,
            'outcome' => SabreGdsPnrCreateStrategyEvidence::OUTCOME_SUCCESS,
            'success_count' => 2,
            'last_success_at' => now(),
            'last_success_booking_id' => $booking->id,
        ]);

        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForBooking($booking);

        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['selected_strategy'],
        );
        $this->assertSame(SabreGdsPnrCreateStrategySelector::REASON_KNOWN_GOOD, $selection['selection_reason']);
        $this->assertSame(
            SabreGdsPnrCreateStrategySelector::TRADITIONAL_NOT_SELECTED_MIXED_SUCCESS,
            $selection['traditional_not_selected_reason'],
        );
    }

    public function test_selector_does_not_auto_try_all_strategies(): void
    {
        $booking = $this->makeDirectPkBooking();
        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForBooking($booking);

        $this->assertNotNull($selection['selected_strategy']);
        $this->assertCount(1, [$selection['selected_strategy']]);
        $this->assertGreaterThanOrEqual(1, count($selection['eligible_strategies']));
    }

    public function test_previous_enhanced_airbook_format_prevents_same_strategy_auto_retry(): void
    {
        $booking = $this->makeDirectPkBooking();
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'failed',
            'attempted_at' => now(),
            'completed_at' => now(),
            'safe_summary' => [
                'payload_schema' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
                'response_error_messages' => ['EnhancedAirBookRQ: FORMAT'],
            ],
        ]);

        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForBooking($booking);

        $this->assertNotSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['selected_strategy'],
        );
        $this->assertTrue($selection['fallback_available']);
    }

    public function test_fallback_command_requires_explicit_confirmation(): void
    {
        $booking = $this->makeDirectPkBooking();

        $this->artisan('sabre:gds-pnr-create-with-strategy', [
            '--booking' => (string) $booking->id,
            '--strategy' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
        ])->expectsOutputToContain('--confirm='.SabreGdsPnrCreateWithStrategyCommand::CONFIRM_PHRASE)
            ->assertExitCode(1);
    }

    public function test_fallback_command_refuses_when_booking_already_has_pnr(): void
    {
        $booking = $this->makeDirectPkBooking(['pnr' => 'ABC123']);
        $this->seedFailedAttempt($booking);

        $this->artisan('sabre:gds-pnr-create-with-strategy', [
            '--booking' => (string) $booking->id,
            '--strategy' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            '--confirm' => SabreGdsPnrCreateWithStrategyCommand::CONFIRM_PHRASE,
        ])->expectsOutputToContain('preflight_passed=false')
            ->expectsOutputToContain('live_supplier_call_attempted=false')
            ->expectsOutputToContain('no_pnr')
            ->assertExitCode(1);
    }

    public function test_fallback_command_refuses_if_selected_fare_context_inconsistent(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::query()->create([
            'agency_id' => $agency->id,
            'booking_reference' => 'BR-INCONSISTENT',
            'status' => 'pending',
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => 2,
                'selected_fare_family_option' => ['brand_code' => 'SM', 'fare_basis_codes_by_segment' => ['VOWSM/V']],
                'sabre_booking_context' => ['selected_brand_code' => 'LT', 'fare_basis_codes_by_segment' => ['VOWLT/V']],
                'normalized_offer_snapshot' => $this->directPkSnapshot(),
            ],
        ]);
        $this->seedPassengers($booking);
        $this->seedFailedAttempt($booking);

        $this->artisan('sabre:gds-pnr-create-with-strategy', [
            '--booking' => (string) $booking->id,
            '--strategy' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            '--confirm' => SabreGdsPnrCreateWithStrategyCommand::CONFIRM_PHRASE,
        ])->expectsOutputToContain('preflight_passed=false')
            ->expectsOutputToContain('live_supplier_call_attempted=false')
            ->expectsOutputToContain('fare_context_consistent')
            ->assertExitCode(1);
    }

    public function test_fallback_command_blocked_in_production_without_ops_approval(): void
    {
        Config::set('app.env', 'production');
        $booking = $this->makeDirectPkBooking();
        $this->seedFailedAttempt($booking);

        $this->artisan('sabre:gds-pnr-create-with-strategy', [
            '--booking' => (string) $booking->id,
            '--strategy' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            '--confirm' => SabreGdsPnrCreateWithStrategyCommand::CONFIRM_PHRASE,
        ])->expectsOutputToContain(
            'Production PNR create requires --production-ops-approval='.SabreGdsPnrCreateWithStrategyCommand::PRODUCTION_OPS_APPROVAL_PHRASE
        )->assertExitCode(1);
    }

    public function test_fallback_command_blocked_in_production_with_wrong_ops_approval(): void
    {
        Config::set('app.env', 'production');
        $booking = $this->makeDirectPkBooking();
        $this->seedFailedAttempt($booking);

        $this->artisan('sabre:gds-pnr-create-with-strategy', [
            '--booking' => (string) $booking->id,
            '--strategy' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            '--confirm' => SabreGdsPnrCreateWithStrategyCommand::CONFIRM_PHRASE,
            '--production-ops-approval' => 'WRONG-PHRASE',
        ])->expectsOutputToContain('Invalid --production-ops-approval phrase for production PNR create.')
            ->assertExitCode(1);
    }

    public function test_fallback_command_allowed_in_production_with_confirm_and_ops_approval(): void
    {
        Config::set('app.env', 'production');
        $booking = $this->makeDirectPkBooking();
        $this->seedFailedAttempt($booking);
        $strategy = SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1;

        $realBookingService = app(SabreBookingService::class);
        $mock = Mockery::mock(SabreBookingService::class);
        $mock->shouldReceive('inspectGdsPnrPayloadIntegrityForCommand')
            ->andReturnUsing(fn (...$args) => $realBookingService->inspectGdsPnrPayloadIntegrityForCommand(...$args));
        $mock->shouldReceive('buildGdsPnrStrategyWireContext')
            ->andReturnUsing(fn (...$args) => $realBookingService->buildGdsPnrStrategyWireContext(...$args));
        $mock->shouldReceive('passengerDataFromBookingForCommand')
            ->never();
        $mock->shouldReceive('createBookingWithStrategyForAdminFallback')
            ->once()
            ->withArgs(function ($passedBooking, $passedStrategy) use ($booking, $strategy) {
                return $passedBooking->id === $booking->id && $passedStrategy === $strategy;
            })
            ->andReturn([
                'success' => true,
                'live_call_attempted' => true,
                'preflight_passed' => true,
                'status' => 'confirmed',
                'pnr' => 'ABC123',
                'reason_code' => '',
                'blocking_conditions' => [],
            ]);
        $this->app->instance(SabreBookingService::class, $mock);

        $this->artisan('sabre:gds-pnr-create-with-strategy', [
            '--booking' => (string) $booking->id,
            '--strategy' => $strategy,
            '--confirm' => SabreGdsPnrCreateWithStrategyCommand::CONFIRM_PHRASE,
            '--production-ops-approval' => SabreGdsPnrCreateWithStrategyCommand::PRODUCTION_OPS_APPROVAL_PHRASE,
        ])->expectsOutputToContain('production_ops_approved=true')
            ->expectsOutputToContain('preflight_passed=true')
            ->expectsOutputToContain('live_supplier_call_attempted=true')
            ->expectsOutputToContain('ticketing_attempted=false')
            ->expectsOutputToContain('cancellation_attempted=false')
            ->expectsOutputToContain('booking_id='.$booking->id)
            ->expectsOutputToContain('strategy='.$strategy)
            ->assertExitCode(0);
    }

    public function test_fallback_command_refuses_when_booking_already_has_supplier_reference(): void
    {
        $booking = $this->makeDirectPkBooking(['supplier_reference' => 'SUP-REF-EXISTING']);
        $this->seedFailedAttempt($booking);

        $this->artisan('sabre:gds-pnr-create-with-strategy', [
            '--booking' => (string) $booking->id,
            '--strategy' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            '--confirm' => SabreGdsPnrCreateWithStrategyCommand::CONFIRM_PHRASE,
        ])->expectsOutputToContain('preflight_passed=false')
            ->expectsOutputToContain('no_supplier_reference')
            ->assertExitCode(1);
    }

    public function test_fallback_command_does_not_attempt_ticketing_or_cancellation(): void
    {
        Config::set('app.env', 'production');
        $booking = $this->makeDirectPkBooking();
        $this->seedFailedAttempt($booking);
        $strategy = SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1;

        $realBookingService = app(SabreBookingService::class);
        $mock = Mockery::mock(SabreBookingService::class);
        $mock->shouldReceive('inspectGdsPnrPayloadIntegrityForCommand')
            ->andReturnUsing(fn (...$args) => $realBookingService->inspectGdsPnrPayloadIntegrityForCommand(...$args));
        $mock->shouldReceive('buildGdsPnrStrategyWireContext')
            ->andReturnUsing(fn (...$args) => $realBookingService->buildGdsPnrStrategyWireContext(...$args));
        $mock->shouldReceive('createBookingWithStrategyForAdminFallback')->once()->andReturn([
            'success' => true,
            'live_call_attempted' => true,
            'preflight_passed' => true,
            'status' => 'confirmed',
            'pnr' => 'ABC123',
            'reason_code' => '',
            'blocking_conditions' => [],
        ]);
        $mock->shouldNotReceive('issueTicket');
        $mock->shouldNotReceive('cancelBooking');
        $this->app->instance(SabreBookingService::class, $mock);

        $this->artisan('sabre:gds-pnr-create-with-strategy', [
            '--booking' => (string) $booking->id,
            '--strategy' => $strategy,
            '--confirm' => SabreGdsPnrCreateWithStrategyCommand::CONFIRM_PHRASE,
            '--production-ops-approval' => SabreGdsPnrCreateWithStrategyCommand::PRODUCTION_OPS_APPROVAL_PHRASE,
        ])->expectsOutputToContain('ticketing_attempted=false')
            ->expectsOutputToContain('cancellation_attempted=false')
            ->assertExitCode(0);
    }

    public function test_successful_strategy_stores_known_good_safe_evidence(): void
    {
        $booking = $this->makeDirectPkBooking();
        app(SabreGdsPnrCreateStrategyEvidenceRecorder::class)->recordSuccess(
            $booking,
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            ['payload_schema' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1, 'pnr' => 'ABC123'],
        );

        $row = SabreGdsPnrCreateStrategyEvidence::query()->first();
        $this->assertNotNull($row);
        $this->assertSame(SabreGdsPnrCreateStrategyEvidence::OUTCOME_SUCCESS, $row->outcome);
        $this->assertSame('PK', $row->validating_carrier);
        $this->assertSame(1, $row->success_count);
    }

    public function test_failed_strategy_stores_safe_failure_evidence(): void
    {
        $booking = $this->makeDirectPkBooking();
        app(SabreGdsPnrCreateStrategyEvidenceRecorder::class)->recordFailure(
            $booking,
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            [
                'payload_schema' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
                'response_error_messages' => ['EnhancedAirBookRQ: FORMAT'],
            ],
        );

        $row = SabreGdsPnrCreateStrategyEvidence::query()->first();
        $this->assertNotNull($row);
        $this->assertSame(SabreGdsPnrCreateStrategyEvidence::OUTCOME_FAILURE, $row->outcome);
        $this->assertSame('ENHANCED_AIRBOOK_FORMAT', $row->host_error_family);
    }

    public function test_enhanced_airbook_format_classified_correctly(): void
    {
        $classified = app(SabreGdsPnrCreateStrategyResultClassifier::class)->classify([
            'response_error_messages' => ['EnhancedAirBookRQ: FORMAT'],
        ]);

        $this->assertSame('sabre_enhanced_airbook_format_error', $classified['safe_reason_code']);
        $this->assertSame('ENHANCED_AIRBOOK_FORMAT', $classified['host_error_family']);
        $this->assertSame('admin_confirmed_fallback_only', $classified['retry_policy']);
    }

    public function test_supplier_attempt_safe_summary_agrees_with_generated_payload(): void
    {
        $booking = $this->makeDirectPkBooking();
        $context = app(SabreBookingService::class)->buildGdsPnrStrategyWireContext($booking);
        $this->assertTrue($context['valid']);

        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = $builder->buildPassengerRecordsCpnrWireForStyle(
            $context['api_draft'],
            $context['hints'],
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
        );
        $diag = $builder->summarizeEnvelopeForDiagnostics($wire);

        $this->assertTrue((bool) ($diag['has_fare_basis'] ?? false) || trim((string) ($context['api_draft']['segments'][0]['fare_basis_code'] ?? '')) !== '');
        $this->assertTrue((bool) ($diag['has_validating_carrier'] ?? false));
    }

    public function test_new_booking_selected_fare_total_equals_branded_total(): void
    {
        $booking = $this->makeDirectPkBooking();
        $this->assertSame(82485.0, (float) $booking->selected_fare_total);
        $this->assertSame(82485.0, (float) $booking->revalidated_fare_total);
    }

    public function test_one_segment_direct_pk_itinerary_is_same_carrier_true(): void
    {
        $booking = $this->makeDirectPkBooking();
        $result = app(SabreOperationalPnrReadiness::class)->evaluate($booking);

        $this->assertTrue($result['same_carrier']);
        $this->assertFalse($result['mixed_carrier']);
        $this->assertNotContains('same_carrier_connecting', $result['blocking_conditions']);
    }

    public function test_digest_command_runs_readonly(): void
    {
        $booking = $this->makeDirectPkBooking();

        $this->artisan('sabre:gds-pnr-strategy-digest', ['--booking' => (string) $booking->id])
            ->expectsOutputToContain('live_supplier_call_attempted=false')
            ->expectsOutputToContain('candidate[0]')
            ->expectsOutputToContain('selected_strategy=')
            ->assertExitCode(0);
    }

    public function test_digest_command_production_requires_confirm(): void
    {
        Config::set('app.env', 'production');

        $this->artisan('sabre:gds-pnr-strategy-digest', ['--booking' => '1'])
            ->expectsOutputToContain(SabreGdsPnrStrategyDigestCommand::PRODUCTION_READONLY_CONFIRM_PHRASE)
            ->assertExitCode(1);
    }

    public function test_admin_fallback_readiness_does_not_require_operational_auto_pnr_flag(): void
    {
        Config::set('suppliers.sabre.verified_multiseg_auto_pnr_enabled', false);
        Config::set('suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled', false);
        Config::set('suppliers.sabre.pnr_create_enabled', true);
        Config::set('suppliers.sabre.admin_manual_pnr_enabled', true);

        $booking = $this->makeDirectPkBooking();
        $this->seedFailedAttempt($booking);
        $strategy = SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS;

        $readiness = app(SabreAdminManualPnrFallbackReadiness::class)->evaluate($booking, $strategy);

        $this->assertTrue($readiness['allowed']);
        $this->assertNotContains('operational_auto_pnr_enabled', $readiness['blocking_conditions']);
    }

    public function test_admin_fallback_readiness_does_not_require_public_auto_pnr_or_ticketing(): void
    {
        Config::set('suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled', false);
        Config::set('suppliers.sabre.ticketing_enabled', true);
        Config::set('suppliers.sabre.pnr_create_enabled', true);

        $booking = $this->makeDirectPkBooking();
        $this->seedFailedAttempt($booking);
        $readiness = app(SabreAdminManualPnrFallbackReadiness::class)->evaluate(
            $booking,
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
        );

        $this->assertTrue($readiness['allowed']);
        $this->assertNotContains('public_checkout_pnr_enabled', $readiness['blocking_conditions']);
        $this->assertNotContains('ticketing_enabled', $readiness['blocking_conditions']);
    }

    public function test_admin_fallback_readiness_requires_pnr_create_enabled(): void
    {
        Config::set('suppliers.sabre.pnr_create_enabled', false);
        Config::set('suppliers.sabre.admin_manual_pnr_enabled', true);

        $booking = $this->makeDirectPkBooking();
        $this->seedFailedAttempt($booking);
        $readiness = app(SabreAdminManualPnrFallbackReadiness::class)->evaluate(
            $booking,
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
        );

        $this->assertFalse($readiness['allowed']);
        $this->assertContains('pnr_create_enabled', $readiness['blocking_conditions']);
    }

    public function test_admin_fallback_readiness_requires_admin_manual_pnr_enabled_when_config_set_false(): void
    {
        Config::set('suppliers.sabre.pnr_create_enabled', true);
        Config::set('suppliers.sabre.admin_manual_pnr_enabled', false);

        $booking = $this->makeDirectPkBooking();
        $this->seedFailedAttempt($booking);
        $readiness = app(SabreAdminManualPnrFallbackReadiness::class)->evaluate(
            $booking,
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
        );

        $this->assertFalse($readiness['allowed']);
        $this->assertContains('admin_manual_pnr_enabled', $readiness['blocking_conditions']);
    }

    public function test_admin_fallback_command_blocks_same_failed_strategy_without_auto_retry(): void
    {
        $booking = $this->makeDirectPkBooking();
        $this->seedFailedAttempt($booking);

        $this->artisan('sabre:gds-pnr-create-with-strategy', [
            '--booking' => (string) $booking->id,
            '--strategy' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            '--confirm' => SabreGdsPnrCreateWithStrategyCommand::CONFIRM_PHRASE,
        ])->expectsOutputToContain('preflight_passed=false')
            ->expectsOutputToContain('strategy_not_same_as_failed')
            ->expectsOutputToContain('live_supplier_call_attempted=false')
            ->assertExitCode(1);
    }

    public function test_admin_fallback_preflight_failure_reports_explicit_reason_and_blocking_conditions(): void
    {
        Config::set('suppliers.sabre.pnr_create_enabled', false);
        $booking = $this->makeDirectPkBooking();
        $this->seedFailedAttempt($booking);

        $this->artisan('sabre:gds-pnr-create-with-strategy', [
            '--booking' => (string) $booking->id,
            '--strategy' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
            '--confirm' => SabreGdsPnrCreateWithStrategyCommand::CONFIRM_PHRASE,
        ])->expectsOutputToContain('preflight_passed=false')
            ->expectsOutputToContain('live_supplier_call_attempted=false')
            ->expectsOutputToContain('reason_code=blocked_by_flags')
            ->expectsOutputToContain('blocking_conditions=')
            ->assertExitCode(1);
    }

    public function test_admin_fallback_honors_requested_strategy_in_service_options(): void
    {
        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', false);
        Config::set('suppliers.sabre.pnr_create_enabled', true);

        $booking = $this->makeDirectPkBooking();
        $this->seedFailedAttempt($booking);
        $strategy = SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS;

        $result = app(SabreBookingService::class)->createBookingWithStrategyForAdminFallback($booking, $strategy);

        $this->assertTrue($result['preflight_passed'] ?? false);
        $this->assertFalse($result['live_call_attempted'] ?? true);
        $payloadSummary = is_array($result['create_payload_safe_summary'] ?? null)
            ? $result['create_payload_safe_summary']
            : (is_array($result['payload_safe_summary'] ?? null) ? $result['payload_safe_summary'] : []);
        $selectedStyle = (string) ($payloadSummary['selected_payload_style'] ?? $result['selected_payload_style'] ?? '');
        $payloadSchema = (string) ($payloadSummary['payload_schema'] ?? $result['payload_schema'] ?? '');
        if ($selectedStyle !== '' || $payloadSchema !== '') {
            $this->assertSame($strategy, $selectedStyle !== '' ? $selectedStyle : $payloadSchema);
        }
    }

    public function test_fresh_direct_pk_selects_iati_without_prior_failure(): void
    {
        $booking = $this->makeDirectPkBooking();
        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForBooking($booking);

        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['selected_strategy'],
        );
        $this->assertSame(
            SabreGdsPnrCreateStrategySelector::REASON_KNOWN_GOOD,
            $selection['selection_reason'],
        );
        $this->assertNotContains(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
            $selection['eligible_strategies'],
        );
        $this->assertContains(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            $selection['blocked_strategies'],
        );
        $this->assertSame(
            SabreGdsPnrCreateStrategySelector::TRADITIONAL_NOT_SELECTED_MIXED_SUCCESS,
            $selection['traditional_not_selected_reason'],
        );
    }

    public function test_passenger_records_v25_not_automatic_for_public_selection(): void
    {
        $booking = $this->makeDirectPkBooking();
        $candidates = app(SabreGdsPnrCreateStrategyDigest::class)->buildCandidateDigests($booking);
        $v25 = collect($candidates)->firstWhere(
            'strategy_code',
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
        );

        $this->assertIsArray($v25);
        $this->assertFalse((bool) ($v25['automatic_allowed'] ?? true));
        $this->assertTrue((bool) ($v25['admin_confirmed_fallback_allowed'] ?? false));
    }

    public function test_successful_attempt_increments_known_good_evidence(): void
    {
        $booking = $this->makeDirectPkBooking();
        $recorder = app(SabreGdsPnrCreateStrategyEvidenceRecorder::class);
        $strategy = SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1;
        $result = ['payload_schema' => $strategy, 'pnr' => 'QWJBFJ'];

        $recorder->recordSuccess($booking, $strategy, $result);
        $recorder->recordSuccess($booking, $strategy, $result);

        $row = SabreGdsPnrCreateStrategyEvidence::query()
            ->where('supplier_connection_id', 2)
            ->where('strategy_code', $strategy)
            ->where('validating_carrier', 'PK')
            ->where('outcome', SabreGdsPnrCreateStrategyEvidence::OUTCOME_SUCCESS)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(2, $row->success_count);
        $this->assertSame($booking->id, $row->last_success_booking_id);
        $this->assertSame('one_way_direct_same_carrier', $row->route_pattern);
        $this->assertSame('PK', $row->carrier_chain);
    }

    public function test_known_good_evidence_selects_iati_over_mixed_traditional(): void
    {
        $booking = $this->makeDirectPkBooking();
        SabreGdsPnrCreateStrategyEvidence::query()->create([
            'supplier_connection_id' => 2,
            'provider' => 'sabre',
            'distribution_channel' => 'gds',
            'strategy_code' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            'endpoint_path' => '/v2.5.0/passenger/records?mode=create',
            'payload_schema' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            'carrier_chain' => 'PK',
            'validating_carrier' => 'PK',
            'route_pattern' => 'one_way_direct_same_carrier',
            'trip_type' => 'one_way_direct',
            'segment_count' => 1,
            'outcome' => SabreGdsPnrCreateStrategyEvidence::OUTCOME_SUCCESS,
            'success_count' => 1,
            'last_success_at' => now(),
            'last_success_booking_id' => 94,
        ]);

        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForBooking($booking);

        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['selected_strategy'],
        );
        $this->assertSame(SabreGdsPnrCreateStrategySelector::REASON_KNOWN_GOOD, $selection['selection_reason']);
        $this->assertIsArray($selection['known_good_strategy_evidence']);
        $this->assertSame(2, $selection['known_good_strategy_evidence']['success_count']);
        $this->assertSame(95, $selection['known_good_strategy_evidence']['last_success_booking_id']);
    }

    public function test_digest_command_shows_iati_selection_and_not_selected_reasons(): void
    {
        $booking = $this->makeDirectPkBooking();

        $this->artisan('sabre:gds-pnr-strategy-digest', ['--booking' => (string) $booking->id])
            ->expectsOutputToContain('selected_strategy='.SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS)
            ->expectsOutputToContain('selection_reason='.SabreGdsPnrCreateStrategySelector::REASON_KNOWN_GOOD)
            ->expectsOutputToContain('passenger_records_v2_5_gds_not_selected_reason='.SabreGdsPnrCreateStrategySelector::V25_NOT_SELECTED_AUTOMATIC_DISABLED)
            ->expectsOutputToContain('traditional_not_selected_reason='.SabreGdsPnrCreateStrategySelector::TRADITIONAL_NOT_SELECTED_MIXED_SUCCESS)
            ->expectsOutputToContain('fallback_available=true')
            ->assertExitCode(0);
    }

    public function test_v25_format_failure_blocks_auto_retry_but_keeps_admin_fallback(): void
    {
        $booking = $this->makeDirectPkBooking();
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'failed',
            'attempted_at' => now(),
            'completed_at' => now(),
            'safe_summary' => [
                'payload_schema' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
                'response_error_messages' => ['EnhancedAirBookRQ: FORMAT'],
            ],
        ]);

        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForBooking($booking);

        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['selected_strategy'],
        );
        $this->assertTrue($selection['fallback_available']);
        $this->assertSame(
            SabreGdsPnrCreateStrategySelector::V25_NOT_SELECTED_PREVIOUS_FORMAT_FAILURE,
            $selection['passenger_records_v2_5_gds_not_selected_reason'],
        );
    }

    public function test_admin_manual_pnr_defaults_enabled_for_command_when_config_missing(): void
    {
        Config::offsetUnset('suppliers.sabre.admin_manual_pnr_enabled');

        $this->assertTrue(app(SupplierPnrFlagGate::class)->sabreAdminManualPnrEnabledForCommand());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeDirectPkBooking(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::query()->create(array_merge([
            'agency_id' => $agency->id,
            'booking_reference' => 'BR-PK-DIRECT-'.uniqid(),
            'status' => 'pending',
            'supplier' => SupplierProvider::Sabre->value,
            'selected_fare_total' => 82485,
            'revalidated_fare_total' => 82485,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => 2,
                'payment_mode' => 'pay_later_booking_request',
                'selected_fare_family_option' => [
                    'brand_code' => 'SM',
                    'displayed_price' => 82485,
                    'baggage_summary' => '20 kg',
                    'fare_basis_codes_by_segment' => ['VOWSM/V'],
                    'booking_classes_by_segment' => ['V'],
                ],
                'sabre_booking_context' => [
                    'selected_brand_code' => 'SM',
                    'brand_code' => 'SM',
                    'fare_basis_codes_by_segment' => ['VOWSM/V'],
                    'booking_classes_by_segment' => ['V'],
                    'baggage' => '20 kg',
                    'validating_carrier' => 'PK',
                    'selected_price_total' => 82485,
                ],
                'normalized_offer_snapshot' => $this->directPkSnapshot(),
                'distribution_channel' => 'gds',
                'fare_option_key' => 'sm-key',
                'search_criteria' => ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => '2026-08-15'],
            ],
        ], $overrides));

        $booking->forceFill(['travel_date' => '2026-08-15'])->save();

        $this->seedPassengers($booking);

        return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function directPkSnapshot(): array
    {
        return [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 2,
            'supplier_offer_id' => 'pk-lhe-dxb-test-offer',
            'offer_id' => 'pk-lhe-dxb-test-offer',
            'validating_carrier' => 'PK',
            'distribution_channel' => 'gds',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'carrier' => 'PK',
                'marketing_carrier' => 'PK',
                'operating_carrier' => 'PK',
                'flight_number' => '233',
                'departure_at' => '2026-08-15T08:00:00',
                'arrival_at' => '2026-08-15T11:00:00',
                'booking_class' => 'V',
                'fare_basis_code' => 'VOWSM/V',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 82485,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
    }

    protected function seedPassengers(Booking $booking): void
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
            'total' => 82485,
            'currency' => 'PKR',
        ]);
    }

    protected function seedFailedAttempt(Booking $booking): void
    {
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'failed',
            'attempted_at' => now(),
            'completed_at' => now(),
            'safe_summary' => [
                'payload_schema' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
                'response_error_messages' => ['EnhancedAirBookRQ: FORMAT'],
            ],
        ]);
    }

    public function test_pk_direct_scenario_runner_selector_ignores_stale_display_option_readiness(): void
    {
        Config::set('suppliers.sabre.public_auto_pnr_enabled', false);
        $booking = $this->makeDirectPkBooking([
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => 2,
                'payment_mode' => 'pay_later_booking_request',
                'scenario_runner' => true,
                'origin_channel' => 'scenario_runner',
                'selected_fare_family_option' => [
                    'brand_code' => 'SM',
                    'selectable' => false,
                    'ready_for_booking_payload' => false,
                    'readiness_reasons' => ['index_only_linkage', 'missing_pricing_ref'],
                    'booking_classes_by_segment' => [],
                    'fare_basis_codes_by_segment' => [],
                ],
                'sabre_booking_context' => [
                    'selected_brand_code' => 'SM',
                    'brand_code' => 'SM',
                    'fare_basis_codes_by_segment' => ['VOWSM/V'],
                    'booking_classes_by_segment' => ['V'],
                    'baggage' => '20 kg',
                    'validating_carrier' => 'PK',
                    'selected_price_total' => 82485,
                    'segment_slice_count' => 1,
                    'ready_for_booking_payload' => true,
                ],
                'normalized_offer_snapshot' => $this->directPkSnapshot(),
                'distribution_channel' => 'gds',
                'fare_option_key' => 'sm-key',
                'search_criteria' => ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => '2026-08-15'],
            ],
        ]);

        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForScenarioRunner($booking);

        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['selected_strategy'],
        );
        $this->assertTrue($selection['scenario_runner_override_applied'] ?? false);
    }

    public function test_pk_direct_scenario_runner_auto_excludes_passenger_records_v25(): void
    {
        Config::set('suppliers.sabre.public_auto_pnr_enabled', false);
        $booking = $this->makeDirectPkBooking([
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => 2,
                'payment_mode' => 'pay_later_booking_request',
                'scenario_runner' => true,
                'origin_channel' => 'scenario_runner',
                'selected_fare_family_option' => [
                    'brand_code' => 'SM',
                    'selectable' => false,
                    'ready_for_booking_payload' => false,
                    'readiness_reasons' => ['index_only_linkage', 'missing_pricing_ref'],
                    'booking_classes_by_segment' => [],
                    'fare_basis_codes_by_segment' => [],
                ],
                'sabre_booking_context' => [
                    'selected_brand_code' => 'SM',
                    'brand_code' => 'SM',
                    'fare_basis_codes_by_segment' => ['VOWSM/V'],
                    'booking_classes_by_segment' => ['V'],
                    'baggage' => '20 kg',
                    'validating_carrier' => 'PK',
                    'selected_price_total' => 82485,
                    'segment_slice_count' => 1,
                    'ready_for_booking_payload' => true,
                ],
                'normalized_offer_snapshot' => $this->directPkSnapshot(),
                'distribution_channel' => 'gds',
                'fare_option_key' => 'sm-key',
                'search_criteria' => ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => '2026-08-15'],
            ],
        ]);

        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForScenarioRunner($booking);

        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['selected_strategy'],
        );
        $this->assertNotContains(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
            $selection['eligible_strategies'] ?? [],
        );
    }

    public function test_return_same_carrier_booking_classifies_not_unknown(): void
    {
        $booking = $this->makeReturnPkBooking();
        $tripType = app(SabrePnrCertificationSupport::class)->detectTripType($booking);

        $this->assertSame(SabreGdsReturnTripClassifier::TRIP_RETURN_SAME_CARRIER, $tripType);
        $digest = app(SabreGdsPnrCreateStrategyDigest::class)->buildBookingSummary($booking);
        $this->assertSame(SabreGdsReturnTripClassifier::TRIP_RETURN_SAME_CARRIER, $digest['trip_type'] ?? null);
        $this->assertSame('LHE-DXB-LHE', $digest['return_origin_destination_pattern'] ?? null);
        $this->assertTrue($digest['return_route_continuity_valid'] ?? false);
        $this->assertTrue($digest['return_chronology_valid'] ?? false);
        $this->assertTrue($digest['return_same_carrier'] ?? false);
    }

    public function test_return_same_carrier_scenario_runner_auto_selects_iati(): void
    {
        Config::set('suppliers.sabre.public_auto_pnr_enabled', false);
        $booking = $this->makeReturnPkBooking([
            'meta' => array_merge($this->returnPkBookingMeta(), [
                'scenario_runner' => true,
                'origin_channel' => 'scenario_runner',
            ]),
        ]);

        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForScenarioRunner($booking);

        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['selected_strategy'],
        );
        $this->assertSame(
            SabreGdsPnrCreateStrategySelector::REASON_SCENARIO_RUNNER_RETURN_SAME_CARRIER_IATI,
            $selection['selection_reason'],
        );
        $this->assertSame(
            '/v2.4.0/passenger/records?mode=create',
            app(SabreGdsPnrCreateStrategyRegistry::class)->endpointPathForStrategy(
                (string) $selection['selected_strategy'],
            ),
        );
        $this->assertTrue($selection['scenario_runner_override_applied'] ?? false);
        $this->assertNotContains(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
            $selection['eligible_strategies'] ?? [],
        );
    }

    public function test_return_same_carrier_iati_context_ready_when_completion_repaired(): void
    {
        $booking = $this->makeReturnPkBooking();
        $completion = app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking);
        $candidates = app(SabreGdsPnrCreateStrategyDigest::class)->buildCandidateDigests($booking, null, [
            'scenario_runner' => true,
            'context_completion' => $completion,
        ]);
        $iati = collect($candidates)->firstWhere(
            'strategy_code',
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
        );
        $this->assertIsArray($iati);
        $this->assertTrue($iati['context_ready'] ?? false);
        $this->assertTrue($iati['required_fields_present'] ?? false);
    }

    public function test_return_mixed_carrier_is_not_automatic_for_scenario_runner(): void
    {
        $booking = $this->makeReturnPkBooking([
            'meta' => array_merge($this->returnPkBookingMeta(), [
                'normalized_offer_snapshot' => $this->returnMixedCarrierSnapshot(),
                'scenario_runner' => true,
                'origin_channel' => 'scenario_runner',
            ]),
        ]);
        $tripType = app(SabrePnrCertificationSupport::class)->detectTripType($booking);
        $this->assertSame(SabreGdsReturnTripClassifier::TRIP_RETURN_MIXED_CARRIER, $tripType);

        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForScenarioRunner($booking);
        $this->assertNull($selection['selected_strategy'] ?? null);
    }

    public function test_scenario_runner_freshness_override_skips_bfm_for_return_iati(): void
    {
        $service = app(SabreBookingService::class);
        $method = new \ReflectionMethod($service, 'applyScenarioRunnerFreshnessOverride');
        $method->setAccessible(true);
        $completion = [
            'public_auto_pnr_attempt_ready' => true,
            'auto_pnr_context_completion_status' => SabreGdsAutoPnrContextCompletionService::STATUS_REPAIRED,
        ];
        $serviceReflection = new \ReflectionClass($service);
        $styleProp = $serviceReflection->getProperty('attemptPassengerRecordsStyleDecision');
        $styleProp->setAccessible(true);
        $styleProp->setValue($service, ['iati_like_selected' => true]);

        $result = $method->invoke($service, [
            'revalidation_required' => true,
            'blocks_booking' => true,
        ], ['auto_pnr_context_completion' => $completion]);

        $this->assertFalse($result['revalidation_required']);
        $this->assertTrue($result['revalidation_skipped']);
        $this->assertSame('scenario_runner_context_completion', $result['freshness_source']);
    }

    public function test_multistop_wy_booking_classifies_as_one_way_multistop_same_carrier(): void
    {
        $booking = $this->makeMultistopWyBooking();
        $tripType = app(SabrePnrCertificationSupport::class)->detectTripType($booking);

        $this->assertSame(SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_MULTISTOP_SAME_CARRIER, $tripType);
        $digest = app(SabreGdsPnrCreateStrategyDigest::class)->buildBookingSummary($booking);
        $this->assertSame(SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_MULTISTOP_SAME_CARRIER, $digest['trip_type'] ?? null);
        $this->assertSame(SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_MULTISTOP_SAME_CARRIER, $digest['trip_type_detected'] ?? null);
        $this->assertSame(
            SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_MULTISTOP_SAME_CARRIER_GDS,
            $digest['category'] ?? null,
        );
        $this->assertSame(
            SabreGdsOneWayTripShapeClassifier::ROUTE_SHAPE_ONE_WAY_MULTISTOP_SAME_CARRIER,
            $digest['route_shape'] ?? null,
        );
        $this->assertTrue($digest['selection_safe'] ?? false);
        $this->assertNotSame('one_way_connecting', $digest['trip_type'] ?? null);
        $this->assertNotSame('unknown', $digest['trip_type_detected'] ?? null);
        $this->assertSame('LHE-DOH-MCT-DXB', $digest['multistop_origin_destination_pattern'] ?? null);
        $this->assertTrue($digest['multistop_route_continuity_valid'] ?? false);
        $this->assertTrue($digest['multistop_chronology_valid'] ?? false);
        $this->assertTrue($digest['multistop_same_carrier'] ?? false);
        $this->assertTrue($digest['multistop_shape_valid'] ?? false);
        $this->assertSame(3, $digest['segment_count'] ?? null);
        $this->assertSame(2, $digest['stops'] ?? null);
        $this->assertArrayNotHasKey('return_shape_valid', $digest);
    }

    public function test_multistop_same_carrier_scenario_runner_auto_selects_iati(): void
    {
        Config::set('suppliers.sabre.public_auto_pnr_enabled', false);
        $booking = $this->makeMultistopWyBooking([
            'meta' => array_merge($this->multistopWyBookingMeta(), [
                'scenario_runner' => true,
                'origin_channel' => 'scenario_runner',
            ]),
        ]);

        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForScenarioRunner($booking);

        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['selected_strategy'],
        );
        $this->assertSame(
            SabreGdsPnrCreateStrategySelector::REASON_SCENARIO_RUNNER_ONE_WAY_MULTISTOP_SAME_CARRIER_IATI,
            $selection['selection_reason'],
        );
        $this->assertSame(
            '/v2.4.0/passenger/records?mode=create',
            app(SabreGdsPnrCreateStrategyRegistry::class)->endpointPathForStrategy(
                (string) $selection['selected_strategy'],
            ),
        );
        $this->assertTrue($selection['scenario_runner_override_applied'] ?? false);
        $this->assertNotContains(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
            $selection['eligible_strategies'] ?? [],
        );
    }

    public function test_multistop_same_carrier_iati_context_ready_when_completion_repaired(): void
    {
        $booking = $this->makeMultistopWyBooking();
        $completion = app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking);
        $candidates = app(SabreGdsPnrCreateStrategyDigest::class)->buildCandidateDigests($booking, null, [
            'scenario_runner' => true,
            'context_completion' => $completion,
        ]);
        $iati = collect($candidates)->firstWhere(
            'strategy_code',
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
        );
        $this->assertIsArray($iati);
        $this->assertTrue($iati['context_ready'] ?? false);
        $this->assertTrue($iati['required_fields_present'] ?? false);
    }

    public function test_multistop_scalar_context_expands_with_schedule_refs_not_leg_refs(): void
    {
        $booking = $this->makeMultistopWyBooking([
            'meta' => array_merge($this->multistopWyBookingMeta(), [
                'selected_fare_family_option' => array_merge($this->multistopWyBookingMeta()['selected_fare_family_option'], [
                    'booking_classes_by_segment' => ['V'],
                    'fare_basis_codes_by_segment' => ['VCMOEQR'],
                    'cabin_by_segment' => ['economy'],
                    'single_fare_component_applies_to_all_segments' => true,
                ]),
                'sabre_booking_context' => array_merge($this->multistopWyBookingMeta()['sabre_booking_context'], [
                    'booking_classes_by_segment' => ['V'],
                    'fare_basis_codes_by_segment' => ['VCMOEQR'],
                    'cabin_by_segment' => ['economy'],
                    'leg_refs' => [1],
                    'schedule_refs' => [1, 2, 3],
                ]),
            ]),
        ]);

        $readiness = app(SabrePnrCertificationSupport::class)->buildReadiness($booking);
        $classified = app(SabreGdsOneWayTripShapeClassifier::class)->classify($booking, $readiness);
        $this->assertSame(
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_MULTISTOP_SAME_CARRIER,
            $classified['trip_type'] ?? null,
        );
        $this->assertTrue($classified['selection_safe'] ?? false);
        $this->assertTrue($classified['segment_sell_context_valid'] ?? false);

        $completion = app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking);

        $this->assertTrue($completion['public_auto_pnr_attempt_ready']);
        $this->assertSame(3, $completion['booking_classes_by_segment_count']);
        $this->assertSame(['V', 'V', 'V'], $completion['completed_booking_classes_by_segment']);
        $this->assertTrue($completion['expanded_single_fare_component_to_all_segments']);
    }

    public function test_iati_registry_pattern_supported_for_multistop_same_carrier(): void
    {
        $registry = app(SabreGdsPnrCreateStrategyRegistry::class);
        $this->assertTrue($registry->patternSupported(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_MULTISTOP_SAME_CARRIER_GDS,
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_MULTISTOP_SAME_CARRIER,
        ));
    }

    public function test_multistop_same_carrier_certified_route_selector_returns_iati_v24(): void
    {
        $booking = $this->makeMultistopWyBooking();
        $selection = app(\App\Support\Bookings\SabreCertifiedRouteSelector::class)->selectForBooking($booking);
        $this->assertSame(SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_MULTISTOP_SAME_CARRIER_GDS, $selection['category'] ?? null);
        $this->assertSame(SabreCertifiedRouteSelector::STATUS_CERTIFIED, $selection['route_status'] ?? null);
        $this->assertSame('/v2.4.0/passenger/records?mode=create', $selection['endpoint_path'] ?? null);
        $this->assertSame(SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS, $selection['payload_style'] ?? null);
        $this->assertTrue($selection['live_booking_allowed'] ?? false);
        $this->assertTrue($selection['iati_like_preference_enabled'] ?? false);
    }

    public function test_multistop_scenario_runner_itinerary_guard_bypass_eligible(): void
    {
        $booking = $this->makeMultistopWyBooking([
            'meta' => array_merge($this->multistopWyBookingMeta(), [
                'scenario_runner' => true,
                'origin_channel' => 'scenario_runner',
            ]),
        ]);
        $completion = app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking);
        $strategySelection = app(SabreGdsPnrCreateStrategySelector::class)->selectForScenarioRunner($booking, ['strategy' => 'auto']);
        $strategy = (string) ($strategySelection['selected_strategy'] ?? '');

        $decision = app(SabrePassengerRecordsItineraryGuardPolicy::class)->resolve(
            $booking,
            $this->multistopWySnapshot(),
            [
                'mode' => 'scenario_runner',
                'operator_approved_live_pnr_create' => true,
                'gds_pnr_strategy_code' => $strategy,
                'gds_strategy_selection' => $strategySelection,
                'auto_pnr_context_completion' => $completion,
            ],
            ['guard_trigger' => 'multi_segment', 'segment_order_corrected' => false],
            3,
            [
                'payload_schema' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            ],
            false,
            SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V24_CREATE,
        );

        $this->assertTrue($decision['guard_bypassed'] ?? false);
        $this->assertSame(SabrePassengerRecordsItineraryGuardPolicy::BYPASS_REASON, $decision['guard_bypass_reason'] ?? null);
        $this->assertTrue($decision['concrete_sell_context_complete'] ?? false);
        $this->assertSame(SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_MULTISTOP_SAME_CARRIER, $decision['trip_type'] ?? null);
    }

    public function test_multistop_guard_bypass_allows_when_selection_safe_false_and_concrete_sell_complete(): void
    {
        $snapshot = $this->multistopWySnapshot();
        foreach ($snapshot['segments'] as $i => $seg) {
            unset($snapshot['segments'][$i]['flight_number'], $snapshot['segments'][$i]['booking_class'], $snapshot['segments'][$i]['cabin']);
        }
        $meta = array_merge($this->multistopWyBookingMeta(), [
            'scenario_runner' => true,
            'origin_channel' => 'scenario_runner',
            'normalized_offer_snapshot' => $snapshot,
            'sabre_booking_context' => array_merge($this->multistopWyBookingMeta()['sabre_booking_context'], [
                'leg_refs' => [1],
                'schedule_refs' => [1, 2, 3],
                'segment_slice_count' => 3,
                'ready_for_booking_payload' => true,
            ]),
        ]);
        $booking = $this->makeMultistopWyBooking(['meta' => $meta]);
        $readiness = app(SabrePnrCertificationSupport::class)->buildReadiness($booking);
        $classified = app(SabreGdsOneWayTripShapeClassifier::class)->classify($booking, $readiness);
        $this->assertFalse($classified['selection_safe'] ?? true);
        $this->assertFalse($classified['segment_sell_context_valid'] ?? true);

        $completion = app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking);
        $strategySelection = app(SabreGdsPnrCreateStrategySelector::class)->selectForScenarioRunner($booking, ['strategy' => 'auto']);

        $decision = app(SabrePassengerRecordsItineraryGuardPolicy::class)->resolve(
            $booking,
            $snapshot,
            [
                'mode' => 'scenario_runner',
                'operator_approved_live_pnr_create' => true,
                'gds_pnr_strategy_code' => $strategySelection['selected_strategy'] ?? '',
                'gds_strategy_selection' => $strategySelection,
                'auto_pnr_context_completion' => $completion,
                'gds_strategy_context_ready' => true,
            ],
            ['guard_trigger' => 'multi_segment', 'segment_order_corrected' => false],
            3,
            ['payload_schema' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS],
            false,
            SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V24_CREATE,
        );

        $this->assertTrue($decision['concrete_sell_context_complete'] ?? false);
        $this->assertTrue($decision['guard_bypassed'] ?? false);
        $this->assertSame(1, $decision['leg_refs_count'] ?? null);
        $this->assertSame(3, $decision['schedule_refs_count'] ?? null);
    }

    public function test_multistop_guard_bypass_allows_when_ticketing_disabled(): void
    {
        Config::set('suppliers.sabre.ticketing_enabled', false);

        $booking = $this->makeMultistopWyBooking([
            'meta' => array_merge($this->multistopWyBookingMeta(), [
                'scenario_runner' => true,
                'origin_channel' => 'scenario_runner',
            ]),
        ]);
        $completion = app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking);
        $strategySelection = app(SabreGdsPnrCreateStrategySelector::class)->selectForScenarioRunner($booking, ['strategy' => 'auto']);

        $decision = app(SabrePassengerRecordsItineraryGuardPolicy::class)->resolve(
            $booking,
            $this->multistopWySnapshot(),
            [
                'mode' => 'scenario_runner',
                'operator_approved_live_pnr_create' => true,
                'gds_pnr_strategy_code' => $strategySelection['selected_strategy'] ?? '',
                'gds_strategy_selection' => $strategySelection,
                'auto_pnr_context_completion' => $completion,
                'gds_strategy_context_ready' => true,
            ],
            ['guard_trigger' => 'multi_segment', 'segment_order_corrected' => false],
            3,
            ['payload_schema' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS],
            false,
            SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V24_CREATE,
        );

        $this->assertFalse($decision['ticketing_enabled'] ?? true);
        $this->assertTrue($decision['guard_bypassed'] ?? false);
        $this->assertSame([], $decision['bypass_block_reasons'] ?? null);
        $this->assertNotContains('ticketing_enabled', $decision['bypass_block_reasons'] ?? []);
    }

    public function test_multistop_guard_bypass_blocks_when_ticketing_enabled(): void
    {
        Config::set('suppliers.sabre.ticketing_enabled', true);

        $booking = $this->makeMultistopWyBooking([
            'meta' => array_merge($this->multistopWyBookingMeta(), [
                'scenario_runner' => true,
                'origin_channel' => 'scenario_runner',
            ]),
        ]);
        $completion = app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking);
        $strategySelection = app(SabreGdsPnrCreateStrategySelector::class)->selectForScenarioRunner($booking, ['strategy' => 'auto']);

        $decision = app(SabrePassengerRecordsItineraryGuardPolicy::class)->resolve(
            $booking,
            $this->multistopWySnapshot(),
            [
                'mode' => 'scenario_runner',
                'operator_approved_live_pnr_create' => true,
                'gds_pnr_strategy_code' => $strategySelection['selected_strategy'] ?? '',
                'gds_strategy_selection' => $strategySelection,
                'auto_pnr_context_completion' => $completion,
                'gds_strategy_context_ready' => true,
            ],
            ['guard_trigger' => 'multi_segment', 'segment_order_corrected' => false],
            3,
            ['payload_schema' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS],
            true,
            SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V24_CREATE,
        );

        $reasons = is_array($decision['bypass_block_reasons'] ?? null) ? $decision['bypass_block_reasons'] : [];
        $this->assertTrue($decision['ticketing_enabled'] ?? false);
        $this->assertFalse($decision['guard_bypassed'] ?? true);
        $this->assertContains('ticketing_enabled', $reasons);
    }

    public function test_multistop_guard_bypass_block_reasons_when_no_scenario_approval(): void
    {
        $booking = $this->makeMultistopWyBooking();
        $decision = app(SabrePassengerRecordsItineraryGuardPolicy::class)->resolve(
            $booking,
            $this->multistopWySnapshot(),
            ['mode' => 'public_checkout'],
            ['guard_trigger' => 'multi_segment', 'segment_order_corrected' => false],
            3,
            ['payload_schema' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS],
            false,
            SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V24_CREATE,
        );

        $reasons = is_array($decision['bypass_block_reasons'] ?? null) ? $decision['bypass_block_reasons'] : [];
        $this->assertFalse($decision['guard_bypassed'] ?? true);
        $this->assertContains('scenario_runner_not_active', $reasons);
    }

    public function test_multistop_guard_bypass_blocks_when_rbd_fbc_cabin_incomplete(): void
    {
        $booking = $this->makeMultistopWyBooking([
            'meta' => array_merge($this->multistopWyBookingMeta(), [
                'scenario_runner' => true,
                'selected_fare_family_option' => array_merge($this->multistopWyBookingMeta()['selected_fare_family_option'], [
                    'booking_classes_by_segment' => [],
                    'fare_basis_codes_by_segment' => [],
                    'cabin_by_segment' => [],
                ]),
                'sabre_booking_context' => array_merge($this->multistopWyBookingMeta()['sabre_booking_context'], [
                    'booking_classes_by_segment' => [],
                    'fare_basis_codes_by_segment' => [],
                    'cabin_by_segment' => [],
                    'schedule_refs' => [],
                    'segment_slice_count' => 0,
                    'ready_for_booking_payload' => false,
                ]),
            ]),
        ]);
        $completion = [
            'auto_pnr_context_completion_status' => SabreGdsAutoPnrContextCompletionService::STATUS_COMPLETE,
            'public_auto_pnr_attempt_ready' => true,
            'booking_classes_by_segment_count' => 0,
            'fare_basis_codes_by_segment_count' => 0,
            'cabin_by_segment_count' => 0,
        ];
        $strategySelection = app(SabreGdsPnrCreateStrategySelector::class)->selectForScenarioRunner($booking, ['strategy' => 'auto']);

        $decision = app(SabrePassengerRecordsItineraryGuardPolicy::class)->resolve(
            $booking,
            $this->multistopWySnapshot(),
            [
                'mode' => 'scenario_runner',
                'operator_approved_live_pnr_create' => true,
                'gds_pnr_strategy_code' => $strategySelection['selected_strategy'] ?? '',
                'gds_strategy_selection' => $strategySelection,
                'auto_pnr_context_completion' => $completion,
            ],
            ['guard_trigger' => 'multi_segment', 'segment_order_corrected' => false],
            3,
            ['payload_schema' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS],
            false,
            SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V24_CREATE,
        );

        $reasons = is_array($decision['bypass_block_reasons'] ?? null) ? $decision['bypass_block_reasons'] : [];
        $this->assertFalse($decision['guard_bypassed'] ?? true);
        $this->assertFalse($decision['concrete_sell_context_complete'] ?? true);
        $this->assertContains('concrete_sell_context_incomplete', $reasons);
    }

    public function test_multistop_scenario_runner_live_create_bypasses_itinerary_guard(): void
    {
        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', true);
        Config::set('suppliers.sabre.revalidate_before_booking', false);
        Config::set('suppliers.sabre.passenger_records_block_risky_itinerary_live', true);
        Config::set('suppliers.sabre.booking_schema', 'create_passenger_name_record');
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.pnr_only_waive_mandatory_revalidation', true);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = strtolower($request->url());
            if (str_contains($url, '/v2/auth/token')) {
                return Http::response(['access_token' => 'test-token', 'expires_in' => 3600], 200);
            }
            if (str_contains($url, 'passenger/records')) {
                return Http::response([
                    'CreatePassengerNameRecordRS' => [
                        'ApplicationResults' => ['status' => 'Complete'],
                        'ItineraryRef' => ['ID' => 'SCNRWY'],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $booking = $this->makeMultistopWyBooking([
            'meta' => array_merge($this->multistopWyBookingMeta(), [
                'scenario_runner' => true,
                'origin_channel' => 'scenario_runner',
            ]),
        ]);
        $completion = app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking);
        $strategySelection = app(SabreGdsPnrCreateStrategySelector::class)->selectForScenarioRunner($booking, ['strategy' => 'auto']);
        $strategy = (string) ($strategySelection['selected_strategy'] ?? '');
        $this->assertSame(SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS, $strategy);

        $result = app(SabreBookingService::class)->createBookingForScenarioRunner(
            $booking->fresh(['passengers', 'contact', 'fareBreakdown']),
            $strategy,
            $strategySelection,
            $completion,
        );

        $this->assertNotSame('sabre_passenger_records_itinerary_guard', $result['error_code'] ?? null, json_encode($result));
        $this->assertTrue($result['live_call_attempted'] ?? false);
        $this->assertTrue($result['pnr_attempted'] ?? false);
        $this->assertFalse($result['ticketing_enabled'] ?? true);
    }

    public function test_multistop_non_scenario_runner_still_blocked_by_itinerary_guard(): void
    {
        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', true);
        Config::set('suppliers.sabre.revalidate_before_booking', false);
        Config::set('suppliers.sabre.passenger_records_block_risky_itinerary_live', true);
        Config::set('suppliers.sabre.booking_schema', 'create_passenger_name_record');
        Config::set('suppliers.sabre.booking_mode', 'certified');

        $booking = $this->makeMultistopWyBooking();
        $offer = $this->multistopWySnapshot();
        $offer['supplier_connection_id'] = 2;
        $passengerData = [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'Test',
                'last_name' => 'User',
                'passport_number' => 'AB9999999',
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => '2035-12-31',
                'nationality' => 'PK',
                'document_type' => 'passport',
            ]],
            'contact' => ['email' => 't@example.com', 'phone' => '+923001234567'],
        ];

        $result = app(SabreBookingService::class)->createBooking($offer, $passengerData, $booking->id);

        $this->assertSame('sabre_passenger_records_itinerary_guard', $result['error_code'] ?? null);
        $this->assertFalse($result['live_call_attempted'] ?? true);
        $this->assertFalse($result['pnr_attempted'] ?? true);
    }

    public function test_multistop_corrected_order_itinerary_guard_not_bypassed(): void
    {
        $booking = $this->makeMultistopWyBooking([
            'meta' => array_merge($this->multistopWyBookingMeta(), [
                'scenario_runner' => true,
                'origin_channel' => 'scenario_runner',
                'normalized_offer_snapshot' => array_merge($this->multistopWySnapshot(), [
                    'raw_payload' => [
                        'sabre_segment_order' => ['segment_order_corrected' => true],
                    ],
                ]),
            ]),
        ]);
        $completion = app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking);
        $strategySelection = app(SabreGdsPnrCreateStrategySelector::class)->selectForScenarioRunner($booking, ['strategy' => 'auto']);

        $decision = app(SabrePassengerRecordsItineraryGuardPolicy::class)->resolve(
            $booking,
            is_array($booking->meta['normalized_offer_snapshot'] ?? null) ? $booking->meta['normalized_offer_snapshot'] : [],
            [
                'mode' => 'scenario_runner',
                'operator_approved_live_pnr_create' => true,
                'gds_pnr_strategy_code' => $strategySelection['selected_strategy'] ?? '',
                'gds_strategy_selection' => $strategySelection,
                'auto_pnr_context_completion' => $completion,
            ],
            ['guard_trigger' => 'multi_segment', 'segment_order_corrected' => true],
            3,
            ['payload_schema' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS],
            false,
            SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V24_CREATE,
        );

        $this->assertFalse($decision['guard_bypassed'] ?? true);
        $this->assertFalse($decision['bypass_allowed'] ?? true);
    }

    public function test_itinerary_guard_block_safe_summary_has_eligibility_fields_without_secrets(): void
    {
        $booking = $this->makeMultistopWyBooking();
        $decision = app(SabrePassengerRecordsItineraryGuardPolicy::class)->resolve(
            $booking,
            $this->multistopWySnapshot(),
            ['mode' => 'public_checkout'],
            ['guard_trigger' => 'multi_segment', 'segment_order_corrected' => false],
            3,
            ['payload_schema' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS],
            false,
            SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V24_CREATE,
        );
        $slice = app(SabrePassengerRecordsItineraryGuardPolicy::class)->safeSummarySlice($decision);
        $encoded = strtolower(json_encode($slice) ?: '');

        $this->assertSame('multi_segment', $slice['guard_trigger'] ?? null);
        $this->assertFalse($slice['bypass_allowed'] ?? true);
        $this->assertArrayHasKey('trip_type', $slice);
        $this->assertArrayHasKey('concrete_sell_context_complete', $slice);
        $this->assertIsArray($decision['bypass_block_reasons'] ?? null);
        $this->assertStringNotContainsString('passport', $encoded);
        $this->assertStringNotContainsString('createpassengernamerecordrq', $encoded);
        $this->assertStringNotContainsString('pcc', $encoded);
    }

    public function test_multistop_mixed_carrier_is_not_automatic_for_scenario_runner(): void
    {
        $booking = $this->makeMultistopWyBooking([
            'meta' => array_merge($this->multistopWyBookingMeta(), [
                'normalized_offer_snapshot' => $this->multistopMixedCarrierSnapshot(),
                'scenario_runner' => true,
                'origin_channel' => 'scenario_runner',
            ]),
        ]);
        $tripType = app(SabrePnrCertificationSupport::class)->detectTripType($booking);
        $this->assertSame(SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_MULTISTOP_MIXED_CARRIER, $tripType);

        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForScenarioRunner($booking);
        $this->assertNull($selection['selected_strategy'] ?? null);
        $this->assertTrue($selection['interline_or_mixed_blocked'] ?? false);
        $this->assertSame(
            SabreGdsLiveScenarioPlanCandidateDiagnostics::BLOCK_MIXED_CARRIER_NOT_CERTIFIED,
            $selection['automatic_block_reason'] ?? null,
        );
    }

    public function test_three_stop_same_carrier_classifies_correctly(): void
    {
        $snap = $this->threeStopWySnapshot();
        $shape = app(SabreGdsOneWayTripShapeClassifier::class)->classifyFromNormalizedOffer($snap);

        $this->assertSame(SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_THREE_STOP_SAME_CARRIER, $shape['trip_type'] ?? null);
        $this->assertSame(4, $shape['segment_count'] ?? null);
        $this->assertSame(3, $shape['stops'] ?? null);
        $this->assertTrue($shape['same_carrier'] ?? false);
        $this->assertFalse($shape['mixed_carrier'] ?? true);
    }

    public function test_four_stop_same_carrier_classifies_correctly(): void
    {
        $snap = $this->fourStopWySnapshot();
        $shape = app(SabreGdsOneWayTripShapeClassifier::class)->classifyFromNormalizedOffer($snap);

        $this->assertSame(SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_FOUR_STOP_SAME_CARRIER, $shape['trip_type'] ?? null);
        $this->assertSame(5, $shape['segment_count'] ?? null);
        $this->assertSame(4, $shape['stops'] ?? null);
        $this->assertTrue($shape['advanced_itinerary_plan_only'] ?? false);
    }

    public function test_mixed_one_stop_classifies_and_blocks_automatic(): void
    {
        $snap = $this->mixedOneStopSnapshot();
        $shape = app(SabreGdsOneWayTripShapeClassifier::class)->classifyFromNormalizedOffer($snap);

        $this->assertSame(SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_SINGLE_CONNECTION_MIXED_CARRIER, $shape['trip_type'] ?? null);
        $this->assertTrue($shape['mixed_carrier'] ?? false);

        $plan = app(SabreGdsLiveScenarioPlanCandidateDiagnostics::class)->diagnose($snap, [
            'route' => 'LHE-DOH-DXB',
            'segment_count' => 2,
            'marketing_carriers' => ['QR', 'EK'],
            'carrier_chain' => 'QR+EK',
            'validating_carrier' => 'QR',
            'same_carrier' => false,
            'mixed_carrier' => true,
            'booking_classes_by_segment' => ['Y', 'Y'],
            'fare_basis_codes_by_segment' => ['YLOW', 'YLOW'],
        ]);

        $this->assertFalse($plan['automatic_allowed'] ?? true);
        $this->assertSame(SabreGdsLiveScenarioPlanCandidateDiagnostics::BLOCK_MIXED_CARRIER_NOT_CERTIFIED, $plan['block_reason'] ?? null);
        $this->assertTrue($plan['interline_or_mixed_blocked'] ?? false);
    }

    public function test_three_stop_same_carrier_plan_expects_iati_but_blocks_automatic(): void
    {
        $snap = $this->threeStopWySnapshot();
        $plan = app(SabreGdsLiveScenarioPlanCandidateDiagnostics::class)->diagnose($snap, [
            'route' => 'LHE-DOH-MCT-BAH-DXB',
            'segment_count' => 4,
            'marketing_carriers' => ['WY', 'WY', 'WY', 'WY'],
            'carrier_chain' => 'WY',
            'validating_carrier' => 'WY',
            'same_carrier' => true,
            'mixed_carrier' => false,
            'booking_classes_by_segment' => ['V', 'V', 'V', 'V'],
            'fare_basis_codes_by_segment' => ['VCMOEQR', 'VCMOEQR', 'VCMOEQR', 'VCMOEQR'],
            'cabin_by_segment' => ['economy', 'economy', 'economy', 'economy'],
        ]);

        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $plan['selected_strategy'] ?? null,
        );
        $this->assertFalse($plan['automatic_allowed'] ?? true);
        $this->assertSame(SabreGdsLiveScenarioPlanCandidateDiagnostics::BLOCK_ADVANCED_ITINERARY_PLAN_ONLY, $plan['block_reason'] ?? null);
    }

    public function test_mixed_return_plan_blocks_automatic(): void
    {
        $snap = $this->returnMixedCarrierSnapshot();
        $plan = app(SabreGdsLiveScenarioPlanCandidateDiagnostics::class)->diagnose($snap, [
            'route' => 'LHE-DXB-LHE',
            'segment_count' => 2,
            'marketing_carriers' => ['PK', 'EK'],
            'carrier_chain' => 'PK+EK',
            'validating_carrier' => 'PK',
            'same_carrier' => false,
            'mixed_carrier' => true,
        ], ['trip_type' => 'return']);

        $this->assertSame(SabreGdsReturnTripClassifier::TRIP_RETURN_MIXED_CARRIER, $plan['trip_type'] ?? null);
        $this->assertFalse($plan['automatic_allowed'] ?? true);
        $this->assertSame(SabreGdsLiveScenarioPlanCandidateDiagnostics::BLOCK_MIXED_CARRIER_NOT_CERTIFIED, $plan['automatic_block_reason'] ?? null);
    }

    public function test_mixed_one_stop_gate_blocks_without_mixed_approval(): void
    {
        $booking = $this->makeMixedOneStopBooking();
        $completion = app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking);
        $gate = app(SabreGdsMixedCarrierCertificationGate::class)->evaluate($booking, [
            'completion' => $completion,
            'scenario_live_pnr_create_approved' => true,
            'mixed_carrier_certification_approved' => false,
            'scenario_runner_override_applied' => true,
        ]);

        $this->assertFalse($gate['allowed'] ?? true);
        $this->assertContains(SabreGdsMixedCarrierCertificationGate::REASON_APPROVAL_MISSING, $gate['block_reasons'] ?? []);

        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForScenarioRunner($booking, [
            'mixed_carrier_certification_approved' => false,
        ]);
        $this->assertNull($selection['selected_strategy'] ?? null);
    }

    public function test_mixed_one_stop_allows_with_mixed_approval_and_readiness(): void
    {
        $booking = $this->makeMixedOneStopBooking();
        $completion = app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking);
        $gate = app(SabreGdsMixedCarrierCertificationGate::class)->evaluate($booking, [
            'completion' => $completion,
            'scenario_live_pnr_create_approved' => true,
            'mixed_carrier_certification_approved' => true,
            'scenario_runner_override_applied' => true,
            'selected_strategy' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
        ]);

        $this->assertTrue($gate['allowed'] ?? false);
        $this->assertTrue($gate['mixed_carrier_detected'] ?? false);
        $this->assertSame(SabreGdsMixedCarrierCertificationGate::SCOPE, $gate['mixed_carrier_certification_scope'] ?? null);

        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForScenarioRunner($booking, [
            'mixed_carrier_certification_approved' => true,
        ]);
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['selected_strategy'] ?? null,
        );
        $this->assertNotContains(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
            $selection['eligible_strategies'] ?? [],
        );
    }

    public function test_mixed_return_blocks_without_mixed_approval(): void
    {
        $booking = $this->makeReturnPkBooking([
            'meta' => array_merge($this->returnPkBookingMeta(), [
                'normalized_offer_snapshot' => $this->returnMixedCarrierSnapshot(),
                'scenario_runner' => true,
                'origin_channel' => 'scenario_runner',
                'sabre_booking_context' => array_merge($this->returnPkBookingMeta()['sabre_booking_context'], [
                    'schedule_refs' => [1, 2],
                    'leg_refs' => [1],
                ]),
            ]),
        ]);

        $gate = app(SabreGdsMixedCarrierCertificationGate::class)->evaluate($booking, [
            'completion' => app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking),
            'scenario_live_pnr_create_approved' => true,
            'mixed_carrier_certification_approved' => false,
            'scenario_runner_override_applied' => true,
        ]);

        $this->assertFalse($gate['allowed'] ?? true);
        $this->assertContains(SabreGdsMixedCarrierCertificationGate::REASON_APPROVAL_MISSING, $gate['block_reasons'] ?? []);

        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForScenarioRunner($booking, [
            'mixed_carrier_certification_approved' => false,
        ]);
        $this->assertNull($selection['selected_strategy'] ?? null);
    }

    public function test_mixed_return_allows_with_mixed_approval_and_readiness(): void
    {
        $booking = $this->makeReturnPkBooking([
            'meta' => array_merge($this->returnPkBookingMeta(), [
                'normalized_offer_snapshot' => $this->returnMixedCarrierSnapshot(),
                'scenario_runner' => true,
                'origin_channel' => 'scenario_runner',
                'sabre_booking_context' => array_merge($this->returnPkBookingMeta()['sabre_booking_context'], [
                    'schedule_refs' => [1, 2],
                    'leg_refs' => [1],
                ]),
            ]),
        ]);

        $gate = app(SabreGdsMixedCarrierCertificationGate::class)->evaluate($booking, [
            'completion' => app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking),
            'scenario_live_pnr_create_approved' => true,
            'mixed_carrier_certification_approved' => true,
            'scenario_runner_override_applied' => true,
            'selected_strategy' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
        ]);

        $this->assertTrue($gate['allowed'] ?? false);
        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForScenarioRunner($booking, [
            'mixed_carrier_certification_approved' => true,
        ]);
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['selected_strategy'] ?? null,
        );
    }

    public function test_mixed_gate_blocks_when_stops_exceed_certified_max(): void
    {
        $snap = $this->threeStopWySnapshot();
        $snap['segments'][1]['carrier'] = 'PK';
        $snap['segments'][1]['marketing_carrier'] = 'PK';
        $snap['segments'][2]['carrier'] = 'EK';
        $snap['segments'][2]['marketing_carrier'] = 'EK';
        $booking = $this->makeMultistopWyBooking([
            'meta' => array_merge($this->multistopWyBookingMeta(), [
                'normalized_offer_snapshot' => $snap,
                'scenario_runner' => true,
                'sabre_booking_context' => array_merge($this->multistopWyBookingMeta()['sabre_booking_context'], [
                    'schedule_refs' => [1, 2, 3, 4],
                    'booking_classes_by_segment' => ['Y', 'Y', 'Y', 'Y'],
                    'fare_basis_codes_by_segment' => ['YLOW', 'YLOW', 'YLOW', 'YLOW'],
                    'cabin_by_segment' => ['economy', 'economy', 'economy', 'economy'],
                    'segment_count' => 4,
                ]),
            ]),
        ]);
        $gate = app(SabreGdsMixedCarrierCertificationGate::class)->evaluate($booking, [
            'completion' => app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking),
            'mixed_carrier_certification_approved' => true,
            'scenario_live_pnr_create_approved' => true,
            'scenario_runner_override_applied' => true,
        ]);

        $this->assertFalse($gate['allowed'] ?? true);
        $this->assertContains(SabreGdsMixedCarrierCertificationGate::REASON_TOO_MANY_STOPS, $gate['block_reasons'] ?? []);
    }

    public function test_mixed_gate_blocks_when_segment_count_exceeds_three(): void
    {
        $snap = $this->threeStopWySnapshot();
        $snap['segments'][1]['carrier'] = 'PK';
        $snap['segments'][1]['marketing_carrier'] = 'PK';
        $snap['segments'][2]['carrier'] = 'EK';
        $snap['segments'][2]['marketing_carrier'] = 'EK';
        $booking = $this->makeMultistopWyBooking([
            'meta' => array_merge($this->multistopWyBookingMeta(), [
                'normalized_offer_snapshot' => $snap,
                'scenario_runner' => true,
            ]),
        ]);
        $gate = app(SabreGdsMixedCarrierCertificationGate::class)->evaluate($booking, [
            'completion' => app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking),
            'mixed_carrier_certification_approved' => true,
            'scenario_live_pnr_create_approved' => true,
            'scenario_runner_override_applied' => true,
        ]);

        $this->assertFalse($gate['allowed'] ?? true);
        $this->assertContains(SabreGdsMixedCarrierCertificationGate::REASON_TOO_MANY_SEGMENTS, $gate['block_reasons'] ?? []);
    }

    public function test_mixed_carrier_payload_preflight_blocks_when_fare_basis_missing_on_wire(): void
    {
        $snap = $this->mixedOneStopSnapshot();
        foreach ($snap['segments'] as $i => $seg) {
            $snap['segments'][$i]['fare_basis_code'] = '';
            $snap['segments'][$i]['booking_class'] = '';
        }
        $snap['sabre_booking_context']['fare_basis_codes_by_segment'] = [];
        $snap['sabre_booking_context']['booking_classes_by_segment'] = [];
        $booking = $this->makeMixedOneStopBooking([
            'meta' => array_merge($this->mixedOneStopBookingMeta(), [
                'normalized_offer_snapshot' => $snap,
                'sabre_booking_context' => array_merge($this->mixedOneStopBookingMeta()['sabre_booking_context'], [
                    'fare_basis_codes_by_segment' => [],
                    'booking_classes_by_segment' => [],
                ]),
                'selected_fare_family_option' => array_merge(
                    $this->mixedOneStopBookingMeta()['selected_fare_family_option'],
                    ['fare_basis_codes_by_segment' => [], 'booking_classes_by_segment' => []],
                ),
            ]),
        ]);
        $completion = app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking);
        $preflight = app(SabreGdsMixedCarrierFareBasisPayloadPreflight::class)->evaluate($booking, [
            'completion' => $completion,
            'mixed_carrier_certification_approved' => true,
            'scenario_live_pnr_create_approved' => true,
            'scenario_runner_override_applied' => true,
            'selected_strategy' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
        ]);

        $this->assertFalse($preflight['allowed'] ?? true);
        $this->assertContains(
            $preflight['block_reason'] ?? '',
            [
                SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_PAYLOAD_MAPPING_INCOMPLETE,
                SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_PAYLOAD_MAPPING_UNAVAILABLE,
                SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_FARE_COMPONENT_CARRIER_MAPPING_UNAVAILABLE,
                SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_V24_COMMANDPRICING_SCHEMA_INVALID,
            ],
        );
        $this->assertFalse($preflight['has_fare_basis'] ?? true);
        $this->assertFalse($preflight['live_call_attempted'] ?? true);
        $this->assertFalse($preflight['pnr_attempted'] ?? true);
    }

    public function test_mixed_carrier_payload_preflight_passes_with_complete_wire_mapping(): void
    {
        $booking = $this->makeMixedOneStopBooking();
        $completion = app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking);
        $preflight = app(SabreGdsMixedCarrierFareBasisPayloadPreflight::class)->evaluate($booking, [
            'completion' => $completion,
            'mixed_carrier_certification_approved' => true,
            'scenario_live_pnr_create_approved' => true,
            'scenario_runner_override_applied' => true,
            'selected_strategy' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
        ]);

        $this->assertTrue($preflight['has_fare_basis'] ?? false, 'IATI wire should include per-segment fare basis');
        $this->assertTrue($preflight['has_booking_class'] ?? false);
        $this->assertTrue($preflight['has_validating_carrier'] ?? false);
        $this->assertTrue($preflight['rbd_carrier_mapping_complete'] ?? false);
        $this->assertTrue($preflight['mixed_fare_carrier_mapping_complete'] ?? false);
        $this->assertFalse($preflight['no_fares_rbd_carrier_preflight_risk'] ?? true);
        $this->assertTrue($preflight['allowed'] ?? false);
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $preflight['selected_strategy'] ?? null,
        );
        $this->assertNotNull($preflight['mixed_carrier_detected'] ?? null);
        $this->assertNotNull($preflight['carrier_chain_count'] ?? null);
        $this->assertNotNull($preflight['validating_carrier'] ?? null);
    }

    public function test_mixed_carrier_payload_preflight_blocks_when_command_pricing_incomplete(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = [
            '_valid' => true,
            'supplier_connection_id' => 2,
            '_sabre_pseudo_city_code' => 'AB12',
            'validating_carrier' => 'QR',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DOH', 'carrier' => 'QR', 'flight_number' => '614', 'departure_at' => '2026-08-18T04:30:00', 'booking_class' => 'Y'],
                ['origin' => 'DOH', 'destination' => 'DXB', 'carrier' => 'EK', 'flight_number' => '842', 'departure_at' => '2026-08-18T08:00:00', 'booking_class' => 'Y'],
            ],
            'passengers' => [['type' => 'ADT', 'first_name' => 'T', 'last_name' => 'U', 'gender' => 'MALE', 'date_of_birth' => '1990-01-01']],
            'contact' => ['email' => 'a@b.com', 'phone' => '3001234567'],
            '_sabre_booking_context' => ['validating_carrier' => 'QR', 'booking_classes_by_segment' => ['Y', 'Y'], 'fare_basis_codes_by_segment' => []],
        ];
        $wire = $builder->stripOtaInternalKeysFromBookingWire($builder->buildIatiLikeCpnrV24GdsWire($draft, []));
        $preflight = app(SabreGdsMixedCarrierFareBasisPayloadPreflight::class);
        $countMethod = new \ReflectionMethod($preflight, 'countCommandPricingFareBasisRows');
        $countMethod->setAccessible(true);
        $this->assertSame(0, $countMethod->invoke($preflight, $wire));
    }

    public function test_same_carrier_direct_still_selects_iati_after_mixed_payload_preflight_changes(): void
    {
        $booking = $this->makeDirectPkBooking();
        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForScenarioRunner($booking, [
            'mixed_carrier_certification_approved' => false,
        ]);
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['selected_strategy'] ?? null,
        );
    }

    public function test_mixed_plan_without_approval_keeps_mixed_carrier_not_certified(): void
    {
        $snap = $this->mixedOneStopSnapshot();
        $plan = app(SabreGdsLiveScenarioPlanCandidateDiagnostics::class)->diagnose($snap, [
            'route' => 'LHE-DOH-DXB',
            'segment_count' => 2,
            'marketing_carriers' => ['QR', 'EK'],
            'carrier_chain' => 'QR+EK',
            'validating_carrier' => 'QR',
            'same_carrier' => false,
            'mixed_carrier' => true,
            'booking_classes_by_segment' => ['Y', 'Y'],
            'fare_basis_codes_by_segment' => ['YLOW', 'YLOW'],
        ], [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_date' => '2026-08-18',
        ], [
            'mixed_carrier_certification_approved' => false,
            'connection' => $this->resolveSabreConnection(),
        ]);

        $this->assertSame(SabreGdsLiveScenarioPlanCandidateDiagnostics::BLOCK_MIXED_CARRIER_NOT_CERTIFIED, $plan['block_reason'] ?? null);
        $this->assertArrayNotHasKey('payload_preflight_status', $plan);
        $this->assertNull($plan['has_fare_basis'] ?? null);
    }

    public function test_mixed_plan_with_approval_exposes_payload_preflight_diagnostics(): void
    {
        $snap = $this->mixedOneStopSnapshot();
        $plan = $this->diagnoseMixedPlanCandidate($snap);

        $this->assertArrayHasKey('payload_preflight_status', $plan);
        $this->assertArrayHasKey('has_fare_basis', $plan);
        $this->assertArrayHasKey('rbd_carrier_mapping_complete', $plan);
        $this->assertArrayHasKey('mixed_fare_carrier_mapping_complete', $plan);
        $this->assertArrayHasKey('no_fares_rbd_carrier_preflight_risk', $plan);
        $this->assertArrayHasKey('fare_component_carriers', $plan);
        $this->assertArrayHasKey('segment_marketing_carriers', $plan);
        $this->assertArrayHasKey('command_pricing_carriers', $plan);
        $this->assertArrayHasKey('mixed_mapping_expected_carriers', $plan);
        $this->assertArrayHasKey('mixed_mapping_actual_carriers', $plan);
        $this->assertArrayHasKey('mixed_mapping_comparison_result', $plan);
        $this->assertArrayHasKey('command_pricing_schema_valid', $plan);
        $this->assertArrayHasKey('wire_command_pricing_count', $plan);
        $this->assertArrayHasKey('wire_fare_basis_count', $plan);
        $this->assertArrayHasKey('wire_segment_count', $plan);
        $this->assertFalse($plan['live_call_attempted'] ?? true);
        $this->assertFalse($plan['pnr_attempted'] ?? true);
    }

    public function test_mixed_plan_clean_candidate_shows_payload_preflight_pass(): void
    {
        $plan = $this->diagnoseMixedPlanCandidate($this->mixedOneStopSnapshot());

        $this->assertSame(SabreGdsLiveScenarioPlanCandidateDiagnostics::PAYLOAD_PREFLIGHT_STATUS_PASS, $plan['payload_preflight_status'] ?? null);
        $this->assertTrue($plan['has_fare_basis'] ?? false);
        $this->assertTrue($plan['has_booking_class'] ?? false);
        $this->assertTrue($plan['has_validating_carrier'] ?? false);
        $this->assertTrue($plan['rbd_carrier_mapping_complete'] ?? false);
        $this->assertTrue($plan['mixed_fare_carrier_mapping_complete'] ?? false);
        $this->assertFalse($plan['no_fares_rbd_carrier_preflight_risk'] ?? true);
        $this->assertTrue($plan['command_pricing_schema_valid'] ?? false);
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $plan['selected_strategy_expected'] ?? null,
        );
        $this->assertNull($plan['selected_strategy'] ?? null);
        $this->assertFalse($plan['automatic_allowed'] ?? true);
        $this->assertSame(
            SabreGdsLiveScenarioPlanCandidateDiagnostics::BLOCK_MIXED_CARRIER_PLAN_ONLY_READY,
            $plan['block_reason'] ?? null,
        );
        $this->assertSame('match', $plan['mixed_mapping_comparison_result'] ?? null);
        $this->assertSame(['QR', 'EK'], $plan['mixed_mapping_expected_carriers'] ?? null);
        $this->assertSame(['QR', 'EK'], $plan['mixed_mapping_actual_carriers'] ?? null);
        $this->assertTrue($plan['command_pricing_schema_valid'] ?? false);
    }

    public function test_mixed_plan_unavailable_carrier_mapping_blocks_with_fare_component_reason(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $segments = [
            ['origin' => 'LHE', 'destination' => 'KHI', 'flight_number' => '301', 'departure_at' => '2026-08-18T04:30:00', 'booking_class' => 'Y', 'fare_basis_code' => 'YLOW'],
            ['origin' => 'KHI', 'destination' => 'DXB', 'flight_number' => '602', 'departure_at' => '2026-08-18T08:00:00', 'booking_class' => 'Y', 'fare_basis_code' => 'YLOW2'],
        ];
        $wire = [
            'CreatePassengerNameRecordRQ' => [
                'AirPrice' => [[
                    'PriceRequestInformation' => [
                        'OptionalQualifiers' => [
                            'PricingQualifiers' => [
                                'CommandPricing' => [
                                    ['RPH' => '1', 'FareBasis' => ['Code' => 'YLOW']],
                                    ['RPH' => '2', 'FareBasis' => ['Code' => 'YLOW2']],
                                ],
                            ],
                        ],
                    ],
                ]],
            ],
        ];
        $diag = $builder->summarizeIatiMixedCarrierCommandPricingMapping($wire, $segments);
        $this->assertFalse($diag['mixed_fare_carrier_mapping_complete'] ?? true);
        $this->assertNull($diag['mixed_mapping_expected_carriers'] ?? null);
        $this->assertContains('segment_marketing_carrier_missing', $diag['mixed_mapping_missing_reasons'] ?? []);
        $this->assertNotSame('match', $diag['mixed_mapping_comparison_result'] ?? null);
    }

    public function test_mixed_plan_incomplete_carrier_mapping_blocks_before_live(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $segments = [
            ['origin' => 'LHE', 'destination' => 'KHI', 'carrier' => 'PK', 'flight_number' => '301', 'departure_at' => '2026-08-18T04:30:00', 'booking_class' => 'Y', 'fare_basis_code' => 'YLOW'],
            ['origin' => 'KHI', 'destination' => 'DXB', 'carrier' => 'EK', 'flight_number' => '602', 'departure_at' => '2026-08-18T08:00:00', 'booking_class' => 'Y', 'fare_basis_code' => 'YLOW2'],
        ];
        $wire = [
            'CreatePassengerNameRecordRQ' => [
                'AirPrice' => [[
                    'PriceRequestInformation' => [
                        'OptionalQualifiers' => [
                            'PricingQualifiers' => [
                                'CommandPricing' => [
                                    ['RPH' => '1', 'FareBasis' => ['Code' => 'YLOW'], 'Airline' => ['Code' => 'PK'], 'ResBookDesigCode' => 'Y'],
                                    ['RPH' => '2', 'FareBasis' => ['Code' => 'YLOW2'], 'Airline' => ['Code' => 'PK'], 'ResBookDesigCode' => 'Y'],
                                ],
                            ],
                        ],
                    ],
                ]],
            ],
        ];
        $diag = $builder->summarizeIatiMixedCarrierCommandPricingMapping($wire, $segments, [], [
            'airbook_sell_carriers' => ['PK', 'PK'],
        ]);
        $this->assertFalse($diag['mixed_fare_carrier_mapping_complete'] ?? true);
        $this->assertContains('command_pricing_carrier_segment_mismatch', $diag['mixed_mapping_missing_reasons'] ?? []);
        $this->assertSame('mismatch', $diag['mixed_mapping_comparison_result'] ?? null);
    }

    public function test_mixed_plan_cannot_pass_when_mapping_comparison_mismatch(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $segments = [
            ['origin' => 'LHE', 'destination' => 'KHI', 'carrier' => 'PK', 'flight_number' => '301', 'departure_at' => '2026-08-18T04:30:00', 'booking_class' => 'Y', 'fare_basis_code' => 'YLOW'],
            ['origin' => 'KHI', 'destination' => 'DXB', 'carrier' => 'EK', 'flight_number' => '602', 'departure_at' => '2026-08-18T08:00:00', 'booking_class' => 'Y', 'fare_basis_code' => 'YLOW2'],
        ];
        $wire = [
            'CreatePassengerNameRecordRQ' => [
                'AirPrice' => [[
                    'PriceRequestInformation' => [
                        'OptionalQualifiers' => [
                            'PricingQualifiers' => [
                                'CommandPricing' => [
                                    ['RPH' => '1', 'FareBasis' => ['Code' => 'YLOW'], 'Airline' => ['Code' => 'PK'], 'ResBookDesigCode' => 'Y'],
                                    ['RPH' => '2', 'FareBasis' => ['Code' => 'YLOW2'], 'Airline' => ['Code' => 'PK'], 'ResBookDesigCode' => 'Y'],
                                ],
                            ],
                        ],
                    ],
                ]],
            ],
        ];
        $diag = $builder->summarizeIatiMixedCarrierCommandPricingMapping($wire, $segments);
        $this->assertSame('mismatch', $diag['mixed_mapping_comparison_result'] ?? null);
        $this->assertFalse($diag['mixed_fare_carrier_mapping_complete'] ?? true);

        $preflight = [
            'allowed' => true,
            'block_reason' => null,
            'has_fare_basis' => true,
            'has_booking_class' => true,
            'has_validating_carrier' => true,
            'rbd_carrier_mapping_complete' => true,
            'mixed_fare_carrier_mapping_complete' => false,
            'no_fares_rbd_carrier_preflight_risk' => true,
            'mixed_mapping_comparison_result' => 'mismatch',
            'mixed_mapping_expected_carriers' => ['PK', 'EK'],
            'mixed_mapping_actual_carriers' => ['PK', 'PK'],
        ];
        $mapped = (new \ReflectionMethod(
            SabreGdsLiveScenarioPlanCandidateDiagnostics::class,
            'mapPayloadPreflightToPlanDiagnostics',
        ));
        $mapped->setAccessible(true);
        $plan = $mapped->invoke(app(SabreGdsLiveScenarioPlanCandidateDiagnostics::class), $preflight);

        $this->assertSame(
            SabreGdsLiveScenarioPlanCandidateDiagnostics::PAYLOAD_PREFLIGHT_STATUS_BLOCKED,
            $plan['payload_preflight_status'] ?? null,
        );
        $this->assertNotSame(
            SabreGdsLiveScenarioPlanCandidateDiagnostics::BLOCK_MIXED_CARRIER_PLAN_ONLY_READY,
            $plan['block_reason'] ?? null,
        );
    }

    public function test_mixed_plan_lhe_khi_dxb_pk_ek_airline_code_segments_mapping_matches(): void
    {
        $snap = $this->mixedOneStopSnapshot();
        $snap['segments'][0]['origin'] = 'LHE';
        $snap['segments'][0]['destination'] = 'KHI';
        $snap['segments'][1]['origin'] = 'KHI';
        $snap['segments'][1]['destination'] = 'DXB';
        foreach ($snap['segments'] as $i => $seg) {
            unset($snap['segments'][$i]['carrier'], $snap['segments'][$i]['marketing_carrier'], $snap['segments'][$i]['operating_carrier']);
        }
        $snap['segments'][0]['airline_code'] = 'PK';
        $snap['segments'][1]['airline_code'] = 'EK';
        $snap['marketing_carrier_chain'] = ['PK', 'EK'];
        $snap['sabre_booking_context']['validating_carrier'] = 'PK';
        $snap['sabre_booking_context']['fare_basis_codes_by_segment'] = ['YLOW', 'YLOW2'];
        $snap['segments'][0]['fare_basis_code'] = 'YLOW';
        $snap['segments'][1]['fare_basis_code'] = 'YLOW2';

        $plan = $this->diagnoseMixedPlanCandidate($snap, [
            'route' => 'LHE-KHI-DXB',
            'marketing_carriers' => ['PK', 'EK'],
            'carrier_chain' => 'PK+EK',
            'validating_carrier' => 'PK',
            'booking_classes_by_segment' => ['Y', 'Y'],
            'fare_basis_codes_by_segment' => ['YLOW', 'YLOW2'],
        ]);

        $this->assertSame('match', $plan['mixed_mapping_comparison_result'] ?? null);
        $this->assertSame(['PK', 'EK'], $plan['mixed_mapping_expected_carriers'] ?? null);
        $this->assertSame(['PK', 'EK'], $plan['command_pricing_carriers'] ?? null);
        $this->assertSame(['PK', 'EK'], $plan['segment_marketing_carriers'] ?? null);
        $this->assertTrue($plan['command_pricing_schema_valid'] ?? false);
    }

    public function test_mixed_plan_cannot_pass_when_command_pricing_schema_invalid(): void
    {
        $preflight = [
            'allowed' => true,
            'block_reason' => null,
            'has_fare_basis' => true,
            'has_booking_class' => true,
            'has_validating_carrier' => true,
            'rbd_carrier_mapping_complete' => true,
            'mixed_fare_carrier_mapping_complete' => true,
            'no_fares_rbd_carrier_preflight_risk' => false,
            'mixed_mapping_comparison_result' => 'match',
            'command_pricing_schema_valid' => false,
            'command_pricing_rejected_keys' => ['Airline'],
        ];
        $mapped = (new \ReflectionMethod(
            SabreGdsLiveScenarioPlanCandidateDiagnostics::class,
            'mapPayloadPreflightToPlanDiagnostics',
        ));
        $mapped->setAccessible(true);
        $plan = $mapped->invoke(app(SabreGdsLiveScenarioPlanCandidateDiagnostics::class), $preflight);

        $this->assertSame(
            SabreGdsLiveScenarioPlanCandidateDiagnostics::PAYLOAD_PREFLIGHT_STATUS_BLOCKED,
            $plan['payload_preflight_status'] ?? null,
        );
        $this->assertSame(
            SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_V24_COMMANDPRICING_SCHEMA_INVALID,
            $plan['block_reason'] ?? null,
        );
    }

    public function test_mixed_plan_cannot_pass_when_segmentselect_pairing_incomplete(): void
    {
        $preflight = [
            'allowed' => false,
            'block_reason' => SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_V24_COMMANDPRICING_SEGMENTSELECT_PAIRING_MISSING,
            'has_fare_basis' => true,
            'has_booking_class' => true,
            'has_validating_carrier' => true,
            'rbd_carrier_mapping_complete' => true,
            'mixed_fare_carrier_mapping_complete' => true,
            'no_fares_rbd_carrier_preflight_risk' => false,
            'mixed_mapping_comparison_result' => 'match',
            'command_pricing_schema_valid' => true,
            'command_pricing_segmentselect_pairing_complete' => false,
            'command_pricing_segmentselect_missing_rph' => ['1', '2'],
        ];
        $mapped = (new \ReflectionMethod(
            SabreGdsLiveScenarioPlanCandidateDiagnostics::class,
            'mapPayloadPreflightToPlanDiagnostics',
        ));
        $mapped->setAccessible(true);
        $plan = $mapped->invoke(app(SabreGdsLiveScenarioPlanCandidateDiagnostics::class), $preflight);

        $this->assertSame(
            SabreGdsLiveScenarioPlanCandidateDiagnostics::PAYLOAD_PREFLIGHT_STATUS_BLOCKED,
            $plan['payload_preflight_status'] ?? null,
        );
        $this->assertFalse($plan['command_pricing_segmentselect_pairing_complete'] ?? true);
        $this->assertSame(
            SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_V24_COMMANDPRICING_SEGMENTSELECT_PAIRING_MISSING,
            $plan['block_reason'] ?? null,
        );
    }

    public function test_mixed_plan_cannot_pass_when_brand_rph_schema_invalid(): void
    {
        $preflight = [
            'allowed' => false,
            'block_reason' => SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_V24_BRAND_RPH_SCHEMA_INVALID,
            'has_fare_basis' => true,
            'has_booking_class' => true,
            'has_validating_carrier' => true,
            'rbd_carrier_mapping_complete' => true,
            'mixed_fare_carrier_mapping_complete' => true,
            'no_fares_rbd_carrier_preflight_risk' => false,
            'mixed_mapping_comparison_result' => 'match',
            'command_pricing_schema_valid' => true,
            'command_pricing_segmentselect_pairing_complete' => true,
            'brand_present' => true,
            'brand_rph_schema_valid' => false,
            'brand_schema_valid' => false,
            'brand_segmentselect_pairing_required' => true,
            'brand_segmentselect_pairing_complete' => false,
        ];
        $mapped = (new \ReflectionMethod(
            SabreGdsLiveScenarioPlanCandidateDiagnostics::class,
            'mapPayloadPreflightToPlanDiagnostics',
        ));
        $mapped->setAccessible(true);
        $plan = $mapped->invoke(app(SabreGdsLiveScenarioPlanCandidateDiagnostics::class), $preflight);

        $this->assertSame(
            SabreGdsLiveScenarioPlanCandidateDiagnostics::PAYLOAD_PREFLIGHT_STATUS_BLOCKED,
            $plan['payload_preflight_status'] ?? null,
        );
        $this->assertFalse($plan['brand_rph_schema_valid'] ?? true);
        $this->assertSame(
            SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_V24_BRAND_RPH_SCHEMA_INVALID,
            $plan['block_reason'] ?? null,
        );
    }

    public function test_mixed_plan_cannot_pass_when_brand_segmentselect_pairing_incomplete(): void
    {
        $preflight = [
            'allowed' => false,
            'block_reason' => SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_V24_BRAND_SEGMENTSELECT_PAIRING_MISSING,
            'has_fare_basis' => true,
            'has_booking_class' => true,
            'has_validating_carrier' => true,
            'rbd_carrier_mapping_complete' => true,
            'mixed_fare_carrier_mapping_complete' => true,
            'no_fares_rbd_carrier_preflight_risk' => false,
            'mixed_mapping_comparison_result' => 'match',
            'command_pricing_schema_valid' => true,
            'command_pricing_segmentselect_pairing_complete' => true,
            'brand_present' => true,
            'brand_schema_valid' => false,
            'brand_segmentselect_pairing_required' => true,
            'brand_segmentselect_pairing_complete' => false,
            'brand_segmentselect_missing_rph' => ['1', '2'],
        ];
        $mapped = (new \ReflectionMethod(
            SabreGdsLiveScenarioPlanCandidateDiagnostics::class,
            'mapPayloadPreflightToPlanDiagnostics',
        ));
        $mapped->setAccessible(true);
        $plan = $mapped->invoke(app(SabreGdsLiveScenarioPlanCandidateDiagnostics::class), $preflight);

        $this->assertSame(
            SabreGdsLiveScenarioPlanCandidateDiagnostics::PAYLOAD_PREFLIGHT_STATUS_BLOCKED,
            $plan['payload_preflight_status'] ?? null,
        );
        $this->assertFalse($plan['brand_segmentselect_pairing_complete'] ?? true);
        $this->assertSame(
            SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_V24_BRAND_SEGMENTSELECT_PAIRING_MISSING,
            $plan['block_reason'] ?? null,
        );
    }

    public function test_mixed_plan_incomplete_candidate_shows_payload_preflight_blocked(): void
    {
        $snap = $this->mixedOneStopSnapshot();
        foreach ($snap['segments'] as $i => $seg) {
            $snap['segments'][$i]['fare_basis_code'] = '';
            $snap['segments'][$i]['booking_class'] = '';
        }
        $snap['sabre_booking_context']['fare_basis_codes_by_segment'] = [];
        $snap['sabre_booking_context']['booking_classes_by_segment'] = [];

        $plan = $this->diagnoseMixedPlanCandidate($snap, [
            'booking_classes_by_segment' => [],
            'fare_basis_codes_by_segment' => [],
        ]);

        $this->assertSame(SabreGdsLiveScenarioPlanCandidateDiagnostics::PAYLOAD_PREFLIGHT_STATUS_BLOCKED, $plan['payload_preflight_status'] ?? null);
        $this->assertContains(
            $plan['payload_preflight_block_reason'] ?? '',
            [
                SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_PAYLOAD_MAPPING_INCOMPLETE,
                SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_PAYLOAD_MAPPING_UNAVAILABLE,
                SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_FARE_COMPONENT_CARRIER_MAPPING_UNAVAILABLE,
                SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_V24_COMMANDPRICING_SCHEMA_INVALID,
            ],
        );
        $this->assertSame($plan['payload_preflight_block_reason'] ?? null, $plan['block_reason'] ?? 'mismatch');
        $this->assertNull($plan['selected_strategy_expected'] ?? null);
        $this->assertFalse($plan['live_call_attempted'] ?? true);
        $this->assertFalse($plan['pnr_attempted'] ?? true);
    }

    public function test_same_carrier_plan_lane_unchanged_with_mixed_approval_flag(): void
    {
        $snap = $this->threeStopWySnapshot();
        $plan = app(SabreGdsLiveScenarioPlanCandidateDiagnostics::class)->diagnose($snap, [
            'route' => 'LHE-DOH-MCT-BAH-DXB',
            'segment_count' => 4,
            'marketing_carriers' => ['WY', 'WY', 'WY', 'WY'],
            'carrier_chain' => 'WY',
            'validating_carrier' => 'WY',
            'same_carrier' => true,
            'mixed_carrier' => false,
            'booking_classes_by_segment' => ['V', 'V', 'V', 'V'],
            'fare_basis_codes_by_segment' => ['VCMOEQR', 'VCMOEQR', 'VCMOEQR', 'VCMOEQR'],
            'cabin_by_segment' => ['economy', 'economy', 'economy', 'economy'],
        ], [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_date' => '2026-08-18',
        ], [
            'mixed_carrier_certification_approved' => true,
            'connection' => $this->resolveSabreConnection(),
        ]);

        $this->assertSame(SabreGdsLiveScenarioPlanCandidateDiagnostics::BLOCK_ADVANCED_ITINERARY_PLAN_ONLY, $plan['block_reason'] ?? null);
        $this->assertArrayNotHasKey('payload_preflight_status', $plan);
    }

    public function test_mixed_book_executor_blocks_incomplete_payload_before_live_call(): void
    {
        $snap = $this->mixedOneStopSnapshot();
        $snap['sabre_booking_context']['fare_basis_codes_by_segment'] = [];
        $snap['sabre_booking_context']['booking_classes_by_segment'] = [];
        foreach ($snap['segments'] as $i => $seg) {
            $snap['segments'][$i]['fare_basis_code'] = '';
            $snap['segments'][$i]['booking_class'] = '';
        }
        $booking = $this->makeMixedOneStopBooking([
            'meta' => array_merge($this->mixedOneStopBookingMeta(), [
                'normalized_offer_snapshot' => $snap,
                'sabre_booking_context' => array_merge($this->mixedOneStopBookingMeta()['sabre_booking_context'], [
                    'fare_basis_codes_by_segment' => [],
                    'booking_classes_by_segment' => [],
                ]),
                'selected_fare_family_option' => array_merge(
                    $this->mixedOneStopBookingMeta()['selected_fare_family_option'],
                    ['fare_basis_codes_by_segment' => [], 'booking_classes_by_segment' => []],
                ),
            ]),
        ]);

        $result = app(SabreGdsLiveScenarioRunnerPnrExecutor::class)->execute($booking, true, [
            'mixed_carrier_certification_approved' => true,
        ]);

        $this->assertFalse($result['live_call_attempted'] ?? true);
        $this->assertFalse($result['pnr_attempted'] ?? true);
        $this->assertFalse($result['ticketing_attempted'] ?? true);
        $this->assertFalse($result['airticket_attempted'] ?? true);
        $this->assertContains(
            (string) ($result['reason_code'] ?? $result['error_code'] ?? $result['block_reason'] ?? ''),
            [
                SabreGdsLiveScenarioRunnerPnrExecutor::REASON_COMPLETION_NOT_READY,
                SabreGdsLiveScenarioRunnerPnrExecutor::REASON_BOOKING_PAYLOAD_NOT_READY,
                SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_PAYLOAD_MAPPING_INCOMPLETE,
                SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_PAYLOAD_MAPPING_UNAVAILABLE,
                SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_FARE_COMPONENT_CARRIER_MAPPING_UNAVAILABLE,
                SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_V24_COMMANDPRICING_SCHEMA_INVALID,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $snap
     * @param  array<string, mixed>  $rowOverrides
     * @return array<string, mixed>
     */
    protected function diagnoseMixedPlanCandidate(array $snap, array $rowOverrides = []): array
    {
        return app(SabreGdsLiveScenarioPlanCandidateDiagnostics::class)->diagnose($snap, array_merge([
            'route' => 'LHE-DOH-DXB',
            'segment_count' => 2,
            'marketing_carriers' => ['QR', 'EK'],
            'carrier_chain' => 'QR+EK',
            'validating_carrier' => 'QR',
            'same_carrier' => false,
            'mixed_carrier' => true,
            'booking_classes_by_segment' => ['Y', 'Y'],
            'fare_basis_codes_by_segment' => ['YLOW', 'YLOW'],
        ], $rowOverrides), [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_date' => '2026-08-18',
        ], [
            'mixed_carrier_certification_approved' => true,
            'connection' => $this->resolveSabreConnection(),
        ]);
    }

    protected function resolveSabreConnection(): SupplierConnection
    {
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

    public function test_scenario_runner_freshness_override_skips_bfm_for_multistop_iati(): void
    {
        $service = app(SabreBookingService::class);
        $method = new \ReflectionMethod($service, 'applyScenarioRunnerFreshnessOverride');
        $method->setAccessible(true);
        $completion = [
            'public_auto_pnr_attempt_ready' => true,
            'auto_pnr_context_completion_status' => SabreGdsAutoPnrContextCompletionService::STATUS_COMPLETE,
        ];
        $serviceReflection = new \ReflectionClass($service);
        $styleProp = $serviceReflection->getProperty('attemptPassengerRecordsStyleDecision');
        $styleProp->setAccessible(true);
        $styleProp->setValue($service, ['iati_like_selected' => true]);

        $result = $method->invoke($service, [
            'revalidation_required' => true,
            'blocks_booking' => true,
        ], ['auto_pnr_context_completion' => $completion]);

        $this->assertFalse($result['revalidation_required']);
        $this->assertTrue($result['revalidation_skipped']);
        $this->assertSame('scenario_runner_context_completion', $result['freshness_source']);
    }

    public function test_pk_direct_remains_public_auto_certified_with_iati_strategy(): void
    {
        $booking = $this->makeDirectPkBooking();
        $cert = app(SabreConnectingBrandedFarePublicAutoCertification::class)->assess($booking);
        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForBooking($booking);

        $this->assertTrue($cert['public_auto_certified']);
        $this->assertTrue($cert['connecting_brand_context_complete']);
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['selected_strategy'],
        );
        $this->assertTrue($selection['public_auto_certified']);
    }

    public function test_qr_connecting_collapsed_single_segment_brand_context_completes_and_selects_strategy(): void
    {
        $booking = $this->makeQrConnectingBooking();
        $completion = app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking);
        $cert = app(SabreConnectingBrandedFarePublicAutoCertification::class)->assess($booking);
        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForBooking($booking);
        $summary = app(SabreGdsPnrCreateStrategyDigest::class)->buildBookingSummary($booking);

        $this->assertSame(SabreGdsAutoPnrContextCompletionService::STATUS_REPAIRED, $completion['auto_pnr_context_completion_status']);
        $this->assertTrue($completion['public_auto_pnr_attempt_ready']);
        $this->assertContains('normalized_offer_pricing_index', $completion['completion_sources_used']);
        $this->assertSame(2, $completion['booking_classes_by_segment_count']);
        $this->assertSame(['H', 'H'], $completion['completed_booking_classes_by_segment']);
        $this->assertFalse($completion['expanded_single_fare_component_to_all_segments']);
        $this->assertFalse($cert['per_segment_booking_class_complete']);
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['selected_strategy'],
        );
        $this->assertTrue($selection['public_auto_pnr_attempt_ready']);
        $this->assertSame(2, $summary['booking_classes_by_segment_count']);
        $this->assertSame(2, $summary['completed_booking_classes_by_segment_count']);
        $this->assertSame(2, $summary['segment_count']);
    }

    public function test_qr_connecting_complete_segment_context_selects_strategy_without_known_good_evidence(): void
    {
        $booking = $this->makeQrConnectingBooking([
            'meta' => array_merge($this->qrConnectingMeta(), [
                'selected_fare_family_option' => array_merge($this->qrConnectingMeta()['selected_fare_family_option'], [
                    'booking_classes_by_segment' => ['H', 'H'],
                    'fare_basis_codes_by_segment' => ['HJR4R1FI/H', 'HJR4R1FI/H'],
                    'cabin_by_segment' => ['economy', 'economy'],
                ]),
                'sabre_booking_context' => array_merge($this->qrConnectingMeta()['sabre_booking_context'], [
                    'booking_classes_by_segment' => ['H', 'H'],
                    'fare_basis_codes_by_segment' => ['HJR4R1FI/H', 'HJR4R1FI/H'],
                    'cabin_by_segment' => ['economy', 'economy'],
                    'segment_slice_count' => 2,
                ]),
            ]),
        ]);
        $completion = app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking);
        $cert = app(SabreConnectingBrandedFarePublicAutoCertification::class)->assess($booking);
        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForBooking($booking);

        $this->assertTrue($cert['connecting_brand_context_complete']);
        $this->assertTrue($cert['public_auto_certified']);
        $this->assertSame(SabreGdsAutoPnrContextCompletionService::STATUS_COMPLETE, $completion['auto_pnr_context_completion_status']);
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['selected_strategy'],
        );
    }

    public function test_context_completion_expands_scalar_only_when_supplier_marks_all_segments(): void
    {
        $booking = $this->makeQrConnectingBooking([
            'meta' => array_merge($this->qrConnectingMeta(), [
                'selected_fare_family_option' => array_merge($this->qrConnectingMeta()['selected_fare_family_option'], [
                    'single_fare_component_applies_to_all_segments' => true,
                ]),
            ]),
        ]);

        $completion = app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking);

        $this->assertTrue($completion['public_auto_pnr_attempt_ready']);
        $this->assertTrue($completion['expanded_single_fare_component_to_all_segments']);
        $this->assertSame(['H', 'H'], $completion['completed_booking_classes_by_segment']);
    }

    public function test_context_completion_reads_per_segment_gir_fare_components(): void
    {
        $gir = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_two_segment_connecting_refs.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $booking = $this->makeQrConnectingBooking([
            'meta' => array_merge($this->qrConnectingMeta(), [
                'selected_fare_family_option' => [
                    'brand_code' => 'ECOMFORT',
                    'booking_classes_by_segment' => ['H'],
                    'fare_basis_codes_by_segment' => ['HJR4R1FI/H'],
                ],
                'sabre_booking_context' => [
                    'selected_brand_code' => 'ECOMFORT',
                    'validating_carrier' => 'QR',
                    'booking_classes_by_segment' => ['H'],
                    'fare_basis_codes_by_segment' => ['HJR4R1FI/H'],
                    'segment_slice_count' => 2,
                    'pricing_information_index' => 0,
                ],
                'normalized_offer_snapshot' => [
                    'validating_carrier' => 'QR',
                    'segments' => [
                        ['origin' => 'LHE', 'destination' => 'JED', 'carrier' => 'SV', 'flight_number' => '739'],
                        ['origin' => 'JED', 'destination' => 'DXB', 'carrier' => 'SV', 'flight_number' => '568'],
                    ],
                    'raw_payload' => [
                        'sabre_bfm_gir_archive' => $gir,
                        'sabre_shop_context' => [
                            'itinerary_group_index' => 0,
                            'itinerary_index' => 0,
                            'pricing_information_index' => 0,
                        ],
                    ],
                ],
            ]),
        ]);

        $completion = app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking);

        $this->assertContains('gir_fare_components', $completion['completion_sources_used']);
        $this->assertSame(['Q', 'Q'], $completion['completed_booking_classes_by_segment']);
        $this->assertSame(['QCLASS01', 'QCLASS02'], $completion['completed_fare_basis_codes_by_segment']);
    }

    public function test_qr_connecting_unrecoverable_context_blocks_without_supplier_mutation(): void
    {
        $booking = $this->makeQrConnectingBooking([
            'meta' => array_merge($this->qrConnectingMeta(), [
                'normalized_offer_snapshot' => [
                    'validating_carrier' => 'QR',
                    'segments' => [
                        ['origin' => 'LHE', 'destination' => 'DOH', 'carrier' => 'QR', 'flight_number' => '615'],
                        ['origin' => 'DOH', 'destination' => 'DXB', 'carrier' => 'QR', 'flight_number' => '1002'],
                    ],
                ],
            ]),
        ]);

        $completion = app(SabreGdsAutoPnrContextCompletionService::class)->completeForBooking($booking);
        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForBooking($booking);

        $this->assertFalse($completion['public_auto_pnr_attempt_ready']);
        $this->assertNull($selection['selected_strategy']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeQrConnectingBooking(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::query()->create(array_merge([
            'agency_id' => $agency->id,
            'booking_reference' => 'BR-QR-CONN-'.uniqid(),
            'status' => 'pending',
            'supplier' => SupplierProvider::Sabre->value,
            'selected_fare_total' => 290800,
            'revalidated_fare_total' => 290800,
            'meta' => $this->qrConnectingMeta(),
        ], $overrides));

        $booking->forceFill(['travel_date' => '2026-08-20'])->save();
        $this->seedPassengers($booking, 290800);

        return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function qrConnectingMeta(): array
    {
        return [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 2,
            'payment_mode' => 'pay_later_booking_request',
            'distribution_channel' => 'gds',
            'selected_fare_family_option' => [
                'brand_code' => 'ECOMFORT',
                'name' => 'ECOMFORT',
                'displayed_price' => 290800,
                'booking_class' => 'H',
                'fare_basis' => 'HJR4R1FI/H',
                'booking_classes_by_segment' => ['H'],
                'fare_basis_codes_by_segment' => ['HJR4R1FI/H'],
            ],
            'sabre_booking_context' => [
                'selected_brand_code' => 'ECOMFORT',
                'brand_code' => 'ECOMFORT',
                'validating_carrier' => 'QR',
                'booking_classes_by_segment' => ['H'],
                'fare_basis_codes_by_segment' => ['HJR4R1FI/H'],
                'selected_price_total' => 290800,
                'segment_slice_count' => 2,
            ],
            'normalized_offer_snapshot' => $this->qrConnectingSnapshot(),
            'search_criteria' => [
                'trip_type' => 'one_way',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-08-20',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function qrConnectingSnapshot(): array
    {
        return [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 2,
            'supplier_offer_id' => 'qr-lhe-dxb-connect',
            'offer_id' => 'qr-lhe-dxb-connect',
            'validating_carrier' => 'QR',
            'distribution_channel' => 'gds',
            'total' => 290800,
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DOH',
                    'carrier' => 'QR',
                    'marketing_carrier' => 'QR',
                    'operating_carrier' => 'QR',
                    'flight_number' => '615',
                    'departure_at' => '2026-08-20T03:30:00',
                    'arrival_at' => '2026-08-20T05:45:00',
                    'booking_class' => 'H',
                    'fare_basis_code' => 'HJR4R1FI/H',
                ],
                [
                    'origin' => 'DOH',
                    'destination' => 'DXB',
                    'carrier' => 'QR',
                    'marketing_carrier' => 'QR',
                    'operating_carrier' => 'QR',
                    'flight_number' => '1002',
                    'departure_at' => '2026-08-20T08:10:00',
                    'arrival_at' => '2026-08-20T10:05:00',
                    'booking_class' => 'H',
                    'fare_basis_code' => 'HJR4R1FI/H',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 290800,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
    }

    protected function configureOperationalFlags(): void
    {
        Config::set('suppliers.sabre.verified_multiseg_auto_pnr_enabled', true);
        Config::set('suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled', true);
        Config::set('suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled', true);
        Config::set('suppliers.sabre.cpnr_iati_style_certified_gds_enabled', true);
        Config::set('suppliers.sabre.traditional_cpnr_airprice_validating_carrier', true);
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.pnr_create_enabled', true);
        Config::set('suppliers.sabre.admin_manual_pnr_enabled', true);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeReturnPkBooking(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::query()->create(array_merge([
            'agency_id' => $agency->id,
            'booking_reference' => 'BR-PK-RETURN-'.uniqid(),
            'status' => 'pending',
            'supplier' => SupplierProvider::Sabre->value,
            'route' => 'LHE-DXB-LHE',
            'selected_fare_total' => 43050,
            'revalidated_fare_total' => 43050,
            'meta' => $this->returnPkBookingMeta(),
        ], $overrides));

        $booking->forceFill(['travel_date' => '2026-08-15'])->save();
        $this->seedPassengers($booking);

        return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function returnPkBookingMeta(): array
    {
        return [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 2,
            'payment_mode' => 'pay_later_booking_request',
            'distribution_channel' => 'gds',
            'selected_fare_family_option' => [
                'brand_code' => 'LT',
                'displayed_price' => 43050,
                'ready_for_booking_payload' => true,
                'booking_classes_by_segment' => ['V', 'V'],
                'fare_basis_codes_by_segment' => ['VNBAG', 'VNBAG'],
                'cabin_by_segment' => ['economy', 'economy'],
            ],
            'sabre_booking_context' => [
                'selected_brand_code' => 'LT',
                'brand_code' => 'LT',
                'validating_carrier' => 'PK',
                'booking_classes_by_segment' => ['V', 'V'],
                'fare_basis_codes_by_segment' => ['VNBAG', 'VNBAG'],
                'cabin_by_segment' => ['economy', 'economy'],
                'segment_slice_count' => 2,
                'segment_count' => 2,
                'ready_for_booking_payload' => true,
            ],
            'normalized_offer_snapshot' => $this->returnPkSnapshot(),
            'search_criteria' => [
                'trip_type' => 'return',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-08-15',
                'return_date' => '2026-08-22',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function returnPkSnapshot(): array
    {
        return [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 2,
            'supplier_offer_id' => 'pk-lhe-dxb-return',
            'offer_id' => 'pk-lhe-dxb-return',
            'validating_carrier' => 'PK',
            'distribution_channel' => 'gds',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'carrier' => 'PK',
                    'marketing_carrier' => 'PK',
                    'operating_carrier' => 'PK',
                    'flight_number' => '233',
                    'departure_at' => '2026-08-15T08:00:00',
                    'arrival_at' => '2026-08-15T11:00:00',
                    'booking_class' => 'V',
                    'fare_basis_code' => 'VNBAG',
                ],
                [
                    'origin' => 'DXB',
                    'destination' => 'LHE',
                    'carrier' => 'PK',
                    'marketing_carrier' => 'PK',
                    'operating_carrier' => 'PK',
                    'flight_number' => '234',
                    'departure_at' => '2026-08-22T12:00:00',
                    'arrival_at' => '2026-08-22T17:00:00',
                    'booking_class' => 'V',
                    'fare_basis_code' => 'VNBAG',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 43050,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function returnMixedCarrierSnapshot(): array
    {
        $snap = $this->returnPkSnapshot();
        $snap['segments'][1]['carrier'] = 'EK';
        $snap['segments'][1]['marketing_carrier'] = 'EK';

        return $snap;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeMultistopWyBooking(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::query()->create(array_merge([
            'agency_id' => $agency->id,
            'booking_reference' => 'BR-WY-MULTI-'.uniqid(),
            'status' => 'pending',
            'supplier' => SupplierProvider::Sabre->value,
            'route' => 'LHE-DOH-MCT-DXB',
            'selected_fare_total' => 95650,
            'revalidated_fare_total' => 95650,
            'meta' => $this->multistopWyBookingMeta(),
        ], $overrides));

        $booking->forceFill(['travel_date' => '2026-08-18'])->save();
        $this->seedPassengers($booking);

        return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function multistopWyBookingMeta(): array
    {
        return [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 2,
            'payment_mode' => 'pay_later_booking_request',
            'distribution_channel' => 'gds',
            'selected_fare_family_option' => [
                'brand_code' => 'EC',
                'displayed_price' => 95650,
                'ready_for_booking_payload' => true,
                'booking_classes_by_segment' => ['V', 'V', 'V'],
                'fare_basis_codes_by_segment' => ['VCMOEQR', 'VCMOEQR', 'VCMOEQR'],
                'cabin_by_segment' => ['economy', 'economy', 'economy'],
            ],
            'sabre_booking_context' => [
                'selected_brand_code' => 'EC',
                'brand_code' => 'EC',
                'validating_carrier' => 'WY',
                'booking_classes_by_segment' => ['V', 'V', 'V'],
                'fare_basis_codes_by_segment' => ['VCMOEQR', 'VCMOEQR', 'VCMOEQR'],
                'cabin_by_segment' => ['economy', 'economy', 'economy'],
                'leg_refs' => [1],
                'schedule_refs' => [1, 2, 3],
                'segment_slice_count' => 3,
                'segment_count' => 3,
                'ready_for_booking_payload' => true,
            ],
            'normalized_offer_snapshot' => $this->multistopWySnapshot(),
            'search_criteria' => [
                'trip_type' => 'one_way',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-08-18',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function multistopWySnapshot(): array
    {
        return [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 2,
            'supplier_offer_id' => 'wy-lhe-dxb-multistop',
            'offer_id' => 'wy-lhe-dxb-multistop',
            'validating_carrier' => 'WY',
            'distribution_channel' => 'gds',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DOH',
                    'carrier' => 'WY',
                    'marketing_carrier' => 'WY',
                    'operating_carrier' => 'WY',
                    'flight_number' => '604',
                    'departure_at' => '2026-08-18T04:30:00',
                    'arrival_at' => '2026-08-18T06:15:00',
                    'booking_class' => 'V',
                    'fare_basis_code' => 'VCMOEQR',
                    'cabin' => 'economy',
                ],
                [
                    'origin' => 'DOH',
                    'destination' => 'MCT',
                    'carrier' => 'WY',
                    'marketing_carrier' => 'WY',
                    'operating_carrier' => 'WY',
                    'flight_number' => '603',
                    'departure_at' => '2026-08-18T08:00:00',
                    'arrival_at' => '2026-08-18T09:10:00',
                    'booking_class' => 'V',
                    'fare_basis_code' => 'VCMOEQR',
                    'cabin' => 'economy',
                ],
                [
                    'origin' => 'MCT',
                    'destination' => 'DXB',
                    'carrier' => 'WY',
                    'marketing_carrier' => 'WY',
                    'operating_carrier' => 'WY',
                    'flight_number' => '602',
                    'departure_at' => '2026-08-18T11:30:00',
                    'arrival_at' => '2026-08-18T12:35:00',
                    'booking_class' => 'V',
                    'fare_basis_code' => 'VCMOEQR',
                    'cabin' => 'economy',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 95650,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function multistopMixedCarrierSnapshot(): array
    {
        $snap = $this->multistopWySnapshot();
        $snap['segments'][2]['carrier'] = 'PK';
        $snap['segments'][2]['marketing_carrier'] = 'PK';

        return $snap;
    }

    /**
     * @return array<string, mixed>
     */
    protected function threeStopWySnapshot(): array
    {
        $snap = $this->multistopWySnapshot();
        $snap['segments'][2]['destination'] = 'BAH';
        $snap['segments'][] = [
            'origin' => 'BAH',
            'destination' => 'DXB',
            'carrier' => 'WY',
            'marketing_carrier' => 'WY',
            'operating_carrier' => 'WY',
            'flight_number' => '601',
            'departure_at' => '2026-08-18T14:00:00',
            'arrival_at' => '2026-08-18T15:30:00',
            'booking_class' => 'V',
            'fare_basis_code' => 'VCMOEQR',
            'cabin' => 'economy',
        ];

        return $snap;
    }

    /**
     * @return array<string, mixed>
     */
    protected function fourStopWySnapshot(): array
    {
        $snap = $this->threeStopWySnapshot();
        $snap['segments'][3]['destination'] = 'MCT';
        $snap['segments'][] = [
            'origin' => 'MCT',
            'destination' => 'DXB',
            'carrier' => 'WY',
            'marketing_carrier' => 'WY',
            'operating_carrier' => 'WY',
            'flight_number' => '600',
            'departure_at' => '2026-08-18T17:00:00',
            'arrival_at' => '2026-08-18T18:15:00',
            'booking_class' => 'V',
            'fare_basis_code' => 'VCMOEQR',
            'cabin' => 'economy',
        ];

        return $snap;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mixedOneStopSnapshot(): array
    {
        return [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 2,
            'supplier_offer_id' => 'qr-ek-lhe-dxb-mixed',
            'offer_id' => 'qr-ek-lhe-dxb-mixed',
            'validating_carrier' => 'QR',
            'distribution_channel' => 'gds',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'marketing_carrier_chain' => ['QR', 'EK'],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DOH',
                    'carrier' => 'QR',
                    'marketing_carrier' => 'QR',
                    'operating_carrier' => 'QR',
                    'flight_number' => '614',
                    'departure_at' => '2026-08-18T04:30:00',
                    'arrival_at' => '2026-08-18T06:15:00',
                    'booking_class' => 'Y',
                    'fare_basis_code' => 'YLOW',
                    'cabin' => 'economy',
                ],
                [
                    'origin' => 'DOH',
                    'destination' => 'DXB',
                    'carrier' => 'EK',
                    'marketing_carrier' => 'EK',
                    'operating_carrier' => 'EK',
                    'flight_number' => '842',
                    'departure_at' => '2026-08-18T08:00:00',
                    'arrival_at' => '2026-08-18T09:30:00',
                    'booking_class' => 'Y',
                    'fare_basis_code' => 'YLOW',
                    'cabin' => 'economy',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 85000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'sabre_booking_context' => [
                'validating_carrier' => 'QR',
                'booking_classes_by_segment' => ['Y', 'Y'],
                'fare_basis_codes_by_segment' => ['YLOW', 'YLOW'],
                'cabin_by_segment' => ['economy', 'economy'],
                'schedule_refs' => [1, 2],
                'leg_refs' => [1],
                'segment_slice_count' => 2,
                'ready_for_booking_payload' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mixedOneStopBookingMeta(): array
    {
        return [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 2,
            'payment_mode' => 'pay_later_booking_request',
            'distribution_channel' => 'gds',
            'scenario_runner' => true,
            'origin_channel' => 'scenario_runner',
            'selected_fare_family_option' => [
                'brand_code' => 'EC',
                'displayed_price' => 85000,
                'ready_for_booking_payload' => true,
                'booking_classes_by_segment' => ['Y', 'Y'],
                'fare_basis_codes_by_segment' => ['YLOW', 'YLOW'],
                'cabin_by_segment' => ['economy', 'economy'],
            ],
            'sabre_booking_context' => [
                'selected_brand_code' => 'EC',
                'brand_code' => 'EC',
                'validating_carrier' => 'QR',
                'booking_classes_by_segment' => ['Y', 'Y'],
                'fare_basis_codes_by_segment' => ['YLOW', 'YLOW'],
                'cabin_by_segment' => ['economy', 'economy'],
                'schedule_refs' => [1, 2],
                'leg_refs' => [1],
                'segment_slice_count' => 2,
                'segment_count' => 2,
                'ready_for_booking_payload' => true,
            ],
            'normalized_offer_snapshot' => $this->mixedOneStopSnapshot(),
            'search_criteria' => [
                'trip_type' => 'one_way',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-08-18',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeMixedOneStopBooking(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::query()->create(array_merge([
            'agency_id' => $agency->id,
            'booking_reference' => 'BR-MIXED-OW-'.uniqid(),
            'status' => 'pending',
            'supplier' => SupplierProvider::Sabre->value,
            'route' => 'LHE-DOH-DXB',
            'selected_fare_total' => 85000,
            'revalidated_fare_total' => 85000,
            'meta' => $this->mixedOneStopBookingMeta(),
        ], $overrides));

        $booking->forceFill(['travel_date' => '2026-08-18'])->save();
        $this->seedPassengers($booking, 85000);

        return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeMixedReturnBooking(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::query()->create(array_merge([
            'agency_id' => $agency->id,
            'booking_reference' => 'BR-MIXED-RT-'.uniqid(),
            'status' => 'pending',
            'supplier' => SupplierProvider::Sabre->value,
            'route' => 'LHE-DXB-LHE',
            'selected_fare_total' => 95000,
            'revalidated_fare_total' => 95000,
            'meta' => $this->mixedReturnBookingMeta(),
        ], $overrides));

        $booking->forceFill(['travel_date' => '2026-08-18'])->save();
        $this->seedPassengers($booking, 95000);

        return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function mixedReturnBookingMeta(): array
    {
        $snap = $this->returnMixedCarrierSnapshot();
        $snap['sabre_booking_context'] = [
            'selected_brand_code' => 'LT',
            'brand_code' => 'LT',
            'validating_carrier' => 'PK',
            'booking_classes_by_segment' => ['V', 'V'],
            'fare_basis_codes_by_segment' => ['VNBAG', 'VNBAG'],
            'cabin_by_segment' => ['economy', 'economy'],
            'schedule_refs' => [1, 2],
            'leg_refs' => [1],
            'segment_slice_count' => 2,
            'segment_count' => 2,
            'ready_for_booking_payload' => true,
        ];

        return [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 2,
            'payment_mode' => 'pay_later_booking_request',
            'distribution_channel' => 'gds',
            'scenario_runner' => true,
            'origin_channel' => 'scenario_runner',
            'selected_fare_family_option' => [
                'brand_code' => 'LT',
                'displayed_price' => 95000,
                'ready_for_booking_payload' => true,
                'booking_classes_by_segment' => ['V', 'V'],
                'fare_basis_codes_by_segment' => ['VNBAG', 'VNBAG'],
                'cabin_by_segment' => ['economy', 'economy'],
            ],
            'sabre_booking_context' => $snap['sabre_booking_context'],
            'normalized_offer_snapshot' => $snap,
            'search_criteria' => [
                'trip_type' => 'return',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-08-18',
                'return_date' => '2026-08-22',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mixedThreeSegmentSnapshot(): array
    {
        $snap = $this->mixedOneStopSnapshot();
        $snap['segments'][] = [
            'origin' => 'DXB',
            'destination' => 'JED',
            'carrier' => 'EK',
            'marketing_carrier' => 'EK',
            'flight_number' => '801',
            'departure_at' => '2026-08-18T12:00:00',
            'booking_class' => 'Y',
            'fare_basis_code' => 'YLOW',
            'cabin' => 'economy',
        ];
        $snap['sabre_booking_context']['schedule_refs'] = [1, 2, 3];
        $snap['sabre_booking_context']['booking_classes_by_segment'] = ['Y', 'Y', 'Y'];
        $snap['sabre_booking_context']['fare_basis_codes_by_segment'] = ['YLOW', 'YLOW', 'YLOW'];
        $snap['sabre_booking_context']['cabin_by_segment'] = ['economy', 'economy', 'economy'];

        return $snap;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mixedFourSegmentSnapshot(): array
    {
        $snap = $this->mixedThreeSegmentSnapshot();
        $snap['segments'][] = [
            'origin' => 'JED',
            'destination' => 'RUH',
            'carrier' => 'EK',
            'marketing_carrier' => 'EK',
            'flight_number' => '802',
            'departure_at' => '2026-08-18T15:00:00',
            'booking_class' => 'Y',
            'fare_basis_code' => 'YLOW',
            'cabin' => 'economy',
        ];
        $snap['sabre_booking_context']['schedule_refs'] = [1, 2, 3, 4];
        $snap['sabre_booking_context']['booking_classes_by_segment'] = ['Y', 'Y', 'Y', 'Y'];
        $snap['sabre_booking_context']['fare_basis_codes_by_segment'] = ['YLOW', 'YLOW', 'YLOW', 'YLOW'];
        $snap['sabre_booking_context']['cabin_by_segment'] = ['economy', 'economy', 'economy', 'economy'];

        return $snap;
    }
}
