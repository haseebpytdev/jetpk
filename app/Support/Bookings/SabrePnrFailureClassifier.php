<?php

namespace App\Support\Bookings;

use Illuminate\Support\Str;

/**
 * S1: Read-time classification for failed Sabre PNR attempts (no payload / retry logic changes).
 */
class SabrePnrFailureClassifier
{
    public const CLASSIFICATION_COMPLEX_DEFERRED = 'complex_itinerary_pnr_deferred';

    public const CLASSIFICATION_BOOKING_CLASS_MISMATCH = 'booking_class_mismatch';

    public const CLASSIFICATION_HOST_SELL_REJECTED_UC = 'host_sell_rejected_uc';

    public const CLASSIFICATION_HOST_SELL_PENDING_NN = 'host_sell_pending_nn';

    public const NEXT_ACTION_CHOOSE_ALTERNATE_ITINERARY = 'choose_alternate_itinerary';

    public const NEXT_ACTION_CERT_ALLOW_NN_OR_ALTERNATE_ITINERARY = 'cert_allow_nn_diagnostic_or_alternate_itinerary';

    public const NEXT_ACTION_OPERATIONAL_ALLOW_NN_RETRY = 'operational_allow_nn_retry';

    /** @var list<string> */
    public const RETRY_BLOCKERS_HOST_SELL_UC = [
        'airline_segment_status_uc',
        'halt_on_status_received',
        'choose_alternate_itinerary',
    ];

    public const CLASSIFICATION_NO_FARES_RBD_CARRIER = 'no_fares_rbd_carrier';

    /** E5F: Terminal create-time host rejection for fare/RBD/carrier combination. */
    public const CLASSIFICATION_FARE_RBD_CARRIER_NOT_SELLABLE = 'fare_rbd_carrier_not_sellable';

    public const CLASSIFICATION_STALE_OR_MISSING_INVENTORY = 'stale_or_missing_inventory';

    public const CLASSIFICATION_PROVIDER_APPLICATION_ERROR = 'provider_application_error';

    public const CLASSIFICATION_TEMPORARY_PROVIDER_ERROR = 'temporary_provider_error';

    public const CLASSIFICATION_SCHEMA_OR_PAYLOAD_VALIDATION_ERROR = 'schema_or_payload_validation_error';

    public const CLASSIFICATION_UNKNOWN_STAFF_REVIEW = 'unknown_staff_review';

    public const CLASSIFICATION_PNR_REQUIRES_MANUAL_SABRE_PRICING = 'pnr_requires_manual_sabre_pricing';

    public const CLASSIFICATION_REVALIDATION_LINKAGE_INCOMPLETE = 'revalidation_linkage_incomplete';

    public const CLASSIFICATION_UPDATED_FARE_REQUIRES_ACCEPTANCE = 'updated_fare_requires_acceptance';

    public const CLASSIFICATION_OFFER_FRESHNESS_RETRYABLE = 'offer_freshness_retryable';

    public const CLASSIFICATION_HOST_AIR_BOOKING_NOOP = 'host_air_booking_noop';

    public const CLASSIFICATION_HOST_INVENTORY_OR_CERT_LIMITATION = 'host_inventory_or_cert_limitation';

    /** E5D: Supplier PNR already exists — post-booking workflow, not a failure state. */
    public const CLASSIFICATION_SUPPLIER_PNR_BOOKED = 'supplier_pnr_booked';

    public const NEXT_ACTION_RETRY_AFTER_OFFER_REFRESH = 'retry_after_offer_refresh';

    public const NEXT_ACTION_DIAGNOSTIC_RETRY_AFTER_OFFER_REFRESH = 'diagnostic_retry_after_offer_refresh';

    public const NEXT_ACTION_MANUAL_FULFILLMENT_OR_FRESH_ITINERARY = 'manual_fulfillment_or_fresh_itinerary';

    public const NEXT_ACTION_FRESH_SEARCH_OR_MANUAL = 'fresh_search_or_manual_fulfillment';

    public const ADMIN_MESSAGE_FARE_RBD_CARRIER_NOT_SELLABLE = 'Sabre host rejected fare/RBD/carrier combination; create fresh search/changed itinerary or manual fulfillment.';

    public const ADMIN_MESSAGE_HOST_NOOP_DIAGNOSTIC = 'Host rejected air booking (FLIGHT NOOP / Unable to perform air booking step). Retry will refresh the offer and regenerate safe Passenger Records create diagnostics.';

    public const ADMIN_MESSAGE_HOST_NOOP_DIAGNOSTIC_WITH_PRIOR_SUMMARY = 'Host already rejected this flight/date. Retry only after fresh search or changed itinerary. Retry will refresh the offer and regenerate safe create diagnostics.';

