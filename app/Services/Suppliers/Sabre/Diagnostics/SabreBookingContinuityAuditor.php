<?php

namespace App\Services\Suppliers\Sabre\Diagnostics;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder;
use App\Services\Suppliers\Sabre\Gds\SabreStoredPricingContextDigest;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreHostErrorClassifier;
use App\Support\Bookings\SabrePnrCertificationSupport;

/**
 * Sprint 11K-B/C/C2: Passive continuity audit from stored snapshot → shop/booking context → revalidation draft → PNR draft,
 * plus optional host outcome overlay from stored {@see sabre_checkout_outcome} metadata (evidence-based host rejection;
 * {@see SabreCertifiedRouteSelector::ERROR_CODE_PENDING} is an internal gate, not host rejection).
 * No live Sabre HTTP, no booking behavior changes, no raw payloads or PII.
 */
final class SabreBookingContinuityAuditor
{
    public const REPORT_VERSION = 'booking_continuity_audit_v1';

    public const HOST_OUTCOME_STATUS_NONE = 'none';

    public const HOST_OUTCOME_STATUS_SUCCESS = 'success';

    public const HOST_OUTCOME_STATUS_FAILED = 'failed';

    public const HOST_OUTCOME_STATUS_SKIPPED = 'skipped';

    public const HOST_ERROR_FAMILY_NONE = 'NONE';

    public const HOST_ERROR_FAMILY_UC_SEGMENT_STATUS = 'UC_SEGMENT_STATUS';

    public const HOST_ERROR_FAMILY_HOST_SEGMENT_STATUS = 'HOST_SEGMENT_STATUS';

    public const HOST_ERROR_FAMILY_NO_FARES_RBD_CARRIER = 'NO_FARES_RBD_CARRIER';

    public const HOST_ERROR_FAMILY_HOST_VALIDATION_FAILURE = 'HOST_VALIDATION_FAILURE';

    public const HOST_ERROR_FAMILY_REVALIDATION_FAILED = 'REVALIDATION_FAILED';

    public const HOST_ERROR_FAMILY_REVALIDATION_SKIPPED = 'REVALIDATION_SKIPPED';

    public const HOST_ERROR_FAMILY_UNKNOWN = 'UNKNOWN_HOST_ERROR';

    public const HOST_ERROR_FAMILY_CERTIFIED_ROUTE_PENDING = 'CERTIFIED_ROUTE_PENDING';

    public const FINAL_REC_BLOCKED_HOST_REJECTED = 'blocked_host_rejected_after_local_continuity';

    /** @var list<string> */
    public const SOURCE_KEYS = [
        'normalized_snapshot',
        'sabre_booking_context',
        'sabre_shop_context',
        'refreshed_offer_snapshot',
        'revalidation_linkage',
        'pnr_draft',
    ];

    /** @var list<string> */
    public const READINESS_VALUES = [
        'auto_pnr_safe',
        'manual_review_required',
        'blocked_missing_rbd',
        'blocked_missing_fare_basis',
        'blocked_validating_carrier_mismatch',
        'blocked_segment_mismatch',
        'blocked_stale_revalidation',
        self::FINAL_REC_BLOCKED_HOST_REJECTED,
    ];

