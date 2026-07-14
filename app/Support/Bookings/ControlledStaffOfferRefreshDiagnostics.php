<?php

namespace App\Support\Bookings;

use App\Models\Booking;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Support\Security\SensitiveDataRedactor;

/**
 * E1F: Safe controlled admin/staff offer-refresh failure diagnostics (no raw Sabre payloads).
 */
final class ControlledStaffOfferRefreshDiagnostics
{
    public const ACTION_FRESH_SEARCH = 'fresh_search_required';

    public const ACTION_RETRY_AFTER_COOLDOWN = 'retry_after_cooldown';

    public const ACTION_FARE_ACCEPTANCE = 'fare_acceptance_required';

    public const ACTION_REBUILD_CONTEXT = 'rebuild_supplier_context';

    public const ACTION_RETRY_OFFER_REFRESH = 'retry_offer_refresh';

    /** @var list<string> */
    private const MISSING_CONTEXT_REFRESH_ERRORS = [
        'missing_search_criteria',
        'missing_offer_snapshot',
        'missing_stored_segments',
        'not_sabre_booking',
        'missing_agency',
    ];

    /** @var list<string> */
    public const REFRESH_STAGE_SUMMARY_KEYS = [
        'refresh_stage',
        'refresh_exception_class',
        'refresh_exception_code',
        'refresh_exception_message_safe',
        'fresh_search_attempted',
        'fresh_search_result_present',
        'fresh_search_error_code',
        'match_attempted',
        'match_found',
        'apply_refresh_attempted',
        'meta_stamp_attempted',
    ];

