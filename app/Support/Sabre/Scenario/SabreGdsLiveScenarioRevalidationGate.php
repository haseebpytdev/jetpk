<?php

namespace App\Support\Sabre\Scenario;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\Gds\SabreGdsRevalidationService;
use App\Support\Security\SensitiveDataRedactor;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationCanonicalSignatureRuntimePropagation;
use Illuminate\Support\Facades\Log;

/**
 * Mandatory production revalidation for {@see SabreGdsLiveScenarioRunner} (not context completion or operator approval).
 */
class SabreGdsLiveScenarioRevalidationGate
{
    public const REASON_REVALIDATION_UNAVAILABLE = 'scenario_revalidation_unavailable';

    public const REASON_REVALIDATION_FAILED = 'scenario_revalidation_failed';

    public const REASON_FRESHNESS_NOT_SATISFIED = 'scenario_freshness_not_satisfied';

    public const REASON_FARE_CHANGE_REQUIRES_ACCEPTANCE = 'scenario_fare_change_requires_acceptance';

    public const REASON_DRAFT_INVALID = 'scenario_revalidation_draft_invalid';

    public function __construct(
        private readonly SabreBookingService $sabreBookingService,
        private readonly SabreGdsRevalidationService $revalidationService,
        private readonly SabreGdsLiveScenarioExactOfferEvidence $exactOfferEvidence,
        private readonly SabreGdsLiveScenarioRevalidationOutcomeMapper $outcomeMapper,
        private readonly SabreGdsScenarioCorrelationRegistry $correlationRegistry,
    ) {}

    public function liveRevalidationAvailable(): bool
    {
        return (bool) config('suppliers.sabre.booking_enabled', false)
            && (bool) config('suppliers.sabre.booking_live_call_enabled', false);
    }

