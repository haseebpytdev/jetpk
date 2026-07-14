<?php

namespace App\Support\Sabre;

use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Carbon;

/**
 * F9H/F9I: Safe structural digest for Passenger Records CPNR wire (AirBook + AirPrice) vs selected booking context.
 * F9I splits hard vs warning risk; adds AirPrice validating-carrier and brand consistency diagnostics.
 * F9K: VC from FlightQualifiers.VendorPrefs.Airline; local CPNR schema validation in digest clean gate.
 * No raw payloads, PII, secrets, or supplier responses.
 */
final class SabrePassengerRecordsPayloadDigest
{
    public const SLIM_DIGEST_KEY = 'passenger_records_payload_digest';

    private const SEGMENT_MAX = 6;

    private const KEY_SAMPLE_MAX = 24;

    /** @var list<string> */
    private const HARD_RISK_REASONS = [
        'missing_rbd',
        'missing_marketing_carrier',
        'missing_operating_carrier',
        'missing_flight_number',
        'rbd_context_payload_mismatch',
        'carrier_context_payload_mismatch',
        'airprice_missing_validating_carrier',
        'airprice_missing_brand_or_fare_qualifier',
        'airbook_missing_number_in_party',
        'segment_order_mismatch',
        'segment_datetime_mismatch',
        'stale_offer_context',
        'validating_carrier_mismatch',
        'brand_context_payload_mismatch',
        'accepted_fare_brand_mismatch',
    ];

    /** @var list<string> */
    private const WARNING_RISK_REASONS = [
        'legacy_revalidation_signal_used',
        'missing_revalidation_linkage',
    ];

    /** @var list<string> */
    private const FORBIDDEN_KEY_SUBSTRINGS = [
        'raw_payload', 'request_body', 'response_body', 'password', 'secret', 'credential',
        'passport', 'email', 'phone', 'first_name', 'last_name', 'givenname', 'surname',
        'personname', 'contactnumbers', 'document',
    ];

    public function __construct(
        protected SabreBookingPayloadBuilder $payloadBuilder,
        protected SabreCpnrIatiWireSchemaValidator $cpnrSchemaValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $wire  Final POST body (CreatePassengerNameRecordRQ root wrapper)
     * @param  array<string, mixed>  $context  endpoint_path, payload_style, version, passenger_count, selected_context, revalidation flags
     * @return array<string, mixed>
     */
    public function digest(array $wire, array $context = []): array
    {
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null)
            ? $wire['CreatePassengerNameRecordRQ']
            : $wire;

        $snapshotSegs = array_values(is_array($context['selected_context_segments'] ?? null)
            ? $context['selected_context_segments']
            : []);
        $apiDraft = is_array($context['api_draft'] ?? null) ? $context['api_draft'] : [];
        $bookingMeta = is_array($context['booking_meta'] ?? null) ? $context['booking_meta'] : null;
        $payloadStyle = trim((string) ($context['payload_style'] ?? ''));
        $version = is_scalar($cpnr['version'] ?? null)
            ? (string) $cpnr['version']
            : (trim((string) ($context['version'] ?? '')) ?: null);

        $segSell = $this->payloadBuilder->traditionalPnrAirBookSegmentSellDiagnostics($wire, $snapshotSegs);
        $airbookSegmentDigest = $this->buildAirbookSegmentDigest($segSell['segments'] ?? []);
        $airpriceDigest = $this->buildAirpriceDigest($cpnr, $apiDraft, $wire, $payloadStyle, $bookingMeta);
        $structural = $this->extractStructuralFlags($cpnr, $wire);
        $contextComparison = $this->buildContextComparison(
            $snapshotSegs,
            $airbookSegmentDigest,
            $context,
            $airpriceDigest,
        );

        $brandDiagnostics = $this->buildBrandConsistencyDiagnostics($context, $airpriceDigest);
        $schemaSummary = SabreBookingPayloadBuilder::isIatiLikeCpnrV24GdsWireStyle($payloadStyle)
            ? $this->cpnrSchemaValidator->validateIatiLikeCpnrV24AirPrice($cpnr)
            : (SabreBookingPayloadBuilder::isPassengerRecordsV25GdsWireStyle($payloadStyle)
                ? app(SabrePassengerRecordsV25WireSchemaValidator::class)->validatePassengerRecordsV25GdsAirPrice($cpnr)
                : SabreCpnrIatiWireSchemaValidator::notRunSummary());
        [$hardReasons, $warningReasons] = $this->deriveNoFaresRbdCarrierRiskReasons(
            $airbookSegmentDigest,
            $airpriceDigest,
            $contextComparison,
            $structural,
            $context,
            $brandDiagnostics,
        );

        $airpriceVc = $airpriceDigest['validating_carrier'] ?? null;
        $airpriceVcPresent = is_string($airpriceVc) && trim($airpriceVc) !== '';

        $out = array_merge($structural, [
            'endpoint_path' => isset($context['endpoint_path']) ? (string) $context['endpoint_path'] : null,
            'payload_schema' => (string) ($context['payload_schema'] ?? 'create_passenger_name_record'),
            'payload_style' => $payloadStyle !== '' ? $payloadStyle : null,
            'version' => $version,
            'passenger_count' => isset($context['passenger_count']) ? (int) $context['passenger_count'] : null,
            'segment_count' => count($airbookSegmentDigest),
            'validating_carrier' => $this->sanitizeCarrier($context['validating_carrier'] ?? $apiDraft['validating_carrier'] ?? null),
            'brand_code' => $this->sanitizeBrand($context['brand_code'] ?? null),
            'brand_name' => isset($context['brand_name']) ? substr(trim((string) $context['brand_name']), 0, 64) : null,
            'airbook_segment_digest' => $airbookSegmentDigest,
            'airprice_digest' => $airpriceDigest,
            'context_comparison' => $contextComparison,
            'mismatch_reasons' => $contextComparison['mismatch_reasons'] ?? [],
            'airprice_validating_carrier_present' => $airpriceVcPresent,
            'airprice_validating_carrier' => $airpriceVcPresent ? $airpriceVc : null,
            'hard_no_fares_rbd_carrier_risk' => $hardReasons !== [],
            'hard_no_fares_rbd_carrier_risk_reasons' => $hardReasons,
            'warning_reasons' => $warningReasons,
            'no_fares_rbd_carrier_risk' => $hardReasons !== [],
            'no_fares_rbd_carrier_risk_reasons' => $hardReasons,
            'recommended_next_action' => $this->recommendedNextAction($hardReasons, $warningReasons, $structural, $context),
        ], $brandDiagnostics, $schemaSummary);

        $out['post_f9i_payload_digest_clean'] = ($hardReasons === [])
            && (($schemaSummary['cpnr_schema_validation_status'] ?? 'not_run') === 'pass'
                || ($schemaSummary['cpnr_schema_validation_status'] ?? 'not_run') === 'not_run');

        return SensitiveDataRedactor::redact($this->stripForbiddenKeys($out));
    }

