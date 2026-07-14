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
use App\Support\Bookings\SabreControlledPnrRetryAllowanceGate;
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use App\Support\Bookings\SupplierBookingPreflightGuard;
use App\Support\Sabre\SabrePassengerRecordsPayloadDigest;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Support\Bookings\ControlledPnrContextTestFixtures;
use Tests\TestCase;

class ControlledSupplierBookingRetryAfterAirpriceVcFixTest extends TestCase
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

    public function test_f9j_allowed_after_f9f_used_prior_no_fares_and_clean_digest(): void
    {
        $booking = $this->bookingReadyForF9jRetry();
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
    }

    public function test_f9j_refused_when_hard_risk_remains(): void
    {
        $booking = $this->bookingReadyForF9jRetry();
        $digest = $this->cleanDigestSummary();
        $digest['hard_no_fares_rbd_carrier_risk'] = true;

        $this->assertFalse($this->gateAllows($booking, $digest));
    }

    public function test_f9j_refused_when_airprice_validating_carrier_missing(): void
    {
        $booking = $this->bookingReadyForF9jRetry();
        $digest = $this->cleanDigestSummary();
        $digest['airprice_validating_carrier_present'] = false;

        $this->assertFalse($this->gateAllows($booking, $digest));
    }

    public function test_f9j_refused_when_brand_match_false(): void
    {
        $booking = $this->bookingReadyForF9jRetry();
        $digest = $this->cleanDigestSummary();
        $digest['brand_match'] = false;

        $this->assertFalse($this->gateAllows($booking, $digest));
    }

    public function test_f9j_refused_without_prior_no_fares_rbd_carrier_error(): void
    {
        $booking = $this->bookingReadyForF9jRetry();
        SupplierBookingAttempt::query()->where('booking_id', $booking->id)->delete();
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'failed',
            'error_code' => 'sabre_offer_validation_failed',
            'error_message' => 'Offer validation failed.',
            'safe_summary' => [
                'source' => 'sabre_booking_service',
                'reason' => 'validation_failed',
                'attempt_source' => 'controlled_pnr_command',
            ],
            'attempted_at' => now()->subMinutes(30),
            'completed_at' => now()->subMinutes(30),
        ]);

        $this->assertFalse($this->gateAllows($booking->fresh(['supplierBookingAttempts']), $this->cleanDigestSummary()));
    }

    public function test_f9j_refused_when_already_used(): void
    {
        $booking = $this->bookingReadyForF9jRetry();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta[SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::META_KEY] = [
            'used' => true,
            'used_at' => now()->toIso8601String(),
            'used_by' => SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::USED_BY_CONTROLLED_PNR_COMMAND,
            'used_for' => SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::USED_FOR_CONTROLLED_PNR_CREATE_AFTER_AIRPRICE_VC_FIX,
            'booking_reference' => (string) ($booking->reference_code ?? ''),
            'previous_error_code' => SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::PREVIOUS_ERROR_CODE,
            'previous_host_message' => SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::PREVIOUS_HOST_MESSAGE,
            'required_payload_digest' => SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::REQUIRED_PAYLOAD_DIGEST,
            'host_application_results_received' => true,
        ];
        $booking->forceFill(['meta' => $meta])->save();

        $this->assertFalse($this->gateAllows($booking->fresh(), $this->cleanDigestSummary()));
    }

    public function test_f9j_refused_for_existing_pnr(): void
    {
        $booking = $this->bookingReadyForF9jRetry();
        $booking->forceFill(['pnr' => 'ABC123'])->save();

        $this->assertFalse($this->gateAllows($booking->fresh(['supplierBookings', 'tickets']), $this->cleanDigestSummary()));
    }

    public function test_f9j_refused_when_booking_ticketed(): void
    {
        $booking = $this->bookingReadyForF9jRetry();
        $booking->forceFill(['status' => BookingStatus::Ticketed])->save();

        $this->assertFalse($this->gateAllows($booking->fresh(['supplierBookings', 'tickets']), $this->cleanDigestSummary()));
    }

    public function test_f9j_refused_when_booking_cancelled(): void
    {
        $booking = $this->bookingReadyForF9jRetry();
        $booking->forceFill(['status' => BookingStatus::Cancelled])->save();

        $this->assertFalse($this->gateAllows($booking->fresh(['supplierBookings', 'tickets']), $this->cleanDigestSummary()));
    }

    public function test_dry_run_reports_availability_without_supplier_http_or_mutation(): void
    {
        $booking = $this->bookingReadyForF9jRetry();
        $attemptsBefore = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count();

        Artisan::call('sabre:controlled-create-pnr', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();
        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];

        $this->assertStringContainsString('controlled_supplier_retry_after_airprice_vc_fix_used=false', $output);
        $this->assertStringContainsString('controlled_retry_after_airprice_vc_fix_available=', $output);
        $this->assertStringContainsString('controlled_retry_after_airprice_vc_fix_blockers=', $output);
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        $this->assertStringContainsString('pnr_create_attempted=false', $output);
        $this->assertStringContainsString('ticketing_attempted=false', $output);
        $this->assertStringContainsString('cancellation_attempted=false', $output);
        $this->assertFalse(app(SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::class)->retryAllowanceAlreadyUsed($meta));
        $this->assertSame($attemptsBefore, SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count());
        Http::assertNothingSent();
    }

    public function test_mutation_flags_remain_disabled_after_f9j_gate_evaluation(): void
    {
        $booking = $this->bookingReadyForF9jRetry();
        $this->gateAllows($booking, $this->cleanDigestSummary());

        $this->assertFalse((bool) config('suppliers.sabre.ticketing_enabled'));
        $this->assertFalse((bool) config('suppliers.sabre.cancel_enabled'));
        $this->assertFalse((bool) config('suppliers.sabre.public_auto_pnr_enabled', false));
    }

    public function test_record_usage_writes_safe_f9j_meta_once(): void
    {
        $booking = $this->bookingReadyForF9jRetry();
        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->firstOrFail();

        app(SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::class)->recordUsage($booking, $attempt);
        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $record = $meta[SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::META_KEY] ?? null;

        $this->assertIsArray($record);
        $this->assertTrue($record['used']);
        $this->assertSame(
            SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::PREVIOUS_ERROR_CODE,
            $record['previous_error_code'],
        );
        $this->assertArrayNotHasKey('response_payload', $record);
    }

    public function test_post_f9i_clean_helper_accepts_legacy_warning_only(): void
    {
        $digest = app(SabrePassengerRecordsPayloadDigest::class);

        $this->assertTrue($digest->isPostF9iCleanForControlledRetry($this->cleanDigestSummary([
            'warning_reasons' => 'legacy_revalidation_signal_used',
        ])));
        $this->assertFalse($digest->isPostF9iCleanForControlledRetry($this->cleanDigestSummary([
            'warning_reasons' => 'missing_revalidation_linkage',
        ])));
        $this->assertFalse($digest->isPostF9iCleanForControlledRetry($this->cleanDigestSummary([
            'cpnr_schema_validation_status' => 'fail',
        ])));
    }

    public function test_f9j_retry_available_after_schema_validation_failure_recovery(): void
    {
        $booking = $this->bookingReadyForF9jRetry();
        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->latest('id')
            ->firstOrFail();

        app(SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::class)->recordSchemaValidationOutcome(
            $booking,
            true,
            [
                'cpnr_schema_validation_pointer' => '/CreatePassengerNameRecordRQ/AirPrice/0/PriceRequestInformation/OptionalQualifiers/PricingQualifiers',
                'cpnr_schema_validation_message_summary' => 'object instance has properties which are not allowed',
            ],
        );
        $booking->refresh();

        $gate = app(SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::class);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertFalse($gate->retryAllowanceFullyConsumed($meta));
        $this->assertTrue($gate->retryAllowanceAvailableForRecovery($meta, $attempt));

        $readiness = $this->readinessFor($booking);
        $assess = $gate->assessAvailability(
            $booking->fresh(['supplierBookingAttempts', 'supplierBookings', 'tickets']),
            $this->cleanDigestSummary(),
            $this->controlledContextFor($booking, $readiness, $this->cleanDigestSummary()),
            $attempt,
        );
        $this->assertTrue($assess['available']);
        $this->assertNotContains('f9j_retry_allowance_already_used', $assess['blockers']);
    }

    public function test_f9j_not_available_after_host_application_results_failure(): void
    {
        $booking = $this->bookingReadyForF9jRetry();
        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->latest('id')
            ->firstOrFail();

        $gate = app(SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::class);
        $gate->recordUsage($booking, $attempt);
        $gate->markHostApplicationResultsReceived($booking->fresh());
        $booking->refresh();

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertTrue($gate->retryAllowanceFullyConsumed($meta));
        $this->assertFalse($gate->retryAllowanceAvailableForRecovery($meta, $attempt));
    }

    public function test_schema_validation_failure_meta_does_not_block_second_attempt_when_digest_clean(): void
    {
        $booking = $this->bookingReadyForF9jRetry();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta[SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::META_KEY] = [
            'used' => true,
            'schema_validation_failed' => true,
            'host_application_results_received' => false,
        ];
        $booking->forceFill(['meta' => $meta])->save();

        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->latest('id')
            ->firstOrFail();

        $readiness = $this->readinessFor($booking);
        $assess = app(SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::class)->assessAvailability(
            $booking->fresh(['supplierBookingAttempts', 'supplierBookings', 'tickets']),
            $this->cleanDigestSummary(),
            $this->controlledContextFor($booking, $readiness, $this->cleanDigestSummary()),
            $attempt,
        );

        $this->assertTrue($assess['available']);
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
            'warning_reasons' => 'legacy_revalidation_signal_used',
        ], $overrides);
    }

    protected function bookingReadyForF9jRetry(): Booking
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
                    'used_at' => now()->subHour()->toIso8601String(),
                    'used_by' => SabreControlledPnrRetryAllowanceGate::USED_BY_CONTROLLED_PNR_COMMAND,
                    'used_for' => SabreControlledPnrRetryAllowanceGate::USED_FOR_CONTROLLED_PNR_CREATE_AFTER_FARE_ACCEPTANCE,
                    'booking_reference' => (string) ($booking->reference_code ?? ''),
                    'previous_blocker' => SabreControlledPnrRetryAllowanceGate::PREVIOUS_BLOCKER_RETRY_NOT_ALLOWED,
                    'prior_meaningful_error_code' => SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE,
                    'required_acceptance_key' => SabreControlledPnrFareChangeAcceptance::META_KEY,
                ],
            ],
        );
        $booking->forceFill(['meta' => $meta])->save();
        $this->seedPriorNoFaresAttempt($booking->fresh());

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'tickets', 'supplierBookingAttempts']);
    }

    protected function seedPriorNoFaresAttempt(Booking $booking): void
    {
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'error_message' => 'Sabre application error during passenger records create.',
            'safe_summary' => [
                'source' => 'sabre_booking_service',
                'reason' => 'application_error',
                'attempt_source' => 'controlled_pnr_command',
                'live_call_attempted' => true,
                'sabre_application_first_error_code' => 'ERR.SP.PROVIDER_ERROR',
                'sabre_application_first_error_message' => 'Unable to perform air booking step',
                'response_error_messages' => [
                    'EnhancedAirBookRQ: *NO FARES/RBD/CARRIER',
                ],
            ],
            'attempted_at' => now()->subMinutes(20),
            'completed_at' => now()->subMinutes(20),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $digestSummary
     */
    protected function gateAllows(Booking $booking, ?array $digestSummary): bool
    {
        $readiness = $this->readinessFor($booking);
        $context = $this->controlledContextFor($booking, $readiness, $digestSummary);
        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->firstOrFail();

        return app(SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::class)->allows(
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
