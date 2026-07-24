<?php

namespace App\Support\Sabre\Scenario;

use App\Models\SupplierConnection;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationCanonicalSignatureRuntimePropagation;

/**
 * Builds {@see SabreGdsAuthoritativeRevalidatedBookingContext} from successful scenario revalidation evidence.
 */
final class SabreGdsAuthoritativeRevalidatedBookingContextBuilder
{
    /**
     * @param  array<string, mixed>  $shoppingOfferSnap
     * @param  array<string, mixed>  $revalidationEvidence
     * @param  array<string, mixed>  $continuityEvidence
     * @param  array{
     *     passenger: array<string, mixed>,
     *     contact: array<string, mixed>
     * }  $passengerBundle
     */
    public function build(
        SupplierConnection $connection,
        array $shoppingOfferSnap,
        array $revalidationEvidence,
        array $continuityEvidence,
        array $passengerBundle,
        ?string $lifecycleRunId = null,
    ): SabreGdsAuthoritativeRevalidatedBookingContext {
        $snap = $this->mergeAuthoritativePricingOntoSnapshot($shoppingOfferSnap, $revalidationEvidence, $continuityEvidence);
        $snap['supplier_provider'] = $snap['supplier_provider'] ?? 'sabre';
        $snap['supplier_connection_id'] = $connection->id;

        $segments = is_array($snap['segments'] ?? null) ? array_values($snap['segments']) : [];
        $bookingClasses = $this->segmentFieldList($segments, 'booking_class');
        $fareBasis = $this->segmentFieldList($segments, 'fare_basis_code');

        $diagnostics = is_array($revalidationEvidence['revalidation_diagnostics'] ?? null)
            ? $revalidationEvidence['revalidation_diagnostics']
            : [];
        $canonical = is_array($revalidationEvidence[SabreGdsRevalidationCanonicalSignatureRuntimePropagation::CANONICAL_LINKAGE_NORMALIZATION_DIAGNOSTICS_KEY] ?? null)
            ? $revalidationEvidence[SabreGdsRevalidationCanonicalSignatureRuntimePropagation::CANONICAL_LINKAGE_NORMALIZATION_DIAGNOSTICS_KEY]
            : (is_array($diagnostics[SabreGdsRevalidationCanonicalSignatureRuntimePropagation::CANONICAL_LINKAGE_NORMALIZATION_DIAGNOSTICS_KEY] ?? null)
                ? $diagnostics[SabreGdsRevalidationCanonicalSignatureRuntimePropagation::CANONICAL_LINKAGE_NORMALIZATION_DIAGNOSTICS_KEY]
                : []);

        $selectedFingerprint = trim((string) ($continuityEvidence['safe_offer_fingerprint'] ?? $revalidationEvidence['selected_offer_fingerprint'] ?? ''));
        $revalidatedFingerprint = trim((string) ($revalidationEvidence['revalidated_offer_fingerprint'] ?? $selectedFingerprint));
        $transition = $this->classifyIdentifierTransition($selectedFingerprint, $revalidatedFingerprint, $canonical);

        $revalidatedTotal = (float) ($revalidationEvidence['revalidated_total'] ?? 0);
        $currency = strtoupper(trim((string) (
            $revalidationEvidence['revalidated_currency']
            ?? $revalidationEvidence['selected_currency']
            ?? $continuityEvidence['currency']
            ?? ''
        )));

        $safe = array_filter([
            'authoritative_context_built' => true,
            'authoritative_context_source' => SabreGdsAuthoritativeRevalidatedBookingContext::SOURCE_REVALIDATION_EVIDENCE,
            'authoritative_candidate_ordinal' => (int) ($diagnostics['selected_response_candidate_ordinal']
                ?? $revalidationEvidence['selected_response_candidate_ordinal']
                ?? 0) ?: null,
            'canonical_segment_signature' => $canonical['selected_segment_signature_digest']
                ?? $continuityEvidence['segment_signature']
                ?? null,
            'segment_order_digest' => $canonical['selected_segment_order_digest'] ?? null,
            'selected_draft_signature_equal' => array_key_exists('selected_draft_signature_equal', $canonical)
                ? (bool) $canonical['selected_draft_signature_equal']
                : null,
            'selected_offer_fingerprint' => $selectedFingerprint !== '' ? $selectedFingerprint : null,
            'revalidated_offer_fingerprint' => $revalidatedFingerprint !== '' ? $revalidatedFingerprint : null,
            'shopping_to_revalidation_identifier_transition' => $transition,
            'source_identifier_hash' => $continuityEvidence['source_identifier_hash'] ?? null,
            'booking_classes_by_segment' => $this->nonEmptyList($bookingClasses) ? $bookingClasses : null,
            'fare_basis_codes_by_segment' => $this->nonEmptyList($fareBasis) ? $fareBasis : null,
            'fare_basis_present_all_segments' => $this->nonEmptyList($fareBasis),
            'fare_basis_complete' => ($diagnostics['fare_basis_complete'] ?? $revalidationEvidence['fare_basis_complete'] ?? null) === true,
            'pricing_amount' => $revalidatedTotal > 0 ? $revalidatedTotal : (float) ($revalidationEvidence['selected_total'] ?? 0),
            'pricing_currency' => $currency !== '' ? $currency : null,
            'supplier_connection_id' => $connection->id,
            'search_correlation_id' => $revalidationEvidence['scenario_search_correlation_id'] ?? null,
            'revalidation_correlation_id' => $revalidationEvidence['revalidation_correlation_id'] ?? null,
            'freshness_timestamp' => $revalidationEvidence['revalidation_at'] ?? now()->toIso8601String(),
            'lifecycle_run_id' => $lifecycleRunId,
            'segment_count' => count($segments),
            'ready_for_booking_payload' => $this->nonEmptyList($bookingClasses) && $this->nonEmptyList($fareBasis),
            'revalidation_success' => ($revalidationEvidence['revalidation_success'] ?? false) === true,
            'freshness_satisfied' => ($revalidationEvidence['freshness_satisfied'] ?? false) === true,
            'unique_usable_linkage_match_count' => (int) ($diagnostics['unique_usable_linkage_match_count'] ?? 0),
            'ambiguous_linkage_match_count' => (int) ($diagnostics['ambiguous_linkage_match_count'] ?? 0),
            'pricing_complete' => ($diagnostics['pricing_complete'] ?? null) === true,
            'usable_fare_linkage' => ($diagnostics['usable_fare_linkage'] ?? null) === true,
            'passenger_context_valid' => $this->passengerBundleValid($passengerBundle),
        ], static fn ($v) => $v !== null);

        return new SabreGdsAuthoritativeRevalidatedBookingContext($snap, $safe);
    }