    /**
     * @param  array<string, mixed>|null  $digest
     * @return array<string, mixed>
     */
    public function commandSummaryFromDigest(?array $digest): array
    {
        if ($digest === null || $digest === []) {
            return [
                'payload_digest_available' => false,
                'no_fares_rbd_carrier_risk' => false,
                'no_fares_rbd_carrier_risk_reasons' => null,
                'hard_no_fares_rbd_carrier_risk' => false,
                'hard_no_fares_rbd_carrier_risk_reasons' => null,
                'warning_reasons' => null,
                'airprice_validating_carrier_present' => false,
                'airprice_validating_carrier' => null,
                'validating_carrier_match' => null,
                'brand_match' => null,
                'brand_mismatch_reason' => null,
                'airbook_segment_count' => 0,
                'airprice_present' => false,
                'airbook_rbd_complete' => false,
                'airbook_carrier_complete' => false,
                'cpnr_schema_validation_status' => 'not_run',
                'cpnr_schema_validation_failed' => false,
                'cpnr_schema_validation_pointer' => null,
                'cpnr_schema_validation_message_summary' => null,
            ];
        }

        $hardReasons = is_array($digest['hard_no_fares_rbd_carrier_risk_reasons'] ?? null)
            ? array_values($digest['hard_no_fares_rbd_carrier_risk_reasons'])
            : (is_array($digest['no_fares_rbd_carrier_risk_reasons'] ?? null)
                ? array_values($digest['no_fares_rbd_carrier_risk_reasons'])
                : []);
        $warningReasons = is_array($digest['warning_reasons'] ?? null)
            ? array_values($digest['warning_reasons'])
            : [];
        $segments = is_array($digest['airbook_segment_digest'] ?? null)
            ? $digest['airbook_segment_digest']
            : [];
        $comparison = is_array($digest['context_comparison'] ?? null) ? $digest['context_comparison'] : [];

        return [
            'payload_digest_available' => true,
            'no_fares_rbd_carrier_risk' => ($digest['hard_no_fares_rbd_carrier_risk'] ?? $digest['no_fares_rbd_carrier_risk'] ?? false) === true,
            'no_fares_rbd_carrier_risk_reasons' => $hardReasons !== [] ? implode(',', $hardReasons) : null,
            'hard_no_fares_rbd_carrier_risk' => ($digest['hard_no_fares_rbd_carrier_risk'] ?? false) === true,
            'hard_no_fares_rbd_carrier_risk_reasons' => $hardReasons !== [] ? implode(',', $hardReasons) : null,
            'warning_reasons' => $warningReasons !== [] ? implode(',', $warningReasons) : null,
            'airprice_validating_carrier_present' => ($digest['airprice_validating_carrier_present'] ?? false) === true,
            'airprice_validating_carrier' => $digest['airprice_validating_carrier'] ?? null,
            'validating_carrier_match' => array_key_exists('validating_carrier_match', $comparison)
                ? $comparison['validating_carrier_match']
                : null,
            'brand_match' => array_key_exists('brand_match', $digest) ? $digest['brand_match'] : null,
            'brand_mismatch_reason' => is_string($digest['brand_mismatch_reason'] ?? null) && trim($digest['brand_mismatch_reason']) !== ''
                ? (string) $digest['brand_mismatch_reason']
                : null,
            'airbook_segment_count' => count($segments),
            'airprice_present' => ($digest['has_air_price'] ?? false) === true,
            'airbook_rbd_complete' => ($comparison['rbd_match'] ?? false) === true
                && ($comparison['missing_rbd_segments'] ?? []) === [],
            'airbook_carrier_complete' => ($comparison['carrier_chain_match'] ?? false) === true
                && ($comparison['missing_carrier_segments'] ?? []) === [],
            'cpnr_schema_validation_status' => (string) ($digest['cpnr_schema_validation_status'] ?? 'not_run'),
            'cpnr_schema_validation_failed' => ($digest['cpnr_schema_validation_failed'] ?? false) === true,
            'cpnr_schema_validation_pointer' => is_string($digest['cpnr_schema_validation_pointer'] ?? null)
                ? (string) $digest['cpnr_schema_validation_pointer']
                : null,
            'cpnr_schema_validation_message_summary' => is_string($digest['cpnr_schema_validation_message_summary'] ?? null)
                ? (string) $digest['cpnr_schema_validation_message_summary']
                : null,
            'post_f9i_payload_digest_clean' => ($digest['post_f9i_payload_digest_clean'] ?? false) === true,
        ];
    }

