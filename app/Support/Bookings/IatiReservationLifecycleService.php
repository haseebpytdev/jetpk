<?php

namespace App\Support\Bookings;

use App\Data\NormalizedFlightOfferData;
use App\Data\OfferValidationResultData;
use App\Data\SupplierBookingResultData;
use App\Enums\IatiReservationLifecycleStatus;
use App\Enums\IatiSupplierReservationSource;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingHoldSession;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Iati\IatiFareRevalidationService;
use Illuminate\Support\Carbon;

/**
 * IATI payment/reservation lifecycle: local payment intent vs supplier hold/book separation.
 * Persists lifecycle fields in booking.meta['iati_reservation'] (and mirrors key expiry fields).
 */
class IatiReservationLifecycleService
{
    public const META_KEY = 'iati_reservation';

    public function __construct(
        private readonly IatiFareRevalidationService $iatiFareRevalidationService,
        private readonly SupplierBookingAttemptGuard $attemptGuard,
    ) {}

    public static function appliesTo(Booking $booking): bool
    {
        return IatiSupplierBookingEligibility::appliesTo($booking);
    }

    /**
     * @param  array<string, mixed>  $protection
     */
    public function initializeFromCheckout(Booking $booking, array $protection, ?BookingHoldSession $holdSession = null): void
    {
        if (! self::appliesTo($booking)) {
            return;
        }

        $requiresInstant = (bool) ($protection['requires_instant_payment'] ?? true);
        $holdSupported = (bool) ($protection['hold_supported'] ?? false);
        $localMinutes = max(1, (int) config('ota.iati_local_checkout_minutes', config('ota.checkout_lock_minutes', 15)));
        $localExpiry = $holdSession?->local_checkout_expires_at !== null
            ? Carbon::parse($holdSession->local_checkout_expires_at)
            : ($this->parseIso((string) ($protection['checkout_lock_expires_at'] ?? ''))
                ?? now()->addMinutes($localMinutes));

        $block = $this->reservationBlock($booking);
        $block['local_checkout_expires_at'] = $localExpiry->toIso8601String();
        $block['fare_quote_expires_at'] = $protection['offer_expires_at'] ?? null;
        $block['revalidation_required_at'] = $requiresInstant ? $localExpiry->toIso8601String() : null;
        $block['supplier_hold_expires_at'] = $protection['payment_required_by']
            ?? $protection['price_guarantee_expires_at']
            ?? null;
        $block['payment_required_by'] = $protection['payment_required_by'] ?? null;
        $block['requires_instant_payment'] = $requiresInstant;
        $block['hold_supported'] = $holdSupported;
        $block['protection_mode'] = (string) ($protection['protection_mode'] ?? 'instant_payment_required');
        $block['supplier_reservation_source'] = IatiSupplierReservationSource::LocalOnly->value;
        $block['supplier_reservation_status'] = $requiresInstant
            ? IatiReservationLifecycleStatus::LocalPaymentPendingNotReserved->value
            : IatiReservationLifecycleStatus::SupplierHoldPendingPayment->value;
        $block['initialized_at'] = now()->toIso8601String();

        if ($holdSession !== null) {
            $holdSession->forceFill([
                'local_checkout_expires_at' => $localExpiry,
                'payment_required_by' => $this->parseIso((string) ($block['payment_required_by'] ?? '')),
                'hold_expires_at' => $this->parseIso((string) ($block['supplier_hold_expires_at'] ?? '')),
            ])->save();
        }

        $this->persistBlock($booking, $block);
    }

