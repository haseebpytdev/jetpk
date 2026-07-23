<?php

namespace App\Services\Booking;

use App\Data\SupplierBookingResultData;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Models\User;
use App\Services\Suppliers\AirBlue\AirBlueBookingRouterService;
use App\Services\Suppliers\Duffel\DuffelBookingService;
use App\Services\Suppliers\Iati\IatiBookingRouterService;
use App\Services\Suppliers\OneApi\OneApiBookingRouterService;
use App\Services\Suppliers\PiaNdc\PiaNdcBookingRouterService;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\SupplierBookingService;
use App\Support\Platform\PlatformModuleEnforcer;
use Illuminate\Support\Facades\Log;

/**
 * Public and staff flows must not silently book a different GDS than the fare source.
 *
 * - {@see SupplierProvider::Duffel}: {@see DuffelBookingService} (wraps existing Duffel path in {@see SupplierBookingService}).
 * - {@see SupplierProvider::Sabre}: {@see SabreBookingService} (skeleton; live HTTP only when
 *   {@see SabreBookingService::mayPerformLiveSabreBookingCall()} is true).
 * - {@see SupplierProvider::PiaNdc}, {@see SupplierProvider::AirlineDirect}: {@see SupplierBookingService}.
 * - Other / missing: safe rejection for checkout and supplier-booking actions.
 */
class BookingProviderRouter
{
    public function __construct(
        protected SupplierBookingService $supplierBookingService,
        protected DuffelBookingService $duffelBookingService,
        protected IatiBookingRouterService $iatiBookingRouterService,
        protected OneApiBookingRouterService $oneApiBookingRouterService,
        protected PiaNdcBookingRouterService $piaNdcBookingRouterService,
        protected AirBlueBookingRouterService $airBlueBookingRouterService,
        protected SabreBookingService $sabreBookingService,
        protected PlatformModuleEnforcer $platformModuleEnforcer,
    ) {}

    /**
     * User-facing checkout block message, or null when checkout may proceed for this provider string.
     */
    public function checkoutBlockedMessage(string $supplierProvider): ?string
    {
        $p = strtolower(trim($supplierProvider));
        if ($p === SupplierProvider::Sabre->value) {
            // Checkout UI (passengers → review) is allowed while booking is disabled; final submit is gated separately.
            return null;
        }

        if ($p === '' || ! $this->isRoutableToExistingSupplierBookingService($p)) {
            return (string) __('This fare cannot be booked online with the current supplier configuration.');
        }

        return null;
    }

    /**
     * Routes automated supplier booking (e.g. hold / admin "Create supplier booking") by booking meta.
     */
    public function createSupplierBooking(
        Booking $booking,
        User $actor,
        bool $adminOverride = false,
        bool $allowControlledStaffPnr = false,
        bool $explicitRetry = false,
    ): SupplierBookingResultData {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $p = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        $attemptSource = $this->resolveAttemptSource($actor, $adminOverride, $allowControlledStaffPnr);
        $distributionChannel = $this->platformModuleEnforcer->distributionChannelFromBookingMeta($meta);

        $moduleBlock = $this->platformModuleEnforcer->supplierBookingBlockedMessage(
            $p !== '' ? $p : null,
            $allowControlledStaffPnr,
            $distributionChannel,
        );
        if ($moduleBlock !== null) {
            Log::notice('supplier_booking.module_blocked', [
                'action' => 'create_supplier_booking',
                'provider' => $p !== '' ? $p : 'unknown',
                'module_key' => $this->platformModuleEnforcer->resolveProviderModuleKey($p !== '' ? $p : null, $distributionChannel),
                'booking_id' => $booking->id,
                'source' => $attemptSource,
            ]);

            return $this->platformModuleBlockedSupplierBookingResult($booking, $actor, $p, $moduleBlock);
        }

        if ($p === SupplierProvider::Sabre->value) {
            return $this->sabreBookingService->createSupplierBooking(
                $booking,
                $actor,
                $adminOverride,
                $allowControlledStaffPnr,
                $explicitRetry,
                $attemptSource,
            );
        }

        if ($p === SupplierProvider::Duffel->value) {
            return $this->duffelBookingService->createSupplierBooking(
                $booking,
                $actor,
                $adminOverride,
                $explicitRetry,
                $attemptSource,
            );
        }

        if ($p === SupplierProvider::Iati->value) {
            return $this->iatiBookingRouterService->createSupplierBooking(
                $booking,
                $actor,
                $adminOverride,
                $explicitRetry,
                $attemptSource,
            );
        }

        if ($p === SupplierProvider::PiaNdc->value) {
            return $this->piaNdcBookingRouterService->createSupplierBooking(
                $booking,
                $actor,
                $adminOverride,
                $explicitRetry,
                $attemptSource,
            );
        }

        if ($p === SupplierProvider::OneApi->value) {
            return $this->oneApiBookingRouterService->createSupplierBooking(
                $booking,
                $actor,
                $adminOverride,
                $explicitRetry,
                $attemptSource,
            );
        }

        if ($p === SupplierProvider::Airblue->value) {
            return $this->airBlueBookingRouterService->createSupplierBooking(
                $booking,
                $actor,
                $adminOverride,
                $explicitRetry,
                $attemptSource,
            );
        }

        if ($p === '' || ! $this->isRoutableToExistingSupplierBookingService($p)) {
            Log::notice('provider_routing_blocked', [
                'reason' => 'unknown_or_unsupported_provider',
                'provider' => $p !== '' ? $p : 'empty',
                'booking_id' => $booking->id,
                'action' => 'create_supplier_booking',
            ]);

            $providerLabel = $p !== '' ? $p : 'unknown';
            $msg = (string) __('This fare cannot be booked online with the current supplier configuration.');
            $this->recordRoutingBlockedSupplierBookingAttempt(
                $booking,
                $actor,
                $providerLabel,
                'unknown_supplier_provider',
                $msg,
            );

            return new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: $providerLabel,
                error_code: 'unknown_supplier_provider',
                error_message: $msg,
            );
        }