    /**
     * F9J: Whether rebuilt payload digest summary is structurally clean for post-F9I controlled retry.
     *
     * @param  array<string, mixed>  $summary  commandSummaryFromDigest shape
     */
    public function isPostF9iCleanForControlledRetry(array $summary): bool
    {
        return $this->postF9iCleanBlockers($summary) === [];
    }

    /**
     * @param  array<string, mixed>  $summary  commandSummaryFromDigest shape
     * @return list<string>
     */
    public function postF9iCleanBlockers(array $summary): array
    {
        $blockers = [];

        if (($summary['payload_digest_available'] ?? false) !== true) {
            $blockers[] = 'payload_digest_not_available';
        }

        if (($summary['hard_no_fares_rbd_carrier_risk'] ?? false) === true) {
            $blockers[] = 'hard_no_fares_rbd_carrier_risk';
        }

        if (($summary['airprice_validating_carrier_present'] ?? false) !== true) {
            $blockers[] = 'airprice_validating_carrier_missing';
        }

        if (($summary['validating_carrier_match'] ?? false) !== true) {
            $blockers[] = 'validating_carrier_mismatch';
        }

        $brandMatch = $summary['brand_match'] ?? null;
        if ($brandMatch === false) {
            $blockers[] = 'brand_match_false';
        }

        if (($summary['airbook_rbd_complete'] ?? false) !== true) {
            $blockers[] = 'airbook_rbd_incomplete';
        }

        if (($summary['airbook_carrier_complete'] ?? false) !== true) {
            $blockers[] = 'airbook_carrier_incomplete';
        }

        if (($summary['airprice_present'] ?? false) !== true) {
            $blockers[] = 'airprice_missing';
        }

        if (($summary['cpnr_schema_validation_status'] ?? 'not_run') === 'fail') {
            $blockers[] = 'cpnr_schema_validation_failed';
        }

        $warnings = $this->normalizeWarningReasonList($summary['warning_reasons'] ?? null);
        $allowedWarnings = ['legacy_revalidation_signal_used'];
        foreach ($warnings as $warning) {
            if (! in_array($warning, $allowedWarnings, true)) {
                $blockers[] = 'disallowed_warning:'.$warning;
            }
        }

        return array_values(array_unique($blockers));
    }

    /**
     * @return list<string>
     */
    protected function normalizeWarningReasonList(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }

        if (is_string($raw)) {
            return array_values(array_filter(array_map(
                static fn (string $part): string => trim($part),
                explode(',', $raw),
            ), static fn (string $part): bool => $part !== ''));
        }

