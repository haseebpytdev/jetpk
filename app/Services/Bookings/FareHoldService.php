<?php

namespace App\Services\Bookings;

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingHoldSession;
use App\Models\User;
use App\Services\Suppliers\OfferValidationService;
use App\Support\Bookings\BookingHoldSessionSupplierOfferIdResolver;
use Illuminate\Support\Carbon;

class FareHoldService
{
    public function __construct(
        protected OfferValidationService $offerValidationService,
    ) {}

    /**
     * @param  array<string, mixed>  $criteria
     * @return array{hold_session: BookingHoldSession, validation: mixed, presented_offer: array<string, mixed>}
     */
    public function prepareCheckoutHold(
        string $searchId,
        string $offerId,
        Agency $agency,
        ?User $user,
        array $offer,
        array $criteria,
        callable $presentOffer,
    ): array {
        $validation = $this->validateOfferForCheckout($agency, $offer, $criteria);
        $normalized = $validation->validated_offer?->toArray() ?? [];
        $pricing = is_array($validation->meta['pricing_snapshot'] ?? null) ? $validation->meta['pricing_snapshot'] : [];
        $presented = $presentOffer($normalized, $pricing);
        $holdData = $this->refreshHoldSession(
            agency: $agency,
            booking: null,
            searchId: $searchId,
            offerId: $offerId,
            normalizedOffer: $presented,
            user: $user,
            holdStatus: $this->canSupplierHoldOffer($presented) ? 'pending' : 'not_supported',
            safeError: null,
        );

        return [
            'hold_session' => $holdData,
            'validation' => $validation,
            'presented_offer' => $presented,
        ];
    }

    /**
     * @param  array<string, mixed>  $selectedOfferSnapshot
     * @param  array<string, mixed>  $searchContext
     */
    public function validateOfferForCheckout(Agency $agency, array $selectedOfferSnapshot, array $searchContext): mixed
    {
        return $this->offerValidationService->validateSelectedOffer($agency, $selectedOfferSnapshot, $searchContext);
    }

    /**
     * @param  array<string, mixed>  $normalizedOffer
     */
    public function canSupplierHoldOffer(array $normalizedOffer): bool
    {
        $rawPayload = is_array($normalizedOffer['raw_payload'] ?? null) ? $normalizedOffer['raw_payload'] : [];
        $paymentRequirements = is_array(data_get($rawPayload, 'payment_requirements'))
            ? data_get($rawPayload, 'payment_requirements')
            : [];

        return ! (bool) ($paymentRequirements['requires_instant_payment'] ?? true);
    }

    public function createHoldIfSupported(Booking $booking, ?User $actor, callable $holdCreator): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $mockHoldState = (string) ($meta['mock_hold_state'] ?? '');
        if ($mockHoldState !== '') {
            if ($mockHoldState === 'not_supported') {
                return ['status' => 'not_supported', 'result' => null];
            }
            if ($mockHoldState === 'hold_pending_passenger_details') {
                return ['status' => 'hold_pending_passenger_details', 'result' => null];
            }
        }

        if (! $this->canSupplierHoldOffer((array) ($booking->meta['flight_offer_snapshot'] ?? []))) {
            return ['status' => 'not_supported', 'result' => null];
        }
        if (! $actor instanceof User) {
            return ['status' => 'hold_pending_passenger_details', 'result' => null];
        }

        $result = $holdCreator($booking, $actor);

