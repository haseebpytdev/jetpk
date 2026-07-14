<?php

namespace App\Support\Bookings;

use Illuminate\Support\Str;

/**
 * D2F-B / 11K-D / 11K-H: Structured admin-safe Sabre host error classification from diagnostic context only.
 * {@see buildPersistedSlice()} produces the booking-meta persistence shape under
 * {@code meta.sabre_checkout_outcome.sabre_host_classification} on live PNR failures (diagnostic only).
 */
class SabreHostErrorClassifier
{
    public const CLASSIFIER_VERSION = 'sabre_host_classifier_v1';

    public const HOST_ERROR_FAMILY_UC_SEGMENT_STATUS = 'UC_SEGMENT_STATUS';

    public const HOST_ERROR_FAMILY_HOST_SEGMENT_STATUS = 'HOST_SEGMENT_STATUS';

    public const HOST_ERROR_FAMILY_NO_FARES_RBD_CARRIER = 'NO_FARES_RBD_CARRIER';

    public const HOST_ERROR_FAMILY_UNKNOWN = 'UNKNOWN_HOST_ERROR';

    public const HOST_ERROR_FAMILY_ENHANCED_AIRBOOK_FORMAT = 'ENHANCED_AIRBOOK_FORMAT';

    public const REASON_ENHANCED_AIRBOOK_FORMAT = 'sabre_enhanced_airbook_format_error';

    public const RETRY_ADMIN_CONFIRMED_FALLBACK_ONLY = 'admin_confirmed_fallback_only';

    public const REASON_HOST_SELL_REJECTED_UC = 'host_sell_rejected_uc';

    public const REASON_HOST_SEGMENT_STATUS_UNCONFIRMED = 'host_segment_status_unconfirmed';

    public const REASON_INVENTORY_UNAVAILABLE = 'inventory_unavailable_or_unable_to_confirm';

    public const REASON_NO_FARES_RBD_CARRIER = 'no_fares_rbd_carrier';

    public const REASON_AIRPRICE_FAILED = 'airprice_failed';

    public const REASON_ENTITLEMENT_OR_SECURITY = 'entitlement_or_security_error';

    public const REASON_SUPPLIER_TIMEOUT_OR_TRANSPORT = 'supplier_timeout_or_transport_error';

    public const REASON_UNKNOWN = 'unknown_sabre_host_error';

    public const REASON_APPLICATION_INCOMPLETE_NO_LOCATOR = 'sabre_application_incomplete_no_locator';

    public const REASON_SEGMENT_SELL_UNAVAILABLE = 'segment_sell_unavailable';

    public const REASON_MIXED_INTERLINE_NOT_BOOKABLE = 'mixed_interline_not_bookable';

    public const REASON_FARE_PRICING_QUALIFIER_REJECTED = 'fare_pricing_qualifier_rejected';

    public const REASON_COMMANDPRICING_SEGMENTSELECT_PAIRING_REQUIRED = 'commandpricing_segmentselect_pairing_required';

    public const REASON_BRAND_SEGMENTSELECT_PAIRING_REQUIRED = 'brand_segmentselect_pairing_required';

    public const REASON_BRAND_RPH_SCHEMA_INVALID = 'brand_rph_schema_invalid';

    public const HOST_ERROR_FAMILY_APPLICATION_INCOMPLETE = 'APPLICATION_INCOMPLETE_NO_LOCATOR';

    public const HOST_ERROR_FAMILY_MIXED_INTERLINE = 'MIXED_INTERLINE_NOT_BOOKABLE';

    public const HOST_ERROR_FAMILY_FARE_PRICING_QUALIFIER = 'FARE_PRICING_QUALIFIER_REJECTED';

    public const HOST_ERROR_FAMILY_SEGMENT_SELL_UNAVAILABLE = 'SEGMENT_SELL_UNAVAILABLE';

    public const RETRY_NO_RETRY_SAME_OFFER = 'no_retry_same_offer';

    public const RETRY_NO_RETRY_UNTIL_CREDENTIALS_OR_PCC = 'no_retry_until_credentials_or_pcc_checked';

    public const RETRY_ONLY_AFTER_OPERATOR_REVIEW = 'retry_only_after_operator_review_or_idempotency_check';

    public const RETRY_NO_AUTO_RETRY = 'no_auto_retry';

    public const LAYER_AIRBOOK_SELL = 'airbook_sell';

