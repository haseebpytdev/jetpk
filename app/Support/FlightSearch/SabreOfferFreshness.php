<?php

namespace App\Support\FlightSearch;

use App\Enums\SupplierProvider;
use App\Services\Suppliers\Sabre\Gds\SabreStoredPricingContextDigest;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Sprint 11K-F: Safe Sabre offer freshness metadata, stale guard, and high-risk cache signals (no raw payloads).
 */
final class SabreOfferFreshness
{
    public const STATUS_FRESH = 'fresh';

    public const STATUS_REFRESH_DUE = 'refresh_due';

    public const STATUS_STALE = 'stale';

    public const DIAG_OFFER_STALE_BEFORE_CHECKOUT = 'offer_stale_before_checkout';

    public const DIAG_SELECTED_OFFER_REVALIDATION_REQUIRED = 'selected_offer_revalidation_required';

    public const DIAG_SELECTED_OFFER_REVALIDATION_FAILED = 'selected_offer_revalidation_failed';

    public const DIAG_HIGH_RISK_CACHED_OFFER = 'high_risk_cached_offer';

    public function refreshDueSeconds(): int
    {
        return max(1, (int) config('ota.offer_freshness.refresh_due_seconds', 300));
    }

    public function staleAfterSeconds(): int
    {
        return max($this->refreshDueSeconds(), (int) config('ota.offer_freshness.stale_after_seconds', 600));
    }

    public function revalidationValiditySeconds(): int
    {
        return $this->staleAfterSeconds();
    }

    public function searchCreatedAtFromPayload(?array $searchPayload): ?Carbon
    {
        if ($searchPayload === null) {
            return null;
        }

        $raw = trim((string) ($searchPayload['search_created_at'] ?? $searchPayload['created_at'] ?? ''));

        return $this->parseTimestamp($raw);
    }