    public function applySupplierHoldOutcome(
        Booking $booking,
        ?SupplierBookingResultData $result,
        ?BookingHoldSession $holdSession = null,
    ): void {
        if (! self::appliesTo($booking)) {
            return;
        }

        $block = $this->reservationBlock($booking);
        $success = (bool) ($result?->success ?? false);
        $orderId = trim((string) ($result?->supplier_reference ?? ''));

        if ($success && $orderId !== '') {
            $block['supplier_reservation_source'] = IatiSupplierReservationSource::SupplierHold->value;
            $block['supplier_reservation_status'] = IatiReservationLifecycleStatus::SupplierHoldPendingPayment->value;
            $block['supplier_order_id'] = $orderId;
            $block['supplier_hold_confirmed_at'] = now()->toIso8601String();
        } elseif (($result?->status ?? '') === 'direct_book_required') {
            $block['supplier_reservation_source'] = IatiSupplierReservationSource::LocalOnly->value;
            $block['supplier_reservation_status'] = IatiReservationLifecycleStatus::PaymentVerifiedPendingSupplierBooking->value;
            $block['deferred_book_after_payment'] = true;
        } else {
            $block['supplier_reservation_source'] = IatiSupplierReservationSource::LocalOnly->value;
            $block['supplier_reservation_status'] = IatiReservationLifecycleStatus::LocalPaymentPendingNotReserved->value;
            $block['supplier_hold_failed'] = true;
            $block['supplier_hold_error'] = (string) ($result?->error_message ?? 'Supplier hold not confirmed.');
        }

        if ($holdSession !== null && $success && $orderId !== '') {
            $holdSession->forceFill([
                'supplier_order_id' => $orderId,
                'supplier_order_reference' => trim((string) ($result?->pnr ?? '')) ?: null,
                'hold_status' => 'held',
            ])->save();
        }

        $this->persistBlock($booking, $block);
    }

    public function markPaymentVerified(Booking $booking): void
    {
        if (! self::appliesTo($booking)) {
            return;
        }

        $block = $this->reservationBlock($booking);
        $source = $this->resolveReservationSource($booking, $block);

        if ($source === IatiSupplierReservationSource::SupplierBooked) {
            $block['supplier_reservation_status'] = IatiReservationLifecycleStatus::SupplierBookingConfirmed->value;

            $this->persistBlock($booking, $block);

            return;
        }

        if ($this->hasUnresolvedFareChange($block)) {
            $block['supplier_reservation_status'] = IatiReservationLifecycleStatus::FareChangedCustomerActionRequired->value;
        } elseif ($block['supplier_reservation_status'] === IatiReservationLifecycleStatus::FareUnavailableAdminReview->value) {
            // keep
        } elseif ($source === IatiSupplierReservationSource::SupplierHold) {
            $block['supplier_reservation_status'] = IatiReservationLifecycleStatus::PaymentVerifiedPendingSupplierBooking->value;
        } else {
            $block['supplier_reservation_status'] = IatiReservationLifecycleStatus::PaymentVerifiedPendingSupplierBooking->value;
        }

        $block['payment_verified_at'] = now()->toIso8601String();
        $this->persistBlock($booking, $block);
    }

    public function markSupplierBookingInProgress(Booking $booking): void
    {
        if (! self::appliesTo($booking)) {
            return;
        }

        $block = $this->reservationBlock($booking);
        $block['supplier_reservation_status'] = IatiReservationLifecycleStatus::SupplierBookingInProgress->value;
        $block['supplier_booking_started_at'] = now()->toIso8601String();
        $this->persistBlock($booking, $block);
    }

    public function markSupplierBookingConfirmed(Booking $booking, ?string $orderId = null, ?string $pnr = null): void
    {
        if (! self::appliesTo($booking)) {
            return;
        }

        $block = $this->reservationBlock($booking);
        $block['supplier_reservation_source'] = IatiSupplierReservationSource::SupplierBooked->value;
        $block['supplier_reservation_status'] = IatiReservationLifecycleStatus::SupplierBookingConfirmed->value;
        $block['supplier_order_id'] = $orderId ?? trim((string) ($booking->supplier_reference ?? ''));
        $block['supplier_pnr'] = $pnr ?? trim((string) ($booking->pnr ?? ''));
        $block['supplier_booking_confirmed_at'] = now()->toIso8601String();
        $block['fare_change_requires_acceptance'] = false;
        $this->persistBlock($booking, $block);
    }

    public function markSupplierBookingFailed(Booking $booking, ?string $safeError = null): void
    {
        if (! self::appliesTo($booking)) {
            return;
        }

        $block = $this->reservationBlock($booking);
        $paid = (string) ($booking->payment_status ?? 'unpaid') === 'paid';
        $block['supplier_reservation_status'] = $paid
            ? IatiReservationLifecycleStatus::PaymentReceivedSupplierBookingFailed->value
            : IatiReservationLifecycleStatus::LocalPaymentPendingNotReserved->value;
        $block['last_supplier_booking_error'] = $safeError;
        $block['supplier_booking_failed_at'] = now()->toIso8601String();
        $this->persistBlock($booking, $block);
    }