        if (is_array($raw)) {
            return array_values(array_filter(array_map(
                static fn ($item): string => is_string($item) ? trim($item) : '',
                $raw,
            ), static fn (string $item): bool => $item !== ''));
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function buildAirbookSegmentDigest(array $rows): array
    {
        $out = [];
        foreach (array_slice($rows, 0, self::SEGMENT_MAX) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $dep = $this->splitDatetime((string) ($row['departure_datetime'] ?? ''));
            $arr = $this->splitDatetime((string) ($row['arrival_datetime'] ?? ''));
            $rbd = $row['res_book_desig_code'] ?? null;
            $out[] = array_filter([
                'index' => $row['index'] ?? null,
                'marketing_carrier' => $row['marketing_airline'] ?? null,
                'operating_carrier' => $row['operating_airline'] ?? null,
                'flight_number' => $row['flight_number'] ?? null,
                'origin' => $row['origin'] ?? null,
                'destination' => $row['destination'] ?? null,
                'departure_date' => $dep['date'],
                'departure_time' => $dep['time'],
                'arrival_date' => $arr['date'],
                'arrival_time' => $arr['time'],
                'booking_class' => $rbd,
                'res_book_desig_code' => $rbd,
                'status_code' => $row['status'] ?? null,
                'action_code' => $row['status'] ?? null,
                'number_in_party' => $row['number_in_party'] ?? null,
                'marriage_group' => $row['marriage_group'] ?? null,
                'fare_basis_snapshot' => $row['fare_basis_snapshot'] ?? null,
            ], static fn ($v) => $v !== null && $v !== '');
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $cpnr
     * @param  array<string, mixed>  $apiDraft
     * @param  array<string, mixed>  $wire
     * @return array<string, mixed>
     */
    protected function buildAirpriceDigest(
        array $cpnr,
        array $apiDraft,
        array $wire,
        string $payloadStyle,
        ?array $bookingMeta,
    ): array {
        $brandInspect = $this->payloadBuilder->summarizeAirPriceBrandQualifierForInspect(
            $apiDraft,
            $wire,
            $payloadStyle !== '' ? $payloadStyle : null,
            $bookingMeta,
        );

        $airPrice = is_array($cpnr['AirPrice'] ?? null) ? $cpnr['AirPrice'] : [];
        $firstPrice = is_array($airPrice[0] ?? null) ? $airPrice[0] : [];
        $pri = is_array($firstPrice['PriceRequestInformation'] ?? null) ? $firstPrice['PriceRequestInformation'] : [];
        $oq = is_array($pri['OptionalQualifiers'] ?? null) ? $pri['OptionalQualifiers'] : [];
        $pq = is_array($oq['PricingQualifiers'] ?? null) ? $oq['PricingQualifiers'] : [];

        $vcFromOq = $this->payloadBuilder->traditionalPnrExtractValidatingCarrierCodeFromAirPriceOptionalQualifiers($oq);
        $vcSanitized = $vcFromOq;

        $ptRows = data_get($pq, 'PassengerType', []);
        if (! is_array($ptRows)) {
            $ptRows = $ptRows !== null && $ptRows !== '' ? [$ptRows] : [];
        }
        if ($ptRows !== [] && ! array_is_list($ptRows)) {
            $ptRows = [$ptRows];
        }
        $ptcSummary = [];
        foreach ($ptRows as $pt) {
            if (! is_array($pt)) {
                continue;
            }
            $code = strtoupper(trim((string) ($pt['Code'] ?? '')));
            $qty = (int) ($pt['Quantity'] ?? 0);
            if ($code !== '') {
                $ptcSummary[] = ['code' => $code, 'quantity' => $qty > 0 ? $qty : null];
            }
        }

        $currency = data_get($pri, 'OptionalQualifiers.PricingQualifiers.CurrencyCode');
        if (! is_string($currency) || trim($currency) === '') {
            $currency = data_get($apiDraft, 'fare.currency');
        }
        $currencySanitized = is_string($currency) && trim($currency) !== ''
            ? strtoupper(substr(trim($currency), 0, 8))
            : null;

        $brandCode = $this->extractBrandCodeFromPricingQualifiers($pq)
            ?? $brandInspect['resolved_brand_code_for_wire']
            ?? $brandInspect['merged_context_brand_code']
            ?? $brandInspect['selected_fare_family_brand_code']
            ?? null;

        return array_filter([
            'validating_carrier' => $vcSanitized,
            'validating_carrier_present' => $vcSanitized !== null,
            'brand_code' => is_string($brandCode) && trim($brandCode) !== ''
                ? strtoupper(substr(trim($brandCode), 0, 32))
                : null,
            'wire_brand_code' => is_string($wireBrandCode = $this->extractBrandCodeFromPricingQualifiers($pq)) && trim($wireBrandCode) !== ''
                ? strtoupper(substr(trim($wireBrandCode), 0, 32))
                : null,
            'currency' => $currencySanitized,
            'type_codes' => array_values(array_filter(array_map(
                static fn ($r) => is_array($r) ? ($r['code'] ?? null) : null,
                $ptcSummary,
            ))),
            'type_code_counts' => $ptcSummary,
            'pricing_qualifiers_keys_sample' => $this->keySample($pq),
            'optional_qualifiers_keys_sample' => $this->keySample($oq),
            'air_price_node_keys_sample' => $this->keySample($firstPrice),
            'brand_present_on_wire' => ($brandInspect['brand_present_on_wire'] ?? false) === true,
        ], static fn ($v) => $v !== null && $v !== [] && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $cpnr
     * @param  array<string, mixed>  $wire
     * @return array<string, mixed>
     */
    protected function extractStructuralFlags(array $cpnr, array $wire): array
    {
        $airBook = is_array($cpnr['AirBook'] ?? null) ? $cpnr['AirBook'] : [];
        $hasEnhanced = is_array($airBook['EnhancedAirBook'] ?? null) && $airBook['EnhancedAirBook'] !== [];

        $numberInParty = null;
        $segments = $this->payloadBuilder->traditionalPnrAirBookSegmentSellDiagnostics($wire, [])['segments'] ?? [];
        foreach ($segments as $seg) {
            if (is_array($seg) && isset($seg['number_in_party']) && $seg['number_in_party'] !== null && $seg['number_in_party'] !== '') {
                $numberInParty = $seg['number_in_party'];
                break;
            }
        }

        return [
            'has_create_passenger_name_record_rq' => $cpnr !== [],
            'has_enhanced_air_book' => $hasEnhanced,
            'has_air_book' => $airBook !== [],
            'has_air_price' => is_array($cpnr['AirPrice'] ?? null) && $cpnr['AirPrice'] !== [],
            'has_travel_itinerary_add_info' => is_array($cpnr['TravelItineraryAddInfo'] ?? null)
                && $cpnr['TravelItineraryAddInfo'] !== [],
            'has_special_req_details' => is_array($cpnr['SpecialReqDetails'] ?? null)
                && $cpnr['SpecialReqDetails'] !== [],
            'has_post_processing' => is_array($cpnr['PostProcessing'] ?? null)
                && $cpnr['PostProcessing'] !== [],
            'number_in_party' => $numberInParty,
            'pricing_information_index' => is_array($cpnr['AirPrice'] ?? null) && isset($cpnr['AirPrice'][0]) ? 0 : null,
            'itinerary_ref' => $this->safeScalarRef($cpnr, ['ItineraryRef', 'itineraryRef']),
            'leg_refs' => $this->safeRefList($cpnr, ['LegRefs', 'legRefs']),
            'schedule_refs' => $this->safeRefList($cpnr, ['ScheduleRefs', 'scheduleRefs']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $contextSegments
     * @param  list<array<string, mixed>>  $payloadSegments
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $airpriceDigest
     * @return array<string, mixed>
     */
    protected function buildContextComparison(
        array $contextSegments,
        array $payloadSegments,
        array $context,
        array $airpriceDigest,
    ): array {
        $ctxCount = count($contextSegments);
        $payloadCount = count($payloadSegments);
        $mismatchReasons = [];

        $missingRbd = [];
        $missingCarrier = [];
        $missingFlight = [];

        foreach ($payloadSegments as $seg) {
            $idx = (int) ($seg['index'] ?? 0);
            if (empty($seg['booking_class']) && empty($seg['res_book_desig_code'])) {
                $missingRbd[] = $idx;
            }
            if (empty($seg['marketing_carrier'])) {
                $missingCarrier[] = $idx;
            }
            if (empty($seg['flight_number'])) {
                $missingFlight[] = $idx;
            }
        }

        $carrierMatch = $this->compareCarrierChain($contextSegments, $payloadSegments);
        $orderMatch = $this->compareSegmentOrder($contextSegments, $payloadSegments);
        $rbdMatch = $this->compareRbd($contextSegments, $payloadSegments);
        $routeMatch = $this->compareRoute($contextSegments, $payloadSegments);
        $dateMatch = $this->compareDates($contextSegments, $payloadSegments);
        $fareBasisPresent = $this->fareBasisPresent($contextSegments, $payloadSegments);

        $ctxVc = $this->sanitizeCarrier($context['validating_carrier'] ?? null);
        $priceVc = $airpriceDigest['validating_carrier'] ?? null;
        $validatingCarrierMatch = $ctxVc !== null && $priceVc !== null && $ctxVc === $priceVc;

        if ($ctxCount !== $payloadCount) {
            $mismatchReasons[] = 'segment_count_mismatch';
        }
        if (! $carrierMatch) {
            $mismatchReasons[] = 'carrier_chain_mismatch';
        }
        if (! $orderMatch) {
            $mismatchReasons[] = 'segment_order_mismatch';
        }
        if (! $rbdMatch) {
            $mismatchReasons[] = 'rbd_mismatch';
        }
        if (! $routeMatch) {
            $mismatchReasons[] = 'route_mismatch';
        }
        if (! $dateMatch) {
            $mismatchReasons[] = 'datetime_mismatch';
        }
        if ($ctxVc !== null && $priceVc !== null && ! $validatingCarrierMatch) {
            $mismatchReasons[] = 'validating_carrier_mismatch';
        }
        if ($missingRbd !== []) {
            $mismatchReasons[] = 'missing_rbd_on_wire';
        }
        if ($missingCarrier !== []) {
            $mismatchReasons[] = 'missing_carrier_on_wire';
        }
        if ($missingFlight !== []) {
            $mismatchReasons[] = 'missing_flight_number_on_wire';
        }

        return [
            'selected_context_segment_count' => $ctxCount,
            'payload_segment_count' => $payloadCount,
            'carrier_chain_match' => $carrierMatch,
            'segment_order_match' => $orderMatch,
            'rbd_match' => $rbdMatch,
            'fare_basis_present' => $fareBasisPresent,
            'validating_carrier_match' => $validatingCarrierMatch,
            'route_match' => $routeMatch,
            'date_match' => $dateMatch,
            'missing_rbd_segments' => $missingRbd,
            'missing_carrier_segments' => $missingCarrier,
            'missing_flight_number_segments' => $missingFlight,
            'mismatch_reasons' => array_values(array_unique($mismatchReasons)),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $airpriceDigest
     * @return array<string, mixed>
     */
    protected function buildBrandConsistencyDiagnostics(array $context, array $airpriceDigest): array
    {
        $selectedContextBrand = $this->sanitizeBrand($context['brand_code'] ?? null);
        $payloadAirpriceBrand = $this->sanitizeBrand(
            $airpriceDigest['wire_brand_code'] ?? $airpriceDigest['brand_code'] ?? null
        );
        $validatedOfferBrand = $this->sanitizeBrand($context['validated_offer_brand_code'] ?? null);
        $acceptedFareBrand = $this->sanitizeBrand($context['accepted_fare_change_brand_code'] ?? null);

        $mismatchReason = null;
        $comparable = array_values(array_filter([
            $selectedContextBrand,
            $payloadAirpriceBrand,
            $validatedOfferBrand,
            $acceptedFareBrand,
        ], static fn ($v) => $v !== null && $v !== ''));

        $uniqueComparable = array_values(array_unique($comparable));
        $brandMatch = null;
        if ($uniqueComparable === []) {
            $brandMatch = null;
        } elseif (count($uniqueComparable) === 1) {
            $brandMatch = true;
        } else {
            $brandMatch = false;
            if ($selectedContextBrand !== null && $payloadAirpriceBrand !== null
                && $selectedContextBrand !== $payloadAirpriceBrand) {
                $mismatchReason = 'brand_context_payload_mismatch';
            } elseif ($acceptedFareBrand !== null && $payloadAirpriceBrand !== null
                && $acceptedFareBrand !== $payloadAirpriceBrand) {
                $mismatchReason = 'accepted_fare_brand_mismatch';
            } elseif ($validatedOfferBrand !== null && $payloadAirpriceBrand !== null
                && $validatedOfferBrand !== $payloadAirpriceBrand) {
                $mismatchReason = 'brand_context_payload_mismatch';
            } else {
                $mismatchReason = 'brand_context_payload_mismatch';
            }
        }

        return array_filter([
            'selected_context_brand_code' => $selectedContextBrand,
            'payload_airprice_brand_code' => $payloadAirpriceBrand,
            'validated_offer_brand_code' => $validatedOfferBrand,
            'accepted_fare_change_brand_code' => $acceptedFareBrand,
            'brand_match' => $brandMatch,
            'brand_mismatch_reason' => $brandMatch === false ? $mismatchReason : null,
        ], static fn ($v) => $v !== null);
    }

    /**
     * @param  list<array<string, mixed>>  $payloadSegments
     * @param  array<string, mixed>  $airpriceDigest
     * @param  array<string, mixed>  $contextComparison
     * @param  array<string, mixed>  $structural
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $brandDiagnostics
     * @return array{0: list<string>, 1: list<string>}
     */
    protected function deriveNoFaresRbdCarrierRiskReasons(
        array $payloadSegments,
        array $airpriceDigest,
        array $contextComparison,
        array $structural,
        array $context,
        array $brandDiagnostics,
    ): array {
        $allReasons = [];

        if (($contextComparison['missing_rbd_segments'] ?? []) !== []) {
            $allReasons[] = 'missing_rbd';
        }
        if (($contextComparison['missing_carrier_segments'] ?? []) !== []) {
            $allReasons[] = 'missing_marketing_carrier';
        }
        foreach ($payloadSegments as $seg) {
            if (empty($seg['operating_carrier']) && ! empty($seg['marketing_carrier'])) {
                // operating may be absent when same as marketing — not a risk alone
            } elseif (empty($seg['operating_carrier']) && empty($seg['marketing_carrier'])) {
                $allReasons[] = 'missing_operating_carrier';
                break;
            }
        }
        if (($contextComparison['missing_flight_number_segments'] ?? []) !== []) {
            $allReasons[] = 'missing_flight_number';
        }
        if (($contextComparison['rbd_match'] ?? true) === false) {
            $allReasons[] = 'rbd_context_payload_mismatch';
        }
        if (($contextComparison['carrier_chain_match'] ?? true) === false) {
            $allReasons[] = 'carrier_context_payload_mismatch';
        }
        if (($structural['has_air_price'] ?? false) === true && empty($airpriceDigest['validating_carrier'])) {
            $allReasons[] = 'airprice_missing_validating_carrier';
        }
        if (($structural['has_air_price'] ?? false) === true
            && ($airpriceDigest['brand_present_on_wire'] ?? false) !== true
            && empty($airpriceDigest['brand_code'])) {
            $allReasons[] = 'airprice_missing_brand_or_fare_qualifier';
        }
        if (($structural['number_in_party'] ?? null) === null && $payloadSegments !== []) {
            $allReasons[] = 'airbook_missing_number_in_party';
        }
        if (($contextComparison['segment_order_match'] ?? true) === false) {
            $allReasons[] = 'segment_order_mismatch';
        }
        if (($contextComparison['date_match'] ?? true) === false) {
            $allReasons[] = 'segment_datetime_mismatch';
        }
        if (($context['stale_offer_context'] ?? false) === true) {
            $allReasons[] = 'stale_offer_context';
        }
        if (($context['missing_revalidation_linkage'] ?? false) === true) {
            $allReasons[] = 'missing_revalidation_linkage';
        }
        if (($context['legacy_revalidation_signal_used'] ?? false) === true) {
            $allReasons[] = 'legacy_revalidation_signal_used';
        }
        if (($contextComparison['validating_carrier_match'] ?? true) === false) {
            $ctxVc = $this->sanitizeCarrier($context['validating_carrier'] ?? null);
            $priceVc = $airpriceDigest['validating_carrier'] ?? null;
            if ($ctxVc !== null && $priceVc !== null) {
                $allReasons[] = 'validating_carrier_mismatch';
            }
        }
        if (($brandDiagnostics['brand_match'] ?? null) === false) {
            $reason = (string) ($brandDiagnostics['brand_mismatch_reason'] ?? '');
            if ($reason === 'accepted_fare_brand_mismatch') {
                $allReasons[] = 'accepted_fare_brand_mismatch';
            } elseif ($reason !== '') {
                $allReasons[] = 'brand_context_payload_mismatch';
            }
        }

        $allReasons = array_values(array_unique($allReasons));
        $hardReasons = array_values(array_intersect($allReasons, self::HARD_RISK_REASONS));
        $warningReasons = array_values(array_intersect($allReasons, self::WARNING_RISK_REASONS));

        return [$hardReasons, $warningReasons];
    }

    /**
     * @param  list<string>  $hardReasons
     * @param  list<string>  $warningReasons
     * @param  array<string, mixed>  $structural
     * @param  array<string, mixed>  $context
     */
    protected function recommendedNextAction(array $hardReasons, array $warningReasons, array $structural, array $context): string
    {
        if (($context['wire_build_error'] ?? null) !== null) {
            return 'Fix booking context/readiness before rebuilding Passenger Records wire.';
        }
        if (($structural['has_air_book'] ?? false) !== true) {
            return 'AirBook block missing on wire — inspect payload builder and certified route style.';
        }
        if ($hardReasons !== []) {
            return 'Review AirBook segment sell rows and AirPrice qualifiers against selected context before any controlled PNR retry. Run sabre:inspect-controlled-pnr-application-error for host application signals.';
        }
        if ($warningReasons !== []) {
            return 'Payload structure appears aligned; warning signals present (legacy revalidation or missing linkage). Pair with sabre:inspect-controlled-pnr-application-error before controlled PNR retry.';
        }

        return 'Payload structure appears aligned with selected context. Pair with sabre:inspect-controlled-pnr-application-error; host may still reject inventory/fare combination.';
    }

    /**
     * @param  list<array<string, mixed>>  $contextSegments
     * @param  list<array<string, mixed>>  $payloadSegments
     */
    protected function compareCarrierChain(array $contextSegments, array $payloadSegments): bool
    {
        if (count($contextSegments) !== count($payloadSegments) || $payloadSegments === []) {
            return count($contextSegments) === count($payloadSegments);
        }
        foreach ($contextSegments as $i => $ctx) {
            $ctxCarrier = strtoupper(trim((string) (
                $ctx['marketing_carrier'] ?? $ctx['carrier'] ?? $ctx['marketing_airline'] ?? ''
            )));
            $payCarrier = strtoupper(trim((string) ($payloadSegments[$i]['marketing_carrier'] ?? '')));
            if ($ctxCarrier !== '' && $payCarrier !== '' && $ctxCarrier !== $payCarrier) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $contextSegments
     * @param  list<array<string, mixed>>  $payloadSegments
     */
    protected function compareSegmentOrder(array $contextSegments, array $payloadSegments): bool
    {
        if (count($contextSegments) !== count($payloadSegments)) {
            return false;
        }
        foreach ($contextSegments as $i => $ctx) {
            $ctxFn = trim((string) ($ctx['flight_number'] ?? ''));
            $payFn = trim((string) ($payloadSegments[$i]['flight_number'] ?? ''));
            if ($ctxFn !== '' && $payFn !== '' && $ctxFn !== $payFn) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $contextSegments
     * @param  list<array<string, mixed>>  $payloadSegments
     */
    protected function compareRbd(array $contextSegments, array $payloadSegments): bool
    {
        if (count($contextSegments) !== count($payloadSegments)) {
            return false;
        }
        foreach ($contextSegments as $i => $ctx) {
            $ctxRbd = strtoupper(trim((string) (
                $ctx['booking_class'] ?? $ctx['rbd'] ?? $ctx['res_book_desig_code'] ?? ''
            )));
            $payRbd = strtoupper(trim((string) (
                $payloadSegments[$i]['booking_class'] ?? $payloadSegments[$i]['res_book_desig_code'] ?? ''
            )));
            if ($ctxRbd !== '' && $payRbd !== '' && $ctxRbd !== $payRbd) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $contextSegments
     * @param  list<array<string, mixed>>  $payloadSegments
     */
    protected function compareRoute(array $contextSegments, array $payloadSegments): bool
    {
        if (count($contextSegments) !== count($payloadSegments)) {
            return false;
        }
        foreach ($contextSegments as $i => $ctx) {
            $ctxO = strtoupper(trim((string) ($ctx['origin'] ?? $ctx['departure_airport'] ?? '')));
            $ctxD = strtoupper(trim((string) ($ctx['destination'] ?? $ctx['arrival_airport'] ?? '')));
            $payO = strtoupper(trim((string) ($payloadSegments[$i]['origin'] ?? '')));
            $payD = strtoupper(trim((string) ($payloadSegments[$i]['destination'] ?? '')));
            if ($ctxO !== '' && $payO !== '' && $ctxO !== $payO) {
                return false;
            }
            if ($ctxD !== '' && $payD !== '' && $ctxD !== $payD) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $contextSegments
     * @param  list<array<string, mixed>>  $payloadSegments
     */
    protected function compareDates(array $contextSegments, array $payloadSegments): bool
    {
        if (count($contextSegments) !== count($payloadSegments)) {
            return false;
        }
        foreach ($contextSegments as $i => $ctx) {
            $ctxDep = trim((string) ($ctx['departure_datetime'] ?? $ctx['departure_at'] ?? ''));
            if ($ctxDep === '' && isset($ctx['departure_date'])) {
                $ctxDep = trim((string) $ctx['departure_date']).' '.trim((string) ($ctx['departure_time'] ?? '00:00'));
            }
            $payDep = trim((string) ($payloadSegments[$i]['departure_datetime'] ?? ''));
            if ($payDep === '' && isset($payloadSegments[$i]['departure_date'])) {
                $payDep = trim((string) $payloadSegments[$i]['departure_date']).' '.trim((string) ($payloadSegments[$i]['departure_time'] ?? '00:00'));
            }
            if ($ctxDep !== '' && $payDep !== '') {
                try {
                    if (Carbon::parse($ctxDep)->format('Y-m-d H:i') !== Carbon::parse($payDep)->format('Y-m-d H:i')) {
                        return false;
                    }
                } catch (\Throwable) {
                    if (substr($ctxDep, 0, 10) !== substr($payDep, 0, 10)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $contextSegments
     * @param  list<array<string, mixed>>  $payloadSegments
     */
    protected function fareBasisPresent(array $contextSegments, array $payloadSegments): bool
    {
        foreach ($contextSegments as $i => $ctx) {
            $ctxFb = trim((string) ($ctx['fare_basis_code'] ?? $ctx['fare_basis'] ?? ''));
            if ($ctxFb === '') {
                continue;
            }
            $payFb = trim((string) ($payloadSegments[$i]['fare_basis_snapshot'] ?? ''));
            if ($payFb === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{date: ?string, time: ?string}
     */
    protected function splitDatetime(string $datetime): array
    {
        if ($datetime === '') {
            return ['date' => null, 'time' => null];
        }
        try {
            $c = Carbon::parse($datetime);

            return ['date' => $c->format('Y-m-d'), 'time' => $c->format('H:i')];
        } catch (\Throwable) {
            if (preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})/', $datetime, $m)) {
                return ['date' => $m[1], 'time' => $m[2]];
            }

            return ['date' => substr($datetime, 0, 10), 'time' => null];
        }
    }

    protected function sanitizeCarrier(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return strtoupper(substr(trim($value), 0, 8));
    }

    protected function sanitizeBrand(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return strtoupper(substr(trim($value), 0, 32));
    }

    /**
     * @param  array<string, mixed>  $pricingQualifiers
     */
    protected function extractBrandCodeFromPricingQualifiers(array $pricingQualifiers): ?string
    {
        $brandNode = $pricingQualifiers['Brand'] ?? null;
        if (! is_array($brandNode) || $brandNode === []) {
            return null;
        }
        $rows = array_is_list($brandNode) ? $brandNode : [$brandNode];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                if (is_string($row) && trim($row) !== '') {
                    return strtoupper(substr(trim($row), 0, 32));
                }

                continue;
            }
            foreach (['content', 'Content', 'Code', 'code'] as $key) {
                $v = $row[$key] ?? null;
                if (is_string($v) && trim($v) !== '') {
                    return strtoupper(substr(trim($v), 0, 32));
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $keys
     */
    protected function safeScalarRef(array $node, array $keys): ?string
    {
        foreach ($keys as $key) {
            $v = $node[$key] ?? null;
            if (is_string($v) && trim($v) !== '') {
                return substr(trim($v), 0, 64);
            }
            if (is_scalar($v) && $v !== '') {
                return substr((string) $v, 0, 64);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $keys
     * @return list<string>
     */
    protected function safeRefList(array $node, array $keys): array
    {
        foreach ($keys as $key) {
            $v = $node[$key] ?? null;
            if (! is_array($v)) {
                continue;
            }
            $out = [];
            foreach ($v as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $out[] = substr(trim($item), 0, 32);
                } elseif (is_scalar($item)) {
                    $out[] = substr((string) $item, 0, 32);
                }
            }
            if ($out !== []) {
                return array_slice($out, 0, 8);
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    protected function keySample(array $node): array
    {
        if ($node === []) {
            return [];
        }

        return array_slice(array_values(array_map('strval', array_keys($node))), 0, self::KEY_SAMPLE_MAX);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stripForbiddenKeys(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $keyLower = strtolower((string) $key);
            $forbidden = false;
            foreach (self::FORBIDDEN_KEY_SUBSTRINGS as $frag) {
                if (str_contains($keyLower, $frag)) {
                    $forbidden = true;
                    break;
                }
            }
            if ($forbidden) {
                continue;
            }
            if (is_array($value)) {
                $out[$key] = $this->stripForbiddenKeys($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
