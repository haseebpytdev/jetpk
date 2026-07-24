<?php

namespace App\Support\Sabre\Scenario;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Gds\SabreStoredPricingContextDigest;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationCanonicalSegmentSignature;

/**
 * Sanitized exact-offer evidence and deterministic fingerprints for Sabre GDS scenario runner plan/book continuity.
 */
class SabreGdsLiveScenarioExactOfferEvidence
{
    public const REASON_EXACT_OFFER_LINKAGE_UNAVAILABLE = 'exact_offer_linkage_unavailable';

    public const REASON_EXACT_OFFER_FINGERPRINT_MISMATCH = 'exact_offer_fingerprint_mismatch';

    public const REASON_EXACT_OFFER_SOURCE_IDENTIFIER_MISMATCH = 'exact_offer_source_identifier_mismatch';

    public const REASON_EXACT_OFFER_SEGMENT_SIGNATURE_MISMATCH = 'exact_offer_segment_signature_mismatch';

    public const MISSING_SOURCE_IDENTIFIER_HASH = 'source_identifier_hash_missing';

    public const MISSING_SEGMENT_SIGNATURE = 'segment_signature_missing';

    public const MISSING_TOTAL = 'total_missing';

    public const MISSING_CURRENCY = 'currency_missing';

    public const MISSING_VALIDATING_CARRIER = 'validating_carrier_missing';

    public const MISSING_BOOKING_CLASSES_INCOMPLETE = 'booking_classes_incomplete';

    public const MISSING_PROVIDER_CONTEXT = 'provider_context_missing';

    public const MISSING_UNSUPPORTED_OFFER_SOURCE = 'unsupported_offer_source';

    public const MISSING_SAFE_OFFER_FINGERPRINT = 'safe_offer_fingerprint_missing';

    /** @var list<string> */
    public const SUPPORTED_OFFER_SOURCES = [
        'bfm_gds_priced_itinerary',
        'formal_ref_linkage',
    ];

    public function __construct(
        private readonly SabreStoredPricingContextDigest $pricingDigest,
        private readonly SabreGdsRevalidationCanonicalSegmentSignature $canonicalSegmentSignature,
    ) {}

    /**
     * @param  array<string, mixed>  $snap
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $selectedFareFamilyOption
     * @return array<string, mixed>
     */
    public function buildPlanCandidateEvidence(
        SupplierConnection $connection,
        array $snap,
        array $row,
        ?array $selectedFareFamilyOption = null,
        ?string $shopCapturedAt = null,
    ): array {
        return $this->buildLinkageContext($connection, $snap, $row, $selectedFareFamilyOption, $shopCapturedAt);
    }