    /**
     * @param  array<string, mixed>  $offerSnap
     * @param  array{
     *     passenger: array<string, mixed>,
     *     contact: array<string, mixed>
     * }  $passengerBundle
     * @return array<string, mixed>
     */
    public function revalidateSelectedOffer(
        SupplierConnection $connection,
        array $offerSnap,
        array $passengerBundle,
        float $selectedTotal,
        ?int $bookingId = null,
        array $continuity = [],
    ): array {
        $selectedCurrency = is_string($continuity['selected_currency'] ?? null)
            ? strtoupper(trim($continuity['selected_currency']))
            : null;
        $expectedFingerprint = trim((string) ($continuity['expected_fingerprint'] ?? ''));
        $expectedSourceHash = trim((string) ($continuity['expected_source_identifier_hash'] ?? ''));
        $expectedSegmentSignature = trim((string) ($continuity['expected_segment_signature'] ?? ''));
        $continuityEvidence = is_array($continuity['continuity_evidence'] ?? null) ? $continuity['continuity_evidence'] : [];
        $revalidationLinkageReady = ($continuity['revalidation_linkage_ready'] ?? null) === true
            || ($continuityEvidence['revalidation_linkage_ready'] ?? false) === true;
        $mappingContext = $this->buildMappingContext(
            $continuity,
            $continuityEvidence,
            $selectedTotal,
            $selectedCurrency,
            $expectedFingerprint,
            $revalidationLinkageReady,
        );

        if ($expectedSourceHash !== '' || $expectedSegmentSignature !== '' || $expectedFingerprint !== '') {
            $row = is_array($continuity['continuity_row'] ?? null) ? $continuity['continuity_row'] : [];
            $selectedFareFamilyOption = is_array($continuity['selected_fare_family_option'] ?? null)
                ? $continuity['selected_fare_family_option']
                : null;
            $actual = $this->exactOfferEvidence->buildLinkageContext(
                $connection,
                $offerSnap,
                $row,
                $selectedFareFamilyOption,
                is_string($continuity['shop_captured_at'] ?? null) ? (string) $continuity['shop_captured_at'] : null,
            );

            if ($expectedSourceHash !== '' && trim((string) ($actual['source_identifier_hash'] ?? '')) !== $expectedSourceHash) {
                return $this->blockedEvidence(
                    SabreGdsLiveScenarioExactOfferEvidence::REASON_EXACT_OFFER_SOURCE_IDENTIFIER_MISMATCH,
                    true,
                    $selectedTotal > 0 ? $selectedTotal : null,
                    null,
                    false,
                    SabreGdsLiveScenarioExactOfferEvidence::REASON_EXACT_OFFER_SOURCE_IDENTIFIER_MISMATCH,
                    $selectedCurrency,
                    $expectedFingerprint !== '' ? $expectedFingerprint : null,
                    false,
                    $mappingContext,
                );
            }

            if ($expectedSegmentSignature !== '' && trim((string) ($actual['segment_signature'] ?? '')) !== $expectedSegmentSignature) {
                return $this->blockedEvidence(
                    SabreGdsLiveScenarioExactOfferEvidence::REASON_EXACT_OFFER_SEGMENT_SIGNATURE_MISMATCH,
                    true,
                    $selectedTotal > 0 ? $selectedTotal : null,
                    null,
                    false,
                    SabreGdsLiveScenarioExactOfferEvidence::REASON_EXACT_OFFER_SEGMENT_SIGNATURE_MISMATCH,
                    $selectedCurrency,
                    $expectedFingerprint !== '' ? $expectedFingerprint : null,
                    false,
                    $mappingContext,
                );
            }

            $actualFingerprint = trim((string) ($actual['safe_offer_fingerprint'] ?? ''));
            if ($expectedFingerprint !== '' && $actualFingerprint !== '' && $actualFingerprint !== $expectedFingerprint) {
                return $this->blockedEvidence(
                    SabreGdsLiveScenarioExactOfferEvidence::REASON_EXACT_OFFER_FINGERPRINT_MISMATCH,
                    true,
                    $selectedTotal > 0 ? $selectedTotal : null,
                    null,
                    false,
                    SabreGdsLiveScenarioExactOfferEvidence::REASON_EXACT_OFFER_FINGERPRINT_MISMATCH,
                    $selectedCurrency,
                    $expectedFingerprint,
                    false,
                    $mappingContext,
                );
            }
        }

        if (! $revalidationLinkageReady) {
            return $this->blockedEvidence(
                SabreGdsLiveScenarioExactOfferEvidence::REASON_EXACT_OFFER_LINKAGE_UNAVAILABLE,
                true,
                $selectedTotal > 0 ? $selectedTotal : null,
                null,
                false,
                SabreGdsLiveScenarioExactOfferEvidence::REASON_EXACT_OFFER_LINKAGE_UNAVAILABLE,
                $selectedCurrency,
                $expectedFingerprint !== '' ? $expectedFingerprint : null,
                false,
                $mappingContext,
            );
        }

        if (! $this->liveRevalidationAvailable()) {
            return $this->blockedEvidence(
                self::REASON_REVALIDATION_UNAVAILABLE,
                false,
                $selectedTotal,
                null,
                false,
                null,
                $selectedCurrency,
                $expectedFingerprint !== '' ? $expectedFingerprint : null,
                $revalidationLinkageReady,
                $mappingContext,
            );
        }

        $offerSnap['supplier_connection_id'] = $connection->id;
        $gate = $this->sabreBookingService->validateNormalizedSabreOffer($offerSnap);
        if (! $gate->success) {
            return $this->blockedEvidence(
                self::REASON_DRAFT_INVALID,
                true,
                $selectedTotal,
                null,
                false,
                (string) ($gate->safe_context['reason'] ?? 'offer_validation_failed'),
                $selectedCurrency,
                $expectedFingerprint !== '' ? $expectedFingerprint : null,
                $revalidationLinkageReady,
                $mappingContext,
            );
        }

        $draft = $this->sabreBookingService->prepareBookingPayload($offerSnap, [
            'passengers' => $this->passengersFromBundle($passengerBundle),
        ]);
        if (($draft['_valid'] ?? false) !== true) {
            return $this->blockedEvidence(
                self::REASON_DRAFT_INVALID,
                true,
                $selectedTotal,
                null,
                false,
                (string) ($draft['code'] ?? 'draft_invalid'),
                $selectedCurrency,
                $expectedFingerprint !== '' ? $expectedFingerprint : null,
                $revalidationLinkageReady,
                $mappingContext,
            );
        }

        $apiDraft = $draft;
        unset($apiDraft['_valid']);

        $row = is_array($continuity['continuity_row'] ?? null) ? $continuity['continuity_row'] : [];
        $selectedFareFamilyOption = is_array($continuity['selected_fare_family_option'] ?? null)
            ? $continuity['selected_fare_family_option']
            : null;
        $continuityEvidence = is_array($continuity['continuity_evidence'] ?? null) ? $continuity['continuity_evidence'] : [];
        if ($continuityEvidence === [] && ($continuity['expected_segment_signature'] ?? null) !== null) {
            $continuityEvidence = $this->exactOfferEvidence->buildLinkageContext(
                $connection,
                $offerSnap,
                $row,
                $selectedFareFamilyOption,
                is_string($continuity['shop_captured_at'] ?? null) ? (string) $continuity['shop_captured_at'] : null,
            );
        }
        $apiDraft = app(SabreGdsRevalidationCanonicalSignatureRuntimePropagation::class)->attachRuntimeToDraft(
            $apiDraft,
            $connection,
            $offerSnap,
            $row,
            $selectedFareFamilyOption,
            $continuityEvidence,
        );

        $revalidationCorrelationId = $this->correlationRegistry->startRevalidationCorrelation();
        $mappingContext['revalidation_correlation_id'] = $revalidationCorrelationId;

        Log::info('sabre.scenario.revalidation.started', [
            'connection_id' => $connection->id,
            'revalidation_correlation_id' => $revalidationCorrelationId,
            'selected_offer_fingerprint' => $expectedFingerprint !== '' ? $expectedFingerprint : null,
            'selected_segment_signature_hash' => $mappingContext['selected_segment_signature_hash'] ?? null,
            'selected_route' => $mappingContext['selected_route'] ?? null,
            'selected_segment_count' => $mappingContext['selected_segment_count'] ?? null,
        ]);

        $outcome = $this->revalidationService->revalidateDraft(
            $apiDraft,
            $connection,
            null,
            $bookingId,
            $revalidationCorrelationId,
        );

        return $this->buildEvidenceFromOutcome($outcome, $mappingContext);
    }