        return ['status' => 'attempted', 'result' => $result];
    }

    /**
     * @param  array<string, mixed>  $normalizedOffer
     */
    public function refreshHoldSession(
        Agency $agency,
        ?Booking $booking,
        string $searchId,
        string $offerId,
        array $normalizedOffer,
        ?User $user,
        string $holdStatus,
        ?string $safeError,
        ?BookingHoldSession $existing = null,
        array $metaOverrides = [],
    ): BookingHoldSession {
        $rawPayload = is_array($normalizedOffer['raw_payload'] ?? null) ? $normalizedOffer['raw_payload'] : [];
        $paymentRequirements = is_array(data_get($rawPayload, 'payment_requirements'))
            ? data_get($rawPayload, 'payment_requirements')
            : [];
        $priceGuarantee = is_array(data_get($rawPayload, 'conditions.price_guarantee'))
            ? data_get($rawPayload, 'conditions.price_guarantee')
            : [];
        $fare = is_array($normalizedOffer['fare_breakdown'] ?? null) ? $normalizedOffer['fare_breakdown'] : [];
        $passengerCounts = is_array($fare['passenger_counts'] ?? null) ? $fare['passenger_counts'] : [];
        $passengerPricing = is_array($fare['passenger_pricing'] ?? null) ? $fare['passenger_pricing'] : null;
        $checkoutExpiry = isset($normalizedOffer['expires_at']) ? Carbon::parse((string) $normalizedOffer['expires_at']) : now()->addMinutes(15);

        $session = $existing ?? new BookingHoldSession;
        $session->fill([
            'agency_id' => $agency->id,
            'booking_id' => $booking?->id,
            'search_id' => $searchId !== '' ? $searchId : null,
            'offer_id' => $offerId,
            'supplier_provider' => (string) ($normalizedOffer['supplier_provider'] ?? ''),
            'supplier_connection_id' => $normalizedOffer['supplier_connection_id'] ?? null,
            'supplier_offer_id' => BookingHoldSessionSupplierOfferIdResolver::resolve($normalizedOffer, $offerId),
            'hold_status' => $holdStatus,
            'requires_instant_payment' => (bool) ($paymentRequirements['requires_instant_payment'] ?? true),
            'price_guarantee_expires_at' => $priceGuarantee['expires_at'] ?? null,
            'payment_required_by' => $paymentRequirements['payment_required_by'] ?? null,
            'local_checkout_expires_at' => $checkoutExpiry,
            'hold_expires_at' => $normalizedOffer['expires_at'] ?? null,
            'validated_total_amount' => (float) ($normalizedOffer['total'] ?? 0),
            'validated_total_currency' => (string) ($normalizedOffer['currency'] ?? 'PKR'),
            'converted_total_pkr' => (float) ($normalizedOffer['total'] ?? 0),
            'markup_snapshot' => is_array($normalizedOffer['pricing_components'] ?? null) ? $normalizedOffer['pricing_components'] : [],
            'passenger_counts' => $passengerCounts,
            'passenger_pricing' => $passengerPricing,
            'passenger_pricing_available' => (bool) ($fare['passenger_pricing_available'] ?? false),
            'validated_offer_snapshot' => $normalizedOffer,
            'safe_error' => $safeError,
            'last_error_safe' => $safeError,
            'meta' => array_merge([
                'price_guarantee' => $priceGuarantee,
                'reason_code' => (bool) ($paymentRequirements['requires_instant_payment'] ?? true)
                    ? 'hold_not_supported_instant_payment_required'
                    : 'hold_supported',
            ], $metaOverrides),
            'expires_at' => $checkoutExpiry,
            'created_by_user_id' => $user?->id,
        ]);
        $session->save();

        if ($booking !== null) {
            $booking->forceFill([
                'hold_session_id' => $session->id,
                'supplier_hold_status' => $holdStatus,
                'price_guarantee_expires_at' => $session->price_guarantee_expires_at,
                'payment_required_by' => $session->payment_required_by,
            ])->save();
        }

        return $session->fresh();
    }

    public function isHoldExpired(BookingHoldSession $session): bool
    {
        $expiry = $session->price_guarantee_expires_at
            ?? $session->payment_required_by
            ?? $session->local_checkout_expires_at
            ?? $session->hold_expires_at;
        if ($expiry === null) {
            return false;
        }

        return now()->greaterThan(Carbon::parse($expiry));
    }

    public function requiresFinalRevalidation(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $mode = (string) ($meta['protection_mode'] ?? 'instant_payment_required');
        if (in_array($mode, ['instant_payment_required', 'hold_no_price_guarantee'], true)) {
            return true;
        }
        if (($booking->supplier_hold_status ?? '') === 'expired') {
            return true;
        }

        return false;
    }

    public function revalidateBeforeConfirmation(Booking $booking, Agency $agency): mixed
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $offerSnapshot = is_array($meta['validated_offer_snapshot'] ?? null) ? $meta['validated_offer_snapshot'] : [];
        $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];

        return $this->validateOfferForCheckout($agency, $offerSnapshot, $criteria + [
            'source_channel' => 'public_guest',
        ]);
    }

    public function markHoldCompleted(Booking $booking, BookingHoldSession $session, ?User $actor = null): void
    {
        $session->forceFill([
            'hold_status' => 'completed',
            'safe_error' => null,
            'last_error_safe' => null,
        ])->save();
        $this->audit($booking, $actor, 'booking.hold.completed', [
            'hold_session_id' => $session->id,
        ]);
    }

    public function markHoldFailed(Booking $booking, BookingHoldSession $session, string $safeError, ?User $actor = null): void
    {
        $session->forceFill([
            'hold_status' => 'failed',
            'safe_error' => $safeError,
            'last_error_safe' => $safeError,
        ])->save();
        $booking->forceFill([
            'supplier_hold_status' => 'failed',
        ])->save();

        $this->audit($booking, $actor, 'booking.hold.failed', [
            'hold_session_id' => $session->id,
            'error' => $safeError,
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    protected function audit(Booking $booking, ?User $actor, string $action, array $properties): void
    {
        AuditLog::query()->create([
            'agency_id' => $booking->agency_id,
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => Booking::class,
            'auditable_id' => $booking->id,
            'properties' => $properties,
            'ip_address' => null,
            'user_agent' => 'system',
        ]);
    }
}