    /**
     * @param  array<string, mixed>  $snap
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $selectedFareFamilyOption
     * @return array<string, mixed>
     */
    public function buildLinkageContext(
        SupplierConnection $connection,
        array $snap,
        array $row,
        ?array $selectedFareFamilyOption = null,
        ?string $shopCapturedAt = null,
    ): array {
        $readiness = $this->pricingDigest->assessReadiness($snap);
        $selectedTotal = $this->resolveSelectedTotal($row, $selectedFareFamilyOption);
        $currency = $this->resolveCurrency($row, $selectedFareFamilyOption, $snap);
        $validatingCarrier = strtoupper(trim((string) ($row['validating_carrier'] ?? $snap['validating_carrier'] ?? '')));
        $bookingClasses = $this->resolveBookingClasses($row, $selectedFareFamilyOption, $snap);
        $fareBasisCodes = $this->resolveFareBasisCodes($row, $selectedFareFamilyOption, $snap);
        $segmentCount = max(
            (int) ($row['segment_count'] ?? 0),
            count(is_array($snap['segments'] ?? null) ? $snap['segments'] : []),
        );
        $offerSource = $this->resolveOfferSource($readiness, $snap);
        $sourceIdentifierHash = $this->sourceOfferIdentifierHash($snap);
        $segmentSignature = $this->segmentSignature($snap, $row, $selectedFareFamilyOption, $bookingClasses, $fareBasisCodes);
        $offerIdentifierPresent = $sourceIdentifierHash !== null || $this->offerIdentifierPresent($snap, $readiness);

        $canonical = $this->canonicalFingerprintPayload(
            $connection,
            $validatingCarrier,
            $sourceIdentifierHash,
            $segmentSignature,
            $bookingClasses,
            $fareBasisCodes,
            $selectedTotal,
            $currency,
            $offerSource,
        );
        $fingerprint = $this->hashCanonical($canonical);
        $linkage = $this->assessLinkageReadiness(
            $connection,
            $selectedTotal,
            $currency,
            $validatingCarrier,
            $bookingClasses,
            $segmentCount,
            $offerSource,
            $sourceIdentifierHash,
            $segmentSignature,
            $fingerprint,
            $offerIdentifierPresent,
        );

        $schedule = $this->resolveScheduleBounds($snap);

        $result = array_filter([
            'selected_total' => $selectedTotal,
            'currency' => $currency,
            'validating_carrier' => $validatingCarrier !== '' ? $validatingCarrier : null,
            'segment_count' => $segmentCount > 0 ? $segmentCount : null,
            'stops' => $segmentCount > 0 ? max(0, $segmentCount - 1) : null,
            'departure_at' => $schedule['departure_at'],
            'arrival_at' => $schedule['arrival_at'],
            'brand_code' => strtoupper(trim((string) ($row['brand_code'] ?? ''))) ?: null,
            'booking_classes_by_segment' => $bookingClasses !== [] ? $bookingClasses : null,
            'fare_basis_codes_by_segment' => $fareBasisCodes !== [] ? $fareBasisCodes : null,
            'connection_id' => (int) $connection->id,
            'provider' => SupplierProvider::Sabre->value,
            'offer_source' => $offerSource,
            'offer_captured_at' => $shopCapturedAt,
            'shop_timestamp' => $shopCapturedAt,
            'source_identifier_hash' => $sourceIdentifierHash,
            'segment_signature' => $segmentSignature,
            'offer_identifier_present' => $offerIdentifierPresent,
            'safe_offer_fingerprint' => $fingerprint,
            'source_identifier_hash_present' => $sourceIdentifierHash !== null && $sourceIdentifierHash !== '',
            'source_identifier_hash_length' => $sourceIdentifierHash !== null ? strlen($sourceIdentifierHash) : 0,
            'segment_signature_present' => $segmentSignature !== null && $segmentSignature !== '',
            'segment_signature_length' => $segmentSignature !== null ? strlen($segmentSignature) : 0,
            'revalidation_linkage_ready' => $linkage['revalidation_linkage_ready'],
        ], static fn ($value) => $value !== null && $value !== [] && $value !== '');

        $result['revalidation_linkage_missing_components'] = $linkage['revalidation_linkage_missing_components'];

        return $result;
    }