    /**
     * @param  array<string, mixed>  $shoppingOfferSnap
     * @param  array<string, mixed>  $revalidationEvidence
     * @param  array<string, mixed>  $continuityEvidence
     * @return array<string, mixed>
     */
    protected function mergeAuthoritativePricingOntoSnapshot(
        array $shoppingOfferSnap,
        array $revalidationEvidence,
        array $continuityEvidence,
    ): array {
        $snap = $shoppingOfferSnap;
        $total = (float) ($revalidationEvidence['revalidated_total'] ?? $revalidationEvidence['selected_total'] ?? 0);
        $currency = strtoupper(trim((string) (
            $revalidationEvidence['revalidated_currency']
            ?? $revalidationEvidence['selected_currency']
            ?? $continuityEvidence['currency']
            ?? ''
        )));

        if ($total > 0) {
            $fare = is_array($snap['fare_breakdown'] ?? null) ? $snap['fare_breakdown'] : [];
            $fare['supplier_total'] = $total;
            $fare['currency'] = $currency !== '' ? $currency : ($fare['currency'] ?? null);
            $snap['fare_breakdown'] = $fare;
            $snap['final_customer_price'] = $total;
            $snap['pricing_currency'] = $currency !== '' ? $currency : ($snap['pricing_currency'] ?? null);
        }

        $canonical = is_array($revalidationEvidence['canonical_linkage_normalization'] ?? null)
            ? $revalidationEvidence['canonical_linkage_normalization']
            : [];

        $draftClasses = is_array($canonical['draft_segment_component_summaries'] ?? null)
            ? $canonical['draft_segment_component_summaries']
            : [];
        $segments = is_array($snap['segments'] ?? null) ? array_values($snap['segments']) : [];
        foreach ($segments as $index => $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $summary = is_array($draftClasses[$index] ?? null) ? $draftClasses[$index] : [];
            $bookingClass = strtoupper(trim((string) ($summary['booking_class'] ?? $segment['booking_class'] ?? '')));
            $fareBasis = strtoupper(trim((string) ($summary['fare_basis_code'] ?? $segment['fare_basis_code'] ?? '')));
            if ($bookingClass !== '') {
                $segments[$index]['booking_class'] = $bookingClass;
            }
            if ($fareBasis !== '') {
                $segments[$index]['fare_basis_code'] = $fareBasis;
            }
        }
        if ($segments !== []) {
            $snap['segments'] = $segments;
        }

        return $snap;
    }

    /**
     * @param  array<string, mixed>  $canonical
     */
    protected function classifyIdentifierTransition(string $selectedFingerprint, string $revalidatedFingerprint, array $canonical): string
    {
        if ($selectedFingerprint === '' || $revalidatedFingerprint === '') {
            return SabreGdsAuthoritativeRevalidatedBookingContext::TRANSITION_ACCEPTED;
        }
        if (hash_equals($selectedFingerprint, $revalidatedFingerprint)) {
            return SabreGdsAuthoritativeRevalidatedBookingContext::TRANSITION_ACCEPTED;
        }
        $signatureEqual = ($canonical['selected_draft_signature_equal'] ?? null) === true;
        $itineraryIntact = ($canonical['selected_segment_count'] ?? null) === ($canonical['draft_segment_count'] ?? null);

        return ($signatureEqual && $itineraryIntact)
            ? SabreGdsAuthoritativeRevalidatedBookingContext::TRANSITION_ACCEPTED
            : SabreGdsAuthoritativeRevalidatedBookingContext::TRANSITION_REJECTED;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    protected function segmentFieldList(array $segments, string $field): array
    {
        $values = [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                $values[] = '';

                continue;
            }
            $values[] = strtoupper(trim((string) ($segment[$field] ?? '')));
        }

        return $values;
    }

    /**
     * @param  list<string>  $values
     */
    protected function nonEmptyList(array $values): bool
    {
        if ($values === []) {
            return false;
        }
        foreach ($values as $value) {
            if (trim((string) $value) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{
     *     passenger: array<string, mixed>,
     *     contact: array<string, mixed>
     * }  $passengerBundle
     */
    protected function passengerBundleValid(array $passengerBundle): bool
    {
        $passenger = is_array($passengerBundle['passenger'] ?? null) ? $passengerBundle['passenger'] : [];
        $contact = is_array($passengerBundle['contact'] ?? null) ? $passengerBundle['contact'] : [];

        return trim((string) ($passenger['first_name'] ?? '')) !== ''
            && trim((string) ($passenger['last_name'] ?? '')) !== ''
            && trim((string) ($contact['email'] ?? '')) !== '';
    }
}