    /**
     * @return array<string, mixed>
     */
    public function buildAttemptSafeSummary(
        Booking $booking,
        string $reason,
        string $reasonCode,
        bool $refreshAttempted = false,
        ?array $refreshResult = null,
        ?\Throwable $exception = null,
    ): array {
        $context = $this->assessBookingContext($booking);
        $refreshStatus = $this->resolveRefreshStatus($reason, $reasonCode, $refreshAttempted, $refreshResult);
        $refreshReasonCode = $this->resolveRefreshReasonCode($reasonCode, $refreshResult, $refreshStatus);
        $recommended = $this->resolveRecommendedStaffAction($reasonCode, $context, $refreshResult, $refreshAttempted, $refreshStatus);
        $message = $this->adminMessageForAction($recommended, $refreshReasonCode);

        $summary = array_merge($context, [
            'refresh_attempted' => $refreshAttempted,
            'refresh_status' => $refreshStatus,
            'refresh_reason_code' => $refreshReasonCode,
            'refresh_message' => $message,
            'live_call_attempted' => false,
            'recommended_staff_action' => $recommended,
        ]);

        if ($refreshResult !== null) {
            if (array_key_exists('match_found', $refreshResult)) {
                $summary['refresh_match_found'] = ($refreshResult['match_found'] ?? false) === true;
            }
            if (array_key_exists('applied', $refreshResult)) {
                $summary['refresh_applied'] = ($refreshResult['applied'] ?? false) === true;
            }
            if (array_key_exists('price_changed', $refreshResult)) {
                $summary['refresh_price_changed'] = ($refreshResult['price_changed'] ?? false) === true;
            }
            $reasons = is_array($refreshResult['reasons'] ?? null) ? $refreshResult['reasons'] : [];
            if ($reasons !== []) {
                $summary['refresh_reasons'] = array_values(array_slice(array_map('strval', $reasons), 0, 8));
            }
        }

        if ($reason !== '') {
            $summary['reason'] = $reason;
        }
        if ($reasonCode !== '') {
            $summary['reason_code'] = $reasonCode;
        }

        return $this->mergeRefreshStageDiagnostics($summary, $refreshResult, $exception);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>|null  $refreshResult
     * @return array<string, mixed>
     */
    public function mergeRefreshStageDiagnostics(array $summary, ?array $refreshResult, ?\Throwable $exception = null): array
    {
        if ($refreshResult !== null) {
            foreach (self::REFRESH_STAGE_SUMMARY_KEYS as $key) {
                if (! array_key_exists($key, $refreshResult)) {
                    continue;
                }
                $value = $refreshResult[$key];
                if ($value === null || $value === '') {
                    if (in_array($key, ['refresh_exception_class', 'refresh_exception_message_safe', 'fresh_search_error_code', 'refresh_exception_code'], true)) {
                        continue;
                    }
                }
                $summary[$key] = $value;
            }
        }

        if ($exception !== null) {
            $summary = array_merge($summary, $this->safeExceptionDiagnostics($exception));
            if (! isset($summary['refresh_stage']) || trim((string) $summary['refresh_stage']) === '') {
                $summary['refresh_stage'] = 'refresh_exception';
            }
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function safeExceptionDiagnostics(\Throwable $exception): array
    {
        $code = $exception->getCode();

        return [
            'refresh_exception_class' => class_basename($exception),
            'refresh_exception_code' => is_int($code) && $code !== 0 ? $code : null,
            'refresh_exception_message_safe' => SensitiveDataRedactor::sanitizeErrorMessage($exception->getMessage()),
        ];
    }

    /**
     * @return array{
     *     checkout_search_id_present: bool,
     *     checkout_search_cache_present: bool,
     *     search_criteria_present: bool,
     *     offer_snapshot_present: bool,
     *     offer_reference_present: bool,
     *     shop_identifiers_present: bool,
     *     missing_context_fields: list<string>,
     *     refresh_available: bool,
     *     context_can_be_rebuilt: bool
     * }
     */
    public function assessBookingContext(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = SabreOfferRefreshAcceptance::authoritativeOfferSnapshot($meta);
        $safeContextAssess = app(SabreSafeRefreshContext::class)->assess($meta);

        $searchCriteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        $searchCriteriaPresent = $searchCriteria !== []
            || ($safeContextAssess['safe_refresh_context_complete'] ?? false);

        $checkoutSearchId = trim((string) ($meta['checkout_search_id'] ?? ''));
        $checkoutSearchIdPresent = $checkoutSearchId !== '';
        $checkoutSearchCachePresent = false;
        if ($checkoutSearchIdPresent) {
            $checkoutSearchCachePresent = app(FlightSearchResultStore::class)->get($checkoutSearchId) !== null;
        }

        $offerSnapshotPresent = $snapshot !== [];
        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $shopIds = is_array($raw['sabre_shop_identifiers'] ?? null) ? $raw['sabre_shop_identifiers'] : [];
        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $durableContext = app(SabreSafeRefreshContext::class)->fromMeta($meta);
        $durableShopIds = is_array($durableContext['shop_identifiers'] ?? null) ? $durableContext['shop_identifiers'] : [];

        $offerReferencePresent = trim((string) (
            $raw['offer_reference']
            ?? $ctx['offer_ref']
            ?? $ctx['offer_id']
            ?? $handoff['offer_reference']
            ?? $durableContext['offer_reference']
            ?? ''
        )) !== '';
        $shopIdentifiersPresent = $shopIds !== [] || $durableShopIds !== [];

        $canRebuildFromSafeContext = ($safeContextAssess['can_rebuild_from_safe_context'] ?? false) === true;
        $missing = [];
        if (! $searchCriteriaPresent) {
            $missing[] = 'search_criteria';
        }
        if (! $offerSnapshotPresent) {
            $missing[] = 'offer_snapshot';
        }
        if (! $canRebuildFromSafeContext) {
            if (! $checkoutSearchIdPresent) {
                $missing[] = 'checkout_search_id';
            } elseif (! $checkoutSearchCachePresent) {
                $missing[] = 'checkout_search_cache';
            }
            if (! $offerReferencePresent) {
                $missing[] = 'offer_reference';
            }
            if (! $shopIdentifiersPresent) {
                $missing[] = 'shop_identifiers';
            }
        }
        if (($safeContextAssess['safe_refresh_context_present'] ?? false) !== true) {
            $missing[] = 'sabre_safe_refresh_context';
        } elseif (($safeContextAssess['safe_refresh_context_complete'] ?? false) !== true) {
            foreach ($safeContextAssess['safe_refresh_context_missing_fields'] ?? [] as $field) {
                $missing[] = is_string($field) ? $field : (string) $field;
            }
        }

        $rebuildProbe = $offerSnapshotPresent
            ? app(SabrePnrCertificationSupport::class)->buildMultiSegmentPnrReadinessDiagnostics($booking)
            : [];
        $contextCanBeRebuilt = ($rebuildProbe['context_refresh_available'] ?? false) === true
            || $canRebuildFromSafeContext;

        $refreshAvailable = ($searchCriteriaPresent || $canRebuildFromSafeContext)
            && $offerSnapshotPresent
            && $booking->agency_id !== null;

        return array_merge($safeContextAssess, [
            'checkout_search_id_present' => $checkoutSearchIdPresent,
            'checkout_search_cache_present' => $checkoutSearchCachePresent,
            'search_criteria_present' => $searchCriteriaPresent,
            'offer_snapshot_present' => $offerSnapshotPresent,
            'offer_reference_present' => $offerReferencePresent,
            'shop_identifiers_present' => $shopIdentifiersPresent,
            'missing_context_fields' => array_values(array_unique($missing)),
            'refresh_available' => $refreshAvailable,
            'context_can_be_rebuilt' => $contextCanBeRebuilt,
        ]);
    }

    public function adminMessageForAction(string $action, string $refreshReasonCode = ''): string
    {
        return match ($action) {
            self::ACTION_FRESH_SEARCH => 'Offer refresh failed. Create a fresh search/booking or rebuild supplier context if available.',
            self::ACTION_FARE_ACCEPTANCE => SabreOfferRefreshAcceptance::ADMIN_MESSAGE,
            self::ACTION_RETRY_AFTER_COOLDOWN => 'Offer refresh failed due to a temporary supplier issue. Wait a few minutes, then retry PNR creation.',
            self::ACTION_REBUILD_CONTEXT => 'Offer context is stale. Use Prepare supplier PNR context when available, then retry.',
            default => 'Retry will refresh the Sabre offer before PNR creation.',
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $refreshResult
     */
    public function resolveRecommendedStaffAction(
        string $reasonCode,
        array $context,
        ?array $refreshResult,
        bool $refreshAttempted,
        string $refreshStatus,
    ): string {
        $reasonCode = strtolower(trim($reasonCode));

        if ($reasonCode === SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE
            || ($refreshResult['price_changed'] ?? false) === true) {
            return self::ACTION_FARE_ACCEPTANCE;
        }

        $refreshError = strtolower(trim((string) ($refreshResult['error'] ?? '')));
        if (in_array($refreshError, self::MISSING_CONTEXT_REFRESH_ERRORS, true)
            || in_array($reasonCode, self::MISSING_CONTEXT_REFRESH_ERRORS, true)) {
            return self::ACTION_FRESH_SEARCH;
        }

        if (($context['refresh_available'] ?? false) !== true
            && ($context['can_rebuild_from_safe_context'] ?? false) !== true) {
            return self::ACTION_FRESH_SEARCH;
        }

        if ($refreshStatus === 'exception') {
            return self::ACTION_RETRY_AFTER_COOLDOWN;
        }

        if ($reasonCode === 'offer_refresh_unavailable'
            || ($refreshResult['match_found'] ?? null) === false
            || $refreshStatus === 'no_match') {
            return self::ACTION_FRESH_SEARCH;
        }

        if ($reasonCode === 'offer_refresh_failed') {
            return self::ACTION_RETRY_AFTER_COOLDOWN;
        }

        if (in_array($reasonCode, [
            'offer_stale_before_checkout',
            'selected_offer_revalidation_required',
            'selected_offer_revalidation_failed',
            'high_risk_cached_offer',
        ], true)) {
            if (($context['context_can_be_rebuilt'] ?? false) === true) {
                return self::ACTION_REBUILD_CONTEXT;
            }

            return ($context['refresh_available'] ?? false) === true
                ? self::ACTION_RETRY_OFFER_REFRESH
                : self::ACTION_FRESH_SEARCH;
        }

        return self::ACTION_RETRY_OFFER_REFRESH;
    }

    /**
     * @param  array<string, mixed>|null  $refreshResult
     */
    protected function resolveRefreshStatus(
        string $reason,
        string $reasonCode,
        bool $refreshAttempted,
        ?array $refreshResult,
    ): string {
        if (! $refreshAttempted) {
            return 'not_attempted';
        }

        if ($refreshResult === null && $reasonCode === 'offer_refresh_failed') {
            return 'exception';
        }

        $error = strtolower(trim((string) ($refreshResult['error'] ?? '')));
        if ($error === 'refresh_exception') {
            return 'exception';
        }
        if ($error !== '') {
            return in_array($error, self::MISSING_CONTEXT_REFRESH_ERRORS, true)
                ? 'context_missing'
                : 'failed';
        }

        if (($refreshResult['match_found'] ?? null) === false) {
            return 'no_match';
        }

        if (($refreshResult['can_apply'] ?? null) === false && ($refreshResult['match_found'] ?? false) === true) {
            return 'match_not_applicable';
        }

        if ($reason === 'offer_validation_required' && $refreshAttempted) {
            return 'blocked_after_refresh';
        }

        return 'failed';
    }

    /**
     * @param  array<string, mixed>|null  $refreshResult
     */
    protected function resolveRefreshReasonCode(string $reasonCode, ?array $refreshResult, string $refreshStatus): string
    {
        $refreshError = strtolower(trim((string) ($refreshResult['error'] ?? '')));
        if ($refreshError === 'refresh_exception') {
            return 'refresh_exception';
        }
        if ($refreshError !== '') {
            return $refreshError;
        }

        if ($refreshStatus === 'exception') {
            return 'refresh_exception';
        }

        if ($refreshStatus === 'no_match') {
            $reasons = is_array($refreshResult['reasons'] ?? null) ? $refreshResult['reasons'] : [];
            if (in_array('no_matching_offer_in_shop', $reasons, true)) {
                return 'no_matching_offer_in_shop';
            }

            return 'offer_refresh_unavailable';
        }

        return strtolower(trim($reasonCode)) !== '' ? strtolower(trim($reasonCode)) : 'offer_refresh_failed';
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     * @return array<string, mixed>|null
     */
    public function panelFromSafeSummary(array $safeSummary, ?string $errorCode = null): ?array
    {
        $errorCode = strtolower(trim((string) ($errorCode ?? '')));
        $refreshAttempted = ($safeSummary['refresh_attempted'] ?? false) === true;
        $signals = array_filter([
            $errorCode,
            strtolower(trim((string) ($safeSummary['reason_code'] ?? ''))),
            strtolower(trim((string) ($safeSummary['reason'] ?? ''))),
        ]);

        $offerRefreshFailure = $refreshAttempted
            || array_intersect($signals, [
                'offer_refresh_failed',
                'offer_refresh_unavailable',
                'offer_validation_required',
                'offer_stale_before_checkout',
                'selected_offer_revalidation_required',
                'selected_offer_revalidation_failed',
            ]) !== [];

        if (! $offerRefreshFailure) {
            return null;
        }

        $recommended = trim((string) ($safeSummary['recommended_staff_action'] ?? ''));
        if ($recommended === '' && in_array('offer_refresh_failed', $signals, true)) {
            $recommended = self::ACTION_RETRY_AFTER_COOLDOWN;
            if (($safeSummary['refresh_attempted'] ?? null) === null) {
                $safeSummary['refresh_attempted'] = true;
            }
            if (($safeSummary['refresh_status'] ?? '') === '') {
                $safeSummary['refresh_status'] = 'exception';
            }
            if (($safeSummary['refresh_reason_code'] ?? '') === '') {
                $safeSummary['refresh_reason_code'] = 'refresh_exception';
            }
        }

        $message = trim((string) ($safeSummary['refresh_message'] ?? ''));
        if ($message === '' && $recommended !== '') {
            $message = $this->adminMessageForAction(
                $recommended,
                (string) ($safeSummary['refresh_reason_code'] ?? ''),
            );
        }

        return [
            'show_panel' => true,
            'admin_message' => $message,
            'recommended_staff_action' => $recommended,
            'refresh_attempted' => $refreshAttempted,
            'refresh_available' => ($safeSummary['refresh_available'] ?? null),
            'refresh_status' => (string) ($safeSummary['refresh_status'] ?? ''),
            'refresh_reason_code' => (string) ($safeSummary['refresh_reason_code'] ?? ''),
            'missing_context_fields' => is_array($safeSummary['missing_context_fields'] ?? null)
                ? array_values($safeSummary['missing_context_fields'])
                : [],
            'checkout_search_id_present' => ($safeSummary['checkout_search_id_present'] ?? null),
            'search_criteria_present' => ($safeSummary['search_criteria_present'] ?? null),
            'offer_reference_present' => ($safeSummary['offer_reference_present'] ?? null),
            'shop_identifiers_present' => ($safeSummary['shop_identifiers_present'] ?? null),
            'safe_refresh_context_present' => ($safeSummary['safe_refresh_context_present'] ?? null),
            'safe_refresh_context_complete' => ($safeSummary['safe_refresh_context_complete'] ?? null),
            'can_rebuild_from_safe_context' => ($safeSummary['can_rebuild_from_safe_context'] ?? null),
            'refresh_reasons' => is_array($safeSummary['refresh_reasons'] ?? null)
                ? array_values($safeSummary['refresh_reasons'])
                : [],
            'refresh_stage' => (string) ($safeSummary['refresh_stage'] ?? ''),
            'refresh_exception_class' => (string) ($safeSummary['refresh_exception_class'] ?? ''),
            'refresh_exception_message_safe' => (string) ($safeSummary['refresh_exception_message_safe'] ?? ''),
            'fresh_search_attempted' => ($safeSummary['fresh_search_attempted'] ?? null),
            'fresh_search_result_present' => ($safeSummary['fresh_search_result_present'] ?? null),
            'match_attempted' => ($safeSummary['match_attempted'] ?? null),
            'match_found' => ($safeSummary['match_found'] ?? null),
        ];
    }
}