    public function __construct(
        protected SabreStoredPricingContextDigest $digestor,
        protected SabreRevalidationPayloadBuilder $revalidationBuilder,
        protected SabreBookingService $sabreBooking,
        protected SabrePnrCertificationSupport $certificationSupport,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function audit(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        if ($provider !== SupplierProvider::Sabre->value) {
            return [
                'report_version' => self::REPORT_VERSION,
                'booking_id' => $booking->id,
                'error' => 'booking_not_sabre',
            ];
        }

        $normalized = $this->resolvePrimarySnapshot($meta);
        if ($normalized === []) {
            return [
                'report_version' => self::REPORT_VERSION,
                'booking_id' => $booking->id,
                'error' => 'no_offer_snapshot',
            ];
        }

        $mergedSnapshot = $this->mergeSnapshotForDraft($booking, $normalized);
        $sources = $this->collectSourceSlices($booking, $meta, $normalized, $mergedSnapshot);
        $rows = $this->buildContinuityRows($sources);
        $readiness = $this->resolveReadinessRecommendation($sources, $rows, $meta, $mergedSnapshot);
        $hostOverlay = $this->resolveHostOutcomeOverlay($meta, (string) $readiness['recommendation'], $booking);

        $report = [
            'report_version' => self::REPORT_VERSION,
            'booking_id' => $booking->id,
            'supplier' => SupplierProvider::Sabre->value,
            'sources_present' => $this->sourcesPresentMap($sources),
            'continuity_rows' => $rows,
            'readiness_recommendation' => $readiness['recommendation'],
            'readiness_reasons' => $readiness['reasons'],
            'pricing_context_ready' => ($this->digestor->assessReadiness($mergedSnapshot)['auto_pnr_pricing_context_ready'] ?? false) === true,
            'host_outcome_overlay' => $hostOverlay,
            'final_diagnostic_recommendation' => (string) ($hostOverlay['final_diagnostic_recommendation'] ?? $readiness['recommendation']),
        ];

        $this->certificationSupport->assertOutputSafe($report);

        return $report;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function resolvePrimarySnapshot(array $meta): array
    {
        foreach (['normalized_offer_snapshot', 'validated_offer_snapshot', 'flight_offer_snapshot'] as $key) {
            $snap = $meta[$key] ?? null;
            if (is_array($snap) && $snap !== []) {
                return $snap;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    protected function mergeSnapshotForDraft(Booking $booking, array $snapshot): array
    {
        $reflection = new \ReflectionClass($this->sabreBooking);
        $merge = $reflection->getMethod('mergePublicReviewSabreSnapshotFromBooking');
        $merge->setAccessible(true);

        return $merge->invoke($this->sabreBooking, $booking, $snapshot);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>  $mergedSnapshot
     * @return array<string, array<string, mixed>>
     */
    protected function collectSourceSlices(
        Booking $booking,
        array $meta,
        array $normalized,
        array $mergedSnapshot,
    ): array {
        $rawNorm = is_array($normalized['raw_payload'] ?? null) ? $normalized['raw_payload'] : [];
        $shopCtx = is_array($rawNorm['sabre_shop_context'] ?? null) ? $rawNorm['sabre_shop_context'] : [];
        $bookingCtx = is_array($rawNorm['sabre_booking_context'] ?? null)
            ? $rawNorm['sabre_booking_context']
            : (is_array($normalized['sabre_booking_context'] ?? null) ? $normalized['sabre_booking_context'] : []);

        $refreshed = $this->resolveRefreshedSnapshotSlice($meta, $normalized);
        $revalidation = $this->resolveRevalidationLinkageSlice($booking, $meta, $mergedSnapshot);
        $pnrDraft = $this->resolvePnrDraftSlice($booking, $mergedSnapshot);

        return [
            'normalized_snapshot' => $this->sliceFromSnapshot($normalized, 'normalized_snapshot'),
            'sabre_booking_context' => $this->sliceFromContextMaps($bookingCtx, $normalized, 'sabre_booking_context'),
            'sabre_shop_context' => $this->sliceFromContextMaps($shopCtx, $normalized, 'sabre_shop_context'),
            'refreshed_offer_snapshot' => $refreshed,
            'revalidation_linkage' => $revalidation,
            'pnr_draft' => $pnrDraft,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    protected function sliceFromSnapshot(array $snapshot, string $sourceKey): array
    {
        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $handoff = is_array($raw['sabre_booking_context'] ?? null) ? $raw['sabre_booking_context'] : [];

        return $this->composeSlice(
            $sourceKey,
            $snapshot,
            $ctx,
            $handoff,
            true,
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    protected function sliceFromContextMaps(array $ctx, array $snapshot, string $sourceKey): array
    {
        $handoff = $sourceKey === 'sabre_booking_context' ? $ctx : [];
        $shop = $sourceKey === 'sabre_shop_context' ? $ctx : [];

        return $this->composeSlice(
            $sourceKey,
            $snapshot,
            $shop,
            $handoff,
            $ctx !== [],
        );
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $shopCtx
     * @param  array<string, mixed>  $handoff
     * @return array<string, mixed>
     */
    protected function composeSlice(
        string $sourceKey,
        array $snapshot,
        array $shopCtx,
        array $handoff,
        bool $layerPresent,
    ): array {
        $segments = array_values(is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : []);
        $segmentCount = count($segments);

        $itinRef = $this->firstNonEmptyScalar([
            $shopCtx['itinerary_ref'] ?? null,
            $handoff['itinerary_reference'] ?? null,
            $handoff['itinerary_ref'] ?? null,
        ]);
        $piIndex = $this->firstNumeric([
            $shopCtx['pricing_information_index'] ?? null,
            $handoff['pricing_information_index'] ?? null,
        ]);
        $vc = strtoupper(trim((string) (
            $shopCtx['validating_carrier']
            ?? $handoff['validating_carrier']
            ?? $snapshot['validating_carrier']
            ?? ''
        )));

        $rbd = $this->perSegmentStrings($handoff, $shopCtx, $segments, 'booking_classes_by_segment', 'booking');
        $fbc = $this->perSegmentStrings($handoff, $shopCtx, $segments, 'fare_basis_codes_by_segment', 'fare_basis');
        $legRefs = $this->intList($shopCtx['leg_refs'] ?? $handoff['leg_refs'] ?? []);
        $scheduleRefs = $this->intList($shopCtx['schedule_refs'] ?? $handoff['schedule_refs'] ?? []);

        $fare = is_array($snapshot['fare_breakdown'] ?? null) ? $snapshot['fare_breakdown'] : [];
        $price = isset($fare['supplier_total']) && is_numeric($fare['supplier_total']) ? round((float) $fare['supplier_total'], 2) : null;
        $currency = isset($fare['currency']) ? strtoupper(substr(trim((string) $fare['currency']), 0, 6)) : null;

        return [
            'source' => $sourceKey,
            'present' => $layerPresent,
            'segment_count' => $segmentCount > 0 ? $segmentCount : null,
            'itinerary_ref' => $itinRef,
            'pricing_information_index' => $piIndex,
            'validating_carrier' => $vc !== '' ? $vc : null,
            'booking_classes_by_segment' => $rbd,
            'fare_basis_codes_by_segment' => $fbc,
            'leg_refs' => $legRefs,
            'schedule_refs' => $scheduleRefs,
            'segment_fingerprints' => $this->segmentFingerprints($segments),
            'marketing_carriers' => $this->carrierChain($segments, 'marketing'),
            'operating_carriers' => $this->carrierChain($segments, 'operating'),
            'price_total' => $price,
            'currency' => $currency !== '' ? $currency : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    protected function resolveRefreshedSnapshotSlice(array $meta, array $normalized): array
    {
        $validated = is_array($meta['validated_offer_snapshot'] ?? null) ? $meta['validated_offer_snapshot'] : [];
        $refreshStatus = strtolower(trim((string) ($meta['offer_refresh_status'] ?? '')));
        $hasRefresh = $refreshStatus === 'refreshed' || ($meta['offer_refresh_accepted'] ?? false) === true;

        if ($validated === [] && ! $hasRefresh) {
            return $this->emptySlice('refreshed_offer_snapshot');
        }

        $target = $validated !== [] ? $validated : $normalized;
        $slice = $this->sliceFromSnapshot($target, 'refreshed_offer_snapshot');
        $slice['present'] = $hasRefresh || ($validated !== [] && $this->snapshotsDiffer($normalized, $validated));

        return $slice;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>  $validated
     */
    protected function snapshotsDiffer(array $normalized, array $validated): bool
    {
        $a = $this->sliceFromSnapshot($normalized, 'normalized_snapshot');
        $b = $this->sliceFromSnapshot($validated, 'refreshed_offer_snapshot');

        foreach (['segment_count', 'itinerary_ref', 'pricing_information_index', 'validating_carrier'] as $key) {
            if (($a[$key] ?? null) !== ($b[$key] ?? null)) {
                return true;
            }
        }

        return ($a['segment_fingerprints'] ?? []) !== ($b['segment_fingerprints'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $mergedSnapshot
     * @return array<string, mixed>
     */
    protected function resolveRevalidationLinkageSlice(Booking $booking, array $meta, array $mergedSnapshot): array
    {
        $stored = is_array($meta['sabre_revalidate_inspect'] ?? null) ? $meta['sabre_revalidate_inspect'] : [];
        $cert = is_array($meta[SabrePnrCertificationSupport::META_CERTIFICATION_REVALIDATE_LINKAGE] ?? null)
            ? $meta[SabrePnrCertificationSupport::META_CERTIFICATION_REVALIDATE_LINKAGE]
            : [];

        $draftSlice = $this->emptySlice('revalidation_linkage');
        $draft = $this->resolveInternalDraft($booking, $mergedSnapshot);
        if ($draft !== null) {
            $payload = $this->revalidationBuilder->buildPayload($draft);
            $summary = $this->revalidationBuilder->safePayloadSummary($payload);
            $draftSegments = array_values(is_array($draft['segments'] ?? null) ? $draft['segments'] : []);
            $draftSlice = [
                'source' => 'revalidation_linkage',
                'present' => true,
                'segment_count' => isset($summary['segment_count']) ? (int) $summary['segment_count'] : null,
                'itinerary_ref' => $this->capScalar($summary['itinerary_reference'] ?? $summary['itinerary_ref'] ?? null),
                'pricing_information_index' => $this->firstNumeric([
                    $summary['pricing_information_index'] ?? null,
                    data_get($payload, '_ota_pricing_information_index'),
                ]),
                'validating_carrier' => $this->capCarrier($summary['validating_carrier'] ?? $draft['validating_carrier'] ?? null),
                'booking_classes_by_segment' => $this->capStringList($summary['booking_classes'] ?? $summary['booking_classes_by_segment'] ?? []),
                'fare_basis_codes_by_segment' => $this->capStringList($summary['fare_basis_codes'] ?? $summary['fare_basis_codes_by_segment'] ?? []),
                'leg_refs' => $this->intList($summary['leg_refs'] ?? data_get($draft, '_sabre_shop_context.leg_refs', [])),
                'schedule_refs' => $this->intList($summary['schedule_refs'] ?? data_get($draft, '_sabre_shop_context.schedule_refs', [])),
                'segment_fingerprints' => $this->segmentFingerprints($draftSegments),
                'marketing_carriers' => $this->carrierChain($draftSegments, 'marketing'),
                'operating_carriers' => $this->carrierChain($draftSegments, 'operating'),
                'price_total' => null,
                'currency' => $this->capScalar($summary['currency'] ?? null),
                'revalidation_success' => null,
                'captured_at' => null,
            ];
        }

        if ($stored !== []) {
            $draftSlice['present'] = true;
            $draftSlice['revalidation_success'] = ($stored['revalidation_success'] ?? false) === true;
            $draftSlice['captured_at'] = $this->capScalar($stored['captured_at'] ?? null);
            if (isset($stored['revalidated_total']) && is_numeric($stored['revalidated_total'])) {
                $draftSlice['price_total'] = round((float) $stored['revalidated_total'], 2);
            }
            if (isset($stored['revalidated_currency'])) {
                $draftSlice['currency'] = $this->capScalar($stored['revalidated_currency']);
            }
        }

        if ($cert !== []) {
            $draftSlice['present'] = true;
            $draftSlice['validating_carrier'] = $draftSlice['validating_carrier']
                ?? $this->capCarrier($cert['validating_carrier'] ?? null);
            $draftSlice['itinerary_ref'] = $draftSlice['itinerary_ref']
                ?? $this->capScalar($cert['itinerary_reference'] ?? $cert['itinerary_ref'] ?? null);
            $draftSlice['pricing_information_index'] = $draftSlice['pricing_information_index']
                ?? $this->firstNumeric([$cert['pricing_information_index'] ?? null]);
        }

        if (! ($draftSlice['present'] ?? false)) {
            return $this->emptySlice('revalidation_linkage');
        }

        return $draftSlice;
    }

    /**
     * @param  array<string, mixed>  $mergedSnapshot
     * @return array<string, mixed>
     */
    protected function resolvePnrDraftSlice(Booking $booking, array $mergedSnapshot): array
    {
        $segSell = $this->sabreBooking->inspectPassengerRecordsAirBookSegmentSellDiagnosticsForCommand($booking);
        if (isset($segSell['error'])) {
            $draft = $this->resolveInternalDraft($booking, $mergedSnapshot);
            if ($draft === null) {
                return $this->emptySlice('pnr_draft');
            }

            return $this->sliceFromDraft($draft);
        }

        $rows = is_array($segSell['segments'] ?? null) ? $segSell['segments'] : [];
        $segmentCount = (int) ($segSell['segment_count'] ?? count($rows));
        $rbd = [];
        $fbc = [];
        $fingerprints = [];
        $marketing = [];
        $operating = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rbd[] = strtoupper(trim((string) ($row['res_book_desig_code'] ?? $row['booking_class'] ?? '')));
            $fbc[] = strtoupper(trim((string) ($row['fare_basis_snapshot'] ?? $row['fare_basis_code'] ?? '')));
            $origin = strtoupper(trim((string) ($row['origin'] ?? '')));
            $dest = strtoupper(trim((string) ($row['destination'] ?? '')));
            $date = substr(trim((string) ($row['departure_datetime'] ?? '')), 0, 10);
            $m = strtoupper(trim((string) ($row['marketing_airline'] ?? $row['marketing_carrier'] ?? '')));
            if ($origin !== '' && $dest !== '') {
                $fingerprints[] = $origin.'-'.$dest.'|'.$date.'|'.$m;
            }
            $o = strtoupper(trim((string) ($row['operating_airline'] ?? $row['operating_carrier'] ?? '')));
            if ($m !== '') {
                $marketing[] = $m;
            }
            if ($o !== '') {
                $operating[] = $o;
            }
        }

        $fareCtx = $this->sabreBooking->inspectPassengerRecordsFareContextDiagnosticsForCommand($booking);
        $vc = $this->capCarrier($fareCtx['validating_carrier_sanitized'] ?? $fareCtx['validating_carrier'] ?? null);

        return [
            'source' => 'pnr_draft',
            'present' => true,
            'segment_count' => $segmentCount > 0 ? $segmentCount : null,
            'itinerary_ref' => null,
            'pricing_information_index' => isset($fareCtx['pricing_information_index'])
                ? $this->firstNumeric([$fareCtx['pricing_information_index']])
                : null,
            'validating_carrier' => $vc,
            'booking_classes_by_segment' => array_values(array_filter($rbd, static fn (string $s): bool => $s !== '')),
            'fare_basis_codes_by_segment' => array_values(array_filter($fbc, static fn (string $s): bool => $s !== '')),
            'leg_refs' => [],
            'schedule_refs' => [],
            'segment_fingerprints' => array_values(array_filter($fingerprints, static fn (string $s): bool => $s !== '')),
            'marketing_carriers' => $marketing,
            'operating_carriers' => $operating,
            'price_total' => null,
            'currency' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    protected function sliceFromDraft(array $draft): array
    {
        $segments = array_values(is_array($draft['segments'] ?? null) ? $draft['segments'] : []);
        $rbd = [];
        $fbc = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $rbd[] = strtoupper(trim((string) ($seg['booking_class'] ?? $seg['class_of_service'] ?? '')));
            $fbc[] = strtoupper(trim((string) ($seg['fare_basis_code'] ?? '')));
        }
        $fare = is_array($draft['fare'] ?? null) ? $draft['fare'] : [];

        return [
            'source' => 'pnr_draft',
            'present' => true,
            'segment_count' => count($segments) > 0 ? count($segments) : null,
            'itinerary_ref' => null,
            'pricing_information_index' => $this->firstNumeric([
                data_get($draft, '_sabre_shop_context.pricing_information_index'),
            ]),
            'validating_carrier' => $this->capCarrier($draft['validating_carrier'] ?? null),
            'booking_classes_by_segment' => array_values(array_filter($rbd, static fn (string $s): bool => $s !== '')),
            'fare_basis_codes_by_segment' => array_values(array_filter($fbc, static fn (string $s): bool => $s !== '')),
            'leg_refs' => $this->intList(data_get($draft, '_sabre_shop_context.leg_refs', [])),
            'schedule_refs' => $this->intList(data_get($draft, '_sabre_shop_context.schedule_refs', [])),
            'segment_fingerprints' => $this->segmentFingerprints($segments),
            'marketing_carriers' => $this->carrierChain($segments, 'marketing'),
            'operating_carriers' => $this->carrierChain($segments, 'operating'),
            'price_total' => isset($fare['amount']) && is_numeric($fare['amount']) ? round((float) $fare['amount'], 2) : null,
            'currency' => $this->capScalar($fare['currency'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $mergedSnapshot
     * @return array<string, mixed>|null
     */
    protected function resolveInternalDraft(Booking $booking, array $mergedSnapshot): ?array
    {
        $reflection = new \ReflectionClass($this->sabreBooking);
        $passengerData = $reflection->getMethod('passengerDataFromBooking');
        $passengerData->setAccessible(true);
        $draft = $this->sabreBooking->prepareBookingPayload($mergedSnapshot, $passengerData->invoke($this->sabreBooking, $booking));
        if (! is_array($draft) || ($draft['_valid'] ?? false) !== true) {
            return null;
        }
        unset($draft['_valid']);

        return $draft;
    }

    /**
     * @param  array<string, array<string, mixed>>  $sources
     * @return list<array<string, mixed>>
     */
    protected function buildContinuityRows(array $sources): array
    {
        $fieldDefs = [
            'segment_count' => 'scalar',
            'itinerary_ref' => 'scalar',
            'pricing_information_index' => 'scalar',
            'validating_carrier' => 'scalar',
            'booking_classes_by_segment' => 'list',
            'fare_basis_codes_by_segment' => 'list',
            'leg_refs' => 'list',
            'schedule_refs' => 'list',
            'segment_fingerprints' => 'list',
            'marketing_carriers' => 'list',
            'operating_carriers' => 'list',
            'price_total' => 'scalar',
            'currency' => 'scalar',
        ];

        $rows = [];
        foreach ($fieldDefs as $field => $kind) {
            $rows[] = $this->buildRow($field, $kind, $sources);
        }

        return $rows;
    }

    /**
     * @param  array<string, array<string, mixed>>  $sources
     * @return array<string, mixed>
     */
    protected function buildRow(string $field, string $kind, array $sources): array
    {
        $valuesBySource = [];
        $presentValues = [];
        foreach (self::SOURCE_KEYS as $sourceKey) {
            $slice = $sources[$sourceKey] ?? [];
            if (($slice['present'] ?? false) !== true) {
                $valuesBySource[$sourceKey] = null;

                continue;
            }
            $raw = $slice[$field] ?? null;
            $display = $this->formatFieldValue($raw, $kind);
            $valuesBySource[$sourceKey] = $display;
            if ($display !== null && $display !== '' && $display !== '—') {
                $presentValues[$sourceKey] = $raw;
            }
        }

        $status = $this->resolveFieldStatus($field, $kind, $presentValues, $sources);
        $authority = $this->resolveFieldAuthority($presentValues, $sources);

        return [
            'field' => $field,
            'status' => $status,
            'authority' => $authority,
            'values_by_source' => $valuesBySource,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function formatFieldValue(mixed $raw, string $kind): ?string
    {
        if ($raw === null) {
            return null;
        }
        if ($kind === 'scalar') {
            if (is_bool($raw)) {
                return $raw ? 'true' : 'false';
            }
            if (is_numeric($raw)) {
                return (string) $raw;
            }

            return trim((string) $raw) !== '' ? substr(trim((string) $raw), 0, 64) : null;
        }
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        return implode(',', array_slice(array_map(static fn ($v): string => substr(trim((string) $v), 0, 16), $raw), 0, 12));
    }

    /**
     * @param  array<string, mixed>  $presentValues
     * @param  array<string, array<string, mixed>>  $sources
     */
    protected function resolveFieldStatus(string $field, string $kind, array $presentValues, array $sources): string
    {
        if ($presentValues === []) {
            return 'missing';
        }

        $canonical = array_values($presentValues)[0];
        $allMatch = true;
        foreach ($presentValues as $value) {
            if ($kind === 'list') {
                if ($this->normalizeList($value) !== $this->normalizeList($canonical)) {
                    $allMatch = false;
                    break;
                }
            } elseif ((string) $value !== (string) $canonical) {
                $allMatch = false;
                break;
            }
        }

        if (! $allMatch) {
            if ($field === 'segment_fingerprints' || $field === 'segment_count') {
                return 'mismatched';
            }

            $reval = $sources['revalidation_linkage'] ?? [];
            $refreshed = $sources['refreshed_offer_snapshot'] ?? [];
            if (($reval['present'] ?? false) === true
                && ($refreshed['present'] ?? false) === true
                && in_array($field, ['itinerary_ref', 'pricing_information_index', 'validating_carrier', 'booking_classes_by_segment', 'fare_basis_codes_by_segment'], true)) {
                return 'stale';
            }

            return 'mismatched';
        }

        return 'present';
    }

    /**
     * @param  array<string, mixed>  $presentValues
     * @param  array<string, array<string, mixed>>  $sources
     */
    protected function resolveFieldAuthority(array $presentValues, array $sources): string
    {
        $priority = [
            'pnr_draft',
            'revalidation_linkage',
            'refreshed_offer_snapshot',
            'sabre_booking_context',
            'sabre_shop_context',
            'normalized_snapshot',
        ];
        foreach ($priority as $key) {
            if (array_key_exists($key, $presentValues) && ($sources[$key]['present'] ?? false) === true) {
                return $key;
            }
        }

        return $presentValues === [] ? 'unknown' : array_key_first($presentValues);
    }

    /**
     * @param  array<string, array<string, mixed>>  $sources
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $mergedSnapshot
     * @return array{recommendation: string, reasons: list<string>}
     */
    protected function resolveReadinessRecommendation(
        array $sources,
        array $rows,
        array $meta,
        array $mergedSnapshot,
    ): array {
        $reasons = [];
        $rowByField = [];
        foreach ($rows as $row) {
            $rowByField[(string) ($row['field'] ?? '')] = $row;
        }

        if ($this->itinerarySourcesMismatch($sources, 'segment_count')) {
            return ['recommendation' => 'blocked_segment_mismatch', 'reasons' => ['segment_count_mismatch_across_layers']];
        }

        if ($this->itinerarySourcesMismatch($sources, 'segment_fingerprints')) {
            return ['recommendation' => 'blocked_segment_mismatch', 'reasons' => ['segment_route_mismatch_across_layers']];
        }

        $normCount = (int) ($sources['normalized_snapshot']['segment_count'] ?? 0);
        if ($normCount > 0 && ! $this->perSegmentListComplete($this->mergedRbdAcrossLayers($sources), $normCount)) {
            return ['recommendation' => 'blocked_missing_rbd', 'reasons' => ['booking_classes_by_segment_incomplete']];
        }

        if ($normCount > 0 && ! $this->perSegmentListComplete($this->mergedFareBasisAcrossLayers($sources), $normCount)) {
            return ['recommendation' => 'blocked_missing_fare_basis', 'reasons' => ['fare_basis_codes_by_segment_incomplete']];
        }

        if ($this->itinerarySourcesMismatch($sources, 'validating_carrier')) {
            return ['recommendation' => 'blocked_validating_carrier_mismatch', 'reasons' => ['validating_carrier_mismatch_across_layers']];
        }

        $refreshStatus = strtolower(trim((string) ($meta['offer_refresh_status'] ?? '')));
        $revalPresent = ($sources['revalidation_linkage']['present'] ?? false) === true;
        if ($refreshStatus === 'refreshed' && $revalPresent) {
            foreach (['itinerary_ref', 'pricing_information_index', 'booking_classes_by_segment', 'fare_basis_codes_by_segment'] as $field) {
                if (($rowByField[$field]['status'] ?? '') === 'stale') {
                    return ['recommendation' => 'blocked_stale_revalidation', 'reasons' => ['offer_refreshed_after_revalidation_linkage', $field.'_stale']];
                }
            }
        }

        $readiness = $this->digestor->assessReadiness($mergedSnapshot);
        if (($readiness['auto_pnr_pricing_context_ready'] ?? false) === true) {
            $allCriticalAligned = true;
            foreach (['segment_count', 'itinerary_ref', 'pricing_information_index', 'validating_carrier', 'booking_classes_by_segment', 'fare_basis_codes_by_segment'] as $field) {
                $status = (string) ($rowByField[$field]['status'] ?? 'missing');
                if (in_array($status, ['mismatched', 'stale'], true)) {
                    $allCriticalAligned = false;
                    $reasons[] = $field.'_'.$status;
                }
            }
            if ($allCriticalAligned && $reasons === []) {
                return ['recommendation' => 'auto_pnr_safe', 'reasons' => ['pricing_context_ready', 'continuity_aligned']];
            }
        }

        $missing = is_array($readiness['missing_pricing_context_fields'] ?? null)
            ? $readiness['missing_pricing_context_fields']
            : [];
        if ($missing !== []) {
            $reasons[] = 'missing_pricing_context:'.implode(',', array_slice($missing, 0, 6));
        }

        return ['recommendation' => 'manual_review_required', 'reasons' => $reasons !== [] ? $reasons : ['continuity_incomplete_or_unverified']];
    }

    /**
     * @param  array<string, array<string, mixed>>  $sources
     * @return array<string, bool>
     */
    protected function sourcesPresentMap(array $sources): array
    {
        $out = [];
        foreach (self::SOURCE_KEYS as $key) {
            $out[$key] = ($sources[$key]['present'] ?? false) === true;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptySlice(string $sourceKey): array
    {
        return [
            'source' => $sourceKey,
            'present' => false,
            'segment_count' => null,
            'itinerary_ref' => null,
            'pricing_information_index' => null,
            'validating_carrier' => null,
            'booking_classes_by_segment' => [],
            'fare_basis_codes_by_segment' => [],
            'leg_refs' => [],
            'schedule_refs' => [],
            'segment_fingerprints' => [],
            'marketing_carriers' => [],
            'operating_carriers' => [],
            'price_total' => null,
            'currency' => null,
        ];
    }

    /**
     * @param  list<mixed>  $list
     * @return list<string>
     */
    protected function normalizeList(array $list): array
    {
        $out = [];
        foreach ($list as $item) {
            $out[] = strtoupper(trim((string) $item));
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $handoff
     * @param  array<string, mixed>  $shopCtx
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    protected function perSegmentStrings(array $handoff, array $shopCtx, array $segments, string $key, string $segKind): array
    {
        $fromCtx = is_array($shopCtx[$key] ?? null) ? $shopCtx[$key] : [];
        $fromHandoff = is_array($handoff[$key] ?? null) ? $handoff[$key] : [];
        $merged = $fromHandoff !== [] ? $fromHandoff : $fromCtx;
        $out = [];
        $expected = count($segments);
        for ($i = 0; $i < $expected; $i++) {
            if (isset($merged[$i]) && trim((string) $merged[$i]) !== '') {
                $out[] = strtoupper(substr(trim((string) $merged[$i]), 0, 16));

                continue;
            }
            $seg = $segments[$i] ?? null;
            if (! is_array($seg)) {
                continue;
            }
            if ($segKind === 'booking') {
                $value = strtoupper(trim((string) (
                    $seg['booking_class'] ?? $seg['class_of_service'] ?? $seg['rbd'] ?? ''
                )));
            } else {
                $value = strtoupper(trim((string) ($seg['fare_basis_code'] ?? $seg['fareBasisCode'] ?? '')));
            }
            if ($value !== '') {
                $out[] = substr($value, 0, 16);
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    protected function segmentFingerprints(array $segments): array
    {
        $out = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $origin = strtoupper(trim((string) ($seg['origin'] ?? '')));
            $dest = strtoupper(trim((string) ($seg['destination'] ?? '')));
            $date = substr(trim((string) ($seg['departure_at'] ?? $seg['depart_at'] ?? '')), 0, 10);
            $carrier = strtoupper(trim((string) ($seg['carrier'] ?? $seg['marketing_carrier'] ?? $seg['airline_code'] ?? '')));
            if ($origin === '' || $dest === '') {
                continue;
            }
            $out[] = $origin.'-'.$dest.'|'.$date.'|'.$carrier;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    protected function carrierChain(array $segments, string $kind): array
    {
        $out = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            if ($kind === 'operating') {
                $c = strtoupper(trim((string) ($seg['operating_carrier'] ?? $seg['operating_airline'] ?? $seg['carrier'] ?? '')));
            } else {
                $c = strtoupper(trim((string) ($seg['carrier'] ?? $seg['marketing_carrier'] ?? $seg['airline_code'] ?? '')));
            }
            if ($c !== '') {
                $out[] = $c;
            }
        }

        return $out;
    }

    /**
     * @param  list<mixed>  $list
     * @return list<int>
     */
    protected function intList(mixed $list): array
    {
        if (! is_array($list)) {
            return [];
        }
        $out = [];
        foreach (array_slice($list, 0, 16) as $item) {
            if (is_numeric($item)) {
                $out[] = (int) $item;
            }
        }

        return $out;
    }

    /**
     * @param  list<mixed>  $candidates
     */
    protected function firstNonEmptyScalar(array $candidates): ?string
    {
        foreach ($candidates as $value) {
            if ($value === null) {
                continue;
            }
            $s = trim((string) $value);
            if ($s !== '') {
                return substr($s, 0, 64);
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $candidates
     */
    protected function firstNumeric(array $candidates): ?int
    {
        foreach ($candidates as $value) {
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $list
     */
    protected function perSegmentListComplete(array $list, int $expectedCount): bool
    {
        if ($expectedCount <= 0) {
            return false;
        }
        if (count($list) < $expectedCount) {
            return false;
        }
        for ($i = 0; $i < $expectedCount; $i++) {
            if (! isset($list[$i]) || trim((string) $list[$i]) === '') {
                return false;
            }
        }

        return true;
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
            $s = trim((string) $item);
            if ($s !== '') {
                $out[] = substr($s, 0, 16);
            }
        }

        return $out;
    }

    protected function capScalar(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        $s = trim((string) $value);

        return $s !== '' ? substr($s, 0, 64) : null;
    }

    protected function capCarrier(mixed $value): ?string
    {
        $s = $this->capScalar($value);
        if ($s === null) {
            return null;
        }
        $c = strtoupper(substr($s, 0, 3));

        return $c !== '' ? $c : null;
    }

    /**
     * Compare itinerary-bearing layers only (exclude revalidation draft envelope formatting differences).
     *
     * @param  array<string, array<string, mixed>>  $sources
     */
    protected function itinerarySourcesMismatch(array $sources, string $field): bool
    {
        $keys = ['normalized_snapshot', 'sabre_shop_context', 'sabre_booking_context', 'refreshed_offer_snapshot', 'pnr_draft'];
        $values = [];
        foreach ($keys as $key) {
            $slice = $sources[$key] ?? [];
            if (($slice['present'] ?? false) !== true) {
                continue;
            }
            $raw = $slice[$field] ?? null;
            if ($raw === null || $raw === '' || $raw === []) {
                continue;
            }
            $values[$key] = $field === 'segment_count' ? (int) $raw : $this->normalizeList(is_array($raw) ? $raw : [$raw]);
        }
        if (count($values) < 2) {
            return false;
        }
        $canonical = array_values($values)[0];
        foreach ($values as $value) {
            if ($value !== $canonical) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array<string, mixed>>  $sources
     * @return list<string>
     */
    protected function mergedRbdAcrossLayers(array $sources): array
    {
        foreach (['pnr_draft', 'sabre_booking_context', 'sabre_shop_context', 'normalized_snapshot'] as $key) {
            $list = $sources[$key]['booking_classes_by_segment'] ?? [];
            if (is_array($list) && $this->nonEmptyStringList($list)) {
                return $this->capStringList($list);
            }
        }

        return [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $sources
     * @return list<string>
     */
    protected function mergedFareBasisAcrossLayers(array $sources): array
    {
        foreach (['pnr_draft', 'sabre_booking_context', 'sabre_shop_context', 'normalized_snapshot'] as $key) {
            $list = $sources[$key]['fare_basis_codes_by_segment'] ?? [];
            if (is_array($list) && $this->nonEmptyStringList($list)) {
                return $this->capStringList($list);
            }
        }

        return [];
    }

    protected function nonEmptyStringList(mixed $list): bool
    {
        if (! is_array($list)) {
            return false;
        }
        foreach ($list as $value) {
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Sprint 11K-C: Summarize stored checkout host outcome against local continuity readiness (read-only meta).
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function resolveHostOutcomeOverlay(array $meta, string $localReadiness, Booking $booking): array
    {
        $checkout = is_array($meta['sabre_checkout_outcome'] ?? null) ? $meta['sabre_checkout_outcome'] : [];
        $classification = is_array($checkout['sabre_host_classification'] ?? null)
            ? $checkout['sabre_host_classification']
            : [];
        $revalInspect = is_array($meta['sabre_revalidate_inspect'] ?? null) ? $meta['sabre_revalidate_inspect'] : [];

        $present = $this->hostOutcomeMetaPresent($checkout);
        $hostRejectionEvidence = $this->hostRejectionEvidencePresent($checkout, $classification, $revalInspect);
        $hostOutcomeStatus = $this->resolveHostOutcomeStatus($checkout, $classification, $revalInspect, $hostRejectionEvidence);
        $hostErrorFamily = $this->resolveHostErrorFamily($checkout, $classification, $hostOutcomeStatus);
        $hostRejected = $hostRejectionEvidence;

        $localAligned = $localReadiness === 'auto_pnr_safe';
        $hostRejectedAfterLocal = $localAligned && $hostRejected && $present;
        $supplierRef = trim((string) ($booking->supplier_reference ?? ''));

        return [
            'host_outcome_present' => $present,
            'host_outcome_status' => $hostOutcomeStatus,
            'host_error_family' => $hostErrorFamily,
            'host_checkout_status' => $this->capScalar($checkout['status'] ?? null),
            'host_error_code' => $this->capScalar($checkout['error_code'] ?? null),
            'host_source_layer' => $this->capScalar($classification['source_layer'] ?? null),
            'host_safe_reason_code' => $this->capScalar($classification['safe_reason_code'] ?? null),
            'revalidation_attempted' => $this->resolveRevalidationAttemptedFlag($checkout, $revalInspect),
            'revalidation_outcome' => $this->capScalar($checkout['revalidation_outcome'] ?? null),
            'revalidation_skipped' => $this->resolveRevalidationSkippedFlag($checkout),
            'local_continuity_aligned' => $localAligned,
            'host_rejection_evidence_present' => $hostRejectionEvidence,
            'host_rejected_after_local_continuity' => $hostRejectedAfterLocal,
            'final_diagnostic_recommendation' => $this->resolveFinalDiagnosticRecommendation(
                $localReadiness,
                $present,
                $hostRejected,
                $hostRejectedAfterLocal,
                $supplierRef !== '',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $checkout
     */
    protected function hostOutcomeMetaPresent(array $checkout): bool
    {
        if ($checkout === []) {
            return false;
        }

        foreach ([
            'status',
            'error_code',
            'live_call_attempted',
            'sabre_host_classification',
            'revalidation_attempted',
            'revalidation_outcome',
            'revalidation_skipped_by_config',
            'prebooking_revalidation_skipped_reason',
            'airline_segment_status',
        ] as $key) {
            if (! array_key_exists($key, $checkout)) {
                continue;
            }
            $val = $checkout[$key];
            if (is_array($val) && $val !== []) {
                return true;
            }
            if (is_bool($val) || (is_scalar($val) && trim((string) $val) !== '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $checkout
     * @param  array<string, mixed>  $classification
     * @param  array<string, mixed>  $revalInspect
     */
    protected function resolveHostOutcomeStatus(
        array $checkout,
        array $classification,
        array $revalInspect,
        bool $hostRejectionEvidence,
    ): string {
        if ($checkout === []) {
            return self::HOST_OUTCOME_STATUS_NONE;
        }

        $status = strtolower(trim((string) ($checkout['status'] ?? '')));
        $liveAttempted = ($checkout['live_call_attempted'] ?? false) === true;

        if ($this->isInternalGateOrCertificationPending($checkout)) {
            return self::HOST_OUTCOME_STATUS_SKIPPED;
        }

        if ($hostRejectionEvidence) {
            return self::HOST_OUTCOME_STATUS_FAILED;
        }

        if ($status === 'pending_payment_or_ticketing' && $liveAttempted) {
            return self::HOST_OUTCOME_STATUS_SUCCESS;
        }

        if ($status === 'dry_run' && ! $liveAttempted) {
            return self::HOST_OUTCOME_STATUS_SKIPPED;
        }

        if (($checkout['revalidation_skipped_by_config'] ?? false) === true
            || trim((string) ($checkout['prebooking_revalidation_skipped_reason'] ?? '')) !== '') {
            return self::HOST_OUTCOME_STATUS_SKIPPED;
        }

        if (in_array($status, ['needs_review', 'failed'], true)) {
            return self::HOST_OUTCOME_STATUS_SKIPPED;
        }

        if ($status !== '' || $liveAttempted) {
            return self::HOST_OUTCOME_STATUS_SUCCESS;
        }

        return self::HOST_OUTCOME_STATUS_NONE;
    }

    /**
     * @param  array<string, mixed>  $checkout
     * @param  array<string, mixed>  $classification
     */
    protected function resolveHostErrorFamily(
        array $checkout,
        array $classification,
        string $hostOutcomeStatus,
    ): ?string {
        if (! $this->hostOutcomeMetaPresent($checkout)) {
            return null;
        }

        if ($this->isInternalGateOrCertificationPending($checkout)) {
            return self::HOST_ERROR_FAMILY_CERTIFIED_ROUTE_PENDING;
        }

        if ($hostOutcomeStatus === self::HOST_OUTCOME_STATUS_SUCCESS) {
            return self::HOST_ERROR_FAMILY_NONE;
        }

        $familyFromPersisted = strtoupper(trim((string) ($classification['host_error_family'] ?? '')));
        if ($familyFromPersisted !== '' && $familyFromPersisted !== self::HOST_ERROR_FAMILY_NONE) {
            return $familyFromPersisted;
        }

        $safeReason = strtolower(trim((string) ($classification['safe_reason_code'] ?? '')));
        $familyFromReason = match ($safeReason) {
            SabreHostErrorClassifier::REASON_HOST_SELL_REJECTED_UC,
            SabreHostErrorClassifier::REASON_INVENTORY_UNAVAILABLE => self::HOST_ERROR_FAMILY_UC_SEGMENT_STATUS,
            SabreHostErrorClassifier::REASON_HOST_SEGMENT_STATUS_UNCONFIRMED => self::HOST_ERROR_FAMILY_HOST_SEGMENT_STATUS,
            SabreHostErrorClassifier::REASON_NO_FARES_RBD_CARRIER => self::HOST_ERROR_FAMILY_NO_FARES_RBD_CARRIER,
            default => null,
        };
        if ($familyFromReason !== null) {
            return $familyFromReason;
        }

        $segmentStatus = strtoupper(trim((string) ($checkout['airline_segment_status'] ?? '')));
        if ($segmentStatus === 'NN') {
            return self::HOST_ERROR_FAMILY_HOST_SEGMENT_STATUS;
        }
        if ($segmentStatus === 'UC') {
            return self::HOST_ERROR_FAMILY_UC_SEGMENT_STATUS;
        }
        if (($checkout['halt_on_status_received'] ?? false) === true) {
            return self::HOST_ERROR_FAMILY_HOST_SEGMENT_STATUS;
        }

        $errorCode = strtolower(trim((string) ($checkout['error_code'] ?? '')));
        if ($errorCode === 'sabre_revalidation_failed') {
            return self::HOST_ERROR_FAMILY_REVALIDATION_FAILED;
        }
        if (in_array($errorCode, [
            'sabre_booking_payload_validation_failed',
            'sabre_passenger_records_itinerary_guard',
            'sabre_passenger_records_stale_shop_segment',
        ], true)) {
            return self::HOST_ERROR_FAMILY_HOST_VALIDATION_FAILURE;
        }

        if (($checkout['revalidation_skipped_by_config'] ?? false) === true
            || trim((string) ($checkout['prebooking_revalidation_skipped_reason'] ?? '')) !== '') {
            return self::HOST_ERROR_FAMILY_REVALIDATION_SKIPPED;
        }

        if ($this->responseErrorCodesIndicateNoFaresRbdCarrier($checkout)) {
            return self::HOST_ERROR_FAMILY_NO_FARES_RBD_CARRIER;
        }

        if ($hostOutcomeStatus === self::HOST_OUTCOME_STATUS_FAILED) {
            return self::HOST_ERROR_FAMILY_UNKNOWN;
        }

        if ($hostOutcomeStatus === self::HOST_OUTCOME_STATUS_SKIPPED) {
            return self::HOST_ERROR_FAMILY_REVALIDATION_SKIPPED;
        }

        return self::HOST_ERROR_FAMILY_NONE;
    }

    /**
     * @param  array<string, mixed>  $checkout
     * @param  array<string, mixed>  $classification
     * @param  array<string, mixed>  $revalInspect
     */
    protected function hostRejectionEvidencePresent(
        array $checkout,
        array $classification,
        array $revalInspect,
    ): bool {
        if ($checkout === [] || $this->isInternalGateOrCertificationPending($checkout)) {
            return false;
        }

        $safeReason = strtolower(trim((string) ($classification['safe_reason_code'] ?? '')));
        if (in_array($safeReason, [
            SabreHostErrorClassifier::REASON_HOST_SELL_REJECTED_UC,
            SabreHostErrorClassifier::REASON_HOST_SEGMENT_STATUS_UNCONFIRMED,
            SabreHostErrorClassifier::REASON_INVENTORY_UNAVAILABLE,
            SabreHostErrorClassifier::REASON_NO_FARES_RBD_CARRIER,
        ], true)) {
            return true;
        }

        $segmentStatus = strtoupper(trim((string) ($checkout['airline_segment_status'] ?? '')));
        if (in_array($segmentStatus, ['UC', 'NN', 'NO', 'HX', 'UN'], true)
            || ($checkout['halt_on_status_received'] ?? false) === true) {
            return true;
        }

        if ($this->responseErrorCodesIndicateNoFaresRbdCarrier($checkout)) {
            return true;
        }

        $errorCode = strtolower(trim((string) ($checkout['error_code'] ?? '')));
        $liveAttempted = ($checkout['live_call_attempted'] ?? false) === true;
        if ($errorCode === 'sabre_revalidation_failed' && $liveAttempted) {
            return true;
        }

        if ($errorCode === 'sabre_booking_application_error' && $liveAttempted && $classification !== []) {
            return true;
        }

        if (in_array($errorCode, [
            'sabre_booking_payload_validation_failed',
            'sabre_passenger_records_itinerary_guard',
            'sabre_passenger_records_stale_shop_segment',
        ], true) && $liveAttempted) {
            return true;
        }

        if (($revalInspect['revalidation_success'] ?? null) === false
            && ($checkout['revalidation_attempted'] ?? false) === true) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $checkout
     */
    protected function isInternalGateOrCertificationPending(array $checkout): bool
    {
        $errorCode = strtolower(trim((string) ($checkout['error_code'] ?? '')));

        return $errorCode === SabreCertifiedRouteSelector::ERROR_CODE_PENDING;
    }

    protected function resolveFinalDiagnosticRecommendation(
        string $localReadiness,
        bool $hostOutcomePresent,
        bool $hostRejected,
        bool $hostRejectedAfterLocal,
        bool $supplierReferencePresent = false,
    ): string {
        if (str_starts_with($localReadiness, 'blocked_') && $localReadiness !== self::FINAL_REC_BLOCKED_HOST_REJECTED) {
            return $localReadiness;
        }

        if ($hostRejectedAfterLocal) {
            return self::FINAL_REC_BLOCKED_HOST_REJECTED;
        }

        if ($localReadiness === 'auto_pnr_safe' && (! $hostOutcomePresent || ! $hostRejected)) {
            return 'auto_pnr_safe';
        }

        if ($localReadiness === 'auto_pnr_safe' && $supplierReferencePresent && ! $hostRejected) {
            return 'auto_pnr_safe';
        }

        if ($hostRejected && ! str_starts_with($localReadiness, 'blocked_')) {
            return 'manual_review_required';
        }

        return $localReadiness;
    }

    /**
     * @param  array<string, mixed>  $checkout
     */
    protected function resolveRevalidationAttemptedFlag(array $checkout, array $revalInspect): ?bool
    {
        if (array_key_exists('revalidation_attempted', $checkout)) {
            return ($checkout['revalidation_attempted'] ?? false) === true;
        }
        if ($revalInspect !== []) {
            return true;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $checkout
     */
    protected function resolveRevalidationSkippedFlag(array $checkout): ?bool
    {
        if (array_key_exists('revalidation_skipped_by_config', $checkout)) {
            return ($checkout['revalidation_skipped_by_config'] ?? false) === true;
        }
        if (trim((string) ($checkout['prebooking_revalidation_skipped_reason'] ?? '')) !== '') {
            return true;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $checkout
     */
    protected function responseErrorCodesIndicateNoFaresRbdCarrier(array $checkout): bool
    {
        $codes = is_array($checkout['response_error_codes'] ?? null) ? $checkout['response_error_codes'] : [];
        foreach ($codes as $code) {
            if (! is_scalar($code)) {
                continue;
            }
            if (str_contains(strtoupper(trim((string) $code)), 'NO FARES/RBD/CARRIER')) {
                return true;
            }
        }

        return false;
    }
}
