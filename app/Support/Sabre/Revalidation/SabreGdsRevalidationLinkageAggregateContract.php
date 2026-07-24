<?php

namespace App\Support\Sabre\Revalidation;

/**
 * Authoritative aggregate booleans for Sabre BFM revalidation linkage (HTTP 200 paths).
 * Derives scoped fare-basis completeness and usable linkage from linkage diagnostics
 * and canonical normalization — never from stale failure_category alone.
 */
final class SabreGdsRevalidationLinkageAggregateContract
{
    public const DERIVATION_SOURCE_LINKAGE_AND_CANONICAL = 'linkage_diagnostics_and_canonical_normalization';

    /**
     * @param  array<string, mixed>  $outcome
     * @return array<string, mixed>
     */
    public function normalizeFromOutcome(array $outcome): array
    {
        $linkage = is_array($outcome['response_linkage_diagnostics'] ?? null)
            ? $outcome['response_linkage_diagnostics']
            : [];

        $canonical = $this->resolveCanonicalBlock($outcome, $linkage);
        $linkageDigest = is_array($outcome['linkage_digest'] ?? null) ? $outcome['linkage_digest'] : [];

        return $this->normalize($linkage, $canonical, [
            'fare_basis_complete' => $outcome['fare_basis_complete'] ?? null,
            'usable_fare_linkage' => $outcome['usable_fare_linkage'] ?? null,
            'failure_category' => $outcome['failure_category'] ?? $outcome['revalidation_failure_class'] ?? null,
            'linkage_digest_per_segment_fare_basis_complete' => $linkageDigest['per_segment_fare_basis_complete'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $linkageDiagnostics
     * @param  array<string, mixed>  $canonicalNormalization
     * @param  array<string, mixed>  $priorStale
     * @return array<string, mixed>
     */
    public function normalize(array $linkageDiagnostics, array $canonicalNormalization, array $priorStale = []): array
    {
        $scoped = $this->resolveScopedFareBasisCompleteness($linkageDiagnostics, $canonicalNormalization);
        $overall = $this->resolveOverallFareBasisComplete($scoped, $linkageDiagnostics, $canonicalNormalization);
        $usable = $this->deriveUsableFareLinkage($linkageDiagnostics, $overall);
        $pricingComplete = $this->derivePricingComplete($linkageDiagnostics);

        $priorStaleValues = array_filter([
            'fare_basis_complete' => $priorStale['fare_basis_complete'] ?? null,
            'usable_fare_linkage' => $priorStale['usable_fare_linkage'] ?? null,
            'failure_category' => $priorStale['failure_category'] ?? null,
            'linkage_digest_per_segment_fare_basis_complete' => $priorStale['linkage_digest_per_segment_fare_basis_complete'] ?? null,
        ], static fn ($value) => $value !== null);

        $inputs = array_filter([
            'unique_usable_linkage_match_count' => $linkageDiagnostics['unique_usable_linkage_match_count'] ?? null,
            'ambiguous_linkage_match_count' => $linkageDiagnostics['ambiguous_linkage_match_count'] ?? null,
            'exact_segment_signature_match_count' => $linkageDiagnostics['exact_segment_signature_match_count'] ?? null,
            'exact_itinerary_match_count' => $linkageDiagnostics['exact_itinerary_match_count'] ?? null,
            'pricing_compatible_match_count' => $linkageDiagnostics['pricing_compatible_match_count'] ?? null,
            'fare_basis_compatible_match_count' => $linkageDiagnostics['fare_basis_compatible_match_count'] ?? null,
            'booking_class_compatible_match_count' => $linkageDiagnostics['booking_class_compatible_match_count'] ?? null,
            'pricing_complete' => $pricingComplete,
            'selected_fare_basis_complete' => $scoped['selected_fare_basis_complete'],
            'draft_fare_basis_complete' => $scoped['draft_fare_basis_complete'],
            'candidate_fare_basis_complete' => $scoped['candidate_fare_basis_complete'],
        ], static fn ($value) => $value !== null);

        return [
            'selected_fare_basis_complete' => $scoped['selected_fare_basis_complete'],
            'draft_fare_basis_complete' => $scoped['draft_fare_basis_complete'],
            'candidate_fare_basis_complete' => $scoped['candidate_fare_basis_complete'],
            'overall_fare_basis_complete' => $overall,
            'fare_basis_complete' => $overall,
            'pricing_complete' => $pricingComplete,
            'usable_fare_linkage' => $usable,
            'aggregate_derivation_inputs' => $inputs,
            'aggregate_derivation_predicate' => $usable
                ? 'unique_usable=1 && ambiguous=0 && linkage_counts>=1 && pricing_complete && overall_fare_basis_complete'
                : 'usable_fare_linkage_gate_failed',
            'aggregate_derivation_source' => self::DERIVATION_SOURCE_LINKAGE_AND_CANONICAL,
            'prior_stale_values' => $priorStaleValues !== [] ? $priorStaleValues : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $linkageDiagnostics
     * @param  array<string, mixed>  $canonicalNormalization
     * @return array{selected_fare_basis_complete: ?bool, draft_fare_basis_complete: ?bool, candidate_fare_basis_complete: ?bool}
     */
    public function resolveScopedFareBasisCompleteness(array $linkageDiagnostics, array $canonicalNormalization): array
    {
        $selected = $this->triStateFromCanonicalKey($canonicalNormalization, 'selected_fare_basis_complete');
        $draft = $this->triStateFromCanonicalKey($canonicalNormalization, 'draft_fare_basis_complete');
        $candidate = $this->triStateFromCanonicalKey($canonicalNormalization, 'candidate_fare_basis_complete');

        if ($candidate === null) {
            $ordinal = (string) ($linkageDiagnostics['selected_response_candidate_ordinal'] ?? '');
            $presenceByCandidate = is_array($canonicalNormalization['fare_basis_presence_by_candidate'] ?? null)
                ? $canonicalNormalization['fare_basis_presence_by_candidate']
                : [];
            if ($ordinal !== '' && is_array($presenceByCandidate[$ordinal] ?? null)) {
                $candidate = ($presenceByCandidate[$ordinal]['complete'] ?? false) === true;
            }
        }

        return [
            'selected_fare_basis_complete' => $selected,
            'draft_fare_basis_complete' => $draft,
            'candidate_fare_basis_complete' => $candidate,
        ];
    }

    /**
     * @param  array{selected_fare_basis_complete: ?bool, draft_fare_basis_complete: ?bool, candidate_fare_basis_complete: ?bool}  $scoped
     * @param  array<string, mixed>  $linkageDiagnostics
     */
    public function resolveOverallFareBasisComplete(array $scoped, array $linkageDiagnostics, array $canonicalNormalization = []): ?bool
    {
        $uniqueUsable = (int) ($linkageDiagnostics['unique_usable_linkage_match_count'] ?? 0);

        if ($scoped['selected_fare_basis_complete'] === false
            || $scoped['draft_fare_basis_complete'] === false
            || $scoped['candidate_fare_basis_complete'] === false) {
            return false;
        }

        if ($scoped['selected_fare_basis_complete'] === true
            && $scoped['draft_fare_basis_complete'] === true
            && $scoped['candidate_fare_basis_complete'] === true) {
            return true;
        }

        if ($uniqueUsable === 1
            && (int) ($linkageDiagnostics['fare_basis_compatible_match_count'] ?? 0) >= 1
            && $this->candidateFareBasisPresenceComplete($linkageDiagnostics, $canonicalNormalization)) {
            return true;
        }

        if ($uniqueUsable === 1) {
            foreach ([
                $scoped['selected_fare_basis_complete'],
                $scoped['draft_fare_basis_complete'],
                $scoped['candidate_fare_basis_complete'],
            ] as $value) {
                if ($value === null) {
                    return null;
                }
            }
        }

        if ($scoped['selected_fare_basis_complete'] === true && $scoped['draft_fare_basis_complete'] === true) {
            return $scoped['candidate_fare_basis_complete'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $linkageDiagnostics
     * @param  array<string, mixed>  $canonicalNormalization
     */
    private function candidateFareBasisPresenceComplete(array $linkageDiagnostics, array $canonicalNormalization): bool
    {
        $ordinal = (string) ($linkageDiagnostics['selected_response_candidate_ordinal'] ?? '');
        if ($ordinal === '') {
            return false;
        }

        $presenceByCandidate = is_array($canonicalNormalization['fare_basis_presence_by_candidate'] ?? null)
            ? $canonicalNormalization['fare_basis_presence_by_candidate']
            : [];

        return ($presenceByCandidate[$ordinal]['complete'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $linkageDiagnostics
     */
    public function deriveUsableFareLinkage(array $linkageDiagnostics, ?bool $overallFareBasisComplete): bool
    {
        if ($overallFareBasisComplete !== true) {
            return false;
        }

        if (! $this->hasAuthoritativeLinkageCounts($linkageDiagnostics)) {
            return false;
        }

        return (int) ($linkageDiagnostics['unique_usable_linkage_match_count'] ?? 0) === 1
            && (int) ($linkageDiagnostics['ambiguous_linkage_match_count'] ?? 0) === 0
            && (int) ($linkageDiagnostics['exact_segment_signature_match_count'] ?? 0) >= 1
            && (int) ($linkageDiagnostics['exact_itinerary_match_count'] ?? 0) >= 1
            && (int) ($linkageDiagnostics['pricing_compatible_match_count'] ?? 0) >= 1
            && (int) ($linkageDiagnostics['fare_basis_compatible_match_count'] ?? 0) >= 1
            && (int) ($linkageDiagnostics['booking_class_compatible_match_count'] ?? 0) >= 1
            && $this->derivePricingComplete($linkageDiagnostics) === true;
    }

    /**
     * @param  array<string, mixed>  $linkageDiagnostics
     */
    public function derivePricingComplete(array $linkageDiagnostics): ?bool
    {
        if (($linkageDiagnostics['pricing_complete'] ?? null) === true) {
            return true;
        }

        if ((int) ($linkageDiagnostics['unique_usable_linkage_match_count'] ?? 0) === 1
            && (int) ($linkageDiagnostics['pricing_compatible_match_count'] ?? 0) >= 1) {
            return true;
        }

        if (array_key_exists('pricing_complete', $linkageDiagnostics)) {
            return ($linkageDiagnostics['pricing_complete'] ?? false) === true;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $linkageDiagnostics
     */
    public function hasAuthoritativeLinkageCounts(array $linkageDiagnostics): bool
    {
        foreach ([
            'unique_usable_linkage_match_count',
            'exact_segment_signature_match_count',
            'exact_itinerary_match_count',
        ] as $key) {
            if (! array_key_exists($key, $linkageDiagnostics) || ! is_int($linkageDiagnostics[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     */
    public function segmentsFareBasisComplete(array $segments): bool
    {
        if ($segments === []) {
            return false;
        }

        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                return false;
            }
            if (trim((string) ($segment['fare_basis_code'] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $sequence
     */
    public function fareBasisSequenceComplete(array $sequence): bool
    {
        if ($sequence === []) {
            return false;
        }

        foreach ($sequence as $value) {
            if (trim((string) $value) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $canonical
     */
    private function triStateFromCanonicalKey(array $canonical, string $key): ?bool
    {
        if (! array_key_exists($key, $canonical)) {
            return null;
        }

        return ($canonical[$key] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>  $linkage
     * @return array<string, mixed>
     */
    private function resolveCanonicalBlock(array $outcome, array $linkage): array
    {
        if (is_array($linkage['canonical_linkage_normalization'] ?? null)) {
            return $linkage['canonical_linkage_normalization'];
        }

        if (is_array($outcome['canonical_linkage_normalization'] ?? null)) {
            return $outcome['canonical_linkage_normalization'];
        }

        $key = SabreGdsRevalidationCanonicalSignatureRuntimePropagation::CANONICAL_LINKAGE_NORMALIZATION_DIAGNOSTICS_KEY;

        return is_array($outcome[$key] ?? null) ? $outcome[$key] : [];
    }
}