    public function parseTimestamp(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function offerAgeSeconds(?Carbon $anchor, ?Carbon $now = null): ?int
    {
        if ($anchor === null) {
            return null;
        }

        $now = $now ?? now();

        return max(0, (int) $anchor->diffInSeconds($now));
    }

    public function freshnessStatusForAge(?int $ageSeconds): string
    {
        if ($ageSeconds === null) {
            return self::STATUS_FRESH;
        }

        if ($ageSeconds >= $this->staleAfterSeconds()) {
            return self::STATUS_STALE;
        }

        if ($ageSeconds >= $this->refreshDueSeconds()) {
            return self::STATUS_REFRESH_DUE;
        }

        return self::STATUS_FRESH;
    }

    /**
     * @param  array<string, mixed>|null  $searchPayload
     * @return array<string, mixed>
     */
    public function buildSearchFreshnessMeta(?array $searchPayload): array
    {
        $createdAt = $this->searchCreatedAtFromPayload($searchPayload);
        $age = $this->offerAgeSeconds($createdAt);
        $status = $this->freshnessStatusForAge($age);

        return [
            'search_created_at' => $createdAt?->toIso8601String(),
            'offer_age_seconds' => $age,
            'offer_freshness_status' => $status,
            'offer_refresh_due_at' => $createdAt?->copy()->addSeconds($this->refreshDueSeconds())->toIso8601String(),
            'offer_stale_at' => $createdAt?->copy()->addSeconds($this->staleAfterSeconds())->toIso8601String(),
            'refresh_due_seconds' => $this->refreshDueSeconds(),
            'stale_after_seconds' => $this->staleAfterSeconds(),
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>|null  $searchPayload
     * @param  array<string, mixed>|null  $bookingMeta
     * @return array<string, mixed>
     */
    public function buildOfferFreshnessMeta(
        array $offer,
        ?array $searchPayload = null,
        ?array $bookingMeta = null,
        bool $forBookingSubmit = false,
    ): array {
        $bookingMeta = $this->mergeRevalidationMetaFromOffer($offer, $bookingMeta);
        $searchCreatedAt = $this->searchCreatedAtFromPayload($searchPayload);
        if ($searchCreatedAt === null && $forBookingSubmit) {
            $searchCreatedAt = $this->parseTimestamp(
                (string) ($bookingMeta['offer_validated_at']
                    ?? $bookingMeta['validated_at']
                    ?? $bookingMeta['checkout_lock_started_at']
                    ?? '')
            );
        }
        $selectedCreatedAt = $searchCreatedAt;
        $lastRevalidatedAt = $this->parseTimestamp(
            is_string($bookingMeta['selected_offer_last_revalidated_at'] ?? null)
                ? (string) $bookingMeta['selected_offer_last_revalidated_at']
                : (is_string($bookingMeta['last_revalidated_at'] ?? null) ? (string) $bookingMeta['last_revalidated_at'] : null)
        );

        $anchor = $lastRevalidatedAt ?? $searchCreatedAt;
        $age = $this->offerAgeSeconds($anchor);
        $status = $this->freshnessStatusForAge($age);
        $highRiskReasons = $this->assessHighRiskReasons($offer, $age ?? 0, $bookingMeta, $forBookingSubmit);
        $revalidationStatus = trim((string) ($bookingMeta['selected_offer_revalidation_status'] ?? $bookingMeta['revalidation_status'] ?? ''));

        $refreshDueAt = $anchor?->copy()->addSeconds($this->refreshDueSeconds())->toIso8601String();
        $staleAt = $anchor?->copy()->addSeconds($this->staleAfterSeconds())->toIso8601String();
        $revalidationExpiresAt = $lastRevalidatedAt?->copy()->addSeconds($this->revalidationValiditySeconds())->toIso8601String();

        return [
            'search_created_at' => $searchCreatedAt?->toIso8601String(),
            'selected_offer_created_at' => $selectedCreatedAt?->toIso8601String(),
            'offer_age_seconds' => $age,
            'offer_freshness_status' => $status,
            'offer_refresh_due_at' => $refreshDueAt,
            'offer_stale_at' => $staleAt,
            'last_revalidated_at' => $lastRevalidatedAt?->toIso8601String(),
            'revalidation_expires_at' => $revalidationExpiresAt,
            'revalidation_status' => $revalidationStatus !== '' ? $revalidationStatus : null,
            'high_risk_cached_offer' => $highRiskReasons !== [],
            'high_risk_reasons' => $highRiskReasons,
            'requires_revalidation_before_checkout' => $this->requiresRevalidationBeforeCheckout($status, $highRiskReasons, $revalidationStatus, $lastRevalidatedAt),
            'refresh_due_seconds' => $this->refreshDueSeconds(),
            'stale_after_seconds' => $this->staleAfterSeconds(),
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>|null  $bookingMeta
     * @return list<string>
     */
    public function assessHighRiskReasons(
        array $offer,
        int $ageSeconds,
        ?array $bookingMeta = null,
        bool $forBookingSubmit = false,
    ): array {
        if (strcasecmp((string) ($offer['supplier_provider'] ?? ''), SupplierProvider::Sabre->value) !== 0) {
            return [];
        }

        $bookingMeta = is_array($bookingMeta) ? $bookingMeta : [];
        $reasons = [];

        if ($ageSeconds >= $this->staleAfterSeconds()) {
            $reasons[] = 'offer_age_stale';
        } elseif ($ageSeconds >= $this->refreshDueSeconds()) {
            $reasons[] = 'offer_age_refresh_due';
        }

        $revalidationStatus = trim((string) ($bookingMeta['selected_offer_revalidation_status'] ?? $bookingMeta['revalidation_status'] ?? ''));
        $lastRevalidatedAt = $this->parseTimestamp(
            is_string($bookingMeta['selected_offer_last_revalidated_at'] ?? null)
                ? (string) $bookingMeta['selected_offer_last_revalidated_at']
                : null
        );

        if ($lastRevalidatedAt === null && $revalidationStatus === '' && $ageSeconds >= $this->refreshDueSeconds()) {
            $reasons[] = 'no_last_revalidated_at';
        }

        if (in_array($revalidationStatus, ['skipped', 'skipped_live_disabled', 'failed'], true)) {
            $reasons[] = 'revalidation_'.$revalidationStatus;
        }

        if (! $forBookingSubmit) {
            if ($this->segmentsMissingBookingClass($offer)) {
                $reasons[] = 'missing_rbd';
            }

            if ($this->offerMissingFareBasis($offer)) {
                $reasons[] = 'missing_fare_basis';
            }

            if ($this->offerMissingValidatingCarrier($offer)) {
                $reasons[] = 'missing_validating_carrier';
            }

            if ($this->offerMissingLegOrScheduleRefs($offer)) {
                $reasons[] = 'missing_leg_or_schedule_refs';
            }

            if ($this->offerIsMixedCarrier($offer)) {
                $reasons[] = 'mixed_carrier_interline';
            }

            $digest = is_array($offer['fare_verification_digest'] ?? null) ? $offer['fare_verification_digest'] : [];
            if (! empty($digest['stale_cached_result_possible'])) {
                $reasons[] = 'stale_cached_result_possible';
            }
        }

        if (($bookingMeta['persisted_host_rejection_for_offer'] ?? false) === true) {
            $reasons[] = 'prior_host_rejection_fingerprint_match';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param  list<string>  $highRiskReasons
     */
    public function requiresRevalidationBeforeCheckout(
        string $freshnessStatus,
        array $highRiskReasons,
        string $revalidationStatus,
        ?Carbon $lastRevalidatedAt,
    ): bool {
        if ($freshnessStatus === self::STATUS_STALE) {
            return true;
        }

        $structural = array_intersect($highRiskReasons, [
            'missing_rbd',
            'missing_fare_basis',
            'missing_validating_carrier',
            'missing_leg_or_schedule_refs',
            'mixed_carrier_interline',
            'stale_cached_result_possible',
            'prior_host_rejection_fingerprint_match',
        ]);

        if ($structural !== []) {
            return true;
        }

        if (in_array($revalidationStatus, ['failed', 'skipped'], true)) {
            return true;
        }

        if ($lastRevalidatedAt !== null && $this->revalidationStillValid($lastRevalidatedAt)) {
            return false;
        }

        if (array_intersect($highRiskReasons, ['offer_age_stale']) !== []) {
            return true;
        }

        if (array_intersect($highRiskReasons, ['offer_age_refresh_due', 'no_last_revalidated_at']) !== []
            && $freshnessStatus !== self::STATUS_FRESH) {
            return true;
        }

        return false;
    }

    public function revalidationStillValid(?Carbon $lastRevalidatedAt, ?Carbon $now = null): bool
    {
        if ($lastRevalidatedAt === null) {
            return false;
        }

        $now = $now ?? now();
        $age = $this->offerAgeSeconds($lastRevalidatedAt, $now);

        return $age !== null && $age < $this->revalidationValiditySeconds();
    }

    /**
     * @param  array<string, mixed>  $freshnessMeta
     * @return array{code: string, message: string, diagnostic: string}|null
     */
    public function blocksCheckoutTransition(array $freshnessMeta): ?array
    {
        $status = (string) ($freshnessMeta['offer_freshness_status'] ?? self::STATUS_FRESH);
        if ($status === self::STATUS_STALE) {
            return [
                'code' => 'offer_stale_before_checkout',
                'message' => $this->customerSafeMessage('offer_stale_before_checkout'),
                'diagnostic' => self::DIAG_OFFER_STALE_BEFORE_CHECKOUT,
            ];
        }

        if (($freshnessMeta['requires_revalidation_before_checkout'] ?? false) === true
            && ! $this->hasValidRecentRevalidation($freshnessMeta)) {
            return [
                'code' => 'selected_offer_revalidation_required',
                'message' => $this->customerSafeMessage('selected_offer_revalidation_required'),
                'diagnostic' => self::DIAG_SELECTED_OFFER_REVALIDATION_REQUIRED,
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>|null  $bookingMeta
     * @param  array<string, mixed>|null  $searchPayload
     * @return array{code: string, message: string, diagnostic: string}|null
     */
    public function blocksBookingSubmit(array $offer, ?array $bookingMeta, ?array $searchPayload): ?array
    {
        if (strcasecmp((string) ($offer['supplier_provider'] ?? ''), SupplierProvider::Sabre->value) !== 0) {
            return null;
        }

        $freshness = $this->buildOfferFreshnessMeta($offer, $searchPayload, $bookingMeta, true);

        $checkoutBlock = $this->blocksCheckoutTransition($freshness);
        if ($checkoutBlock !== null) {
            return $checkoutBlock;
        }

        $revalidationStatus = trim((string) ($freshness['revalidation_status'] ?? ''));
        if ($revalidationStatus === 'failed') {
            return [
                'code' => 'selected_offer_revalidation_failed',
                'message' => $this->customerSafeMessage('selected_offer_revalidation_failed'),
                'diagnostic' => self::DIAG_SELECTED_OFFER_REVALIDATION_FAILED,
            ];
        }

        if (($freshness['requires_revalidation_before_checkout'] ?? false) === true
            && ! $this->hasValidRecentRevalidation($freshness)) {
            return [
                'code' => 'selected_offer_revalidation_required',
                'message' => $this->customerSafeMessage('selected_offer_revalidation_required'),
                'diagnostic' => self::DIAG_SELECTED_OFFER_REVALIDATION_REQUIRED,
            ];
        }

        $highRiskReasons = is_array($freshness['high_risk_reasons'] ?? null) ? $freshness['high_risk_reasons'] : [];
        if (in_array('prior_host_rejection_fingerprint_match', $highRiskReasons, true)) {
            return [
                'code' => 'high_risk_cached_offer',
                'message' => $this->customerSafeMessage('prior_host_rejection_after_revalidation'),
                'diagnostic' => self::DIAG_HIGH_RISK_CACHED_OFFER,
            ];
        }

        if (($freshness['high_risk_cached_offer'] ?? false) === true
            && ! $this->hasValidRecentRevalidation($freshness)) {
            return [
                'code' => 'high_risk_cached_offer',
                'message' => $this->customerSafeMessage('high_risk_cached_offer'),
                'diagnostic' => self::DIAG_HIGH_RISK_CACHED_OFFER,
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $freshnessMeta
     */
    public function hasValidRecentRevalidation(array $freshnessMeta): bool
    {
        $last = $this->parseTimestamp(
            is_string($freshnessMeta['last_revalidated_at'] ?? null) ? (string) $freshnessMeta['last_revalidated_at'] : null
        );
        if ($last === null) {
            return false;
        }

        $status = trim((string) ($freshnessMeta['revalidation_status'] ?? ''));

        return $status === 'success' && $this->revalidationStillValid($last);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>|null  $bookingMeta
     * @return array<string, mixed>
     */
    public function mergeRevalidationMetaFromOffer(array $offer, ?array $bookingMeta = null): array
    {
        $bookingMeta = is_array($bookingMeta) ? $bookingMeta : [];
        $fromOffer = [];

        foreach ([
            'selected_offer_revalidation_status',
            'selected_offer_last_revalidated_at',
            'last_revalidated_at',
            'revalidation_status',
            'selected_offer_revalidation_reason',
            'selected_offer_revalidation_at',
        ] as $key) {
            if (array_key_exists($key, $offer) && $offer[$key] !== null && $offer[$key] !== '') {
                $fromOffer[$key] = $offer[$key];
            }
        }

        return array_merge($fromOffer, $bookingMeta);
    }

    /**
     * Stamp booking meta after a successful controlled/admin offer re-shop (no raw Sabre payloads).
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function stampBookingMetaAfterSuccessfulOfferRefresh(array $meta, ?CarbonInterface $now = null): array
    {
        $now = $now ?? now();
        $iso = $now->toIso8601String();

        $meta['offer_validated_at'] = $iso;
        $meta['validated_at'] = $iso;
        $meta['selected_offer_last_revalidated_at'] = $iso;
        $meta['last_revalidated_at'] = $iso;
        $meta['selected_offer_revalidation_at'] = $iso;
        $meta['selected_offer_revalidation_status'] = 'success';
        $meta['revalidation_status'] = 'success';
        $meta['offer_refresh_status'] = 'refreshed';
        $meta['offer_refresh_refreshed_at'] = $iso;

        if (trim((string) ($meta['flight_offer_snapshot_refreshed_at'] ?? '')) === '') {
            $meta['flight_offer_snapshot_refreshed_at'] = $iso;
        }

        return $meta;
    }

    public function customerSafeMessage(string $code): string
    {
        return match ($code) {
            'offer_stale_before_checkout', 'offer_stale' => (string) __(
                'This fare needs to be refreshed because airline prices and availability can change quickly.'
            ),
            'selected_offer_revalidation_required', 'high_risk_cached_offer' => (string) __(
                'This fare needs to be refreshed before you continue. Please update availability and try again.'
            ),
            'selected_offer_revalidation_failed', 'prior_host_rejection_after_revalidation' => (string) __(
                'We could not confirm this fare with the airline. Please choose another option or refresh your search.'
            ),
            'refresh_due_warning' => (string) __(
                'Fares and availability may have changed.'
            ),
            'refresh_search_success' => (string) __(
                'Fares and availability have been refreshed. You can continue with your selection.'
            ),
            default => (string) __('This fare needs to be refreshed because airline prices and availability can change quickly.'),
        };
    }

    public function diagnosticClassification(string $code): string
    {
        return match ($code) {
            'offer_stale_before_checkout', 'offer_stale' => self::DIAG_OFFER_STALE_BEFORE_CHECKOUT,
            'selected_offer_revalidation_required' => self::DIAG_SELECTED_OFFER_REVALIDATION_REQUIRED,
            'selected_offer_revalidation_failed' => self::DIAG_SELECTED_OFFER_REVALIDATION_FAILED,
            'high_risk_cached_offer' => self::DIAG_HIGH_RISK_CACHED_OFFER,
            default => self::DIAG_HIGH_RISK_CACHED_OFFER,
        };
    }

    /**
     * Customer/API-safe subset (no internal high-risk reason lists in public JSON when not debug).
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function sanitizeForCustomerApi(array $meta, bool $includeRiskReasons = false): array
    {
        $keys = [
            'search_created_at',
            'selected_offer_created_at',
            'offer_age_seconds',
            'offer_freshness_status',
            'offer_refresh_due_at',
            'offer_stale_at',
            'last_revalidated_at',
            'revalidation_expires_at',
            'revalidation_status',
            'high_risk_cached_offer',
            'requires_revalidation_before_checkout',
            'refresh_due_seconds',
            'stale_after_seconds',
        ];

        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $meta)) {
                $out[$key] = $meta[$key];
            }
        }

        if ($includeRiskReasons && isset($meta['high_risk_reasons']) && is_array($meta['high_risk_reasons'])) {
            $out['high_risk_reasons'] = array_values(array_map(static fn ($r) => (string) $r, $meta['high_risk_reasons']));
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function segmentsMissingBookingClass(array $offer): bool
    {
        $segments = is_array($offer['segments'] ?? null) ? $offer['segments'] : [];
        if ($segments === []) {
            return true;
        }

        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                return true;
            }
            $rbd = trim((string) ($segment['booking_class'] ?? $segment['class_of_service'] ?? $segment['rbd'] ?? ''));

            if ($rbd === '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function offerMissingFareBasis(array $offer): bool
    {
        $digest = app(SabreStoredPricingContextDigest::class)->digest($offer);
        $fbc = is_array($digest['fare_basis_codes'] ?? null) ? $digest['fare_basis_codes'] : [];

        return $fbc === [];
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function offerMissingValidatingCarrier(array $offer): bool
    {
        $vc = strtoupper(trim((string) ($offer['validating_carrier'] ?? '')));
        if ($vc !== '') {
            return false;
        }

        $digest = app(SabreStoredPricingContextDigest::class)->digest($offer);

        return trim((string) ($digest['validating_carrier'] ?? '')) === '';
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function offerMissingLegOrScheduleRefs(array $offer): bool
    {
        $raw = is_array($offer['raw_payload'] ?? null) ? $offer['raw_payload'] : [];
        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $legRefs = is_array($ctx['leg_refs'] ?? null) ? $ctx['leg_refs'] : [];
        $scheduleRefs = is_array($ctx['schedule_refs'] ?? null) ? $ctx['schedule_refs'] : [];

        if ($legRefs !== [] && $scheduleRefs !== []) {
            return false;
        }

        $bookingCtx = is_array($raw['sabre_booking_context'] ?? null) ? $raw['sabre_booking_context'] : [];
        $legRefs = $legRefs !== [] ? $legRefs : (is_array($bookingCtx['leg_refs'] ?? null) ? $bookingCtx['leg_refs'] : []);
        $scheduleRefs = $scheduleRefs !== [] ? $scheduleRefs : (is_array($bookingCtx['schedule_refs'] ?? null) ? $bookingCtx['schedule_refs'] : []);

        return $legRefs === [] || $scheduleRefs === [];
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function offerIsMixedCarrier(array $offer): bool
    {
        $segments = is_array($offer['segments'] ?? null) ? $offer['segments'] : [];
        $carriers = [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $carrier = strtoupper(trim((string) ($segment['marketing_carrier'] ?? $segment['airline_code'] ?? $segment['carrier'] ?? '')));
            if ($carrier !== '') {
                $carriers[$carrier] = true;
            }
        }

        return count($carriers) > 1;
    }
}