    public const ADMIN_MESSAGE_HOST_NOOP_TERMINAL = 'Sabre host rejected the refreshed itinerary during air booking. Do not retry this same flight/date. Use fresh search/alternate itinerary or manual fulfillment.';

    public const RETRY_REASON_HOST_NOOP_TERMINAL = 'Sabre host rejected this refreshed flight/date. Do not retry the same itinerary. Create a fresh search/changed itinerary or fulfill manually outside the system.';

    public const ADMIN_MESSAGE_MANUAL_SABRE_PRICING = 'Shopping returned this fare, but auto-PNR pricing linkage is incomplete. Manual Sabre pricing or a different fare is required.';

    public const NEXT_ACTION_ACCEPT_UPDATED_FARE = 'accept_updated_fare';

    /**
     * @param  array<string, mixed>  $safeSummary
     * @return array{
     *     classification: string,
     *     next_action: string,
     *     retry_allowed: bool,
     *     admin_message: string,
     *     customer_message: string,
     *     retry_blocker_reasons?: list<string>
     * }
     */
    public static function classify(?string $errorCode, array $safeSummary = []): array
    {
        $errorCode = strtolower(trim((string) $errorCode));
        $messagesUpper = strtoupper(self::stringifyMessages($safeSummary));
        $probableIssue = self::resolveProbableIssue($safeSummary);

        if ($errorCode === ComplexItineraryPolicy::ERROR_CODE) {
            return self::result(
                self::CLASSIFICATION_COMPLEX_DEFERRED,
                'manual_staff_confirmation',
                false,
                ComplexItineraryPolicy::adminDeferMessage(),
            );
        }

        if ($errorCode === SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE) {
            return self::result(
                self::CLASSIFICATION_UPDATED_FARE_REQUIRES_ACCEPTANCE,
                self::NEXT_ACTION_ACCEPT_UPDATED_FARE,
                false,
                SabreOfferRefreshAcceptance::ADMIN_MESSAGE,
            );
        }

        if ($errorCode === 'sabre_passenger_records_stale_shop_segment') {
            $guardReason = strtolower(trim((string) ($safeSummary['full_itinerary_guard_reason'] ?? '')));
            if (str_contains($guardReason, 'price_changed')
                && ($safeSummary['offer_refresh_requires_acceptance'] ?? false) === true
                && ($safeSummary['offer_refresh_accepted'] ?? false) !== true) {
                return self::result(
                    self::CLASSIFICATION_UPDATED_FARE_REQUIRES_ACCEPTANCE,
                    self::NEXT_ACTION_ACCEPT_UPDATED_FARE,
                    false,
                    SabreOfferRefreshAcceptance::ADMIN_MESSAGE,
                );
            }

            if ($probableIssue === 'booking_class_mismatch' || self::safeSummaryIndicatesBookingClassMismatch($safeSummary)) {
                return self::result(
                    self::CLASSIFICATION_BOOKING_CLASS_MISMATCH,
                    'search_again_required',
                    false,
                    'Selected booking class is no longer available. Search again or choose another fare.',
                );
            }

            return self::result(
                self::CLASSIFICATION_STALE_OR_MISSING_INVENTORY,
                'search_again_required',
                false,
                'Flight or fare is no longer available in shop inventory. Search again or choose another fare.',
            );
        }

        if (in_array($probableIssue, ['flight_not_in_shop_inventory', 'no_normalized_offers'], true)) {
            return self::result(
                self::CLASSIFICATION_STALE_OR_MISSING_INVENTORY,
                'search_again_required',
                false,
                'Flight or fare is no longer available in shop inventory. Search again or choose another fare.',
            );
        }

        if ($probableIssue === 'booking_class_mismatch' || self::safeSummaryIndicatesBookingClassMismatch($safeSummary)) {
            return self::result(
                self::CLASSIFICATION_BOOKING_CLASS_MISMATCH,
                'search_again_required',
                false,
                'Selected booking class is no longer available. Search again or choose another fare.',
            );
        }

        if (self::safeSummaryIndicatesHostSellPendingNn($safeSummary, $messagesUpper)) {
            if (SabreCpnrOperationalAllowNnPolicy::isConfigEnabled()) {
                return self::result(
                    self::CLASSIFICATION_HOST_SELL_PENDING_NN,
                    self::NEXT_ACTION_OPERATIONAL_ALLOW_NN_RETRY,
                    true,
                    self::hostSellPendingNnAdminMessage($safeSummary),
                    [
                        'airline_segment_status_nn_halt',
                        'halt_on_status_received',
                    ],
                );
            }

            return self::result(
                self::CLASSIFICATION_HOST_SELL_PENDING_NN,
                self::NEXT_ACTION_CERT_ALLOW_NN_OR_ALTERNATE_ITINERARY,
                false,
                self::hostSellPendingNnAdminMessage($safeSummary),
                [
                    'airline_segment_status_nn_halt',
                    'halt_on_status_received',
                    'cert_allow_nn_diagnostic_or_alternate_itinerary',
                ],
            );
        }

        if (self::safeSummaryIndicatesHostSellRejectedUc($safeSummary, $messagesUpper)) {
            return self::result(
                self::CLASSIFICATION_HOST_SELL_REJECTED_UC,
                self::NEXT_ACTION_CHOOSE_ALTERNATE_ITINERARY,
                false,
                self::hostSellRejectAdminMessage($safeSummary),
                self::RETRY_BLOCKERS_HOST_SELL_UC,
            );
        }

        if (self::safeSummaryIndicatesRevalidationLinkageIncomplete($safeSummary)) {
            return self::result(
                self::CLASSIFICATION_REVALIDATION_LINKAGE_INCOMPLETE,
                'manual_sabre_pricing_or_alternate_fare',
                false,
                self::ADMIN_MESSAGE_MANUAL_SABRE_PRICING,
            );
        }

        if (self::safeSummaryIndicatesFareRbdCarrierHostRejection($safeSummary, $messagesUpper)
            && self::safeSummaryIndicatesCreateTimeHostRejection($safeSummary)) {
            return self::result(
                self::CLASSIFICATION_FARE_RBD_CARRIER_NOT_SELLABLE,
                self::NEXT_ACTION_FRESH_SEARCH_OR_MANUAL,
                false,
                self::ADMIN_MESSAGE_FARE_RBD_CARRIER_NOT_SELLABLE,
            );
        }

        if (self::safeSummaryIndicatesFareRbdCarrierHostRejection($safeSummary, $messagesUpper)) {
            if (! self::safeSummaryIndicatesAutoPnrPricingContextReady($safeSummary)) {
                return self::result(
                    self::CLASSIFICATION_PNR_REQUIRES_MANUAL_SABRE_PRICING,
                    'manual_sabre_pricing_or_alternate_fare',
                    false,
                    self::ADMIN_MESSAGE_MANUAL_SABRE_PRICING,
                );
            }

            return self::result(
                self::CLASSIFICATION_NO_FARES_RBD_CARRIER,
                'search_again_or_select_different_fare',
                false,
                'Sabre could not book this fare/class/carrier combination. Search again or choose another fare.',
            );
        }

        if ($errorCode === 'sabre_booking_payload_validation_failed') {
            return self::result(
                self::CLASSIFICATION_SCHEMA_OR_PAYLOAD_VALIDATION_ERROR,
                'manual_staff_confirmation',
                false,
                'Supplier booking payload validation failed — staff review required before retry.',
            );
        }

        if (self::isTemporaryProviderError($errorCode, $safeSummary, $messagesUpper)) {
            return self::result(
                self::CLASSIFICATION_TEMPORARY_PROVIDER_ERROR,
                'retry_after_cooldown',
                true,
                'Sabre is busy or temporarily unavailable — retry after the cooldown period.',
            );
        }

        $offerRefreshClassification = self::classifyControlledStaffOfferRefreshFailure($errorCode, $safeSummary);
        if ($offerRefreshClassification !== null) {
            return $offerRefreshClassification;
        }

        if (self::isControlledStaffOfferValidationRetryable($errorCode, $safeSummary)) {
            return self::result(
                self::CLASSIFICATION_OFFER_FRESHNESS_RETRYABLE,
                self::NEXT_ACTION_RETRY_AFTER_OFFER_REFRESH,
                true,
                'Retry will refresh the Sabre offer before PNR creation.',
            );
        }

        if ($errorCode === 'sabre_booking_application_error'
            && self::safeSummaryIndicatesFareRbdCarrierHostRejection($safeSummary, $messagesUpper)
            && self::safeSummaryIndicatesCreateTimeHostRejection($safeSummary)) {
            return self::result(
                self::CLASSIFICATION_FARE_RBD_CARRIER_NOT_SELLABLE,
                self::NEXT_ACTION_FRESH_SEARCH_OR_MANUAL,
                false,
                self::ADMIN_MESSAGE_FARE_RBD_CARRIER_NOT_SELLABLE,
            );
        }

        if ($errorCode === 'sabre_booking_application_error'
            && self::safeSummaryIndicatesHostAirBookingNoop($safeSummary, $messagesUpper)) {
            if (self::safeSummaryHasTerminalHostNoopCreateSummary($safeSummary)) {
                return self::result(
                    self::CLASSIFICATION_HOST_INVENTORY_OR_CERT_LIMITATION,
                    self::NEXT_ACTION_MANUAL_FULFILLMENT_OR_FRESH_ITINERARY,
                    false,
                    self::ADMIN_MESSAGE_HOST_NOOP_TERMINAL,
                );
            }

            return self::result(
                self::CLASSIFICATION_HOST_AIR_BOOKING_NOOP,
                self::NEXT_ACTION_DIAGNOSTIC_RETRY_AFTER_OFFER_REFRESH,
                false,
                self::hostAirBookingNoopAdminMessage($safeSummary),
            );
        }

        if ($errorCode === 'sabre_booking_application_error') {
            return self::result(
                self::CLASSIFICATION_PROVIDER_APPLICATION_ERROR,
                'manual_staff_confirmation',
                false,
                'Supplier booking failed — staff review required.',
            );
        }

        if ($errorCode !== '') {
            return self::result(
                self::CLASSIFICATION_UNKNOWN_STAFF_REVIEW,
                'manual_staff_confirmation',
                false,
                'Supplier PNR failed — staff review required.',
            );
        }

        return self::result(
            self::CLASSIFICATION_UNKNOWN_STAFF_REVIEW,
            'manual_staff_confirmation',
            false,
            '',
        );
    }

