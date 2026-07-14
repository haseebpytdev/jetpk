<?php

namespace App\Support\Bookings;

use App\Models\Booking;
use Illuminate\Support\Facades\Log;

/**
 * Server-persisted public checkout fare-change state (no client-trusted flags).
 *
 * Fare-change UI appears only when {@see self::persistedFareChanged()} is true.
 * Accepted-fare mismatch blocking requires active fare-change context plus a
 * matching {@see self::META_ACCEPTED_FARE_CONTEXT_HASH}.
 */
final class PublicCheckoutFareChangeState
{
    public const META_FARE_CHANGE = 'fare_change';

    public const META_CHECKOUT_PRICE_CHANGE = 'checkout_price_change';

    public const META_ACCEPTED_FARE_CONTEXT_HASH = 'accepted_fare_context_hash';

    public const META_ACCEPTED_FARE_TOTAL = 'accepted_fare_total';

    private const TOTAL_MATCH_THRESHOLD = 0.009;

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function fareChangePresent(array $meta): bool
    {
        return is_array($meta[self::META_FARE_CHANGE] ?? null);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function checkoutPriceChangePresent(array $meta): bool
    {
        return is_array($meta[self::META_CHECKOUT_PRICE_CHANGE] ?? null);
    }

    /**
     * True only when persisted fare_change or checkout_price_change records fare_changed=true.
     */
    public function persistedFareChanged(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];

        if ($this->strictPersistedFareChanged($meta)) {
            return true;
        }

        if (SabreOfferRefreshAcceptance::requiresAcceptanceFromMeta($meta)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function strictPersistedFareChanged(array $meta): bool
    {
        if (($meta[self::META_FARE_CHANGE]['fare_changed'] ?? false) === true) {
            return true;
        }

        return ($meta[self::META_CHECKOUT_PRICE_CHANGE]['fare_changed'] ?? false) === true;
    }

    /**
     * Blocking modal or submit gate — server acceptance required before confirm.
     */
    public function requiresCustomerAcceptance(Booking $booking): bool
    {
        if (SabreOfferRefreshAcceptance::requiresAcceptance($booking)) {
            return true;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        if (! $this->persistedFareChanged($booking)) {
            return false;
        }

        return $booking->fare_change_accepted_at === null
            && SabreOfferRefreshAcceptance::isAccepted($booking) === false;
    }

    /**
     * Reconcile meta after checkout revalidation; clears stale inline flags when totals agree.
     *
     * @param  array<string, mixed>  $meta
     * @return array{fare_changed: bool, old_total: float|null, new_total: float|null}
     */
    public function reconcileAfterRevalidation(
        Booking $booking,
        array &$meta,
        float $selectedTotal,
        float $revalidatedTotal,
        bool $validationReportedChange,
    ): array {
        $fareChanged = $validationReportedChange
            && $selectedTotal > 0
            && $revalidatedTotal > 0
            && abs($revalidatedTotal - $selectedTotal) > self::TOTAL_MATCH_THRESHOLD;

        if ($fareChanged) {
            $meta[self::META_FARE_CHANGE] = [
                'fare_changed' => true,
                'old_total' => round($selectedTotal, 2),
                'new_total' => round($revalidatedTotal, 2),
                'difference' => round($revalidatedTotal - $selectedTotal, 2),
                'currency' => strtoupper((string) ($meta['supplier_currency'] ?? $booking->currency ?? 'PKR')),
                'recorded_at' => now()->toIso8601String(),
            ];
            $meta[self::META_CHECKOUT_PRICE_CHANGE] = $meta[self::META_FARE_CHANGE];
            $meta['requires_price_change_confirmation'] = true;
            $meta['price_change_old_total'] = $selectedTotal;
            $meta['price_change_new_total'] = $revalidatedTotal;
        } else {
            unset(
                $meta[self::META_FARE_CHANGE],
                $meta[self::META_CHECKOUT_PRICE_CHANGE],
                $meta['requires_price_change_confirmation'],
                $meta['price_change_old_total'],
                $meta['price_change_new_total'],
            );
            if (($meta['price_changed'] ?? false) !== true) {
                $meta['price_changed'] = false;
            }
        }

        if ($fareChanged) {
            $this->clearAcceptedFareState($booking, $meta);
        }

        return [
            'fare_changed' => $fareChanged,
            'old_total' => $fareChanged ? $selectedTotal : null,
            'new_total' => $fareChanged ? $revalidatedTotal : null,
        ];
    }

    /**
     * Reset acceptance when fare/passenger/payment context changes before confirm.
     *
     * @param  array<string, mixed>  $meta
     */
    public function resetAcceptanceOnContextChange(Booking $booking, array &$meta): void
    {
        $this->clearAcceptedFareState($booking, $meta);
    }

    /**
     * Review GET/POST: discard stale acceptance when no active fare_changed=true or context drifted.
     */
    public function synchronizeAcceptanceOnReview(Booking $booking): bool
    {
        $booking->loadMissing(['passengers', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $mutated = false;

        $selectedTotal = (float) ($booking->selected_fare_total ?? 0);
        $revalidatedTotal = (float) ($booking->revalidated_fare_total ?? 0);
        $totalsAgree = $selectedTotal > 0
            && $revalidatedTotal > 0
            && abs($selectedTotal - $revalidatedTotal) <= self::TOTAL_MATCH_THRESHOLD;

        $strictFareChanged = $this->strictPersistedFareChanged($meta);
        $offerRefreshPending = SabreOfferRefreshAcceptance::requiresAcceptanceFromMeta($meta);

        if (! $strictFareChanged && ! $offerRefreshPending) {
            if (self::fareChangePresent($meta)
                || self::checkoutPriceChangePresent($meta)
                || ($meta['requires_price_change_confirmation'] ?? false) === true) {
                unset(
                    $meta[self::META_FARE_CHANGE],
                    $meta[self::META_CHECKOUT_PRICE_CHANGE],
                    $meta['requires_price_change_confirmation'],
                    $meta['price_change_old_total'],
                    $meta['price_change_new_total'],
                );
                if (($meta['price_changed'] ?? false) !== true) {
                    $meta['price_changed'] = false;
                }
                $mutated = true;
            }
        }

        if ($totalsAgree && ! $strictFareChanged && $offerRefreshPending) {
            $this->clearOfferRefreshPendingMeta($meta);
            $mutated = true;
        }

        if (! $strictFareChanged && ! SabreOfferRefreshAcceptance::requiresAcceptanceFromMeta($meta)) {
            if ($this->hasStoredAcceptance($booking, $meta)) {
                $this->clearAcceptedFareState($booking, $meta);
                Log::info('checkout.accepted_fare_state_discarded', [
                    'booking_id' => $booking->id,
                    'accepted_fare_state_discarded' => true,
                    'discard_reason' => 'no_active_fare_change',
                ]);
                $mutated = true;
            }
        } elseif ($this->hasStoredAcceptance($booking, $meta)) {
            $storedHash = trim((string) ($meta[self::META_ACCEPTED_FARE_CONTEXT_HASH] ?? ''));
            $currentHash = $this->buildReviewContextHash($booking);
            if ($storedHash !== '' && $storedHash !== $currentHash) {
                $this->clearAcceptedFareState($booking, $meta);
                Log::info('checkout.accepted_fare_state_discarded', [
                    'booking_id' => $booking->id,
                    'accepted_fare_state_discarded' => true,
                    'discard_reason' => 'context_hash_mismatch',
                ]);
                $mutated = true;
            }
        }

        if ($mutated) {
            $booking->forceFill(['meta' => $meta])->save();
            $booking->refresh();
        }

        return $mutated;
    }

    /**
     * POST confirm gate — only when active fare-change context, accepted total, and hash match.
     */
    public function confirmationTotalMismatchBlocksSubmit(Booking $booking): bool
    {
        if (! $this->hasActiveFareChangeContext($booking)) {
            return false;
        }

        $acceptedTotal = $this->resolvedAcceptedFareTotal($booking);
        if ($acceptedTotal === null) {
            return false;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $storedHash = trim((string) ($meta[self::META_ACCEPTED_FARE_CONTEXT_HASH] ?? ''));
        $currentHash = $this->buildReviewContextHash($booking);
        if ($storedHash === '' || $storedHash !== $currentHash) {
            return false;
        }

        $booking->loadMissing('fareBreakdown');
        $displayTotal = (float) ($booking->fareBreakdown?->total ?? 0);
        if ($displayTotal <= 0) {
            $displayTotal = (float) ($booking->revalidated_fare_total ?? 0);
        }
        if ($displayTotal <= 0) {
            return false;
        }

        return abs($displayTotal - $acceptedTotal) > self::TOTAL_MATCH_THRESHOLD;
    }

    /**
     * Persist server-side acceptance with review context hash (customer or reconcile path).
     */
    public function recordCustomerAcceptance(Booking $booking): void
    {
        $booking->loadMissing(['passengers', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $acceptedTotal = (float) ($booking->fareBreakdown?->total ?? 0);
        if ($acceptedTotal <= 0) {
            $acceptedTotal = (float) ($booking->revalidated_fare_total ?? 0);
        }
        if ($acceptedTotal <= 0) {
            $acceptedTotal = (float) ($booking->selected_fare_total ?? 0);
        }

        $meta[self::META_ACCEPTED_FARE_CONTEXT_HASH] = $this->buildReviewContextHash($booking);
        if ($acceptedTotal > 0) {
            $meta[self::META_ACCEPTED_FARE_TOTAL] = round($acceptedTotal, 2);
        }

        $booking->forceFill([
            'meta' => $meta,
            'fare_change_accepted_at' => now(),
        ])->save();
    }

    public function buildReviewContextHash(Booking $booking): string
    {
        $booking->loadMissing('passengers');
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $option = is_array($meta['selected_fare_family_option'] ?? null)
            ? $meta['selected_fare_family_option']
            : [];
        $sabreCtx = is_array($meta['sabre_booking_context'] ?? null)
            ? $meta['sabre_booking_context']
            : [];

        $payload = [
            'booking_id' => $booking->id,
            'offer_id' => trim((string) ($meta['original_offer_id'] ?? $booking->flight_offer_id ?? '')),
            'fare_option_key' => trim((string) ($meta['fare_option_key'] ?? '')),
            'brand_code' => trim((string) ($option['brand_code'] ?? $sabreCtx['selected_brand_code'] ?? '')),
            'fare_basis' => $this->resolveFareBasis($option, $sabreCtx, $meta),
            'selected_fare_total' => round((float) ($booking->selected_fare_total ?? 0), 2),
            'revalidated_fare_total' => round((float) ($booking->revalidated_fare_total ?? 0), 2),
            'segment_hash' => $this->buildSegmentHash($meta),
            'passenger_count' => $booking->passengers->count(),
            'confirmation_method' => trim((string) (
                $meta['confirmation_method']
                ?? $meta['booking_method']
                ?? $booking->confirmation_method
                ?? ''
            )),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function resolvedAcceptedFareTotal(Booking $booking): ?float
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $fromMeta = $meta[self::META_ACCEPTED_FARE_TOTAL] ?? null;
        if (is_numeric($fromMeta) && (float) $fromMeta > 0) {
            return (float) $fromMeta;
        }

        if ($booking->fare_change_accepted_at === null && ! SabreOfferRefreshAcceptance::isAccepted($booking)) {
            return null;
        }

        $revalidatedTotal = (float) ($booking->revalidated_fare_total ?? 0);
        if ($revalidatedTotal > 0) {
            return $revalidatedTotal;
        }

        $selectedTotal = (float) ($booking->selected_fare_total ?? 0);

        return $selectedTotal > 0 ? $selectedTotal : null;
    }

    public function hasActiveFareChangeContext(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];

        return $this->strictPersistedFareChanged($meta)
            || SabreOfferRefreshAcceptance::requiresAcceptanceFromMeta($meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function clearAcceptedFareState(Booking $booking, array &$meta): void
    {
        $meta[SabreOfferRefreshAcceptance::META_ACCEPTED] = false;
        unset(
            $meta[SabreOfferRefreshAcceptance::META_ACCEPTED_AT],
            $meta[SabreOfferRefreshAcceptance::META_ACCEPTED_BY],
            $meta[self::META_ACCEPTED_FARE_CONTEXT_HASH],
            $meta[self::META_ACCEPTED_FARE_TOTAL],
        );
        $booking->forceFill(['fare_change_accepted_at' => null]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function clearOfferRefreshPendingMeta(array &$meta): void
    {
        $meta[SabreOfferRefreshAcceptance::META_REQUIRES_CONFIRMATION] = false;
        $meta[SabreOfferRefreshAcceptance::META_PRICE_CHANGED] = false;
        unset(
            $meta[SabreOfferRefreshAcceptance::META_OLD_SUPPLIER_TOTAL],
            $meta[SabreOfferRefreshAcceptance::META_NEW_SUPPLIER_TOTAL],
            $meta[SabreOfferRefreshAcceptance::META_PRICE_DELTA],
            $meta[SabreOfferRefreshAcceptance::META_OLD_CUSTOMER_TOTAL],
            $meta[SabreOfferRefreshAcceptance::META_NEW_CUSTOMER_TOTAL],
            $meta[SabreOfferRefreshAcceptance::META_CUSTOMER_PRICE_DELTA],
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function hasStoredAcceptance(Booking $booking, array $meta): bool
    {
        if ($booking->fare_change_accepted_at !== null) {
            return true;
        }

        if (($meta[SabreOfferRefreshAcceptance::META_ACCEPTED] ?? false) === true) {
            return true;
        }

        return isset($meta[self::META_ACCEPTED_FARE_CONTEXT_HASH])
            || isset($meta[self::META_ACCEPTED_FARE_TOTAL]);
    }

    /**
     * @param  array<string, mixed>  $option
     * @param  array<string, mixed>  $sabreCtx
     * @param  array<string, mixed>  $meta
     */
    protected function resolveFareBasis(array $option, array $sabreCtx, array $meta): string
    {
        $codes = $option['fare_basis_codes_by_segment']
            ?? $sabreCtx['fare_basis_codes_by_segment']
            ?? null;
        if (is_array($codes) && $codes !== []) {
            return implode('/', array_map(static fn ($c) => trim((string) $c), $codes));
        }

        foreach (['normalized_offer_snapshot', 'validated_offer_snapshot', 'flight_offer_snapshot'] as $key) {
            $segments = is_array($meta[$key]['segments'] ?? null) ? $meta[$key]['segments'] : [];
            $fromSegments = [];
            foreach ($segments as $segment) {
                if (! is_array($segment)) {
                    continue;
                }
                $code = trim((string) ($segment['fare_basis_code'] ?? ''));
                if ($code !== '') {
                    $fromSegments[] = $code;
                }
            }
            if ($fromSegments !== []) {
                return implode('/', $fromSegments);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function buildSegmentHash(array $meta): string
    {
        $parts = [];
        foreach (['normalized_offer_snapshot', 'validated_offer_snapshot', 'flight_offer_snapshot'] as $key) {
            $segments = is_array($meta[$key]['segments'] ?? null) ? $meta[$key]['segments'] : [];
            if ($segments === []) {
                continue;
            }
            foreach ($segments as $segment) {
                if (! is_array($segment)) {
                    continue;
                }
                $parts[] = implode('|', [
                    strtoupper(trim((string) ($segment['origin'] ?? ''))),
                    strtoupper(trim((string) ($segment['destination'] ?? ''))),
                    trim((string) ($segment['carrier'] ?? $segment['marketing_carrier'] ?? '')),
                    trim((string) ($segment['flight_number'] ?? '')),
                    trim((string) ($segment['departure_at'] ?? '')),
                    strtoupper(trim((string) ($segment['booking_class'] ?? ''))),
                    trim((string) ($segment['fare_basis_code'] ?? '')),
                ]);
            }
            break;
        }

        return hash('sha256', implode(';', $parts));
    }

    /**
     * Safe checkout diagnostics (Part E).
     *
     * @return array<string, mixed>
     */
    public function checkoutDiagnostics(Booking $booking, ?array $strategySelection = null): array
    {
        $booking->loadMissing('fareBreakdown');
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $selectedTotal = (float) ($booking->selected_fare_total ?? 0);
        $revalidatedTotal = (float) ($booking->revalidated_fare_total ?? 0);
        $confirmationDisplay = (float) ($booking->fareBreakdown?->total ?? 0);
        if ($confirmationDisplay <= 0) {
            $confirmationDisplay = $revalidatedTotal > 0 ? $revalidatedTotal : $selectedTotal;
        }

        $checkoutOutcome = is_array($meta['sabre_checkout_outcome'] ?? null)
            ? $meta['sabre_checkout_outcome']
            : [];
        $operationalReadiness = is_array($meta['operational_pnr_readiness'] ?? null)
            ? $meta['operational_pnr_readiness']
            : [];

        $strategySelected = trim((string) (
            $strategySelection['selected_strategy']
            ?? $checkoutOutcome['pnr_strategy_selected']
            ?? ''
        ));
        $strategyUsed = trim((string) ($checkoutOutcome['pnr_strategy_used'] ?? $checkoutOutcome['payload_schema'] ?? ''));

        return [
            'fare_change_present' => self::fareChangePresent($meta),
            'checkout_price_change_present' => self::checkoutPriceChangePresent($meta),
            'fare_changed' => $this->persistedFareChanged($booking),
            'accepted_fare_total' => $this->resolvedAcceptedFareTotal($booking),
            'selected_fare_total' => $selectedTotal > 0 ? $selectedTotal : null,
            'revalidated_fare_total' => $revalidatedTotal > 0 ? $revalidatedTotal : null,
            'confirmation_display_total' => $confirmationDisplay > 0 ? $confirmationDisplay : null,
            'pnr_attempted' => ($checkoutOutcome['live_call_attempted'] ?? false) === true
                || ($meta['operational_auto_pnr_attempted'] ?? false) === true,
            'pnr_strategy_selected' => $strategySelected !== '' ? $strategySelected : null,
            'pnr_strategy_used' => $strategyUsed !== '' ? $strategyUsed : null,
            'pnr_block_reason_code' => $checkoutOutcome['error_code']
                ?? $meta['operational_auto_pnr_reason_code']
                ?? ($operationalReadiness['reason_code'] ?? null),
            'pnr_blocking_conditions' => is_array($operationalReadiness['blocking_conditions'] ?? null)
                ? array_values($operationalReadiness['blocking_conditions'])
                : [],
        ];
    }

    /**
     * @return array{old_total: float, new_total: float, delta: float, currency: string, brand_label: string|null}|null
     */
    public function customerModalDisplay(Booking $booking): ?array
    {
        if (! $this->persistedFareChanged($booking)) {
            return null;
        }

        if (SabreOfferRefreshAcceptance::requiresAcceptance($booking)) {
            $display = SabreOfferRefreshAcceptance::customerDisplayFromBooking($booking);
            if ($display === null) {
                return null;
            }

            return array_merge($display, [
                'brand_label' => $this->selectedBrandLabel($booking),
            ]);
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $block = is_array($meta[self::META_FARE_CHANGE] ?? null)
            ? $meta[self::META_FARE_CHANGE]
            : (is_array($meta[self::META_CHECKOUT_PRICE_CHANGE] ?? null) ? $meta[self::META_CHECKOUT_PRICE_CHANGE] : null);
        if ($block === null || ($block['fare_changed'] ?? false) !== true) {
            return null;
        }

        $old = (float) ($block['old_total'] ?? 0);
        $new = (float) ($block['new_total'] ?? 0);
        if ($old <= 0 || $new <= 0) {
            return null;
        }

        return [
            'old_total' => $old,
            'new_total' => $new,
            'delta' => (float) ($block['difference'] ?? ($new - $old)),
            'currency' => strtoupper((string) ($block['currency'] ?? $booking->currency ?? 'PKR')),
            'brand_label' => $this->selectedBrandLabel($booking),
        ];
    }

    protected function selectedBrandLabel(Booking $booking): ?string
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $option = is_array($meta['selected_fare_family_option'] ?? null)
            ? $meta['selected_fare_family_option']
            : [];
        $name = trim((string) ($option['name'] ?? $option['fare_family_name'] ?? ''));
        $brand = trim((string) ($option['brand_code'] ?? data_get($meta, 'sabre_booking_context.selected_brand_code', '')));
        if ($name !== '' && $brand !== '') {
            return $name.'/'.$brand;
        }

        return $name !== '' ? $name : ($brand !== '' ? $brand : null);
    }
}
