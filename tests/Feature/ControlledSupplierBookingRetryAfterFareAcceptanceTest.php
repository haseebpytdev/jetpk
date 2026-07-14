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
use App\Support\Bookings\SabreControlledPnrRetryAllowanceGate;
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use App\Support\Bookings\SupplierBookingPreflightGuard;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Support\Bookings\ControlledPnrContextTestFixtures;
use Tests\TestCase;

class ControlledSupplierBookingRetryAfterFareAcceptanceTest extends TestCase
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
        ]);
        Http::fake();
    }

    public function test_preflight_passes_after_f9c_f9e_with_prior_fare_acceptance_attempt(): void
    {
        $booking = $this->bookingReadyForControlledRetry();
        $readiness = $this->readinessFor($booking);
        $context = $this->controlledContextFor($booking, $readiness);

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

    public function test_preflight_blocks_retry_without_f9e_fare_acceptance(): void
    {
        $booking = $this->booking53StyleWithFareChangeGate(array_merge(
            $this->approvalMetaForBooking(),
            [
                'defer_supplier_booking_to_manual_review' => true,
                'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
            ],
        ));
        $this->seedPriorFareAcceptanceAttempt($booking);
        $readiness = $this->readinessFor($booking);
        $context = $this->controlledContextFor($booking, $readiness);

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['supplierBookingAttempts']),
            $this->platformAdmin(),
            'controlled_pnr_command',
            false,
            true,
            $context,
        );

        $this->assertNotNull($result);
        $this->assertSame('blocked', $result->status);
        $this->assertSame('supplier_booking_retry_not_allowed', $result->error_code);
    }

    public function test_preflight_blocks_retry_without_f9c_manual_review_approval(): void
    {
        $booking = $this->booking53StyleWithFareChangeGate([
            'defer_supplier_booking_to_manual_review' => true,
            'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
        ]);
        $booking->forceFill([
            'meta' => array_merge(
                is_array($booking->meta) ? $booking->meta : [],
                $this->fareChangeAcceptanceMetaForBooking($booking),
            ),
        ])->save();
        $this->seedPriorFareAcceptanceAttempt($booking->fresh());
        $readiness = $this->readinessFor($booking->fresh());
        $context = $this->controlledContextFor($booking->fresh(), $readiness);

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['supplierBookingAttempts']),
            $this->platformAdmin(),
            'controlled_pnr_command',
            false,
            true,
            $context,
        );

        $this->assertNotNull($result);
        $this->assertSame('supplier_booking_retry_not_allowed', $result->error_code);
    }

    public function test_retry_allowance_refused_when_existing_pnr_present(): void
    {
        $booking = $this->bookingReadyForControlledRetry();
        $booking->forceFill(['pnr' => 'ABC123'])->save();
        $readiness = $this->readinessFor($booking->fresh());
        $context = $this->controlledContextFor($booking->fresh(), $readiness);

        $gate = app(SabreControlledPnrRetryAllowanceGate::class);
        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertFalse($gate->allows(
            $booking->fresh(['supplierBookings', 'tickets']),
            $attempt,
            'controlled_pnr_command',
            true,
            $context,
        ));
    }

    public function test_preflight_blocks_retry_when_booking_ticketed(): void
    {
        $booking = $this->bookingReadyForControlledRetry();
        $booking->forceFill(['status' => BookingStatus::Ticketed])->save();
        $readiness = $this->readinessFor($booking->fresh());
        $context = $this->controlledContextFor($booking->fresh(), $readiness);

        $gate = app(SabreControlledPnrRetryAllowanceGate::class);
        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertFalse($gate->allows(
            $booking->fresh(['supplierBookings', 'tickets']),
            $attempt,
            'controlled_pnr_command',
            true,
            $context,
        ));
    }

    public function test_preflight_blocks_retry_when_booking_cancelled(): void
    {
        $booking = $this->bookingReadyForControlledRetry();
        $booking->forceFill(['status' => BookingStatus::Cancelled])->save();
        $readiness = $this->readinessFor($booking->fresh());
        $context = $this->controlledContextFor($booking->fresh(), $readiness);

        $gate = app(SabreControlledPnrRetryAllowanceGate::class);
        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertFalse($gate->allows(
            $booking->fresh(['supplierBookings', 'tickets']),
            $attempt,
            'controlled_pnr_command',
            true,
            $context,
        ));
    }

    public function test_retry_allowance_refused_when_readiness_has_blockers(): void
    {
        $booking = $this->bookingReadyForControlledRetry();
        $readiness = $this->readinessFor($booking);
        $readiness['blockers'] = ['manual_review_required'];
        $readiness['eligible'] = false;
        $context = $this->controlledContextFor($booking, $readiness);

        $gate = app(SabreControlledPnrRetryAllowanceGate::class);
        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertFalse($gate->allows(
            $booking->fresh(['supplierBookingAttempts']),
            $attempt,
            'controlled_pnr_command',
            true,
            $context,
        ));
    }

    public function test_retry_allowance_refused_for_non_controlled_context(): void
    {
        $booking = $this->bookingReadyForControlledRetry();
        $readiness = $this->readinessFor($booking);
        $context = $this->controlledContextFor($booking, $readiness);

        $gate = app(SabreControlledPnrRetryAllowanceGate::class);
        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertFalse($gate->allows(
            $booking->fresh(['supplierBookingAttempts']),
            $attempt,
            'admin',
            true,
            $context,
        ));
    }

    public function test_dry_run_does_not_use_retry_allowance_or_call_supplier(): void
    {
        $booking = $this->bookingReadyForControlledRetry();
        $attemptsBefore = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count();

        Artisan::call('sabre:controlled-create-pnr', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();
        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];

        $this->assertStringContainsString('controlled_supplier_retry_allowance_used=false', $output);
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        $this->assertStringContainsString('pnr_create_attempted=false', $output);
        $this->assertFalse(app(SabreControlledPnrRetryAllowanceGate::class)->retryAllowanceAlreadyUsed($meta));
        $this->assertSame($attemptsBefore, SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count());
        Http::assertNothingSent();
    }

    public function test_second_live_attempt_blocked_after_retry_allowance_meta_used(): void
    {
        $booking = $this->bookingReadyForControlledRetry();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta[SabreControlledPnrRetryAllowanceGate::META_KEY] = [
            'used' => true,
            'used_at' => now()->toIso8601String(),
            'used_by' => SabreControlledPnrRetryAllowanceGate::USED_BY_CONTROLLED_PNR_COMMAND,
            'used_for' => SabreControlledPnrRetryAllowanceGate::USED_FOR_CONTROLLED_PNR_CREATE_AFTER_FARE_ACCEPTANCE,
            'booking_reference' => (string) ($booking->reference_code ?? ''),
            'previous_blocker' => SabreControlledPnrRetryAllowanceGate::PREVIOUS_BLOCKER_RETRY_NOT_ALLOWED,
            'prior_meaningful_error_code' => SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE,
            'required_acceptance_key' => SabreControlledPnrFareChangeAcceptance::META_KEY,
        ];
        $booking->forceFill(['meta' => $meta])->save();
        $readiness = $this->readinessFor($booking);
        $context = $this->controlledContextFor($booking, $readiness);

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['supplierBookingAttempts']),
            $this->platformAdmin(),
            'controlled_pnr_command',
            false,
            true,
            $context,
        );

        $this->assertNotNull($result);
        $this->assertSame('supplier_booking_retry_not_allowed', $result->error_code);
    }

    public function test_mutation_flags_remain_disabled_after_retry_gate_evaluation(): void
    {
        $booking = $this->bookingReadyForControlledRetry();
        $this->readinessFor($booking);

        $this->assertFalse((bool) config('suppliers.sabre.ticketing_enabled'));
        $this->assertFalse((bool) config('suppliers.sabre.cancel_enabled'));
        $this->assertFalse((bool) config('suppliers.sabre.public_auto_pnr_enabled', false));
    }

    /**
     * @param  array<string, mixed>  $metaOverrides
     */
    protected function bookingReadyForControlledRetry(array $metaOverrides = []): Booking
    {
        $booking = $this->booking53StyleWithFareChangeGate(array_merge(
            $this->approvalMetaForBooking(),
            [
                'defer_supplier_booking_to_manual_review' => true,
                'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
            ],
            $metaOverrides,
        ));

        $booking->forceFill([
            'meta' => array_merge(
                is_array($booking->meta) ? $booking->meta : [],
                $this->fareChangeAcceptanceMetaForBooking($booking),
            ),
        ])->save();

        $this->seedPriorFareAcceptanceAttempt($booking->fresh());

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'tickets', 'supplierBookingAttempts']);
    }

    protected function seedPriorFareAcceptanceAttempt(Booking $booking): void
    {
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE,
            'error_message' => SabreOfferRefreshAcceptance::ADMIN_MESSAGE,
            'safe_summary' => [
                'source' => 'sabre_booking_service',
                'reason' => 'offer_validation_required',
                'reason_code' => SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE,
                'attempt_source' => 'controlled_pnr_command',
                'live_call_attempted' => false,
            ],
            'attempted_at' => now()->subHour(),
            'completed_at' => now()->subHour(),
        ]);
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
     * @return array<string, mixed>
     */
    protected function controlledContextFor(Booking $booking, array $readiness): array
    {
        return [
            'controlled_pnr_create' => true,
            'controlled_manual_review_approved' => true,
            'controlled_approval_source' => 'artisan',
            'controlled_approval_confirm_phrase' => 'CREATE-PNR-FOR-BOOKING-'.$booking->id,
            'readiness_snapshot' => $readiness,
        ];
    }

    protected function platformAdmin(): User
    {
        return User::query()->where('account_type', AccountType::PlatformAdmin)->orderBy('id')->first()
            ?? User::query()->where('email', 'admin@ota.demo')->orderBy('id')->firstOrFail();
    }
}