    public function markFareUnavailable(Booking $booking, ?string $reason = null): void
    {
        if (! self::appliesTo($booking)) {
            return;
        }

        $block = $this->reservationBlock($booking);
        $block['supplier_reservation_status'] = IatiReservationLifecycleStatus::FareUnavailableAdminReview->value;
        $block['last_revalidation_status'] = 'unavailable';
        $block['fare_unavailable_reason'] = $reason;
        $block['fare_unavailable_at'] = now()->toIso8601String();
        $this->persistBlock($booking, $block);
    }

    /**
     * @return array{
     *     allowed: bool,
     *     error_code: string|null,
     *     error_message: string|null,
     *     lifecycle_status: string
     * }
     */
    public function assertSupplierBookAllowed(Booking $booking, bool $adminOverride = false): array
    {
        if (! self::appliesTo($booking)) {
            return [
                'allowed' => true,
                'error_code' => null,
                'error_message' => null,
                'lifecycle_status' => '',
            ];
        }

        $status = $this->resolveLifecycleStatus($booking);
        $block = $this->reservationBlock($booking);
        $requiresInstant = (bool) ($block['requires_instant_payment'] ?? $this->requiresInstantPayment($booking));
        $paid = (string) ($booking->payment_status ?? 'unpaid') === 'paid' || $adminOverride;

        if ($status === IatiReservationLifecycleStatus::ExpiredLocalPaymentRequest) {
            return $this->deny('local_checkout_expired', 'Local checkout expired. Revalidate fare before supplier booking.', $status);
        }

        if ($status === IatiReservationLifecycleStatus::ExpiredSupplierHold) {
            return $this->deny('supplier_hold_expired', 'Supplier hold expired before payment.', $status);
        }

        if ($requiresInstant && ! $paid && ! $adminOverride) {
            return $this->deny('payment_not_verified', 'Payment must be verified before supplier booking for instant-payment IATI offers.', $status);
        }

        if ($status === IatiReservationLifecycleStatus::FareUnavailableAdminReview) {
            return $this->deny('fare_unavailable', 'Fare is no longer available. Admin review required.', $status);
        }

        if ($this->hasUnresolvedFareChange($block)) {
            return $this->deny('fare_change_pending', 'Fare change must be accepted before supplier booking.', $status);
        }

        if ($this->hasActiveSupplierBookingAttempt($booking)) {
            $diagnostics = $this->attemptGuard->assertRetryAllowed($booking, SupplierProvider::Iati->value, 'create_pnr');

            return array_merge(
                $this->deny('supplier_booking_in_progress', 'Supplier booking already in progress.', $status),
                ['safe_summary' => $this->attemptGuard->blockedSafeSummary($diagnostics)],
            );
        }

        return [
            'allowed' => true,
            'error_code' => null,
            'error_message' => null,
            'lifecycle_status' => $status->value,
        ];
    }

    /**
     * @return array{
     *     ok: bool,
     *     status: string,
     *     price_changed: bool,
     *     old_total: float|null,
     *     new_total: float|null,
     *     currency: string|null,
     *     comparison: array<string, mixed>,
     *     validation: OfferValidationResultData|null,
     *     error_code: string|null,
     *     error_message: string|null
     * }
     */
    public function runPreBookRevalidation(Booking $booking, SupplierConnection $connection): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = IatiSupplierBookingEligibility::resolveOfferSnapshot($meta);
        if ($snapshot === []) {
            return $this->revalidationFailure('missing_snapshot', 'Persisted offer snapshot is missing.');
        }

        $storedTotal = (float) ($booking->selected_fare_total ?? $booking->revalidated_fare_total ?? $meta['supplier_total'] ?? 0);
        $storedCurrency = (string) ($meta['supplier_currency'] ?? data_get($snapshot, 'fare_breakdown.currency') ?? 'PKR');
        $fareOptionKey = IatiSupplierBookingEligibility::selectedFareOptionKeyFromMeta($meta);