    /**
     * Controlled admin/staff PNR may retry after stale-offer validation failures when readiness still permits live create.
     *
     * Uses top-level attempt {@code error_code} and redacted {@code safe_summary} keys
     * ({@code error_code}, {@code reason_code}, {@code reason}, {@code create_status}).
     *
     * @param  array<string, mixed>  $safeSummary
     */
    /**
     * E3B: Controlled admin/staff diagnostic retry after host NOOP / air-booking-step / Incomplete without PNR.
     *
     * @param  array<string, mixed>  $safeSummary
     */
    public static function isControlledStaffHostNoopDiagnosticRetryable(?string $errorCode, array $safeSummary = []): bool
    {
        $errorCode = strtolower(trim((string) $errorCode));

        if (self::safeSummaryIndicatesFareRbdCarrierHostRejection($safeSummary)
            && self::safeSummaryIndicatesCreateTimeHostRejection($safeSummary)) {
            return false;
        }

        return $errorCode === 'sabre_booking_application_error'
            && self::safeSummaryIndicatesHostAirBookingNoop($safeSummary)
            && ! self::safeSummaryHasTerminalHostNoopCreateSummary($safeSummary);
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    public static function safeSummaryHasTerminalHostNoopCreateSummary(array $safeSummary): bool
    {
        if (self::safeSummaryIndicatesPnrPresent($safeSummary)) {
            return false;
        }

        if (! array_key_exists('create_segment_count', $safeSummary)
            || ! array_key_exists('create_segment_source', $safeSummary)
            || ! array_key_exists('create_segments_summary', $safeSummary)) {
            return false;
        }

        $segmentCount = $safeSummary['create_segment_count'];
        $segmentSource = trim((string) $safeSummary['create_segment_source']);
        $segmentSummary = $safeSummary['create_segments_summary'];

        return is_numeric($segmentCount)
            && (int) $segmentCount > 0
            && $segmentSource !== ''
            && is_array($segmentSummary)
            && $segmentSummary !== [];
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    protected static function safeSummaryIndicatesPnrPresent(array $safeSummary): bool
    {
        foreach (['pnr', 'supplier_reference', 'supplier_api_booking_id'] as $key) {
            if (trim((string) ($safeSummary[$key] ?? '')) !== '') {
                return true;
            }
        }

        return ($safeSummary['pnr_present'] ?? false) === true
            || ($safeSummary['supplier_reference_present'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    public static function safeSummaryIndicatesHostAirBookingNoop(array $safeSummary, string $messagesUpper = ''): bool
    {
        if ($messagesUpper === '') {
            $messagesUpper = strtoupper(self::stringifyMessages($safeSummary));
        }

        if (Str::contains($messagesUpper, [
            'FLIGHT NOOP FOR THIS FLIGHT',
            'FLIGHT NOOP',
            'UNABLE TO PERFORM AIR BOOKING STEP',
        ])) {
            return true;
        }

        $codes = array_map(
            static fn ($code) => strtoupper(trim((string) $code)),
            (array) ($safeSummary['response_error_codes'] ?? []),
        );

        if (in_array('0118', $codes, true)
            && (Str::contains($messagesUpper, ['ENHANCEDAIRBOOK', 'NOOP'])
                || Str::contains($messagesUpper, 'SYSTEM UNABLE TO PROCESS'))) {
            return true;
        }

        if (in_array('ERR.SP.PROVIDER_ERROR', $codes, true)
            && in_array('WARN.SWS.HOST.ERROR_IN_RESPONSE', $codes, true)
            && (Str::contains($messagesUpper, ['ENHANCEDAIRBOOK', 'NOOP'])
                || Str::contains($messagesUpper, 'UNABLE TO PERFORM AIR BOOKING STEP'))) {
            return true;
        }

        $appStatus = strtolower(trim((string) ($safeSummary['application_results_status'] ?? '')));
        if (in_array($appStatus, ['incomplete', 'notprocessed'], true)) {
            return true;
        }

        return ($safeSummary['application_results_incomplete'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    public static function hostAirBookingNoopAdminMessage(array $safeSummary): string
    {
        if (! empty($safeSummary['create_segments_summary'])) {
            return self::ADMIN_MESSAGE_HOST_NOOP_DIAGNOSTIC_WITH_PRIOR_SUMMARY;
        }

        return self::ADMIN_MESSAGE_HOST_NOOP_DIAGNOSTIC;
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    public static function hostNoopDiagnosticRetryHelper(array $safeSummary): string
    {
        if (! empty($safeSummary['create_segments_summary'])) {
            return 'Host already rejected this flight/date. Retry only after fresh search or changed itinerary. Retry will refresh the offer and regenerate safe Passenger Records create diagnostics.';
        }

        return 'Retry will refresh the offer and regenerate safe Passenger Records create diagnostics.';
    }

    public static function isControlledStaffOfferValidationRetryable(?string $errorCode, array $safeSummary = []): bool
    {
        $recommended = strtolower(trim((string) ($safeSummary['recommended_staff_action'] ?? '')));
        if (in_array($recommended, [
            ControlledStaffOfferRefreshDiagnostics::ACTION_FRESH_SEARCH,
            ControlledStaffOfferRefreshDiagnostics::ACTION_FARE_ACCEPTANCE,
        ], true)) {
            return false;
        }

        if (($safeSummary['refresh_available'] ?? null) === false
            && ($safeSummary['refresh_attempted'] ?? false) === true) {
            return false;
        }

        $signals = self::collectOfferValidationSignals($errorCode, $safeSummary);

        $retryableErrorCodes = [
            'sabre_offer_validation_failed',
            'sabre_offer_freshness_blocked',
            'offer_stale_before_checkout',
            'offer_validation_required',
            'selected_offer_revalidation_required',
            'offer_refresh_unavailable',
            'offer_refresh_failed',
        ];

        $retryableReasonCodes = [
            'offer_stale_before_checkout',
            'offer_validation_required',
            'selected_offer_revalidation_required',
            'selected_offer_revalidation_failed',
            'high_risk_cached_offer',
            'offer_refresh_unavailable',
            'offer_refresh_failed',
        ];

        foreach ($signals['error_codes'] as $code) {
            if (in_array($code, $retryableErrorCodes, true)) {
                return true;
            }
        }

        foreach ($signals['reason_codes'] as $code) {
            if (in_array($code, $retryableReasonCodes, true)) {
                return true;
            }
        }

        return $signals['create_status'] === 'validation_failed';
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     * @return array{
     *     classification: string,
     *     next_action: string,
     *     retry_allowed: bool,
     *     admin_message: string,
     *     customer_message: string
     * }|null
     */
    protected static function classifyControlledStaffOfferRefreshFailure(?string $errorCode, array $safeSummary): ?array
    {
        $errorCode = strtolower(trim((string) $errorCode));
        $recommended = strtolower(trim((string) ($safeSummary['recommended_staff_action'] ?? '')));
        $refreshAttempted = ($safeSummary['refresh_attempted'] ?? false) === true;

        if ($recommended === '' && ! $refreshAttempted) {
            return null;
        }

        $offerRefreshSignals = array_filter([
            $errorCode,
            strtolower(trim((string) ($safeSummary['reason_code'] ?? ''))),
            strtolower(trim((string) ($safeSummary['reason'] ?? ''))),
        ]);

        $isOfferRefreshFailure = $refreshAttempted
            || array_intersect($offerRefreshSignals, [
                'offer_refresh_failed',
                'offer_refresh_unavailable',
                'offer_validation_required',
            ]) !== [];

        if (! $isOfferRefreshFailure && $recommended === '') {
            return null;
        }

        $diagnostics = app(ControlledStaffOfferRefreshDiagnostics::class);
        $adminMessage = trim((string) ($safeSummary['refresh_message'] ?? ''));
        if ($adminMessage === '' && $recommended !== '') {
            $adminMessage = $diagnostics->adminMessageForAction(
                $recommended,
                (string) ($safeSummary['refresh_reason_code'] ?? ''),
            );
        }

        return match ($recommended) {
            ControlledStaffOfferRefreshDiagnostics::ACTION_FRESH_SEARCH => self::result(
                self::CLASSIFICATION_STALE_OR_MISSING_INVENTORY,
                'search_again_required',
                false,
                $adminMessage !== '' ? $adminMessage : 'Offer refresh failed. Create a fresh search/booking or rebuild supplier context if available.',
            ),
            ControlledStaffOfferRefreshDiagnostics::ACTION_FARE_ACCEPTANCE => self::result(
                self::CLASSIFICATION_UPDATED_FARE_REQUIRES_ACCEPTANCE,
                self::NEXT_ACTION_ACCEPT_UPDATED_FARE,
                false,
                $adminMessage !== '' ? $adminMessage : SabreOfferRefreshAcceptance::ADMIN_MESSAGE,
            ),
            ControlledStaffOfferRefreshDiagnostics::ACTION_RETRY_AFTER_COOLDOWN => self::result(
                self::CLASSIFICATION_TEMPORARY_PROVIDER_ERROR,
                'retry_after_cooldown',
                true,
                $adminMessage !== '' ? $adminMessage : 'Offer refresh failed due to a temporary supplier issue. Wait a few minutes, then retry PNR creation.',
            ),
            ControlledStaffOfferRefreshDiagnostics::ACTION_REBUILD_CONTEXT => self::result(
                self::CLASSIFICATION_REVALIDATION_LINKAGE_INCOMPLETE,
                'rebuild_supplier_context',
                false,
                $adminMessage !== '' ? $adminMessage : 'Offer context is stale. Use Prepare supplier PNR context when available, then retry.',
            ),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     * @return array{error_codes: list<string>, reason_codes: list<string>, create_status: string}
     */
    protected static function collectOfferValidationSignals(?string $errorCode, array $safeSummary): array
    {
        $errorCodes = [];
        $reasonCodes = [];

        $top = strtolower(trim((string) $errorCode));
        if ($top !== '') {
            $errorCodes[] = $top;
        }

        $summaryError = strtolower(trim((string) ($safeSummary['error_code'] ?? '')));
        if ($summaryError !== '') {
            $errorCodes[] = $summaryError;
        }

        foreach (['reason_code', 'reason', 'prior_error_code'] as $key) {
            $val = strtolower(trim((string) ($safeSummary[$key] ?? '')));
            if ($val === '') {
                continue;
            }
            $reasonCodes[] = $val;
            $errorCodes[] = $val;
        }

        return [
            'error_codes' => array_values(array_unique($errorCodes)),
            'reason_codes' => array_values(array_unique($reasonCodes)),
            'create_status' => strtolower(trim((string) ($safeSummary['create_status'] ?? ''))),
        ];
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    public static function safeSummaryIndicatesFareRbdCarrierHostRejection(array $safeSummary, string $messagesUpper = ''): bool
    {
        if ($messagesUpper === '') {
            $messagesUpper = strtoupper(self::stringifyMessages($safeSummary));
        }

        if (Str::contains($messagesUpper, ['NO FARES/RBD/CARRIER', '*NO FARES/RBD/CARRIER'])) {
            return true;
        }

        if (Str::contains($messagesUpper, 'NO FARES')
            && (Str::contains($messagesUpper, ['RBD', 'CARRIER', 'ENHANCEDAIRBOOK'])
                || Str::contains($messagesUpper, 'UNABLE TO PERFORM AIR BOOKING STEP'))) {
            return true;
        }

        return Str::contains($messagesUpper, 'UNABLE TO PERFORM AIR BOOKING STEP')
            && Str::contains($messagesUpper, ['NO FARES', 'RBD', 'CARRIER']);
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    public static function safeSummaryIndicatesCreateTimeHostRejection(array $safeSummary): bool
    {
        if (($safeSummary['create_air_price_present'] ?? false) === true) {
            return true;
        }

        if (array_key_exists('create_segment_count', $safeSummary)
            && is_numeric($safeSummary['create_segment_count'])
            && (int) $safeSummary['create_segment_count'] > 0) {
            return true;
        }

        $segmentSummary = $safeSummary['create_segments_summary'] ?? null;

        return is_array($segmentSummary) && $segmentSummary !== [];
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    public static function safeSummaryIndicatesAutoPnrPricingContextReady(array $safeSummary): bool
    {
        if (array_key_exists('auto_pnr_pricing_context_ready', $safeSummary)) {
            return $safeSummary['auto_pnr_pricing_context_ready'] === true;
        }

        if (array_key_exists('pricing_context_ready', $safeSummary)) {
            return $safeSummary['pricing_context_ready'] === true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    public static function safeSummaryIndicatesRevalidationLinkageIncomplete(array $safeSummary): bool
    {
        if (($safeSummary['includes_sabre_error_27131'] ?? false) === true
            && ! self::safeSummaryIndicatesAutoPnrPricingContextReady($safeSummary)) {
            return true;
        }

        $probable = strtolower(trim((string) ($safeSummary['probable_issue'] ?? '')));
        if (in_array($probable, [
            'revalidation_linkage_incomplete',
            'revalidation_linkage_missing_per_segment_class',
        ], true)) {
            return true;
        }

        return ($safeSummary['revalidation_linkage_incomplete'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    public static function safeSummaryIndicatesBookingClassMismatch(array $safeSummary): bool
    {
        if (array_key_exists('fresh_same_rbd_found', $safeSummary) && $safeSummary['fresh_same_rbd_found'] === false) {
            return true;
        }

        foreach (self::segmentDiagnosticRows($safeSummary) as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['fresh_same_rbd_found'] ?? null) === false) {
                return true;
            }
            if (($row['probable_issue'] ?? '') === 'booking_class_mismatch') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    protected static function resolveProbableIssue(array $safeSummary): string
    {
        $top = strtolower(trim((string) ($safeSummary['probable_issue'] ?? '')));
        if ($top !== '') {
            return $top;
        }

        foreach (self::segmentDiagnosticRows($safeSummary) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $issue = strtolower(trim((string) ($row['probable_issue'] ?? '')));
            if ($issue !== '' && $issue !== 'ok') {
                return $issue;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     * @return list<mixed>
     */
    protected static function segmentDiagnosticRows(array $safeSummary): array
    {
        foreach (['segment_diagnostics', 'segments', 'segment_reports'] as $key) {
            $rows = $safeSummary[$key] ?? null;
            if (is_array($rows) && $rows !== []) {
                return array_values($rows);
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    protected static function isTemporaryProviderError(string $errorCode, array $safeSummary, string $messagesUpper): bool
    {
        $httpStatus = isset($safeSummary['http_status']) ? (int) $safeSummary['http_status'] : null;

        if (in_array($errorCode, ['sabre_booking_connection_error', 'transport_timeout', 'sabre_timeout'], true)) {
            return true;
        }

        if (str_contains($errorCode, 'timeout')) {
            return true;
        }

        if ($httpStatus === 429 || Str::contains($messagesUpper, 'TOO MANY REQUESTS')) {
            return true;
        }

        if ($httpStatus !== null && $httpStatus >= 500) {
            return true;
        }

        return in_array($errorCode, ['sabre_booking_http_failed'], true)
            && $httpStatus !== null
            && $httpStatus >= 500;
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    protected static function stringifyMessages(array $safeSummary): string
    {
        $parts = [];
        foreach (['response_error_messages', 'application_error_messages', 'messages', 'message'] as $key) {
            $val = $safeSummary[$key] ?? null;
            if (is_string($val)) {
                $parts[] = $val;
            } elseif (is_array($val)) {
                foreach ($val as $item) {
                    if (is_scalar($item)) {
                        $parts[] = (string) $item;
                    }
                }
            }
        }

        return implode(' ', $parts);
    }

    /**
     * BF7-J-OPS-FIX3: Prior live Passenger Records NN + HaltOnStatus failure (structured or message-only safe_summary).
     *
     * @param  array<string, mixed>  $safeSummary
     */
    public static function safeSummaryIndicatesPriorNnHaltOnStatusFailure(array $safeSummary, string $messagesUpper = ''): bool
    {
        if (strtolower(trim((string) ($safeSummary['reason_code'] ?? ''))) === 'sabre_passenger_records_halt_on_status_nn') {
            return true;
        }

        if (($safeSummary['probable_issue'] ?? '') === 'airline_segment_status_nn_halt') {
            return true;
        }

        if ($messagesUpper === '') {
            $messagesUpper = strtoupper(self::stringifyMessages($safeSummary));
        }

        $status = strtoupper(trim((string) ($safeSummary['airline_segment_status'] ?? '')));
        if ($status === 'NN' && ($safeSummary['halt_on_status_received'] ?? false) === true) {
            return true;
        }

        $haltOnStatus = ($safeSummary['halt_on_status_received'] ?? false) === true
            || Str::contains($messagesUpper, ['HALT_ON_STATUS_RECEIVED', 'HALT_ON_STATUS RECEIVED', 'SPECIFIED HALTONSTATUS RECEIVED']);

        if (! $haltOnStatus) {
            return false;
        }

        if ($status === 'NN') {
            return true;
        }

        if (Str::contains($messagesUpper, 'STATUS CODE NN')) {
            return true;
        }

        if (preg_match_all('/\b(?:Flight|Segment)\s+([A-Z]{2}\d{1,4})\s+returned\s+status\s+code\s+NN\b/i', $messagesUpper) > 0) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    public static function safeSummaryIndicatesHostSellPendingNn(array $safeSummary, string $messagesUpper = ''): bool
    {
        return self::safeSummaryIndicatesPriorNnHaltOnStatusFailure($safeSummary, $messagesUpper);
    }

    public static function safeSummaryIndicatesHostSellRejectedUc(array $safeSummary, string $messagesUpper = ''): bool
    {
        if (self::safeSummaryIndicatesHostSellPendingNn($safeSummary, $messagesUpper)) {
            return false;
        }
        if (strtoupper(trim((string) ($safeSummary['airline_segment_status'] ?? ''))) === 'UC') {
            return true;
        }
        if (($safeSummary['halt_on_status_received'] ?? false) === true) {
            return true;
        }
        if (($safeSummary['probable_issue'] ?? '') === 'airline_segment_status_uc') {
            return true;
        }

        if ($messagesUpper === '') {
            $messagesUpper = strtoupper(self::stringifyMessages($safeSummary));
        }

        return Str::contains($messagesUpper, ['RETURNED STATUS CODE UC', 'HALT_ON_STATUS_RECEIVED', 'HALT_ON_STATUS RECEIVED']);
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    public static function hostSellPendingNnAdminMessage(array $safeSummary): string
    {
        $flights = [];
        foreach ((array) ($safeSummary['affected_flight_numbers'] ?? []) as $flight) {
            if (is_scalar($flight) && trim((string) $flight) !== '') {
                $flights[] = strtoupper(trim((string) $flight));
            }
        }
        $flights = array_values(array_unique(array_slice($flights, 0, 8)));

        $base = 'Airline returned pending segment status NN (sell requested, not confirmed HK).';
        if ($flights !== []) {
            $base .= ' Affected flights: '.implode(', ', $flights).'.';
        }
        $base .= ' For CERT, retry with allow-NN diagnostic or choose another itinerary — do not retry the same offer without a strategy change.';
        if (SabreCpnrOperationalAllowNnPolicy::isConfigEnabled()) {
            $base .= ' Operational allow-NN is enabled — retry may omit NN/WN from HaltOnStatus when gates pass.';
        }

        return $base;
    }

    public static function hostSellRejectAdminMessage(array $safeSummary): string
    {
        $status = strtoupper(trim((string) ($safeSummary['airline_segment_status'] ?? 'UC')));
        if ($status === '') {
            $status = 'UC';
        }
        $flights = [];
        foreach ((array) ($safeSummary['affected_flight_numbers'] ?? []) as $flight) {
            if (is_scalar($flight) && trim((string) $flight) !== '') {
                $flights[] = strtoupper(trim((string) $flight));
            }
        }
        if ($flights === []) {
            $messagesUpper = strtoupper(self::stringifyMessages($safeSummary));
            if (preg_match_all('/\b(?:Flight|Segment)\s+([A-Z]{2}\d{1,4})\s+returned\s+status\s+code/i', $messagesUpper, $mm)) {
                foreach ($mm[1] as $hit) {
                    $tok = strtoupper(trim((string) $hit));
                    if ($tok !== '') {
                        $flights[] = $tok;
                    }
                }
            }
        }
        $flights = array_values(array_unique(array_slice($flights, 0, 8)));

        $base = 'Airline did not confirm/sell segments (status '.$status.').';
        if ($flights !== []) {
            $base .= ' Affected flights: '.implode(', ', $flights).'.';
        }
        $base .= ' Choose another itinerary or run a fresh search — do not retry the same offer.';

        return $base;
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     * @return array{
     *     classification: string,
     *     next_action: string,
     *     retry_allowed: bool,
     *     admin_message: string,
     *     customer_message: string,
     *     retry_blocker_reasons?: list<string>
     * }
     */
    protected static function result(
        string $classification,
        string $nextAction,
        bool $retryAllowed,
        string $adminMessage,
        array $retryBlockerReasons = [],
    ): array {
        $out = [
            'classification' => $classification,
            'next_action' => $nextAction,
            'retry_allowed' => $retryAllowed,
            'admin_message' => $adminMessage,
            'customer_message' => 'Your booking request has been received. Staff will review and confirm availability.',
        ];
        if ($retryBlockerReasons !== []) {
            $out['retry_blocker_reasons'] = array_values(array_slice($retryBlockerReasons, 0, 8));
        }

        return $out;
    }
}