        return $this->supplierBookingService->createSupplierBooking(
            $booking,
            $actor,
            $adminOverride,
            $explicitRetry,
            $attemptSource,
        );
    }

    /**
     * @return 'public_checkout'|'admin'|'staff'|'system'
     */
    protected function resolveAttemptSource(User $actor, bool $adminOverride, bool $allowControlledStaffPnr): string
    {
        if ($allowControlledStaffPnr) {
            return $actor->isStaff() ? 'staff' : 'admin';
        }

        if ($adminOverride) {
            return 'public_checkout';
        }

        return 'system';
    }

    /**
     * Persist a failed attempt when routing blocks supplier PNR creation (audit parity with adapter failures).
     */
    protected function platformModuleBlockedSupplierBookingResult(
        Booking $booking,
        User $actor,
        string $provider,
        string $message,
    ): SupplierBookingResultData {
        $this->recordRoutingBlockedSupplierBookingAttempt(
            $booking,
            $actor,
            $provider !== '' ? $provider : 'unknown',
            'platform_module_disabled',
            $message,
        );

        return new SupplierBookingResultData(
            success: false,
            status: 'failed',
            provider: $provider !== '' ? $provider : 'unknown',
            error_code: 'platform_module_disabled',
            error_message: $message,
        );
    }

    protected function recordRoutingBlockedSupplierBookingAttempt(
        Booking $booking,
        User $actor,
        string $provider,
        string $errorCode,
        string $errorMessage,
    ): void {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $cid = $meta['supplier_connection_id'] ?? null;
        $cid = is_numeric($cid) ? (int) $cid : null;

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $cid,
            'provider' => $provider,
            'action' => 'create_pnr',
            'status' => 'blocked',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'attempted_by' => $actor->id,
            'attempted_at' => now(),
            'completed_at' => now(),
            'safe_summary' => [
                'source' => 'provider_routing_blocked',
                'provider' => $provider,
            ],
        ]);
    }

    protected function isRoutableToExistingSupplierBookingService(string $normalizedProvider): bool
    {
        $enum = SupplierProvider::tryFrom($normalizedProvider);

        return $enum !== null
            && in_array($enum, [
                SupplierProvider::Duffel,
                SupplierProvider::Iati,
                SupplierProvider::PiaNdc,
                SupplierProvider::Airblue,
                SupplierProvider::AirlineDirect,
            ], true);
    }

    public function isBookingEligible(Booking $booking): bool
    {
        return $this->supplierBookingService->isBookingEligible($booking);
    }

    public function markManualPnr(
        Booking $booking,
        User $actor,
        string $pnr,
        ?string $supplierReference = null,
        ?string $note = null,
    ): \App\Models\SupplierBooking {
        return $this->supplierBookingService->markManualPnr($booking, $actor, $pnr, $supplierReference, $note);
    }
}