        try {
            $offer = NormalizedFlightOfferData::fromArray($snapshot);
            $validation = $this->iatiFareRevalidationService->revalidate($offer, $connection, $fareOptionKey !== '' ? $fareOptionKey : null);
        } catch (\Throwable) {
            $this->markFareUnavailable($booking, 'Revalidation request failed.');

            return $this->revalidationFailure('revalidation_failed', 'IATI fare revalidation failed.');
        }

        $block = $this->reservationBlock($booking);
        $block['last_revalidation_at'] = now()->toIso8601String();
        $block['last_revalidation_status'] = (string) ($validation->status ?? 'unknown');

        if (! $validation->is_valid || $validation->validated_offer === null) {
            $this->markFareUnavailable($booking, 'Fare no longer available from supplier.');
            $block['last_revalidation_status'] = 'unavailable';
            $this->persistBlock($booking, $block);

            return $this->revalidationFailure('fare_unavailable', 'Fare no longer available. Select a new fare or refund.', $validation);
        }

        $validated = $validation->validated_offer->toArray();
        $newTotal = (float) ($validation->new_total ?? $validated['fare_breakdown']['supplier_total'] ?? $validated['total'] ?? 0);
        $currency = (string) ($validation->currency ?? $validated['fare_breakdown']['currency'] ?? $storedCurrency);
        $block['last_revalidated_total'] = $newTotal;
        $block['last_revalidated_currency'] = $currency;

        $comparison = $this->compareRevalidationContext($booking, $validated, $storedTotal, $storedCurrency);
        if (($comparison['route_match'] ?? true) === false || ($comparison['passenger_count_match'] ?? true) === false) {
            $this->markFareUnavailable($booking, 'Revalidated offer context mismatch.');
            $block['last_revalidation_status'] = 'context_mismatch';
            $this->persistBlock($booking, $block);

            return $this->revalidationFailure('context_mismatch', 'Revalidated fare does not match booking context.', $validation, $comparison);
        }

        $priceChanged = (bool) $validation->price_changed || ($storedTotal > 0 && abs($newTotal - $storedTotal) > 0.01);
        if ($priceChanged) {
            $difference = $newTotal - $storedTotal;
            $block['fare_change_requires_acceptance'] = true;
            $block['fare_change_old_total'] = $storedTotal;
            $block['fare_change_new_total'] = $newTotal;
            $block['fare_change_difference'] = $difference;
            $block['supplier_reservation_status'] = IatiReservationLifecycleStatus::FareChangedCustomerActionRequired->value;
            $block['last_revalidation_status'] = 'changed';
            $meta['requires_price_change_confirmation'] = true;
            $meta['price_change_old_total'] = $storedTotal;
            $meta['price_change_new_total'] = $newTotal;
            $booking->forceFill(['meta' => $this->mergeMetaReservation($booking, $meta, $block)])->save();

            return [
                'ok' => false,
                'status' => 'changed',
                'price_changed' => true,
                'old_total' => $storedTotal,
                'new_total' => $newTotal,
                'currency' => $currency,
                'comparison' => $comparison,
                'validation' => $validation,
                'error_code' => 'fare_changed',
                'error_message' => sprintf(
                    'Fare changed from %s %.0f to %s %.0f. Accept new fare to continue.',
                    $storedCurrency,
                    $storedTotal,
                    $currency,
                    $newTotal,
                ),
            ];
        }

        $block['fare_change_requires_acceptance'] = false;
        $block['last_revalidation_status'] = 'same';
        $block['revalidated_at'] = now()->toIso8601String();
        $meta['fare_rechecked_at'] = now()->toIso8601String();
        $meta['requires_price_change_confirmation'] = false;
        unset($meta['price_change_old_total'], $meta['price_change_new_total']);
        $booking->forceFill([
            'meta' => $this->mergeMetaReservation($booking, $meta, $block),
            'revalidated_fare_total' => $newTotal,
            'fare_revalidated_at' => now(),
        ])->save();