    /**
     * @return array<string, mixed>
     */
    public function revalidateForBooking(Booking $booking, SupplierConnection $connection): array
    {
        $selectedTotal = (float) ($booking->selected_fare_total ?? 0);

        if (! $this->liveRevalidationAvailable()) {
            return $this->blockedEvidence(
                self::REASON_REVALIDATION_UNAVAILABLE,
                false,
                $selectedTotal > 0 ? $selectedTotal : null,
                null,
                false,
            );
        }

        $outcome = $this->revalidationService->revalidateForBooking($booking, $connection, true);

        return $this->buildEvidenceFromOutcome($outcome, [
            'selected_total' => $selectedTotal,
        ]);
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    public function shouldProceed(array $evidence): bool
    {
        return ($evidence['freshness_satisfied'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    public function persistOnBooking(Booking $booking, array $evidence): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['sabre_scenario_revalidation'] = SensitiveDataRedactor::redact($evidence);
        $meta['sabre_offer_freshness'] = array_merge(
            is_array($meta['sabre_offer_freshness'] ?? null) ? $meta['sabre_offer_freshness'] : [],
            [
                'satisfied' => ($evidence['freshness_satisfied'] ?? false) === true,
                'source' => 'scenario_revalidation',
                'revalidated_at' => $evidence['revalidation_at'] ?? null,
            ],
        );

        $revalidatedTotal = $evidence['revalidated_total'] ?? null;
        $patch = ['meta' => $meta];
        if (is_numeric($revalidatedTotal) && (float) $revalidatedTotal > 0) {
            $patch['revalidated_fare_total'] = (float) $revalidatedTotal;
        }

        $booking->forceFill($patch)->save();
    }

    /**
     * @param  array{
     *     passenger: array<string, mixed>,
     *     contact: array<string, mixed>
     * }  $passengerBundle
     * @return list<array<string, mixed>>
     */
    protected function passengersFromBundle(array $passengerBundle): array
    {
        $passenger = is_array($passengerBundle['passenger'] ?? null) ? $passengerBundle['passenger'] : [];

        return [[
            'type' => (string) ($passenger['passenger_type'] ?? 'adult'),
            'first_name' => (string) ($passenger['first_name'] ?? ''),
            'last_name' => (string) ($passenger['last_name'] ?? ''),
        ]];
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function buildEvidenceFromOutcome(array $outcome, array $context): array
    {
        return $this->outcomeMapper->mapToScenarioEvidence($outcome, $context);
    }

    /**
     * @param  array<string, mixed>  $continuity
     * @param  array<string, mixed>  $continuityEvidence
     * @return array<string, mixed>
     */
    protected function buildMappingContext(
        array $continuity,
        array $continuityEvidence,
        float $selectedTotal,
        ?string $selectedCurrency,
        ?string $selectedOfferFingerprint,
        bool $revalidationLinkageReady,
    ): array {
        $origin = strtoupper(trim((string) ($continuityEvidence['origin'] ?? $continuity['origin'] ?? '')));
        $destination = strtoupper(trim((string) ($continuityEvidence['destination'] ?? $continuity['destination'] ?? '')));
        $selectedRoute = $origin !== '' && $destination !== '' ? $origin.'-'.$destination : null;

        return array_filter([
            'selected_total' => $selectedTotal,
            'selected_currency' => $selectedCurrency,
            'selected_offer_fingerprint' => $selectedOfferFingerprint,
            'revalidation_linkage_ready' => $revalidationLinkageReady,
            'offer_source' => is_string($continuity['offer_source'] ?? null) ? (string) $continuity['offer_source'] : null,
            'shop_captured_at' => is_string($continuity['shop_captured_at'] ?? null) ? (string) $continuity['shop_captured_at'] : null,
            'selected_segment_signature_hash' => $continuityEvidence['segment_signature'] ?? $continuity['expected_segment_signature'] ?? null,
            'selected_source_identifier_hash' => $continuityEvidence['source_identifier_hash'] ?? $continuity['expected_source_identifier_hash'] ?? null,
            'selected_route' => $selectedRoute,
            'selected_segment_count' => $continuityEvidence['segment_count'] ?? null,
            'scenario_search_correlation_id' => $this->correlationRegistry->searchCorrelationId(),
        ], static fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $mappingContext
     * @return array<string, mixed>
     */
    protected function blockedEvidence(
        string $blockReason,
        bool $attempted,
        ?float $selectedTotal,
        ?float $revalidatedTotal,
        bool $fareChanged,
        ?string $reasonCode = null,
        ?string $selectedCurrency = null,
        ?string $selectedOfferFingerprint = null,
        ?bool $revalidationLinkageReady = null,
        array $mappingContext = [],
    ): array {
        return $this->outcomeMapper->mapBlockedEvidence(array_merge($mappingContext, array_filter([
            'block_reason' => $blockReason,
            'attempted' => $attempted,
            'selected_total' => $selectedTotal,
            'revalidated_total' => $revalidatedTotal,
            'fare_changed' => $fareChanged,
            'reason_code' => $reasonCode ?? $blockReason,
            'selected_currency' => $selectedCurrency,
            'selected_offer_fingerprint' => $selectedOfferFingerprint,
            'revalidation_linkage_ready' => $revalidationLinkageReady,
        ], static fn ($value) => $value !== null)));
    }
}