    public const LAYER_AIRPRICE = 'airprice';

    public const LAYER_ENTITLEMENT = 'entitlement';

    public const LAYER_HTTP_TRANSPORT = 'http_transport';

    public const LAYER_PASSENGER_RECORDS_HOST = 'passenger_records_host';

    public const LAYER_UNKNOWN = 'unknown';

    /** @var list<string> */
    private const TRANSPORT_HTTP_STATUSES = [408, 429, 500, 502, 503, 504];

    /** @var list<string> */
    private const HOST_SEGMENT_STATUS_MESSAGE_NEEDLES = [
        'HALT_ON_STATUS_RECEIVED',
        'HALT_ON_STATUS RECEIVED',
        'RETURNED STATUS CODE NN',
        'STATUS CODE NN',
        'SEGMENT STATUS NN',
        'PENDING SEGMENT STATUS NN',
        'SPECIFIED HALTONSTATUS RECEIVED',
    ];

    /** @var list<string> */
    private const FORBIDDEN_OUTPUT_SUBSTRINGS = [
        'createpassengernamerecordrq',
        'passengername',
        'formofpayment',
        'telephone',
        'targetcity',
    ];

    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *     safe_reason_code: string,
     *     safe_summary: string,
     *     recommended_admin_action: string,
     *     retry_policy: string,
     *     manual_review_required: bool,
     *     source_layer: string,
     *     matched_signals: list<string>
     * }
     */
    /**
     * @param  array<string, mixed>  $context  Whitelisted diagnostic context (no raw payload / PII keys)
     * @param  array<string, mixed>  $resultSlice  Optional safe booking-result fields (counts, schema flags)
     * @return array<string, mixed>
     */
    public static function buildPersistedSlice(array $context, array $resultSlice = []): array
    {
        $classified = self::classify($context);
        $safeReason = strtolower(trim((string) ($classified['safe_reason_code'] ?? '')));

        $slice = [
            'safe_reason_code' => $classified['safe_reason_code'],
            'source_layer' => $classified['source_layer'],
            'host_error_family' => self::hostErrorFamilyForReason($safeReason),
            'retry_policy' => $classified['retry_policy'],
            'admin_summary' => $classified['recommended_admin_action'],
            'safe_summary' => $classified['safe_summary'],
            'recommended_admin_action' => $classified['recommended_admin_action'],
            'manual_review_required' => $classified['manual_review_required'],
            'matched_signals' => $classified['matched_signals'],
            'classifier_version' => self::CLASSIFIER_VERSION,
            'recorded_at' => now()->toIso8601String(),
        ];

        foreach (['live_call_attempted', 'booking_schema', 'payload_schema', 'segment_count', 'passenger_count'] as $key) {
            if (! array_key_exists($key, $resultSlice)) {
                continue;
            }
            $value = $resultSlice[$key];
            if ($value === null || $value === '') {
                continue;
            }
            $slice[$key] = $value;
        }

        return $slice;
    }

    public static function hostErrorFamilyForReason(string $safeReasonCode): ?string
    {
        return match (strtolower(trim($safeReasonCode))) {
            self::REASON_HOST_SELL_REJECTED_UC,
            self::REASON_INVENTORY_UNAVAILABLE => self::HOST_ERROR_FAMILY_UC_SEGMENT_STATUS,
            self::REASON_HOST_SEGMENT_STATUS_UNCONFIRMED => self::HOST_ERROR_FAMILY_HOST_SEGMENT_STATUS,
            self::REASON_NO_FARES_RBD_CARRIER,
            self::REASON_AIRPRICE_FAILED => self::HOST_ERROR_FAMILY_NO_FARES_RBD_CARRIER,
            self::REASON_ENHANCED_AIRBOOK_FORMAT => self::HOST_ERROR_FAMILY_ENHANCED_AIRBOOK_FORMAT,
            self::REASON_APPLICATION_INCOMPLETE_NO_LOCATOR => self::HOST_ERROR_FAMILY_APPLICATION_INCOMPLETE,
            self::REASON_SEGMENT_SELL_UNAVAILABLE => self::HOST_ERROR_FAMILY_SEGMENT_SELL_UNAVAILABLE,
            self::REASON_MIXED_INTERLINE_NOT_BOOKABLE => self::HOST_ERROR_FAMILY_MIXED_INTERLINE,
            self::REASON_FARE_PRICING_QUALIFIER_REJECTED => self::HOST_ERROR_FAMILY_FARE_PRICING_QUALIFIER,
            self::REASON_COMMANDPRICING_SEGMENTSELECT_PAIRING_REQUIRED => self::HOST_ERROR_FAMILY_FARE_PRICING_QUALIFIER,
            self::REASON_BRAND_SEGMENTSELECT_PAIRING_REQUIRED => self::HOST_ERROR_FAMILY_FARE_PRICING_QUALIFIER,
            self::REASON_BRAND_RPH_SCHEMA_INVALID => self::HOST_ERROR_FAMILY_FARE_PRICING_QUALIFIER,
            self::REASON_UNKNOWN => self::HOST_ERROR_FAMILY_UNKNOWN,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *     safe_reason_code: string,
     *     safe_summary: string,
     *     recommended_admin_action: string,
     *     retry_policy: string,
     *     manual_review_required: bool,
     *     source_layer: string,
     *     matched_signals: list<string>
     * }
     */
    public static function classify(array $context): array
    {
        $messagesUpper = strtoupper(self::stringifyMessages($context));
        $safeSummaryUpper = strtoupper(self::stringifySafeSummaryField($context));
        $combinedUpper = trim($messagesUpper.' '.$safeSummaryUpper);
        $errorCode = strtolower(trim((string) ($context['error_code'] ?? '')));
        $httpStatus = isset($context['http_status']) ? (int) $context['http_status'] : null;
        $segmentStatus = strtoupper(trim((string) ($context['airline_segment_status'] ?? '')));

        if (self::indicatesUcSegmentFailure($context, $messagesUpper, $segmentStatus)) {
            return self::result(
                self::REASON_HOST_SELL_REJECTED_UC,
                'Sabre could not confirm one or more requested flight segments.',
                'Re-shop/revalidate this itinerary and review availability before retrying.',
                self::RETRY_NO_RETRY_SAME_OFFER,
                self::LAYER_AIRBOOK_SELL,
                self::buildUcSignals($context, $messagesUpper, $segmentStatus),
            );
        }

        if (self::indicatesHostSegmentStatusUnconfirmed($context, $messagesUpper, $combinedUpper, $segmentStatus)) {
            return self::result(
                self::REASON_HOST_SEGMENT_STATUS_UNCONFIRMED,
                'We could not confirm this fare with the airline. Please choose another option or refresh your search.',
                'Sabre returned an unconfirmed/pending segment status during booking. Re-shop/revalidate and choose a fresh confirmable itinerary before retrying.',
                self::RETRY_NO_RETRY_SAME_OFFER,
                self::LAYER_AIRBOOK_SELL,
                self::buildHostSegmentStatusSignals($context, $messagesUpper, $combinedUpper, $segmentStatus),
            );
        }

        if (self::indicatesInventoryUnavailable($context, $messagesUpper, $segmentStatus)) {
            return self::result(
                self::REASON_INVENTORY_UNAVAILABLE,
                'Sabre could not confirm one or more requested flight segments.',
                'Re-shop/revalidate this itinerary and review availability before retrying.',
                self::RETRY_NO_RETRY_SAME_OFFER,
                self::LAYER_AIRBOOK_SELL,
                self::buildInventoryUnavailableSignals($context, $messagesUpper, $segmentStatus),
            );
        }

        if (Str::contains($combinedUpper, 'ENHANCEDAIRBOOKRQ') && Str::contains($combinedUpper, 'FORMAT')) {
            return self::result(
                self::REASON_ENHANCED_AIRBOOK_FORMAT,
                'Sabre rejected the Passenger Records AirBook payload format.',
                'Review strategy digest and retry with eligible fallback strategy only after operator confirmation.',
                self::RETRY_ADMIN_CONFIRMED_FALLBACK_ONLY,
                self::LAYER_AIRBOOK_SELL,
                self::sanitizeSignals(['message_contains:enhanced_airbook_format']),
            );
        }

        if (Str::contains($combinedUpper, 'NO FARES/RBD/CARRIER')) {
            return self::result(
                self::REASON_NO_FARES_RBD_CARRIER,
                'Sabre could not price the requested RBD/carrier/fare combination.',
                'Re-shop/revalidate and choose a fresh priced itinerary before retrying.',
                self::RETRY_NO_RETRY_SAME_OFFER,
                self::LAYER_AIRPRICE,
                self::sanitizeSignals(['message_contains:no_fares_rbd_carrier']),
            );
        }

        if (Str::contains($combinedUpper, 'NO FARES')) {
            return self::result(
                self::REASON_AIRPRICE_FAILED,
                'Sabre could not price the requested itinerary.',
                'Re-shop/revalidate and choose a fresh priced itinerary before retrying.',
                self::RETRY_NO_RETRY_SAME_OFFER,
                self::LAYER_AIRPRICE,
                self::sanitizeSignals(['message_contains:no_fares']),
            );
        }

        if (self::indicatesEntitlementOrSecurityError($context, $messagesUpper, $errorCode, $httpStatus)) {
            return self::result(
                self::REASON_ENTITLEMENT_OR_SECURITY,
                'Sabre rejected the request due to entitlement or security configuration.',
                'Verify PCC, credentials, and Sabre entitlement configuration before retrying.',
                self::RETRY_NO_RETRY_UNTIL_CREDENTIALS_OR_PCC,
                self::LAYER_ENTITLEMENT,
                self::buildEntitlementSignals($context, $messagesUpper, $errorCode, $httpStatus),
            );
        }

        if (self::indicatesTransportOrTimeoutError($context, $messagesUpper, $errorCode, $httpStatus)) {
            return self::result(
                self::REASON_SUPPLIER_TIMEOUT_OR_TRANSPORT,
                'Sabre transport or temporary supplier communication failed.',
                'Review supplier connectivity and idempotency before any retry.',
                self::RETRY_ONLY_AFTER_OPERATOR_REVIEW,
                self::LAYER_HTTP_TRANSPORT,
                self::buildTransportSignals($context, $messagesUpper, $errorCode, $httpStatus),
            );
        }

        if (self::indicatesBrandRphSchemaInvalid($combinedUpper)) {
            return self::result(
                self::REASON_BRAND_RPH_SCHEMA_INVALID,
                'Sabre rejected Brand RPH schema/type on Passenger Records v2.4 AirPrice.',
                'Fix v2.4 Brand RPH schema/type before retry.',
                self::RETRY_NO_AUTO_RETRY,
                self::LAYER_AIRPRICE,
                self::sanitizeSignals(['message_contains:brand_rph_schema_invalid']),
            );
        }

        if (self::indicatesBrandSegmentSelectPairingRequired($combinedUpper)) {
            return self::result(
                self::REASON_BRAND_SEGMENTSELECT_PAIRING_REQUIRED,
                'Sabre requires Brand RPH alignment when SegmentSelect is present on EnhancedAirBookRQ.',
                'Fix v2.4 Brand/SegmentSelect RPH pairing or omit Brand safely for mixed v2.4 create before retry.',
                self::RETRY_NO_AUTO_RETRY,
                self::LAYER_AIRPRICE,
                self::sanitizeSignals(['message_contains:brand_segmentselect_pairing_required']),
            );
        }

        if (self::indicatesCommandPricingSegmentSelectPairingRequired($combinedUpper)) {
            return self::result(
                self::REASON_COMMANDPRICING_SEGMENTSELECT_PAIRING_REQUIRED,
                'Sabre requires each CommandPricing RPH to be paired with SegmentSelect RPH.',
                'Fix v2.4 CommandPricing/SegmentSelect RPH pairing before retry.',
                self::RETRY_NO_AUTO_RETRY,
                self::LAYER_AIRPRICE,
                self::sanitizeSignals(['message_contains:commandpricing_segmentselect_pairing_required']),
            );
        }

        if (self::indicatesApplicationIncompleteNoLocator($context, $messagesUpper, $combinedUpper)) {
            return self::result(
                self::REASON_APPLICATION_INCOMPLETE_NO_LOCATOR,
                'Sabre Passenger Records returned Incomplete or NotProcessed without a PNR locator.',
                'Review safe ApplicationResults errors/warnings before any controlled retry; do not auto-retry.',
                self::RETRY_NO_AUTO_RETRY,
                self::LAYER_PASSENGER_RECORDS_HOST,
                self::sanitizeSignals(['application_status:incomplete_or_notprocessed']),
            );
        }

        if (self::indicatesMixedInterlineNotBookable($combinedUpper)) {
            return self::result(
                self::REASON_MIXED_INTERLINE_NOT_BOOKABLE,
                'Sabre rejected the mixed-carrier or interline combination.',
                'Re-shop with a bookable carrier combination; do not retry the same mixed offer without staff review.',
                self::RETRY_NO_AUTO_RETRY,
                self::LAYER_PASSENGER_RECORDS_HOST,
                self::sanitizeSignals(['message_contains:mixed_interline_not_bookable']),
            );
        }

        if (self::indicatesFarePricingQualifierRejected($combinedUpper)) {
            return self::result(
                self::REASON_FARE_PRICING_QUALIFIER_REJECTED,
                'Sabre rejected fare basis, RBD, or pricing qualifier on the Passenger Records request.',
                'Review fare basis/RBD/pricing qualifier mapping before any retry.',
                self::RETRY_NO_AUTO_RETRY,
                self::LAYER_AIRPRICE,
                self::sanitizeSignals(['message_contains:fare_pricing_qualifier_rejected']),
            );
        }

        if (self::indicatesSegmentSellUnavailable($context, $messagesUpper, $combinedUpper, $segmentStatus)) {
            return self::result(
                self::REASON_SEGMENT_SELL_UNAVAILABLE,
                'Sabre could not sell or confirm one or more flight segments.',
                'Re-shop/revalidate and choose confirmable inventory before retrying.',
                self::RETRY_NO_RETRY_SAME_OFFER,
                self::LAYER_AIRBOOK_SELL,
                self::buildInventoryUnavailableSignals($context, $messagesUpper, $segmentStatus),
            );
        }

        return self::result(
            self::REASON_UNKNOWN,
            'Sabre returned an unclassified host response requiring staff review.',
            'Review supplier attempt diagnostics and contact support if needed.',
            self::RETRY_NO_AUTO_RETRY,
            self::LAYER_UNKNOWN,
            self::sanitizeSignals(self::buildUnknownSignals($errorCode)),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected static function indicatesUcSegmentFailure(array $context, string $messagesUpper, string $segmentStatus): bool
    {
        if ($segmentStatus === 'UC') {
            return true;
        }

        if (str_contains($messagesUpper, 'STATUS CODE UC')) {
            return true;
        }

        return ($context['halt_on_status_received'] ?? false) === true
            && ($segmentStatus === 'UC' || str_contains($messagesUpper, 'STATUS CODE UC'));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    /**
     * @param  array<string, mixed>  $context
     */
    protected static function indicatesHostSegmentStatusUnconfirmed(
        array $context,
        string $messagesUpper,
        string $combinedUpper,
        string $segmentStatus,
    ): bool {
        if ($segmentStatus === 'NN') {
            return true;
        }

        if (($context['probable_issue'] ?? '') === 'airline_segment_status_nn_halt') {
            return true;
        }

        foreach (self::HOST_SEGMENT_STATUS_MESSAGE_NEEDLES as $needle) {
            if (str_contains($combinedUpper, $needle)) {
                return true;
            }
        }

        if (str_contains($combinedUpper, 'HALT ON STATUS RECEIVED')) {
            return true;
        }

        return ($context['halt_on_status_received'] ?? false) === true
            && ($segmentStatus === 'NN' || str_contains($messagesUpper, 'STATUS CODE NN'));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected static function indicatesInventoryUnavailable(array $context, string $messagesUpper, string $segmentStatus): bool
    {
        if (in_array($segmentStatus, ['NO', 'HX', 'UN'], true)) {
            return true;
        }

        foreach (['UNABLE TO CONFIRM', 'STATUS CODE NO', 'STATUS CODE HX', 'STATUS CODE UN'] as $needle) {
            if (str_contains($messagesUpper, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected static function indicatesEntitlementOrSecurityError(
        array $context,
        string $messagesUpper,
        string $errorCode,
        ?int $httpStatus,
    ): bool {
        if ($errorCode === 'sabre_booking_forbidden') {
            return true;
        }

        if ($httpStatus === 403) {
            return true;
        }

        foreach ([
            'NOT AUTHORIZED',
            'FORBIDDEN',
            'SECURITY',
            'ENTITLEMENT',
            'ERR.2SG.SEC.NOT_AUTHORIZED',
        ] as $needle) {
            if (str_contains($messagesUpper, $needle)) {
                return true;
            }
        }

        if (str_contains($messagesUpper, 'PCC')) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    /**
     * @param  array<string, mixed>  $context
     */
    protected static function indicatesApplicationIncompleteNoLocator(
        array $context,
        string $messagesUpper,
        string $combinedUpper,
    ): bool {
        if ($combinedUpper !== '') {
            foreach ([
                'NO FARES/RBD/CARRIER',
                'NO FARES',
                'INTERLINE',
                'MIXED CARRIER',
                'FARE BASIS',
                'PRICING QUALIFIER',
                'UNABLE TO SELL',
                'COMMANDPRICING@RPH',
                'STATUS CODE UC',
                'STATUS CODE NN',
                'STATUS CODE HX',
                'STATUS CODE NO',
            ] as $needle) {
                if (str_contains($combinedUpper, $needle)) {
                    return false;
                }
            }
        }

        $reasonCode = strtolower(trim((string) ($context['reason_code'] ?? '')));
        if ($reasonCode === 'sabre_passenger_records_incomplete_no_pnr') {
            return true;
        }

        $appStatus = strtolower(trim((string) ($context['application_status'] ?? '')));
        if (in_array($appStatus, ['incomplete', 'notprocessed'], true)) {
            return true;
        }

        $digestStatus = strtolower(trim((string) ($context['application_digest_status'] ?? '')));

        return $digestStatus === 'incomplete_no_locator';
    }

    protected static function indicatesMixedInterlineNotBookable(string $combinedUpper): bool
    {
        if ($combinedUpper === '') {
            return false;
        }

        foreach ([
            'INTERLINE NOT',
            'INTERLINE COMBINATION',
            'MIXED CARRIER',
            'CARRIER COMBINATION',
            'NOT BOOKABLE',
            'INVALID CARRIER COMBINATION',
            'NOT VALID FOR BOOKING',
        ] as $needle) {
            if (str_contains($combinedUpper, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected static function indicatesBrandRphSchemaInvalid(string $combinedUpper): bool
    {
        if ($combinedUpper === '') {
            return false;
        }

        return (str_contains($combinedUpper, '/PRICINGQUALIFIERS/BRAND/') || str_contains($combinedUpper, '/BRAND/0/RPH'))
            && str_contains($combinedUpper, 'INSTANCE TYPE (STRING)');
    }

    protected static function indicatesBrandSegmentSelectPairingRequired(string $combinedUpper): bool
    {
        if ($combinedUpper === '') {
            return false;
        }

        return str_contains($combinedUpper, 'BRAND WITHOUT RPH CANNOT COMBINE WITH SEGMENTSELECT')
            || (str_contains($combinedUpper, 'BRAND WITHOUT RPH') && str_contains($combinedUpper, 'SEGMENTSELECT'));
    }

    protected static function indicatesCommandPricingSegmentSelectPairingRequired(string $combinedUpper): bool
    {
        if ($combinedUpper === '') {
            return false;
        }

        if (self::indicatesBrandSegmentSelectPairingRequired($combinedUpper)) {
            return false;
        }

        return str_contains($combinedUpper, 'COMMANDPRICING@RPH MUST BE COMBINED WITH SEGMENTSELECT@RPH')
            || str_contains($combinedUpper, 'COMMANDPRICING@RPH MUST BE COMBINED WITH SEGMENTSELECT');
    }

    protected static function indicatesFarePricingQualifierRejected(string $combinedUpper): bool
    {
        if ($combinedUpper === '') {
            return false;
        }

        if (self::indicatesCommandPricingSegmentSelectPairingRequired($combinedUpper)) {
            return false;
        }

        if (self::indicatesBrandRphSchemaInvalid($combinedUpper)) {
            return false;
        }

        if (self::indicatesBrandSegmentSelectPairingRequired($combinedUpper)) {
            return false;
        }

        foreach ([
            'FARE BASIS',
            'PRICING QUALIFIER',
            'COMMANDPRICING',
            'COMMAND PRICING',
            'INVALID FARE',
            'QUALIFIER REJECTED',
            'FARE QUALIFIER',
        ] as $needle) {
            if (str_contains($combinedUpper, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected static function indicatesSegmentSellUnavailable(
        array $context,
        string $messagesUpper,
        string $combinedUpper,
        string $segmentStatus,
    ): bool {
        if (in_array($segmentStatus, ['NN', 'UC', 'HX', 'NO', 'UN'], true)) {
            return true;
        }

        foreach ([
            'UNABLE TO SELL',
            'SEGMENT SELL',
            'SELL SEGMENT',
            'NO INVENTORY',
            'INVENTORY NOT AVAILABLE',
            'UNABLE TO BOOK SEGMENT',
        ] as $needle) {
            if (str_contains($messagesUpper, $needle) || str_contains($combinedUpper, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected static function indicatesTransportOrTimeoutError(
        array $context,
        string $messagesUpper,
        string $errorCode,
        ?int $httpStatus,
    ): bool {
        if (in_array($errorCode, ['sabre_booking_connection_error', 'transport_timeout', 'sabre_timeout'], true)) {
            return true;
        }

        if (str_contains($errorCode, 'timeout') || str_contains($errorCode, 'connection')) {
            return true;
        }

        if ($httpStatus !== null && in_array($httpStatus, self::TRANSPORT_HTTP_STATUSES, true)) {
            return true;
        }

        foreach (['TIMEOUT', 'CONNECTION REFUSED', 'CONNECTION RESET', 'TEMPORARILY UNAVAILABLE'] as $needle) {
            if (str_contains($messagesUpper, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    protected static function buildUcSignals(array $context, string $messagesUpper, string $segmentStatus): array
    {
        $signals = [];

        if ($segmentStatus === 'UC') {
            $signals[] = 'airline_segment_status:UC';
        }

        if (str_contains($messagesUpper, 'STATUS CODE UC')) {
            $signals[] = 'segment_status:UC';
        }

        if (($context['halt_on_status_received'] ?? false) === true) {
            $signals[] = 'halt_on_status_received:true';
        }

        return self::sanitizeSignals($signals);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    protected static function buildHostSegmentStatusSignals(
        array $context,
        string $messagesUpper,
        string $combinedUpper,
        string $segmentStatus,
    ): array {
        $signals = [];

        if ($segmentStatus === 'NN') {
            $signals[] = 'airline_segment_status:NN';
        }

        if (str_contains($messagesUpper, 'STATUS CODE NN')) {
            $signals[] = 'segment_status:NN';
        }

        if (str_contains($messagesUpper, 'SEGMENT STATUS NN')) {
            $signals[] = 'message_contains:segment_status_nn';
        }

        if (str_contains($combinedUpper, 'HALT_ON_STATUS_RECEIVED')
            || str_contains($combinedUpper, 'HALT ON STATUS RECEIVED')) {
            $signals[] = 'halt_on_status_received:true';
        }

        if (str_contains($combinedUpper, 'SPECIFIED HALTONSTATUS RECEIVED')) {
            $signals[] = 'message_contains:specified_halt_on_status_received';
        }

        if (($context['halt_on_status_received'] ?? false) === true) {
            $signals[] = 'halt_on_status_received:flag';
        }

        if (($context['probable_issue'] ?? '') === 'airline_segment_status_nn_halt') {
            $signals[] = 'probable_issue:airline_segment_status_nn_halt';
        }

        return self::sanitizeSignals($signals);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    protected static function buildInventoryUnavailableSignals(
        array $context,
        string $messagesUpper,
        string $segmentStatus,
    ): array {
        $signals = [];

        if (in_array($segmentStatus, ['NO', 'HX', 'UN'], true)) {
            $signals[] = 'airline_segment_status:'.$segmentStatus;
        }

        foreach (['NO' => 'segment_status:NO', 'HX' => 'segment_status:HX', 'UN' => 'segment_status:UN'] as $code => $signal) {
            if (str_contains($messagesUpper, 'STATUS CODE '.$code)) {
                $signals[] = $signal;
            }
        }

        if (str_contains($messagesUpper, 'UNABLE TO CONFIRM')) {
            $signals[] = 'message_contains:unable_to_confirm';
        }

        return self::sanitizeSignals($signals);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    protected static function buildEntitlementSignals(
        array $context,
        string $messagesUpper,
        string $errorCode,
        ?int $httpStatus,
    ): array {
        $signals = [];

        if ($errorCode === 'sabre_booking_forbidden') {
            $signals[] = 'error_code:sabre_booking_forbidden';
        }

        if ($httpStatus === 403) {
            $signals[] = 'http_status:403';
        }

        if (str_contains($messagesUpper, 'ERR.2SG.SEC.NOT_AUTHORIZED')) {
            $signals[] = 'message_contains:err_2sg_sec_not_authorized';
        } elseif (str_contains($messagesUpper, 'NOT AUTHORIZED')) {
            $signals[] = 'message_contains:not_authorized';
        } elseif (str_contains($messagesUpper, 'FORBIDDEN')) {
            $signals[] = 'message_contains:forbidden';
        } elseif (str_contains($messagesUpper, 'ENTITLEMENT')) {
            $signals[] = 'message_contains:entitlement';
        } elseif (str_contains($messagesUpper, 'SECURITY')) {
            $signals[] = 'message_contains:security';
        }

        return self::sanitizeSignals($signals);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    protected static function buildTransportSignals(
        array $context,
        string $messagesUpper,
        string $errorCode,
        ?int $httpStatus,
    ): array {
        $signals = [];

        if ($errorCode !== '') {
            $signals[] = 'error_code:'.$errorCode;
        }

        if ($httpStatus !== null && in_array($httpStatus, self::TRANSPORT_HTTP_STATUSES, true)) {
            $signals[] = 'http_status:'.$httpStatus;
        }

        if (str_contains($messagesUpper, 'TIMEOUT')) {
            $signals[] = 'message_contains:timeout';
        } elseif (str_contains($messagesUpper, 'CONNECTION')) {
            $signals[] = 'message_contains:connection';
        }

        return self::sanitizeSignals($signals);
    }

    /**
     * @return list<string>
     */
    protected static function buildUnknownSignals(string $errorCode): array
    {
        if ($errorCode === '') {
            return ['classification:fallback'];
        }

        return ['error_code:'.$errorCode];
    }

    /**
     * @param  list<string>  $signals
     * @return list<string>
     */
    protected static function sanitizeSignals(array $signals): array
    {
        $out = [];

        foreach (array_values(array_unique($signals)) as $signal) {
            $signal = strtolower(trim((string) $signal));
            if ($signal === '') {
                continue;
            }

            $signal = preg_replace('/[^a-z0-9:_\-.]/', '_', $signal) ?? $signal;
            $signal = preg_replace('/_+/', '_', $signal) ?? $signal;
            $signal = trim($signal, '_');

            if ($signal === '' || self::signalLooksUnsafe($signal)) {
                continue;
            }

            $out[] = Str::limit($signal, 80, '');
        }

        return array_values(array_slice($out, 0, 8));
    }

    protected static function signalLooksUnsafe(string $signal): bool
    {
        foreach (self::FORBIDDEN_OUTPUT_SUBSTRINGS as $forbidden) {
            if (str_contains($signal, $forbidden)) {
                return true;
            }
        }

        if (preg_match('/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/', $signal) === 1) {
            return true;
        }

        if (preg_match('/@[a-z0-9._-]+\.[a-z]{2,}/i', $signal) === 1) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected static function stringifyMessages(array $context): string
    {
        $parts = [];

        foreach (['response_error_messages', 'application_error_messages', 'host_warning_messages_truncated', 'messages', 'message'] as $key) {
            $val = $context[$key] ?? null;
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
     * @param  array<string, mixed>  $context
     */
    protected static function stringifySafeSummaryField(array $context): string
    {
        $val = $context['safe_summary'] ?? null;

        if (is_string($val)) {
            return $val;
        }

        if (is_array($val)) {
            $parts = [];
            foreach ($val as $item) {
                if (is_scalar($item)) {
                    $parts[] = (string) $item;
                }
            }

            return implode(' ', $parts);
        }

        return '';
    }

    /**
     * @param  list<string>  $matchedSignals
     * @return array{
     *     safe_reason_code: string,
     *     safe_summary: string,
     *     recommended_admin_action: string,
     *     retry_policy: string,
     *     manual_review_required: bool,
     *     source_layer: string,
     *     matched_signals: list<string>
     * }
     */
    protected static function result(
        string $safeReasonCode,
        string $safeSummary,
        string $recommendedAdminAction,
        string $retryPolicy,
        string $sourceLayer,
        array $matchedSignals,
    ): array {
        return [
            'safe_reason_code' => $safeReasonCode,
            'safe_summary' => $safeSummary,
            'recommended_admin_action' => $recommendedAdminAction,
            'retry_policy' => $retryPolicy,
            'manual_review_required' => true,
            'source_layer' => $sourceLayer,
            'matched_signals' => $matchedSignals,
        ];
    }
}