        return [
            'ok' => true,
            'status' => 'same',
            'price_changed' => false,
            'old_total' => $storedTotal,
            'new_total' => $newTotal,
            'currency' => $currency,
            'comparison' => $comparison,
            'validation' => $validation,
            'error_code' => null,
            'error_message' => null,
        ];
    }

    public function acceptFareChange(Booking $booking, User $acceptor, bool $applyNewTotalToBooking = true): void
    {
        if (! self::appliesTo($booking)) {
            return;
        }

        $block = $this->reservationBlock($booking);
        if (! $this->hasUnresolvedFareChange($block)) {
            return;
        }

        $newTotal = (float) ($block['fare_change_new_total'] ?? 0);
        $block['fare_change_requires_acceptance'] = false;
        $block['fare_change_accepted_at'] = now()->toIso8601String();
        $block['fare_change_accepted_by_user_id'] = $acceptor->id;
        $block['supplier_reservation_status'] = (string) ($booking->payment_status ?? '') === 'paid'
            ? IatiReservationLifecycleStatus::PaymentVerifiedPendingSupplierBooking->value
            : IatiReservationLifecycleStatus::LocalPaymentPendingNotReserved->value;

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['requires_price_change_confirmation'] = false;
        unset($meta['price_change_old_total'], $meta['price_change_new_total']);

        $updates = ['meta' => $this->mergeMetaReservation($booking, $meta, $block)];
        if ($applyNewTotalToBooking && $newTotal > 0) {
            $updates['selected_fare_total'] = $newTotal;
            $updates['revalidated_fare_total'] = $newTotal;
        }

        $booking->forceFill($updates)->save();
    }

    public function resolveLifecycleStatus(Booking $booking): IatiReservationLifecycleStatus
    {
        if (! self::appliesTo($booking)) {
            return IatiReservationLifecycleStatus::LocalPaymentPendingNotReserved;
        }

        $block = $this->reservationBlock($booking);
        $stored = trim((string) ($block['supplier_reservation_status'] ?? ''));
        if ($stored !== '') {
            $enum = IatiReservationLifecycleStatus::tryFrom($stored);
            if ($enum !== null && ! $this->shouldRecomputeStatus($booking, $block, $enum)) {
                return $enum;
            }
        }

        return $this->computeLifecycleStatus($booking, $block);
    }

    public function resolveReservationSource(Booking $booking, ?array $block = null): IatiSupplierReservationSource
    {
        $block ??= $this->reservationBlock($booking);

        if (trim((string) ($booking->pnr ?? '')) !== ''
            || trim((string) ($booking->supplier_reference ?? '')) !== ''
            || trim((string) ($block['supplier_pnr'] ?? '')) !== '') {
            return IatiSupplierReservationSource::SupplierBooked;
        }

        $orderId = trim((string) ($block['supplier_order_id'] ?? $booking->supplier_reference ?? ''));
        if ($orderId !== '') {
            return IatiSupplierReservationSource::SupplierHold;
        }

        $stored = IatiSupplierReservationSource::tryFrom((string) ($block['supplier_reservation_source'] ?? ''));

        return $stored ?? IatiSupplierReservationSource::LocalOnly;
    }

    /**
     * @return array<string, mixed>
     */
    public function presentation(Booking $booking): array
    {
        $status = $this->resolveLifecycleStatus($booking);
        $source = $this->resolveReservationSource($booking);
        $block = $this->reservationBlock($booking);
        $hasSupplierReservation = $source !== IatiSupplierReservationSource::LocalOnly
            && $source !== IatiSupplierReservationSource::None;
        $booking->loadMissing('holdSession');
        $holdSession = $booking->holdSession;
        $supplierOrderId = trim((string) (
            $block['supplier_order_id']
            ?? $booking->supplier_reference
            ?? $holdSession?->supplier_order_id
            ?? ''
        ));
        $supplierOrderReference = trim((string) (
            $block['supplier_order_reference']
            ?? $holdSession?->supplier_order_reference
            ?? ''
        ));
        $showSupplierHoldExpiry = $source === IatiSupplierReservationSource::SupplierHold
            && ($supplierOrderId !== '' || $supplierOrderReference !== '');

        return [
            'lifecycle_status' => $status->value,
            'lifecycle_label' => $status->label(),
            'customer_headline' => $status->customerHeadline(),
            'customer_detail' => $status->customerDetail(),
            'reservation_source' => $source->value,
            'reservation_source_label' => $source->adminLabel(),
            'is_reserved_with_supplier' => $hasSupplierReservation && $status === IatiReservationLifecycleStatus::SupplierBookingConfirmed,
            'show_not_reserved_yet' => $status === IatiReservationLifecycleStatus::LocalPaymentPendingNotReserved,
            'show_supplier_hold_active' => $status === IatiReservationLifecycleStatus::SupplierHoldPendingPayment,
            'local_checkout_expires_at' => $block['local_checkout_expires_at'] ?? null,
            'supplier_hold_expires_at' => $showSupplierHoldExpiry
                ? ($block['supplier_hold_expires_at'] ?? $block['payment_required_by'] ?? null)
                : null,
            'fare_change_requires_acceptance' => (bool) ($block['fare_change_requires_acceptance'] ?? false),
            'fare_change_old_total' => isset($block['fare_change_old_total']) ? (float) $block['fare_change_old_total'] : null,
            'fare_change_new_total' => isset($block['fare_change_new_total']) ? (float) $block['fare_change_new_total'] : null,
            'fare_change_difference' => isset($block['fare_change_difference']) ? (float) $block['fare_change_difference'] : null,
            'fare_change_currency' => (string) ($block['last_revalidated_currency'] ?? $booking->meta['supplier_currency'] ?? 'PKR'),
            'may_show_pnr_pending' => $this->hasSupplierMutationSent($booking),
        ];
    }

    /**
     * @return list<string>
     */
    public function eligibilityBlockers(Booking $booking, bool $adminOverride = false): array
    {
        if (! self::appliesTo($booking)) {
            return [];
        }

        $gate = $this->assertSupplierBookAllowed($booking, $adminOverride);
        if ($gate['allowed']) {
            return [];
        }

        return array_filter([(string) ($gate['error_code'] ?? '')]);
    }

    /**
     * @param  array<string, mixed>  $block
     */
    protected function computeLifecycleStatus(Booking $booking, array $block): IatiReservationLifecycleStatus
    {
        if (trim((string) ($booking->pnr ?? '')) !== '' || trim((string) ($booking->supplier_reference ?? '')) !== '') {
            return IatiReservationLifecycleStatus::SupplierBookingConfirmed;
        }

        if ($this->hasActiveSupplierBookingAttempt($booking)) {
            return IatiReservationLifecycleStatus::SupplierBookingInProgress;
        }

        if (($block['last_revalidation_status'] ?? '') === 'unavailable'
            || ($block['supplier_reservation_status'] ?? '') === IatiReservationLifecycleStatus::FareUnavailableAdminReview->value) {
            return IatiReservationLifecycleStatus::FareUnavailableAdminReview;
        }

        if ($this->hasUnresolvedFareChange($block)) {
            return IatiReservationLifecycleStatus::FareChangedCustomerActionRequired;
        }

        if (($block['supplier_booking_failed_at'] ?? null) !== null && (string) ($booking->payment_status ?? '') === 'paid') {
            return IatiReservationLifecycleStatus::PaymentReceivedSupplierBookingFailed;
        }

        if ($this->isExpiredSupplierHold($booking, $block)) {
            return IatiReservationLifecycleStatus::ExpiredSupplierHold;
        }

        if ($this->isExpiredLocalCheckout($booking, $block) && (string) ($booking->payment_status ?? 'unpaid') !== 'paid') {
            return IatiReservationLifecycleStatus::ExpiredLocalPaymentRequest;
        }

        $source = $this->resolveReservationSource($booking, $block);
        $paid = (string) ($booking->payment_status ?? 'unpaid') === 'paid';

        if ($paid && $source !== IatiSupplierReservationSource::SupplierBooked) {
            return IatiReservationLifecycleStatus::PaymentVerifiedPendingSupplierBooking;
        }

        if ($source === IatiSupplierReservationSource::SupplierHold && ! $paid) {
            return IatiReservationLifecycleStatus::SupplierHoldPendingPayment;
        }

        return IatiReservationLifecycleStatus::LocalPaymentPendingNotReserved;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    protected function shouldRecomputeStatus(Booking $booking, array $block, IatiReservationLifecycleStatus $stored): bool
    {
        if ($stored === IatiReservationLifecycleStatus::SupplierBookingConfirmed) {
            return trim((string) ($booking->pnr ?? '')) === '' && trim((string) ($booking->supplier_reference ?? '')) === '';
        }

        if ($this->isExpiredLocalCheckout($booking, $block) && (string) ($booking->payment_status ?? 'unpaid') !== 'paid') {
            return true;
        }

        if ($this->isExpiredSupplierHold($booking, $block)) {
            return true;
        }

        if ($stored === IatiReservationLifecycleStatus::SupplierBookingInProgress
            && $this->attemptGuard->resolveActiveAttempt($booking, SupplierProvider::Iati->value, 'create_pnr') === null) {
            return true;
        }

        return false;
    }

    protected function requiresInstantPayment(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $block = $this->reservationBlock($booking);

        return (bool) ($block['requires_instant_payment'] ?? $meta['requires_instant_payment'] ?? true);
    }

    protected function requiresPreBookRevalidation(Booking $booking): bool
    {
        return true;
    }

    protected function hasFreshRevalidation(Booking $booking): bool
    {
        $block = $this->reservationBlock($booking);
        $revalidatedAt = $this->parseIso((string) ($block['revalidated_at'] ?? $block['last_revalidation_at'] ?? ''));
        if ($revalidatedAt === null) {
            return false;
        }

        if (($block['last_revalidation_status'] ?? '') === 'same' && ! $this->hasUnresolvedFareChange($block)) {
            return $revalidatedAt->greaterThan(now()->subMinutes(30));
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    protected function hasUnresolvedFareChange(array $block): bool
    {
        return (bool) ($block['fare_change_requires_acceptance'] ?? false)
            && ($block['fare_change_accepted_at'] ?? null) === null;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    protected function isExpiredLocalCheckout(Booking $booking, array $block): bool
    {
        if ((string) ($booking->payment_status ?? 'unpaid') === 'paid') {
            return false;
        }

        $expiry = $this->parseIso((string) (
            $block['local_checkout_expires_at']
            ?? $booking->meta['checkout_lock_expires_at']
            ?? ''
        ));

        return $expiry !== null && now()->greaterThan($expiry);
    }

    /**
     * @param  array<string, mixed>  $block
     */
    protected function isExpiredSupplierHold(Booking $booking, array $block): bool
    {
        if ((string) ($booking->payment_status ?? 'unpaid') === 'paid') {
            return false;
        }

        $source = $this->resolveReservationSource($booking, $block);
        if ($source !== IatiSupplierReservationSource::SupplierHold) {
            return false;
        }

        $expiry = $this->parseIso((string) (
            $block['supplier_hold_expires_at']
            ?? $block['payment_required_by']
            ?? $booking->payment_required_by?->toIso8601String()
            ?? ''
        ));

        return $expiry !== null && now()->greaterThan($expiry);
    }

    protected function hasActiveSupplierBookingAttempt(Booking $booking): bool
    {
        return $this->attemptGuard->resolveActiveAttempt(
            $booking,
            SupplierProvider::Iati->value,
            'create_pnr',
        ) !== null;
    }

    protected function hasSupplierMutationSent(Booking $booking): bool
    {
        if (trim((string) ($booking->supplier_reference ?? '')) !== ''
            || trim((string) ($booking->supplier_api_booking_id ?? '')) !== ''
            || trim((string) ($booking->pnr ?? '')) !== '') {
            return true;
        }

        return SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Iati->value)
            ->where('action', 'create_pnr')
            ->whereIn('status', ['processing', 'success', 'created', 'failed'])
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function compareRevalidationContext(
        Booking $booking,
        array $validated,
        float $storedTotal,
        string $storedCurrency,
    ): array {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        $storedOrigin = strtoupper(trim((string) ($criteria['origin'] ?? $booking->route ?? '')));
        $storedDestination = strtoupper(trim((string) ($criteria['destination'] ?? '')));
        if ($storedDestination === '' && str_contains((string) $booking->route, '→')) {
            $parts = array_map('trim', explode('→', (string) $booking->route));
            $storedOrigin = strtoupper($parts[0] ?? $storedOrigin);
            $storedDestination = strtoupper($parts[1] ?? '');
        }

        $newOrigin = strtoupper(trim((string) ($validated['origin'] ?? data_get($validated, 'segments.0.origin') ?? '')));
        $newDestination = strtoupper(trim((string) ($validated['destination'] ?? data_get($validated, 'segments.0.destination') ?? '')));
        $storedAirline = strtoupper(trim((string) ($meta['validated_offer_snapshot']['airline_code'] ?? $booking->airline ?? '')));
        $newAirline = strtoupper(trim((string) ($validated['airline_code'] ?? $validated['carrier_code'] ?? '')));

        $storedPax = (int) ($booking->adults ?? 0) + (int) ($booking->children ?? 0) + (int) ($booking->infants ?? 0);
        $counts = is_array($meta['passenger_counts'] ?? null) ? $meta['passenger_counts'] : [];
        $storedPax = max($storedPax, (int) ($counts['total'] ?? 0));
        $newPax = (int) data_get($validated, 'fare_breakdown.passenger_counts.total', 0);
        if ($newPax <= 0) {
            $newPax = $storedPax;
        }

        $providerContext = IatiPersistedContextResolver::resolveProviderContext($meta, $booking);
        $fareKeysValid = trim((string) ($providerContext['departure_fare_key'] ?? '')) !== ''
            && trim((string) ($providerContext['fare_detail_key'] ?? '')) !== '';

        return [
            'stored_total' => $storedTotal,
            'stored_currency' => $storedCurrency,
            'route_match' => ($storedOrigin === '' || $newOrigin === '' || $storedOrigin === $newOrigin)
                && ($storedDestination === '' || $newDestination === '' || $storedDestination === $newDestination),
            'airline_match' => $storedAirline === '' || $newAirline === '' || $storedAirline === $newAirline,
            'passenger_count_match' => $storedPax <= 0 || $newPax <= 0 || $storedPax === $newPax,
            'fare_keys_valid' => $fareKeysValid,
            'currency_match' => strtoupper($storedCurrency) === strtoupper((string) ($validated['fare_breakdown']['currency'] ?? $validated['currency'] ?? $storedCurrency)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function reservationBlock(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];

        return is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : [];
    }

    /**
     * @param  array<string, mixed>  $block
     */
    protected function persistBlock(Booking $booking, array $block): void
    {
        $status = $this->computeLifecycleStatus($booking, $block);
        $block['supplier_reservation_status'] = $status->value;
        $block['supplier_reservation_source'] = $this->resolveReservationSource($booking, $block)->value;

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta[self::META_KEY] = $block;

        if (! empty($block['local_checkout_expires_at'])) {
            $meta['checkout_lock_expires_at'] = (string) $block['local_checkout_expires_at'];
        }
        if (! empty($block['fare_change_requires_acceptance'])) {
            $meta['requires_price_change_confirmation'] = true;
            $meta['price_change_old_total'] = $block['fare_change_old_total'] ?? null;
            $meta['price_change_new_total'] = $block['fare_change_new_total'] ?? null;
        }

        $booking->forceFill(['meta' => $meta])->save();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    protected function mergeMetaReservation(Booking $booking, array $meta, array $block): array
    {
        $block['supplier_reservation_status'] = $this->computeLifecycleStatus($booking, $block)->value;
        $block['supplier_reservation_source'] = $this->resolveReservationSource($booking, $block)->value;
        $meta[self::META_KEY] = $block;

        return $meta;
    }

    protected function parseIso(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{allowed: bool, error_code: string|null, error_message: string|null, lifecycle_status: string}
     */
    protected function deny(string $code, string $message, IatiReservationLifecycleStatus $status): array
    {
        return [
            'allowed' => false,
            'error_code' => $code,
            'error_message' => $message,
            'lifecycle_status' => $status->value,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $comparison
     * @return array<string, mixed>
     */
    protected function revalidationFailure(
        string $code,
        string $message,
        ?OfferValidationResultData $validation = null,
        ?array $comparison = null,
    ): array {
        return [
            'ok' => false,
            'status' => 'failed',
            'price_changed' => false,
            'old_total' => null,
            'new_total' => null,
            'currency' => null,
            'comparison' => $comparison ?? [],
            'validation' => $validation,
            'error_code' => $code,
            'error_message' => $message,
        ];
    }
}
