<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Models\User;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreControlledPnrFareChangeAcceptance;
use App\Support\Bookings\SabreControlledPnrReadiness;
use App\Support\Bookings\SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate;
use App\Support\Bookings\SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate;
use App\Support\Bookings\SabreControlledPnrRetryAllowanceGate;
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use App\Support\Bookings\SupplierBookingPreflightGuard;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Support\Bookings\ControlledPnrContextTestFixtures;
use Tests\TestCase;

class ControlledSupplierBookingSchemaFixRetryRecoveryTest extends TestCase
{
    use ControlledPnrContextTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.cancel_enabled' => false,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.public_auto_pnr_enabled' => false,
        ]);
        Http::fake();
    }

    public function test_schema_fix_recovery_allowed_after_f9j_pre_http_schema_failure(): void
    {
        $booking = $this->bookingReadyForF9lRecovery();
        $readiness = $this->readinessFor($booking);
        $context = $this->controlledContextFor($booking, $readiness, $this->cleanDigestSummary());

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['supplierBookingAttempts']),
            $this->platformAdmin(),
            'controlled_pnr_command',
            false,
            true,
            $context,
        );

        $this->assertNull($result);
        $this->assertTrue($this->f9lGateAllows($booking, $this->cleanDigestSummary()));
    }

    public function test_schema_fix_recovery_refused_when_schema_validation_still_fails(): void
    {
        $booking = $this->bookingReadyForF9lRecovery();
        $digest = $this->cleanDigestSummary(['cpnr_schema_validation_status' => 'fail']);

        $this->assertFalse($this->f9lGateAllows($booking, $digest));
    }

    public function test_schema_fix_recovery_refused_when_pnr_exists(): void
    {
        $booking = $this->bookingReadyForF9lRecovery();
        $booking->forceFill(['pnr' => 'ABC123'])->save();

        $this->assertFalse($this->f9lGateAllows($booking->fresh(['supplierBookings', 'tickets']), $this->cleanDigestSummary()));
    }

    public function test_schema_fix_recovery_refused_when_booking_ticketed(): void
    {
        $booking = $this->bookingReadyForF9lRecovery();
        $booking->forceFill(['status' => BookingStatus::Ticketed])->save();

        $this->assertFalse($this->f9lGateAllows($booking->fresh(['supplierBookings', 'tickets']), $this->cleanDigestSummary()));
    }

    public function test_schema_fix_recovery_refused_when_booking_cancelled(): void
    {
        $booking = $this->bookingReadyForF9lRecovery();
        $booking->forceFill(['status' => BookingStatus::Cancelled])->save();

        $this->assertFalse($this->f9lGateAllows($booking->fresh(['supplierBookings', 'tickets']), $this->cleanDigestSummary()));
    }

    public function test_schema_fix_recovery_refused_after_host_application_results(): void
    {
        $booking = $this->bookingReadyForF9lRecovery();
        $gate = app(SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::class);
        $gate->markHostApplicationResultsReceived($booking->fresh());

        $this->assertFalse($this->f9lGateAllows($booking->fresh(), $this->cleanDigestSummary()));
    }

    public function test_schema_fix_recovery_refused_after_recovery_already_used(): void
    {
        $booking = $this->bookingReadyForF9lRecovery();
        app(SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate::class)->recordUsage($booking);

        $this->assertFalse($this->f9lGateAllows($booking->fresh(), $this->cleanDigestSummary()));
    }

    public function test_schema_fix_recovery_allowed_without_current_no_fares_when_f9j_meta_has_prior_host_message(): void
    {
        $booking = $this->bookingReadyForF9lRecovery();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta[SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::META_KEY] = array_merge(
            is_array($meta[SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::META_KEY] ?? null)
                ? $meta[SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::META_KEY]
                : [],
            [
                'previous_host_message' => SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::PREVIOUS_HOST_MESSAGE,
                'previous_error_code' => SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::PREVIOUS_ERROR_CODE,
            ],
        );
        $booking->forceFill(['meta' => $meta])->save();

        $diagnostics = app(SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate::class)
            ->buildF9jAccountingDiagnostics($booking->fresh(['supplierBookingAttempts']), null, $this->cleanDigestSummary());
        $this->assertTrue($diagnostics['f9j_previous_no_fares_rbd_carrier_present']);
        $this->assertTrue($this->f9lGateAllows($booking->fresh(['supplierBookingAttempts']), $this->cleanDigestSummary()));
    }

    public function test_schema_fix_recovery_allowed_without_no_fares_meta_when_validation_failed_attempt_exists(): void
    {
        $booking = $this->bookingReadyForF9lRecovery();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        unset($meta[SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::META_KEY]['previous_host_message']);
        unset($meta[SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::META_KEY]['previous_error_code']);
        $booking->forceFill(['meta' => $meta])->save();

        $this->assertTrue($this->f9lGateAllows($booking->fresh(['supplierBookingAttempts']), $this->cleanDigestSummary()));
    }

    public function test_schema_fix_recovery_refused_when_real_application_results_on_meaningful_attempt(): void
    {
        $booking = $this->bookingReadyForF9lRecovery();
        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->firstOrFail();
        $attempt->forceFill([
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => array_merge(is_array($attempt->safe_summary) ? $attempt->safe_summary : [], [
                'application_error_digest_available' => true,
            ]),
        ])->save();

        $this->assertFalse($this->f9lGateAllows($booking->fresh(['supplierBookingAttempts']), $this->cleanDigestSummary()));
    }

    public function test_dry_run_reports_schema_recovery_availability_without_supplier_http_or_mutation(): void
    {
        $booking = $this->bookingReadyForF9lRecovery();
        $attemptsBefore = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count();

        Artisan::call('sabre:controlled-create-pnr', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();
        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];

        $this->assertStringContainsString('f9j_used=true', $output);
        $this->assertStringContainsString('f9j_schema_validation_failed=true', $output);
        $this->assertStringContainsString('f9k_schema_recovery_available=', $output);
        $this->assertStringContainsString('f9k_schema_recovery_blockers=', $output);
        $this->assertStringContainsString('controlled_retry_after_airprice_vc_schema_fix_available=', $output);
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        $this->assertStringContainsString('pnr_create_attempted=false', $output);
        $this->assertStringContainsString('ticketing_attempted=false', $output);
        $this->assertStringContainsString('cancellation_attempted=false', $output);
        $this->assertFalse(app(SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate::class)->schemaFixRecoveryAlreadyUsed($meta));
        $this->assertSame($attemptsBefore, SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count());
        Http::assertNothingSent();

        $assess = app(SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate::class)
            ->assessSchemaRecoveryAvailability(
                $booking->fresh(['supplierBookingAttempts']),
                $this->cleanDigestSummary(),
                $this->controlledContextFor($booking, $this->readinessFor($booking), $this->cleanDigestSummary()),
                null,
                'controlled_pnr_command',
                true,
                false,
            );
        $this->assertTrue($assess['f9k_schema_recovery_available']);
        $this->assertTrue($assess['available']);
    }

    public function test_record_usage_writes_safe_f9l_meta_once(): void
    {
        $booking = $this->bookingReadyForF9lRecovery();

        app(SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate::class)->recordUsage($booking);
        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $record = $meta[SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate::META_KEY] ?? null;

        $this->assertIsArray($record);
        $this->assertTrue($record['used']);
        $this->assertSame('sabre_booking_validation_failed', $record['previous_f9j_failure']);
        $this->assertSame('pre_http_schema_validation', $record['previous_stage']);
        $this->assertArrayNotHasKey('response_payload', $record);
    }

    public function test_mutation_flags_remain_disabled_after_f9l_gate_evaluation(): void
    {
        $booking = $this->bookingReadyForF9lRecovery();
        $this->f9lGateAllows($booking, $this->cleanDigestSummary());

        $this->assertFalse((bool) config('suppliers.sabre.ticketing_enabled'));
        $this->assertFalse((bool) config('suppliers.sabre.cancel_enabled'));
        $this->assertFalse((bool) config('suppliers.sabre.public_auto_pnr_enabled', false));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function cleanDigestSummary(array $overrides = []): array
    {
        return array_merge([
            'payload_digest_available' => true,
            'hard_no_fares_rbd_carrier_risk' => false,
            'airprice_validating_carrier_present' => true,
            'validating_carrier_match' => true,
            'brand_match' => true,
            'airbook_rbd_complete' => true,
            'airbook_carrier_complete' => true,
            'airprice_present' => true,
            'cpnr_schema_validation_status' => 'pass',
            'cpnr_schema_validation_failed' => false,
            'warning_reasons' => 'legacy_revalidation_signal_used',
        ], $overrides);
    }

    protected function bookingReadyForF9lRecovery(): Booking
    {
        $booking = $this->booking53StyleWithFareChangeGate(array_merge(
            $this->approvalMetaForBooking(),
            [
                'defer_supplier_booking_to_manual_review' => true,
                'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
                'offer_refresh_status' => 'refreshed',
                'offer_refresh_reason' => 'inventory_refresh',
                'offer_refresh_accepted' => true,
            ],
        ));

        $meta = array_merge(
            is_array($booking->meta) ? $booking->meta : [],
            $this->fareChangeAcceptanceMetaForBooking($booking),
            [
                SabreControlledPnrRetryAllowanceGate::META_KEY => [
                    'used' => true,
                    'used_at' => now()->subHours(2)->toIso8601String(),
                    'used_by' => SabreControlledPnrRetryAllowanceGate::USED_BY_CONTROLLED_PNR_COMMAND,
                    'used_for' => SabreControlledPnrRetryAllowanceGate::USED_FOR_CONTROLLED_PNR_CREATE_AFTER_FARE_ACCEPTANCE,
                    'booking_reference' => (string) ($booking->reference_code ?? ''),
                    'previous_blocker' => SabreControlledPnrRetryAllowanceGate::PREVIOUS_BLOCKER_RETRY_NOT_ALLOWED,
                    'prior_meaningful_error_code' => SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE,
                    'required_acceptance_key' => SabreControlledPnrFareChangeAcceptance::META_KEY,
                ],
                SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::META_KEY => [
                    'used' => true,
                    'used_at' => now()->subHour()->toIso8601String(),
                    'used_by' => SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::USED_BY_CONTROLLED_PNR_COMMAND,
                    'used_for' => SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::USED_FOR_CONTROLLED_PNR_CREATE_AFTER_AIRPRICE_VC_FIX,
                    'booking_reference' => (string) ($booking->reference_code ?? ''),
                    'previous_error_code' => SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::PREVIOUS_ERROR_CODE,
                    'previous_host_message' => SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::PREVIOUS_HOST_MESSAGE,
                    'required_payload_digest' => SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::REQUIRED_PAYLOAD_DIGEST,
                    'schema_validation_failed' => false,
                    'host_application_results_received' => false,
                ],
            ],
        );
        $booking->forceFill(['meta' => $meta])->save();
        $this->seedF9jSchemaValidationFailureAttempt($booking->fresh());

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'tickets', 'supplierBookingAttempts']);
    }

    protected function seedF9jSchemaValidationFailureAttempt(Booking $booking): void
    {
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'failed',
            'error_code' => 'sabre_booking_validation_failed',
            'error_message' => 'Sabre booking validation failed: object instance has properties which are not allowed pointer: /CreatePassengerNameRecordRQ/AirPrice/0/PriceRequestInformation/OptionalQualifiers/PricingQualifiers',
            'safe_summary' => [
                'source' => 'sabre_booking_service',
                'reason' => 'validation_failed',
                'attempt_source' => 'controlled_pnr_command',
                'live_call_attempted' => false,
                'application_error_digest_available' => false,
            ],
            'attempted_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(10),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $digestSummary
     */
    protected function f9lGateAllows(Booking $booking, ?array $digestSummary): bool
    {
        $readiness = $this->readinessFor($booking);
        $context = $this->controlledContextFor($booking, $readiness, $digestSummary);
        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->firstOrFail();

        return app(SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate::class)->allows(
            $booking->fresh(['supplierBookings', 'tickets', 'supplierBookingAttempts']),
            $attempt,
            'controlled_pnr_command',
            true,
            $context,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function readinessFor(Booking $booking): array
    {
        return app(SabreControlledPnrReadiness::class)->evaluate($booking, [
            'context' => 'create_command',
            'require_admin_confirmation' => true,
            'admin_confirmation_provided' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $readiness
     * @param  array<string, mixed>|null  $digestSummary
     * @return array<string, mixed>
     */
    protected function controlledContextFor(Booking $booking, array $readiness, ?array $digestSummary = null): array
    {
        return [
            'controlled_pnr_create' => true,
            'controlled_manual_review_approved' => true,
            'controlled_approval_source' => 'artisan',
            'controlled_approval_confirm_phrase' => 'CREATE-PNR-FOR-BOOKING-'.$booking->id,
            'readiness_snapshot' => $readiness,
            'post_f9i_payload_digest_summary' => $digestSummary,
        ];
    }

    protected function platformAdmin(): User
    {
        return User::query()->where('account_type', AccountType::PlatformAdmin)->orderBy('id')->first()
            ?? User::query()->where('email', 'admin@ota.demo')->orderBy('id')->firstOrFail();
    }
}