    /**
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $snap
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $selectedFareFamilyOption
     */
    public function assertContinuityMatch(
        array $expected,
        SupplierConnection $connection,
        array $snap,
        array $row,
        ?array $selectedFareFamilyOption = null,
        ?string $shopCapturedAt = null,
    ): ?string {
        $actual = $this->buildLinkageContext($connection, $snap, $row, $selectedFareFamilyOption, $shopCapturedAt);

        if (($actual['revalidation_linkage_ready'] ?? false) !== true) {
            return self::REASON_EXACT_OFFER_LINKAGE_UNAVAILABLE;
        }

        $expectedSourceHash = trim((string) ($expected['source_identifier_hash'] ?? ''));
        $actualSourceHash = trim((string) ($actual['source_identifier_hash'] ?? ''));
        if ($expectedSourceHash !== '' && $actualSourceHash !== '' && $expectedSourceHash !== $actualSourceHash) {
            return self::REASON_EXACT_OFFER_SOURCE_IDENTIFIER_MISMATCH;
        }

        $expectedSegmentSignature = trim((string) ($expected['segment_signature'] ?? ''));
        $actualSegmentSignature = trim((string) ($actual['segment_signature'] ?? ''));
        if ($expectedSegmentSignature !== '' && $actualSegmentSignature !== '' && $expectedSegmentSignature !== $actualSegmentSignature) {
            return self::REASON_EXACT_OFFER_SEGMENT_SIGNATURE_MISMATCH;
        }

        $expectedFingerprint = trim((string) ($expected['safe_offer_fingerprint'] ?? ''));
        $actualFingerprint = trim((string) ($actual['safe_offer_fingerprint'] ?? ''));
        if ($expectedFingerprint !== '' && $actualFingerprint !== '' && $expectedFingerprint !== $actualFingerprint) {
            return self::REASON_EXACT_OFFER_FINGERPRINT_MISMATCH;
        }

        $expectedTotal = $expected['selected_total'] ?? null;
        $actualTotal = $actual['selected_total'] ?? null;
        if (is_numeric($expectedTotal) && is_numeric($actualTotal) && round((float) $expectedTotal, 2) !== round((float) $actualTotal, 2)) {
            return self::REASON_EXACT_OFFER_FINGERPRINT_MISMATCH;
        }

        $expectedCurrency = strtoupper(trim((string) ($expected['currency'] ?? '')));
        $actualCurrency = strtoupper(trim((string) ($actual['currency'] ?? '')));
        if ($expectedCurrency !== '' && $actualCurrency !== '' && $expectedCurrency !== $actualCurrency) {
            return self::REASON_EXACT_OFFER_FINGERPRINT_MISMATCH;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $snap
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $selectedFareFamilyOption
     */
    public function fingerprint(
        SupplierConnection $connection,
        array $snap,
        array $row,
        ?array $selectedFareFamilyOption,
        ?float $selectedTotal = null,
        ?string $currency = null,
        ?string $shopCapturedAt = null,
    ): string {
        $context = $this->buildLinkageContext($connection, $snap, $row, $selectedFareFamilyOption, $shopCapturedAt);
        if (is_numeric($selectedTotal) && (float) $selectedTotal > 0) {
            $context['selected_total'] = round((float) $selectedTotal, 2);
        }
        if (is_string($currency) && trim($currency) !== '') {
            $context['currency'] = strtoupper(trim($currency));
        }

        return (string) ($context['safe_offer_fingerprint'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $snap
     * @param  array<string, mixed>  $readiness
     */
    public function offerIdentifierPresent(array $snap, ?array $readiness = null): bool
    {
        return $this->sourceOfferIdentifierHash($snap) !== null;
    }

    /**
     * @param  array<string, mixed>  $snap
     */
    public function revalidationLinkageReady(array $snap): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $selectedFareFamilyOption
     */
    public function resolveSelectedTotal(array $row, ?array $selectedFareFamilyOption): ?float
    {
        $fromOption = $selectedFareFamilyOption['displayed_price']
            ?? $selectedFareFamilyOption['price_total']
            ?? null;
        if (is_numeric($fromOption) && (float) $fromOption > 0) {
            return round((float) $fromOption, 2);
        }

        $fromRow = $row['total_fare'] ?? null;

        return is_numeric($fromRow) && (float) $fromRow > 0 ? round((float) $fromRow, 2) : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $selectedFareFamilyOption
     * @param  array<string, mixed>  $snap
     */
    public function resolveCurrency(array $row, ?array $selectedFareFamilyOption, array $snap): ?string
    {
        foreach ([
            $selectedFareFamilyOption['currency'] ?? null,
            $row['currency'] ?? null,
            data_get($snap, 'fare_breakdown.currency'),
            data_get($snap, 'fare_breakdown.supplier_currency'),
        ] as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }
            $currency = strtoupper(substr(trim((string) $candidate), 0, 6));
            if ($currency !== '') {
                return $currency;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $bookingClasses
     * @param  list<string>  $fareBasisCodes
     */
    protected function segmentSignature(
        array $snap,
        array $row,
        ?array $selectedFareFamilyOption,
        array $bookingClasses,
        array $fareBasisCodes,
    ): ?string {
        $canonicalSegments = $this->canonicalSegmentRows($snap, $row, $selectedFareFamilyOption, $bookingClasses);
        if ($canonicalSegments === []) {
            return null;
        }

        return $this->canonicalSegmentSignature->hashFromSegments($canonicalSegments) ?: null;
    }

    /**
     * @param  list<string>  $bookingClasses
     * @return list<array<string, mixed>>
     */
    public function canonicalLinkageSegmentRows(
        array $snap,
        array $row,
        ?array $selectedFareFamilyOption,
        array $bookingClasses = [],
    ): array {
        return $this->canonicalSegmentRows($snap, $row, $selectedFareFamilyOption, $bookingClasses);
    }

    /**
     * @param  list<string>  $bookingClasses
     * @return list<array<string, mixed>>
     */
    protected function canonicalSegmentRows(
        array $snap,
        array $row,
        ?array $selectedFareFamilyOption,
        array $bookingClasses,
    ): array {
        $segments = is_array($snap['segments'] ?? null) ? array_values(array_filter($snap['segments'], 'is_array')) : [];
        if ($segments === []) {
            return [];
        }

        if ($bookingClasses === []) {
            $bookingClasses = $this->resolveBookingClasses($row, $selectedFareFamilyOption, $snap);
        }

        $rows = [];
        foreach ($segments as $index => $segment) {
            $marketing = $this->canonicalSegmentSignature->normalizeMarketingCarrier($segment);
            $operating = $this->canonicalSegmentSignature->normalizeOperatingCarrier($segment, $marketing);
            $rows[] = array_filter([
                'origin' => strtoupper(trim((string) ($segment['origin'] ?? ''))),
                'destination' => strtoupper(trim((string) ($segment['destination'] ?? ''))),
                'departure_at' => (string) ($segment['departure_at'] ?? ''),
                'arrival_at' => (string) ($segment['arrival_at'] ?? ''),
                'marketing_carrier' => $marketing,
                'operating_carrier' => $operating,
                'flight_number' => $this->canonicalSegmentSignature->normalizeFlightNumber((string) ($segment['flight_number'] ?? '')),
                'booking_class' => $this->canonicalSegmentSignature->normalizeBookingClass((string) (
                    $bookingClasses[$index]
                    ?? $segment['booking_class']
                    ?? ''
                )),
            ], static fn ($value) => $value !== null && $value !== '');
        }

        return $this->canonicalSegmentSignature->canonicalScheduleIdentityRows($rows);
    }

    /**
     * @param  list<string>  $bookingClasses
     * @param  list<string>  $fareBasisCodes
     * @return list<string>
     */
    protected function canonicalSegmentParts(
        array $snap,
        array $row,
        ?array $selectedFareFamilyOption,
        array $bookingClasses,
        array $fareBasisCodes,
    ): array {
        return $this->canonicalSegmentSignature->signaturePartsFromSegments(
            $this->canonicalSegmentRows($snap, $row, $selectedFareFamilyOption, $bookingClasses),
        );
    }

    /**
     * @param  list<string>  $bookingClasses
     * @param  list<string>  $fareBasisCodes
     * @return array<string, mixed>
     */
    protected function canonicalFingerprintPayload(
        SupplierConnection $connection,
        string $validatingCarrier,
        ?string $sourceIdentifierHash,
        ?string $segmentSignature,
        array $bookingClasses,
        array $fareBasisCodes,
        ?float $selectedTotal,
        ?string $currency,
        string $offerSource,
    ): array {
        $payload = [
            'supplier_connection_id' => (int) $connection->id,
            'provider' => SupplierProvider::Sabre->value,
            'validating_carrier' => $validatingCarrier,
            'source_identifier_hash' => $sourceIdentifierHash,
            'segment_signature' => $segmentSignature,
            'booking_classes_by_segment' => $bookingClasses,
            'selected_total' => $selectedTotal,
            'currency' => $currency,
            'offer_source' => $offerSource,
        ];
        if ($fareBasisCodes !== []) {
            $payload['fare_basis_codes_by_segment'] = $fareBasisCodes;
        }
        ksort($payload);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $canonical
     */
    protected function hashCanonical(array $canonical): string
    {
        ksort($canonical);

        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{
     *     revalidation_linkage_ready: bool,
     *     revalidation_linkage_missing_components: list<string>
     * }
     */
    protected function assessLinkageReadiness(
        SupplierConnection $connection,
        ?float $selectedTotal,
        ?string $currency,
        string $validatingCarrier,
        array $bookingClasses,
        int $segmentCount,
        string $offerSource,
        ?string $sourceIdentifierHash,
        ?string $segmentSignature,
        string $fingerprint,
        bool $offerIdentifierPresent,
    ): array {
        $missing = [];

        if ((int) $connection->id <= 0) {
            $missing[] = self::MISSING_PROVIDER_CONTEXT;
        }
        $provider = $connection->provider instanceof SupplierProvider
            ? $connection->provider->value
            : trim((string) ($connection->provider ?? ''));
        if ($provider === '') {
            $missing[] = self::MISSING_PROVIDER_CONTEXT;
        }
        if (! $offerIdentifierPresent || $sourceIdentifierHash === null || $sourceIdentifierHash === '') {
            $missing[] = self::MISSING_SOURCE_IDENTIFIER_HASH;
        }
        if ($segmentSignature === null || $segmentSignature === '') {
            $missing[] = self::MISSING_SEGMENT_SIGNATURE;
        }
        if (! is_numeric($selectedTotal) || (float) $selectedTotal <= 0) {
            $missing[] = self::MISSING_TOTAL;
        }
        if (! is_string($currency) || trim($currency) === '') {
            $missing[] = self::MISSING_CURRENCY;
        }
        if ($validatingCarrier === '') {
            $missing[] = self::MISSING_VALIDATING_CARRIER;
        }
        if ($segmentCount > 0 && (count($bookingClasses) !== $segmentCount || in_array('', $bookingClasses, true))) {
            $missing[] = self::MISSING_BOOKING_CLASSES_INCOMPLETE;
        }
        if (! in_array($offerSource, self::SUPPORTED_OFFER_SOURCES, true)) {
            $missing[] = self::MISSING_UNSUPPORTED_OFFER_SOURCE;
        }
        if ($fingerprint === '') {
            $missing[] = self::MISSING_SAFE_OFFER_FINGERPRINT;
        }

        return [
            'revalidation_linkage_ready' => $missing === [],
            'revalidation_linkage_missing_components' => array_values(array_unique($missing)),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $selectedFareFamilyOption
     * @param  array<string, mixed>  $snap
     * @return list<string>
     */
    protected function resolveBookingClasses(array $row, ?array $selectedFareFamilyOption, array $snap): array
    {
        $fromOption = is_array($selectedFareFamilyOption['booking_classes_by_segment'] ?? null)
            ? $selectedFareFamilyOption['booking_classes_by_segment']
            : [];
        $fromRow = is_array($row['booking_classes_by_segment'] ?? null) ? $row['booking_classes_by_segment'] : [];
        $handoff = $this->resolveHandoffContext($snap);
        $fromHandoff = is_array($handoff['booking_classes_by_segment'] ?? null) ? $handoff['booking_classes_by_segment'] : [];
        $fromSegments = $this->bookingClassesFromSegments($snap);

        return $this->capStringList($fromOption !== [] ? $fromOption : ($fromRow !== [] ? $fromRow : ($fromHandoff !== [] ? $fromHandoff : $fromSegments)));
    }

    /**
     * @param  array<string, mixed>  $snap
     * @return list<string>
     */
    protected function bookingClassesFromSegments(array $snap): array
    {
        $segments = is_array($snap['segments'] ?? null) ? array_values($snap['segments']) : [];
        $out = [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $value = strtoupper(trim((string) (
                $segment['booking_class']
                ?? $segment['class_of_service']
                ?? $segment['rbd']
                ?? $segment['booking_code']
                ?? ''
            )));
            if ($value !== '') {
                $out[] = $value;
            }
        }

        if ($out === [] && count($segments) === 1) {
            $fareLevel = strtoupper(trim((string) (
                data_get($snap, 'sabre_booking_context.booking_classes_by_segment.0')
                ?? data_get($snap, 'raw_payload.sabre_booking_context.booking_classes_by_segment.0')
                ?? data_get($snap, 'raw_payload.sabre_shop_context.booking_class.0')
                ?? data_get($snap, 'fare_breakdown.booking_class')
                ?? ''
            )));
            if ($fareLevel !== '') {
                $out[] = $fareLevel;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $selectedFareFamilyOption
     * @param  array<string, mixed>  $snap
     * @return list<string>
     */
    protected function resolveFareBasisCodes(array $row, ?array $selectedFareFamilyOption, array $snap): array
    {
        $fromOption = is_array($selectedFareFamilyOption['fare_basis_codes_by_segment'] ?? null)
            ? $selectedFareFamilyOption['fare_basis_codes_by_segment']
            : [];
        $fromRow = is_array($row['fare_basis_codes_by_segment'] ?? null) ? $row['fare_basis_codes_by_segment'] : [];
        $handoff = $this->resolveHandoffContext($snap);
        $fromHandoff = is_array($handoff['fare_basis_codes_by_segment'] ?? null) ? $handoff['fare_basis_codes_by_segment'] : [];

        return $this->capStringList($fromOption !== [] ? $fromOption : ($fromRow !== [] ? $fromRow : $fromHandoff));
    }

    /**
     * @param  array<string, mixed>  $snap
     * @return array<string, mixed>
     */
    protected function resolveHandoffContext(array $snap): array
    {
        $handoff = is_array($snap['sabre_booking_context'] ?? null) ? $snap['sabre_booking_context'] : [];
        $raw = is_array($snap['raw_payload'] ?? null) ? $snap['raw_payload'] : [];
        $rawHandoff = is_array($raw['sabre_booking_context'] ?? null) ? $raw['sabre_booking_context'] : [];

        return $handoff !== [] ? $handoff : $rawHandoff;
    }

    /**
     * @param  array<string, mixed>  $snap
     * @return array<string, mixed>
     */
    protected function resolveShopContext(array $snap): array
    {
        $raw = is_array($snap['raw_payload'] ?? null) ? $snap['raw_payload'] : [];
        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $top = is_array($snap['sabre_shop_context'] ?? null) ? $snap['sabre_shop_context'] : [];

        return $ctx !== [] ? $ctx : $top;
    }

    /**
     * @param  array<string, mixed>  $snap
     * @return array<string, mixed>
     */
    protected function resolveShopIdentifiers(array $snap): array
    {
        $raw = is_array($snap['raw_payload'] ?? null) ? $snap['raw_payload'] : [];
        $ids = is_array($raw['sabre_shop_identifiers'] ?? null) ? $raw['sabre_shop_identifiers'] : [];
        $top = is_array($snap['sabre_shop_identifiers'] ?? null) ? $snap['sabre_shop_identifiers'] : [];

        return $ids !== [] ? $ids : $top;
    }

    /**
     * @param  array<string, mixed>  $snap
     * @return array{departure_at: string|null, arrival_at: string|null}
     */
    protected function resolveScheduleBounds(array $snap): array
    {
        $segments = is_array($snap['segments'] ?? null) ? array_values(array_filter($snap['segments'], 'is_array')) : [];
        if ($segments === []) {
            return ['departure_at' => null, 'arrival_at' => null];
        }

        $first = $segments[0];
        $last = $segments[count($segments) - 1];

        return [
            'departure_at' => $this->safeDateTime((string) ($first['departure_at'] ?? '')),
            'arrival_at' => $this->safeDateTime((string) ($last['arrival_at'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $readiness
     * @param  array<string, mixed>  $snap
     */
    protected function resolveOfferSource(array $readiness, array $snap): string
    {
        $policy = trim((string) ($readiness['pricing_context_policy'] ?? ''));
        if (in_array($policy, ['bfm_gds_priced_itinerary', 'bfm_gds_priced_itinerary_incomplete'], true)) {
            return 'bfm_gds_priced_itinerary';
        }
        if ($policy === 'formal_ref_linkage') {
            return 'formal_ref_linkage';
        }
        if ($policy !== '') {
            return $policy;
        }

        $handoff = $this->resolveHandoffContext($snap);
        $channel = strtolower(trim((string) ($handoff['distribution_channel'] ?? $snap['distribution_channel'] ?? 'gds')));

        return $channel === 'gds' ? 'bfm_gds_priced_itinerary' : 'sabre_gds_shop';
    }

    /**
     * @param  array<string, mixed>  $snap
     */
    protected function sourceOfferIdentifierHash(array $snap): ?string
    {
        $ctx = $this->resolveShopContext($snap);
        $handoff = $this->resolveHandoffContext($snap);
        $ids = $this->resolveShopIdentifiers($snap);

        foreach ([
            (string) ($snap['supplier_offer_id'] ?? ''),
            (string) ($snap['offer_id'] ?? ''),
            (string) ($ctx['pricing_information_ref'] ?? ''),
            (string) ($ctx['offer_ref'] ?? ''),
            (string) ($ctx['offer_reference'] ?? ''),
            (string) ($handoff['pricing_information_ref'] ?? ''),
            (string) ($handoff['offer_reference'] ?? ''),
            (string) ($handoff['offer_ref'] ?? ''),
            (string) ($ids['offer_item_id'] ?? ''),
            (string) ($ids['offer_reference'] ?? ''),
        ] as $identifier) {
            $identifier = trim($identifier);
            if ($identifier !== '') {
                return hash('sha256', $identifier);
            }
        }

        $itineraryRef = trim((string) (
            $ctx['itinerary_ref']
            ?? $handoff['itinerary_reference']
            ?? $handoff['itinerary_ref']
            ?? $ids['itinerary_id']
            ?? $ids['itinerary_ref']
            ?? ''
        ));
        $pricingIndex = $ctx['pricing_information_index']
            ?? $handoff['pricing_information_index']
            ?? $ids['pricing_information_index']
            ?? null;

        if ($itineraryRef !== '' && is_numeric($pricingIndex)) {
            return hash('sha256', $itineraryRef.'|'.(int) $pricingIndex);
        }

        if ($itineraryRef !== '') {
            return hash('sha256', $itineraryRef);
        }

        return null;
    }

    protected function safeDateTime(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return substr($value, 0, 19);
    }

    /**
     * @param  list<mixed>  $list
     * @return list<string>
     */
    protected function capStringList(array $list): array
    {
        $out = [];
        foreach (array_slice($list, 0, 12) as $item) {
            if (! is_scalar($item)) {
                continue;
            }
            $value = trim((string) $item);
            if ($value !== '') {
                $out[] = substr($value, 0, 16);
            }
        }

        return $out;
    }
}
