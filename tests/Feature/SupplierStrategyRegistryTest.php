<?php

namespace Tests\Feature;

use App\Console\Commands\SupplierCreateWithStrategyCommand;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Support\Bookings\SabreOperationalPnrReadiness;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyRegistry;
use App\Support\Suppliers\SupplierActionCode;
use App\Support\Suppliers\SupplierActionStrategyDigest;
use App\Support\Suppliers\SupplierActionStrategyRegistry;
use App\Support\Suppliers\SupplierActionStrategySelector;
use App\Support\Suppliers\SupplierLifecycleCapabilities;
use App\Support\Suppliers\SupplierMutationGuard;
use App\Support\Suppliers\SupplierPnrFlagGate;
use App\Support\Suppliers\SupplierPnrValidationSummary;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class SupplierStrategyRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.verified_multiseg_auto_pnr_enabled', true);
        Config::set('suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled', true);
        Config::set('suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled', true);
        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', true);
        Config::set('suppliers.sabre.cpnr_iati_style_certified_gds_enabled', true);
        Config::set('suppliers.sabre.traditional_cpnr_airprice_validating_carrier', true);
        Config::set('suppliers.sabre.ticketing_enabled', false);
    }

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');
        Mockery::close();
        parent::tearDown();
    }

    public function test_lifecycle_capabilities_declares_sabre_and_stubs(): void
    {
        $caps = app(SupplierLifecycleCapabilities::class);
        $this->assertTrue($caps->forProvider(SupplierProvider::Sabre->value)['create_pnr']);
        $this->assertTrue($caps->forProvider(SupplierProvider::PiaNdc->value)['create_order']);
        $this->assertFalse($caps->forProvider(SupplierProvider::Duffel->value)['create_pnr']);
    }

    public function test_universal_registry_delegates_sabre_without_leaking_to_duffel(): void
    {
        $registry = app(SupplierActionStrategyRegistry::class);
        $sabre = $registry->adapterFor(SupplierProvider::Sabre->value, SupplierActionCode::CREATE_PNR);
        $duffel = $registry->adapterFor(SupplierProvider::Duffel->value, SupplierActionCode::CREATE_PNR);

        $this->assertSame(SupplierProvider::Sabre->value, $sabre->provider());
        $this->assertCount(4, $sabre->supportedStrategyCodes());
        $this->assertSame([], $duffel->supportedStrategyCodes());
    }

    public function test_universal_digest_lists_multiple_sabre_strategies_with_certification(): void
    {
        $booking = $this->makeDirectPkBooking();
        $candidates = app(SupplierActionStrategyDigest::class)->buildCandidateDigests(
            $booking,
            SupplierProvider::Sabre->value,
            SupplierActionCode::CREATE_PNR,
        );

        $this->assertCount(4, $candidates);
        foreach ($candidates as $candidate) {
            $this->assertArrayHasKey('strategy_code', $candidate);
            $this->assertArrayHasKey('endpoint_path', $candidate);
            $this->assertArrayHasKey('payload_schema', $candidate);
            $this->assertArrayHasKey('certification_status', $candidate);
        }
    }

    public function test_selector_chooses_only_one_automatic_strategy(): void
    {
        $booking = $this->makeDirectPkBooking();
        $selection = app(SupplierActionStrategySelector::class)->selectForBooking(
            $booking,
            SupplierProvider::Sabre->value,
            SupplierActionCode::CREATE_PNR,
        );

        $this->assertNotNull($selection['selected_strategy']);
        $this->assertCount(1, [$selection['selected_strategy']]);
    }

    public function test_ticketing_enabled_does_not_block_pnr_create_flag_gate(): void
    {
        Config::set('suppliers.sabre.ticketing_enabled', true);
        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', true);

        $flags = app(SupplierPnrFlagGate::class)->sabreFlags();
        $this->assertTrue($flags['ticketing_enabled']);
        $this->assertTrue($flags['pnr_create_enabled']);

        $booking = $this->makeDirectPkBooking();
        $result = app(SabreOperationalPnrReadiness::class)->evaluate($booking);
        $this->assertNotContains('ticketing_disabled', $result['blocking_conditions']);
    }

    public function test_one_segment_direct_same_carrier_not_blocked_by_connecting_rule(): void
    {
        $booking = $this->makeDirectPkBooking();
        $result = app(SabreOperationalPnrReadiness::class)->evaluate($booking);

        $this->assertTrue($result['same_carrier']);
        $this->assertNotContains('same_carrier_connecting', $result['blocking_conditions']);
    }

    public function test_mutation_guard_blocks_existing_pnr_and_fare_mismatch(): void
    {
        $booking = $this->makeDirectPkBooking(['pnr' => 'ABC123']);
        $guard = app(SupplierMutationGuard::class)->assertCreateAllowed($booking, SupplierProvider::Sabre->value);
        $this->assertFalse($guard['allowed']);
        $this->assertSame(SupplierMutationGuard::REASON_DUPLICATE_PNR, $guard['reason_code']);
    }

    public function test_universal_create_command_requires_confirmation(): void
    {
        $booking = $this->makeDirectPkBooking();
        $this->artisan('supplier:create-with-strategy', [
            '--booking' => (string) $booking->id,
            '--provider' => 'sabre',
            '--action' => 'create_pnr',
            '--strategy' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
        ])->expectsOutputToContain('--confirm='.SupplierCreateWithStrategyCommand::CONFIRM_PHRASE)
            ->assertExitCode(1);
    }

    public function test_universal_create_command_blocked_in_production_without_ops_approval(): void
    {
        Config::set('app.env', 'production');
        $booking = $this->makeDirectPkBooking();
        $this->seedFailedAttempt($booking);

        $this->artisan('supplier:create-with-strategy', [
            '--booking' => (string) $booking->id,
            '--provider' => 'sabre',
            '--action' => 'create_pnr',
            '--strategy' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            '--confirm' => SupplierCreateWithStrategyCommand::CONFIRM_PHRASE,
        ])->expectsOutputToContain(
            'Production supplier create requires --production-ops-approval='.SupplierCreateWithStrategyCommand::PRODUCTION_OPS_APPROVAL_PHRASE
        )->assertExitCode(1);
    }

    public function test_universal_create_command_allowed_in_production_with_confirm_and_ops_approval(): void
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
        $this->app->instance(SabreBookingService::class, $mock);

        $this->artisan('supplier:create-with-strategy', [
            '--booking' => (string) $booking->id,
            '--provider' => 'sabre',
            '--action' => 'create_pnr',
            '--strategy' => $strategy,
            '--confirm' => SupplierCreateWithStrategyCommand::CONFIRM_PHRASE,
            '--production-ops-approval' => SupplierCreateWithStrategyCommand::PRODUCTION_OPS_APPROVAL_PHRASE,
        ])->expectsOutputToContain('production_ops_approved=true')
            ->expectsOutputToContain('preflight_passed=true')
            ->expectsOutputToContain('live_supplier_call_attempted=true')
            ->expectsOutputToContain('cancellation_attempted=false')
            ->assertExitCode(0);
    }

    public function test_universal_digest_command_runs_for_sabre(): void
    {
        $booking = $this->makeDirectPkBooking();
        $this->artisan('supplier:strategy-digest', [
            '--booking' => (string) $booking->id,
            '--provider' => 'sabre',
            '--action' => 'create_pnr',
        ])->expectsOutputToContain('candidate[0]')
            ->assertExitCode(0);
    }

    public function test_diagnostics_output_has_no_forbidden_secrets(): void
    {
        $booking = $this->makeDirectPkBooking();
        $output = json_encode(app(SupplierActionStrategyDigest::class)->buildCandidateDigests(
            $booking,
            SupplierProvider::Sabre->value,
            SupplierActionCode::CREATE_PNR,
        ));
        $this->assertIsString($output);
        $this->assertStringNotContainsString('password', strtolower($output));
        $this->assertStringNotContainsString('createpassengernamerecordrq', strtolower($output));
    }

    public function test_pnr_validation_summary_reports_ticketing_not_required(): void
    {
        $booking = $this->makeDirectPkBooking();
        $summary = app(SupplierPnrValidationSummary::class)->build($booking);
        $this->assertFalse($summary['pnr_created']);
        $this->assertFalse($summary['ticketing_required']);
    }

    public function test_previous_format_failure_exposes_admin_fallback_only(): void
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

        $selection = app(SupplierActionStrategySelector::class)->selectForBooking(
            $booking,
            SupplierProvider::Sabre->value,
            SupplierActionCode::CREATE_PNR,
        );
        $this->assertTrue($selection['fallback_available']);
        $this->assertNotSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['selected_strategy'],
        );
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
}
