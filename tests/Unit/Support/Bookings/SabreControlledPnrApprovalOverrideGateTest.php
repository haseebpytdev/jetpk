<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreControlledPnrApprovalOverrideGate;
use App\Support\Bookings\SabreControlledPnrManualReviewApproval;
use App\Support\Bookings\SabreControlledPnrReadiness;
use App\Support\Bookings\SupplierBookingPreflightGuard;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Bookings\ControlledPnrContextTestFixtures;
use Tests\TestCase;

class SabreControlledPnrApprovalOverrideGateTest extends TestCase
{
    use ControlledPnrContextTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_allows_defer_override_when_controlled_context_and_approval_valid(): void
    {
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
        ]);

        $booking = $this->booking53Style(array_merge([
            'defer_supplier_booking_to_manual_review' => true,
            'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
        ], $this->approvalMeta()));

        $readiness = app(SabreControlledPnrReadiness::class)->evaluate($booking, [
            'context' => 'create_command',
            'require_admin_confirmation' => true,
            'admin_confirmation_provided' => true,
        ]);

        $context = $this->controlledContextFor($booking, $readiness);
        $gate = app(SabreControlledPnrApprovalOverrideGate::class);

        $this->assertTrue($gate->allowsDeferOverride(
            $booking,
            'controlled_pnr_command',
            true,
            $context,
        ));
    }

    public function test_denies_defer_override_without_approval(): void
    {
        $booking = $this->booking53Style([
            'defer_supplier_booking_to_manual_review' => true,
            'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
        ]);

        $readiness = app(SabreControlledPnrReadiness::class)->evaluate($booking, [
            'context' => 'create_command',
            'require_admin_confirmation' => true,
            'admin_confirmation_provided' => true,
        ]);

        $context = $this->controlledContextFor($booking, $readiness);
        $gate = app(SabreControlledPnrApprovalOverrideGate::class);

        $this->assertFalse($gate->allowsDeferOverride(
            $booking,
            'controlled_pnr_command',
            true,
            $context,
        ));
    }

    public function test_denies_defer_override_when_existing_pnr_present(): void
    {
        $booking = $this->booking53Style(array_merge([
            'defer_supplier_booking_to_manual_review' => true,
            'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
            'pnr' => 'ABC123',
        ], $this->approvalMeta()));

        $readiness = app(SabreControlledPnrReadiness::class)->evaluate($booking, [
            'context' => 'create_command',
            'require_admin_confirmation' => true,
            'admin_confirmation_provided' => true,
        ]);

        $context = $this->controlledContextFor($booking, $readiness);
        $gate = app(SabreControlledPnrApprovalOverrideGate::class);

        $this->assertFalse($gate->allowsDeferOverride(
            $booking,
            'controlled_pnr_command',
            true,
            $context,
        ));
    }

    public function test_denies_defer_override_when_booking_cancelled(): void
    {
        $booking = $this->booking53Style(array_merge([
            'defer_supplier_booking_to_manual_review' => true,
            'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
        ], $this->approvalMeta()));
        $booking->forceFill(['status' => BookingStatus::Cancelled])->save();

        $readiness = app(SabreControlledPnrReadiness::class)->evaluate($booking->fresh(), [
            'context' => 'create_command',
            'require_admin_confirmation' => true,
            'admin_confirmation_provided' => true,
        ]);

        $context = $this->controlledContextFor($booking->fresh(), $readiness);
        $gate = app(SabreControlledPnrApprovalOverrideGate::class);

        $this->assertFalse($gate->allowsDeferOverride(
            $booking->fresh(),
            'controlled_pnr_command',
            true,
            $context,
        ));
    }

    public function test_preflight_skips_defer_without_controlled_context(): void
    {
        $booking = $this->booking53Style(array_merge([
            'defer_supplier_booking_to_manual_review' => true,
            'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
        ], $this->approvalMeta()));

        $guard = app(SupplierBookingPreflightGuard::class);
        $actor = $this->platformAdmin();

        $result = $guard->preflightAutomatedCreate(
            $booking,
            $actor,
            'controlled_pnr_command',
            false,
            true,
            null,
        );

        $this->assertNotNull($result);
        $this->assertSame('skipped', $result->status);
        $this->assertSame('defer_supplier_booking_to_manual_review', $result->error_code);
    }

    public function test_preflight_passes_defer_with_valid_controlled_context(): void
    {
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
        ]);

        $booking = $this->booking53Style(array_merge([
            'defer_supplier_booking_to_manual_review' => true,
            'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
        ], $this->approvalMeta()));

        $readiness = app(SabreControlledPnrReadiness::class)->evaluate($booking, [
            'context' => 'create_command',
            'require_admin_confirmation' => true,
            'admin_confirmation_provided' => true,
        ]);

        $guard = app(SupplierBookingPreflightGuard::class);
        $actor = $this->platformAdmin();

        $result = $guard->preflightAutomatedCreate(
            $booking,
            $actor,
            'controlled_pnr_command',
            false,
            true,
            $this->controlledContextFor($booking, $readiness),
        );

        $this->assertNull($result);
    }

    /**
     * @return array<string, mixed>
     */
    protected function approvalMeta(): array
    {
        return [
            SabreControlledPnrManualReviewApproval::META_KEY => app(SabreControlledPnrManualReviewApproval::class)
                ->buildApprovalRecord(
                    Booking::factory()->make(['reference_code' => 'PAR-F9D']),
                    'controlled_burn_in',
                    'platform_ops',
                ),
        ];
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
