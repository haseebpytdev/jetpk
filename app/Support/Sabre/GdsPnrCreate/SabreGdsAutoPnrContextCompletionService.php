<?php

namespace App\Support\Sabre\GdsPnrCreate;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\Gds\SabreFlightSearchNormalizer;
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use App\Support\Bookings\SabrePnrCertificationSupport;
use App\Support\Bookings\SabreSafeRefreshContext;

/**
 * Repairs per-segment Sabre GDS branded fare context before public auto-PNR (safe diagnostics only).
 */
final class SabreGdsAutoPnrContextCompletionService
{
    public const STATUS_COMPLETE = 'complete';

    public const STATUS_REPAIRED = 'repaired';

    public const STATUS_FAILED = 'failed';

    public const REASON_CONTEXT_COMPLETION_FAILED = 'context_completion_failed';

    public const META_KEY = 'auto_pnr_context_completion';

    /** @var list<string> */
    private const SOURCE_ORDER = [
        'selected_branded_fare_option',
        'sabre_booking_context',
        'normalized_offer_pricing_index',
        'gir_fare_components',
        'safe_offer_refresh',
    ];

    public function __construct(
        protected SabrePnrCertificationSupport $certificationSupport,
        protected SabreConnectingBrandedFarePublicAutoCertification $publicAutoCertification,
        protected SabreFlightSearchNormalizer $flightSearchNormalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function completeForBooking(Booking $booking, array $options = []): array
    {
        $booking->loadMissing(['passengers', 'contact']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $readiness = $this->certificationSupport->buildReadiness($booking);
        $segmentCount = max(0, (int) ($readiness['segment_count'] ?? 0));
        $validatingCarrier = strtoupper(trim((string) ($readiness['validating_carrier'] ?? '')));
        $carriers = is_array($readiness['carrier_chain'] ?? null) ? $readiness['carrier_chain'] : [];
        $selected = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $snapshot = $this->offerSnapshotFromMeta($meta);
        $brandCode = strtoupper(trim((string) (
            $handoff['selected_brand_code']
            ?? $handoff['brand_code']
            ?? $selected['brand_code']
            ?? ''
        )));
        $pricingIndex = $this->resolvePricingInformationIndex($meta, $selected, $handoff, $snapshot);

        $rawBefore = $this->publicAutoCertification->resolveMergedSegmentContext($selected, $handoff, $meta, $segmentCount);
        $wasIncomplete = $segmentCount >= 1 && (
            ! $this->publicAutoCertification->perSegmentStringListComplete($rawBefore['booking_classes_by_segment'], $segmentCount)
            || ! $this->publicAutoCertification->perSegmentStringListComplete($rawBefore['fare_basis_codes_by_segment'], $segmentCount)
        );

        $sourcesUsed = [];
        $expandedSingle = false;
        $resolved = null;

        foreach (self::SOURCE_ORDER as $source) {
            $candidate = match ($source) {
                'selected_branded_fare_option' => $this->segmentContextFromRow($selected, $segmentCount, $snapshot, $source),
                'sabre_booking_context' => $this->segmentContextFromRow($handoff, $segmentCount, $snapshot, $source),
                'normalized_offer_pricing_index' => $this->segmentContextFromOfferSegments($snapshot, $segmentCount),
                'gir_fare_components' => $this->segmentContextFromGirArchive($snapshot, $meta, $segmentCount),
                'safe_offer_refresh' => $this->segmentContextFromSafeRefresh($meta, $segmentCount),
                default => null,
            };
            if ($candidate === null) {
                continue;
            }
            if (($candidate['expanded_single_fare_component_to_all_segments'] ?? false) === true) {
                $expandedSingle = true;
            }
            if (($candidate['complete'] ?? false) === true) {
                $sourcesUsed[] = $source;
                $resolved = $candidate;
                break;
            }
            if ($resolved === null && ($candidate['partial'] ?? false) === true) {
                $sourcesUsed[] = $source.'_partial';
            }
        }

        $exactRefreshAttempted = ($options['exact_refresh_attempted'] ?? false) === true;
        $needsExactRefresh = $resolved === null
            && ! $exactRefreshAttempted
            && $this->canAttemptExactRefresh($meta, $snapshot);

        $status = self::STATUS_FAILED;
        $attemptReady = false;
        $blockReason = null;

        if ($resolved !== null) {
            $status = $wasIncomplete ? self::STATUS_REPAIRED : self::STATUS_COMPLETE;
            $attemptReady = true;
        } else {
            $blockReason = (! $needsExactRefresh || $exactRefreshAttempted)
                ? self::REASON_CONTEXT_COMPLETION_FAILED
                : null;
        }

        if (SabreOfferRefreshAcceptance::requiresAcceptance($booking)) {
            $attemptReady = false;
            $blockReason = SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE;
        }

        $bookingClasses = is_array($resolved['booking_classes_by_segment'] ?? null)
            ? $resolved['booking_classes_by_segment']
            : [];
        $fareBasisCodes = is_array($resolved['fare_basis_codes_by_segment'] ?? null)
            ? $resolved['fare_basis_codes_by_segment']
            : [];
        $cabins = is_array($resolved['cabin_by_segment'] ?? null)
            ? $resolved['cabin_by_segment']
            : [];

        return [
            'auto_pnr_context_completion_attempted' => true,
            'auto_pnr_context_completion_status' => $status,
            'completion_sources_used' => array_values(array_unique($sourcesUsed)),
            'segment_count' => $segmentCount,
            'validating_carrier' => $validatingCarrier !== '' ? $validatingCarrier : null,
            'carrier_chain' => $carriers,
            'selected_brand_code' => $brandCode !== '' ? $brandCode : null,
            'pricing_information_index' => $pricingIndex,
            'completed_booking_classes_by_segment' => $bookingClasses,
            'completed_fare_basis_codes_by_segment' => $fareBasisCodes,
            'completed_cabin_by_segment' => $cabins,
            'booking_classes_by_segment_count' => count($bookingClasses),
            'fare_basis_codes_by_segment_count' => count($fareBasisCodes),
            'cabin_by_segment_count' => count($cabins),
            'per_segment_booking_class_complete' => $this->publicAutoCertification->perSegmentStringListComplete($bookingClasses, $segmentCount),
            'per_segment_fare_basis_complete' => $this->publicAutoCertification->perSegmentStringListComplete($fareBasisCodes, $segmentCount),
            'per_segment_cabin_complete' => $segmentCount <= 1
                || $this->publicAutoCertification->perSegmentStringListComplete($cabins, $segmentCount),
            'expanded_single_fare_component_to_all_segments' => $expandedSingle,
            'needs_exact_refresh' => $needsExactRefresh,
            'exact_refresh_attempted' => $exactRefreshAttempted,
            'exact_refresh_result' => $options['exact_refresh_result'] ?? null,
            'public_auto_pnr_attempt_ready' => $attemptReady,
            'public_auto_pnr_block_reason' => $attemptReady ? null : $blockReason,
            'connecting_brand_context_complete' => $attemptReady && $brandCode !== '' && $validatingCarrier !== '',
        ];
    }

    /**
     * Persist repaired segment context into booking meta when completion succeeded.
     *
     * @param  array<string, mixed>  $completion
     */
    public function persistCompletedContext(Booking $booking, array $completion): void
    {
        if (($completion['public_auto_pnr_attempt_ready'] ?? false) !== true) {
            $this->persistCompletionDiagnostics($booking, $completion);

            return;
        }

        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $classes = is_array($completion['completed_booking_classes_by_segment'] ?? null)
            ? $completion['completed_booking_classes_by_segment']
            : [];
        $fareBasis = is_array($completion['completed_fare_basis_codes_by_segment'] ?? null)
            ? $completion['completed_fare_basis_codes_by_segment']
            : [];
        $cabins = is_array($completion['completed_cabin_by_segment'] ?? null)
            ? $completion['completed_cabin_by_segment']
            : [];
        $segmentCount = (int) ($completion['segment_count'] ?? 0);

        if ($classes !== []) {
            $meta = $this->mirrorCompletedSegmentContextIntoMeta($meta, $completion, $classes, $fareBasis, $cabins, $segmentCount);
        }

        $this->persistCompletionDiagnostics($booking, $completion, $meta);
    }

    /**
     * Strategy selector / digest overlay: repaired Sabre booking context wins over stale display-option readiness.
     *
     * @param  array<string, mixed>  $completion
     * @return array<string, mixed>
     */
    public function scenarioRunnerStrategyMetaOverlay(Booking $booking, array $completion): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        if (($completion['public_auto_pnr_attempt_ready'] ?? false) !== true) {
            return $meta;
        }

        $classes = is_array($completion['completed_booking_classes_by_segment'] ?? null)
            ? $completion['completed_booking_classes_by_segment']
            : [];
        if ($classes === []) {
            return $meta;
        }

        $fareBasis = is_array($completion['completed_fare_basis_codes_by_segment'] ?? null)
            ? $completion['completed_fare_basis_codes_by_segment']
            : [];
        $cabins = is_array($completion['completed_cabin_by_segment'] ?? null)
            ? $completion['completed_cabin_by_segment']
            : [];
        $segmentCount = (int) ($completion['segment_count'] ?? count($classes));

        return $this->mirrorCompletedSegmentContextIntoMeta($meta, $completion, $classes, $fareBasis, $cabins, $segmentCount);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $completion
     * @param  list<string>  $classes
     * @param  list<string>  $fareBasis
     * @param  list<string>  $cabins
     * @return array<string, mixed>
     */
    protected function mirrorCompletedSegmentContextIntoMeta(
        array $meta,
        array $completion,
        array $classes,
        array $fareBasis,
        array $cabins,
        int $segmentCount,
    ): array {
        $selected = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
        $selected['booking_classes_by_segment'] = $classes;
        $selected['booking_class'] = $classes[0] ?? ($selected['booking_class'] ?? null);
        if ($fareBasis !== []) {
            $selected['fare_basis_codes_by_segment'] = $fareBasis;
            $selected['fare_basis'] = $fareBasis[0] ?? ($selected['fare_basis'] ?? null);
        }
        if ($cabins !== []) {
            $selected['cabin_by_segment'] = $cabins;
        }
        if ($segmentCount > 0) {
            $selected['segment_slice_count'] = $segmentCount;
        }
        $selected['ready_for_booking_payload'] = true;
        $selected['selectable'] = true;
        $selected['readiness_reasons'] = [];
        $meta['selected_fare_family_option'] = $selected;

        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $handoff['booking_classes_by_segment'] = $classes;
        if ($fareBasis !== []) {
            $handoff['fare_basis_codes_by_segment'] = $fareBasis;
        }
        if ($cabins !== []) {
            $handoff['cabin_by_segment'] = $cabins;
        }
        if ($segmentCount > 0) {
            $handoff['segment_slice_count'] = $segmentCount;
        }
        if (($completion['pricing_information_index'] ?? null) !== null) {
            $handoff['pricing_information_index'] = (int) $completion['pricing_information_index'];
        }
        $handoff['ready_for_booking_payload'] = true;
        $handoff['selected_fare_family_option'] = $selected;
        $meta['sabre_booking_context'] = $handoff;

        $slice = $this->safeDiagnosticsSlice($completion);
        if ($slice !== []) {
            $meta[self::META_KEY] = $slice;
            $meta['sabre_booking_context'] = $this->mergeCompletionIntoSabreBookingContext($handoff, $slice);
        }

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $completion
     * @param  array<string, mixed>|null  $meta
     */
    public function persistCompletionDiagnostics(Booking $booking, array $completion, ?array $meta = null): void
    {
        $meta ??= is_array($booking->meta) ? $booking->meta : [];
        $slice = $this->safeDiagnosticsSlice($completion);
        $meta[self::META_KEY] = $slice;
        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $meta['sabre_booking_context'] = $this->mergeCompletionIntoSabreBookingContext($handoff, $slice);
        $booking->forceFill(['meta' => $meta])->save();
    }

    /**
     * @param  array<string, mixed>  $completion
     * @return array<string, mixed>
     */
    public function safeDiagnosticsSlice(array $completion): array
    {
        return array_filter([
            'auto_pnr_context_completion_attempted' => ($completion['auto_pnr_context_completion_attempted'] ?? false) === true,
            'auto_pnr_context_completion_status' => $completion['auto_pnr_context_completion_status'] ?? null,
            'completion_sources_used' => is_array($completion['completion_sources_used'] ?? null)
                ? array_values($completion['completion_sources_used'])
                : [],
            'segment_count' => isset($completion['segment_count']) ? (int) $completion['segment_count'] : null,
            'booking_classes_by_segment_count' => isset($completion['booking_classes_by_segment_count'])
                ? (int) $completion['booking_classes_by_segment_count']
                : null,
            'fare_basis_codes_by_segment_count' => isset($completion['fare_basis_codes_by_segment_count'])
                ? (int) $completion['fare_basis_codes_by_segment_count']
                : null,
            'cabin_by_segment_count' => isset($completion['cabin_by_segment_count'])
                ? (int) $completion['cabin_by_segment_count']
                : null,
            'per_segment_booking_class_complete' => ($completion['per_segment_booking_class_complete'] ?? false) === true,
            'per_segment_fare_basis_complete' => ($completion['per_segment_fare_basis_complete'] ?? false) === true,
            'expanded_single_fare_component_to_all_segments' => ($completion['expanded_single_fare_component_to_all_segments'] ?? false) === true,
            'exact_refresh_attempted' => ($completion['exact_refresh_attempted'] ?? false) === true,
            'exact_refresh_result' => is_string($completion['exact_refresh_result'] ?? null)
                ? $completion['exact_refresh_result']
                : null,
            'public_auto_pnr_attempt_ready' => ($completion['public_auto_pnr_attempt_ready'] ?? false) === true,
            'public_auto_pnr_block_reason' => is_string($completion['public_auto_pnr_block_reason'] ?? null)
                ? $completion['public_auto_pnr_block_reason']
                : null,
            'completed_at' => now()->toIso8601String(),
        ], static fn ($v) => $v !== null && $v !== []);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function readStoredCompletion(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $stored = is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : [];

        return $stored;
    }

    /**
     * Whether a public GDS auto-PNR live create is allowed for the completed context.
     *
     * @param  array<string, mixed>  $completion
     */
    public function publicCreateAllowed(int $segmentCount, array $completion): bool
    {
        if (($completion['auto_pnr_context_completion_attempted'] ?? false) !== true) {
            return false;
        }

        if (($completion['public_auto_pnr_attempt_ready'] ?? false) === true) {
            return true;
        }

        $status = trim((string) ($completion['auto_pnr_context_completion_status'] ?? ''));

        return $segmentCount <= 1
            && in_array($status, [self::STATUS_COMPLETE, self::STATUS_REPAIRED], true);
    }

    /**
     * Flat safe slice for checkout outcome, sabre_booking_context, and attempt safe_summary.
     *
     * @param  array<string, mixed>  $completion
     * @return array<string, mixed>
     */
    public function checkoutPersistSlice(array $completion): array
    {
        return $this->safeDiagnosticsSlice($completion);
    }

    /**
     * @param  array<string, mixed>  $handoff
     * @param  array<string, mixed>  $persistSlice
     * @return array<string, mixed>
     */
    public function mergeCompletionIntoSabreBookingContext(array $handoff, array $persistSlice): array
    {
        if ($persistSlice === []) {
            return $handoff;
        }

        return array_merge($handoff, $persistSlice, [
            'auto_pnr_context_completion' => $persistSlice,
        ]);
    }

    /**
     * Resolve + optional exact refresh for public checkout (no live PNR).
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function resolveForPublicCheckout(Booking $booking, array $options = []): array
    {
        $completion = $this->completeForBooking($booking, $options);

        if (($completion['needs_exact_refresh'] ?? false) === true
            && ($options['exact_refresh_attempted'] ?? false) !== true
            && (bool) config('suppliers.sabre.refresh_offer_before_public_pnr', true)
            && ($options['skip_exact_refresh'] ?? false) !== true) {
            $completion['exact_refresh_pending'] = true;
        }

        return $completion;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>|null
     */
    protected function segmentContextFromRow(array $row, int $segmentCount, array $snapshot, string $source): ?array
    {
        if ($row === [] || $segmentCount < 1) {
            return null;
        }

        $bookingClasses = $this->normalizeSegmentStringList(
            is_array($row['booking_classes_by_segment'] ?? null) ? $row['booking_classes_by_segment'] : [],
        );
        $fareBasisCodes = $this->normalizeSegmentStringList(
            is_array($row['fare_basis_codes_by_segment'] ?? null) ? $row['fare_basis_codes_by_segment'] : [],
        );
        $cabins = $this->normalizeSegmentStringList(
            is_array($row['cabin_by_segment'] ?? null) ? $row['cabin_by_segment'] : [],
            lowercase: true,
        );

        if ($bookingClasses === [] && trim((string) ($row['booking_class'] ?? '')) !== '') {
            $bookingClasses = $this->normalizeSegmentStringList([(string) $row['booking_class']]);
        }
        if ($fareBasisCodes === [] && trim((string) ($row['fare_basis'] ?? '')) !== '') {
            $fareBasisCodes = $this->normalizeSegmentStringList([(string) $row['fare_basis']]);
        }

        $expanded = false;
        if ($segmentCount > 1 && count($bookingClasses) === 1) {
            $expandedClasses = $this->trySafeExpandSingleSegmentList($bookingClasses, $segmentCount, $row, $snapshot, false);
            if ($expandedClasses === null) {
                $bookingClasses = [];
            } else {
                $bookingClasses = $expandedClasses;
                $expanded = true;
            }
        }
        if ($segmentCount > 1 && count($fareBasisCodes) === 1) {
            $expandedFare = $this->trySafeExpandSingleSegmentList($fareBasisCodes, $segmentCount, $row, $snapshot, false);
            if ($expandedFare === null) {
                $fareBasisCodes = [];
            } elseif ($expanded) {
                $fareBasisCodes = $expandedFare;
            } elseif ($this->publicAutoCertification->perSegmentStringListComplete($expandedFare, $segmentCount)) {
                $fareBasisCodes = $expandedFare;
                $expanded = true;
            }
        }

        return $this->buildCandidateResult($bookingClasses, $fareBasisCodes, $cabins, $segmentCount, $expanded);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>|null
     */
    protected function segmentContextFromOfferSegments(array $snapshot, int $segmentCount): ?array
    {
        if ($segmentCount < 1) {
            return null;
        }
        $segments = is_array($snapshot['segments'] ?? null) ? array_values($snapshot['segments']) : [];
        if (count($segments) !== $segmentCount) {
            return null;
        }

        $bookingClasses = [];
        $fareBasisCodes = [];
        $cabins = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                return null;
            }
            $bc = strtoupper(trim((string) ($seg['booking_class'] ?? $seg['res_book_desig'] ?? $seg['class_of_service'] ?? '')));
            $fb = strtoupper(trim((string) ($seg['fare_basis_code'] ?? $seg['fare_basis'] ?? '')));
            $cab = strtolower(trim((string) ($seg['cabin'] ?? $seg['cabin_code'] ?? '')));
            if ($bc === '' || $fb === '') {
                return null;
            }
            $bookingClasses[] = $bc;
            $fareBasisCodes[] = $fb;
            if ($cab === '' && $bc !== '') {
                $cab = 'economy';
            }
            if ($cab !== '') {
                $cabins[] = $cab;
            }
        }

        return $this->buildCandidateResult($bookingClasses, $fareBasisCodes, $cabins, $segmentCount, false);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>|null
     */
    protected function segmentContextFromGirArchive(array $snapshot, array $meta, int $segmentCount): ?array
    {
        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $gir = is_array($raw['sabre_bfm_gir_archive'] ?? null) ? $raw['sabre_bfm_gir_archive'] : [];
        if ($gir === []) {
            return null;
        }

        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $groupIndex = (int) ($ctx['itinerary_group_index'] ?? $handoff['itinerary_group_index'] ?? 0);
        $itineraryIndex = (int) ($ctx['itinerary_index'] ?? $handoff['itinerary_index'] ?? 0);
        $piIndex = $this->resolvePricingInformationIndex($meta, [], $handoff, $snapshot);

        $fareNode = $this->flightSearchNormalizer->resolveFareNodeFromGirArchive(
            $gir,
            $groupIndex,
            $itineraryIndex,
            $piIndex,
        );
        if ($fareNode === null) {
            return null;
        }

        $slices = $this->flightSearchNormalizer->segmentBookingSlicesFromFareNode($fareNode);
        $bookingClasses = $this->normalizeSegmentStringList($slices['booking_classes_by_segment'] ?? []);
        $fareBasisCodes = $this->normalizeSegmentStringList($slices['fare_basis_codes_by_segment'] ?? []);
        $cabins = $this->normalizeSegmentStringList($slices['cabin_by_segment'] ?? [], lowercase: true);

        $expanded = $segmentCount > 1
            && count($bookingClasses) === 1
            && (int) ($slices['segment_slice_count'] ?? 0) >= $segmentCount
            && ($slices['segment_slice_count'] ?? 0) === 1;

        if ($expanded) {
            $bookingClasses = array_fill(0, $segmentCount, $bookingClasses[0]);
            if (count($fareBasisCodes) === 1) {
                $fareBasisCodes = array_fill(0, $segmentCount, $fareBasisCodes[0]);
            }
        }

        return $this->buildCandidateResult($bookingClasses, $fareBasisCodes, $cabins, $segmentCount, $expanded);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>|null
     */
    protected function segmentContextFromSafeRefresh(array $meta, int $segmentCount): ?array
    {
        $refresh = is_array($meta[SabreSafeRefreshContext::META_KEY] ?? null)
            ? $meta[SabreSafeRefreshContext::META_KEY]
            : [];
        if ($refresh === []) {
            return null;
        }

        $bookingClasses = $this->normalizeSegmentStringList(
            is_array($refresh['booking_classes_by_segment'] ?? null) ? $refresh['booking_classes_by_segment'] : [],
        );
        $fareBasisCodes = $this->normalizeSegmentStringList(
            is_array($refresh['fare_basis_codes_by_segment'] ?? null) ? $refresh['fare_basis_codes_by_segment'] : [],
        );
        $cabins = $this->normalizeSegmentStringList(
            is_array($refresh['cabin_by_segment'] ?? null) ? $refresh['cabin_by_segment'] : [],
            lowercase: true,
        );

        return $this->buildCandidateResult($bookingClasses, $fareBasisCodes, $cabins, $segmentCount, false);
    }

    /**
     * @param  list<string>  $bookingClasses
     * @param  list<string>  $fareBasisCodes
     * @param  list<string>  $cabins
     * @return array<string, mixed>|null
     */
    protected function buildCandidateResult(
        array $bookingClasses,
        array $fareBasisCodes,
        array $cabins,
        int $segmentCount,
        bool $expandedSingle,
    ): ?array {
        if ($segmentCount < 1) {
            return null;
        }

        $classComplete = $this->publicAutoCertification->perSegmentStringListComplete($bookingClasses, $segmentCount);
        $fareComplete = $this->publicAutoCertification->perSegmentStringListComplete($fareBasisCodes, $segmentCount);
        $cabinComplete = $segmentCount <= 1
            || $this->publicAutoCertification->perSegmentStringListComplete($cabins, $segmentCount);

        if (! $classComplete || ! $fareComplete) {
            return [
                'booking_classes_by_segment' => $bookingClasses,
                'fare_basis_codes_by_segment' => $fareBasisCodes,
                'cabin_by_segment' => $cabins,
                'expanded_single_fare_component_to_all_segments' => $expandedSingle,
                'complete' => false,
                'partial' => $bookingClasses !== [] || $fareBasisCodes !== [],
            ];
        }

        return [
            'booking_classes_by_segment' => $bookingClasses,
            'fare_basis_codes_by_segment' => $fareBasisCodes,
            'cabin_by_segment' => $cabinComplete ? $cabins : [],
            'expanded_single_fare_component_to_all_segments' => $expandedSingle,
            'complete' => true,
            'partial' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $snapshot
     * @param  list<string>  $list
     * @return list<string>|null
     */
    protected function trySafeExpandSingleSegmentList(
        array $list,
        int $segmentCount,
        array $row,
        array $snapshot,
        bool $allowOfferSegmentScalarProof = false,
    ): ?array {
        if ($segmentCount <= 1 || count($list) !== 1) {
            return $list;
        }

        if (($row['single_fare_component_applies_to_all_segments'] ?? false) === true
            || ($row['expanded_single_fare_component_to_all_segments'] ?? false) === true) {
            return array_fill(0, $segmentCount, $list[0]);
        }

        $sliceCount = (int) ($row['segment_slice_count'] ?? 0);
        $fareComponentCount = (int) data_get($row, 'linkage_summary.fare_component_ref_count', 0);
        if ($sliceCount >= $segmentCount && $fareComponentCount === 1) {
            return array_fill(0, $segmentCount, $list[0]);
        }

        $scheduleRefs = is_array($row['schedule_refs'] ?? null) ? array_values($row['schedule_refs']) : [];
        if (count($scheduleRefs) >= $segmentCount) {
            return array_fill(0, $segmentCount, $list[0]);
        }

        if ($allowOfferSegmentScalarProof) {
            $offerSegments = is_array($snapshot['segments'] ?? null) ? array_values($snapshot['segments']) : [];
            if (count($offerSegments) === $segmentCount) {
                $values = [];
                foreach ($offerSegments as $seg) {
                    if (! is_array($seg)) {
                        return null;
                    }
                    $values[] = strtoupper(trim((string) ($seg['booking_class'] ?? $seg['fare_basis_code'] ?? '')));
                }
                if ($values !== [] && count(array_unique($values)) === 1 && $values[0] === $list[0]) {
                    return array_fill(0, $segmentCount, $list[0]);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $selected
     * @param  array<string, mixed>  $handoff
     * @param  array<string, mixed>  $snapshot
     */
    protected function resolvePricingInformationIndex(array $meta, array $selected, array $handoff, array $snapshot): int
    {
        foreach ([
            $handoff['pricing_information_index'] ?? null,
            $selected['pricing_information_index'] ?? null,
            data_get($snapshot, 'raw_payload.sabre_shop_context.pricing_information_index'),
            data_get($meta, 'pricing_information_index'),
        ] as $candidate) {
            if (is_numeric($candidate)) {
                return (int) $candidate;
            }
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function offerSnapshotFromMeta(array $meta): array
    {
        if (is_array($meta['normalized_offer_snapshot'] ?? null)) {
            return $meta['normalized_offer_snapshot'];
        }
        if (is_array($meta['validated_offer_snapshot'] ?? null)) {
            return $meta['validated_offer_snapshot'];
        }

        return is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : [];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $snapshot
     */
    protected function canAttemptExactRefresh(array $meta, array $snapshot): bool
    {
        if (is_array($meta[SabreSafeRefreshContext::META_KEY] ?? null)) {
            return true;
        }

        return is_array($snapshot['segments'] ?? null) && $snapshot['segments'] !== [];
    }

    /**
     * @param  list<mixed>  $list
     * @return list<string>
     */
    protected function normalizeSegmentStringList(array $list, bool $lowercase = false): array
    {
        $out = [];
        foreach ($list as $item) {
            $value = trim((string) $item);
            if ($value === '') {
                continue;
            }
            $out[] = $lowercase ? strtolower($value) : strtoupper($value);
        }

        return array_values($out);
    }
}
